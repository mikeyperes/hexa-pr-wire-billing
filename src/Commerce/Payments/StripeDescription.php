<?php

namespace HexaPrWire\Billing\Commerce\Payments;

use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class StripeDescription {
    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! SettingsRepository::feature_enabled( 'stripe_descriptions' ) ) {
            return;
        }
        add_filter( 'wc_stripe_payment_intent_args', [ $this, 'filter_intent_args' ], 50, 2 );
    }

    public function filter_intent_args( array $args, \WC_Order $order ): array {
        if ( ! ProductCatalog::order_has_managed_product( $order ) ) {
            return $args;
        }

        $kind = '';
        foreach ( $order->get_items() as $item ) {
            $kind = ProductCatalog::kind_for_product( (int) $item->get_product_id() ) ?? '';
            if ( '' !== $kind ) {
                break;
            }
        }

        $labels = [
            ProductCatalog::STANDARD => 'Standard Press Release',
            ProductCatalog::WRITING  => 'Press Release Writing',
            ProductCatalog::CUSTOM   => 'Custom Product',
        ];
        $parts = [ 'HPR', $labels[ $kind ] ?? 'Order' ];

        $service = sanitize_text_field( (string) $order->get_meta( '_order_service_title' ) );
        $title   = sanitize_text_field( (string) $order->get_meta( '_order_press_release_title' ) );
        if ( '' !== $service ) {
            $parts[] = $service;
        }
        if ( '' !== $title ) {
            $parts[] = $title;
        }

        $name = trim( $order->get_formatted_billing_full_name() );
        if ( '' !== $name ) {
            $parts[] = $name;
        }
        $parts[] = 'Order ' . $order->get_id();
        $parts[] = sanitize_email( $order->get_billing_email() );

        $description         = implode( ' | ', array_filter( $parts ) );
        $args['description'] = function_exists( 'mb_substr' ) ? mb_substr( $description, 0, 450 ) : substr( $description, 0, 450 );
        return $args;
    }
}

