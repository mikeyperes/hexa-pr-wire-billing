<?php

namespace HexaPrWire\Billing\Commerce\Payments;

use HexaPrWire\Billing\Commerce\Pricing\CustomerPricingRepository;
use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class GatewayPolicy {
    private const ACH_GATEWAYS = [ 'stripe_upm', 'stripe_ach' ];
    private const CARD_GATEWAY = 'stripe_cc';
    private const UPM_ACH_TYPE = 'us_bank_account';

    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! SettingsRepository::feature_enabled( 'gateway_policy' ) || ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_gateways' ], 100 );
        add_filter( 'wc_stripe_get_element_options', [ $this, 'filter_stripe_element_options' ], 100, 2 );
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_submitted_gateway' ], 30, 2 );
    }

    public function filter_gateways( array $gateways ): array {
        [ $managed, $user_id ] = $this->checkout_context();
        if ( ! $managed ) {
            return $gateways;
        }

        $allowed = self::ACH_GATEWAYS;
        $pricing = new CustomerPricingRepository();
        if ( $pricing->card_allowed( $user_id ) ) {
            $allowed[] = self::CARD_GATEWAY;
        }

        foreach ( array_keys( $gateways ) as $gateway_id ) {
            if ( ! in_array( (string) $gateway_id, $allowed, true ) ) {
                unset( $gateways[ $gateway_id ] );
            }
        }

        return $gateways;
    }

    public function filter_stripe_element_options( array $options, mixed $gateway ): array {
        [ $managed ] = $this->checkout_context();
        $gateway_id  = is_object( $gateway ) && isset( $gateway->id ) ? (string) $gateway->id : '';
        if ( ! $managed || 'stripe_upm' !== $gateway_id ) {
            return $options;
        }

        unset( $options['paymentMethodConfiguration'] );
        $options['paymentMethodTypes'] = [ self::UPM_ACH_TYPE ];
        return $options;
    }

    public function validate_submitted_gateway( array $data, \WP_Error $errors ): void {
        [ $managed, $user_id ] = $this->checkout_context();
        if ( ! $managed ) {
            return;
        }

        $gateway = sanitize_key( (string) ( $data['payment_method'] ?? '' ) );
        if ( '' === $gateway ) {
            return;
        }

        $pricing = new CustomerPricingRepository();
        if ( self::CARD_GATEWAY === $gateway && ! $pricing->card_allowed( $user_id ) ) {
            $errors->add( 'hpr_billing_card_not_allowed', __( 'Card payment is not enabled for this account.', 'hexa-pr-wire-billing' ) );
            return;
        }

        if ( 'stripe_upm' === $gateway ) {
            $type = isset( $_POST['_stripe_payment_method_type'] ) && is_scalar( $_POST['_stripe_payment_method_type'] )
                ? sanitize_key( wp_unslash( (string) $_POST['_stripe_payment_method_type'] ) )
                : '';
            if ( self::UPM_ACH_TYPE !== $type ) {
                $errors->add( 'hpr_billing_ach_required', __( 'Select ACH bank payment for this order.', 'hexa-pr-wire-billing' ) );
            }
        }
    }

    /** @return array{0:bool,1:int} */
    private function checkout_context(): array {
        $user_id = get_current_user_id();
        $managed = ProductCatalog::cart_has_managed_product();

        if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
            $order_id = absint( get_query_var( 'order-pay' ) );
            $order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
            if ( $order instanceof \WC_Order ) {
                $managed = ProductCatalog::order_has_managed_product( $order );
                $user_id = (int) $order->get_customer_id();
            }
        }

        return [ $managed, $user_id ];
    }
}
