<?php

namespace HexaPrWire\Billing\Commerce\Cache;

use HexaPrWire\Billing\Settings\SettingsRepository;

final class PersonalizedPageCache {
    public function register(): void {
        add_action( 'template_redirect', [ $this, 'disable_page_cache' ], -100 );
    }

    public function disable_page_cache(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! function_exists( 'is_page' ) ) {
            return;
        }

        $page_ids = array_values(
            array_filter(
                array_map(
                    'absint',
                    [
                        SettingsRepository::get( 'packages_page_id', 0 ),
                        SettingsRepository::get( 'submit_page_id', 0 ),
                        SettingsRepository::get( 'checkout_page_id', 0 ),
                    ]
                )
            )
        );

        if ( [] === $page_ids || ! is_page( $page_ids ) ) {
            return;
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        if ( function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }

        do_action( 'litespeed_control_set_nocache', 'Hexa PR Wire personalized billing page' );
    }
}
