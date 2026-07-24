<?php
/**
 * Plugin Name: Hexa PR Wire Billing
 * Description: Billing, checkout, customer pricing, payment policy, and fulfillment for Hexa PR Wire.
 * Author: Michael Peres
 * Plugin URI: https://github.com/mikeyperes/hexa-pr-wire-billing
 * Version: 1.0.3
 * Text Domain: hexa-pr-wire-billing
 * Domain Path: /languages
 * Author URI: https://michaelperes.com
 * GitHub Plugin URI: https://github.com/mikeyperes/hexa-pr-wire-billing/
 * GitHub Branch: main
 * Requires PHP: 8.0
 * Requires at least: 6.5
 */

namespace HexaPrWire\Billing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HPR_BILLING_FILE', __FILE__ );

require_once __DIR__ . '/src/Support/Autoloader.php';
Support\Autoloader::register( __DIR__ . '/src' );

$hexa_plugin_core_root = __DIR__ . '/lib/hexa-wordpress-plugin-core';
require_once $hexa_plugin_core_root . '/bootstrap.php';
\hexa_plugin_core_register_package(
    'hexa-pr-wire-billing',
    $hexa_plugin_core_root,
    [ 'minimum_version' => '0.19.73' ]
);

final class Config {
    public const VERSION = '1.0.3';

    public static string $plugin_name        = 'Hexa PR Wire Billing';
    public static string $plugin_slug        = 'hexa-pr-wire-billing';
    public static string $plugin_folder_name = 'hexa-pr-wire-billing';
    public static string $plugin_file        = 'hexa-pr-wire-billing.php';

    public static string $settings_page_name          = 'Hexa PR Wire Billing';
    public static string $settings_page_capability    = 'manage_woocommerce';
    public static string $settings_page_slug          = 'hexa-pr-wire-billing';
    public static string $settings_page_display_title = 'Hexa PR Wire Billing';

    public static string $github_repo   = 'mikeyperes/hexa-pr-wire-billing';
    public static string $github_branch = 'main';

    public static function plugin_basename(): string {
        return plugin_basename( __FILE__ );
    }
}

function updater_config(): \Hexa\PluginCore\PluginUpdates\UpdaterConfig {
    static $config = null;

    if ( $config instanceof \Hexa\PluginCore\PluginUpdates\UpdaterConfig ) {
        return $config;
    }

    $config = \Hexa\PluginCore\PluginUpdates\UpdaterConfig::from_plugin_file(
        __FILE__,
        Config::$github_repo,
        [
            'plugin_slug'               => Config::$plugin_folder_name,
            'proper_folder_name'        => Config::$plugin_folder_name,
            'runtime_folder_name'       => Config::$plugin_folder_name,
            'plugin_basename'           => Config::plugin_basename(),
            'canonical_plugin_basename' => Config::$plugin_folder_name . '/' . Config::$plugin_file,
            'plugin_starter_file'       => Config::$plugin_file,
            'github_branch'             => Config::$github_branch,
            'requires'                  => '6.5',
            'tested'                    => '7.0',
            'nonce_action'              => Admin\Ajax::NONCE,
            'nonce_param'               => 'nonce',
            'ajax_action_prefix'        => 'hpr_billing_updater',
            'progress_key'              => 'hpr_billing_update_progress',
        ]
    );

    return $config;
}

function core_package_config(): \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig {
    static $config = null;

    if ( $config instanceof \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig ) {
        return $config;
    }

    $config = \Hexa\PluginCore\CorePackageUpdates\CorePackageConfig::from_core_root(
        __DIR__ . '/lib/hexa-wordpress-plugin-core',
        [
            'github_repo'        => 'mikeyperes/hexa-wordpress-plugin-core',
            'github_branch'      => 'main',
            'nonce_action'       => Admin\Ajax::NONCE,
            'nonce_param'        => 'nonce',
            'ajax_action_prefix' => 'hpr_billing_core_package',
            'cache_key'          => 'hpr_billing_core_package',
        ]
    );

    return $config;
}

function declare_woocommerce_compatibility(): void {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_woocommerce_compatibility' );

function boot_plugin(): void {
    static $plugin = null;

    if ( ! $plugin instanceof Bootstrap\Plugin ) {
        $plugin = new Bootstrap\Plugin();
    }

    $plugin->boot();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot_plugin', 30 );

function activate_plugin(): void {
    Settings\SettingsRepository::ensure_defaults();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );
