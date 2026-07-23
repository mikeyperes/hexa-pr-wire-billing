<?php

namespace HexaPrWire\Billing\Admin\Ajax;

use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use HexaPrWire\Billing\Admin\Dashboard;
use HexaPrWire\Billing\Config;
use HexaPrWire\Billing\Migration\LegacyCommerceMigration;
use HexaPrWire\Billing\Reports\CommerceReport;
use HexaPrWire\Billing\Settings\SettingsRepository;
use HexaPrWire\Billing\Support\Activity;
use HexaPrWire\Billing\Support\FeatureDefinitions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AjaxController {
    public const NONCE = 'hpr_billing_admin';

    public function register(): void {
        ( new AjaxActionRegistry(
            [
                'capability'   => Config::$settings_page_capability,
                'nonce_action' => self::NONCE,
                'nonce_field'  => 'nonce',
                'logger'       => static function ( \Throwable $throwable ): void {
                    error_log( '[Hexa PR Wire Billing] AJAX error: ' . $throwable->getMessage() );
                },
            ]
        ) )->register(
            [
                'hpr_billing_load_tab'        => [ 'callback' => [ $this, 'load_tab' ] ],
                'hpr_billing_save_settings'   => [ 'callback' => [ $this, 'save_settings' ] ],
                'hpr_billing_feature_toggle'  => [ 'callback' => [ $this, 'toggle_feature' ] ],
                'hpr_billing_feature_test'    => [ 'callback' => [ $this, 'test_feature' ] ],
                'hpr_billing_save_field_structure' => [ 'callback' => [ $this, 'save_field_structure' ] ],
                'hpr_billing_refresh_report'  => [ 'callback' => [ $this, 'refresh_report' ] ],
                'hpr_billing_run_migration'   => [ 'callback' => [ $this, 'run_migration' ] ],
                'hpr_billing_run_rollback'    => [ 'callback' => [ $this, 'run_rollback' ] ],
            ]
        );
    }

    public static function nonce(): string {
        return AjaxGuard::create_nonce( self::NONCE );
    }

    public function load_tab( AjaxRequest $request ): array {
        $tab = $request->key( 'tab', 'overview', 'post' );

        return ( new Dashboard() )->tab_fragment( $tab );
    }

    public function save_settings( AjaxRequest $request ): array {
        $changes = [];
        foreach ( [
            'standard_product_id',
            'premium_product_id',
            'writing_product_id',
            'custom_product_id',
            'packages_page_id',
            'submit_page_id',
            'checkout_page_id',
        ] as $key ) {
            if ( $request->has( $key, 'post' ) ) {
                $changes[ $key ] = $request->int( $key, 0, 'post' );
            }
        }

        foreach ( [ 'standard_category', 'custom_category' ] as $key ) {
            if ( $request->has( $key, 'post' ) ) {
                $slug = $request->title_slug( $key, '', 'post' );
                if ( '' === $slug ) {
                    throw AjaxFailure::bad_request( 'Category slugs cannot be empty.', 'invalid_category_slug' );
                }
                $changes[ $key ] = $slug;
            }
        }

        if ( $request->has( 'support_email', 'post' ) ) {
            $email = sanitize_email( $request->text( 'support_email', '', 'post' ) );
            if ( '' === $email || ! is_email( $email ) ) {
                throw AjaxFailure::bad_request( 'Enter a valid support email address.', 'invalid_support_email' );
            }
            $changes['support_email'] = $email;
        }

        if ( [] === $changes ) {
            throw AjaxFailure::bad_request( 'No supported billing setting was provided.', 'empty_settings' );
        }

        $settings = SettingsRepository::update( $changes );
        CommerceReport::clear_cache();
        Activity::add( 'Billing settings updated.', 'success', [ 'keys' => array_keys( $changes ) ], 'settings' );

        return [
            'settings' => $settings,
            'message'  => 'Billing settings saved.',
        ];
    }

    public function toggle_feature( AjaxRequest $request ): array {
        $id         = $request->key( 'snippet_id', '', 'post' );
        $definition = FeatureDefinitions::definition( $id );
        if ( null === $definition ) {
            throw AjaxFailure::not_found( 'Unknown billing feature.', 'unknown_feature' );
        }

        $registry = FeatureDefinitions::registry();
        $enabled  = $request->bool( 'enable', false, 'post' );
        if ( ! $registry->set_enabled( $id, $enabled ) ) {
            throw AjaxFailure::server_error( 'The feature state could not be saved.', 'feature_save_failed' );
        }

        Activity::add(
            $enabled ? 'Billing feature enabled.' : 'Billing feature disabled.',
            $enabled ? 'success' : 'warning',
            [ 'feature' => $id ],
            'features'
        );

        return [
            'snippet_id' => $id,
            'enabled'    => $registry->is_enabled( $id ),
            'message'    => $enabled ? 'Feature enabled.' : 'Feature disabled.',
            'test'       => $registry->test( $id ),
        ];
    }

    public function test_feature( AjaxRequest $request ): array {
        $id = $request->key( 'snippet_id', '', 'post' );
        if ( null === FeatureDefinitions::definition( $id ) ) {
            throw AjaxFailure::not_found( 'Unknown billing feature.', 'unknown_feature' );
        }

        return FeatureDefinitions::registry()->test( $id );
    }

    public function save_field_structure( AjaxRequest $request ): array {
        $option = SettingsRepository::FEATURE_OPTIONS['acf_fields'];
        if ( ! $request->has( $option, 'post' ) ) {
            throw AjaxFailure::bad_request( 'No supported field structure setting was provided.', 'missing_field_setting' );
        }

        $enabled = $request->bool( $option, false, 'post' );
        update_option( $option, $enabled, false );
        Activity::add(
            $enabled ? 'Billing ACF structures enabled.' : 'Billing ACF structures disabled.',
            $enabled ? 'success' : 'warning',
            [],
            'fields'
        );

        return [
            'enabled' => $enabled,
            'message' => $enabled ? 'Billing field structures enabled.' : 'Billing field structures disabled.',
        ];
    }

    public function refresh_report( AjaxRequest $request ): array {
        unset( $request );
        CommerceReport::clear_cache();
        $report = new CommerceReport();

        return [
            'summary'   => $report->summary( true ),
            'integrity' => $report->integrity_checks(),
            'message'   => 'Commerce report refreshed.',
        ];
    }

    public function run_migration( AjaxRequest $request ): array|\WP_Error {
        if ( 'MIGRATE' !== $request->text( 'confirmation', '', 'post' ) ) {
            throw AjaxFailure::bad_request( 'Type MIGRATE to transfer billing ownership.', 'migration_confirmation_required' );
        }

        return ( new LegacyCommerceMigration() )->run();
    }

    public function run_rollback( AjaxRequest $request ): array|\WP_Error {
        if ( 'ROLLBACK' !== $request->text( 'confirmation', '', 'post' ) ) {
            throw AjaxFailure::bad_request( 'Type ROLLBACK to restore legacy billing ownership.', 'rollback_confirmation_required' );
        }

        return ( new LegacyCommerceMigration() )->rollback();
    }
}
