<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCGW_Order {

	const MAX_ATTEMPTS = 3;

	public static function init() {
		add_action( 'woocommerce_payment_complete', [ __CLASS__, 'process_order' ], 20 );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'process_order' ], 20 );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'process_order' ], 20 );

		add_filter( 'woocommerce_order_actions', [ __CLASS__, 'register_order_action' ] );
		add_action( 'woocommerce_order_action_wcgw_resend', [ __CLASS__, 'manual_resend' ] );

		add_action( 'wcgw_retry_send', [ __CLASS__, 'retry_send' ], 10, 2 );
	}

	public static function process_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( 'cod' === $order->get_payment_method() ) {
			return;
		}
		if ( ! $order->is_paid() && ! in_array( $order->get_status(), [ 'processing', 'completed' ], true ) ) {
			return;
		}
		if ( 'yes' === $order->get_meta( '_wcgw_processed' ) ) {
			return;
		}

		$processed_any = false;

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();
			if ( ! WCGW_Product::is_gift_card( $product_id ) ) {
				continue;
			}
			if ( $item->get_meta( '_wcgw_coupon_code' ) ) {
				continue;
			}

			$coupon = WCGW_Coupon::create_for( $item, $order );
			$item->add_meta_data( '_wcgw_coupon_code', $coupon->get_code(), true );
			$item->save();

			self::send_for_item( $order, $item, $coupon->get_code(), $coupon->get_amount() );
			$processed_any = true;
		}

		if ( $processed_any ) {
			$order->update_meta_data( '_wcgw_processed', 'yes' );
			$order->save();
		}
	}

	private static function send_for_item( $order, $item, $coupon_code, $amount ) {
		$recipient_name = (string) $item->get_meta( '_wcgw_recipient_name' );
		$recipient_phone = (string) $item->get_meta( '_wcgw_recipient_phone' );
		$sender_name    = (string) $item->get_meta( '_wcgw_sender_name' );
		$personal_msg   = (string) $item->get_meta( '_wcgw_personal_message' );

		if ( '' === $sender_name ) {
			$sender_name = get_option( 'wcgw_default_sender_name', get_bloginfo( 'name' ) );
		}

		$template = get_option( 'wcgw_template_name', 'gift_card_notification' );
		$lang     = get_option( 'wcgw_template_language', 'en_US' );

		$variables = [
			$recipient_name,
			html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ),
			$sender_name,
			$coupon_code,
			'' !== $personal_msg ? $personal_msg : ' ',
		];

		try {
			WCGW_WhatsApp::send_template( $recipient_phone, $template, $lang, $variables );
			$order->add_order_note( sprintf(
				__( 'WhatsApp gift card %1$s sent to %2$s (+%3$s).', 'wcgw' ),
				$coupon_code,
				$recipient_name,
				$recipient_phone
			) );
		} catch ( Exception $e ) {
			$order->add_order_note( sprintf(
				__( 'Failed to send WhatsApp gift card %1$s: %2$s. Will retry.', 'wcgw' ),
				$coupon_code,
				$e->getMessage()
			) );
			self::schedule_retry( $order->get_id(), $item->get_id(), 1 );
		}
	}

	private static function schedule_retry( $order_id, $item_id, $attempt ) {
		if ( $attempt > self::MAX_ATTEMPTS ) {
			return;
		}
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + ( 300 * $attempt ), 'wcgw_retry_send', [ $order_id, $item_id, $attempt ], 'wcgw' );
		}
	}

	public static function retry_send( $order_id, $item_id, $attempt = 1 ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$item = $order->get_item( $item_id );
		if ( ! $item ) {
			return;
		}

		$coupon_code = (string) $item->get_meta( '_wcgw_coupon_code' );
		if ( '' === $coupon_code ) {
			return;
		}

		$coupon_id = wc_get_coupon_id_by_code( $coupon_code );
		$amount    = $coupon_id ? (float) get_post_meta( $coupon_id, '_wcgw_initial_balance', true ) : 0;

		try {
			$recipient_phone = (string) $item->get_meta( '_wcgw_recipient_phone' );
			$recipient_name  = (string) $item->get_meta( '_wcgw_recipient_name' );
			$sender_name     = (string) $item->get_meta( '_wcgw_sender_name' );
			$personal_msg    = (string) $item->get_meta( '_wcgw_personal_message' );
			$template        = get_option( 'wcgw_template_name', 'gift_card_notification' );
			$lang            = get_option( 'wcgw_template_language', 'en_US' );

			WCGW_WhatsApp::send_template(
				$recipient_phone,
				$template,
				$lang,
				[
					$recipient_name,
					html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ),
					$sender_name,
					$coupon_code,
					'' !== $personal_msg ? $personal_msg : ' ',
				]
			);
			$order->add_order_note( sprintf(
				__( 'WhatsApp gift card %s re-sent successfully (attempt %d).', 'wcgw' ),
				$coupon_code,
				$attempt + 1
			) );
		} catch ( Exception $e ) {
			$order->add_order_note( sprintf(
				__( 'Retry %d failed for gift card %s: %s', 'wcgw' ),
				$attempt,
				$coupon_code,
				$e->getMessage()
			) );
			self::schedule_retry( $order_id, $item_id, (int) $attempt + 1 );
		}
	}

	public static function register_order_action( $actions ) {
		$actions['wcgw_resend'] = __( 'Resend WhatsApp gift card', 'wcgw' );
		return $actions;
	}

	public static function manual_resend( $order ) {
		$order->update_meta_data( '_wcgw_processed', 'no' );
		$order->save();
		self::process_order( $order->get_id() );
	}
}
