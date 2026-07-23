<?php

declare(strict_types=1);

$GLOBALS['hpr_test_current_user'] = 0;
$GLOBALS['hpr_test_options'] = [
    'hpr_billing_settings' => [
        'standard_product_id' => 84,
        'premium_product_id'  => 85,
        'writing_product_id'  => 260868,
        'custom_product_id'   => 323645,
    ],
    'hpr_billing_runtime_enabled'         => true,
    'hpr_billing_feature_gateway_policy' => true,
];
$GLOBALS['hpr_test_user_meta'] = [
    7 => [ 'billing_allow_credit_card' => '1' ],
];

class WooCommerce {
}

class WC_Cart {
    public function get_cart(): array {
        return [ 'managed' => [ 'product_id' => 84 ] ];
    }
}

class HprGatewayTestWoo {
    public WC_Cart $cart;

    public function __construct() {
        $this->cart = new WC_Cart();
    }
}

class WP_Error {
    public array $errors = [];

    public function add( string $code, string $message ): void {
        $this->errors[ $code ] = $message;
    }
}

$GLOBALS['hpr_test_woo'] = new HprGatewayTestWoo();

function WC(): HprGatewayTestWoo {
    return $GLOBALS['hpr_test_woo'];
}

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['hpr_test_options'][ $key ] ?? $default;
}

function wp_parse_args( array $args, array $defaults ): array {
    return array_merge( $defaults, $args );
}

function get_current_user_id(): int {
    return $GLOBALS['hpr_test_current_user'];
}

function get_user_meta( int $user_id, string $key, bool $single ): mixed {
    unset( $single );
    return $GLOBALS['hpr_test_user_meta'][ $user_id ][ $key ] ?? '';
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function sanitize_key( string $value ): string {
    return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '' );
}

function wp_unslash( mixed $value ): mixed {
    return $value;
}

function __( string $message, string $domain ): string {
    unset( $domain );
    return $message;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
    unset( $hook, $callback, $priority, $accepted_args );
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
    unset( $hook, $callback, $priority, $accepted_args );
}

function is_checkout_pay_page(): bool {
    return false;
}

require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Commerce/ProductCatalog.php';
require dirname( __DIR__ ) . '/src/Commerce/Pricing/CustomerPricingRepository.php';
require dirname( __DIR__ ) . '/src/Commerce/Payments/GatewayPolicy.php';

use HexaPrWire\Billing\Commerce\Payments\GatewayPolicy;

$policy   = new GatewayPolicy();
$gateways = [ 'stripe_upm' => new stdClass(), 'stripe_ach' => new stdClass(), 'stripe_cc' => new stdClass(), 'cod' => new stdClass() ];

$guest = $policy->filter_gateways( $gateways );
if ( array_keys( $guest ) !== [ 'stripe_upm', 'stripe_ach' ] ) {
    fwrite( STDERR, "FAIL: Managed guest checkout was not restricted to ACH gateways.\n" );
    exit( 1 );
}

$GLOBALS['hpr_test_current_user'] = 7;
$entitled = $policy->filter_gateways( $gateways );
if ( array_keys( $entitled ) !== [ 'stripe_upm', 'stripe_ach', 'stripe_cc' ] ) {
    fwrite( STDERR, "FAIL: Card-entitled checkout did not retain ACH plus Stripe card.\n" );
    exit( 1 );
}

$upm = (object) [ 'id' => 'stripe_upm' ];
$options = $policy->filter_stripe_element_options(
    [ 'paymentMethodConfiguration' => 'pmc_test', 'appearance' => [ 'theme' => 'stripe' ] ],
    $upm
);
if ( isset( $options['paymentMethodConfiguration'] ) || [ 'us_bank_account' ] !== $options['paymentMethodTypes'] ) {
    fwrite( STDERR, "FAIL: UPM Elements were not constrained to ACH.\n" );
    exit( 1 );
}

$GLOBALS['hpr_test_current_user'] = 0;
$_POST['_stripe_payment_method_type'] = 'card';
$errors = new WP_Error();
$policy->validate_submitted_gateway( [ 'payment_method' => 'stripe_upm' ], $errors );
if ( ! isset( $errors->errors['hpr_billing_ach_required'] ) ) {
    fwrite( STDERR, "FAIL: A direct UPM card submission bypassed ACH enforcement.\n" );
    exit( 1 );
}

$_POST['_stripe_payment_method_type'] = 'us_bank_account';
$errors = new WP_Error();
$policy->validate_submitted_gateway( [ 'payment_method' => 'stripe_upm' ], $errors );
if ( [] !== $errors->errors ) {
    fwrite( STDERR, "FAIL: A valid UPM ACH submission was rejected.\n" );
    exit( 1 );
}

echo "PASS: Managed gateway allowlist, card entitlement, and ACH-only UPM enforcement are stable.\n";
