<?php

namespace HexaPrWire\Billing\Admin\Dashboard;

use Hexa\PluginCore\CorePackageUpdates\CorePackageStatus;
use Hexa\PluginCore\PluginUpdates\PluginUpdateStatus;
use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;
use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;
use HexaPrWire\Billing\Admin\Ajax;
use HexaPrWire\Billing\Admin\Navigation\AdminNavigation;
use HexaPrWire\Billing\Config;
use HexaPrWire\Billing\Support\Dependencies;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardController {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ], 30 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function add_settings_page(): void {
        add_submenu_page(
            Dependencies::woocommerce_active() ? 'woocommerce' : 'options-general.php',
            Config::$settings_page_name,
            Config::$settings_page_name,
            Config::$settings_page_capability,
            Config::$settings_page_slug,
            [ $this, 'render' ]
        );
    }

    public function enqueue_admin_assets(): void {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( Config::$settings_page_slug !== $page ) {
            return;
        }

        wp_enqueue_style(
            'hpr-billing-admin',
            plugins_url( 'assets/admin/dashboard.css', dirname( __DIR__, 3 ) . '/hexa-pr-wire-billing.php' ),
            [],
            Config::VERSION
        );
        wp_enqueue_script(
            'hpr-billing-admin',
            plugins_url( 'assets/admin/dashboard.js', dirname( __DIR__, 3 ) . '/hexa-pr-wire-billing.php' ),
            [],
            Config::VERSION,
            true
        );
    }

    public function render(): void {
        if ( ! current_user_can( Config::$settings_page_capability ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'hexa-pr-wire-billing' ) );
        }

        $navigation = $this->navigation();
        $registry   = $this->tab_registry( $navigation );
        $tabs       = $registry->all();
        $requested  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        $section    = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
        $route      = $navigation->resolve( $requested, $section );
        $active     = $route->section();
        ?>
        <div class="wrap hpr-billing-admin" data-hpr-billing-admin data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( Ajax::nonce() ); ?>">
            <div class="hpr-billing-titlebar">
                <div>
                    <p class="hpr-billing-kicker"><?php echo esc_html__( 'Commerce control', 'hexa-pr-wire-billing' ); ?></p>
                    <h1><?php echo esc_html( Config::$settings_page_display_title ); ?></h1>
                </div>
                <span class="hpr-billing-version">v<?php echo esc_html( Config::VERSION ); ?></span>
            </div>
            <?php
            ( new HostTabsRenderer() )->render(
                [
                    'tabs'            => $tabs,
                    'active'          => $active,
                    'page_url'        => $this->page_url(),
                    'ajax_action'     => 'hpr_billing_load_tab',
                    'nonce'           => Ajax::nonce(),
                    'nonce_field'     => 'nonce',
                    'root_id'         => 'hpr-billing-core-tabs',
                    'panel_id'        => 'hpr-billing-tab-panel',
                    'label'           => 'Hexa PR Wire Billing sections',
                    'layout'           => 'sidebar',
                    'groups'           => $navigation->groups(),
                    'sidebar_identity' => $this->sidebar_identity(),
                    'sidebar_collapsible' => true,
                    'sidebar_collapsed'   => false,
                    'sidebar_persist'     => true,
                    'render_callback' => function ( string $tab ) use ( $registry ): void {
                        $this->render_registered_tab( $registry, $tab );
                    },
                ]
            );
            ?>
            <div class="hpr-billing-toast" data-hpr-billing-toast role="status" aria-live="polite"></div>
        </div>
        <?php
    }

    public function tab_fragment( string $id ): array {
        $navigation = $this->navigation();
        $registry   = $this->tab_registry( $navigation );
        $route      = $navigation->resolve( $id );
        $tab_id     = $route->section();
        $definition = $registry->get( $tab_id ) ?? $registry->get( 'overview' );

        if ( ! $definition instanceof TabDefinition ) {
            return [
                'tab'   => 'overview',
                'label' => 'Overview',
                'html'  => '',
            ];
        }

        $tab_id = $definition->id;

        ob_start();
        $this->render_registered_tab( $registry, $tab_id );
        $html = ob_get_clean();

        return [
            'tab'   => $tab_id,
            'label' => $this->tab_label( $definition ),
            'html'  => is_string( $html ) ? $html : '',
        ];
    }

    private function tab_registry( ?AdminNavigation $navigation = null ): TabRegistry {
        $navigation = $navigation ?? $this->navigation();

        return $navigation->registry(
            function ( string $id ): void {
                $this->render_tab( $id );
            },
            Config::$settings_page_capability
        );
    }

    private function render_registered_tab( TabRegistry $registry, string $id ): void {
        $definition = $registry->get( $id ) ?? $registry->get( 'overview' );
        if ( ! $definition instanceof TabDefinition ) {
            return;
        }

        if (
            null !== $definition->capability
            && '' !== $definition->capability
            && ! current_user_can( $definition->capability )
        ) {
            echo '<div class="notice notice-error"><p>You do not have permission to view this section.</p></div>';
            return;
        }

        if ( is_callable( $definition->renderer ) ) {
            call_user_func( $definition->renderer );
        }
    }

    private function tab_label( TabDefinition $definition ): string {
        return $definition->label . ( $definition->deprecated ? ' (Deprecated)' : '' );
    }

    private function render_tab( string $id ): void {
        if ( apply_filters( 'hpr_billing_render_dashboard_tab', false, $id ) ) {
            return;
        }

        ( new SectionRenderer() )->render( $id );
    }

    /**
     * @return array<string,string>
     */
    private function sidebar_identity(): array {
        $plugin_status = ( new PluginUpdateStatus( \HexaPrWire\Billing\updater_config() ) )->get();
        $core_status   = ( new CorePackageStatus( \HexaPrWire\Billing\core_package_config() ) )->get();

        return [
            'plugin_name'     => (string) ( $plugin_status['plugin_name'] ?? Config::$plugin_name ),
            'current_version' => (string) ( $plugin_status['current_version'] ?? Config::VERSION ),
            'github_version'  => (string) ( $plugin_status['latest_version'] ?? 'Unknown' ),
            'github_url'      => (string) ( $plugin_status['github_url'] ?? 'https://github.com/' . Config::$github_repo ),
            'core_name'       => 'Hexa WP Core',
            'core_version'    => (string) ( $core_status['current_version'] ?? 'Unknown' ),
            'core_github_url' => (string) ( $core_status['github_url'] ?? 'https://github.com/mikeyperes/hexa-wordpress-plugin-core' ),
        ];
    }

    private function navigation(): AdminNavigation {
        return new AdminNavigation();
    }

    private function page_url(): string {
        return admin_url( 'admin.php?page=' . Config::$settings_page_slug );
    }
}
