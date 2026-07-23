<?php

namespace HexaPrWire\Billing\Commerce;

use HexaPrWire\Billing\Settings\SettingsRepository;

final class ProductCatalog {
    public const STANDARD = 'standard';
    public const PREMIUM  = 'premium';
    public const WRITING  = 'writing';
    public const CUSTOM   = 'custom';

    public static function product_id( string $kind ): int {
        $keys = [
            self::STANDARD => 'standard_product_id',
            self::PREMIUM  => 'premium_product_id',
            self::WRITING  => 'writing_product_id',
            self::CUSTOM   => 'custom_product_id',
        ];
        return isset( $keys[ $kind ] ) ? absint( SettingsRepository::get( $keys[ $kind ], 0 ) ) : 0;
    }

    public static function kind_for_product( int $product_id ): ?string {
        foreach ( [ self::STANDARD, self::PREMIUM, self::WRITING, self::CUSTOM ] as $kind ) {
            if ( $product_id > 0 && self::product_id( $kind ) === $product_id ) {
                return $kind;
            }
        }
        return null;
    }

    public static function managed_ids(): array {
        return array_values(
            array_filter(
                array_map( [ self::class, 'product_id' ], [ self::STANDARD, self::WRITING, self::CUSTOM ] )
            )
        );
    }

    public static function is_managed( int $product_id ): bool {
        return in_array( $product_id, self::managed_ids(), true );
    }

    public static function cart_has_managed_product(): bool {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( self::is_managed( absint( $item['product_id'] ?? 0 ) ) ) {
                return true;
            }
        }
        return false;
    }

    public static function order_has_managed_product( \WC_Order $order ): bool {
        foreach ( $order->get_items() as $item ) {
            if ( self::is_managed( (int) $item->get_product_id() ) ) {
                return true;
            }
        }
        return false;
    }
}

