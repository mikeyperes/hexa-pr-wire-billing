<?php

namespace HexaPrWire\Billing\Commerce\Pricing;

use HexaPrWire\Billing\Commerce\ProductCatalog;

final class CustomerPricingRepository {
    private const MAX_SERVICES = 100;

    public function standard_price( int $user_id ): ?string {
        if ( $user_id <= 0 ) {
            return null;
        }
        return $this->positive_decimal( get_user_meta( $user_id, 'billing_price_standard_release', true ) );
    }

    public function resolved_standard_price( int $user_id = 0 ): ?string {
        $custom = $this->standard_price( $user_id );
        if ( null !== $custom ) {
            return $custom;
        }

        return $this->product_price( ProductCatalog::product_id( ProductCatalog::STANDARD ) );
    }

    public function card_allowed( int $user_id ): bool {
        return $user_id > 0 && '1' === (string) get_user_meta( $user_id, 'billing_allow_credit_card', true );
    }

    /**
     * @return array<int,array{index:int,key:string,name:string,price:string}>
     */
    public function custom_services( int $user_id ): array {
        if ( $user_id <= 0 ) {
            return [];
        }

        $count = min( self::MAX_SERVICES, max( 0, (int) get_user_meta( $user_id, 'billing_custom_services', true ) ) );
        $rows  = [];
        for ( $index = 0; $index < $count; $index++ ) {
            $name  = sanitize_text_field( (string) get_user_meta( $user_id, 'billing_custom_services_' . $index . '_name', true ) );
            $price = $this->positive_decimal( get_user_meta( $user_id, 'billing_custom_services_' . $index . '_price', true ) );
            if ( '' === $name || null === $price ) {
                continue;
            }

            $slug   = sanitize_title( $name );
            $rows[] = [
                'index' => $index,
                'key'   => ( '' !== $slug ? $slug : 'service' ) . '-' . ( $index + 1 ),
                'name'  => $name,
                'price' => $price,
            ];
        }

        return $rows;
    }

    public function find_custom_service( int $user_id, string $requested ): ?array {
        $requested = trim( rawurldecode( $requested ) );
        if ( '' === $requested ) {
            return null;
        }

        foreach ( $this->custom_services( $user_id ) as $service ) {
            if ( hash_equals( $service['key'], $requested ) || 0 === strcasecmp( $service['name'], $requested ) ) {
                return $service;
            }
        }
        return null;
    }

    public function product_price( int $product_id ): ?string {
        if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
            return null;
        }
        $product = wc_get_product( $product_id );
        return $product ? $this->positive_decimal( $product->get_price() ) : null;
    }

    /**
     * Stores the customer's billing settings in the stable meta contract
     * (billing_custom_services uses the ACF repeater layout readers expect).
     *
     * @param array<int,array{name:string,price:string}> $services
     */
    public function save_settings( int $user_id, string $standard_price, bool $card_allowed, array $services ): void {
        if ( $user_id <= 0 ) {
            return;
        }

        $standard = $this->positive_decimal( $standard_price );
        null === $standard
            ? delete_user_meta( $user_id, 'billing_price_standard_release' )
            : update_user_meta( $user_id, 'billing_price_standard_release', $standard );
        update_user_meta( $user_id, 'billing_allow_credit_card', $card_allowed ? '1' : '0' );

        $rows = [];
        foreach ( $services as $service ) {
            $name  = sanitize_text_field( (string) ( $service['name'] ?? '' ) );
            $price = $this->positive_decimal( $service['price'] ?? '' );
            if ( '' !== $name && null !== $price && count( $rows ) < self::MAX_SERVICES ) {
                $rows[] = [ 'name' => $name, 'price' => $price ];
            }
        }

        $old_count = min( self::MAX_SERVICES, max( 0, (int) get_user_meta( $user_id, 'billing_custom_services', true ) ) );
        for ( $index = count( $rows ); $index < $old_count; $index++ ) {
            delete_user_meta( $user_id, 'billing_custom_services_' . $index . '_name' );
            delete_user_meta( $user_id, 'billing_custom_services_' . $index . '_price' );
        }
        update_user_meta( $user_id, 'billing_custom_services', count( $rows ) );
        foreach ( $rows as $index => $row ) {
            update_user_meta( $user_id, 'billing_custom_services_' . $index . '_name', $row['name'] );
            update_user_meta( $user_id, 'billing_custom_services_' . $index . '_price', $row['price'] );
        }
    }

    private function positive_decimal( mixed $value ): ?string {
        if ( ! is_scalar( $value ) || '' === trim( (string) $value ) || ! is_numeric( $value ) ) {
            return null;
        }

        $decimal = function_exists( 'wc_format_decimal' )
            ? wc_format_decimal( (string) $value, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 )
            : number_format( (float) $value, 2, '.', '' );

        return (float) $decimal > 0 ? (string) $decimal : null;
    }
}

