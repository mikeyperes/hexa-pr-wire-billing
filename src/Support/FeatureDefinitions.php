<?php

namespace HexaPrWire\Billing\Support;

use Hexa\PluginCore\SnippetRegistry\SnippetDefinition;
use Hexa\PluginCore\SnippetRegistry\SnippetRegistry;
use HexaPrWire\Billing\Settings\SettingsRepository;

final class FeatureDefinitions {
    public static function registry(): SnippetRegistry {
        return ( new SnippetRegistry() )->add_many( self::all() );
    }

    public static function definition( string $id ): ?SnippetDefinition {
        return self::registry()->get( $id );
    }

    public static function all(): array {
        return [
            self::definition_array(
                'single_item_cart',
                'Single-item billing cart',
                'cart-checkout',
                'Validates managed products, protects custom service pricing, and removes other cart lines only after a valid managed item is added.',
                'woocommerce_add_to_cart_validation and woocommerce_add_to_cart',
                static fn(): bool => has_filter( 'woocommerce_add_to_cart_validation' ) && has_action( 'woocommerce_add_to_cart' )
            ),
            self::definition_array(
                'customer_pricing',
                'Customer-specific pricing',
                'pricing',
                'Reads standard pricing and custom service rows from server-side user meta and snapshots the resolved amount into the WooCommerce cart.',
                'billing_price_standard_release and billing_custom_services',
                static fn(): bool => class_exists( '\\HexaPrWire\\Billing\\Commerce\\Pricing\\CustomerPricingRepository' )
            ),
            self::definition_array(
                'checkout_fields',
                'Managed checkout fields',
                'cart-checkout',
                'Owns the digital-service billing fields, article title validation, and order metadata without Flexible Checkout Fields.',
                'woocommerce_checkout_fields and woocommerce_checkout_create_order',
                static fn(): bool => has_filter( 'woocommerce_checkout_fields' ) && has_action( 'woocommerce_checkout_create_order' )
            ),
            self::definition_array(
                'gateway_policy',
                'Per-customer card access',
                'payments',
                'Allowlists ACH for managed orders, constrains universal Payment Elements to bank accounts, and exposes Stripe card only to entitled accounts.',
                'woocommerce_available_payment_gateways, wc_stripe_get_element_options, and checkout validation',
                static fn(): bool => has_filter( 'woocommerce_available_payment_gateways' )
                    && has_filter( 'wc_stripe_get_element_options' )
                    && has_action( 'woocommerce_after_checkout_validation' )
            ),
            self::definition_array(
                'stripe_descriptions',
                'Stripe intent descriptions',
                'payments',
                'Builds one bounded Stripe description from the order, service, article title, customer, and order ID.',
                'wc_stripe_payment_intent_args',
                static fn(): bool => has_filter( 'wc_stripe_payment_intent_args' )
            ),
            self::definition_array(
                'fulfillment',
                'Idempotent fulfillment drafts',
                'fulfillment',
                'Creates at most one editorial draft for a managed order and stores a reversible order-to-post link.',
                'woocommerce_order_status_processing and completed',
                static fn(): bool => has_action( 'woocommerce_order_status_processing' ) && has_action( 'woocommerce_order_status_completed' )
            ),
            self::definition_array(
                'order_portal',
                'Customer order portal',
                'customer-experience',
                'Provides the Place an Order wp-admin screen and account-aware service links.',
                'admin_menu: hpr-place-order',
                static fn(): bool => class_exists( '\\HexaPrWire\\Billing\\Admin\\OrderPortal' )
            ),
            self::definition_array(
                'catalog',
                'Billing catalog shortcodes',
                'customer-experience',
                'Renders the public package catalog and signed-in account order portal.',
                '[hpr_billing_catalog] and [hpr_billing_order_portal]',
                static fn(): bool => shortcode_exists( 'hpr_billing_catalog' ) && shortcode_exists( 'hpr_billing_order_portal' )
            ),
            self::definition_array(
                'acf_fields',
                'Plugin-owned ACF billing fields',
                'fields',
                'Registers customer pricing, custom services, card access, and fulfillment linkage with stable local field keys.',
                'group_hpr_billing_customer_settings and group_hpr_billing_order_linkage',
                static fn(): bool => function_exists( 'acf_get_field_group' )
                    && (bool) acf_get_field_group( 'group_hpr_billing_customer_settings' )
                    && (bool) acf_get_field_group( 'group_hpr_billing_order_linkage' )
            ),
        ];
    }

    private static function definition_array( string $id, string $name, string $category, string $description, string $hook, callable $test ): array {
        return [
            'id'              => $id,
            'name'            => $name,
            'category'        => $category,
            'option_key'      => SettingsRepository::FEATURE_OPTIONS[ $id ],
            'default_enabled' => true,
            'description'     => $description,
            'snippets'        => [
                [
                    'label'       => 'Runtime contract',
                    'value'       => $hook,
                    'description' => 'The hook or structure registered by this module.',
                ],
            ],
            'test_rules'      => [
                [
                    'id'          => 'runtime_enabled',
                    'label'       => 'Billing runtime is enabled',
                    'description' => 'Confirms the guarded legacy migration has transferred hook ownership.',
                    'type'        => 'callback',
                    'callback'    => static fn(): bool => SettingsRepository::runtime_enabled(),
                    'required'    => true,
                ],
                [
                    'id'          => 'module_registered',
                    'label'       => 'Module contract is registered',
                    'description' => 'Confirms the expected runtime hook, shortcode, class, or ACF structure exists.',
                    'type'        => 'callback',
                    'callback'    => $test,
                    'required'    => true,
                ],
            ],
            'readme' => $name . "\n\n" . $description . "\n\nRuntime: " . $hook . '.',
        ];
    }
}
