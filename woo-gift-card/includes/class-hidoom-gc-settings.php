<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hidoom_GC_Settings {

    const OPTION_KEY = 'hidoom_gc_settings';

    public static function init() {
        // Options accessor only; registration lives in Hidoom_GC_Admin.
    }

    public static function get_all() {
        $defaults = array(
            'gmail_client_id'     => '',
            'gmail_client_secret' => '',
            'gmail_refresh_token' => '',
            'gmail_from_email'    => '',
            'gmail_from_name'     => '',
            'email_subject'       => 'You received a gift card!',
            'coupon_prefix'       => 'GIFT',
            'coupon_expiry_days'  => 365,
        );
        $saved = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
    }

    public static function get( $key ) {
        $all = self::get_all();
        return isset( $all[ $key ] ) ? $all[ $key ] : '';
    }

    public static function update( $values ) {
        update_option( self::OPTION_KEY, $values );
    }
}
