<?php

declare(strict_types=1);

class WooCommerce {
}

class WC_Product {
    public function __construct( private int $id ) {
    }

    public function get_status(): string {
        return 'publish';
    }
}

class WP_Post {
    public string $post_type = 'page';
    public string $post_status = 'publish';
    public string $post_title = 'Page';
    public string $post_content = '';
}

class WP_Error {
    public function __construct( private string $code, private string $message, private mixed $data = null ) {
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }
}

class HprTestWpdb {
    public string $prefix = 'wp_';

    public function prepare( string $query, mixed ...$args ): string {
        return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
    }

    public function get_var( string $query ): string {
        unset( $query );
        return 'wp_snippets';
    }

    public function get_results( string $query, mixed $format ): array {
        unset( $query, $format );
        return array_map( static fn( int $id ): array => [ 'id' => $id, 'name' => 'Snippet ' . $id, 'active' => 1 ], [ 26, 30, 34, 43, 44 ] );
    }
}

define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['wpdb'] = new HprTestWpdb();
$GLOBALS['hpr_test_update_count'] = 0;
$GLOBALS['hpr_test_options'] = [
    'hpr_billing_settings' => [
        'checkout_page_id' => 81,
        'packages_page_id' => 41,
        'submit_page_id'   => 41,
    ],
    'woocommerce_checkout_page_id' => 81,
    'hpr_billing_runtime_enabled'   => false,
    'hpr_billing_migration_state'   => [],
    'hpr_billing_feature_single_item_cart'    => true,
    'hpr_billing_feature_customer_pricing'    => true,
    'hpr_billing_feature_checkout_fields'     => true,
    'hpr_billing_feature_gateway_policy'      => true,
    'hpr_billing_feature_stripe_descriptions' => true,
    'hpr_billing_feature_fulfillment'         => true,
    'hpr_billing_feature_order_portal'        => true,
    'hpr_billing_feature_catalog'             => true,
    'hpr_billing_feature_acf_fields'          => true,
    'woocommerce_stripe_upm_settings'         => [ 'enabled' => 'yes' ],
];

function wc_get_orders(): array {
    return [];
}

function wc_get_product( int $product_id ): WC_Product|false {
    return in_array( $product_id, [ 84, 85, 260868, 323645 ], true ) ? new WC_Product( $product_id ) : false;
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

function get_post( int $post_id ): WP_Post|false {
    return $post_id > 0 ? new WP_Post() : false;
}

function is_wp_error( mixed $value ): bool {
    return $value instanceof WP_Error;
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    unset( $key, $value, $autoload );
    $GLOBALS['hpr_test_update_count']++;
    return true;
}

require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Migration/LegacyCommerceMigration.php';

use HexaPrWire\Billing\Migration\LegacyCommerceMigration;

$migration = new LegacyCommerceMigration();
$checks    = $migration->preflight();
$distinct  = array_values( array_filter( $checks, static fn( array $check ): bool => 'Distinct page mapping' === $check['label'] ) );
if ( 1 !== count( $distinct ) || 'fail' !== $distinct[0]['status'] ) {
    fwrite( STDERR, "FAIL: Duplicate migration pages were not detected.\n" );
    exit( 1 );
}

$result = $migration->run();
if ( ! $result instanceof WP_Error || 'billing_migration_preflight_failed' !== $result->get_error_code() || 0 !== $GLOBALS['hpr_test_update_count'] ) {
    fwrite( STDERR, "FAIL: Failed preflight performed a mutation or returned the wrong error.\n" );
    exit( 1 );
}

echo "PASS: Migration preflight blocks duplicate page ownership before mutation.\n";
