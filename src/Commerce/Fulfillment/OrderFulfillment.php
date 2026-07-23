<?php

namespace HexaPrWire\Billing\Commerce\Fulfillment;

use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Reports\CommerceReport;
use HexaPrWire\Billing\Settings\SettingsRepository;
use HexaPrWire\Billing\Support\Activity;

final class OrderFulfillment {
    private const ORDER_POST_META = '_hpr_billing_fulfillment_post_id';
    private const LOCK_TTL        = 300;

    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! SettingsRepository::feature_enabled( 'fulfillment' ) || ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        add_action( 'woocommerce_order_status_processing', [ $this, 'fulfill' ], 20 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'fulfill' ], 20 );
    }

    public function fulfill( int $order_id ): int|\WP_Error {
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof \WC_Order || ! ProductCatalog::order_has_managed_product( $order ) ) {
            return 0;
        }

        $existing = $this->existing_post_id( $order );
        if ( $existing > 0 ) {
            $this->link_order( $order, $existing, false );
            return $existing;
        }

        $lock = 'hpr_billing_fulfillment_lock_' . $order_id;
        if ( ! $this->acquire_lock( $lock ) ) {
            return new \WP_Error( 'fulfillment_locked', 'Fulfillment is already running for this order.' );
        }

        try {
            $post_id = $this->create_fulfillment_post( $order );
            if ( is_wp_error( $post_id ) ) {
                $order->add_order_note( 'Hexa PR Wire fulfillment failed: ' . $post_id->get_error_message() );
                Activity::add( 'Order fulfillment failed.', 'error', [ 'order_id' => $order_id, 'error' => $post_id->get_error_message() ], 'fulfillment' );
                return $post_id;
            }

            $this->link_order( $order, $post_id, true );
            CommerceReport::clear_cache();
            Activity::add( 'Created a fulfillment draft.', 'success', [ 'order_id' => $order_id, 'post_id' => $post_id ], 'fulfillment' );
            return $post_id;
        } finally {
            delete_option( $lock );
        }
    }

    private function existing_post_id( \WC_Order $order ): int {
        $linked = absint( $order->get_meta( self::ORDER_POST_META ) );
        if ( $linked > 0 && 'trash' !== get_post_status( $linked ) ) {
            return $linked;
        }

        $matches = get_posts(
            [
                'post_type'      => 'post',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    'relation' => 'OR',
                    [ 'key' => 'billing_invoice_id', 'value' => (string) $order->get_id() ],
                    [ 'key' => 'invoice_id', 'value' => (string) $order->get_id() ],
                ],
            ]
        );
        return isset( $matches[0] ) ? absint( $matches[0] ) : 0;
    }

    private function create_fulfillment_post( \WC_Order $order ): int|\WP_Error {
        $kind = '';
        foreach ( $order->get_items() as $item ) {
            $candidate = ProductCatalog::kind_for_product( (int) $item->get_product_id() );
            if ( in_array( $candidate, [ ProductCatalog::STANDARD, ProductCatalog::CUSTOM, ProductCatalog::WRITING ], true ) ) {
                $kind = (string) $candidate;
                break;
            }
        }
        if ( '' === $kind ) {
            return new \WP_Error( 'unsupported_product', 'The order has no fulfillable billing product.' );
        }

        $full_name = trim( $order->get_formatted_billing_full_name() );
        $full_name = '' !== $full_name ? $full_name : 'Customer';
        $article   = sanitize_text_field( (string) $order->get_meta( '_order_press_release_title' ) );
        $service   = sanitize_text_field( (string) $order->get_meta( '_order_service_title' ) );

        if ( ProductCatalog::STANDARD === $kind ) {
            $title    = 'New Post for User ' . $full_name;
            $category = (string) SettingsRepository::get( 'standard_category', 'press-release' );
        } else {
            if ( ProductCatalog::WRITING === $kind && '' === $service ) {
                $service = 'Press Release Writing';
            }
            $title    = implode( ', ', array_filter( [ $full_name, $service, $article ] ) );
            $category = (string) SettingsRepository::get( 'custom_category', 'custom-order' );
        }

        $post_id = wp_insert_post(
            [
                'post_title'   => $title,
                'post_content' => '',
                'post_status'  => 'draft',
                'post_author'  => max( 0, (int) $order->get_customer_id() ),
                'post_type'    => 'post',
            ],
            true
        );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $term = get_category_by_slug( $category );
        if ( $term && ! is_wp_error( $term ) ) {
            wp_set_post_terms( $post_id, [ (int) $term->term_id ], 'category' );
        }

        $this->save_post_linkage( $post_id, $order, $article, $service );
        return $post_id;
    }

    private function save_post_linkage( int $post_id, \WC_Order $order, string $article, string $service ): void {
        $order_id = (string) $order->get_id();
        $user_id  = (int) $order->get_customer_id();

        update_post_meta( $post_id, 'submitted_by', $user_id );
        update_post_meta( $post_id, 'invoice_id', $order_id );
        update_post_meta( $post_id, 'billing_invoice_id', $order_id );
        update_post_meta( $post_id, 'billing_original_title', $article );
        update_post_meta( $post_id, 'billing_service', $service );

        if ( function_exists( 'update_field' ) ) {
            update_field( 'field_651368fa55448', $user_id, $post_id );
            update_field( 'field_65625151e2502', $order_id, $post_id );
            update_field( 'field_hpr_billing_order_invoice_id', $order_id, $post_id );
            update_field( 'field_hpr_billing_order_original_title', $article, $post_id );
            update_field( 'field_hpr_billing_order_service', $service, $post_id );
        }
    }

    private function link_order( \WC_Order $order, int $post_id, bool $created ): void {
        $order->update_meta_data( self::ORDER_POST_META, $post_id );
        $order->save();
        if ( $created ) {
            $order->add_order_note( 'Hexa PR Wire fulfillment draft created: post #' . $post_id . '.' );
        }
    }

    private function acquire_lock( string $key ): bool {
        $now      = time();
        $existing = (int) get_option( $key, 0 );
        if ( $existing > 0 && ( $now - $existing ) > self::LOCK_TTL ) {
            delete_option( $key );
        }

        return add_option( $key, $now, '', 'no' );
    }
}
