<?php

namespace HexaPrWire\Billing\Admin\Navigation;

final class AdminNavigation {
    private const AREAS = [
        'overview'  => 'Overview',
        'commerce'  => 'Commerce',
        'customers' => 'Customers',
        'reporting' => 'Reporting',
        'advanced'  => 'Advanced',
    ];

    private const SECTIONS = [
        'overview' => [
            'overview' => 'Overview',
        ],
        'commerce' => [
            'catalog'     => 'Catalog',
            'checkout'    => 'Checkout',
            'payments'    => 'Payments',
            'fulfillment' => 'Fulfillment',
        ],
        'customers' => [
            'pricing'      => 'Pricing',
            'order_portal' => 'Order Portal',
        ],
        'reporting' => [
            'orders'    => 'Orders',
            'integrity' => 'Integrity',
            'activity'  => 'Activity',
        ],
        'advanced' => [
            'features'      => 'Features',
            'custom_fields' => 'ACF',
            'git_updates'   => 'Git Reporting',
            'hexa_core'     => 'Hexa WP Core',
        ],
    ];

    public function areas(): array {
        return apply_filters( 'hpr_billing_dashboard_areas', self::AREAS );
    }

    public function sections( string $area ): array {
        $area     = sanitize_key( $area );
        $sections = self::SECTIONS[ $area ] ?? [];
        $legacy   = $this->legacy_tab_labels();
        foreach ( $sections as $id => $label ) {
            if ( isset( $legacy[ $id ] ) ) {
                $sections[ $id ] = (string) $legacy[ $id ];
            }
        }
        if ( 'advanced' === $area ) {
            foreach ( $legacy as $id => $label ) {
                if ( ! $this->known_section( (string) $id ) ) {
                    $sections[ sanitize_key( (string) $id ) ] = (string) $label;
                }
            }
        }
        return apply_filters( 'hpr_billing_dashboard_area_sections', $sections, $area );
    }

    public function resolve( string $tab, string $section = '' ): AdminRoute {
        $tab     = sanitize_key( $tab );
        $section = sanitize_key( $section );
        if ( 'hexa-core' === $tab ) {
            $tab = 'hexa_core';
        }
        if ( 'hexa-core' === $section ) {
            $section = 'hexa_core';
        }
        if ( isset( $this->areas()[ $tab ] ) ) {
            $sections = $this->sections( $tab );
            if ( '' === $section || ! isset( $sections[ $section ] ) ) {
                $section = (string) array_key_first( $sections );
            }
            return new AdminRoute( $tab, $section );
        }
        foreach ( array_keys( self::AREAS ) as $area ) {
            if ( isset( $this->sections( $area )[ $tab ] ) ) {
                return new AdminRoute( $area, $tab );
            }
        }
        return new AdminRoute( 'overview', 'overview' );
    }

    public function section_url( string $page_url, string $area, string $section ): string {
        return add_query_arg( [ 'tab' => sanitize_key( $area ), 'section' => sanitize_key( $section ) ], $page_url );
    }

    private function known_section( string $section ): bool {
        foreach ( self::SECTIONS as $sections ) {
            if ( isset( $sections[ $section ] ) ) {
                return true;
            }
        }
        return false;
    }

    private function legacy_tab_labels(): array {
        $tabs = [];
        foreach ( self::SECTIONS as $sections ) {
            $tabs = array_merge( $tabs, $sections );
        }
        return apply_filters( 'hpr_billing_dashboard_tabs', $tabs );
    }
}
