<?php
/**
 * Plugin Name:       WC Gift Card
 * Description:       Adds a gift card form to products in the gift-card category. On order completion, generates a WooCommerce coupon and emails it to the recipient.
 * Version:           1.0.0
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-gift-card
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html__( 'WC Gift Card requires WooCommerce to be installed and active.', 'wc-gift-card' )
				. '</p></div>';
		} );
		return;
	}
	WC_Gift_Card_Plugin::get_instance();
}, 10 );

class WC_Gift_Card_Plugin {

	const GIFT_CARD_CATEGORY_SLUG = 'gift-card';

	private static ?WC_Gift_Card_Plugin $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'add_gift_card_form' ] );
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
		add_filter( 'woocommerce_get_item_data', [ $this, 'get_item_data' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'checkout_create_order_line_item' ], 10, 4 );
		add_action( 'woocommerce_order_status_completed', [ $this, 'order_completed' ] );
	}

	// -------------------------------------------------------------------------
	// Frontend: form on product page
	// -------------------------------------------------------------------------

	public function add_gift_card_form(): void {
		global $product;
		if ( ! $product instanceof WC_Product || ! $this->is_gift_card_product( $product->get_id() ) ) {
			return;
		}
		?>
		<div class="wc-gift-card-form" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Gift Card Details', 'wc-gift-card' ); ?></h3>
			<?php wp_nonce_field( 'wc_gift_card_add', 'wc_gift_card_nonce' ); ?>

			<p style="margin-bottom:12px;">
				<label for="gift_card_receiver_name" style="display:block; font-weight:600; margin-bottom:4px;">
					<?php esc_html_e( "Recipient's Name", 'wc-gift-card' ); ?> <span style="color:red;">*</span>
				</label>
				<input type="text" id="gift_card_receiver_name" name="gift_card_receiver_name"
					style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"
					placeholder="<?php esc_attr_e( 'Enter recipient name', 'wc-gift-card' ); ?>" required />
			</p>

			<p style="margin-bottom:12px;">
				<label for="gift_card_receiver_email" style="display:block; font-weight:600; margin-bottom:4px;">
					<?php esc_html_e( "Recipient's Email", 'wc-gift-card' ); ?> <span style="color:red;">*</span>
				</label>
				<input type="email" id="gift_card_receiver_email" name="gift_card_receiver_email"
					style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"
					placeholder="<?php esc_attr_e( 'recipient@example.com', 'wc-gift-card' ); ?>" required />
			</p>

			<p style="margin-bottom:0;">
				<label for="gift_card_sender_name" style="display:block; font-weight:600; margin-bottom:4px;">
					<?php esc_html_e( 'Your Name (Sender)', 'wc-gift-card' ); ?> <span style="color:red;">*</span>
				</label>
				<input type="text" id="gift_card_sender_name" name="gift_card_sender_name"
					style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"
					placeholder="<?php esc_attr_e( 'Enter your name', 'wc-gift-card' ); ?>" required />
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Cart: attach gift card data to cart item
	// -------------------------------------------------------------------------

	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		if ( ! isset( $_POST['wc_gift_card_nonce'] ) ) {
			return $cart_item_data;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_gift_card_nonce'] ) ), 'wc_gift_card_add' ) ) {
			return $cart_item_data;
		}
		if ( ! $this->is_gift_card_product( $product_id ) ) {
			return $cart_item_data;
		}

		$receiver_name  = sanitize_text_field( wp_unslash( $_POST['gift_card_receiver_name'] ?? '' ) );
		$receiver_email = sanitize_email( wp_unslash( $_POST['gift_card_receiver_email'] ?? '' ) );
		$sender_name    = sanitize_text_field( wp_unslash( $_POST['gift_card_sender_name'] ?? '' ) );

		if ( empty( $receiver_name ) || empty( $receiver_email ) || empty( $sender_name ) ) {
			wc_add_notice( __( 'Please fill in all gift card recipient details.', 'wc-gift-card' ), 'error' );
			return $cart_item_data;
		}

		if ( ! is_email( $receiver_email ) ) {
			wc_add_notice( __( "Please enter a valid recipient email address.", 'wc-gift-card' ), 'error' );
			return $cart_item_data;
		}

		$cart_item_data['wc_gift_card'] = [
			'receiver_name'  => $receiver_name,
			'receiver_email' => $receiver_email,
			'sender_name'    => $sender_name,
		];

		return $cart_item_data;
	}

	// -------------------------------------------------------------------------
	// Cart / Checkout: display gift card details in cart
	// -------------------------------------------------------------------------

	public function get_item_data( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['wc_gift_card'] ) ) {
			return $item_data;
		}
		$gc = $cart_item['wc_gift_card'];

		$item_data[] = [
			'key'   => __( 'To', 'wc-gift-card' ),
			'value' => esc_html( $gc['receiver_name'] ),
		];
		$item_data[] = [
			'key'   => __( 'Recipient Email', 'wc-gift-card' ),
			'value' => esc_html( $gc['receiver_email'] ),
		];
		$item_data[] = [
			'key'   => __( 'From', 'wc-gift-card' ),
			'value' => esc_html( $gc['sender_name'] ),
		];

		return $item_data;
	}

	// -------------------------------------------------------------------------
	// Order: save gift card data to order line item meta
	// -------------------------------------------------------------------------

	public function checkout_create_order_line_item(
		\WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		\WC_Order $order
	): void {
		if ( empty( $values['wc_gift_card'] ) ) {
			return;
		}
		$gc = $values['wc_gift_card'];
		$item->add_meta_data( 'gift_card_receiver_name',  $gc['receiver_name'],  true );
		$item->add_meta_data( 'gift_card_receiver_email', $gc['receiver_email'], true );
		$item->add_meta_data( 'gift_card_sender_name',    $gc['sender_name'],    true );
	}

	// -------------------------------------------------------------------------
	// Order completed: create coupon and send email
	// -------------------------------------------------------------------------

	public function order_completed( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( ! $product || ! $this->is_gift_card_product( $product->get_id() ) ) {
				continue;
			}

			// Idempotency: skip if already processed
			if ( '1' === $item->get_meta( '_gift_card_coupon_sent' ) ) {
				continue;
			}

			$receiver_name  = $item->get_meta( 'gift_card_receiver_name' );
			$receiver_email = $item->get_meta( 'gift_card_receiver_email' );
			$sender_name    = $item->get_meta( 'gift_card_sender_name' );

			if ( empty( $receiver_name ) || empty( $receiver_email ) || empty( $sender_name ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: %d: order item ID */
						__( 'Gift card item #%d skipped: missing recipient details.', 'wc-gift-card' ),
						$item->get_id()
					)
				);
				continue;
			}

			if ( ! is_email( $receiver_email ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: email address */
						__( 'Gift card not sent: invalid recipient email "%s".', 'wc-gift-card' ),
						esc_html( $receiver_email )
					)
				);
				continue;
			}

			$coupon_amount = floatval( $product->get_price() );
			if ( $coupon_amount <= 0 ) {
				$order->add_order_note(
					__( 'Gift card coupon not created: product price is zero.', 'wc-gift-card' )
				);
				continue;
			}

			$coupon_code = $this->generate_coupon_code( $receiver_name );
			$coupon_id   = $this->create_coupon( $coupon_code, $coupon_amount );

			if ( ! $coupon_id ) {
				$order->add_order_note(
					sprintf(
						__( 'Gift card coupon creation failed for recipient "%s".', 'wc-gift-card' ),
						esc_html( $receiver_name )
					)
				);
				continue;
			}

			$email_sent = $this->send_gift_card_email(
				$receiver_email,
				$receiver_name,
				$sender_name,
				$coupon_code,
				$coupon_amount
			);

			if ( ! $email_sent ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: recipient name, 2: coupon code */
						__( 'Gift card email failed for "%1$s". Coupon "%2$s" was created — share it manually.', 'wc-gift-card' ),
						esc_html( $receiver_name ),
						esc_html( $coupon_code )
					)
				);
			}

			// Mark as processed (coupon code stored for reference)
			$item->update_meta_data( '_gift_card_coupon_sent', '1' );
			$item->update_meta_data( '_gift_card_coupon_code', $coupon_code );
			$item->save();

			$order->add_order_note(
				sprintf(
					/* translators: 1: coupon code, 2: recipient email */
					__( 'Gift card coupon "%1$s" created and emailed to %2$s.', 'wc-gift-card' ),
					esc_html( $coupon_code ),
					esc_html( $receiver_email )
				)
			);
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function is_gift_card_product( int $product_id ): bool {
		return has_term( self::GIFT_CARD_CATEGORY_SLUG, 'product_cat', $product_id );
	}

	private function generate_coupon_code( string $receiver_name ): string {
		$slug   = str_replace( '-', '_', sanitize_title( $receiver_name ) );
		$slug   = substr( $slug, 0, 20 );
		$suffix = substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 6 );
		$code   = $slug . '_' . $suffix;

		if ( $this->coupon_code_exists( $code ) ) {
			$code = $slug . '_' . $suffix . '_' . substr( (string) time(), -4 );
		}

		return strtolower( $code );
	}

	private function coupon_code_exists( string $code ): bool {
		return (bool) wc_get_coupon_id_by_code( $code );
	}

	private function create_coupon( string $code, float $amount ): int {
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( $amount );
		$coupon->set_usage_limit( 1 );
		$coupon->set_individual_use( true );
		$coupon->set_description( __( 'Auto-generated gift card coupon.', 'wc-gift-card' ) );
		return (int) $coupon->save();
	}

	private function send_gift_card_email(
		string $to,
		string $receiver_name,
		string $sender_name,
		string $coupon_code,
		float $coupon_amount
	): bool {
		$subject = __( "You've received a gift card!", 'wc-gift-card' );
		$body    = $this->build_email_html( $receiver_name, $sender_name, $coupon_code, $coupon_amount );
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'Content-Transfer-Encoding: 8bit',
		];
		return wp_mail( $to, $subject, $body, $headers );
	}

	private function build_email_html(
		string $receiver_name,
		string $sender_name,
		string $coupon_code,
		float $coupon_amount
	): string {
		$shop_name     = esc_html( get_bloginfo( 'name' ) );
		$shop_url      = esc_url( home_url() );
		$amount_fmt    = wc_price( $coupon_amount );
		$receiver_esc  = esc_html( $receiver_name );
		$sender_esc    = esc_html( $sender_name );
		$coupon_esc    = esc_html( $coupon_code );

		return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Gift Card</title>
  <style>
    body { margin:0; padding:0; background:#f4f4f4; font-family:Arial,Helvetica,sans-serif; }
    .wrap { max-width:600px; margin:40px auto; background:#ffffff; border-radius:8px; overflow:hidden; }
    .header { background:#2d6a4f; padding:32px 40px; text-align:center; }
    .header h1 { margin:0; color:#ffffff; font-size:26px; }
    .body { padding:36px 40px; color:#333333; font-size:16px; line-height:1.6; }
    .coupon-box { background:#d8f3dc; border:2px dashed #2d6a4f; border-radius:8px;
                  text-align:center; padding:24px; margin:28px 0; }
    .coupon-label { font-size:13px; color:#555; margin-bottom:8px; text-transform:uppercase; letter-spacing:1px; }
    .coupon-code { font-size:32px; font-weight:bold; color:#1b4332; letter-spacing:4px; word-break:break-all; }
    .one-time { font-size:13px; color:#888; margin-top:8px; }
    .arabic { font-size:20px; color:#2d6a4f; font-weight:bold; direction:rtl; text-align:right;
              margin-top:28px; padding-top:20px; border-top:1px solid #eee; }
    .footer { padding:20px 40px; background:#f9f9f9; text-align:center; font-size:12px; color:#999; }
    .footer a { color:#2d6a4f; text-decoration:none; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <h1>&#127873; You've Got a Gift Card!</h1>
    </div>
    <div class="body">
      <p>Congratulations <strong>{$receiver_esc}</strong>, you got gifted <strong>{$amount_fmt}</strong> from <strong>{$sender_esc}</strong>.</p>
      <p>Your coupon is <strong>one time purchase</strong> and it is:</p>
      <div class="coupon-box">
        <div class="coupon-label">Your Coupon Code</div>
        <div class="coupon-code">{$coupon_esc}</div>
        <div class="one-time">&#9888; One-time use only</div>
      </div>
      <p>Use this code at checkout on our store to apply your gift card balance.</p>
      <div class="arabic">هيدوم. هيدوم معاك</div>
    </div>
    <div class="footer">
      <p><a href="{$shop_url}">{$shop_name}</a></p>
    </div>
  </div>
</body>
</html>
HTML;
	}
}
