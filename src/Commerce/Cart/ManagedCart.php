<?php

namespace HexaPrWire\Billing\Commerce\Cart;

use HexaPrWire\Billing\Commerce\Pricing\CustomerPricingRepository;
use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class ManagedCart {
    private CustomerPricingRepository $pricing;

    public function __construct() {
        $this->pricing = new CustomerPricingRepository();
    }

    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 20, 5 );
        add_filter( 'woocommerce_is_purchasable', [ $this, 'filter_custom_purchasable' ], 100, 2 );
        add_filter( 'woocommerce_is_sold_individually', [ $this, 'force_sold_individually' ], 20, 2 );
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 20, 3 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_managed_prices' ], 20 );
        add_action( 'woocommerce_check_cart_items', [ $this, 'validate_cart_items' ], 20 );
        add_action( 'woocommerce_add_to_cart', [ $this, 'enforce_single_item_cart' ], 20, 6 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 20, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_item_data' ], 20, 4 );
        add_filter( 'woocommerce_add_to_cart_redirect', [ $this, 'redirect_to_checkout' ], 20, 2 );
        add_filter( 'wc_add_to_cart_message_html', [ $this, 'filter_add_to_cart_message' ], 20, 3 );
        add_filter( 'woocommerce_cart_needs_shipping', [ $this, 'filter_needs_shipping' ], 20 );
        add_filter( 'woocommerce_cart_needs_shipping_address', [ $this, 'filter_needs_shipping' ], 20 );
    }

    public function validate_add_to_cart( bool $passed, int $product_id, int $quantity, int $variation_id = 0, array $variations = [] ): bool {
        unset( $variation_id, $variations );
        if ( ! $passed ) {
            return false;
        }

        $kind = ProductCatalog::kind_for_product( $product_id );
        if ( null === $kind ) {
            if ( SettingsRepository::feature_enabled( 'single_item_cart' ) && ProductCatalog::cart_has_managed_product() ) {
                $this->add_error( __( 'Complete or remove the current billing service before adding another product.', 'hexa-pr-wire-billing' ) );
                return false;
            }
            return true;
        }
        if ( ProductCatalog::PREMIUM === $kind ) {
            wc_add_notice( __( 'Premium distribution is available through the sales team.', 'hexa-pr-wire-billing' ), 'error' );
            return false;
        }
        if ( $quantity > 1 ) {
            wc_add_notice( __( 'Billing products can only be purchased one at a time.', 'hexa-pr-wire-billing' ), 'error' );
            return false;
        }

        if ( ProductCatalog::CUSTOM === $kind ) {
            if ( ! is_user_logged_in() ) {
                wc_add_notice( __( 'Sign in to purchase a custom service.', 'hexa-pr-wire-billing' ), 'error' );
                return false;
            }
            if ( null === $this->requested_service( get_current_user_id() ) ) {
                wc_add_notice( __( 'That custom service is not available for your account.', 'hexa-pr-wire-billing' ), 'error' );
                return false;
            }
        }

        $price = match ( $kind ) {
            ProductCatalog::STANDARD => SettingsRepository::feature_enabled( 'customer_pricing' )
                ? $this->pricing->resolved_standard_price( get_current_user_id() )
                : $this->pricing->product_price( $product_id ),
            ProductCatalog::WRITING => $this->pricing->product_price( $product_id ),
            ProductCatalog::CUSTOM => $this->requested_service( get_current_user_id() )['price'] ?? null,
            default => null,
        };
        if ( null === $price ) {
            $this->add_error( __( 'This billing service does not have a valid server-side price.', 'hexa-pr-wire-billing' ) );
            return false;
        }

        return true;
    }

    public function force_sold_individually( bool $sold_individually, \WC_Product $product ): bool {
        return ProductCatalog::is_managed( $product->get_id() ) ? true : $sold_individually;
    }

    public function filter_custom_purchasable( bool $purchasable, \WC_Product $product ): bool {
        if ( ProductCatalog::CUSTOM !== ProductCatalog::kind_for_product( $product->get_id() ) ) {
            return $purchasable;
        }

        $user_id = get_current_user_id();
        // Woo checks purchasability before the saved cart item is hydrated. The
        // add-to-cart and cart-validation hooks enforce the specific entitlement.
        return $user_id > 0;
    }

    public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
        unset( $variation_id );
        if ( ! ProductCatalog::is_managed( $product_id ) ) {
            return $cart_item_data;
        }

        $kind    = ProductCatalog::kind_for_product( $product_id );
        $user_id = get_current_user_id();
        $price   = null;
        $service = null;

        if ( ProductCatalog::STANDARD === $kind ) {
            $price = SettingsRepository::feature_enabled( 'customer_pricing' )
                ? $this->pricing->resolved_standard_price( $user_id )
                : $this->pricing->product_price( $product_id );
        } elseif ( ProductCatalog::WRITING === $kind ) {
            $price = $this->pricing->product_price( $product_id );
        } elseif ( ProductCatalog::CUSTOM === $kind ) {
            $service = $this->requested_service( $user_id );
            $price   = $service['price'] ?? null;
        }

        if ( null === $price ) {
            return $cart_item_data;
        }

        $cart_item_data['_hpr_billing_managed'] = 1;
        $cart_item_data['_hpr_billing_kind']    = $kind;
        $cart_item_data['_hpr_billing_price']   = $price;
        $cart_item_data['_hpr_billing_user_id'] = $user_id;
        if ( is_array( $service ) ) {
            $cart_item_data['_hpr_billing_service_key']   = $service['key'];
            $cart_item_data['_hpr_billing_service_title'] = $service['name'];
        }

        return $cart_item_data;
    }

    public function set_managed_prices( \WC_Cart $cart ): void {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product_id = absint( $cart_item['product_id'] ?? 0 );
            $kind       = sanitize_key( (string) ( $cart_item['_hpr_billing_kind'] ?? ProductCatalog::kind_for_product( $product_id ) ?? '' ) );
            if ( ! ProductCatalog::is_managed( $product_id ) || '' === $kind ) {
                continue;
            }

            $user_id    = get_current_user_id();
            $price      = null;
            $service    = null;

            if ( ProductCatalog::STANDARD === $kind ) {
                $price = SettingsRepository::feature_enabled( 'customer_pricing' )
                    ? $this->pricing->resolved_standard_price( $user_id )
                    : $this->pricing->product_price( $product_id );
            } elseif ( ProductCatalog::WRITING === $kind ) {
                $price = $this->pricing->product_price( $product_id );
            } elseif ( ProductCatalog::CUSTOM === $kind && $user_id > 0 ) {
                $service = $this->pricing->find_custom_service( $user_id, (string) ( $cart_item['_hpr_billing_service_key'] ?? '' ) );
                $price   = $service['price'] ?? null;
            }

            if ( null === $price ) {
                continue;
            }

            $cart->cart_contents[ $cart_item_key ]['_hpr_billing_managed'] = 1;
            $cart->cart_contents[ $cart_item_key ]['_hpr_billing_kind']    = $kind;
            $cart->cart_contents[ $cart_item_key ]['_hpr_billing_price']   = $price;
            $cart->cart_contents[ $cart_item_key ]['_hpr_billing_user_id'] = $user_id;
            if ( is_array( $service ) ) {
                $cart->cart_contents[ $cart_item_key ]['_hpr_billing_service_title'] = $service['name'];
            }
            if ( isset( $cart_item['data'] ) && $cart_item['data'] instanceof \WC_Product ) {
                $cart_item['data']->set_price( (string) $price );
            }
        }
    }

    public function validate_cart_items(): void {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_id = absint( $cart_item['product_id'] ?? 0 );
            $kind       = sanitize_key( (string) ( $cart_item['_hpr_billing_kind'] ?? ProductCatalog::kind_for_product( $product_id ) ?? '' ) );
            if ( ! ProductCatalog::is_managed( $product_id ) || '' === $kind ) {
                continue;
            }

            if ( ProductCatalog::CUSTOM === $kind ) {
                $service = get_current_user_id() > 0
                    ? $this->pricing->find_custom_service( get_current_user_id(), (string) ( $cart_item['_hpr_billing_service_key'] ?? '' ) )
                    : null;
                if ( null === $service ) {
                    $this->add_error( __( 'Your custom service is no longer available. Remove it and select an available service again.', 'hexa-pr-wire-billing' ) );
                }
                continue;
            }

            $price = ProductCatalog::STANDARD === $kind && SettingsRepository::feature_enabled( 'customer_pricing' )
                ? $this->pricing->resolved_standard_price( get_current_user_id() )
                : $this->pricing->product_price( $product_id );
            if ( null === $price ) {
                $this->add_error( __( 'A managed billing product does not have a valid server-side price.', 'hexa-pr-wire-billing' ) );
            }
        }
    }

    public function enforce_single_item_cart( string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data ): void {
        unset( $quantity, $variation_id, $variation, $cart_item_data );
        if ( ! SettingsRepository::feature_enabled( 'single_item_cart' ) || ! ProductCatalog::is_managed( $product_id ) || ! WC()->cart ) {
            return;
        }

        foreach ( array_keys( WC()->cart->get_cart() ) as $existing_key ) {
            if ( $existing_key !== $cart_item_key ) {
                WC()->cart->remove_cart_item( $existing_key );
            }
        }
    }

    public function display_cart_item_data( array $item_data, array $cart_item ): array {
        if ( ! empty( $cart_item['_hpr_billing_service_title'] ) ) {
            $item_data[] = [
                'key'   => __( 'Service', 'hexa-pr-wire-billing' ),
                'value' => sanitize_text_field( (string) $cart_item['_hpr_billing_service_title'] ),
            ];
        }
        return $item_data;
    }

    public function save_order_item_data( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
        unset( $cart_item_key, $order );
        if ( empty( $values['_hpr_billing_managed'] ) ) {
            return;
        }
        $item->add_meta_data( '_hpr_billing_kind', sanitize_key( (string) ( $values['_hpr_billing_kind'] ?? '' ) ), true );
        $item->add_meta_data( '_hpr_billing_unit_price', wc_format_decimal( (string) ( $values['_hpr_billing_price'] ?? '' ) ), true );
        $item->add_meta_data( '_hpr_billing_pricing_user_id', absint( $values['_hpr_billing_user_id'] ?? 0 ), true );
        if ( ! empty( $values['_hpr_billing_service_title'] ) ) {
            $item->add_meta_data( 'Service', sanitize_text_field( (string) $values['_hpr_billing_service_title'] ), true );
            $item->add_meta_data( '_hpr_billing_service_key', sanitize_key( (string) ( $values['_hpr_billing_service_key'] ?? '' ) ), true );
        }
    }

    public function redirect_to_checkout( string $url, mixed $adding_to_cart = null ): string {
        $product_id = $adding_to_cart instanceof \WC_Product ? $adding_to_cart->get_id() : absint( $adding_to_cart );
        if ( ! $product_id && isset( $_REQUEST['add-to-cart'] ) ) {
            $product_id = absint( wp_unslash( $_REQUEST['add-to-cart'] ) );
        }
        return ProductCatalog::is_managed( $product_id ) ? wc_get_checkout_url() : $url;
    }

    public function filter_add_to_cart_message( string $message, array $products, bool $show_qty ): string {
        unset( $show_qty );
        foreach ( array_keys( $products ) as $product_id ) {
            if ( ProductCatalog::is_managed( absint( $product_id ) ) ) {
                return '';
            }
        }
        return $message;
    }

    public function filter_needs_shipping( bool $needs_shipping ): bool {
        return ProductCatalog::cart_has_managed_product() ? false : $needs_shipping;
    }

    private function requested_service( int $user_id ): ?array {
        $requested = '';
        foreach ( [ 'service', 'service_title' ] as $key ) {
            if ( isset( $_REQUEST[ $key ] ) && is_scalar( $_REQUEST[ $key ] ) ) {
                $requested = sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) );
                break;
            }
        }
        return $this->pricing->find_custom_service( $user_id, $requested );
    }

    private function add_error( string $message ): void {
        if ( function_exists( 'wc_has_notice' ) && wc_has_notice( $message, 'error' ) ) {
            return;
        }
        wc_add_notice( $message, 'error' );
    }
}
