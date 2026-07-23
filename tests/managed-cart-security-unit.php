<?php

declare(strict_types=1);

$GLOBALS['hpr_test_current_user'] = 0;
$GLOBALS['hpr_test_notices']      = [];
$GLOBALS['hpr_test_options']      = [
    'hpr_billing_settings' => [
        'standard_product_id' => 84,
        'premium_product_id'  => 85,
        'writing_product_id'  => 260868,
        'custom_product_id'   => 323645,
    ],
    'hpr_billing_runtime_enabled'              => true,
    'hpr_billing_feature_single_item_cart'     => true,
    'hpr_billing_feature_customer_pricing'     => true,
];
$GLOBALS['hpr_test_user_meta'] = [
    7 => [
        'billing_price_standard_release'  => '175',
        'billing_custom_services'         => '1',
        'billing_custom_services_0_name'  => 'Executive Distribution',
        'billing_custom_services_0_price' => '425',
    ],
];

class WooCommerce {
}

class WC_Product {
    private string $price;

    public function __construct( private int $id, string $price ) {
        $this->price = $price;
    }

    public function get_id(): int {
        return $this->id;
    }

    public function get_price(): string {
        return $this->price;
    }

    public function set_price( string $price ): void {
        $this->price = $price;
    }
}

class WC_Cart {
    public array $cart_contents = [];

    public function get_cart(): array {
        return $this->cart_contents;
    }

    public function remove_cart_item( string $key ): void {
        unset( $this->cart_contents[ $key ] );
    }
}

class HprTestWoo {
    public WC_Cart $cart;

    public function __construct() {
        $this->cart = new WC_Cart();
    }
}

$GLOBALS['hpr_test_woo'] = new HprTestWoo();

function WC(): HprTestWoo {
    return $GLOBALS['hpr_test_woo'];
}

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['hpr_test_options'][ $key ] ?? $default;
}

function wp_parse_args( array $args, array $defaults ): array {
    return array_merge( $defaults, $args );
}

function get_user_meta( int $user_id, string $key, bool $single ): mixed {
    unset( $single );
    return $GLOBALS['hpr_test_user_meta'][ $user_id ][ $key ] ?? '';
}

function get_current_user_id(): int {
    return $GLOBALS['hpr_test_current_user'];
}

function is_user_logged_in(): bool {
    return get_current_user_id() > 0;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
    unset( $hook, $callback, $priority, $accepted_args );
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
    unset( $hook, $callback, $priority, $accepted_args );
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function sanitize_key( string $value ): string {
    return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '' );
}

function sanitize_text_field( string $value ): string {
    return trim( strip_tags( $value ) );
}

function sanitize_title( string $value ): string {
    return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $value ) ?: '' ), '-' );
}

function wp_unslash( mixed $value ): mixed {
    return $value;
}

function wc_format_decimal( string $value, int $decimals = 2 ): string {
    return number_format( (float) $value, $decimals, '.', '' );
}

function wc_get_price_decimals(): int {
    return 2;
}

function wc_get_product( int $product_id ): WC_Product|false {
    return match ( $product_id ) {
        84 => new WC_Product( 84, '299' ),
        260868 => new WC_Product( 260868, '125' ),
        323645 => new WC_Product( 323645, '1' ),
        default => false,
    };
}

function wc_add_notice( string $message, string $type ): void {
    $GLOBALS['hpr_test_notices'][] = [ $type, $message ];
}

function wc_has_notice( string $message, string $type ): bool {
    return in_array( [ $type, $message ], $GLOBALS['hpr_test_notices'], true );
}

function __( string $message, string $domain ): string {
    unset( $domain );
    return $message;
}

function is_admin(): bool {
    return false;
}

function wp_doing_ajax(): bool {
    return false;
}

require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Commerce/ProductCatalog.php';
require dirname( __DIR__ ) . '/src/Commerce/Pricing/CustomerPricingRepository.php';
require dirname( __DIR__ ) . '/src/Commerce/Cart/ManagedCart.php';

use HexaPrWire\Billing\Commerce\Cart\ManagedCart;

$cart = new ManagedCart();
$_REQUEST = [ 'service' => 'executive-distribution-1', 'price' => '1' ];
if ( $cart->validate_add_to_cart( true, 323645, 1 ) ) {
    fwrite( STDERR, "FAIL: Guests must not add the custom carrier product.\n" );
    exit( 1 );
}
if ( $cart->filter_custom_purchasable( true, new WC_Product( 323645, '1' ) ) ) {
    fwrite( STDERR, "FAIL: The custom carrier must not be purchasable by guests.\n" );
    exit( 1 );
}

$GLOBALS['hpr_test_current_user'] = 7;
if ( ! $cart->validate_add_to_cart( true, 323645, 1 ) ) {
    fwrite( STDERR, "FAIL: Valid account custom service was rejected.\n" );
    exit( 1 );
}
if ( ! $cart->filter_custom_purchasable( false, new WC_Product( 323645, '' ) ) ) {
    fwrite( STDERR, "FAIL: An entitled custom service must override the empty carrier fallback price.\n" );
    exit( 1 );
}

$_REQUEST = [];
WC()->cart->cart_contents = [];
if ( ! $cart->filter_custom_purchasable( false, new WC_Product( 323645, '' ) ) ) {
    fwrite( STDERR, "FAIL: A signed-in custom carrier could not hydrate before its cart entitlement check.\n" );
    exit( 1 );
}

WC()->cart->cart_contents = [
    'restored-custom' => [
        'product_id'                 => 323645,
        '_hpr_billing_kind'          => 'custom',
        '_hpr_billing_service_key'   => 'executive-distribution-1',
        '_hpr_billing_service_title' => 'Executive Distribution',
        'data'                       => new WC_Product( 323645, '' ),
    ],
];
$GLOBALS['hpr_test_notices'] = [];
$cart->set_managed_prices( WC()->cart );
$cart->validate_cart_items();
$restored = WC()->cart->cart_contents['restored-custom'];
if ( [] !== $GLOBALS['hpr_test_notices'] || '425.00' !== $restored['_hpr_billing_price'] || '425.00' !== $restored['data']->get_price() ) {
    fwrite( STDERR, "FAIL: An entitled custom cart did not survive session hydration and server repricing.\n" );
    exit( 1 );
}

$_REQUEST = [ 'service' => 'executive-distribution-1' ];
$data = $cart->add_cart_item_data( [], 323645, 0 );
if ( '425.00' !== $data['_hpr_billing_price'] || 7 !== $data['_hpr_billing_user_id'] ) {
    fwrite( STDERR, "FAIL: Request price overrode the server-side custom price.\n" );
    exit( 1 );
}

$GLOBALS['hpr_test_current_user'] = 8;
if ( $cart->validate_add_to_cart( true, 323645, 1 ) ) {
    fwrite( STDERR, "FAIL: A signed-in account without the requested entitlement added the custom carrier.\n" );
    exit( 1 );
}

$GLOBALS['hpr_test_current_user'] = 7;
WC()->cart->cart_contents = [
    'legacy-standard' => [ 'product_id' => 84, 'data' => new WC_Product( 84, '299' ) ],
];
$cart->set_managed_prices( WC()->cart );
$standard = WC()->cart->cart_contents['legacy-standard'];
if ( empty( $standard['_hpr_billing_managed'] ) || '175.00' !== $standard['_hpr_billing_price'] || '175.00' !== $standard['data']->get_price() ) {
    fwrite( STDERR, "FAIL: A safe legacy standard cart was not adopted and repriced.\n" );
    exit( 1 );
}

if ( $cart->validate_add_to_cart( true, 999, 1 ) ) {
    fwrite( STDERR, "FAIL: Unrelated products must not join a managed billing cart.\n" );
    exit( 1 );
}

$GLOBALS['hpr_test_notices'] = [];
$GLOBALS['hpr_test_current_user'] = 0;
WC()->cart->cart_contents = [
    'legacy-custom' => [ 'product_id' => 323645, 'data' => new WC_Product( 323645, '1' ) ],
];
$cart->validate_cart_items();
if ( [] === $GLOBALS['hpr_test_notices'] ) {
    fwrite( STDERR, "FAIL: Legacy guest custom-product carts must be blocked at checkout.\n" );
    exit( 1 );
}

echo "PASS: Managed cart pricing, account binding, and legacy custom-cart rejection are enforced.\n";
