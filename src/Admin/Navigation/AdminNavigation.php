<?php

namespace HexaPrWire\Billing\Admin\Navigation;

use Hexa\PluginCore\WpAdminTabs\TabDefinition;
use Hexa\PluginCore\WpAdminTabs\TabRegistry;

final class AdminNavigation {
    private const FLAT_TABS = [
        'overview'      => 'Overview',
        'catalog'       => 'Catalog',
        'checkout'      => 'Checkout',
        'payments'      => 'Payments',
        'fulfillment'   => 'Fulfillment',
        'pricing'       => 'Pricing',
        'order_portal'  => 'Order Portal',
        'orders'        => 'Orders',
        'integrity'     => 'Integrity',
        'activity'      => 'Activity',
        'features'      => 'Features',
        'custom_fields' => 'ACF',
        'git_updates'   => 'Git Reporting',
        'hexa_core'     => 'Hexa WP Core',
    ];

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

    /**
     * @return array<string,string>
     */
    public function tabs(): array {
        $tabs = self::FLAT_TABS;

        foreach ( $this->legacy_tab_labels() as $id => $label ) {
            $id = sanitize_key( (string) $id );
            if ( '' !== $id && ! isset( $tabs[ $id ] ) && ! $this->known_section( $id ) ) {
                $tabs[ $id ] = (string) $label;
            }
        }

        return apply_filters( 'hpr_billing_dashboard_flat_tabs', $tabs );
    }

    /**
     * @return array<int,array{label:string,tabs:array<int,string>}>
     */
    public function groups(): array {
        $tabs     = $this->tabs();
        $assigned = [];
        $groups   = [];

        foreach ( $this->areas() as $area => $area_label ) {
            $group_tabs = [];
            foreach ( array_keys( $this->sections( (string) $area ) ) as $id ) {
                $id = sanitize_key( (string) $id );
                if ( '' !== $id && isset( $tabs[ $id ] ) && ! isset( $assigned[ $id ] ) ) {
                    $group_tabs[]    = $id;
                    $assigned[ $id ] = true;
                }
            }

            if ( [] !== $group_tabs ) {
                $groups[] = [
                    'label' => (string) $area_label,
                    'tabs'  => $group_tabs,
                ];
            }
        }

        $leftover = [];
        foreach ( array_keys( $tabs ) as $id ) {
            $id = sanitize_key( (string) $id );
            if ( '' !== $id && ! isset( $assigned[ $id ] ) ) {
                $leftover[] = $id;
            }
        }

        if ( [] !== $leftover ) {
            $groups[] = [
                'label' => 'More',
                'tabs'  => $leftover,
            ];
        }

        return apply_filters( 'hpr_billing_dashboard_tab_groups', $groups );
    }

    public function registry( callable $renderer, ?string $capability = null ): TabRegistry {
        $registry = new TabRegistry();

        foreach ( $this->tabs() as $id => $label ) {
            $id = sanitize_key( (string) $id );
            if ( '' === $id ) {
                continue;
            }

            $registry->add(
                new TabDefinition(
                    $id,
                    (string) $label,
                    static function () use ( $renderer, $id ): void {
                        $renderer( $id );
                    },
                    $capability
                )
            );
        }

        return $registry;
    }

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
        if ( isset( $this->tabs()[ $tab ] ) ) {
            return new AdminRoute( 'advanced', $tab );
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
