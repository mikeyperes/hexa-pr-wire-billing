<?php

declare(strict_types=1);

function sanitize_key( string $value ): string {
    return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '' );
}

function apply_filters( string $hook, mixed $value, mixed ...$arguments ): mixed {
    unset( $arguments );
    if ( 'hpr_billing_dashboard_tabs' === $hook && is_array( $value ) ) {
        $value['extension_diagnostics'] = 'Extension Diagnostics';
    }

    return $value;
}

function add_query_arg( array $args, string $url ): string {
    return $url . '?' . http_build_query( $args );
}

require dirname( __DIR__ ) . '/src/Admin/Navigation/AdminRoute.php';
require dirname( __DIR__ ) . '/src/Admin/Navigation/AdminNavigation.php';

use HexaPrWire\Billing\Admin\Navigation\AdminNavigation;

$navigation = new AdminNavigation();
$areas      = $navigation->areas();

if ( array_keys( $areas ) !== [ 'overview', 'commerce', 'customers', 'reporting', 'advanced' ] ) {
    fwrite( STDERR, "FAIL: Dashboard must expose five ordered areas.\n" );
    exit( 1 );
}

$legacy = $navigation->resolve( 'payments' );
if ( 'commerce' !== $legacy->area() || 'payments' !== $legacy->section() ) {
    fwrite( STDERR, "FAIL: Legacy Payments route did not resolve to Commerce.\n" );
    exit( 1 );
}

$modern = $navigation->resolve( 'reporting', 'integrity' );
if ( 'reporting' !== $modern->area() || 'integrity' !== $modern->section() ) {
    fwrite( STDERR, "FAIL: Reporting Integrity route did not resolve.\n" );
    exit( 1 );
}

$core = $navigation->resolve( 'hexa-core' );
if ( 'advanced' !== $core->area() || 'hexa_core' !== $core->section() ) {
    fwrite( STDERR, "FAIL: Legacy Hexa Core route did not resolve to Advanced.\n" );
    exit( 1 );
}

$core_section = $navigation->resolve( 'advanced', 'hexa-core' );
if ( 'advanced' !== $core_section->area() || 'hexa_core' !== $core_section->section() ) {
    fwrite( STDERR, "FAIL: Hexa Core section alias did not resolve under Advanced.\n" );
    exit( 1 );
}

$extension = $navigation->resolve( 'extension_diagnostics' );
if ( 'advanced' !== $extension->area() || 'extension_diagnostics' !== $extension->section() ) {
    fwrite( STDERR, "FAIL: Extension tabs must remain reachable under Advanced.\n" );
    exit( 1 );
}

echo "PASS: Five-area navigation preserves legacy, Core, and extension routes.\n";
