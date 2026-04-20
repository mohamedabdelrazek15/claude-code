<?php
/**
 * Plugin Name: Hidoom Gift Cards for WooCommerce
 * Plugin URI:  https://example.com/hidoom-gift-card
 * Description: Adds a gift card form to products in the "Gift Card" category, generates a unique one-time coupon for the receiver on payment, and emails it via Gmail API. هيدوم. هيدوم معاك
 * Version:     1.0.0
 * Author:      Hidoom
 * Text Domain: hidoom-gift-card
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HIDOOM_GC_VERSION', '1.0.0' );
define( 'HIDOOM_GC_FILE', __FILE__ );
define( 'HIDOOM_GC_PATH', plugin_dir_path( __FILE__ ) );
define( 'HIDOOM_GC_URL', plugin_dir_url( __FILE__ ) );
define( 'HIDOOM_GC_CATEGORY_SLUG', 'gift-card' );
define( 'HIDOOM_GC_SIGNATURE', 'هيدوم. هيدوم معاك' );

register_activation_hook( __FILE__, 'hidoom_gc_activate' );

function hidoom_gc_activate() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'Hidoom Gift Cards requires WooCommerce to be installed and active.', 'hidoom-gift-card' ) );
    }

    if ( ! term_exists( HIDOOM_GC_CATEGORY_SLUG, 'product_cat' ) ) {
        wp_insert_term(
            'Gift Card',
            'product_cat',
            array( 'slug' => HIDOOM_GC_CATEGORY_SLUG )
        );
    }
}

add_action( 'plugins_loaded', 'hidoom_gc_bootstrap' );

function hidoom_gc_bootstrap() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>' .
                esc_html__( 'Hidoom Gift Cards needs WooCommerce to be active.', 'hidoom-gift-card' ) .
                '</p></div>';
        } );
        return;
    }

    require_once HIDOOM_GC_PATH . 'includes/class-hidoom-gc-settings.php';
    require_once HIDOOM_GC_PATH . 'includes/class-hidoom-gc-form.php';
    require_once HIDOOM_GC_PATH . 'includes/class-hidoom-gc-cart.php';
    require_once HIDOOM_GC_PATH . 'includes/class-hidoom-gc-coupon.php';
    require_once HIDOOM_GC_PATH . 'includes/class-hidoom-gc-gmail.php';
    require_once HIDOOM_GC_PATH . 'includes/class-hidoom-gc-order.php';
    require_once HIDOOM_GC_PATH . 'includes/class-hidoom-gc-admin.php';

    Hidoom_GC_Settings::init();
    Hidoom_GC_Form::init();
    Hidoom_GC_Cart::init();
    Hidoom_GC_Order::init();
    Hidoom_GC_Admin::init();
}
