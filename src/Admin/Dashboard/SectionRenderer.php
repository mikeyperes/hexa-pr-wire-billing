<?php

namespace HexaPrWire\Billing\Admin\Dashboard;

use Hexa\PluginCore\CorePackageUpdates\CorePackagePanelRenderer;
use Hexa\PluginCore\FieldStructures\FieldStructureRenderer;
use Hexa\PluginCore\PluginUpdates\UpdaterPanelRenderer;
use Hexa\PluginCore\SnippetRegistry\SnippetsTableRenderer;
use HexaPrWire\Billing\Admin\Ajax;
use HexaPrWire\Billing\Commerce\ProductCatalog;
use HexaPrWire\Billing\Config;
use HexaPrWire\Billing\Fields\AcfFields;
use HexaPrWire\Billing\Migration\LegacyCommerceMigration;
use HexaPrWire\Billing\Reports\CommerceReport;
use HexaPrWire\Billing\Settings\SettingsRepository;
use HexaPrWire\Billing\Support\Activity;
use HexaPrWire\Billing\Support\Dependencies;
use HexaPrWire\Billing\Support\FeatureDefinitions;

final class SectionRenderer {
    private CommerceReport $report;

    public function __construct( ?CommerceReport $report = null ) {
        $this->report = $report ?? new CommerceReport();
    }

    public function render( string $section ): void {
        switch ( sanitize_key( $section ) ) {
            case 'catalog':
                $this->catalog();
                return;
            case 'checkout':
                $this->checkout();
                return;
            case 'payments':
                $this->payments();
                return;
            case 'fulfillment':
                $this->fulfillment();
                return;
            case 'pricing':
                $this->pricing();
                return;
            case 'order_portal':
                $this->order_portal();
                return;
            case 'orders':
                $this->orders();
                return;
            case 'integrity':
                $this->integrity();
                return;
            case 'activity':
                $this->activity();
                return;
            case 'features':
                $this->features();
                return;
            case 'custom_fields':
                $this->custom_fields();
                return;
            case 'git_updates':
                $this->git_updates();
                return;
            default:
                $this->overview();
        }
    }

    private function overview(): void {
        $summary   = $this->report->summary();
        $migration = SettingsRepository::migration_state();
        $runtime   = SettingsRepository::runtime_enabled();
        ?>
        <section class="hpr-billing-lead">
            <div>
                <p class="hpr-billing-kicker">Billing runtime</p>
                <h2><?php echo esc_html( $runtime ? 'Plugin ownership is active' : 'Observation mode is active' ); ?></h2>
                <p><?php echo esc_html( $runtime ? 'Checkout, pricing, payment policy, and fulfillment are owned by this plugin.' : 'Legacy billing remains live while the replacement is audited and prepared.' ); ?></p>
            </div>
            <?php echo $this->status_badge( $runtime ? 'Active' : 'Guarded', $runtime ? 'pass' : 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>

        <div class="hpr-billing-metrics">
            <?php $this->metric( 'Orders', (string) $summary['orders'], 'All recorded WooCommerce orders' ); ?>
            <?php $this->metric( 'Active gross', $this->money( (float) $summary['active_gross'] ), 'Processing and completed' ); ?>
            <?php $this->metric( 'Paid active', (string) $summary['active_paid_orders'], 'Positive total with payment method' ); ?>
            <?php $this->metric( 'Zero total', (string) $summary['zero_total_orders'], 'Historical data requiring review' ); ?>
            <?php $this->metric( 'Fulfilled', (string) $summary['linked_fulfillments'], 'Plugin order-to-draft links' ); ?>
        </div>

        <section class="hpr-billing-panel">
            <div class="hpr-billing-panel__head"><div><h2>Integration status</h2><p>Required runtime and managed integration boundaries.</p></div></div>
            <table class="widefat striped hpr-billing-table"><tbody>
                <?php $this->status_row( 'WooCommerce', Dependencies::woocommerce_active(), 'Required commerce engine' ); ?>
                <?php $this->status_row( 'ACF Pro', Dependencies::acf_active(), 'Customer pricing and fulfillment field interface', true ); ?>
                <?php $this->status_row( 'Hexa WP Core', defined( 'HEXA_PLUGIN_CORE_SELECTED_VERSION' ), defined( 'HEXA_PLUGIN_CORE_SELECTED_VERSION' ) ? 'Selected package ' . HEXA_PLUGIN_CORE_SELECTED_VERSION : 'No selected Core package' ); ?>
                <tr><th>Migration state</th><td><?php echo esc_html( (string) ( $migration['status'] ?? 'not_started' ) ); ?></td><td><?php echo esc_html( (string) ( $migration['completed_at'] ?? $migration['rolled_back_at'] ?? 'No ownership transfer recorded' ) ); ?></td></tr>
            </tbody></table>
        </section>

        <?php $this->migration_panel(); ?>
        <?php
    }

    private function catalog(): void {
        $settings = SettingsRepository::all();
        ?>
        <?php $this->section_heading( 'Managed product catalog', 'Products are mapped by ID; customer prices are resolved server-side and never accepted from query parameters.' ); ?>
        <section class="hpr-billing-panel">
            <table class="widefat striped hpr-billing-table">
                <thead><tr><th>Role</th><th>Product</th><th>Status</th><th>Base price</th><th>Virtual</th><th></th></tr></thead>
                <tbody>
                <?php foreach ( $this->report->product_rows() as $row ) : ?>
                    <tr>
                        <th><?php echo esc_html( ucfirst( (string) $row['kind'] ) ); ?></th>
                        <td><strong><?php echo esc_html( (string) $row['name'] ); ?></strong><br><code>#<?php echo absint( $row['id'] ); ?></code></td>
                        <td><?php echo $this->status_badge( (string) $row['status'], ! empty( $row['exists'] ) && 'publish' === $row['status'] ? 'pass' : 'fail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td><?php echo '' !== (string) $row['price'] ? wp_kses_post( $this->money( (float) $row['price'] ) ) : '&mdash;'; ?></td>
                        <td><?php echo ! empty( $row['virtual'] ) ? 'Yes' : 'No'; ?></td>
                        <td><?php if ( ! empty( $row['edit_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( (string) $row['edit_url'] ); ?>">Edit</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
        $this->settings_form(
            'Product mapping',
            'Only update these IDs when the corresponding WooCommerce product has been reviewed.',
            [
                'standard_product_id' => [ 'Standard distribution product ID', 'number' ],
                'premium_product_id'  => [ 'Premium quote product ID', 'number' ],
                'writing_product_id'  => [ 'Writing product ID', 'number' ],
                'custom_product_id'   => [ 'Custom service carrier product ID', 'number' ],
            ],
            $settings
        );
    }

    private function checkout(): void {
        $settings = SettingsRepository::all();
        $checks   = $this->checks_by_label();
        ?>
        <?php $this->section_heading( 'Checkout ownership', 'Canonical pages, digital-service fields, validation, and order metadata.' ); ?>
        <div class="hpr-billing-two-column">
            <section class="hpr-billing-panel">
                <div class="hpr-billing-panel__head"><div><h2>Critical pages</h2><p>Configured page IDs and current public targets.</p></div></div>
                <table class="widefat striped hpr-billing-table"><tbody>
                    <?php $this->page_row( 'Checkout', (int) $settings['checkout_page_id'], $checks['Canonical checkout fallback'] ?? null ); ?>
                    <?php $this->page_row( 'Packages', (int) $settings['packages_page_id'], $checks['Packages page'] ?? null ); ?>
                    <?php $this->page_row( 'Submit', (int) $settings['submit_page_id'], $checks['Submit page'] ?? null ); ?>
                </tbody></table>
            </section>
            <section class="hpr-billing-panel">
                <div class="hpr-billing-panel__head"><div><h2>Field contract</h2><p>Applied only when a managed billing product is in the cart.</p></div></div>
                <dl class="hpr-billing-definition-list">
                    <div><dt>Required</dt><dd>First name, last name, email, and article title</dd></div>
                    <div><dt>Optional</dt><dd>Company and order notes</dd></div>
                    <div><dt>Removed</dt><dd>Shipping and physical-address collection</dd></div>
                    <div><dt>Stored</dt><dd>Article title, service title, kind, unit-price snapshot, and fulfillment link</dd></div>
                </dl>
            </section>
        </div>
        <?php
        $this->settings_form(
            'Page mapping',
            'The migration validates these pages before replacing their fallback content.',
            [
                'checkout_page_id' => [ 'Checkout page ID', 'number' ],
                'packages_page_id' => [ 'Packages page ID', 'number' ],
                'submit_page_id'   => [ 'Submit page ID', 'number' ],
            ],
            $settings
        );
    }

    private function payments(): void {
        $checks = $this->checks_by_label();
        $gateways = $this->report->gateway_rows();
        ?>
        <?php $this->section_heading( 'Payment policy', 'ACH remains available for managed orders. Stripe card is exposed only when the customer account permits it.' ); ?>
        <section class="hpr-billing-panel">
            <table class="widefat striped hpr-billing-table">
                <thead><tr><th>Gateway</th><th>ID</th><th>Enabled</th><th>Implementation</th></tr></thead>
                <tbody>
                <?php foreach ( $gateways as $row ) : ?>
                    <tr><th><?php echo esc_html( (string) $row['title'] ); ?></th><td><code><?php echo esc_html( (string) $row['id'] ); ?></code></td><td><?php echo $this->status_badge( ! empty( $row['enabled'] ) ? 'Enabled' : 'Disabled', ! empty( $row['enabled'] ) ? 'pass' : 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><code><?php echo esc_html( (string) $row['class'] ); ?></code></td></tr>
                <?php endforeach; ?>
                <?php if ( [] === $gateways ) : ?><tr><td colspan="4">No matching payment gateway could be loaded.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
        <section class="hpr-billing-panel">
            <div class="hpr-billing-panel__head"><div><h2>Stripe readiness</h2><p>Credential values are never displayed in this report.</p></div></div>
            <?php $this->checks_table( array_values( array_filter( $checks, static fn( array $check ): bool => str_starts_with( (string) $check['label'], 'Stripe' ) ) ) ); ?>
        </section>
        <?php
    }

    private function fulfillment(): void {
        $settings = SettingsRepository::all();
        $summary  = $this->report->summary();
        ?>
        <?php $this->section_heading( 'Editorial fulfillment', 'Processing and completed orders create at most one linked draft through a lock and persisted order-to-post relationship.' ); ?>
        <div class="hpr-billing-metrics">
            <?php $this->metric( 'Plugin links', (string) $summary['linked_fulfillments'], 'Order meta linkage' ); ?>
            <?php $this->metric( 'Legacy links', (string) $summary['legacy_links'], 'Historical invoice metadata' ); ?>
            <?php $this->metric( 'Unlinked active', (string) max( 0, (int) $summary['active_paid_orders'] - (int) $summary['linked_fulfillments'] ), 'Requires order review' ); ?>
        </div>
        <?php
        $this->settings_form(
            'Draft routing',
            'Category values are slugs. Missing categories do not block draft creation.',
            [
                'standard_category' => [ 'Standard release category slug', 'text' ],
                'custom_category'   => [ 'Custom order category slug', 'text' ],
            ],
            $settings
        );
        $this->recent_orders_table( 12, true );
    }

    private function pricing(): void {
        $pricing = $this->report->pricing_summary();
        ?>
        <?php $this->section_heading( 'Customer pricing', 'Account pricing is stored in user metadata and resolved again on the server when a managed item enters the cart.' ); ?>
        <div class="hpr-billing-two-column">
            <section class="hpr-billing-panel">
                <div class="hpr-billing-panel__head"><div><h2>Standard prices</h2><p>Distinct assigned prices and affected account counts.</p></div></div>
                <table class="widefat striped hpr-billing-table"><thead><tr><th>Price</th><th>Accounts</th></tr></thead><tbody>
                    <?php foreach ( $pricing['prices'] as $row ) : ?><tr><td><?php echo wp_kses_post( $this->money( (float) $row['price'] ) ); ?></td><td><?php echo absint( $row['users'] ); ?></td></tr><?php endforeach; ?>
                    <?php if ( [] === $pricing['prices'] ) : ?><tr><td colspan="2">No customer-specific standard prices found.</td></tr><?php endif; ?>
                </tbody></table>
            </section>
            <section class="hpr-billing-panel">
                <div class="hpr-billing-panel__head"><div><h2>Entitlements</h2><p>Stored card-access and custom-service row counts.</p></div></div>
                <table class="widefat striped hpr-billing-table"><thead><tr><th>Card setting</th><th>Accounts</th></tr></thead><tbody>
                    <?php foreach ( $pricing['cards'] as $row ) : ?><tr><td><?php echo '1' === (string) $row['allowed'] ? 'Allowed' : 'Not allowed'; ?></td><td><?php echo absint( $row['users'] ); ?></td></tr><?php endforeach; ?>
                    <?php if ( [] === $pricing['cards'] ) : ?><tr><td colspan="2">No explicit card entitlements found.</td></tr><?php endif; ?>
                </tbody></table>
                <p class="hpr-billing-panel-note"><a class="button" href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">Manage customer accounts</a></p>
            </section>
        </div>
        <?php
    }

    private function order_portal(): void {
        $settings = SettingsRepository::all();
        ?>
        <?php $this->section_heading( 'Order portal', 'The public catalog and signed-in order portal share one server-authoritative product and pricing layer.' ); ?>
        <section class="hpr-billing-panel">
            <table class="widefat striped hpr-billing-table"><thead><tr><th>Surface</th><th>Shortcode</th><th>Page</th><th></th></tr></thead><tbody>
                <?php $this->portal_row( 'Public packages', '[hpr_billing_catalog]', (int) $settings['packages_page_id'] ); ?>
                <?php $this->portal_row( 'Signed-in ordering', '[hpr_billing_order_portal]', (int) $settings['submit_page_id'] ); ?>
                <tr><th>WP admin ordering</th><td><code>admin.php?page=hpr-place-order</code></td><td>Authenticated users</td><td><a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=hpr-place-order' ) ); ?>">Open</a></td></tr>
            </tbody></table>
        </section>
        <?php
        $this->settings_form(
            'Customer support',
            'This address appears in the customer order portal and premium quote action.',
            [ 'support_email' => [ 'Support email', 'email' ] ],
            $settings
        );
    }

    private function orders(): void {
        $summary = $this->report->summary();
        ?>
        <?php $this->section_heading( 'Order reporting', 'WooCommerce CRUD reporting remains compatible with classic order storage and HPOS.' ); ?>
        <div class="hpr-billing-metrics">
            <?php $this->metric( 'Total', (string) $summary['orders'], 'All statuses' ); ?>
            <?php foreach ( $summary['statuses'] as $status => $count ) : ?><?php $this->metric( ucfirst( (string) $status ), (string) $count, 'Orders' ); ?><?php endforeach; ?>
        </div>
        <?php $this->recent_orders_table( 25 ); ?>
        <?php
    }

    private function integrity(): void {
        ?>
        <div class="hpr-billing-section-heading hpr-billing-section-heading--actions">
            <div><h2>Commerce integrity</h2><p>Live configuration checks for ownership, pages, products, payment readiness, and storage.</p></div>
            <button type="button" class="button button-primary" data-hpr-billing-action="hpr_billing_refresh_report">Refresh report</button>
        </div>
        <section class="hpr-billing-panel"><?php $this->checks_table( $this->report->integrity_checks() ); ?></section>
        <?php
    }

    private function activity(): void {
        $this->section_heading( 'Billing activity', 'Permanent bounded audit entries for settings, migration, fulfillment, and feature ownership.' );
        Activity::render();
    }

    private function features(): void {
        $this->section_heading( 'Billing features', 'Hexa WP Core feature definitions expose ownership, runtime hooks, enablement, tests, and implementation notes.' );
        echo ( new SnippetsTableRenderer() )->render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            FeatureDefinitions::registry(),
            [
                'title'         => 'Billing features',
                'description'   => 'Each feature is independently reported and remains behind the guarded runtime migration.',
                'toggle_action' => 'hpr_billing_feature_toggle',
                'test_action'   => 'hpr_billing_feature_test',
                'nonce'         => Ajax::nonce(),
                'nonce_field'   => 'nonce',
                'root_id'       => 'hpr-billing-features',
                'categories'    => [
                    'cart-checkout'       => [ 'label' => 'Cart and checkout' ],
                    'pricing'             => [ 'label' => 'Pricing' ],
                    'payments'            => [ 'label' => 'Payments' ],
                    'fulfillment'         => [ 'label' => 'Fulfillment' ],
                    'customer-experience' => [ 'label' => 'Customer experience' ],
                    'fields'              => [ 'label' => 'Custom fields' ],
                ],
            ]
        );
    }

    private function custom_fields(): void {
        $this->section_heading( 'ACF field structures', 'Plugin-owned field definitions preserve the existing billing metadata names while removing runtime dependence on legacy nested groups.' );
        $enabled = SettingsRepository::runtime_enabled() && SettingsRepository::feature_enabled( 'acf_fields' );
        echo ( new FieldStructureRenderer() )->render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            [
                [
                    'id'            => 'billing_customer_settings',
                    'label'         => 'Customer billing settings',
                    'type'          => 'acf',
                    'description'   => 'Per-account standard pricing, custom service rows, and card access.',
                    'setting_key'   => SettingsRepository::FEATURE_OPTIONS['acf_fields'],
                    'enabled'       => $enabled,
                    'registered'    => static fn(): bool => function_exists( 'acf_get_field_group' ) && (bool) acf_get_field_group( AcfFields::CUSTOMER_GROUP ),
                    'acf_group_key' => AcfFields::CUSTOMER_GROUP,
                    'object_name'   => 'user',
                    'location'      => 'User edit screen for administrators',
                    'fields'        => [ 'billing_price_standard_release', 'billing_custom_services', 'billing_allow_credit_card' ],
                    'dependencies'  => [ 'ACF Pro', 'WordPress user metadata' ],
                    'instructions'  => 'Edit a customer account in Users. Empty standard pricing falls back to the mapped product price.',
                    'test_report'   => Dependencies::acf_active() ? 'ACF is available; registration is reported above.' : 'ACF is unavailable; metadata remains readable without the field interface.',
                ],
                [
                    'id'            => 'billing_fulfillment_linkage',
                    'label'         => 'Fulfillment linkage',
                    'type'          => 'acf',
                    'description'   => 'Read-only order, original title, and service metadata on generated editorial drafts.',
                    'setting_key'   => SettingsRepository::FEATURE_OPTIONS['acf_fields'],
                    'enabled'       => $enabled,
                    'registered'    => static fn(): bool => function_exists( 'acf_get_field_group' ) && (bool) acf_get_field_group( AcfFields::ORDER_GROUP ),
                    'acf_group_key' => AcfFields::ORDER_GROUP,
                    'object_name'   => 'post',
                    'location'      => 'Post edit screen for administrators',
                    'fields'        => [ 'billing_invoice_id', 'billing_original_title', 'billing_service' ],
                    'dependencies'  => [ 'ACF Pro', 'WooCommerce order CRUD', 'WordPress post metadata' ],
                    'instructions'  => 'Values are written by fulfillment and are not customer-editable.',
                    'test_report'   => Dependencies::acf_active() ? 'ACF is available; registration is reported above.' : 'ACF is unavailable; post metadata still persists during fulfillment.',
                ],
            ],
            [
                'title'       => 'Billing field structures',
                'description' => 'Stable metadata contracts shared with the existing site.',
                'save_action' => 'hpr_billing_save_field_structure',
                'nonce'       => Ajax::nonce(),
                'nonce_field' => 'nonce',
            ]
        );
    }

    private function git_updates(): void {
        $this->section_heading( 'Git reporting', 'Plugin and bundled Core versions are independently compared against their configured GitHub sources.' );
        ?>
        <section class="hpr-billing-callout">
            <strong>Canonical repository</strong>
            <p><code><?php echo esc_html( Config::$github_repo ); ?></code> on <code><?php echo esc_html( Config::$github_branch ); ?></code> is the public source used by plugin update reporting.</p>
        </section>
        <?php
        ( new UpdaterPanelRenderer( \HexaPrWire\Billing\updater_config() ) )->render();
        ( new CorePackagePanelRenderer( \HexaPrWire\Billing\core_package_config() ) )->render();
    }

    private function migration_panel(): void {
        $migration = new LegacyCommerceMigration();
        $preflight = $migration->preflight();
        $state     = SettingsRepository::migration_state();
        $backup    = get_option( LegacyCommerceMigration::BACKUP_OPTION, [] );
        ?>
        <section class="hpr-billing-panel hpr-billing-migration">
            <div class="hpr-billing-panel__head">
                <div><h2>Ownership migration</h2><p>Preflight, backup, page claims, legacy snippet shutdown, checkout-field transfer, and reversible rollback.</p></div>
                <?php echo $this->status_badge( (string) ( $state['status'] ?? 'Not run' ), SettingsRepository::runtime_enabled() ? 'pass' : 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <?php $this->checks_table( $preflight ); ?>
            <div class="hpr-billing-migration-actions">
                <label><span>Type <code>MIGRATE</code></span><input type="text" autocomplete="off" data-hpr-billing-confirm="hpr_billing_run_migration"></label>
                <button type="button" class="button button-primary" data-hpr-billing-action="hpr_billing_run_migration" <?php disabled( SettingsRepository::runtime_enabled() ); ?>>Transfer ownership</button>
                <label><span>Type <code>ROLLBACK</code></span><input type="text" autocomplete="off" data-hpr-billing-confirm="hpr_billing_run_rollback" <?php disabled( ! is_array( $backup ) || [] === $backup ); ?>></label>
                <button type="button" class="button" data-hpr-billing-action="hpr_billing_run_rollback" <?php disabled( ! is_array( $backup ) || [] === $backup ); ?>>Restore legacy state</button>
            </div>
            <p class="hpr-billing-action-status" data-hpr-billing-action-status aria-live="polite"></p>
        </section>
        <?php
    }

    private function recent_orders_table( int $limit, bool $linked_only = false ): void {
        $rows = $this->report->recent_orders( $limit );
        if ( $linked_only ) {
            $rows = array_values( array_filter( $rows, static fn( array $row ): bool => ! empty( $row['post_id'] ) ) );
        }
        ?>
        <section class="hpr-billing-panel">
            <div class="hpr-billing-panel__head"><div><h2><?php echo esc_html( $linked_only ? 'Recent fulfillment links' : 'Recent orders' ); ?></h2><p>Newest WooCommerce order records.</p></div></div>
            <div class="hpr-billing-table-scroll"><table class="widefat striped hpr-billing-table"><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Items</th><th>Status</th><th>Payment</th><th>Total</th><th>Draft</th></tr></thead><tbody>
            <?php foreach ( $rows as $row ) : ?>
                <tr>
                    <th><a href="<?php echo esc_url( (string) $row['order_url'] ); ?>">#<?php echo absint( $row['id'] ); ?></a></th>
                    <td><?php echo esc_html( (string) $row['date'] ); ?></td>
                    <td><?php echo esc_html( (string) $row['customer'] ); ?></td>
                    <td><?php echo esc_html( (string) $row['items'] ); ?></td>
                    <td><?php echo $this->status_badge( (string) $row['status'], in_array( $row['status'], [ 'processing', 'completed' ], true ) ? 'pass' : 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    <td><?php echo esc_html( (string) $row['method'] ); ?></td>
                    <td><?php echo wp_kses_post( (string) $row['total'] ); ?></td>
                    <td><?php if ( ! empty( $row['post_id'] ) ) : ?><a href="<?php echo esc_url( (string) $row['post_url'] ); ?>">#<?php echo absint( $row['post_id'] ); ?></a><?php else : ?>&mdash;<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ( [] === $rows ) : ?><tr><td colspan="8">No matching orders found.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
        <?php
    }

    private function settings_form( string $title, string $description, array $fields, array $settings ): void {
        ?>
        <section class="hpr-billing-panel">
            <div class="hpr-billing-panel__head"><div><h2><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $description ); ?></p></div></div>
            <form class="hpr-billing-settings-form" data-hpr-billing-settings>
                <div class="hpr-billing-form-grid">
                    <?php foreach ( $fields as $name => $field ) : ?>
                        <label><span><?php echo esc_html( (string) $field[0] ); ?></span><input type="<?php echo esc_attr( (string) $field[1] ); ?>" name="<?php echo esc_attr( (string) $name ); ?>" value="<?php echo esc_attr( (string) ( $settings[ $name ] ?? '' ) ); ?>" <?php echo 'number' === $field[1] ? 'min="0" step="1"' : ''; ?>></label>
                    <?php endforeach; ?>
                </div>
                <div class="hpr-billing-form-actions"><button type="submit" class="button button-primary">Save settings</button><span data-hpr-billing-form-status aria-live="polite"></span></div>
            </form>
        </section>
        <?php
    }

    private function checks_table( array $checks ): void {
        ?>
        <table class="widefat striped hpr-billing-table hpr-billing-checks"><thead><tr><th>Check</th><th>Status</th><th>Evidence</th></tr></thead><tbody>
        <?php foreach ( $checks as $check ) : ?>
            <tr><th><?php echo esc_html( (string) ( $check['label'] ?? 'Check' ) ); ?></th><td><?php echo $this->status_badge( strtoupper( (string) ( $check['status'] ?? 'fail' ) ), (string) ( $check['status'] ?? 'fail' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo esc_html( (string) ( $check['message'] ?? '' ) ); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php
    }

    private function checks_by_label(): array {
        $checks = [];
        foreach ( $this->report->integrity_checks() as $check ) {
            $checks[ (string) $check['label'] ] = $check;
        }

        return $checks;
    }

    private function status_row( string $label, bool $passed, string $message, bool $optional = false ): void {
        ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo $this->status_badge( $passed ? 'Available' : ( $optional ? 'Optional' : 'Missing' ), $passed ? 'pass' : ( $optional ? 'warn' : 'fail' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo esc_html( $message ); ?></td></tr><?php
    }

    private function page_row( string $label, int $page_id, ?array $check ): void {
        $url = $page_id > 0 ? get_permalink( $page_id ) : '';
        ?><tr><th><?php echo esc_html( $label ); ?></th><td><code>#<?php echo absint( $page_id ); ?></code><br><?php echo esc_html( get_the_title( $page_id ) ?: 'Missing page' ); ?></td><td><?php echo $this->status_badge( strtoupper( (string) ( $check['status'] ?? 'fail' ) ), (string) ( $check['status'] ?? 'fail' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php if ( $url ) : ?><a class="button button-small" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">View</a><?php endif; ?></td></tr><?php
    }

    private function portal_row( string $label, string $shortcode, int $page_id ): void {
        $url = $page_id > 0 ? get_permalink( $page_id ) : '';
        ?><tr><th><?php echo esc_html( $label ); ?></th><td><code><?php echo esc_html( $shortcode ); ?></code></td><td>#<?php echo absint( $page_id ); ?> <?php echo esc_html( get_the_title( $page_id ) ?: 'Missing page' ); ?></td><td><?php if ( $url ) : ?><a class="button button-small" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">View</a><?php endif; ?></td></tr><?php
    }

    private function metric( string $label, string $value, string $detail ): void {
        ?><article class="hpr-billing-metric"><span><?php echo esc_html( $label ); ?></span><strong><?php echo wp_kses_post( $value ); ?></strong><small><?php echo esc_html( $detail ); ?></small></article><?php
    }

    private function section_heading( string $title, string $description ): void {
        ?><div class="hpr-billing-section-heading"><h2><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $description ); ?></p></div><?php
    }

    private function status_badge( string $label, string $status ): string {
        $tone = match ( $status ) {
            'pass', 'success' => 'pass',
            'warn', 'warning' => 'warn',
            default => 'fail',
        };

        return '<span class="hpr-billing-status is-' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
    }

    private function money( float $amount ): string {
        return function_exists( 'wc_price' ) ? (string) wc_price( $amount ) : '$' . number_format( $amount, 2 );
    }
}
