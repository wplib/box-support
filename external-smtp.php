<?php

namespace WPLib_Box;

use PHPMailer;

class External_Smtp {

	/**
	 * @var string
	 */
	static private $_external_host;

	/**
	 * @var bool|callable
	 */
	static private $_switch_criteria;

	static function on_load() {
		add_action( 'phpmailer_init', [ __CLASS__, '_phpmailer_init' ] );
	}

	/**
	 * @param string $host
	 */
	static function set_external_host( $host ) {
		self::$_external_host = $host;
	}

	/**
	 * @param callable $switch_criteria
	 */
	static function set_switch_criteria( $switch_criteria ) {
		self::$_switch_criteria = $switch_criteria;
	}

	/**
	 * @param PHPMailer $phpmailer
	 */
	static function _phpmailer_init( $phpmailer ) {
		do {

			$use_external = true;

			$headers = $phpmailer->getCustomHeaders();
			if ( isset( $headers[ 'X-External-Host' ] ) ) {
				/**
				 * First look for a custom header and use it if found
				 */
				self::$_external_host = $headers[ 'X-External-Host' ];
				/**
				 * If set we know we want to use external host.
				 */
				break;
			}

			if ( ! isset( self::$_external_host ) ) {
				/**
				 * If no external host we can't send via external host
				 */
				break;
			}

			if ( ! isset( self::$_switch_criteria ) ) {
				/**
				 * If switch criteria evaluates to false, we should not use external host
				 */
				break;
			}

			if ( ! self::$_switch_criteria ) {
				/**
				 * Look to see if it wasn't set to some non-false value.
				 */
				break;
			} elseif ( ! is_callable( self::$_switch_criteria ) ) {
				/**
				 * Now look to specifically to see if ::$_switch_criteria() can be called
				 */
				break;
			}

			/**
			 * @param PHPMailer $phpmailer
			 */
			if ( ! call_user_func( self::$_switch_criteria, $phpmailer ) )  {
				/**
				 * It can be called. Leave $use_external === true
				 */
				break;
			}

			$use_external = false;

		} while ( false );

		if ( $use_external && isset( self::$_external_host ) ) {

			$phpmailer->Host = self::$_external_host;

		}

	}
}
External_Smtp::on_load();