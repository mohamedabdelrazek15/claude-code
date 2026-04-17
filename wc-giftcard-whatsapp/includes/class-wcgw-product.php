<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCGW_Product {

	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'render_admin_field' ] );
		add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'save_admin_field' ] );

		add_action( 'woocommerce_before_add_to_cart_button', [ __CLASS__, 'render_recipient_fields' ] );
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'validate_recipient_fields' ], 10, 3 );

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function is_gift_card( $product_id ) {
		return 'yes' === get_post_meta( (int) $product_id, '_wcgw_is_gift_card', true );
	}

	public static function render_admin_field() {
		woocommerce_wp_checkbox( [
			'id'          => '_wcgw_is_gift_card',
			'label'       => __( 'This is a gift card', 'wcgw' ),
			'description' => __( 'When checked, buyers are asked for a recipient name + WhatsApp number, and a balance-tracking coupon is sent to the recipient after payment.', 'wcgw' ),
			'desc_tip'    => true,
		] );
	}

	public static function save_admin_field( $post_id ) {
		$value = isset( $_POST['_wcgw_is_gift_card'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wcgw_is_gift_card', $value );
	}

	public static function render_recipient_fields() {
		global $product;
		if ( ! $product || ! self::is_gift_card( $product->get_id() ) ) {
			return;
		}

		$current_user   = wp_get_current_user();
		$default_sender = $current_user && $current_user->exists() ? $current_user->display_name : '';
		?>
		<div class="wcgw-recipient-fields" style="margin:1em 0;padding:1em;border:1px solid #e2e2e2;border-radius:4px;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Recipient details', 'wcgw' ); ?></h3>
			<p class="form-row form-row-wide">
				<label for="wcgw_recipient_name"><?php esc_html_e( 'Recipient name', 'wcgw' ); ?> <span class="required">*</span></label>
				<input type="text" id="wcgw_recipient_name" name="wcgw_recipient_name" required maxlength="60" />
			</p>
			<p class="form-row form-row-wide">
				<label for="wcgw_recipient_phone"><?php esc_html_e( 'Recipient WhatsApp number', 'wcgw' ); ?> <span class="required">*</span></label>
				<input type="tel" id="wcgw_recipient_phone" name="wcgw_recipient_phone" required placeholder="+20 1X XXX XXXX" />
				<small><?php esc_html_e( 'Include country code, e.g. +201234567890', 'wcgw' ); ?></small>
			</p>
			<p class="form-row form-row-wide">
				<label for="wcgw_sender_name"><?php esc_html_e( 'Your name (sender)', 'wcgw' ); ?> <span class="required">*</span></label>
				<input type="text" id="wcgw_sender_name" name="wcgw_sender_name" required maxlength="60" value="<?php echo esc_attr( $default_sender ); ?>" />
			</p>
			<p class="form-row form-row-wide">
				<label for="wcgw_personal_message"><?php esc_html_e( 'Personal message (optional)', 'wcgw' ); ?></label>
				<textarea id="wcgw_personal_message" name="wcgw_personal_message" maxlength="200" rows="2"></textarea>
			</p>
		</div>
		<?php
	}

	public static function validate_recipient_fields( $passed, $product_id, $quantity ) {
		if ( ! self::is_gift_card( $product_id ) ) {
			return $passed;
		}

		$name  = isset( $_POST['wcgw_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgw_recipient_name'] ) ) : '';
		$phone = isset( $_POST['wcgw_recipient_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgw_recipient_phone'] ) ) : '';
		$sender = isset( $_POST['wcgw_sender_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgw_sender_name'] ) ) : '';

		if ( '' === $name ) {
			wc_add_notice( __( 'Please enter the recipient name.', 'wcgw' ), 'error' );
			return false;
		}
		if ( '' === $sender ) {
			wc_add_notice( __( 'Please enter your name as the sender.', 'wcgw' ), 'error' );
			return false;
		}
		if ( ! preg_match( '/^\+?[0-9\s\-]{8,20}$/', $phone ) ) {
			wc_add_notice( __( 'Please enter a valid recipient WhatsApp number in international format (e.g. +201234567890).', 'wcgw' ), 'error' );
			return false;
		}

		return $passed;
	}

	public static function normalise_phone( $phone ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $phone );
		return $digits;
	}

	public static function enqueue_assets() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_script( 'wcgw-recipient-fields', WCGW_URL . 'assets/js/recipient-fields.js', [ 'jquery' ], WCGW_VERSION, true );
		}
	}
}
