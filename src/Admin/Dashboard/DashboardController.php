<?php

namespace HexaPrWire\Billing\Admin\Dashboard;

use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;
use HexaPrWire\Billing\Admin\Ajax;
use HexaPrWire\Billing\Admin\Navigation\AdminNavigation;
use HexaPrWire\Billing\Admin\Navigation\SectionNavigation;
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
        $requested  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        $section    = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
        $route      = $navigation->resolve( $requested, $section );
        $tabs       = $navigation->areas();
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
                    'active'          => $route->area(),
                    'page_url'        => $this->page_url(),
                    'ajax_action'     => 'hpr_billing_load_tab',
                    'nonce'           => Ajax::nonce(),
                    'nonce_field'     => 'nonce',
                    'root_id'         => 'hpr-billing-tabs',
                    'panel_id'        => 'hpr-billing-tab-panel',
                    'label'           => 'Hexa PR Wire Billing sections',
                    'render_callback' => function ( string $area ) use ( $route ): void {
                        $section = $area === $route->area() ? $route->section() : '';
                        $this->render_area( $area, $section );
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
        $route      = $navigation->resolve( $id );
        $tabs       = $navigation->areas();

        ob_start();
        $this->render_area( $route->area(), $route->section() );
        $html = ob_get_clean();

        return [
            'tab'   => $route->area(),
            'label' => (string) ( $tabs[ $route->area() ] ?? $route->area() ),
            'html'  => is_string( $html ) ? $html : '',
        ];
    }

    private function render_area( string $area, string $section = '' ): void {
        $navigation = $this->navigation();
        $route      = $navigation->resolve( $area, $section );

        ( new SectionNavigation( $navigation ) )->render( $route, $this->page_url() );

        if ( apply_filters( 'hpr_billing_render_dashboard_tab', false, $route->section() ) ) {
            return;
        }

        ( new SectionRenderer() )->render( $route->section() );
    }

    private function navigation(): AdminNavigation {
        return new AdminNavigation();
    }

    private function page_url(): string {
        return admin_url( 'admin.php?page=' . Config::$settings_page_slug );
    }
}
