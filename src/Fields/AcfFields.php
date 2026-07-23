<?php

namespace HexaPrWire\Billing\Fields;

use HexaPrWire\Billing\Settings\SettingsRepository;

final class AcfFields {
    public const CUSTOMER_GROUP = 'group_hpr_billing_customer_settings';
    public const ORDER_GROUP    = 'group_hpr_billing_order_linkage';

    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() || ! SettingsRepository::feature_enabled( 'acf_fields' ) ) {
            return;
        }
        add_action( 'acf/init', [ $this, 'register_groups' ] );
        add_filter( 'acf/prepare_field/key=field_6640053880290', [ $this, 'hide_legacy_billing_group' ] );
        add_filter( 'acf/prepare_field/key=field_66417d52d4884', [ $this, 'hide_legacy_billing_group' ] );
    }

    public function hide_legacy_billing_group( array|false $field ): false {
        unset( $field );
        return false;
    }

    public function register_groups(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group(
            [
                'key'      => self::CUSTOMER_GROUP,
                'title'    => 'Hexa PR Wire Billing',
                'fields'   => [
                    [
                        'key'           => 'field_hpr_billing_standard_price',
                        'label'         => 'Standard Release Price',
                        'name'          => 'billing_price_standard_release',
                        'type'          => 'number',
                        'instructions'  => 'Leave empty to use the public product price.',
                        'min'           => 0,
                        'step'          => '0.01',
                        'prepend'       => '$',
                    ],
                    [
                        'key'          => 'field_hpr_billing_custom_services',
                        'label'        => 'Custom Services',
                        'name'         => 'billing_custom_services',
                        'type'         => 'repeater',
                        'layout'       => 'table',
                        'button_label' => 'Add Service',
                        'sub_fields'   => [
                            [
                                'key'      => 'field_hpr_billing_custom_service_name',
                                'label'    => 'Name',
                                'name'     => 'name',
                                'type'     => 'text',
                                'required' => 1,
                            ],
                            [
                                'key'      => 'field_hpr_billing_custom_service_price',
                                'label'    => 'Price',
                                'name'     => 'price',
                                'type'     => 'number',
                                'required' => 1,
                                'min'      => 0.01,
                                'step'     => '0.01',
                                'prepend'  => '$',
                            ],
                        ],
                    ],
                    [
                        'key'           => 'field_hpr_billing_allow_credit_card',
                        'label'         => 'Allow Credit Card',
                        'name'          => 'billing_allow_credit_card',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                    ],
                ],
                'location' => [
                    [
                        [ 'param' => 'user_role', 'operator' => '==', 'value' => 'all' ],
                        [ 'param' => 'current_user_role', 'operator' => '==', 'value' => 'administrator' ],
                    ],
                ],
                'active'   => true,
            ]
        );

        acf_add_local_field_group(
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
}

