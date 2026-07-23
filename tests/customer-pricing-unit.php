<?php

declare(strict_types=1);

$GLOBALS['hpr_test_options'] = [
    'hpr_billing_settings' => [
        'standard_product_id' => 84,
        'premium_product_id'  => 85,
        'writing_product_id'  => 260868,
        'custom_product_id'   => 323645,
    ],
];
$GLOBALS['hpr_test_user_meta'] = [
    7 => [
        'billing_price_standard_release'    => '175.50',
        'billing_custom_services'           => '3',
        'billing_custom_services_0_name'    => 'Executive Distribution',
        'billing_custom_services_0_price'   => '425',
        'billing_custom_services_1_name'    => 'Invalid Free Service',
        'billing_custom_services_1_price'   => '0',
        'billing_custom_services_2_name'    => '',
        'billing_custom_services_2_price'   => '99',
        'billing_allow_credit_card'         => '1',
    ],
];

class WC_Product {
    public function __construct( private string $price ) {
    }

    public function get_price(): string {
        return $this->price;
    }
}

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['hpr_test_options'][ $key ] ?? $default;
}

function wp_parse_args( array $args, array $defaults ): array {
    return array_merge( $defaults, $args );
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function get_user_meta( int $user_id, string $key, bool $single ): mixed {
    unset( $single );
    return $GLOBALS['hpr_test_user_meta'][ $user_id ][ $key ] ?? '';
}

function sanitize_text_field( string $value ): string {
    return trim( strip_tags( $value ) );
}

function sanitize_title( string $value ): string {
    return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $value ) ?: '' ), '-' );
}

function wc_format_decimal( string $value, int $decimals = 2 ): string {
    return number_format( (float) $value, $decimals, '.', '' );
}

function wc_get_price_decimals(): int {
    return 2;
}

function wc_get_product( int $product_id ): WC_Product|false {
    return 84 === $product_id ? new WC_Product( '299' ) : false;
}

require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Commerce/ProductCatalog.php';
require dirname( __DIR__ ) . '/src/Commerce/Pricing/CustomerPricingRepository.php';

use HexaPrWire\Billing\Commerce\Pricing\CustomerPricingRepository;

$pricing = new CustomerPricingRepository();

if ( '175.50' !== $pricing->resolved_standard_price( 7 ) || '299.00' !== $pricing->resolved_standard_price( 0 ) ) {
    fwrite( STDERR, "FAIL: Standard account and fallback prices did not resolve correctly.\n" );
    exit( 1 );
}

$services = $pricing->custom_services( 7 );
if ( 1 !== count( $services ) || 'executive-distribution-1' !== $services[0]['key'] || '425.00' !== $services[0]['price'] ) {
    fwrite( STDERR, "FAIL: Invalid repeater rows were not excluded from custom pricing.\n" );
    exit( 1 );
}

if ( $services[0] !== $pricing->find_custom_service( 7, rawurlencode( $services[0]['key'] ) ) || ! $pricing->card_allowed( 7 ) ) {
    fwrite( STDERR, "FAIL: Custom service lookup or card entitlement failed.\n" );
    exit( 1 );
}

echo "PASS: Customer pricing is positive, server-backed, and account-specific.\n";

