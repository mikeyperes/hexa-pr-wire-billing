# Production Migration Runbook

## Preconditions

- Deploy and activate the plugin in observation mode.
- Confirm `wp hpr-billing status` reports `runtime_enabled: false`.
- Run `wp hpr-billing audit` and retain the output.
- Confirm the admin Integrity and Features screens load.
- Run `wp hpr-billing preflight` and confirm it contains no FAIL result.
- Confirm the configured Checkout, Packages, and Submit IDs are distinct and correct.
- Confirm a recent database backup exists outside the plugin migration backup.
- Confirm the custom carrier product is available; migration will back up and remove its persisted fallback price.

## Ownership Transfer

Run one migration path only, either the admin control with the exact confirmation `MIGRATE` or WP-CLI:

```bash
wp hpr-billing migrate --confirm=MIGRATE
```

The command must report:

- Runtime enabled
- Checkout, Packages, and Submit pages claimed
- Active legacy commerce snippets disabled
- Flexible Checkout Fields deactivated when previously active
- Custom carrier persisted price cleared
- Migration state `complete`

## Verification

Do not submit a real payment during rollout verification.

1. Run `wp hpr-billing audit` again.
2. Confirm snippets 26, 30, 34, 43, and 44 are inactive.
3. Confirm Flexible Checkout Fields is inactive.
4. Confirm custom product 323645 has no persisted fallback price.
5. Confirm Packages renders `[hpr_billing_catalog]` output.
6. Confirm Submit requires sign-in and renders account services after sign-in.
7. Confirm the Checkout fallback contains `[woocommerce_checkout]`.
8. Confirm a guest cannot add custom product 323645, including a direct add-to-cart URL.
9. Confirm a signed-in account sees its configured standard price and custom services.
10. Confirm changing or removing a custom entitlement invalidates the existing custom cart.
11. Confirm ACH is available for a managed checkout and UPM cannot submit a card method.
12. Confirm Stripe card is hidden by default and shown only for an entitled account.
13. Confirm article title validation runs.
14. Confirm no shipping or physical-address fields appear for managed products.
15. Confirm the admin dashboard, features, ACF, Git, Core, order, integrity, and activity sections load.

## Rollback

Use rollback when a migration-owned surface cannot pass verification:

```bash
wp hpr-billing rollback --confirm=ROLLBACK
```

Rollback disables the plugin runtime, restores captured page content and custom carrier prices, restores each audited snippet's prior active state, and reactivates Flexible Checkout Fields when it was active before migration.

After rollback, purge page/object caches and verify the legacy checkout before reopening sales traffic.
