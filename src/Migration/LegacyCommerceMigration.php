<?php

namespace HexaPrWire\Billing\Migration;

use HexaPrWire\Billing\Reports\CommerceReport;
use HexaPrWire\Billing\Settings\SettingsRepository;
use HexaPrWire\Billing\Support\Activity;

final class LegacyCommerceMigration {
    public const BACKUP_OPTION = 'hpr_billing_migration_backup';
    private const FCF_PLUGIN   = 'flexible-checkout-fields/flexible-checkout-fields.php';
    private const LOCK_OPTION  = 'hpr_billing_migration_lock';
    private const LOCK_TTL     = 300;
    private const SNIPPET_IDS  = [ 26, 30, 34, 43, 44 ];

    public function run(): array|\WP_Error {
        if ( SettingsRepository::runtime_enabled() && 'complete' === ( SettingsRepository::migration_state()['status'] ?? '' ) ) {
            return [ 'status' => 'complete', 'message' => 'The commerce migration is already complete.' ];
        }

        $preflight = $this->preflight();
        $blocking  = array_values( array_filter( $preflight, static fn( array $check ): bool => 'fail' === ( $check['status'] ?? '' ) ) );
        if ( [] !== $blocking ) {
            return new \WP_Error(
                'billing_migration_preflight_failed',
                'Billing migration preflight failed: ' . implode( '; ', array_column( $blocking, 'message' ) ),
                [ 'checks' => $preflight ]
            );
        }

        if ( ! $this->acquire_lock() ) {
            return new \WP_Error( 'billing_migration_locked', 'Another billing migration or rollback is already running.' );
        }

        try {
            return $this->run_locked();
        } finally {
            delete_option( self::LOCK_OPTION );
        }
    }

    private function run_locked(): array|\WP_Error {
        if ( SettingsRepository::runtime_enabled() && 'complete' === ( SettingsRepository::migration_state()['status'] ?? '' ) ) {
            return [ 'status' => 'complete', 'message' => 'The commerce migration is already complete.' ];
        }

        SettingsRepository::ensure_defaults();
        $backup = $this->build_backup();
        if ( is_wp_error( $backup ) ) {
            return $backup;
        }
        update_option( self::BACKUP_OPTION, $backup, false );
        $stored_backup = get_option( self::BACKUP_OPTION, [] );
        if ( ! is_array( $stored_backup ) || ( $stored_backup['created_at'] ?? '' ) !== $backup['created_at'] ) {
            return new \WP_Error( 'migration_backup_failed', 'The migration backup could not be persisted before ownership transfer.' );
        }

        $page_results = $this->claim_pages();
        if ( is_wp_error( $page_results ) ) {
            return $this->recover_failed_migration( $backup, $page_results );
        }

        $custom_product = $this->claim_custom_carrier();
        if ( is_wp_error( $custom_product ) ) {
            return $this->recover_failed_migration( $backup, $custom_product );
        }

        $snippets = $this->disable_legacy_snippets();
        if ( is_wp_error( $snippets ) ) {
            return $this->recover_failed_migration( $backup, $snippets );
        }

        if ( $backup['flexible_checkout_fields_active'] ) {
            $this->load_plugin_functions();
            deactivate_plugins( self::FCF_PLUGIN, true );
            if ( is_plugin_active( self::FCF_PLUGIN ) ) {
                return $this->recover_failed_migration( $backup, new \WP_Error( 'checkout_fields_deactivation_failed', 'Flexible Checkout Fields could not be deactivated.' ) );
            }
        }

        SettingsRepository::set_runtime_enabled( true );
        if ( ! SettingsRepository::runtime_enabled() ) {
            return $this->recover_failed_migration( $backup, new \WP_Error( 'runtime_enable_failed', 'The billing runtime option could not be enabled.' ) );
        }
        $state = [
            'status'       => 'complete',
            'completed_at' => gmdate( 'c' ),
            'version'      => 2,
        ];
        update_option( SettingsRepository::MIGRATION_STATE, $state, false );
        if ( 'complete' !== ( SettingsRepository::migration_state()['status'] ?? '' ) ) {
            return $this->recover_failed_migration( $backup, new \WP_Error( 'migration_state_failed', 'The completed migration state could not be persisted.' ) );
        }
        $this->purge_caches();
        CommerceReport::clear_cache();
        Activity::add( 'Legacy billing ownership migrated to the plugin.', 'success', [ 'pages' => $page_results, 'snippets' => $snippets ], 'migration' );

        return [
            'status'   => 'complete',
            'pages'    => $page_results,
            'custom_product' => $custom_product,
            'snippets' => $snippets,
            'message'  => 'Billing runtime enabled and legacy commerce hooks disabled.',
        ];
    }

    public function preflight(): array {
        $checks = [];
        $checks[] = $this->check(
            'WooCommerce runtime',
            class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ),
            'WooCommerce is available.',
            'WooCommerce is unavailable.'
        );

        $pages = $this->target_pages();
        $checks[] = $this->check(
            'Distinct page mapping',
            3 === count( array_unique( array_filter( array_values( $pages ) ) ) ),
            'Checkout, Packages, and Submit map to distinct pages.',
            'Checkout, Packages, and Submit must map to three distinct page IDs.'
        );
        foreach ( $pages as $label => $page_id ) {
            $post = $page_id > 0 ? get_post( $page_id ) : null;
            $checks[] = $this->check(
                $label . ' page',
                $post instanceof \WP_Post && 'page' === $post->post_type && 'trash' !== $post->post_status,
                $label . ' page #' . $page_id . ' is available.',
                $label . ' page #' . $page_id . ' is missing or not a usable page.'
            );
        }

        $table_exists = $this->snippets_table_exists();
        $checks[] = $this->check(
            'Code Snippets storage',
            $table_exists,
            'The Code Snippets table is available for the guarded handoff.',
            'The Code Snippets storage table is missing.'
        );
        if ( $table_exists ) {
            $found   = array_map( 'absint', array_column( $this->legacy_snippets(), 'id' ) );
            $missing = array_values( array_diff( self::SNIPPET_IDS, $found ) );
            $checks[] = [
                'label'   => 'Legacy snippet inventory',
                'status'  => [] === $missing ? 'pass' : 'warn',
                'message' => [] === $missing
                    ? 'All five audited legacy commerce snippets are present.'
                    : 'No row exists for legacy snippet IDs: ' . implode( ', ', $missing ) . '. Missing rows do not block migration.',
            ];
        }

        $product_ids = [
            'Standard' => absint( SettingsRepository::get( 'standard_product_id', 0 ) ),
            'Premium'  => absint( SettingsRepository::get( 'premium_product_id', 0 ) ),
            'Writing'  => absint( SettingsRepository::get( 'writing_product_id', 0 ) ),
            'Custom'   => absint( SettingsRepository::get( 'custom_product_id', 0 ) ),
        ];
        $checks[] = $this->check(
            'Distinct product mapping',
            4 === count( array_unique( array_filter( array_values( $product_ids ) ) ) ),
            'All four billing roles map to distinct WooCommerce products.',
            'Standard, Premium, Writing, and Custom must map to four distinct product IDs.'
        );
        foreach ( $product_ids as $label => $product_id ) {
            $product = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
            $checks[] = $this->check(
                $label . ' product',
                $product instanceof \WC_Product && 'trash' !== $product->get_status(),
                $label . ' product #' . $product_id . ' is available.',
                $label . ' product #' . $product_id . ' is missing or unusable.'
            );
        }

        $disabled_features = [];
        foreach ( SettingsRepository::FEATURE_OPTIONS as $feature => $option ) {
            unset( $option );
            if ( ! SettingsRepository::feature_enabled( $feature ) ) {
                $disabled_features[] = $feature;
            }
        }
        $checks[] = $this->check(
            'Replacement feature set',
            [] === $disabled_features,
            'All replacement billing features are enabled for ownership transfer.',
            'Enable these billing features before migration: ' . implode( ', ', $disabled_features ) . '.'
        );

        $upm = get_option( 'woocommerce_stripe_upm_settings', [] );
        $ach = get_option( 'woocommerce_stripe_ach_settings', [] );
        $checks[] = $this->check(
            'ACH gateway',
            ( is_array( $upm ) && 'yes' === ( $upm['enabled'] ?? '' ) ) || ( is_array( $ach ) && 'yes' === ( $ach['enabled'] ?? '' ) ),
            'A Stripe ACH-capable gateway is enabled.',
            'Enable Stripe UPM or Stripe ACH before transferring checkout ownership.'
        );

        return $checks;
    }

    public function rollback(): array|\WP_Error {
        $backup = get_option( self::BACKUP_OPTION, [] );
        if ( ! is_array( $backup ) || empty( $backup['pages'] ) ) {
            return new \WP_Error( 'missing_migration_backup', 'No billing migration backup is available.' );
        }

        if ( ! $this->acquire_lock() ) {
            return new \WP_Error( 'billing_migration_locked', 'Another billing migration or rollback is already running.' );
        }

        try {
            $result = $this->restore_backup( $backup, false );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            update_option(
                SettingsRepository::MIGRATION_STATE,
                [ 'status' => 'rolled_back', 'rolled_back_at' => gmdate( 'c' ), 'version' => 2 ],
                false
            );
            $this->purge_caches();
            CommerceReport::clear_cache();
            Activity::add( 'Billing migration rolled back.', 'warning', [], 'migration' );
            return [ 'status' => 'rolled_back', 'message' => 'Legacy page, product, snippet, and checkout-field ownership restored.' ];
        } finally {
            delete_option( self::LOCK_OPTION );
        }
    }

    private function build_backup(): array|\WP_Error {
        $page_ids = array_unique(
            array_filter(
                [
                    absint( get_option( 'woocommerce_checkout_page_id', SettingsRepository::get( 'checkout_page_id', 0 ) ) ),
                    absint( SettingsRepository::get( 'packages_page_id', 0 ) ),
                    absint( SettingsRepository::get( 'submit_page_id', 0 ) ),
                ]
            )
        );
        $pages = [];
        foreach ( $page_ids as $page_id ) {
            $post = get_post( $page_id );
            if ( ! $post instanceof \WP_Post ) {
                return new \WP_Error( 'missing_migration_page', 'Migration page #' . $page_id . ' does not exist.' );
            }
            $pages[ $page_id ] = [
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_status'  => $post->post_status,
            ];
        }

        $custom_id      = absint( SettingsRepository::get( 'custom_product_id', 0 ) );
        $custom_product = $custom_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $custom_id ) : false;
        if ( ! $custom_product instanceof \WC_Product ) {
            return new \WP_Error( 'missing_custom_product', 'The custom service carrier product is unavailable.' );
        }

        $this->load_plugin_functions();
        return [
            'created_at'                      => gmdate( 'c' ),
            'version'                         => 2,
            'pages'                           => $pages,
            'snippets'                        => $this->legacy_snippets(),
            'custom_product'                  => [
                'id'            => $custom_id,
                'price'         => $custom_product->get_price( 'edit' ),
                'regular_price' => $custom_product->get_regular_price( 'edit' ),
                'sale_price'    => $custom_product->get_sale_price( 'edit' ),
            ],
            'flexible_checkout_fields_active' => is_plugin_active( self::FCF_PLUGIN ),
            'runtime_enabled_before'          => SettingsRepository::runtime_enabled(),
            'migration_state_before'          => SettingsRepository::migration_state(),
        ];
    }

    private function claim_pages(): array|\WP_Error {
        $pages   = $this->target_pages();
        $targets = [
            $pages['Checkout'] => "<!-- wp:shortcode -->\n[woocommerce_checkout]\n<!-- /wp:shortcode -->",
            $pages['Packages'] => "<!-- wp:shortcode -->\n[hpr_billing_catalog]\n<!-- /wp:shortcode -->",
            $pages['Submit']   => "<!-- wp:shortcode -->\n[hpr_billing_order_portal]\n<!-- /wp:shortcode -->",
        ];
        $results = [];
        foreach ( $targets as $page_id => $content ) {
            if ( $page_id <= 0 ) {
                continue;
            }
            $result = wp_update_post( [ 'ID' => $page_id, 'post_content' => $content ], true );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $results[] = $page_id;
        }
        return $results;
    }

    private function disable_legacy_snippets(): array|\WP_Error {
        global $wpdb;
        $table = $wpdb->prefix . 'snippets';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return new \WP_Error( 'missing_snippets_table', 'Code Snippets storage table was not found.' );
        }

        $disabled = [];
        foreach ( $this->legacy_snippets() as $snippet ) {
            if ( empty( $snippet['active'] ) ) {
                continue;
            }
            $updated = $wpdb->update( $table, [ 'active' => 0 ], [ 'id' => absint( $snippet['id'] ) ], [ '%d' ], [ '%d' ] );
            if ( false === $updated ) {
                return new \WP_Error( 'snippet_disable_failed', 'Could not disable legacy snippet #' . absint( $snippet['id'] ) . '.' );
            }
            $disabled[] = absint( $snippet['id'] );
        }
        return $disabled;
    }

    private function claim_custom_carrier(): array|\WP_Error {
        $product_id = absint( SettingsRepository::get( 'custom_product_id', 0 ) );
        $product    = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
        if ( ! $product instanceof \WC_Product ) {
            return new \WP_Error( 'missing_custom_product', 'The custom service carrier product is unavailable.' );
        }

        try {
            $product->set_regular_price( '' );
            $product->set_sale_price( '' );
            $product->set_price( '' );
            $product->save();
        } catch ( \Throwable $throwable ) {
            return new \WP_Error( 'custom_product_claim_failed', 'Could not remove the custom carrier fallback price: ' . $throwable->getMessage() );
        }

        $saved = wc_get_product( $product_id );
        if ( ! $saved instanceof \WC_Product || '' !== (string) $saved->get_price( 'edit' ) ) {
            return new \WP_Error( 'custom_product_claim_failed', 'The custom carrier fallback price was not removed.' );
        }

        return [ 'id' => $product_id, 'fallback_price_removed' => true ];
    }

    private function legacy_snippets(): array {
        if ( ! $this->snippets_table_exists() ) {
            return [];
        }
        global $wpdb;
        $table = $wpdb->prefix . 'snippets';
        $ids   = implode( ',', array_map( 'absint', self::SNIPPET_IDS ) );
        $rows  = $wpdb->get_results( "SELECT id, name, active FROM {$table} WHERE id IN ({$ids}) ORDER BY id", ARRAY_A );
        return is_array( $rows ) ? $rows : [];
    }

    private function load_plugin_functions(): void {
        if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    private function purge_caches(): void {
        foreach ( $this->target_pages() as $page_id ) {
            clean_post_cache( $page_id );
        }
        if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        do_action( 'litespeed_purge_all' );
    }

    private function target_pages(): array {
        return [
            'Checkout' => absint( get_option( 'woocommerce_checkout_page_id', SettingsRepository::get( 'checkout_page_id', 0 ) ) ),
            'Packages' => absint( SettingsRepository::get( 'packages_page_id', 0 ) ),
            'Submit'   => absint( SettingsRepository::get( 'submit_page_id', 0 ) ),
        ];
    }

    private function snippets_table_exists(): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'snippets';

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    private function restore_backup( array $backup, bool $restore_previous_runtime ): true|\WP_Error {
        SettingsRepository::set_runtime_enabled( $restore_previous_runtime && ! empty( $backup['runtime_enabled_before'] ) );
        update_option( SettingsRepository::MIGRATION_STATE, is_array( $backup['migration_state_before'] ?? null ) ? $backup['migration_state_before'] : [], false );

        foreach ( $backup['pages'] ?? [] as $page_id => $page ) {
            $result = wp_update_post(
                [
                    'ID'           => absint( $page_id ),
                    'post_content' => (string) ( $page['post_content'] ?? '' ),
                ],
                true
            );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        $product_backup = $backup['custom_product'] ?? [];
        $product_id     = absint( $product_backup['id'] ?? 0 );
        $product        = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
        if ( ! $product instanceof \WC_Product ) {
            return new \WP_Error( 'custom_product_restore_failed', 'The custom service carrier product is unavailable during rollback.' );
        }
        try {
            $product->set_regular_price( (string) ( $product_backup['regular_price'] ?? '' ) );
            $product->set_sale_price( (string) ( $product_backup['sale_price'] ?? '' ) );
            $product->set_price( (string) ( $product_backup['price'] ?? '' ) );
            $product->save();
        } catch ( \Throwable $throwable ) {
            return new \WP_Error( 'custom_product_restore_failed', 'Could not restore the custom carrier price: ' . $throwable->getMessage() );
        }
        $restored_product = wc_get_product( $product_id );
        if ( ! $restored_product instanceof \WC_Product
            || (string) $restored_product->get_price( 'edit' ) !== (string) ( $product_backup['price'] ?? '' )
            || (string) $restored_product->get_regular_price( 'edit' ) !== (string) ( $product_backup['regular_price'] ?? '' )
            || (string) $restored_product->get_sale_price( 'edit' ) !== (string) ( $product_backup['sale_price'] ?? '' )
        ) {
            return new \WP_Error( 'custom_product_restore_failed', 'The custom carrier price did not match its migration backup after rollback.' );
        }

        if ( ! $this->snippets_table_exists() ) {
            return new \WP_Error( 'missing_snippets_table', 'Code Snippets storage disappeared before rollback could finish.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'snippets';
        foreach ( $backup['snippets'] ?? [] as $snippet ) {
            $updated = $wpdb->update(
                $table,
                [ 'active' => empty( $snippet['active'] ) ? 0 : 1 ],
                [ 'id' => absint( $snippet['id'] ) ],
                [ '%d' ],
                [ '%d' ]
            );
            if ( false === $updated ) {
                return new \WP_Error( 'snippet_restore_failed', 'Could not restore legacy snippet #' . absint( $snippet['id'] ) . '.' );
            }
        }

        if ( ! empty( $backup['flexible_checkout_fields_active'] ) ) {
            $this->load_plugin_functions();
            if ( ! is_plugin_active( self::FCF_PLUGIN ) ) {
                $result = \activate_plugin( self::FCF_PLUGIN, '', false, true );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
            }
        }

        return true;
    }

    private function recover_failed_migration( array $backup, \WP_Error $failure ): \WP_Error {
        $restore = $this->restore_backup( $backup, true );
        $message = $failure->get_error_message();
        if ( is_wp_error( $restore ) ) {
            $message .= ' Automatic recovery also failed: ' . $restore->get_error_message();
        } else {
            $message .= ' The pre-migration state was restored automatically.';
        }

        return new \WP_Error( 'billing_migration_failed', $message, [ 'original_error' => $failure->get_error_code() ] );
    }

    private function acquire_lock(): bool {
        $now = time();
        $raw = get_option( self::LOCK_OPTION, false );
        if ( false !== $raw && ( ! is_numeric( $raw ) || ( $now - (int) $raw ) > self::LOCK_TTL ) ) {
            delete_option( self::LOCK_OPTION );
        }

        return add_option( self::LOCK_OPTION, $now, '', 'no' );
    }

    private function check( string $label, bool $passed, string $pass_message, string $fail_message ): array {
        return [
            'label'   => $label,
            'status'  => $passed ? 'pass' : 'fail',
            'message' => $passed ? $pass_message : $fail_message,
        ];
    }
}
