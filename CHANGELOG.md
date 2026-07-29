# Changelog

## 1.0.5 - 2026-07-29

- Add read-only Service Order Portal provenance to the post editor, including purchase time, visible price, Stripe invoice ID, billing mode, and service.
- Add a validated, authenticated Billing link for opening the source order without exposing it on public pages.
- Add regression coverage for provenance metadata, price formatting, and Billing-host link restrictions.

## 1.0.4 - 2026-07-23

- Stack the overview integration report rows on narrow admin viewports so table content stays inside the Core tab panel.
- Constrain long titlebar content on mobile and add a regression contract for the responsive report treatment.

## 1.0.3 - 2026-07-23

- Move billing administration onto the Hexa WP Core tab registry and grouped sidebar renderer used by SMP Publication Integration.
- Add plugin and Hexa WP Core Git version identity to the Core-owned navigation rail.
- Preserve direct, grouped, legacy, Core, and third-party extension tab routes.
- Add regression coverage for Core tab definitions, sidebar groups, capabilities, and renderer dispatch.

## 1.0.2 - 2026-07-23

- Prevent full-page caches from serving guest catalog and portal output to signed-in customers.
- Mark Packages, Submit, and Checkout non-cacheable through WordPress and LiteSpeed contracts.
- Preserve entitled custom-service carts across WooCommerce session hydration while retaining server-side entitlement checks.
- Add regression coverage for personalized-page cache isolation.
- Publish the canonical plugin source for live Git version reporting.

## 1.0.1 - 2026-07-23

- Remove the custom carrier's persisted fallback price during migration and restore it on rollback.
- Allowlist ACH plus explicitly entitled Stripe card checkout for managed orders.
- Constrain Stripe universal Payment Elements to bank-account payments and reject direct card bypasses.
- Add a migration/rollback lock and verify backup, plugin deactivation, runtime, and migration-state writes.
- Expand preflight coverage to product mappings, replacement features, and ACH readiness.
- Add gateway-policy and custom-carrier regression tests.

## 1.0.0 - 2026-07-23

- Introduce a guarded, plugin-owned billing and checkout runtime.
- Add server-authoritative product, account pricing, and custom-service policies.
- Add managed checkout fields, payment policy, Stripe descriptions, and idempotent fulfillment.
- Add public catalog and signed-in ordering surfaces.
- Add ACF customer billing and fulfillment linkage structures.
- Add Hexa WP Core navigation, feature tests, reports, activity, Git reporting, and Core package reporting.
- Add a preflighted, backed-up, reversible legacy ownership migration.
- Add WP-CLI audit, status, migration, and rollback commands.
