<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the gift card form on product pages that belong to the Gift Card category.
 */
class Hidoom_GC_Form {

    public static function init() {
        add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_fields' ) );
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_fields' ), 10, 3 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function is_gift_card_product( $product_id ) {
        if ( ! $product_id ) {
            return false;
        }
        return has_term( HIDOOM_GC_CATEGORY_SLUG, 'product_cat', $product_id );
    }

    public static function enqueue_assets() {
        if ( ! is_product() ) {
            return;
        }
        global $post;
        if ( ! $post || ! self::is_gift_card_product( $post->ID ) ) {
            return;
        }
        wp_enqueue_style(
            'hidoom-gc',
            HIDOOM_GC_URL . 'assets/css/gift-card.css',
            array(),
            HIDOOM_GC_VERSION
        );
    }

    public static function render_fields() {
        global $product;
        if ( ! $product || ! self::is_gift_card_product( $product->get_id() ) ) {
            return;
        }
        ?>
        <div class="hidoom-gc-form">
            <h3><?php esc_html_e( 'Gift Card Details', 'hidoom-gift-card' ); ?></h3>
            <p class="hidoom-gc-field">
                <label for="hidoom_gc_receiver_name"><?php esc_html_e( "Receiver's Name", 'hidoom-gift-card' ); ?> <span class="required">*</span></label>
                <input type="text" id="hidoom_gc_receiver_name" name="hidoom_gc_receiver_name" required />
            </p>
            <p class="hidoom-gc-field">
                <label for="hidoom_gc_receiver_email"><?php esc_html_e( "Receiver's Email", 'hidoom-gift-card' ); ?> <span class="required">*</span></label>
                <input type="email" id="hidoom_gc_receiver_email" name="hidoom_gc_receiver_email" required />
            </p>
            <p class="hidoom-gc-field">
                <label for="hidoom_gc_sender_name"><?php esc_html_e( "Sender's Name", 'hidoom-gift-card' ); ?> <span class="required">*</span></label>
                <input type="text" id="hidoom_gc_sender_name" name="hidoom_gc_sender_name" required />
            </p>
            <p class="hidoom-gc-field">
                <label for="hidoom_gc_message"><?php esc_html_e( 'Personal Message (optional)', 'hidoom-gift-card' ); ?></label>
                <textarea id="hidoom_gc_message" name="hidoom_gc_message" rows="3"></textarea>
            </p>
        </div>
        <?php
    }

    public static function validate_fields( $passed, $product_id, $quantity ) {
        if ( ! self::is_gift_card_product( $product_id ) ) {
            return $passed;
        }

        $receiver_name  = isset( $_POST['hidoom_gc_receiver_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hidoom_gc_receiver_name'] ) ) : '';
        $receiver_email = isset( $_POST['hidoom_gc_receiver_email'] ) ? sanitize_email( wp_unslash( $_POST['hidoom_gc_receiver_email'] ) ) : '';
        $sender_name    = isset( $_POST['hidoom_gc_sender_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hidoom_gc_sender_name'] ) ) : '';

        if ( empty( $receiver_name ) ) {
            wc_add_notice( __( "Please enter the receiver's name.", 'hidoom-gift-card' ), 'error' );
            $passed = false;
        }
        if ( empty( $receiver_email ) || ! is_email( $receiver_email ) ) {
            wc_add_notice( __( "Please enter a valid receiver email address.", 'hidoom-gift-card' ), 'error' );
            $passed = false;
        }
        if ( empty( $sender_name ) ) {
            wc_add_notice( __( "Please enter the sender's name.", 'hidoom-gift-card' ), 'error' );
            $passed = false;
        }
        return $passed;
    }
}
