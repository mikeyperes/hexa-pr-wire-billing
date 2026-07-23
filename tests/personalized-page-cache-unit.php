<?php

declare(strict_types=1);

$GLOBALS['hpr_cache_options'] = [
    'hpr_billing_runtime_enabled' => false,
    'hpr_billing_settings'        => [
        'packages_page_id' => 41,
        'submit_page_id'   => 261050,
        'checkout_page_id' => 81,
    ],
];
$GLOBALS['hpr_cache_page_id'] = 999;
$GLOBALS['hpr_cache_headers'] = 0;
$GLOBALS['hpr_cache_actions'] = [];
$GLOBALS['hpr_cache_hooks']   = [];

function get_option( string $key, mixed $default = false ): mixed {
    return $GLOBALS['hpr_cache_options'][ $key ] ?? $default;
}

function wp_parse_args( array $args, array $defaults ): array {
    return array_merge( $defaults, $args );
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function add_action( string $hook, callable $callback, int $priority = 10 ): void {
    $GLOBALS['hpr_cache_hooks'][] = compact( 'hook', 'callback', 'priority' );
}

function do_action( string $hook, mixed ...$arguments ): void {
    $GLOBALS['hpr_cache_actions'][] = [ 'hook' => $hook, 'arguments' => $arguments ];
}

function is_page( array|int $page ): bool {
    return in_array( $GLOBALS['hpr_cache_page_id'], (array) $page, true );
}

function nocache_headers(): void {
    $GLOBALS['hpr_cache_headers']++;
}

require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';
require dirname( __DIR__ ) . '/src/Commerce/Cache/PersonalizedPageCache.php';

use HexaPrWire\Billing\Commerce\Cache\PersonalizedPageCache;

$policy = new PersonalizedPageCache();
$policy->register();

if ( 1 !== count( $GLOBALS['hpr_cache_hooks'] ) || 'template_redirect' !== $GLOBALS['hpr_cache_hooks'][0]['hook'] || -100 !== $GLOBALS['hpr_cache_hooks'][0]['priority'] ) {
    fwrite( STDERR, "FAIL: Personalized cache policy did not register at the expected early template priority.\n" );
    exit( 1 );
}

$policy->disable_page_cache();
if ( 0 !== $GLOBALS['hpr_cache_headers'] || [] !== $GLOBALS['hpr_cache_actions'] ) {
    fwrite( STDERR, "FAIL: Cache policy changed headers while the billing runtime was disabled.\n" );
    exit( 1 );
}

$GLOBALS['hpr_cache_options']['hpr_billing_runtime_enabled'] = true;
$policy->disable_page_cache();
if ( 0 !== $GLOBALS['hpr_cache_headers'] || [] !== $GLOBALS['hpr_cache_actions'] ) {
    fwrite( STDERR, "FAIL: Cache policy changed headers on an unrelated page.\n" );
    exit( 1 );
}

$GLOBALS['hpr_cache_page_id'] = 41;
$policy->disable_page_cache();
if ( ! defined( 'DONOTCACHEPAGE' ) || true !== DONOTCACHEPAGE || 1 !== $GLOBALS['hpr_cache_headers'] ) {
    fwrite( STDERR, "FAIL: Personalized billing page was not marked non-cacheable.\n" );
    exit( 1 );
}

$last_action = end( $GLOBALS['hpr_cache_actions'] );
if ( 'litespeed_control_set_nocache' !== ( $last_action['hook'] ?? '' ) || 'Hexa PR Wire personalized billing page' !== ( $last_action['arguments'][0] ?? '' ) ) {
    fwrite( STDERR, "FAIL: LiteSpeed did not receive the personalized-page no-cache signal.\n" );
    exit( 1 );
}

echo "PASS: Personalized billing pages bypass full-page caches without affecting unrelated requests.\n";
