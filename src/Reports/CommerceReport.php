<?php

namespace HexaPrWire\Billing\Reports;

use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class CommerceReport {
    private const CACHE_KEY = 'hpr_billing_commerce_report';

    public static function register_invalidation(): void {
        static $registered = false;
        if ( $registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        add_action( 'woocommerce_new_order', [ self::class, 'clear_cache' ] );
        add_action( 'woocommerce_update_order', [ self::class, 'clear_cache' ] );
        add_action( 'woocommerce_order_status_changed', [ self::class, 'clear_cache' ] );
        $registered = true;
    }

    public static function clear_cache(): void {
        delete_transient( self::CACHE_KEY );
    }

    public function summary( bool $fresh = false ): array {
        if ( ! $fresh ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $summary = [
            'orders'              => 0,
            'active_gross'        => 0.0,
            'active_paid_orders'  => 0,
            'zero_total_orders'   => 0,
            'linked_fulfillments' => 0,
            'legacy_links'        => $this->legacy_fulfillment_order_count(),
            'statuses'            => [],
            'payments'            => [],
            'products'            => [],
        ];

        if ( ! function_exists( 'wc_get_orders' ) ) {
            return $summary;
        }

        $statuses = array_keys( wc_get_order_statuses() );
        $ids      = wc_get_orders(
            [
                'type'   => 'shop_order',
                'status' => $statuses,
                'limit'  => -1,
                'return' => 'ids',
                'orderby'=> 'date',
                'order'  => 'DESC',
            ]
        );

        foreach ( $ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order instanceof \WC_Order ) {
                continue;
            }

            $summary['orders']++;
            $status = $order->get_status();
            $method = $order->get_payment_method() ?: 'none';
            $total  = (float) $order->get_total();
            $summary['statuses'][ $status ] = ( $summary['statuses'][ $status ] ?? 0 ) + 1;
            $summary['payments'][ $method ] = ( $summary['payments'][ $method ] ?? 0 ) + 1;

            if ( in_array( $status, [ 'processing', 'completed' ], true ) ) {
                $summary['active_gross'] += $total;
                if ( $total > 0 && 'none' !== $method ) {
                    $summary['active_paid_orders']++;
                }
            }
            if ( $total <= 0 ) {
                $summary['zero_total_orders']++;
            }
            if ( absint( $order->get_meta( '_hpr_billing_fulfillment_post_id' ) ) > 0 ) {
                $summary['linked_fulfillments']++;
            }

            foreach ( $order->get_items() as $item ) {
                $product_id = (int) $item->get_product_id();
                $key        = $product_id . ':' . $item->get_name();
                if ( ! isset( $summary['products'][ $key ] ) ) {
                    $summary['products'][ $key ] = [
                        'product_id' => $product_id,
                        'name'       => $item->get_name(),
                        'quantity'   => 0,
                        'gross'      => 0.0,
                    ];
                }
                $summary['products'][ $key ]['quantity'] += (int) $item->get_quantity();
                $summary['products'][ $key ]['gross']    += (float) $item->get_total();
            }
        }

        ksort( $summary['statuses'] );
        arsort( $summary['payments'] );
        usort( $summary['products'], static fn( array $a, array $b ): int => $b['quantity'] <=> $a['quantity'] );
        $summary['active_gross'] = round( $summary['active_gross'], 2 );
        set_transient( self::CACHE_KEY, $summary, 5 * MINUTE_IN_SECONDS );

        return $summary;
    }

    public function recent_orders( int $limit = 25 ): array {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return [];
        }
        $orders = wc_get_orders(
            [
                'type'    => 'shop_order',
                'status'  => array_keys( wc_get_order_statuses() ),
                'limit'   => max( 1, min( 100, $limit ) ),
                'orderby' => 'date',
                'order'   => 'DESC',
            ]
        );
        $rows = [];
        foreach ( $orders as $order ) {
            if ( ! $order instanceof \WC_Order ) {
                continue;
            }
            $items = [];
            foreach ( $order->get_items() as $item ) {
                $items[] = $item->get_name();
            }
            $post_id = absint( $order->get_meta( '_hpr_billing_fulfillment_post_id' ) );
            $rows[]  = [
                'id'          => $order->get_id(),
                'date'        => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
                'status'      => $order->get_status(),
                'total'       => $order->get_formatted_order_total(),
                'method'      => $order->get_payment_method_title() ?: 'None',
                'customer'    => $order->get_formatted_billing_full_name() ?: 'Customer',
                'items'       => implode( ', ', $items ),
                'post_id'     => $post_id,
                'order_url'   => $order->get_edit_order_url(),
                'post_url'    => $post_id ? get_edit_post_link( $post_id, '' ) : '',
            ];
        }
        return $rows;
    }

    public function pricing_summary(): array {
        global $wpdb;

        $price_rows = $wpdb->get_results(
            "SELECT meta_value AS price, COUNT(DISTINCT user_id) AS users
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'billing_price_standard_release'
             GROUP BY meta_value
             ORDER BY CAST(meta_value AS DECIMAL(12,2))",
            ARRAY_A
        );
        $card_rows = $wpdb->get_results(
            "SELECT meta_value AS allowed, COUNT(DISTINCT user_id) AS users
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'billing_allow_credit_card'
             GROUP BY meta_value",
            ARRAY_A
        );
        $service_rows = $wpdb->get_results(
            "SELECT meta_value AS service_count, COUNT(DISTINCT user_id) AS users
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'billing_custom_services'
             GROUP BY meta_value
             ORDER BY CAST(meta_value AS UNSIGNED)",
            ARRAY_A
        );

        return [
            'prices'   => is_array( $price_rows ) ? $price_rows : [],
            'cards'    => is_array( $card_rows ) ? $card_rows : [],
            'services' => is_array( $service_rows ) ? $service_rows : [],
        ];
    }

    public function product_rows(): array {
        $rows = [];
        foreach ( [ ProductCatalog::STANDARD, ProductCatalog::PREMIUM, ProductCatalog::WRITING, ProductCatalog::CUSTOM ] as $kind ) {
            $id      = ProductCatalog::product_id( $kind );
            $product = $id && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : false;
            $rows[]  = [
                'kind'       => $kind,
                'id'         => $id,
                'exists'     => $product instanceof \WC_Product,
                'name'       => $product instanceof \WC_Product ? $product->get_name() : 'Missing product',
                'status'     => $product instanceof \WC_Product ? $product->get_status() : 'missing',
                'price'      => $product instanceof \WC_Product ? $product->get_price() : '',
                'virtual'    => $product instanceof \WC_Product && $product->is_virtual(),
                'edit_url'   => $id ? get_edit_post_link( $id, '' ) : '',
                'public_url' => $id ? get_permalink( $id ) : '',
            ];
        }
        return $rows;
    }

    public function gateway_rows(): array {
        if ( ! function_exists( 'WC' ) ) {
            return [];
        }
        $woocommerce = WC();
        if ( ! is_object( $woocommerce ) || ! is_callable( [ $woocommerce, 'payment_gateways' ] ) ) {
            return [];
        }
        $payment_gateways = $woocommerce->payment_gateways();
        if ( ! is_object( $payment_gateways ) || ! is_callable( [ $payment_gateways, 'payment_gateways' ] ) ) {
            return [];
        }
        $rows = [];
        foreach ( $payment_gateways->payment_gateways() as $id => $gateway ) {
            if ( ! str_starts_with( (string) $id, 'stripe_' ) && ! in_array( $id, [ 'bacs', 'cheque', 'cod' ], true ) ) {
                continue;
            }
            $rows[] = [
                'id'      => (string) $id,
                'title'   => wp_strip_all_tags( $gateway->get_title() ),
                'enabled' => 'yes' === $gateway->enabled,
                'class'   => get_class( $gateway ),
            ];
        }
        return $rows;
    }

    public function integrity_checks(): array {
        $checks = [];
        $woocommerce_ready = class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
        $checks[] = $this->check( 'Runtime ownership', SettingsRepository::runtime_enabled(), 'The plugin runtime owns commerce hooks.', 'The plugin is installed in observation mode.' );
        $checks[] = $this->check( 'WooCommerce', $woocommerce_ready, 'WooCommerce is active.', 'WooCommerce is missing.' );
        $checks[] = $this->check( 'ACF Pro', function_exists( 'acf_add_local_field_group' ), 'Plugin-owned billing field groups can register.', 'ACF is unavailable; billing values still work through user meta.', 'warn' );

        $checkout_id      = absint( get_option( 'woocommerce_checkout_page_id', SettingsRepository::get( 'checkout_page_id', 0 ) ) );
        $checkout_content = $checkout_id ? (string) get_post_field( 'post_content', $checkout_id ) : '';
        $checks[] = $this->check( 'Canonical checkout fallback', str_contains( $checkout_content, '[woocommerce_checkout]' ), 'The checkout page has a canonical WooCommerce shortcode fallback.', 'The checkout page content is a saved render instead of a canonical shortcode.' );

        $packages_id      = absint( SettingsRepository::get( 'packages_page_id', 0 ) );
        $packages_content = $packages_id ? (string) get_post_field( 'post_content', $packages_id ) : '';
        $checks[] = $this->check( 'Packages page', str_contains( $packages_content, '[hpr_billing_catalog' ), 'The packages page is owned by the billing catalog.', 'The packages page has no billing catalog.' );

        $submit_id      = absint( SettingsRepository::get( 'submit_page_id', 0 ) );
        $submit_content = $submit_id ? (string) get_post_field( 'post_content', $submit_id ) : '';
        $checks[] = $this->check( 'Submit page', str_contains( $submit_content, '[hpr_billing_order_portal' ), 'The submit page routes customers into the account order portal.', 'The submit page does not expose the billing order portal.', 'warn' );

        $active_legacy = array_filter( $this->legacy_snippets(), static fn( array $row ): bool => ! empty( $row['active'] ) );
        $checks[] = $this->check( 'Legacy commerce snippets', [] === $active_legacy, 'Legacy commerce snippets are disabled.', count( $active_legacy ) . ' legacy commerce snippets are still active.' );

        $fcf_active = $this->plugin_active( 'flexible-checkout-fields/flexible-checkout-fields.php' );
        $checks[]   = $this->check( 'Checkout field ownership', ! $fcf_active, 'Checkout fields are owned by this plugin.', 'Flexible Checkout Fields is still active and can conflict with plugin fields.' );

        foreach ( [ ProductCatalog::STANDARD, ProductCatalog::WRITING, ProductCatalog::CUSTOM ] as $kind ) {
            $id = ProductCatalog::product_id( $kind );
            $product_available = $woocommerce_ready && $id > 0 && wc_get_product( $id ) instanceof \WC_Product;
            $checks[] = $this->check( ucfirst( $kind ) . ' product', $product_available, 'Product #' . $id . ' is available.', 'Configured product #' . $id . ' is missing.' );
        }

        $custom_id      = ProductCatalog::product_id( ProductCatalog::CUSTOM );
        $custom_product = $woocommerce_ready && $custom_id > 0 ? wc_get_product( $custom_id ) : false;
        $custom_locked  = $custom_product instanceof \WC_Product && '' === (string) $custom_product->get_price( 'edit' );
        $checks[] = $this->check(
            'Custom carrier fallback price',
            $custom_locked,
            'The custom carrier has no persisted fallback price; entitled prices are injected server-side.',
            'The custom carrier still has a directly purchasable fallback price.'
        );

        $cc  = get_option( 'woocommerce_stripe_cc_settings', [] );
        $upm = get_option( 'woocommerce_stripe_upm_settings', [] );
        $ach = get_option( 'woocommerce_stripe_ach_settings', [] );
        $checks[] = $this->check( 'Stripe card gateway', is_array( $cc ) && 'yes' === ( $cc['enabled'] ?? '' ), 'Stripe card is enabled and controlled per customer.', 'Stripe card is not enabled.', 'warn' );
        $checks[] = $this->check(
            'Stripe ACH gateway',
            ( is_array( $upm ) && 'yes' === ( $upm['enabled'] ?? '' ) ) || ( is_array( $ach ) && 'yes' === ( $ach['enabled'] ?? '' ) ),
            'Stripe ACH is enabled.',
            'Stripe ACH is not enabled.'
        );
        $checks[] = $this->check(
            'Managed payment policy',
            has_filter( 'wc_stripe_get_element_options' ) && has_action( 'woocommerce_after_checkout_validation' ),
            'Managed UPM checkout is constrained to ACH and server-validated.',
            'The ACH-only universal payment policy is not registered.'
        );

        $api = get_option( 'woocommerce_stripe_api_settings', [] );
        $webhook_ready = is_array( $api ) && ! empty( $api['webhook_secret_live'] ) && ! empty( $api['webhook_id_live'] );
        $checks[] = $this->check( 'Stripe live webhook', $webhook_ready, 'A live webhook ID and signing secret are configured.', 'The live Stripe webhook is not fully configured.' );

        $terms_id = absint( get_option( 'woocommerce_terms_page_id', 0 ) );
        $is_terms = $terms_id > 0 && ! str_contains( strtolower( (string) get_the_title( $terms_id ) ), 'privacy' );
        $checks[] = $this->check( 'Checkout terms page', $is_terms, 'A dedicated terms page is assigned.', 'WooCommerce points its terms checkbox to the Privacy Policy page.', 'warn' );

        $hpos = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
        $checks[] = $this->check( 'High-performance order storage', $hpos, 'HPOS is enabled.', 'HPOS is currently disabled; the plugin is compatible when it is enabled.', 'warn' );

        return $checks;
    }

    public function legacy_snippets(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'snippets';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return [];
        }
        $rows = $wpdb->get_results( "SELECT id, name, active FROM {$table} WHERE id IN (26,30,34,43,44) ORDER BY id", ARRAY_A );
        return is_array( $rows ) ? $rows : [];
    }

    private function legacy_fulfillment_order_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT meta_value)
             FROM {$wpdb->postmeta}
             WHERE meta_key IN ('billing_invoice_id','invoice_id')
               AND meta_value REGEXP '^[0-9]+$'"
        );
    }

    private function check( string $label, bool $passed, string $pass_message, string $fail_message, string $failure_level = 'fail' ): array {
        return [
            'label'   => $label,
            'status'  => $passed ? 'pass' : $failure_level,
            'message' => $passed ? $pass_message : $fail_message,
        ];
    }

    private function plugin_active( string $plugin_file ): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active( $plugin_file );
    }
}
