<?php

namespace HexaPrWire\Billing\Commerce\Checkout;

use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Settings\SettingsRepository;

/**
 * Checkout is the only place to order. The wp-admin "Place an Order" item opens it
 * in a new tab, and the old Packages and Submit pages redirect to it.
 */
final class OrderEntry {
    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        add_action( 'admin_menu', [ $this, 'register_menu' ], 30 );
        add_action( 'admin_footer', [ $this, 'open_in_new_tab' ] );
        add_action( 'template_redirect', [ $this, 'redirect_legacy_pages' ], 1 );
    }

    public static function checkout_url(): string {
        $product_id = ProductCatalog::product_id( ProductCatalog::STANDARD );
        return $product_id > 0 ? add_query_arg( 'add-to-cart', $product_id, wc_get_checkout_url() ) : wc_get_checkout_url();
    }

    /** A URL menu slug with no callback renders as a direct link. */
    public function register_menu(): void {
        add_menu_page( 'Place an Order', 'Place an Order', 'read', self::checkout_url(), '', 'dashicons-cart', 6 );
    }

    public function open_in_new_tab(): void {
        ?>
        <script>(function(){var a=document.querySelector('#adminmenu a[href=' + JSON.stringify(<?php echo wp_json_encode( self::checkout_url() ); ?>) + ']');if(a){a.target='_blank';a.rel='noopener';}})();</script>
        <?php
    }

    public function redirect_legacy_pages(): void {
        $pages = array_filter( array_map( 'absint', [ SettingsRepository::get( 'packages_page_id', 0 ), SettingsRepository::get( 'submit_page_id', 0 ) ] ) );
        if ( [] !== $pages && is_page( $pages ) ) {
            wp_safe_redirect( self::checkout_url(), 302 );
            exit;
        }
    }
}
