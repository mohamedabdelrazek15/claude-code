<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sends gift card messages through the Gmail API using OAuth2 refresh tokens.
 *
 * Setup:
 *   1. In Google Cloud Console create an OAuth 2.0 Client ID (Web app).
 *   2. Enable the Gmail API.
 *   3. Obtain a refresh token for the sending Gmail account with scope
 *      https://www.googleapis.com/auth/gmail.send
 *   4. Enter Client ID, Client Secret, Refresh Token, and From email in
 *      WooCommerce → Gift Cards.
 */
class Hidoom_GC_Gmail {

    const TOKEN_OPTION = 'hidoom_gc_gmail_access_token';

    public static function is_configured() {
        $client_id     = Hidoom_GC_Settings::get( 'gmail_client_id' );
        $client_secret = Hidoom_GC_Settings::get( 'gmail_client_secret' );
        $refresh_token = Hidoom_GC_Settings::get( 'gmail_refresh_token' );
        $from_email    = Hidoom_GC_Settings::get( 'gmail_from_email' );
        return $client_id && $client_secret && $refresh_token && $from_email;
    }

    /**
     * Get a valid access token, refreshing via the refresh_token grant if needed.
     *
     * @return string|WP_Error
     */
    protected static function get_access_token() {
        $cached = get_option( self::TOKEN_OPTION );
        if ( is_array( $cached ) && ! empty( $cached['access_token'] ) && ! empty( $cached['expires_at'] ) && $cached['expires_at'] > ( time() + 60 ) ) {
            return $cached['access_token'];
        }

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'timeout' => 20,
            'body'    => array(
                'client_id'     => Hidoom_GC_Settings::get( 'gmail_client_id' ),
                'client_secret' => Hidoom_GC_Settings::get( 'gmail_client_secret' ),
                'refresh_token' => Hidoom_GC_Settings::get( 'gmail_refresh_token' ),
                'grant_type'    => 'refresh_token',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $body['access_token'] ) ) {
            $message = isset( $body['error_description'] ) ? $body['error_description'] : 'Unknown error';
            return new WP_Error( 'hidoom_gc_token_failed', 'Gmail token refresh failed: ' . $message );
        }

        $expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;
        update_option( self::TOKEN_OPTION, array(
            'access_token' => $body['access_token'],
            'expires_at'   => time() + $expires_in,
        ) );

        return $body['access_token'];
    }

    /**
     * Send an HTML email via Gmail API.
     *
     * @param string $to_email
     * @param string $to_name
     * @param string $subject
     * @param string $html_body
     * @return true|WP_Error
     */
    public static function send( $to_email, $to_name, $subject, $html_body ) {
        if ( ! self::is_configured() ) {
            return new WP_Error( 'hidoom_gc_not_configured', __( 'Gmail API is not configured.', 'hidoom-gift-card' ) );
        }

        $token = self::get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $from_email = Hidoom_GC_Settings::get( 'gmail_from_email' );
        $from_name  = Hidoom_GC_Settings::get( 'gmail_from_name' );
        $from       = $from_name ? sprintf( '%s <%s>', self::encode_header( $from_name ), $from_email ) : $from_email;
        $to         = $to_name ? sprintf( '%s <%s>', self::encode_header( $to_name ), $to_email ) : $to_email;

        $headers   = array();
        $headers[] = 'From: ' . $from;
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . self::encode_header( $subject );
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';

        $raw_message = implode( "\r\n", $headers ) . "\r\n\r\n" . chunk_split( base64_encode( $html_body ) );
        $raw_b64url  = rtrim( strtr( base64_encode( $raw_message ), '+/', '-_' ), '=' );

        $response = wp_remote_post( 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send', array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array( 'raw' => $raw_b64url ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            $body = wp_remote_retrieve_body( $response );
            return new WP_Error( 'hidoom_gc_send_failed', 'Gmail send failed (' . $code . '): ' . $body );
        }

        return true;
    }

    protected static function encode_header( $value ) {
        if ( preg_match( '/[^\x20-\x7E]/', $value ) ) {
            return '=?UTF-8?B?' . base64_encode( $value ) . '?=';
        }
        return $value;
    }
}
