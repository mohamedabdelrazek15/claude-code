<?php
/**
 * Plugin Name: WooCommerce Gift Card → WhatsApp
 * Plugin URI:  https://example.com/
 * Description: Turns selected WooCommerce products into gift cards. When a buyer completes payment, a balance-tracking coupon is generated and sent to the recipient over WhatsApp Cloud API.
 * Version:     1.0.0
 * Author:      Your Store
 * Text Domain: wcgw
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCGW_VERSION', '1.0.0' );
define( 'WCGW_FILE', __FILE__ );
define( 'WCGW_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCGW_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'wcgw_bootstrap', 20 );

function wcgw_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>WooCommerce Gift Card → WhatsApp</strong> requires WooCommerce to be active.</p></div>';
		} );
		return;
	}

	require_once WCGW_PATH . 'includes/class-wcgw-product.php';
	require_once WCGW_PATH . 'includes/class-wcgw-cart.php';
	require_once WCGW_PATH . 'includes/class-wcgw-checkout.php';
	require_once WCGW_PATH . 'includes/class-wcgw-coupon.php';
	require_once WCGW_PATH . 'includes/class-wcgw-whatsapp.php';
	require_once WCGW_PATH . 'includes/class-wcgw-order.php';
	require_once WCGW_PATH . 'includes/class-wcgw-settings.php';

	WCGW_Product::init();
	WCGW_Cart::init();
	WCGW_Checkout::init();
	WCGW_Coupon::init();
	WCGW_Order::init();
	WCGW_Settings::init();
}

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
