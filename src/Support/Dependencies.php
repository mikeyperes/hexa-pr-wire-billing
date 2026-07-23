<?php

namespace HexaPrWire\Billing\Support;

use HexaPrWire\Billing\Config;

final class Dependencies {
    public static function woocommerce_active(): bool {
        return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' );
    }

    public static function acf_active(): bool {
        return function_exists( 'acf_add_local_field_group' );
    }

    public function register(): void {
        add_action( 'admin_notices', [ $this, 'render_notices' ] );
    }

    public function render_notices(): void {
        if ( ! current_user_can( Config::$settings_page_capability ) ) {
            return;
        }

        if ( ! self::woocommerce_active() ) {
            echo '<div class="notice notice-error"><p><strong>'
                . esc_html__( 'Hexa PR Wire Billing requires WooCommerce.', 'hexa-pr-wire-billing' )
                . '</strong> '
                . esc_html__( 'Its commerce runtime is not registered while WooCommerce is unavailable.', 'hexa-pr-wire-billing' )
                . '</p></div>';
        }

        if ( ! self::acf_active() ) {
            echo '<div class="notice notice-warning"><p><strong>'
                . esc_html__( 'Hexa PR Wire Billing: ACF Pro is unavailable.', 'hexa-pr-wire-billing' )
                . '</strong> '
                . esc_html__( 'Existing metadata remains readable, but the managed customer pricing field interface is not registered.', 'hexa-pr-wire-billing' )
                . '</p></div>';
        }
    }
}

