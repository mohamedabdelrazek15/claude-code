<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates one-time use WooCommerce coupons for gift card purchases.
 */
class Hidoom_GC_Coupon {

    /**
     * Build a coupon code based on the receiver's name.
     *
     * Coupon "name" per the brief = receiver's name, made unique by suffix.
     */
    public static function build_code( $receiver_name ) {
        $prefix = Hidoom_GC_Settings::get( 'coupon_prefix' );
        $prefix = $prefix ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $prefix ) ) : 'GIFT';

        $slug = sanitize_title( $receiver_name );
        $slug = preg_replace( '/[^A-Za-z0-9\-]/', '', $slug );
        if ( empty( $slug ) ) {
            $slug = 'friend';
        }
        $slug = strtoupper( $slug );

        $suffix = strtoupper( wp_generate_password( 5, false, false ) );
        $code   = $prefix . '-' . $slug . '-' . $suffix;

        while ( wc_get_coupon_id_by_code( $code ) ) {
            $code = $prefix . '-' . $slug . '-' . strtoupper( wp_generate_password( 5, false, false ) );
        }
        return $code;
    }

    /**
     * Create a one-time purchase coupon.
     *
     * @param string $receiver_name
     * @param float  $amount
     * @param int    $order_id
     * @return string|WP_Error coupon code on success
     */
    public static function create( $receiver_name, $amount, $order_id = 0 ) {
        $code = self::build_code( $receiver_name );

        $coupon = new WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( 'fixed_cart' );
        $coupon->set_amount( (float) $amount );
        $coupon->set_individual_use( true );
        $coupon->set_usage_limit( 1 );
        $coupon->set_usage_limit_per_user( 1 );
        $coupon->set_description(
            sprintf(
                /* translators: 1: receiver name, 2: order ID */
                __( 'Gift card for %1$s (order #%2$s).', 'hidoom-gift-card' ),
                $receiver_name,
                $order_id
            )
        );

        $days = absint( Hidoom_GC_Settings::get( 'coupon_expiry_days' ) );
        if ( $days > 0 ) {
            $coupon->set_date_expires( time() + ( $days * DAY_IN_SECONDS ) );
        }

        $coupon_id = $coupon->save();
        if ( ! $coupon_id ) {
            return new WP_Error( 'hidoom_gc_coupon_failed', __( 'Could not create gift card coupon.', 'hidoom-gift-card' ) );
        }

        update_post_meta( $coupon_id, '_hidoom_gc_order_id', $order_id );
        update_post_meta( $coupon_id, '_hidoom_gc_receiver_name', $receiver_name );

        return $code;
    }
}
