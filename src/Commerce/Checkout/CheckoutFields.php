<?php

namespace HexaPrWire\Billing\Commerce\Checkout;

use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class CheckoutFields {
    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! SettingsRepository::feature_enabled( 'checkout_fields' ) || ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_filter( 'woocommerce_checkout_fields', [ $this, 'filter_fields' ], 1000 );
        add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'render_order_card' ], 5 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_fields' ], 20, 2 );
        add_action( 'woocommerce_checkout_create_order', [ $this, 'save_order_fields' ], 20, 2 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'render_admin_order_fields' ] );
        add_filter( 'woocommerce_email_order_meta_fields', [ $this, 'email_order_fields' ], 20, 3 );
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'render_customer_order_fields' ] );
    }

    public function filter_fields( array $fields ): array {
        if ( ! ProductCatalog::cart_has_managed_product() ) {
            return $fields;
        }

        $allowed_billing = [ 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_company' ];
        foreach ( array_keys( $fields['billing'] ?? [] ) as $key ) {
            if ( ! in_array( $key, $allowed_billing, true ) ) {
                unset( $fields['billing'][ $key ] );
            }
        }

        foreach ( [ 'billing_first_name', 'billing_last_name' ] as $key ) {
            if ( isset( $fields['billing'][ $key ] ) ) {
                $fields['billing'][ $key ]['class'] = [ 'form-row-wide' ];
            }
        }

        if ( isset( $fields['billing']['billing_email'] ) ) {
            $fields['billing']['billing_email']['label']       = __( 'Email address', 'hexa-pr-wire-billing' );
            $fields['billing']['billing_email']['description'] = __( 'Use the email attached to your Hexa PR Wire account when applicable.', 'hexa-pr-wire-billing' );
        }
        $fields['shipping'] = [];

        $comments = $fields['order']['order_comments'] ?? [
            'type'     => 'textarea',
            'label'    => __( 'Order notes', 'hexa-pr-wire-billing' ),
            'required' => false,
            'priority' => 30,
        ];
        $fields['order'] = [
            'order_press_release_title' => [
                'type'        => 'text',
                'label'       => __( 'Article title', 'hexa-pr-wire-billing' ),
                'description' => __( 'A rough title is enough. You can also enter the company or entity being featured.', 'hexa-pr-wire-billing' ),
                'required'    => true,
                'class'       => [ 'form-row-wide' ],
                'priority'    => 20,
            ],
            'order_comments' => $comments,
        ];

        return $fields;
    }

    /** One-page checkout: what is being bought, its price and what happens after payment, above the form. */
    public function render_order_card( mixed $checkout = null ): void {
        unset( $checkout );
        if ( ! ProductCatalog::cart_has_managed_product() ) {
            return;
        }
        $items = WC()->cart->get_cart();
        ?>
        <section class="hpr-checkout-order" aria-label="<?php echo esc_attr__( 'Your order', 'hexa-pr-wire-billing' ); ?>">
            <h2 class="hpr-checkout-order__title"><?php echo esc_html__( 'Your order', 'hexa-pr-wire-billing' ); ?></h2>
            <?php foreach ( $items as $item ) :
                $product = $item['data'] ?? null;
                if ( ! $product instanceof \WC_Product ) {
                    continue;
                }
                $details = wc_get_formatted_cart_item_data( $item, true );
                $service = sanitize_text_field( (string) ( $item['_hpr_billing_service_title'] ?? '' ) );
                ?>
                <div class="hpr-checkout-order__item">
                    <div class="hpr-checkout-order__name">
                        <strong><?php echo esc_html( '' !== $service ? $service : $product->get_name() ); ?></strong>
                        <?php if ( '' !== trim( $details ) ) : ?><span class="hpr-checkout-order__meta"><?php echo esc_html( implode( ' · ', array_filter( array_map( 'trim', explode( "\n", $details ) ) ) ) ); ?></span><?php endif; ?>
                    </div>
                    <span class="hpr-checkout-order__price"><?php echo wp_kses_post( WC()->cart->get_product_subtotal( $product, (int) $item['quantity'] ) ); ?></span>
                </div>
            <?php endforeach; ?>
            <?php do_action( 'hpr_billing_checkout_order_details', $items ); ?>
            <div class="hpr-checkout-order__next">
                <strong><?php echo esc_html__( 'What happens after payment', 'hexa-pr-wire-billing' ); ?></strong>
                <p><?php echo esc_html__( 'A draft for this release appears in your Hexa PR Wire portal right away. Open it to upload your press release and submit it for review.', 'hexa-pr-wire-billing' ); ?></p>
            </div>
        </section>
        <?php
    }

    public function enqueue_assets(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! ProductCatalog::cart_has_managed_product() ) {
            return;
        }
        wp_enqueue_style(
            'hpr-billing-checkout',
            plugins_url( 'assets/frontend/checkout.css', dirname( __DIR__, 3 ) . '/hexa-pr-wire-billing.php' ),
            [],
            \HexaPrWire\Billing\Config::VERSION . '.' . (string) filemtime( dirname( __DIR__, 3 ) . '/assets/frontend/checkout.css' )
        );
    }

    public function validate_fields( array $data, \WP_Error $errors ): void {
        if ( ! ProductCatalog::cart_has_managed_product() ) {
            return;
        }
        $title = sanitize_text_field( (string) ( $data['order_press_release_title'] ?? '' ) );
        if ( '' === $title ) {
            $errors->add( 'hpr_billing_article_title', __( 'Please enter an article title or the featured company name.', 'hexa-pr-wire-billing' ) );
        }

        if ( $this->cart_contains_kind( ProductCatalog::CUSTOM ) && '' === $this->service_from_cart() ) {
            $errors->add( 'hpr_billing_custom_service', __( 'The custom service selection is missing or invalid.', 'hexa-pr-wire-billing' ) );
        }
    }

    public function save_order_fields( \WC_Order $order, array $data ): void {
        if ( ! ProductCatalog::cart_has_managed_product() ) {
            return;
        }
        $title = sanitize_text_field( (string) ( $data['order_press_release_title'] ?? '' ) );
        if ( '' !== $title ) {
            $order->update_meta_data( '_order_press_release_title', $title );
        }
        $service = $this->service_from_cart();
        if ( '' !== $service ) {
            $order->update_meta_data( '_order_service_title', $service );
        } elseif ( $this->cart_contains_kind( ProductCatalog::WRITING ) ) {
            $order->update_meta_data( '_order_service_title', 'Press Release Writing' );
        }
    }

    public function render_admin_order_fields( \WC_Order $order ): void {
        $fields = $this->order_fields( $order );
        foreach ( $fields as $label => $value ) {
            echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
        }
    }

    public function email_order_fields( array $fields, bool $sent_to_admin, \WC_Order $order ): array {
        unset( $sent_to_admin );
        foreach ( $this->order_fields( $order ) as $label => $value ) {
            $fields[ sanitize_key( $label ) ] = [ 'label' => $label, 'value' => $value ];
        }
        return $fields;
    }

    public function render_customer_order_fields( \WC_Order $order ): void {
        $fields = $this->order_fields( $order );
        if ( [] === $fields ) {
            return;
        }
        echo '<section class="woocommerce-order-details hpr-billing-order-details"><h2>' . esc_html__( 'Release details', 'hexa-pr-wire-billing' ) . '</h2><dl>';
        foreach ( $fields as $label => $value ) {
            echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
        }
        echo '</dl></section>';
    }

    private function order_fields( \WC_Order $order ): array {
        if ( ! ProductCatalog::order_has_managed_product( $order ) ) {
            return [];
        }
        $fields  = [];
        $title   = sanitize_text_field( (string) $order->get_meta( '_order_press_release_title' ) );
        $service = sanitize_text_field( (string) $order->get_meta( '_order_service_title' ) );
        if ( '' !== $title ) {
            $fields[ __( 'Article title', 'hexa-pr-wire-billing' ) ] = $title;
        }
        if ( '' !== $service ) {
            $fields[ __( 'Service', 'hexa-pr-wire-billing' ) ] = $service;
        }
        return $fields;
    }

    private function service_from_cart(): string {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return '';
        }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! empty( $item['_hpr_billing_service_title'] ) ) {
                return sanitize_text_field( (string) $item['_hpr_billing_service_title'] );
            }
        }
        return '';
    }

    private function cart_contains_kind( string $kind ): bool {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( $kind === ( $item['_hpr_billing_kind'] ?? ProductCatalog::kind_for_product( absint( $item['product_id'] ?? 0 ) ) ) ) {
                return true;
            }
        }
        return false;
    }
}
