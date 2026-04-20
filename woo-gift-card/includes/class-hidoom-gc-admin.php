<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin settings page: WooCommerce → Gift Cards.
 * Lets the shop owner enter Gmail API credentials and email defaults.
 */
class Hidoom_GC_Admin {

    const PAGE_SLUG        = 'hidoom-gift-card';
    const LAST_ERROR_OPTION = 'hidoom_gc_last_error';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_hidoom_gc_save', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_hidoom_gc_test_email', array( __CLASS__, 'handle_test_email' ) );
        add_action( 'admin_notices', array( __CLASS__, 'maybe_show_notice' ) );
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

    protected static function redirect_with( $args ) {
        $url = add_query_arg(
            array_merge( array( 'page' => self::PAGE_SLUG ), $args ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission.', 'hidoom-gift-card' ) );
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
        delete_option( self::LAST_ERROR_OPTION );

        self::redirect_with( array( 'hidoom_gc_notice' => 'saved' ) );
    }

    public static function handle_test_email() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission.', 'hidoom-gift-card' ) );
        }
        check_admin_referer( 'hidoom_gc_test_email' );

        $to = isset( $_POST['hidoom_gc_test_to'] ) ? sanitize_email( wp_unslash( $_POST['hidoom_gc_test_to'] ) ) : '';
        if ( ! is_email( $to ) ) {
            self::redirect_with( array( 'hidoom_gc_notice' => 'bad_email' ) );
        }

        if ( ! Hidoom_GC_Gmail::is_configured() ) {
            update_option( self::LAST_ERROR_OPTION, __( 'Gmail API is not configured. Save Client ID, Secret, Refresh Token, and From email first.', 'hidoom-gift-card' ) );
            self::redirect_with( array( 'hidoom_gc_notice' => 'test_failed' ) );
        }

        $html = '<p>Hidoom Gift Cards test email. If you can read this, Gmail API is working.</p>'
            . '<p dir="rtl"><strong>' . esc_html( HIDOOM_GC_SIGNATURE ) . '</strong></p>';

        $result = Hidoom_GC_Gmail::send( $to, '', 'Hidoom Gift Cards — Test', $html );

        if ( is_wp_error( $result ) ) {
            update_option( self::LAST_ERROR_OPTION, $result->get_error_message() );
            self::redirect_with( array( 'hidoom_gc_notice' => 'test_failed' ) );
        }

        delete_option( self::LAST_ERROR_OPTION );
        self::redirect_with( array(
            'hidoom_gc_notice' => 'test_ok',
            'hidoom_gc_to'     => rawurlencode( $to ),
        ) );
    }

    public static function maybe_show_notice() {
        if ( empty( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
            return;
        }
        $notice = isset( $_GET['hidoom_gc_notice'] ) ? sanitize_key( $_GET['hidoom_gc_notice'] ) : '';
        if ( ! $notice ) {
            return;
        }

        switch ( $notice ) {
            case 'saved':
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gift Card settings saved.', 'hidoom-gift-card' ) . '</p></div>';
                break;
            case 'bad_email':
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid test recipient email.', 'hidoom-gift-card' ) . '</p></div>';
                break;
            case 'test_failed':
                $msg = get_option( self::LAST_ERROR_OPTION, '' );
                echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Test email failed:', 'hidoom-gift-card' ) . '</strong><br><code>' . esc_html( $msg ) . '</code></p></div>';
                break;
            case 'test_ok':
                $to = isset( $_GET['hidoom_gc_to'] ) ? sanitize_email( rawurldecode( wp_unslash( $_GET['hidoom_gc_to'] ) ) ) : '';
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Test email sent to %s.', 'hidoom-gift-card' ), $to ) ) . '</p></div>';
                break;
        }
    }

    public static function render_page() {
        $s          = Hidoom_GC_Settings::get_all();
        $last_error = get_option( self::LAST_ERROR_OPTION, '' );
        $configured = Hidoom_GC_Gmail::is_configured();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Hidoom Gift Cards', 'hidoom-gift-card' ); ?></h1>
            <p>
                <?php esc_html_e( 'Configure the Gmail API credentials used to send gift card emails. Products in the "Gift Card" category will show the gift form automatically.', 'hidoom-gift-card' ); ?>
            </p>

            <p>
                <?php esc_html_e( 'Gmail API status:', 'hidoom-gift-card' ); ?>
                <?php if ( $configured ) : ?>
                    <strong style="color:#1a7f37;"><?php esc_html_e( 'Configured', 'hidoom-gift-card' ); ?></strong>
                <?php else : ?>
                    <strong style="color:#b00;"><?php esc_html_e( 'Not configured', 'hidoom-gift-card' ); ?></strong>
                    <?php esc_html_e( '(fill in the 4 required fields below and click Save)', 'hidoom-gift-card' ); ?>
                <?php endif; ?>
            </p>

            <?php if ( $last_error ) : ?>
                <div class="notice notice-warning"><p>
                    <strong><?php esc_html_e( 'Last Gmail error:', 'hidoom-gift-card' ); ?></strong><br>
                    <code><?php echo esc_html( $last_error ); ?></code>
                </p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="hidoom_gc_save">
                <?php wp_nonce_field( 'hidoom_gc_save_settings' ); ?>

                <h2><?php esc_html_e( 'Gmail API', 'hidoom-gift-card' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Create an OAuth2 client in Google Cloud Console, enable the Gmail API, then obtain a refresh token with the gmail.send scope for the sending account.', 'hidoom-gift-card' ); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="gmail_client_id"><?php esc_html_e( 'Client ID', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="gmail_client_id" name="gmail_client_id" value="<?php echo esc_attr( $s['gmail_client_id'] ); ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gmail_client_secret"><?php esc_html_e( 'Client Secret', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="gmail_client_secret" name="gmail_client_secret" value="<?php echo esc_attr( $s['gmail_client_secret'] ); ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gmail_refresh_token"><?php esc_html_e( 'Refresh Token', 'hidoom-gift-card' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="gmail_refresh_token" name="gmail_refresh_token" value="<?php echo esc_attr( $s['gmail_refresh_token'] ); ?>" autocomplete="off"></td>
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
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'hidoom-gift-card' ); ?></button>
                </p>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Send Test Email', 'hidoom-gift-card' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="hidoom_gc_test_email">
                <?php wp_nonce_field( 'hidoom_gc_test_email' ); ?>
                <p>
                    <input type="email" class="regular-text" name="hidoom_gc_test_to" placeholder="recipient@example.com" required>
                    <button type="submit" class="button"><?php esc_html_e( 'Send Test', 'hidoom-gift-card' ); ?></button>
                </p>
                <p class="description"><?php esc_html_e( 'Uses the Gmail API credentials saved above.', 'hidoom-gift-card' ); ?></p>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Troubleshooting', 'hidoom-gift-card' ); ?></h2>
            <ul style="list-style:disc; padding-left:20px;">
                <li><?php esc_html_e( 'If the test email fails with "invalid_grant", your refresh token is expired or revoked — in Google Cloud Console, publish the OAuth consent screen (move it out of Testing) or re-issue a token at developers.google.com/oauthplayground.', 'hidoom-gift-card' ); ?></li>
                <li><?php esc_html_e( 'If you see "unauthorized_client", the Client ID and Secret do not match the project where Gmail API is enabled.', 'hidoom-gift-card' ); ?></li>
                <li><?php esc_html_e( 'The refresh token must be obtained with scope https://www.googleapis.com/auth/gmail.send and while signed into the same Gmail account you entered in From Email.', 'hidoom-gift-card' ); ?></li>
                <li><?php esc_html_e( 'The OAuth consent screen should be set to External. In Testing mode, add the From Email as a test user (published apps do not need this).', 'hidoom-gift-card' ); ?></li>
            </ul>

            <h2><?php esc_html_e( 'Quick Reference', 'hidoom-gift-card' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Create Simple products for each gift card value and assign them to the Gift Card category.', 'hidoom-gift-card' ); ?>
                    <?php printf( '<code>%s</code>', esc_html( HIDOOM_GC_CATEGORY_SLUG ) ); ?>
                </li>
                <li><?php esc_html_e( 'Customers will see the gift card form on the product page automatically.', 'hidoom-gift-card' ); ?></li>
                <li><?php esc_html_e( 'After payment, a one-time coupon is created and emailed to the receiver.', 'hidoom-gift-card' ); ?></li>
            </ol>
        </div>
        <?php
    }
}
