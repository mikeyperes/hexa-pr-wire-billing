# Hexa PR Wire Billing

Hexa PR Wire Billing owns the bounded commerce domain for `hexaprwire.com`: product mapping, customer pricing, custom services, cart policy, checkout fields, payment access, order metadata, and editorial fulfillment.

The plugin is intentionally separate from `hexa-pr-wire-distributor`. Distribution and editorial syndication remain in the distributor plugin; payment and checkout state remain here.

## Runtime Model

Activation does not transfer commerce ownership. The plugin starts in observation mode with `hpr_billing_runtime_enabled` set to false. Its reports and migration preflight are available, but no replacement cart, checkout, payment, ACF, or fulfillment hooks run until the guarded migration completes.

Required runtime:

- WordPress 6.5 or newer
- PHP 8.0 or newer
- WooCommerce
- ACF Pro for the managed field interface; metadata behavior remains available without its UI
- Vendored Hexa WP Core 0.19.73

## Admin Structure

The WooCommerce > Hexa PR Wire Billing screen uses `Hexa\PluginCore\WpAdminTabs\HostTabsRenderer` with five areas:

- Overview
- Commerce: Catalog, Checkout, Payments, Fulfillment
- Customers: Pricing, Order Portal
- Reporting: Orders, Integrity, Activity
- Advanced: Features, ACF, Git Reporting, Hexa WP Core

Features use the Core `SnippetRegistry` and `SnippetsTableRenderer`. ACF reporting uses `FieldStructureRenderer`. Plugin and Core Git status use their respective Core updater panels.

## Commerce Contracts

Managed product roles are configured in `hpr_billing_settings`:

- Standard distribution
- Premium quote only
- Press release writing
- Account-specific custom service carrier

Customer metadata:

- `billing_price_standard_release`
- `billing_custom_services`
- `billing_custom_services_{index}_name`
- `billing_custom_services_{index}_price`
- `billing_allow_credit_card`

Order and fulfillment metadata:

- `_order_press_release_title`
- `_order_service_title`
- `_hpr_billing_fulfillment_post_id`
- `billing_invoice_id`
- `billing_original_title`
- `billing_service`

Prices and custom-service entitlements are resolved from the server on every cart totals pass. Query-string prices are never accepted. A custom cart becomes invalid when the signed-in account no longer owns its service. Migration also clears the custom carrier's persisted fallback price, while rollback restores the exact prior product values.

Managed payment gateways are allowlisted to ACH and, only for entitled accounts, Stripe card. The Stripe universal Payment Element is constrained to `us_bank_account`, and checkout validates the submitted payment type server-side.

## Customer Surfaces

- `[hpr_billing_catalog]` renders the public package catalog.
- `[hpr_billing_order_portal]` requires sign-in before displaying account pricing and custom services.
- `wp-admin/admin.php?page=hpr-place-order` provides the authenticated admin order portal.

The mapped Packages, Submit, and Checkout pages explicitly bypass full-page caches. This prevents guest catalog output from hiding signed-in prices and custom-service entitlements.

## Migration

The migration validates all dependencies before its first mutation. It then:

1. Acquires a short-lived migration lock.
2. Backs up Checkout, Packages, and Submit page content plus the custom carrier price fields.
3. Assigns canonical shortcodes to the three pages and removes the custom carrier fallback price.
4. Disables legacy Code Snippets IDs 26, 30, 34, 43, and 44.
5. Deactivates Flexible Checkout Fields when it was active.
6. Verifies and enables the plugin runtime.

Any page or snippet failure triggers automatic restoration. A persisted backup also supports an explicit rollback.

```bash
wp hpr-billing audit
wp hpr-billing preflight
wp hpr-billing migrate --confirm=MIGRATE
wp hpr-billing status
wp hpr-billing rollback --confirm=ROLLBACK
```

See [docs/migration-runbook.md](docs/migration-runbook.md) before production execution.

## Verification

Run the isolated suite from the plugin root:

```bash
bash tests/run.sh
```

The suite verifies navigation, guarded defaults, customer pricing, custom-product access, legacy cart adoption/rejection, ACH/card enforcement, migration preflight, Core integration, package integrity, and PHP syntax.

## Git Reporting

The canonical public source is `mikeyperes/hexa-pr-wire-billing` on `main`. The dashboard compares the installed plugin and bundled Core versions against their respective repositories without storing or displaying GitHub credentials.
