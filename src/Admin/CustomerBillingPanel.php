<?php

namespace HexaPrWire\Billing\Admin;

use HexaPrWire\Billing\Commerce\Pricing\CustomerPricingRepository;
use HexaPrWire\Billing\Settings\SettingsRepository;

/**
 * Renders the customer's billing settings inside Hexa PR Wire Core's
 * "Publications, pricing & payment" profile card, so access, prices and
 * payment are edited in one place.
 */
final class CustomerBillingPanel {
    private const NONCE = 'hpr_billing_customer_settings';

    public function __construct( private CustomerPricingRepository $pricing = new CustomerPricingRepository() ) {}

    public function register(): void {
        if ( ! SettingsRepository::runtime_enabled() ) {
            return;
        }
        add_action( 'hprwc_customer_billing_settings', [ $this, 'render_settings' ] );
        add_action( 'hprwc_customer_billing_services', [ $this, 'render_services' ] );
        add_filter( 'hprwc_customer_default_price', [ $this, 'default_price' ], 10, 2 );
        add_action( 'personal_options_update', [ $this, 'save' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save' ] );
    }

    public function default_price( mixed $price, int $user_id ): string {
        unset( $price );
        return (string) $this->pricing->resolved_standard_price( $user_id );
    }

    public function render_settings( \WP_User $user ): void {
        if ( ! $this->can_manage() ) {
            return;
        }
        $user_id  = (int) $user->ID;
        $standard = (string) $this->pricing->standard_price( $user_id );
        $store    = (string) $this->pricing->resolved_standard_price( 0 );
        ?>
        <div class="hprwc-billing">
            <?php wp_nonce_field( self::NONCE, 'hpr_billing_customer_nonce' ); ?>
            <div class="hprwc-field">
                <label class="hprwc-label" for="hpr_billing_standard_price">Standard price</label>
                <div class="hprwc-inline">
                    <span class="hprwc-price"><span aria-hidden="true">$</span><input type="text" inputmode="decimal" pattern="[0-9]*[.]?[0-9]{0,2}" id="hpr_billing_standard_price" name="hpr_billing_standard_price" value="<?php echo esc_attr( $standard ); ?>" placeholder="<?php echo esc_attr( '' !== $store ? $store : 'Store' ); ?>"></span>
                    <span class="hprwc-billing-help">Per release, unless a publication below has its own price. Empty = store price<?php echo '' !== $store ? esc_html( ' ($' . $store . ')' ) : ''; ?>.</span>
                </div>
            </div>
            <div class="hprwc-field">
                <span class="hprwc-label">Credit card</span>
                <div class="hprwc-inline">
                    <?php echo \HexaPrWire\Core\Admin\CustomerProfile::toggle_html( 'hpr_billing_allow_credit_card', 1, $this->pricing->card_allowed( $user_id ), 'Allow credit card' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span class="hprwc-billing-help">On: card or ACH bank payment at checkout. Off: ACH bank payment only.</span>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_services( \WP_User $user ): void {
        if ( ! $this->can_manage() ) {
            return;
        }
        ?>
        <div class="hprwc-services" data-hprwc-repeater>
            <p class="hprwc-section-label">Custom services</p>
            <p class="hprwc-billing-help">Extra items only this customer can buy, each with its own price.</p>
            <ul class="hprwc-repeater-rows">
                <?php foreach ( $this->pricing->custom_services( (int) $user->ID ) as $service ) : ?>
                    <?php $this->service_row( $service['name'], $service['price'] ); ?>
                <?php endforeach; ?>
            </ul>
            <p class="hprwc-repeater-empty" hidden>No custom services.</p>
            <template><?php $this->service_row( '', '' ); ?></template>
            <button type="button" class="button" data-repeater-add>Add service</button>
        </div>
        <?php
    }

    private function service_row( string $name, string $price ): void {
        ?>
        <li class="hprwc-repeater-row">
            <input type="text" class="hprwc-service-name" name="hpr_billing_service_name[]" value="<?php echo esc_attr( $name ); ?>" placeholder="Service name" aria-label="Service name">
            <span class="hprwc-price"><span aria-hidden="true">$</span><input type="text" inputmode="decimal" pattern="[0-9]*[.]?[0-9]{0,2}" name="hpr_billing_service_price[]" value="<?php echo esc_attr( $price ); ?>" placeholder="0.00" aria-label="Service price"></span>
            <button type="button" class="button-link" data-repeater-remove>Remove</button>
        </li>
        <?php
    }

    public function save( int $user_id ): void {
        $nonce = isset( $_POST['hpr_billing_customer_nonce'] ) && is_scalar( $_POST['hpr_billing_customer_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hpr_billing_customer_nonce'] ) ) : '';
        if ( ! $this->can_manage() || ! current_user_can( 'edit_user', $user_id ) || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
            return;
        }
        $names  = isset( $_POST['hpr_billing_service_name'] ) && is_array( $_POST['hpr_billing_service_name'] ) ? array_values( wp_unslash( $_POST['hpr_billing_service_name'] ) ) : [];
        $prices = isset( $_POST['hpr_billing_service_price'] ) && is_array( $_POST['hpr_billing_service_price'] ) ? array_values( wp_unslash( $_POST['hpr_billing_service_price'] ) ) : [];
        $services = [];
        foreach ( $names as $index => $name ) {
            $services[] = [ 'name' => is_scalar( $name ) ? (string) $name : '', 'price' => is_scalar( $prices[ $index ] ?? '' ) ? (string) ( $prices[ $index ] ?? '' ) : '' ];
        }
        $standard = isset( $_POST['hpr_billing_standard_price'] ) && is_scalar( $_POST['hpr_billing_standard_price'] ) ? (string) wp_unslash( $_POST['hpr_billing_standard_price'] ) : '';
        $this->pricing->save_settings( $user_id, $standard, ! empty( $_POST['hpr_billing_allow_credit_card'] ), $services );
    }

    private function can_manage(): bool {
        return current_user_can( 'edit_users' ) && current_user_can( 'manage_options' );
    }
}
