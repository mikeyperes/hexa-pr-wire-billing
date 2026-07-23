<?php

declare(strict_types=1);

$GLOBALS['hpr_test_options'] = [
    'hpr_billing_settings' => [
        'standard_product_id' => 84,
        'premium_product_id'  => 85,
        'writing_product_id'  => 260868,
        'custom_product_id'   => 323645,
        'checkout_page_id'    => 81,
        'packages_page_id'    => 41,
        'submit_page_id'      => 261050,
    ],
    'woocommerce_checkout_page_id'              => 81,
    'hpr_billing_runtime_enabled'                => false,
    'hpr_billing_migration_state'                => [],
    'hpr_billing_feature_single_item_cart'       => true,
    'hpr_billing_feature_customer_pricing'       => true,
    'hpr_billing_feature_checkout_fields'        => true,
    'hpr_billing_feature_gateway_policy'         => true,
    'hpr_billing_feature_stripe_descriptions'    => true,
    'hpr_billing_feature_fulfillment'            => true,
    'hpr_billing_feature_order_portal'            => true,
    'hpr_billing_feature_catalog'                 => true,
    'hpr_billing_feature_acf_fields'              => true,
    'woocommerce_stripe_upm_settings'             => [ 'enabled' => 'yes' ],
];
$GLOBALS['hpr_test_pages'] = [
    81     => [ 'title' => 'Checkout', 'content' => 'legacy checkout', 'status' => 'publish' ],
    41     => [ 'title' => 'Packages', 'content' => '', 'status' => 'publish' ],
    261050 => [ 'title' => 'Submit', 'content' => '', 'status' => 'publish' ],
];
$GLOBALS['hpr_test_products'] = [
    84     => [ 'status' => 'publish', 'price' => '120', 'regular_price' => '120', 'sale_price' => '' ],
    85     => [ 'status' => 'publish', 'price' => '', 'regular_price' => '', 'sale_price' => '' ],
    260868 => [ 'status' => 'publish', 'price' => '150', 'regular_price' => '150', 'sale_price' => '' ],
    323645 => [ 'status' => 'publish', 'price' => '1', 'regular_price' => '1', 'sale_price' => '' ],
];
$GLOBALS['hpr_test_plugins'] = [ 'flexible-checkout-fields/flexible-checkout-fields.php' => true ];

class WooCommerce {
}

class WC_Product {
    public function __construct( private int $id ) {
    }

    public function get_status(): string {
        return $GLOBALS['hpr_test_products'][ $this->id ]['status'];
    }

    public function get_price( string $context = 'view' ): string {
        unset( $context );
        return $GLOBALS['hpr_test_products'][ $this->id ]['price'];
    }

    public function get_regular_price( string $context = 'view' ): string {
        unset( $context );
        return $GLOBALS['hpr_test_products'][ $this->id ]['regular_price'];
    }

    public function get_sale_price( string $context = 'view' ): string {
        unset( $context );
        return $GLOBALS['hpr_test_products'][ $this->id ]['sale_price'];
    }

    public function set_price( string $value ): void {
        $GLOBALS['hpr_test_products'][ $this->id ]['price'] = $value;
    }

    public function set_regular_price( string $value ): void {
        $GLOBALS['hpr_test_products'][ $this->id ]['regular_price'] = $value;
    }

    public function set_sale_price( string $value ): void {
        $GLOBALS['hpr_test_products'][ $this->id ]['sale_price'] = $value;
    }

    public function save(): int {
        return $this->id;
    }
}

class WP_Post {
    public string $post_type = 'page';
    public string $post_title;
    public string $post_content;
    public string $post_status;

    public function __construct( int $id ) {
        $page = $GLOBALS['hpr_test_pages'][ $id ];
        $this->post_title   = $page['title'];
        $this->post_content = $page['content'];
        $this->post_status  = $page['status'];
    }
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

class HprMigrationWpdb {
    public string $prefix = 'wp_';
    public array $snippets;

    public function __construct() {
        foreach ( [ 26, 30, 34, 43, 44 ] as $id ) {
            $this->snippets[ $id ] = [ 'id' => $id, 'name' => 'Snippet ' . $id, 'active' => 1 ];
        }
    }

    public function prepare( string $query, mixed ...$args ): string {
        return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
    }

    public function get_var( string $query ): string {
        unset( $query );
        return 'wp_snippets';
    }

    public function get_results( string $query, mixed $format ): array {
        unset( $query, $format );
        return array_values( $this->snippets );
    }

    public function update( string $table, array $data, array $where, array $formats, array $where_formats ): int|false {
        unset( $table, $formats, $where_formats );
        $id = (int) $where['id'];
        if ( ! isset( $this->snippets[ $id ] ) ) {
            return false;
        }
        $this->snippets[ $id ]['active'] = (int) $data['active'];
        return 1;
    }
}

class HprMigrationActivity {
    public static function add( string $message, string $level = 'info', array $context = [], string $source = 'billing' ): void {
        unset( $message, $level, $context, $source );
    }
}

class HprMigrationReport {
    public static function clear_cache(): void {
    }
}

class_alias( HprMigrationActivity::class, 'HexaPrWire\\Billing\\Support\\Activity' );
class_alias( HprMigrationReport::class, 'HexaPrWire\\Billing\\Reports\\CommerceReport' );

define( 'ARRAY_A', 'ARRAY_A' );
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['wpdb'] = new HprMigrationWpdb();

function wc_get_orders(): array {
    return [];
}

function wc_get_product( int $product_id ): WC_Product|false {
    return isset( $GLOBALS['hpr_test_products'][ $product_id ] ) ? new WC_Product( $product_id ) : false;
}

function get_option( string $key, mixed $default = false ): mixed {
    return array_key_exists( $key, $GLOBALS['hpr_test_options'] ) ? $GLOBALS['hpr_test_options'][ $key ] : $default;
}

function add_option( string $key, mixed $value, string $deprecated = '', mixed $autoload = null ): bool {
    unset( $deprecated, $autoload );
    if ( array_key_exists( $key, $GLOBALS['hpr_test_options'] ) ) {
        return false;
    }
    $GLOBALS['hpr_test_options'][ $key ] = $value;
    return true;
}

function update_option( string $key, mixed $value, mixed $autoload = null ): bool {
    unset( $autoload );
    $GLOBALS['hpr_test_options'][ $key ] = $value;
    return true;
}

function delete_option( string $key ): bool {
    unset( $GLOBALS['hpr_test_options'][ $key ] );
    return true;
}

function wp_parse_args( array $args, array $defaults ): array {
    return array_merge( $defaults, $args );
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function get_post( int $post_id ): WP_Post|false {
    return isset( $GLOBALS['hpr_test_pages'][ $post_id ] ) ? new WP_Post( $post_id ) : false;
}

function wp_update_post( array $data, bool $wp_error = false ): int|WP_Error {
    unset( $wp_error );
    $id = (int) $data['ID'];
    if ( ! isset( $GLOBALS['hpr_test_pages'][ $id ] ) ) {
        return new WP_Error( 'missing_page', 'Page missing.' );
    }
    $GLOBALS['hpr_test_pages'][ $id ]['content'] = (string) $data['post_content'];
    return $id;
}

function is_wp_error( mixed $value ): bool {
    return $value instanceof WP_Error;
}

function is_plugin_active( string $plugin ): bool {
    return ! empty( $GLOBALS['hpr_test_plugins'][ $plugin ] );
}

function deactivate_plugins( string|array $plugins, bool $silent = false ): void {
    unset( $silent );
    foreach ( (array) $plugins as $plugin ) {
        $GLOBALS['hpr_test_plugins'][ $plugin ] = false;
    }
}

function activate_plugin( string $plugin, string $redirect = '', bool $network_wide = false, bool $silent = false ): null|WP_Error {
    unset( $redirect, $network_wide, $silent );
    $GLOBALS['hpr_test_plugins'][ $plugin ] = true;
    return null;
}

function clean_post_cache( int $post_id ): void {
    unset( $post_id );
}

function wp_cache_flush(): bool {
    return true;
}

function do_action( string $hook, mixed ...$args ): void {
    unset( $hook, $args );
}

require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Migration/LegacyCommerceMigration.php';

use HexaPrWire\Billing\Migration\LegacyCommerceMigration;

$migration = new LegacyCommerceMigration();

$GLOBALS['hpr_test_options']['hpr_billing_migration_lock'] = time();
$locked = $migration->run();
if ( ! $locked instanceof WP_Error || 'billing_migration_locked' !== $locked->get_error_code() ) {
    fwrite( STDERR, "FAIL: Concurrent migration lock was not enforced.\n" );
    exit( 1 );
}
delete_option( 'hpr_billing_migration_lock' );

$result = $migration->run();
if ( is_wp_error( $result ) || 'complete' !== ( $result['status'] ?? '' ) ) {
    fwrite( STDERR, 'FAIL: Full migration did not complete: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'unknown' ) . "\n" );
    exit( 1 );
}

$backup = get_option( LegacyCommerceMigration::BACKUP_OPTION, [] );
$migrated = true === get_option( 'hpr_billing_runtime_enabled' )
    && 'complete' === ( get_option( 'hpr_billing_migration_state', [] )['status'] ?? '' )
    && '' === $GLOBALS['hpr_test_products'][323645]['price']
    && false === $GLOBALS['hpr_test_plugins']['flexible-checkout-fields/flexible-checkout-fields.php']
    && str_contains( $GLOBALS['hpr_test_pages'][81]['content'], '[woocommerce_checkout]' )
    && str_contains( $GLOBALS['hpr_test_pages'][41]['content'], '[hpr_billing_catalog]' )
    && str_contains( $GLOBALS['hpr_test_pages'][261050]['content'], '[hpr_billing_order_portal]' )
    && '1' === (string) ( $backup['custom_product']['price'] ?? '' )
    && ! array_key_exists( 'hpr_billing_migration_lock', $GLOBALS['hpr_test_options'] );
foreach ( $GLOBALS['wpdb']->snippets as $snippet ) {
    $migrated = $migrated && 0 === $snippet['active'];
}
if ( ! $migrated ) {
    fwrite( STDERR, "FAIL: Migration did not transfer every owned surface.\n" );
    exit( 1 );
}

$rollback = $migration->rollback();
if ( is_wp_error( $rollback ) || 'rolled_back' !== ( $rollback['status'] ?? '' ) ) {
    fwrite( STDERR, "FAIL: Rollback did not complete.\n" );
    exit( 1 );
}

$restored = false === get_option( 'hpr_billing_runtime_enabled' )
    && 'rolled_back' === ( get_option( 'hpr_billing_migration_state', [] )['status'] ?? '' )
    && '1' === $GLOBALS['hpr_test_products'][323645]['price']
    && '1' === $GLOBALS['hpr_test_products'][323645]['regular_price']
    && true === $GLOBALS['hpr_test_plugins']['flexible-checkout-fields/flexible-checkout-fields.php']
    && 'legacy checkout' === $GLOBALS['hpr_test_pages'][81]['content']
    && '' === $GLOBALS['hpr_test_pages'][41]['content']
    && '' === $GLOBALS['hpr_test_pages'][261050]['content']
    && ! array_key_exists( 'hpr_billing_migration_lock', $GLOBALS['hpr_test_options'] );
foreach ( $GLOBALS['wpdb']->snippets as $snippet ) {
    $restored = $restored && 1 === $snippet['active'];
}
if ( ! $restored ) {
    fwrite( STDERR, "FAIL: Rollback did not restore every backed-up surface.\n" );
    exit( 1 );
}

echo "PASS: Locked migration and rollback transfer and restore every owned surface.\n";
