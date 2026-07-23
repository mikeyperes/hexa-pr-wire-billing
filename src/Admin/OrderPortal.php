<?php

namespace HexaPrWire\Billing\Admin;

use HexaPrWire\Billing\Commerce\Catalog\CatalogShortcode;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class OrderPortal {
    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! SettingsRepository::feature_enabled( 'order_portal' ) ) {
            return;
        }
        add_action( 'admin_menu', [ $this, 'register_menu' ], 30 );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Place an Order', 'hexa-pr-wire-billing' ),
            __( 'Place an Order', 'hexa-pr-wire-billing' ),
            'read',
            'hpr-place-order',
            [ $this, 'render' ],
            'dashicons-cart',
            6
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'hexa-pr-wire-billing' ) );
        }
        echo '<div class="wrap hpr-order-portal"><h1>' . esc_html__( 'Place an Order', 'hexa-pr-wire-billing' ) . '</h1>';
        echo '<p><strong>' . esc_html__( 'Support', 'hexa-pr-wire-billing' ) . ':</strong> <a href="mailto:' . esc_attr( (string) SettingsRepository::get( 'support_email' ) ) . '">' . esc_html( (string) SettingsRepository::get( 'support_email' ) ) . '</a></p>';
        echo ( new CatalogShortcode() )->render_order_portal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }
}

