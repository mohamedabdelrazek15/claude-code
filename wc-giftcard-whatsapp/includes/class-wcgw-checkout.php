<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCGW_Checkout {

	public static function init() {
		add_filter( 'woocommerce_available_payment_gateways', [ __CLASS__, 'filter_gateways' ] );
		add_action( 'woocommerce_review_order_before_payment', [ __CLASS__, 'show_notice' ] );
		add_action( 'woocommerce_checkout_process', [ __CLASS__, 'guard_cod_submit' ] );
	}

	public static function cart_has_gift_card() {
		if ( ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['product_id'] ) && WCGW_Product::is_gift_card( $cart_item['product_id'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function filter_gateways( $gateways ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return $gateways;
		}
		if ( ! self::cart_has_gift_card() ) {
			return $gateways;
		}

		foreach ( array_keys( $gateways ) as $id ) {
			if ( 'cod' === $id || false !== stripos( $id, 'cod' ) || false !== stripos( $id, 'cash_on_delivery' ) ) {
				unset( $gateways[ $id ] );
			}
		}

		return $gateways;
	}

	public static function show_notice() {
		if ( ! self::cart_has_gift_card() ) {
			return;
		}
		echo '<p class="wcgw-cod-notice" style="padding:0.75em;background:#fff8e5;border-left:3px solid #f0b849;margin:0 0 1em;">'
			. esc_html__( 'Gift card purchases must be paid online. Cash on delivery is not available.', 'wcgw' )
			. '</p>';
	}

	public static function guard_cod_submit() {
		if ( ! self::cart_has_gift_card() ) {
			return;
		}
		$chosen = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
		if ( 'cod' === $chosen || false !== stripos( $chosen, 'cod' ) ) {
			wc_add_notice( __( 'Cash on delivery is not allowed when purchasing a gift card. Please choose an online payment method.', 'wcgw' ), 'error' );
		}
	}
}
