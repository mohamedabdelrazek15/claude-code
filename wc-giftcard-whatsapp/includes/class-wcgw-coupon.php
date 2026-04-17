<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCGW_Coupon {

	public static function init() {
		add_filter( 'woocommerce_coupon_get_discount_amount', [ __CLASS__, 'cap_discount_at_balance' ], 10, 5 );

		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'decrement_balance_for_order' ], 20 );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'decrement_balance_for_order' ], 20 );

		add_action( 'woocommerce_order_status_cancelled', [ __CLASS__, 'restore_balance_for_order' ], 20 );
		add_action( 'woocommerce_order_status_refunded', [ __CLASS__, 'restore_balance_for_order' ], 20 );

		add_shortcode( 'wcgw_balance', [ __CLASS__, 'balance_shortcode' ] );
	}

	public static function create_for( $order_item, $order ) {
		$order_id = $order->get_id();
		$amount   = (float) $order_item->get_total() + (float) $order_item->get_total_tax();

		$code = self::generate_unique_code( $order_id );

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( $amount );
		$coupon->set_individual_use( false );
		$coupon->set_usage_limit( 0 );
		$coupon->set_usage_limit_per_user( 0 );
		$coupon->set_description( sprintf( 'Gift card from order #%d', $order_id ) );
		$coupon->save();

		$coupon_id = $coupon->get_id();
		update_post_meta( $coupon_id, '_wcgw_gift_card', 'yes' );
		update_post_meta( $coupon_id, '_wcgw_initial_balance', $amount );
		update_post_meta( $coupon_id, '_wcgw_remaining_balance', $amount );
		update_post_meta( $coupon_id, '_wcgw_order_id', $order_id );
		update_post_meta( $coupon_id, '_wcgw_recipient_name', (string) $order_item->get_meta( '_wcgw_recipient_name' ) );
		update_post_meta( $coupon_id, '_wcgw_recipient_phone', (string) $order_item->get_meta( '_wcgw_recipient_phone' ) );

		return $coupon;
	}

	private static function generate_unique_code( $order_id ) {
		$attempts = 0;
		do {
			$suffix = strtoupper( wp_generate_password( 4, false, false ) );
			$code   = sprintf( 'GC-%d-%s', $order_id, $suffix );
			$attempts++;
		} while ( wc_get_coupon_id_by_code( $code ) && $attempts < 20 );

		return $code;
	}

	public static function cap_discount_at_balance( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
		if ( ! $coupon instanceof WC_Coupon ) {
			return $discount;
		}
		$coupon_id = $coupon->get_id();
		if ( ! $coupon_id || 'yes' !== get_post_meta( $coupon_id, '_wcgw_gift_card', true ) ) {
			return $discount;
		}

		$balance = (float) get_post_meta( $coupon_id, '_wcgw_remaining_balance', true );
		if ( $balance <= 0 ) {
			return 0;
		}

		return min( (float) $discount, $balance );
	}

	public static function decrement_balance_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( 'yes' === $order->get_meta( '_wcgw_balance_decremented' ) ) {
			return;
		}

		foreach ( $order->get_coupons() as $coupon_item ) {
			$code      = $coupon_item->get_code();
			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( ! $coupon_id || 'yes' !== get_post_meta( $coupon_id, '_wcgw_gift_card', true ) ) {
				continue;
			}

			$used     = (float) $coupon_item->get_discount() + (float) $coupon_item->get_discount_tax();
			$balance  = (float) get_post_meta( $coupon_id, '_wcgw_remaining_balance', true );
			$new_bal  = max( 0, $balance - $used );
			update_post_meta( $coupon_id, '_wcgw_remaining_balance', $new_bal );

			if ( $new_bal <= 0 ) {
				$coupon = new WC_Coupon( $coupon_id );
				$coupon->set_date_expires( time() - 60 );
				$coupon->save();
			}

			$order->add_order_note( sprintf(
				/* translators: 1: coupon code, 2: used amount, 3: new balance */
				__( 'Gift card %1$s used for %2$s. Remaining balance: %3$s.', 'wcgw' ),
				$code,
				wc_price( $used ),
				wc_price( $new_bal )
			) );
		}

		$order->update_meta_data( '_wcgw_balance_decremented', 'yes' );
		$order->save();
	}

	public static function restore_balance_for_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( 'yes' !== $order->get_meta( '_wcgw_balance_decremented' ) ) {
			return;
		}
		if ( 'yes' === $order->get_meta( '_wcgw_balance_restored' ) ) {
			return;
		}

		foreach ( $order->get_coupons() as $coupon_item ) {
			$code      = $coupon_item->get_code();
			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( ! $coupon_id || 'yes' !== get_post_meta( $coupon_id, '_wcgw_gift_card', true ) ) {
				continue;
			}

			$used    = (float) $coupon_item->get_discount() + (float) $coupon_item->get_discount_tax();
			$balance = (float) get_post_meta( $coupon_id, '_wcgw_remaining_balance', true );
			$initial = (float) get_post_meta( $coupon_id, '_wcgw_initial_balance', true );
			$new_bal = min( $initial, $balance + $used );
			update_post_meta( $coupon_id, '_wcgw_remaining_balance', $new_bal );

			$coupon = new WC_Coupon( $coupon_id );
			$coupon->set_date_expires( null );
			$coupon->save();

			$order->add_order_note( sprintf(
				__( 'Gift card %1$s balance restored by %2$s after cancellation/refund. Balance: %3$s.', 'wcgw' ),
				$code,
				wc_price( $used ),
				wc_price( $new_bal )
			) );
		}

		$order->update_meta_data( '_wcgw_balance_restored', 'yes' );
		$order->save();
	}

	public static function balance_shortcode( $atts ) {
		$atts = shortcode_atts( [ 'code' => '' ], $atts );
		if ( '' === $atts['code'] ) {
			return '';
		}
		$coupon_id = wc_get_coupon_id_by_code( $atts['code'] );
		if ( ! $coupon_id || 'yes' !== get_post_meta( $coupon_id, '_wcgw_gift_card', true ) ) {
			return esc_html__( 'Unknown gift card code.', 'wcgw' );
		}
		$balance = (float) get_post_meta( $coupon_id, '_wcgw_remaining_balance', true );
		return sprintf( '<span class="wcgw-balance">%s</span>', wc_price( $balance ) );
	}
}
