<?php

namespace HexaPrWire\Billing\Settings;

final class SettingsRepository {
    public const OPTION          = 'hpr_billing_settings';
    public const RUNTIME_OPTION  = 'hpr_billing_runtime_enabled';
    public const MIGRATION_STATE = 'hpr_billing_migration_state';

    public const FEATURE_OPTIONS = [
        'single_item_cart'    => 'hpr_billing_feature_single_item_cart',
        'customer_pricing'    => 'hpr_billing_feature_customer_pricing',
        'checkout_fields'     => 'hpr_billing_feature_checkout_fields',
        'gateway_policy'      => 'hpr_billing_feature_gateway_policy',
        'stripe_descriptions' => 'hpr_billing_feature_stripe_descriptions',
        'fulfillment'         => 'hpr_billing_feature_fulfillment',
        'order_portal'        => 'hpr_billing_feature_order_portal',
        'catalog'             => 'hpr_billing_feature_catalog',
        'acf_fields'          => 'hpr_billing_feature_acf_fields',
    ];

    public static function defaults(): array {
        return [
            'standard_product_id' => 84,
            'premium_product_id'  => 85,
            'writing_product_id'  => 260868,
            'custom_product_id'   => 323645,
            'packages_page_id'    => 41,
            'submit_page_id'      => 261050,
            'checkout_page_id'    => 81,
            'standard_category'   => 'press-release',
            'custom_category'     => 'custom-order',
            'support_email'       => 'contact@michaelperes.com',
        ];
    }

    public static function ensure_defaults(): void {
        if ( false === get_option( self::OPTION, false ) ) {
            add_option( self::OPTION, self::defaults(), '', 'no' );
        }
        if ( false === get_option( self::RUNTIME_OPTION, false ) ) {
            add_option( self::RUNTIME_OPTION, false, '', 'no' );
        }
        foreach ( self::FEATURE_OPTIONS as $option ) {
            if ( false === get_option( $option, false ) ) {
                add_option( $option, true, '', 'no' );
            }
        }
    }

    public static function all(): array {
        $stored = get_option( self::OPTION, [] );
        return wp_parse_args( is_array( $stored ) ? $stored : [], self::defaults() );
    }

    public static function get( string $key, mixed $default = null ): mixed {
        $settings = self::all();
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    public static function update( array $changes ): array {
        $settings = self::all();

        foreach ( [ 'standard_product_id', 'premium_product_id', 'writing_product_id', 'custom_product_id', 'packages_page_id', 'submit_page_id', 'checkout_page_id' ] as $key ) {
            if ( array_key_exists( $key, $changes ) ) {
                $settings[ $key ] = absint( $changes[ $key ] );
            }
        }
        foreach ( [ 'standard_category', 'custom_category' ] as $key ) {
            if ( array_key_exists( $key, $changes ) ) {
                $settings[ $key ] = sanitize_title( (string) $changes[ $key ] );
            }
        }
        if ( array_key_exists( 'support_email', $changes ) ) {
            $email = sanitize_email( (string) $changes['support_email'] );
            if ( '' !== $email ) {
                $settings['support_email'] = $email;
            }
        }

        update_option( self::OPTION, $settings, false );
        return $settings;
    }

    public static function runtime_enabled(): bool {
        return (bool) get_option( self::RUNTIME_OPTION, false );
    }

    public static function set_runtime_enabled( bool $enabled ): void {
        update_option( self::RUNTIME_OPTION, $enabled, false );
    }

    public static function feature_enabled( string $feature ): bool {
        $option = self::FEATURE_OPTIONS[ $feature ] ?? '';
        return '' !== $option && (bool) get_option( $option, true );
    }

    public static function migration_state(): array {
        $state = get_option( self::MIGRATION_STATE, [] );
        return is_array( $state ) ? $state : [];
    }
}

