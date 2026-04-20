<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin settings page: WooCommerce → Gift Cards.
 * Lets the shop owner enter Gmail API credentials and email defaults.
 */
class Hidoom_GC_Admin {

    const PAGE_SLUG = 'hidoom-gift-card';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_test_email' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Gift Cards', 'hidoom-gift-card' ),
            __( 'Gift Cards', 'hidoom-gift-card' ),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    public static function handle_post() {
        if ( empty( $_POST['hidoom_gc_save'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        check_admin_referer( 'hidoom_gc_save_settings' );

        $values = array(
            'gmail_client_id'     => isset( $_POST['gmail_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gmail_client_id'] ) ) : '',
            'gmail_client_secret' => isset( $_POST['gmail_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['gmail_client_secret'] ) ) : '',
            'gmail_refresh_token' => isset( $_POST['gmail_refresh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['gmail_refresh_token'] ) ) : '',
            'gmail_from_email'    => isset( $_POST['gmail_from_email'] ) ? sanitize_email( wp_unslash( $_POST['gmail_from_email'] ) ) : '',
            'gmail_from_name'     => isset( $_POST['gmail_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['gmail_from_name'] ) ) : '',
            'email_subject'       => isset( $_POST['email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_subject'] ) ) : '',
            'coupon_prefix'       => isset( $_POST['coupon_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_prefix'] ) ) : 'GIFT',
            'coupon_expiry_days'  => isset( $_POST['coupon_expiry_days'] ) ? absint( $_POST['coupon_expiry_days'] ) : 365,
        );

        Hidoom_GC_Settings::update( $values );
        delete_option( Hidoom_GC_Gmail::TOKEN_OPTION );

        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__( 'Gift Card settings saved.', 'hidoom-gift-card' ) .
                '</p></div>';
        } );
    }

    public static function handle_test_email() {
        if ( empty( $_POST['hidoom_gc_test_email'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        check_admin_referer( 'hidoom_gc_test_email' );

        $to = isset( $_POST['hidoom_gc_test_to'] ) ? sanitize_email( wp_unslash( $_POST['hidoom_gc_test_to'] ) ) : '';
        if ( ! is_email( $to ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-error is-dismissible"><p>Invalid test recipient email.</p></div>';
            } );
            return;
        }

        $html = '<p>Hidoom Gift Cards test email. If you can read this, Gmail API is working.</p>'
            . '<p dir="rtl"><strong>' . esc_html( HIDOOM_GC_SIGNATURE ) . '</strong></p>';

        $result = Hidoom_GC_Gmail::send( $to, '', 'Hidoom Gift Cards — Test', $html );

        if ( is_wp_error( $result ) ) {
            $msg = esc_html( $result->get_error_message() );
            add_action( 'admin_notices', function () use ( $msg ) {
                echo '<div class="notice notice-error is-dismissible"><p>Test email failed: ' . $msg . '</p></div>';
            } );
        } else {
            add_action( 'admin_notices', function () use ( $to ) {
                echo '<div class="notice notice-success is-dismissible"><p>Test email sent to ' . esc_html( $to ) . '.</p></div>';
            } );
        }
    }

    public static function render_page() {
        $s = Hidoom_GC_Settings::get_all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Hidoom Gift Cards', 'hidoom-gift-card' ); ?></h1>
            <p>
                <?php esc_html_e( 'Configure the Gmail API credentials used to send gift card emails. Products in the "Gift Card" category will show the gift form automatically.', 'hidoom-gift-card' ); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field( 'hidoom_gc_save_settings' ); ?>

                <h2><?php esc_html_e( 'Gmail API', 'hidoom-gift-card' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Create an OAuth2 client in Google Cloud Console, enable the Gmail API, then obtain a refresh token with the gmail.send scope for the sending account.', 'hidoom-gift-card' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="gmail_client_id"><?php esc_html_e( 'Client ID', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="gmail_client_id" name="gmail_client_id" value="<?php echo esc_attr( $s['gmail_client_id'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gmail_client_secret"><?php esc_html_e( 'Client Secret', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="password" class="regular-text" id="gmail_client_secret" name="gmail_client_secret" value="<?php echo esc_attr( $s['gmail_client_secret'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gmail_refresh_token"><?php esc_html_e( 'Refresh Token', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="password" class="regular-text" id="gmail_refresh_token" name="gmail_refresh_token" value="<?php echo esc_attr( $s['gmail_refresh_token'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gmail_from_email"><?php esc_html_e( 'From Email', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="email" class="regular-text" id="gmail_from_email" name="gmail_from_email" value="<?php echo esc_attr( $s['gmail_from_email'] ); ?>" placeholder="you@example.com"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gmail_from_name"><?php esc_html_e( 'From Name', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="gmail_from_name" name="gmail_from_name" value="<?php echo esc_attr( $s['gmail_from_name'] ); ?>" placeholder="Hidoom"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Email & Coupon', 'hidoom-gift-card' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="email_subject"><?php esc_html_e( 'Email Subject', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="email_subject" name="email_subject" value="<?php echo esc_attr( $s['email_subject'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coupon_prefix"><?php esc_html_e( 'Coupon Prefix', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="coupon_prefix" name="coupon_prefix" value="<?php echo esc_attr( $s['coupon_prefix'] ); ?>" placeholder="GIFT"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coupon_expiry_days"><?php esc_html_e( 'Coupon Expires After (days)', 'hidoom-gift-card' ); ?></label></th>
                        <td>
                            <input type="number" min="0" step="1" id="coupon_expiry_days" name="coupon_expiry_days" value="<?php echo esc_attr( $s['coupon_expiry_days'] ); ?>">
                            <p class="description"><?php esc_html_e( 'Set 0 for no expiry.', 'hidoom-gift-card' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="hidoom_gc_save" class="button button-primary"><?php esc_html_e( 'Save Settings', 'hidoom-gift-card' ); ?></button>
                </p>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Send Test Email', 'hidoom-gift-card' ); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field( 'hidoom_gc_test_email' ); ?>
                <p>
                    <input type="email" class="regular-text" name="hidoom_gc_test_to" placeholder="recipient@example.com" required>
                    <button type="submit" name="hidoom_gc_test_email" class="button"><?php esc_html_e( 'Send Test', 'hidoom-gift-card' ); ?></button>
                </p>
                <p class="description"><?php esc_html_e( 'Uses the Gmail API credentials saved above.', 'hidoom-gift-card' ); ?></p>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Quick Reference', 'hidoom-gift-card' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Create 5 Simple products for each gift card value (e.g. $25, $50, $100...).', 'hidoom-gift-card' ); ?></li>
                <li><?php
                    printf(
                        /* translators: %s: category slug */
                        esc_html__( 'Assign them to the "Gift Card" category (slug: %s).', 'hidoom-gift-card' ),
                        '<code>' . esc_html( HIDOOM_GC_CATEGORY_SLUG ) . '</code>'
                    );
                ?></li>
                <li><?php esc_html_e( 'Customers will see the gift card form on the product page automatically.', 'hidoom-gift-card' ); ?></li>
                <li><?php esc_html_e( 'After payment, a one-time coupon is created and emailed to the receiver.', 'hidoom-gift-card' ); ?></li>
            </ol>
        </div>
        <?php
    }
}
