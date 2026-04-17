<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCGW_Cart {

	public static function init() {
		add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'attach_cart_item_data' ], 10, 3 );
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_cart_item_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'save_order_line_meta' ], 10, 4 );
	}

	public static function attach_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! WCGW_Product::is_gift_card( $product_id ) ) {
			return $cart_item_data;
		}

		$name    = isset( $_POST['wcgw_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgw_recipient_name'] ) ) : '';
		$phone   = isset( $_POST['wcgw_recipient_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgw_recipient_phone'] ) ) : '';
		$sender  = isset( $_POST['wcgw_sender_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgw_sender_name'] ) ) : '';
		$message = isset( $_POST['wcgw_personal_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wcgw_personal_message'] ) ) : '';

		if ( '' === $name || '' === $phone ) {
			return $cart_item_data;
		}

		$cart_item_data['wcgw_gift'] = [
			'recipient_name'   => $name,
			'recipient_phone'  => WCGW_Product::normalise_phone( $phone ),
			'sender_name'      => $sender,
			'personal_message' => $message,
			'unique_key'       => md5( $name . $phone . microtime() ),
		];

		return $cart_item_data;
	}

	public static function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['wcgw_gift'] ) ) {
			return $item_data;
		}

		$gift = $cart_item['wcgw_gift'];
		$item_data[] = [
			'key'     => __( 'Gift for', 'wcgw' ),
			'value'   => sprintf( '%s (+%s)', esc_html( $gift['recipient_name'] ), esc_html( $gift['recipient_phone'] ) ),
			'display' => '',
		];

		if ( ! empty( $gift['personal_message'] ) ) {
			$item_data[] = [
				'key'     => __( 'Message', 'wcgw' ),
				'value'   => esc_html( $gift['personal_message'] ),
				'display' => '',
			];
		}

		return $item_data;
	}

	public static function save_order_line_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['wcgw_gift'] ) ) {
			return;
		}

		$gift = $values['wcgw_gift'];
		$item->add_meta_data( '_wcgw_recipient_name', $gift['recipient_name'], true );
		$item->add_meta_data( '_wcgw_recipient_phone', $gift['recipient_phone'], true );
		$item->add_meta_data( '_wcgw_sender_name', $gift['sender_name'], true );
		$item->add_meta_data( '_wcgw_personal_message', $gift['personal_message'], true );
	}
}
