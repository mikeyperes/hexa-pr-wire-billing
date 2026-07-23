<?php

namespace HexaPrWire\Billing\Cli;

use HexaPrWire\Billing\Migration\LegacyCommerceMigration;
use HexaPrWire\Billing\Reports\CommerceReport;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class Commands {
    public static function register(): void {
        if ( class_exists( '\\WP_CLI' ) ) {
            \WP_CLI::add_command( 'hpr-billing', new self() );
        }
    }

    /** Transfer live billing ownership from Code Snippets and Flexible Checkout Fields. */
    public function migrate( array $args, array $assoc_args ): void {
        unset( $args );
        if ( 'MIGRATE' !== (string) ( $assoc_args['confirm'] ?? '' ) ) {
            \WP_CLI::error( 'Pass --confirm=MIGRATE to transfer billing ownership.' );
        }
        $result = ( new LegacyCommerceMigration() )->run();
        $this->finish( $result );
    }

    /** Restore the pre-migration page, snippet, and checkout-field state. */
    public function rollback( array $args, array $assoc_args ): void {
        unset( $args );
        if ( 'ROLLBACK' !== (string) ( $assoc_args['confirm'] ?? '' ) ) {
            \WP_CLI::error( 'Pass --confirm=ROLLBACK to restore legacy billing ownership.' );
        }
        $result = ( new LegacyCommerceMigration() )->rollback();
        $this->finish( $result );
    }

    /** Print the guarded migration preflight without changing production state. */
    public function preflight( array $args, array $assoc_args ): void {
        unset( $args, $assoc_args );
        $checks   = ( new LegacyCommerceMigration() )->preflight();
        $blocking = array_filter( $checks, static fn( array $check ): bool => 'fail' === ( $check['status'] ?? '' ) );
        \WP_CLI::line( wp_json_encode( $checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
        if ( [] !== $blocking ) {
            \WP_CLI::error( count( $blocking ) . ' migration preflight check(s) failed.' );
        }
        \WP_CLI::success( 'Migration preflight passed.' );
    }

    /** Print the current commerce summary and integrity report. */
    public function audit( array $args, array $assoc_args ): void {
        unset( $args, $assoc_args );
        $report = new CommerceReport();
        \WP_CLI::line(
            wp_json_encode(
                [
                    'runtime_enabled' => SettingsRepository::runtime_enabled(),
                    'migration'       => SettingsRepository::migration_state(),
                    'summary'         => $report->summary( true ),
                    'integrity'       => $report->integrity_checks(),
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }

    /** Print migration and runtime ownership status. */
    public function status( array $args, array $assoc_args ): void {
        unset( $args, $assoc_args );
        \WP_CLI::line( wp_json_encode( [ 'runtime_enabled' => SettingsRepository::runtime_enabled(), 'migration' => SettingsRepository::migration_state() ], JSON_PRETTY_PRINT ) );
    }

    private function finish( array|\WP_Error $result ): void {
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( (string) ( $result['message'] ?? 'Command completed.' ) );
        \WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
    }
}
