# Architecture

## Boundary

`hexa-pr-wire-billing` owns commerce policy and state. It does not own publication syndication, editorial delivery, general authentication, or unrelated site security.

## Bootstrap

The main plugin file follows the Hexa WP Core host protocol:

1. Require the vendored Core `bootstrap.php`.
2. Register the package candidate with minimum Core version 0.19.73.
3. Create a host `PluginContext` after package resolution.
4. Create one `CoreBootstrap`.
5. Add host modules and Core modules.
6. Call `boot()` once.

Commerce modules expose `register()` and are adapted to Core `ModuleInterface`. Runtime modules test both the ownership guard and their feature option before registering hooks.

## Modules

- `Commerce\Cart\ManagedCart`: managed-item validation, account-bound repricing, cart isolation, item metadata, and checkout redirect.
- `Commerce\Checkout\CheckoutFields`: digital-service fields, validation, order metadata, email/admin/customer display.
- `Commerce\Payments\GatewayPolicy`: account-specific Stripe card access while preserving ACH.
- `Commerce\Payments\StripeDescription`: one bounded payment-intent description.
- `Commerce\Fulfillment\OrderFulfillment`: locked, idempotent order-to-draft creation and linkage.
- `Commerce\Catalog\CatalogShortcode`: public and signed-in ordering surfaces.
- `Fields\AcfFields`: stable local groups over existing metadata names.
- `Reports\CommerceReport`: Woo CRUD reports, aggregate pricing reports, and integrity checks.
- `Migration\LegacyCommerceMigration`: preflighted ownership transfer and rollback.
- `Admin`: Core-backed navigation, features, field structures, updates, reports, settings, and migration controls.
- `Cli\Commands`: audit, status, migration, and rollback operations.

## Data Rules

- Product IDs are configuration, not inferred by title.
- Managed prices must be positive server-side decimals.
- Custom services must belong to the current signed-in account.
- The cart can contain one managed billing line and no unrelated lines.
- Premium distribution cannot be purchased directly.
- Card access defaults closed and requires `billing_allow_credit_card=1`.
- Fulfillment links are written through WooCommerce order CRUD for HPOS compatibility.
- Sensitive Stripe settings are checked only for presence and are never rendered.

## Failure Rules

- Missing WooCommerce prevents commerce module registration and migration.
- Missing ACF prevents field UI registration but does not invalidate existing metadata.
- Invalid prices block add-to-cart or checkout.
- Missing custom-service entitlement blocks checkout.
- A failed migration restores the captured pre-migration state.
- Fulfillment lock records expire after five minutes; persisted post metadata still prevents duplicate drafts.

