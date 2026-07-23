<?php

namespace HexaPrWire\Billing\Commerce\Catalog;

use HexaPrWire\Billing\Commerce\Pricing\CustomerPricingRepository;
use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Config;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class CatalogShortcode {
    private CustomerPricingRepository $pricing;

    public function __construct() {
        $this->pricing = new CustomerPricingRepository();
    }

    public function register(): void {
        add_shortcode( 'hpr_billing_catalog', [ $this, 'render_catalog' ] );
        add_shortcode( 'hpr_billing_order_portal', [ $this, 'render_order_portal' ] );
    }

    public function render_catalog( array $attributes = [] ): string {
        unset( $attributes );
        if ( ! SettingsRepository::runtime_enabled() ) {
            return current_user_can( 'manage_options' )
                ? '<div class="woocommerce-info">' . esc_html__( 'Hexa PR Wire Billing is installed but its runtime migration has not been enabled.', 'hexa-pr-wire-billing' ) . '</div>'
                : '';
        }
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '<div class="woocommerce-error">' . esc_html__( 'Checkout is temporarily unavailable.', 'hexa-pr-wire-billing' ) . '</div>';
        }

        $this->enqueue_assets();
        $user_id = get_current_user_id();

        ob_start();
        ?>
        <section class="hpr-billing-catalog" aria-label="<?php echo esc_attr__( 'Hexa PR Wire services', 'hexa-pr-wire-billing' ); ?>">
            <header class="hpr-billing-catalog__header">
                <p class="hpr-billing-catalog__eyebrow"><?php echo esc_html__( 'Hexa PR Wire', 'hexa-pr-wire-billing' ); ?></p>
                <h2><?php echo esc_html__( 'Press release services', 'hexa-pr-wire-billing' ); ?></h2>
            </header>
            <div class="hpr-billing-catalog__grid">
                <?php $this->render_product_card( ProductCatalog::STANDARD, $this->pricing->resolved_standard_price( $user_id ), true ); ?>
                <?php $this->render_product_card( ProductCatalog::WRITING, $this->pricing->product_price( ProductCatalog::product_id( ProductCatalog::WRITING ) ), true ); ?>
                <?php $this->render_product_card( ProductCatalog::PREMIUM, null, false ); ?>
            </div>
            <?php $this->render_custom_services( $user_id ); ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public function render_order_portal( array $attributes = [] ): string {
        unset( $attributes );
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
            return '<div class="woocommerce-error">' . esc_html__( 'Checkout is temporarily unavailable.', 'hexa-pr-wire-billing' ) . '</div>';
        }
        if ( ! is_user_logged_in() ) {
            $login = wc_get_page_permalink( 'myaccount' );
            return '<div class="woocommerce-info">' . esc_html__( 'Sign in to view account pricing and custom services.', 'hexa-pr-wire-billing' ) . ' <a href="' . esc_url( $login ) . '">' . esc_html__( 'Sign in', 'hexa-pr-wire-billing' ) . '</a></div>';
        }
        return $this->render_catalog();
    }

    private function render_product_card( string $kind, ?string $price, bool $purchasable ): void {
        $product_id = ProductCatalog::product_id( $kind );
        $product    = $product_id ? wc_get_product( $product_id ) : false;
        if ( ! $product instanceof \WC_Product || 'publish' !== $product->get_status() ) {
            return;
        }

        $descriptions = [
            ProductCatalog::STANDARD => __( 'Distribution for one press release through the standard Hexa PR Wire network.', 'hexa-pr-wire-billing' ),
            ProductCatalog::WRITING  => __( 'Professional drafting for one press release. Distribution is purchased separately.', 'hexa-pr-wire-billing' ),
            ProductCatalog::PREMIUM  => __( 'Expanded distribution with premium publication placements.', 'hexa-pr-wire-billing' ),
        ];
        ?>
        <article class="hpr-billing-service">
            <div class="hpr-billing-service__body">
                <h3><?php echo esc_html( $product->get_name() ); ?></h3>
                <p><?php echo esc_html( $descriptions[ $kind ] ?? '' ); ?></p>
            </div>
            <div class="hpr-billing-service__action">
                <?php if ( $purchasable && null !== $price ) : ?>
                    <strong class="hpr-billing-service__price"><?php echo wp_kses_post( wc_price( $price ) ); ?></strong>
                    <a class="button hpr-billing-button" href="<?php echo esc_url( $this->checkout_url( $product_id ) ); ?>"><?php echo esc_html__( 'Continue to checkout', 'hexa-pr-wire-billing' ); ?></a>
                <?php else : ?>
                    <strong class="hpr-billing-service__price"><?php echo esc_html__( 'Custom quote', 'hexa-pr-wire-billing' ); ?></strong>
                    <a class="button hpr-billing-button hpr-billing-button--secondary" href="mailto:<?php echo esc_attr( (string) SettingsRepository::get( 'support_email' ) ); ?>"><?php echo esc_html__( 'Contact sales', 'hexa-pr-wire-billing' ); ?></a>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    private function render_custom_services( int $user_id ): void {
        if ( $user_id <= 0 ) {
            $login = wc_get_page_permalink( 'myaccount' );
            echo '<p class="hpr-billing-catalog__account">' . esc_html__( 'Account-specific pricing and custom services are available after sign-in.', 'hexa-pr-wire-billing' ) . ' <a href="' . esc_url( $login ) . '">' . esc_html__( 'Sign in', 'hexa-pr-wire-billing' ) . '</a></p>';
            return;
        }

        $services = $this->pricing->custom_services( $user_id );
        if ( [] === $services ) {
            return;
        }
        ?>
        <section class="hpr-billing-custom-services">
            <div class="hpr-billing-custom-services__heading">
                <p class="hpr-billing-catalog__eyebrow"><?php echo esc_html__( 'Your account', 'hexa-pr-wire-billing' ); ?></p>
                <h3><?php echo esc_html__( 'Custom services', 'hexa-pr-wire-billing' ); ?></h3>
            </div>
            <div class="hpr-billing-custom-services__rows">
                <?php foreach ( $services as $service ) : ?>
                    <div class="hpr-billing-custom-service">
                        <strong><?php echo esc_html( $service['name'] ); ?></strong>
                        <span><?php echo wp_kses_post( wc_price( $service['price'] ) ); ?></span>
                        <a class="button hpr-billing-button hpr-billing-button--small" href="<?php echo esc_url( $this->checkout_url( ProductCatalog::product_id( ProductCatalog::CUSTOM ), [ 'service' => $service['key'] ] ) ); ?>"><?php echo esc_html__( 'Order', 'hexa-pr-wire-billing' ); ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private function checkout_url( int $product_id, array $arguments = [] ): string {
        return add_query_arg( array_merge( [ 'add-to-cart' => $product_id ], $arguments ), wc_get_checkout_url() );
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'hpr-billing-catalog',
            plugins_url( 'assets/frontend/catalog.css', dirname( __DIR__, 3 ) . '/hexa-pr-wire-billing.php' ),
            [],
            Config::VERSION
        );
    }
}
