<?php

declare(strict_types=1);

$GLOBALS['hpr_test_options'] = [];

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

function wp_parse_args( array $args, array $defaults ): array {
    return array_merge( $defaults, $args );
}

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function sanitize_title( string $value ): string {
    return trim( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $value ) ?: '' ), '-' );
}

function sanitize_email( string $value ): string {
    return filter_var( $value, FILTER_VALIDATE_EMAIL ) ? strtolower( $value ) : '';
}

require dirname( __DIR__ ) . '/src/Settings/SettingsRepository.php';

use HexaPrWire\Billing\Settings\SettingsRepository;

SettingsRepository::ensure_defaults();

if ( true === SettingsRepository::runtime_enabled() ) {
    fwrite( STDERR, "FAIL: Activation defaults must leave commerce runtime disabled.\n" );
    exit( 1 );
}

foreach ( SettingsRepository::FEATURE_OPTIONS as $feature => $option ) {
    if ( true !== get_option( $option ) ) {
        fwrite( STDERR, "FAIL: Feature {$feature} did not receive its enabled default.\n" );
        exit( 1 );
    }
}

$updated = SettingsRepository::update(
    [
        'standard_product_id' => '-901',
        'standard_category'   => ' Press Releases ',
        'support_email'       => 'BILLING@EXAMPLE.COM',
    ]
);

if ( 901 !== $updated['standard_product_id'] || 'press-releases' !== $updated['standard_category'] || 'billing@example.com' !== $updated['support_email'] ) {
    fwrite( STDERR, "FAIL: Settings normalization did not preserve the typed contract.\n" );
    exit( 1 );
}

echo "PASS: Settings defaults, runtime guard, features, and normalization are stable.\n";

