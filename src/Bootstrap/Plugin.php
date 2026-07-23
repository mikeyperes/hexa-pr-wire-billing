<?php

namespace HexaPrWire\Billing\Bootstrap;

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CorePackageUpdates\CorePackageAjaxController;
use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\UpdaterAjaxController;
use Hexa\PluginCore\WpAdminTabs\CoreTabConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabModule;
use HexaPrWire\Billing\Admin;
use HexaPrWire\Billing\Cli;
use HexaPrWire\Billing\Commerce;
use HexaPrWire\Billing\Config;
use HexaPrWire\Billing\Fields;
use HexaPrWire\Billing\Reports\CommerceReport;

final class Plugin {
    private bool $booted = false;

    public function boot(): void {
        if ( $this->booted ) {
            return;
        }

        $context = new PluginContext(
            [
                'slug'        => Config::$plugin_slug,
                'basename'    => Config::plugin_basename(),
                'version'     => Config::VERSION,
                'path'        => dirname( __DIR__, 2 ) . '/',
                'url'         => plugin_dir_url( dirname( __DIR__, 2 ) . '/hexa-pr-wire-billing.php' ),
                'github_repo' => Config::$github_repo,
                'admin_page'  => Config::$settings_page_slug,
                'capability'  => Config::$settings_page_capability,
            ]
        );

        $core = new CoreBootstrap( $context );
        foreach ( $this->runtime_modules() as $module ) {
            $core->add_module( new ModuleAdapter( $module ) );
        }

        if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            $core->add_module( new ModuleAdapter( new \HexaPrWire\Billing\Support\Dependencies() ) );
            $core->add_module( new GitHubPluginUpdater( \HexaPrWire\Billing\updater_config() ) );
            $core->add_module( new UpdaterAjaxController( \HexaPrWire\Billing\updater_config() ) );
            $core->add_module( new CorePackageAjaxController( \HexaPrWire\Billing\core_package_config() ) );
            $core->add_module( $this->core_tab_module() );
            $core->add_module( new ModuleAdapter( new Admin\Ajax() ) );
            $core->add_module( new ModuleAdapter( new Admin\Dashboard() ) );
            $core->add_module( new ModuleAdapter( new Admin\OrderPortal() ) );
        }

        $core->boot();
        CommerceReport::register_invalidation();

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            Cli\Commands::register();
        }

        $this->booted = true;
    }

    /**
     * @return array<int,object>
     */
    private function runtime_modules(): array {
        return [
            new Commerce\Cache\PersonalizedPageCache(),
            new Fields\AcfFields(),
            new Commerce\Cart\ManagedCart(),
            new Commerce\Checkout\CheckoutFields(),
            new Commerce\Payments\GatewayPolicy(),
            new Commerce\Payments\StripeDescription(),
            new Commerce\Fulfillment\OrderFulfillment(),
            new Commerce\Catalog\CatalogShortcode(),
        ];
    }

    private function core_tab_module(): CoreTabModule {
        $plugin_root = dirname( __DIR__, 2 );

        return new CoreTabModule(
            new CoreTabConfig(
                [
                    'tab_id'        => 'hexa_core',
                    'label'         => 'Hexa WP Core',
                    'tabs_filter'   => 'hpr_billing_dashboard_tabs',
                    'render_filter' => 'hpr_billing_render_dashboard_tab',
                    'capability'    => Config::$settings_page_capability,
                    'core_root'     => $plugin_root . '/lib/hexa-wordpress-plugin-core',
                    'readme_path'   => $plugin_root . '/lib/hexa-wordpress-plugin-core/README.md',
                    'library_path'  => $plugin_root . '/HEXA_PLUGIN_CORE_LIBRARY.md',
                ]
            )
        );
    }
}
