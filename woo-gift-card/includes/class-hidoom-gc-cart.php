<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Carries gift card form values from the product page into the cart, then into the order item.
 */
class Hidoom_GC_Cart {

    public static function init() {
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_to_order_item' ), 10, 4 );
    }

    public static function add_cart_item_data( $cart_item_data, $product_id ) {
        if ( ! Hidoom_GC_Form::is_gift_card_product( $product_id ) ) {
            return $cart_item_data;
        }
        if ( ! empty( $_POST['hidoom_gc_receiver_name'] ) ) {
            $cart_item_data['hidoom_gc'] = array(
                'receiver_name'  => sanitize_text_field( wp_unslash( $_POST['hidoom_gc_receiver_name'] ) ),
                'receiver_email' => sanitize_email( wp_unslash( $_POST['hidoom_gc_receiver_email'] ) ),
                'sender_name'    => sanitize_text_field( wp_unslash( $_POST['hidoom_gc_sender_name'] ) ),
                'message'        => isset( $_POST['hidoom_gc_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hidoom_gc_message'] ) ) : '',
                'unique_key'     => wp_generate_uuid4(),
            );
        }
        return $cart_item_data;
    }

    public static function display_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['hidoom_gc'] ) ) {
            return $item_data;
        }
        $gc = $cart_item['hidoom_gc'];
        $item_data[] = array(
            'key'     => __( 'To', 'hidoom-gift-card' ),
            'value'   => $gc['receiver_name'] . ' <' . $gc['receiver_email'] . '>',
            'display' => '',
        );
        $item_data[] = array(
            'key'     => __( 'From', 'hidoom-gift-card' ),
            'value'   => $gc['sender_name'],
            'display' => '',
        );
        return $item_data;
    }

    public static function save_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['hidoom_gc'] ) ) {
            return;
        }
        $gc = $values['hidoom_gc'];
        $item->add_meta_data( '_hidoom_gc_receiver_name', $gc['receiver_name'], true );
        $item->add_meta_data( '_hidoom_gc_receiver_email', $gc['receiver_email'], true );
        $item->add_meta_data( '_hidoom_gc_sender_name', $gc['sender_name'], true );
        $item->add_meta_data( '_hidoom_gc_message', $gc['message'], true );
        $item->add_meta_data( __( 'Gift To', 'hidoom-gift-card' ), $gc['receiver_name'] );
        $item->add_meta_data( __( 'Gift From', 'hidoom-gift-card' ), $gc['sender_name'] );
    }
}
