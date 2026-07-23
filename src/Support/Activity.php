<?php

namespace HexaPrWire\Billing\Support;

use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogEntry;
use Hexa\PluginCore\ActivityLog\ActivityLogRenderer;
use Hexa\PluginCore\ActivityLog\ActivityLogger;

final class Activity {
    public static function logger(): ActivityLogger {
        return new ActivityLogger( self::config() );
    }

    public static function add( string $message, string $level = 'info', array $context = [], string $source = 'billing' ): void {
        $user  = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
        $actor = $user && ! empty( $user->user_login ) ? (string) $user->user_login : ( defined( 'WP_CLI' ) && WP_CLI ? 'wp-cli' : 'system' );
        self::logger()->add( new ActivityLogEntry( $message, $context, $actor, $source, null, $level ) );
    }

    public static function render(): void {
        $logger = self::logger();
        ( new ActivityLogRenderer( self::config() ) )->render( $logger->all() );
    }

    private static function config(): ActivityLogConfig {
        return new ActivityLogConfig(
            [
                'id'          => 'hpr-billing-activity-log',
                'title'       => 'Billing Activity Log',
                'storage'     => ActivityLogConfig::STORAGE_PERMANENT,
                'storage_key' => 'hpr_billing_activity_log',
                'max_entries' => 250,
                'collapsed'   => false,
                'dark'        => true,
            ]
        );
    }
}

