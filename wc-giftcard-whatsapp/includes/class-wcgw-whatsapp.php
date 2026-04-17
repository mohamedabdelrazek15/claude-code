<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCGW_WhatsApp {

	const GRAPH_VERSION = 'v21.0';

	public static function send_template( $to_phone, $template, $lang, array $variables ) {
		$token           = get_option( 'wcgw_access_token', '' );
		$phone_number_id = get_option( 'wcgw_phone_number_id', '' );

		if ( '' === $token || '' === $phone_number_id ) {
			throw new Exception( 'WhatsApp credentials are not configured.' );
		}

		if ( 'yes' === get_option( 'wcgw_test_mode', 'no' ) ) {
			$test_recipient = get_option( 'wcgw_test_recipient', '' );
			if ( '' !== $test_recipient ) {
				$to_phone = $test_recipient;
			}
		}

		$to = preg_replace( '/[^0-9]/', '', (string) $to_phone );
		if ( '' === $to ) {
			throw new Exception( 'Recipient phone number is empty.' );
		}

		$parameters = [];
		foreach ( $variables as $value ) {
			$parameters[] = [
				'type' => 'text',
				'text' => (string) $value,
			];
		}

		$body = [
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'template',
			'template'          => [
				'name'       => $template,
				'language'   => [ 'code' => $lang ],
				'components' => [
					[
						'type'       => 'body',
						'parameters' => $parameters,
					],
				],
			],
		];

		$url = sprintf(
			'https://graph.facebook.com/%s/%s/messages',
			self::GRAPH_VERSION,
			rawurlencode( $phone_number_id )
		);

		$response = wp_remote_post( $url, [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			self::log_error( 'HTTP error: ' . $response->get_error_message(), $body );
			throw new Exception( 'WhatsApp request failed: ' . $response->get_error_message() );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$payload = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			self::log_error( sprintf( 'WhatsApp API returned HTTP %d: %s', $code, $payload ), $body );
			throw new Exception( sprintf( 'WhatsApp API HTTP %d: %s', $code, $payload ) );
		}

		self::log_info( sprintf( 'Sent template "%s" to %s. Response: %s', $template, $to, $payload ) );

		return json_decode( $payload, true );
	}

	private static function log_info( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( $message, [ 'source' => 'wcgw' ] );
		}
	}

	private static function log_error( $message, $context = [] ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message . ' Context: ' . wp_json_encode( $context ), [ 'source' => 'wcgw' ] );
		}
	}
}
