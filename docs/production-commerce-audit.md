# HexaPRWire.com Commerce Audit

Audit snapshot: 2026-07-23

Production root: `/home/hexaprwire/public_html`

No production mutation was performed during discovery.

## Executive Finding

Billing and checkout ownership was fragmented across WooCommerce, Woo Stripe Payment, Flexible Checkout Fields, ACF, Elementor, Code Snippets, theme/page content, and user metadata. The same behaviors were implemented at different hook priorities without one source of truth. The highest-risk path allowed the custom carrier product to be purchased by a guest for its persisted one-dollar product price through a direct add-to-cart request.

The replacement is a separate commerce plugin. Folding these policies into the distributor plugin would combine payment state with syndication delivery and make rollback, reporting, and ownership less clear.

## Platform Inventory

- WordPress 7.0.2
- PHP 8.5.7
- WooCommerce 10.8.0
- Classic order storage; HPOS disabled
- Stripe live mode configured
- Stripe live webhook ID and signing secret present; values intentionally excluded
- WooCommerce terms page assigned to the Privacy Policy page

## Page Structure

| Surface | Page ID | Discovery state | Replacement owner |
| --- | ---: | --- | --- |
| Packages | 41 | Blank page content | `[hpr_billing_catalog]` |
| Checkout | 81 | Saved checkout render in post content plus dynamic Elementor Woo checkout widget | Canonical `[woocommerce_checkout]` fallback; Elementor widget may remain the visual renderer |
| Submit | 261050 | Blank page content | `[hpr_billing_order_portal]` |

The saved Checkout post content was not an acceptable fallback because it captured generated checkout markup rather than a canonical WooCommerce shortcode.

## Product Structure

Configured product roles found in the legacy implementation:

- Standard distribution: product 84, published base price $120
- Premium distribution/quote: product 85, published without a direct price
- Press release writing: product 260868, published base price $150
- Custom service carrier: product 323645, published base price $1

Product 323645 had a persisted price of one dollar. Legacy request-time price mutation was the only intended protection, making direct or stale-cart access unsafe.

The replacement migration backs up and clears that persisted fallback price. The plugin only marks the carrier purchasable for a signed-in account with a current server-side service entitlement, and rollback restores the exact prior product values.

## Legacy Code Snippets

| ID | Role | Audit result |
| ---: | --- | --- |
| 26 | WooCommerce cart, pricing, redirects, and fulfillment | Active; globally emptied carts, used session/global price state, and created drafts on processing and completed without durable idempotency |
| 30 | Purchase page | Present but code commented |
| 34 | Stripe payment description | Active; overlaps payment metadata ownership |
| 43 | Place an Order admin screen | Active; customer ordering UI embedded in a snippet |
| 44 | Payment gateway policy | Active; account-specific card access embedded in a snippet |

These five snippets are the only snippets transferred by the billing migration. Their exact prior active states are backed up for rollback.

## Checkout and Payment Behavior

- Guest and ordinary customer checkout exposed ACH.
- Stripe card access depended on `billing_allow_credit_card` user metadata.
- Standard account pricing depended on `billing_price_standard_release`.
- Custom services used ACF repeater-compatible metadata under `billing_custom_services_{index}_{name|price}`.
- Checkout fields were partly owned by Flexible Checkout Fields and partly by snippets.
- Stripe live webhook configuration was present.
- Sensitive Stripe credentials were not copied into the plugin or audit artifacts.

## Historical Orders

115 orders were present at the audit snapshot:

- Processing: 95
- Completed: 7
- Cancelled: 11
- Failed: 2

Forty-eight zero-dollar processing orders had no payment method. Fulfillment metadata across historical orders and posts was incomplete, so the replacement preserves legacy invoice-link discovery while writing a new durable order-to-post relationship.

## ACF and Metadata

Legacy nested billing field keys:

- User parent: `field_6640053880290`
- Post parent: `field_66417d52d4884`

The replacement hides these legacy parent fields only after runtime ownership transfers. New local ACF groups retain the existing metadata names so customer prices and service rows do not require a bulk data rewrite.

## Security Findings

### Critical: Guest custom-product purchase

Product 323645 could be added directly by a guest and purchased at its one-dollar persisted price. The replacement rejects guest custom purchases, requires a current account-owned service key, resolves the amount server-side, revalidates it during cart totals and checkout, and blocks stale legacy custom carts.

### High: Non-idempotent fulfillment

Legacy processing and completed hooks could create duplicate drafts. The replacement checks durable order metadata, searches legacy invoice links, acquires an expiring lock, and writes a reversible order-to-post link.

### High: Unauthenticated email actions outside commerce scope

Legacy snippet 27 exposed unauthenticated email AJAX actions without nonces. This finding is outside the billing plugin boundary and still requires a separate remediation.

### High: Plaintext generated passwords outside commerce scope

Legacy snippet 32 stored generated user passwords in plaintext ACF metadata. This finding is outside the billing plugin boundary and still requires credential invalidation, data cleanup, and workflow replacement.

### Medium: Fragmented payment policy

Gateway availability and Stripe descriptions were spread across snippets. The replacement allowlists ACH plus entitled Stripe card, constrains the universal Payment Element to bank-account payments, validates the submitted method server-side, and provides one bounded Stripe intent description.

### Medium: Noncanonical checkout fallback

Checkout post content stored generated markup. The migration replaces it with a canonical shortcode fallback and preserves the prior content in its rollback backup.

### Medium: Incorrect legal-page assignment

WooCommerce points the terms checkbox to the Privacy Policy page. A dedicated Terms and Conditions page must be assigned separately from this plugin migration.

## Replacement Mapping

| Legacy owner | Replacement module |
| --- | --- |
| Snippet 26 cart and prices | `Commerce\Cart\ManagedCart` and `Commerce\Pricing\CustomerPricingRepository` |
| Flexible Checkout Fields and checkout snippet logic | `Commerce\Checkout\CheckoutFields` |
| Snippet 44 | `Commerce\Payments\GatewayPolicy` |
| Snippet 34 | `Commerce\Payments\StripeDescription` |
| Snippet 26 draft creation | `Commerce\Fulfillment\OrderFulfillment` |
| Blank Packages and Submit pages | `Commerce\Catalog\CatalogShortcode` |
| Snippet 43 | `Admin\OrderPortal` |
| Legacy nested ACF billing UI | `Fields\AcfFields` |
| Manual database inspection | `Reports\CommerceReport`, activity log, admin dashboard, and WP-CLI audit |

## Required Acceptance Criteria

- Plugin activates with runtime disabled.
- Migration preflight passes before any mutation.
- Legacy commerce snippets and Flexible Checkout Fields stop owning checkout after migration.
- Direct guest custom-product requests fail.
- Custom price and service values cannot be supplied by a request.
- Existing account-specific standard prices and custom-service rows remain usable.
- Managed checkout contains one line and no unrelated products.
- ACH and entitled-card behavior match policy.
- Draft fulfillment is idempotent across processing and completed transitions.
- Checkout, Packages, and Submit have canonical plugin-owned fallbacks.
- Dashboard overview, reports, features, ACF, Git, and Core sections render.
- Rollback restores page, snippet, plugin, and runtime state.
