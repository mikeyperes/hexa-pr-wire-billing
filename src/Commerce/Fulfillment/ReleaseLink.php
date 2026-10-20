<?php

namespace HexaPrWire\Billing\Commerce\Fulfillment;

/**
 * Paying for a release that already exists. Checkout carries the release ID
 * (?release=), the order item keeps it, and fulfillment links the payment to that
 * release instead of creating a new blank draft.
 */
final class ReleaseLink {
    public const ITEM_META = '_hpr_billing_release_id';

    /** The customer a release belongs to: Core's owner rule when present. */
    public static function owner( \WP_Post $post ): int {
        return class_exists( '\\HexaPrWire\\Core\\Customer\\ReleaseOwner' )
            ? \HexaPrWire\Core\Customer\ReleaseOwner::id( $post )
            : (int) $post->post_author;
    }

    public static function is_paid( int $post_id ): bool {
        $order_id = absint( get_post_meta( $post_id, 'billing_invoice_id', true ) );
        $order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
        return $order instanceof \WC_Order && $order->is_paid();
    }

    public static function payable( int $post_id, int $user_id ): bool {
        $post = $post_id > 0 ? get_post( $post_id ) : null;
        return $post instanceof \WP_Post && 'post' === $post->post_type && 'trash' !== $post->post_status
            && $user_id > 0 && self::owner( $post ) === $user_id && ! self::is_paid( $post_id );
    }

    public static function requested(): int {
        return isset( $_REQUEST['release'] ) && is_scalar( $_REQUEST['release'] ) ? absint( wp_unslash( (string) $_REQUEST['release'] ) ) : 0;
    }

    public static function from_order( \WC_Order $order ): int {
        foreach ( $order->get_items() as $item ) {
            $release = absint( $item->get_meta( self::ITEM_META ) );
            if ( $release > 0 ) {
                return $release;
            }
        }
        return 0;
    }
}
