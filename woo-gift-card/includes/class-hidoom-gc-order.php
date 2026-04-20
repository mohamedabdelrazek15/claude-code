<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * On successful payment, generate a coupon per gift-card line item and email the receiver.
 */
class Hidoom_GC_Order {

    public static function init() {
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'process_order' ), 10, 1 );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'process_order' ), 10, 1 );
        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'process_order' ), 10, 1 );
    }

    public static function process_order( $order_id ) {
        if ( ! $order_id ) {
            return;
        }
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        if ( $order->get_meta( '_hidoom_gc_processed' ) === 'yes' ) {
            return;
        }

        $handled_any = false;

        foreach ( $order->get_items() as $item_id => $item ) {
            /** @var WC_Order_Item_Product $item */
            $product_id = $item->get_product_id();
            if ( ! Hidoom_GC_Form::is_gift_card_product( $product_id ) ) {
                continue;
            }

            if ( $item->get_meta( '_hidoom_gc_coupon_code' ) ) {
                continue;
            }

            $receiver_name  = $item->get_meta( '_hidoom_gc_receiver_name' );
            $receiver_email = $item->get_meta( '_hidoom_gc_receiver_email' );
            $sender_name    = $item->get_meta( '_hidoom_gc_sender_name' );
            $message        = $item->get_meta( '_hidoom_gc_message' );

            if ( empty( $receiver_email ) || ! is_email( $receiver_email ) ) {
                $order->add_order_note( sprintf( 'Gift card item #%d has no valid receiver email; skipped.', $item_id ) );
                continue;
            }

            $quantity = max( 1, (int) $item->get_quantity() );
            $amount   = $quantity > 0 ? ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $quantity : (float) $item->get_total();
            if ( $amount <= 0 ) {
                $product = $item->get_product();
                $amount  = $product ? (float) $product->get_price() : 0;
            }

            for ( $i = 0; $i < $quantity; $i++ ) {
                $code = Hidoom_GC_Coupon::create( $receiver_name, $amount, $order_id );
                if ( is_wp_error( $code ) ) {
                    $order->add_order_note( 'Gift card coupon creation failed: ' . $code->get_error_message() );
                    continue;
                }

                $existing = (array) $item->get_meta( '_hidoom_gc_coupon_code', true );
                if ( ! is_array( $existing ) ) {
                    $existing = $existing ? array( $existing ) : array();
                }
                $existing[] = $code;
                $item->update_meta_data( '_hidoom_gc_coupon_code', $existing );
                $item->save();

                $send = self::send_email(
                    $receiver_name,
                    $receiver_email,
                    $sender_name,
                    $amount,
                    $code,
                    $message,
                    $order
                );

                if ( is_wp_error( $send ) ) {
                    $order->add_order_note( 'Gift card email failed for ' . $receiver_email . ': ' . $send->get_error_message() );
                } else {
                    $order->add_order_note( sprintf( 'Gift card coupon %s emailed to %s.', $code, $receiver_email ) );
                }

                $handled_any = true;
            }
        }

        if ( $handled_any ) {
            $order->update_meta_data( '_hidoom_gc_processed', 'yes' );
            $order->save();
        }
    }

    /**
     * Build and send the gift card email.
     */
    protected static function send_email( $receiver_name, $receiver_email, $sender_name, $amount, $code, $personal_message, $order ) {
        $amount_display = wc_price( $amount, array( 'currency' => $order ? $order->get_currency() : '' ) );
        $amount_plain   = html_entity_decode( wp_strip_all_tags( $amount_display ), ENT_QUOTES, 'UTF-8' );

        $subject = Hidoom_GC_Settings::get( 'email_subject' );
        if ( empty( $subject ) ) {
            $subject = __( 'You received a gift card!', 'hidoom-gift-card' );
        }

        $headline = sprintf(
            /* translators: 1: receiver name, 2: amount, 3: sender name */
            __( 'Congratulations %1$s, you got gifted %2$s from %3$s. The coupon is one-time purchase and it is %4$s.', 'hidoom-gift-card' ),
            $receiver_name,
            $amount_plain,
            $sender_name,
            $code
        );

        $signature_html = esc_html( HIDOOM_GC_SIGNATURE );

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color:#222; background:#f7f7f7; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:8px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <h2 style="margin-top:0; color:#b8860b;">🎁 <?php echo esc_html__( 'A Gift Just For You', 'hidoom-gift-card' ); ?></h2>
        <p style="font-size:16px; line-height:1.6;">
            <?php
            printf(
                /* translators: 1: receiver name, 2: amount, 3: sender name */
                esc_html__( 'Congratulations %1$s you got gifted by %2$s from %3$s and the coupon is one-time purchase and it is %4$s', 'hidoom-gift-card' ),
                '<strong>' . esc_html( $receiver_name ) . '</strong>',
                '<strong>' . esc_html( $amount_plain ) . '</strong>',
                '<strong>' . esc_html( $sender_name ) . '</strong>',
                '<strong style="background:#fff3cd; padding:2px 6px; border-radius:4px;">' . esc_html( $code ) . '</strong>'
            );
            ?>
        </p>
        <?php if ( ! empty( $personal_message ) ) : ?>
            <blockquote style="border-left:4px solid #b8860b; margin:20px 0; padding:10px 16px; background:#faf6ea; color:#555;">
                <?php echo nl2br( esc_html( $personal_message ) ); ?>
            </blockquote>
        <?php endif; ?>
        <div style="margin:24px 0; padding:16px; background:#fafafa; border:1px dashed #b8860b; text-align:center; border-radius:6px;">
            <div style="font-size:12px; color:#888; letter-spacing:1px;"><?php esc_html_e( 'YOUR COUPON CODE', 'hidoom-gift-card' ); ?></div>
            <div style="font-size:22px; font-weight:bold; color:#b8860b; margin-top:6px;"><?php echo esc_html( $code ); ?></div>
            <div style="font-size:13px; color:#555; margin-top:6px;">
                <?php
                printf(
                    /* translators: %s: amount */
                    esc_html__( 'Value: %s • One-time use', 'hidoom-gift-card' ),
                    esc_html( $amount_plain )
                );
                ?>
            </div>
        </div>
        <hr style="border:none; border-top:1px solid #eee; margin:24px 0;">
        <p style="font-size:14px; color:#666; text-align:center; margin:0;" dir="rtl">
            <strong><?php echo $signature_html; ?></strong>
        </p>
    </div>
</body>
</html>
        <?php
        $html = ob_get_clean();

        if ( Hidoom_GC_Gmail::is_configured() ) {
            return Hidoom_GC_Gmail::send( $receiver_email, $receiver_name, $subject, $html );
        }

        // Fallback: send via wp_mail so coupons are still delivered if Gmail is not configured.
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $from    = Hidoom_GC_Settings::get( 'gmail_from_email' );
        if ( $from ) {
            $name     = Hidoom_GC_Settings::get( 'gmail_from_name' );
            $headers[] = $name ? sprintf( 'From: %s <%s>', $name, $from ) : 'From: ' . $from;
        }
        $sent = wp_mail( $receiver_email, $subject, $html, $headers );
        if ( ! $sent ) {
            return new WP_Error( 'hidoom_gc_wpmail_failed', 'wp_mail() returned false; Gmail API not configured.' );
        }
        return true;
    }
}
