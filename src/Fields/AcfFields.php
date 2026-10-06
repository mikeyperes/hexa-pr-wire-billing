<?php

namespace HexaPrWire\Billing\Fields;

use HexaPrWire\Billing\Settings\SettingsRepository;

final class AcfFields {
    public const ORDER_GROUP    = 'group_hpr_billing_order_linkage';

    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! SettingsRepository::feature_enabled( 'acf_fields' ) ) {
            return;
        }
        \Hexa\PluginCore\Fields\Hooks::on( 'init', [ $this, 'register_groups' ] );
        \Hexa\PluginCore\Fields\Hooks::on( 'prepare_field/key=field_6640053880290', [ $this, 'hide_legacy_billing_group' ] );
        \Hexa\PluginCore\Fields\Hooks::on( 'prepare_field/key=field_66417d52d4884', [ $this, 'hide_legacy_billing_group' ] );
        \Hexa\PluginCore\Fields\Hooks::on( 'load_value/key=field_hpr_portal_amount_display', [ $this, 'load_portal_amount' ], 10, 3 );
        \Hexa\PluginCore\Fields\Hooks::on( 'prepare_field/key=field_hpr_portal_invoice_link_action', [ $this, 'prepare_portal_order_link' ] );
    }

    public function hide_legacy_billing_group( array|false $field ): false {
        unset( $field );
        return false;
    }

    public function register_groups(): void {
        \Hexa\PluginCore\Fields\FieldGroups::add(
            [
                'key'      => self::ORDER_GROUP,
                'title'    => 'Hexa PR Wire Billing Linkage',
                'fields'   => [
                    [
                        'key'      => 'field_hpr_billing_order_invoice_id',
                        'label'    => 'Order ID',
                        'name'     => 'billing_invoice_id',
                        'type'     => 'text',
                        'readonly' => 1,
                    ],
                    [
                        'key'      => 'field_hpr_billing_order_original_title',
                        'label'    => 'Original Title',
                        'name'     => 'billing_original_title',
                        'type'     => 'text',
                        'readonly' => 1,
                    ],
                    [
                        'key'      => 'field_hpr_billing_order_service',
                        'label'    => 'Service',
                        'name'     => 'billing_service',
                        'type'     => 'text',
                        'readonly' => 1,
                    ],
                    [
                        'key'          => 'field_hpr_portal_order_heading',
                        'label'        => 'Service Order Portal Provenance',
                        'name'         => '',
                        'type'         => 'message',
                        'message'      => 'Read-only purchase provenance supplied by the internal Service Order Portal.',
                        'new_lines'    => 'wpautop',
                        'esc_html'     => 1,
                    ],
                    [
                        'key'      => 'field_hpr_portal_order_number',
                        'label'    => 'Portal Order Number',
                        'name'     => '_hpr_portal_order_number',
                        'type'     => 'text',
                        'readonly' => 1,
                        'disabled' => 1,
                    ],
                    [
                        'key'      => 'field_hpr_portal_purchased_at',
                        'label'    => 'Purchased At',
                        'name'     => '_hpr_portal_purchased_at',
                        'type'     => 'text',
                        'readonly' => 1,
                        'disabled' => 1,
                    ],
                    [
                        'key'      => 'field_hpr_portal_amount_display',
                        'label'    => 'Purchased For',
                        'name'     => 'hpr_portal_amount_display',
                        'type'     => 'text',
                        'readonly' => 1,
                        'disabled' => 1,
                    ],
                    [
                        'key'      => 'field_hpr_portal_invoice_id',
                        'label'    => 'Stripe Invoice ID',
                        'name'     => '_hpr_portal_invoice_id',
                        'type'     => 'text',
                        'readonly' => 1,
                        'disabled' => 1,
                    ],
                    [
                        'key'      => 'field_hpr_portal_billing_mode',
                        'label'    => 'Portal Billing Mode',
                        'name'     => '_hpr_portal_billing_mode',
                        'type'     => 'text',
                        'readonly' => 1,
                        'disabled' => 1,
                    ],
                    [
                        'key'      => 'field_hpr_portal_service',
                        'label'    => 'Portal Service',
                        'name'     => '_hpr_portal_service',
                        'type'     => 'text',
                        'readonly' => 1,
                        'disabled' => 1,
                    ],
                    [
                        'key'       => 'field_hpr_portal_invoice_link_action',
                        'label'     => 'Internal Billing Record',
                        'name'      => '',
                        'type'      => 'message',
                        'message'   => 'No Service Order Portal record is attached.',
                        'new_lines' => '',
                        'esc_html'  => 0,
                    ],
                ],
                'location' => [
                    [
                        [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ],
                        [ 'param' => 'current_user_role', 'operator' => '==', 'value' => 'administrator' ],
                    ],
                ],
                'menu_order' => 90,
                'active'     => true,
            ]
        );
    }

    public function load_portal_amount( mixed $value, mixed $post_id, array $field ): string {
        unset( $value, $field );
        $post_id  = (int) $post_id;
        $cents    = (int) get_post_meta( $post_id, '_hpr_portal_amount_cents', true );
        $currency = strtoupper( (string) get_post_meta( $post_id, '_hpr_portal_currency', true ) );
        $currency = '' !== $currency ? $currency : 'USD';

        return ( 'USD' === $currency ? '$' : '' ) . number_format( $cents / 100, 2 ) . ' ' . $currency;
    }

    public function prepare_portal_order_link( array|false $field ): array|false {
        if ( false === $field ) {
            return false;
        }

        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
        $url     = $post_id > 0 ? (string) get_post_meta( $post_id, '_hpr_portal_invoice_link', true ) : '';
        $host    = '' !== $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';
        if ( 'billing.hexawebsystems.com' !== $host ) {
            $field['message'] = 'No authenticated Billing record is attached to this post.';
            return $field;
        }

        $invoice_id      = (string) get_post_meta( $post_id, '_hpr_portal_invoice_id', true );
        $reference_label = '' !== $invoice_id ? 'Open invoice ' . $invoice_id : 'Open source order';
        $field['message'] = sprintf(
            '<a class="button button-secondary" href="%s" target="_blank" rel="noopener noreferrer">%s</a><p class="description">Billing authentication is required.</p>',
            esc_url( $url ),
            esc_html( $reference_label )
        );

        return $field;
    }
}
