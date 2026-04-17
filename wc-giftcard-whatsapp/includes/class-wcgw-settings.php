<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCGW_Settings {

	const TAB_SLUG = 'wcgw';

	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', [ __CLASS__, 'add_tab' ], 60 );
		add_action( 'woocommerce_settings_tabs_' . self::TAB_SLUG, [ __CLASS__, 'render_tab' ] );
		add_action( 'woocommerce_update_options_' . self::TAB_SLUG, [ __CLASS__, 'save_tab' ] );

		add_action( 'wp_ajax_wcgw_test_send', [ __CLASS__, 'ajax_test_send' ] );
		add_action( 'admin_footer', [ __CLASS__, 'inject_test_button_js' ] );
	}

	public static function add_tab( $tabs ) {
		$tabs[ self::TAB_SLUG ] = __( 'Gift Cards', 'wcgw' );
		return $tabs;
	}

	private static function fields() {
		return [
			[
				'title' => __( 'WhatsApp Gift Card Settings', 'wcgw' ),
				'type'  => 'title',
				'desc'  => __( 'Credentials are obtained from the Meta Business WhatsApp dashboard.', 'wcgw' ),
				'id'    => 'wcgw_section_title',
			],
			[
				'title'    => __( 'Meta Access Token', 'wcgw' ),
				'id'       => 'wcgw_access_token',
				'type'     => 'password',
				'desc_tip' => __( 'Permanent System User access token with whatsapp_business_messaging permission.', 'wcgw' ),
			],
			[
				'title'    => __( 'Phone Number ID', 'wcgw' ),
				'id'       => 'wcgw_phone_number_id',
				'type'     => 'text',
				'desc_tip' => __( 'From Meta: WhatsApp > API Setup > Phone Number ID.', 'wcgw' ),
			],
			[
				'title'    => __( 'Template name', 'wcgw' ),
				'id'       => 'wcgw_template_name',
				'type'     => 'text',
				'default'  => 'gift_card_notification',
			],
			[
				'title'    => __( 'Template language', 'wcgw' ),
				'id'       => 'wcgw_template_language',
				'type'     => 'text',
				'default'  => 'en_US',
				'desc_tip' => __( 'Language code used when creating the template (e.g. en_US, ar).', 'wcgw' ),
			],
			[
				'title'    => __( 'Default sender name', 'wcgw' ),
				'id'       => 'wcgw_default_sender_name',
				'type'     => 'text',
				'default'  => get_bloginfo( 'name' ),
				'desc_tip' => __( 'Used when the buyer is a guest and did not provide a sender name.', 'wcgw' ),
			],
			[
				'title'    => __( 'Test mode', 'wcgw' ),
				'id'       => 'wcgw_test_mode',
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc'     => __( 'Route all messages to the test recipient below instead of real recipients.', 'wcgw' ),
			],
			[
				'title'    => __( 'Test recipient phone', 'wcgw' ),
				'id'       => 'wcgw_test_recipient',
				'type'     => 'text',
				'desc_tip' => __( 'International format, no spaces. e.g. 201234567890', 'wcgw' ),
			],
			[
				'type' => 'sectionend',
				'id'   => 'wcgw_section_title',
			],
		];
	}

	public static function render_tab() {
		woocommerce_admin_fields( self::fields() );

		echo '<h3>' . esc_html__( 'Send a test message', 'wcgw' ) . '</h3>';
		echo '<p>' . esc_html__( 'Sends the configured template to the test recipient to verify credentials.', 'wcgw' ) . '</p>';
		echo '<p><button type="button" class="button" id="wcgw-test-send">' . esc_html__( 'Send test message', 'wcgw' ) . '</button>';
		echo ' <span id="wcgw-test-result" style="margin-left:1em;"></span></p>';
		wp_nonce_field( 'wcgw_test_send', 'wcgw_test_nonce' );
	}

	public static function save_tab() {
		woocommerce_update_options( self::fields() );
	}

	public static function ajax_test_send() {
		check_ajax_referer( 'wcgw_test_send', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
		}

		$phone = get_option( 'wcgw_test_recipient', '' );
		if ( '' === $phone ) {
			wp_send_json_error( [ 'message' => __( 'Set a test recipient phone first.', 'wcgw' ) ] );
		}

		$template = get_option( 'wcgw_template_name', 'gift_card_notification' );
		$lang     = get_option( 'wcgw_template_language', 'en_US' );

		try {
			$response = WCGW_WhatsApp::send_template(
				$phone,
				$template,
				$lang,
				[
					__( 'Test Recipient', 'wcgw' ),
					wc_price( 100 ),
					get_option( 'wcgw_default_sender_name', get_bloginfo( 'name' ) ),
					'GC-TEST-0000',
					__( 'This is a test message.', 'wcgw' ),
				]
			);
			wp_send_json_success( [ 'response' => $response ] );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function inject_test_button_js() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}
		if ( ! isset( $_GET['tab'] ) || self::TAB_SLUG !== $_GET['tab'] ) {
			return;
		}
		?>
		<script>
		jQuery(function($){
			$('#wcgw-test-send').on('click', function(){
				var $btn = $(this), $out = $('#wcgw-test-result');
				$btn.prop('disabled', true);
				$out.text('<?php echo esc_js( __( 'Sending…', 'wcgw' ) ); ?>');
				$.post(ajaxurl, {
					action: 'wcgw_test_send',
					nonce: $('#wcgw_test_nonce').val()
				}).done(function(resp){
					if (resp && resp.success) {
						$out.css('color', 'green').text('<?php echo esc_js( __( 'Sent. Check the test phone.', 'wcgw' ) ); ?>');
					} else {
						$out.css('color', 'red').text((resp && resp.data && resp.data.message) || 'Error');
					}
				}).fail(function(xhr){
					$out.css('color', 'red').text('HTTP ' + xhr.status);
				}).always(function(){
					$btn.prop('disabled', false);
				});
			});
		});
		</script>
		<?php
	}
}
