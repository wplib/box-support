<?php

/**
 * Class Disable_WP_Org_Api_Requests
 *
 */
class Disable_WP_Org_Api_Requests {

	static function on_load() {
		add_action( 'pre_http_request',     [ __CLASS__, '_pre_http_request' ], 10, 3 );
		add_action( 'plugins_api',          [ __CLASS__, '_plugins_api' ], 10 );
		add_action( 'plugins_api_result',   [ __CLASS__, '_plugins_api_result' ], 10 );
	}

	/**
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @return bool
	 */
	static function _plugins_api( $result ) {
		return false;
	}

	/**
	 * @param object|WP_Error $res    Response object or WP_Error.
	 * @return WP_Error
	 */
	static function _plugins_api_result( $response ) {
		return new WP_Error();
	}

	static function _pre_http_request( $result, $request, $url ) {

		do {

			if ( 'https://api.wordpress.org/core/browse-happy/1.1/' === $url ) {
				$result = new WP_Error();
				break;
			}

			if ( 'https://api.wordpress.org/events/1.0/' === $url ) {
				$result = new WP_Error();
				break;
			}

			if ( 'https://api.wordpress.org/themes/info/1.0/' === $url ) {
				$result = array( 'body' => [] );
				break;
			}

			if ( 'https://api.wordpress.org/plugins/info/1.0/' === $url ) {
				$result = array( 'body' => (object)array( 'plugins' => [] ) );
				break;
			}

			if ( 'https://api.wordpress.org/plugins/update-check/1.1/' === $url ) {
				$result = array( 'body' => '[]' );
				break;
			}

			if ( preg_match( '#^' . preg_quote( 'https://api.wordpress.org/translations/' ) . '#', $url ) ) {
				$result = array( 'body' => '[]' );
				break;
			}

			if ( preg_match( '#^' . preg_quote( 'https://api.wordpress.org/core/version-check/1.7/' ) . '#', $url ) ) {
				$result = array( 'body' => '0' );
				break;
			}

		} while ( false );

		return $result;
	}

}

Disable_WP_Org_Api_Requests::on_load();