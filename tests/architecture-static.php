<?php

declare(strict_types=1);

$root       = dirname( __DIR__ );
$main       = (string) file_get_contents( $root . '/hexa-pr-wire-billing.php' );
$legacy     = (string) file_get_contents( $root . '/initialization.php' );
$bootstrap  = (string) file_get_contents( $root . '/src/Bootstrap/Plugin.php' );
$controller = (string) file_get_contents( $root . '/src/Admin/Dashboard/DashboardController.php' );
$navigation = (string) file_get_contents( $root . '/src/Admin/Navigation/AdminNavigation.php' );
$dashboard  = (string) file_get_contents( $root . '/src/Admin/Dashboard/SectionRenderer.php' );
$checkout   = (string) file_get_contents( $root . '/src/Commerce/Checkout/CheckoutFields.php' );
$cart       = (string) file_get_contents( $root . '/src/Commerce/Cart/ManagedCart.php' );
$migration  = (string) file_get_contents( $root . '/src/Migration/LegacyCommerceMigration.php' );
$gateways   = (string) file_get_contents( $root . '/src/Commerce/Payments/GatewayPolicy.php' );
$cache      = (string) file_get_contents( $root . '/src/Commerce/Cache/PersonalizedPageCache.php' );

$requirements = [
    [ $main, "hexa_plugin_core_register_package", 'Core package registration' ],
    [ $bootstrap, 'new PluginContext', 'Core PluginContext integration' ],
    [ $bootstrap, 'new CoreBootstrap', 'Core bootstrap integration' ],
    [ $controller, 'new HostTabsRenderer', 'Core host tab renderer' ],
    [ $controller, "'layout'           => 'sidebar'", 'Core sidebar tab layout' ],
    [ $controller, 'new PluginUpdateStatus', 'Plugin Git identity reporting' ],
    [ $controller, 'new CorePackageStatus', 'Core Git identity reporting' ],
    [ $navigation, 'new TabRegistry', 'Core tab registry' ],
    [ $navigation, 'new TabDefinition', 'Core tab definitions' ],
    [ $dashboard, 'SnippetsTableRenderer', 'Core feature renderer' ],
    [ $dashboard, 'FieldStructureRenderer', 'Core ACF structure renderer' ],
    [ $dashboard, 'UpdaterPanelRenderer', 'Git updater reporting' ],
    [ $dashboard, 'CorePackagePanelRenderer', 'Core package reporting' ],
    [ $checkout, "woocommerce_email_order_meta_fields', [ \$this, 'email_order_fields' ], 20, 3", 'Woo email hook signature' ],
    [ $cart, 'woocommerce_check_cart_items', 'Checkout cart entitlement validation' ],
    [ $migration, 'recover_failed_migration', 'Automatic failed-migration recovery' ],
    [ $migration, 'claim_custom_carrier', 'Custom carrier fallback-price removal' ],
    [ $migration, 'hpr_billing_migration_lock', 'Atomic migration lock' ],
    [ $gateways, 'wc_stripe_get_element_options', 'Stripe UPM ACH constraint' ],
    [ $gateways, 'hpr_billing_ach_required', 'Server-side payment-method enforcement' ],
    [ $cache, 'DONOTCACHEPAGE', 'Personalized page-cache bypass' ],
    [ $cache, 'litespeed_control_set_nocache', 'LiteSpeed personalized-page bypass' ],
];

foreach ( $requirements as [ $source, $needle, $label ] ) {
    if ( ! str_contains( $source, $needle ) ) {
        fwrite( STDERR, "FAIL: Missing {$label}.\n" );
        exit( 1 );
    }
}

foreach ( [ "empty_cart()", "\$_REQUEST['price']", "\$_GET['price']" ] as $forbidden ) {
    if ( str_contains( $cart, $forbidden ) ) {
        fwrite( STDERR, "FAIL: Managed cart contains forbidden legacy pattern {$forbidden}.\n" );
        exit( 1 );
    }
}

preg_match( '/^ \* Version: ([0-9.]+)$/m', $main, $header_version );
preg_match( "/public const VERSION = '([0-9.]+)';/", $main, $config_version );
preg_match( '/^ \* Version: ([0-9.]+)$/m', $legacy, $legacy_version );
$manifest_version = trim( (string) file_get_contents( $root . '/VERSION' ) );
$release_versions = [
    $header_version[1] ?? '',
    $config_version[1] ?? '',
    $legacy_version[1] ?? '',
    $manifest_version,
];
if ( '' === $manifest_version || 1 !== count( array_unique( $release_versions ) ) ) {
    fwrite( STDERR, 'FAIL: Plugin release version sources do not agree: ' . implode( ', ', $release_versions ) . ".\n" );
    exit( 1 );
}

$version = trim( (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/VERSION' ) );
$hash    = trim( (string) file_get_contents( $root . '/lib/hexa-wordpress-plugin-core/PACKAGE_HASH' ) );
if ( '0.19.73' !== $version || 'f62aa1db620b66ae7822cd0dadc66525f181fc53d973106a8b29301b95e10575' !== $hash ) {
    fwrite( STDERR, "FAIL: Vendored Core version or package hash is not the audited package.\n" );
    exit( 1 );
}

echo "PASS: Architecture, hook contracts, migration recovery, and Core package identity are present.\n";
