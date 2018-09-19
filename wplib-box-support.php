<?php

/*
 * Plugin Name: WPLib Box Support Plugin
 * Plugin URL: https://github.com/wplib/wplib-box-support-plugin
 * Description: Plugin to provide UX support to WPLib Box
 * Version: 0.16.0-rc
 * Author: The WPLib Team
 * Author URI: https://github.com/wplib
 */

/**
 * Class WPLib_Box_Support
 */
class WPLib_Box_Support {

	const AUTO_LOGIN_PARAM = 'auto-login';
	const AUTO_LOGIN_VALUE = 'yes';
	const AUTO_LOGIN_EMAIL = 'admin@wplib.box';

	const DEFAULT_USERNAME = 'admin';
	const DEFAULT_PASSWORD = 'password';

	/**
	 *
	 */
	static function on_load() {

		add_action( 'init', array( __CLASS__, '_init_11' ), 11 );

	}

	/**
	 *
	 */
	static function _init_11() {

		if ( self::_can_auto_login() ) {
			/**
			 * ONLY run WPLib Box support plugin when WPLib Box is the host.
			 */
			add_action( 'login_message', array( __CLASS__, '_login_message' ) );
			add_action( 'wp_loaded', array( __CLASS__, '_wp_loaded' ) );
			add_action( 'set_url_scheme', array( __CLASS__, '_set_url_scheme' ) );
		}

	}

	/**
	 * Limits auto login support to only use in WPLib Box, unless the developer
	 * uses the `'wplib:can_auto_login'` filter.
	 */
	static function _can_auto_login() {

		return apply_filters( 'wplib:can_auto_login', isset( $_SERVER[ 'WPLIB_BOX' ] ) );

	}

	/**
	 * @param string $url
	 *
	 * @return string
	 */
	static function _set_url_scheme( $url ) {
		return defined( 'BOX_URL_SCHEME' )
			? preg_replace( '#^https?#', BOX_URL_SCHEME, $url )
			: $url;
	}

	/**
	 * Test to see if the auto-login URL is as we expect it to be.
	 */
	static function _wp_loaded() {
		do {
			if ( ! is_admin() ) {
				break;
			}

			if ( ! isset( $_GET[ self::AUTO_LOGIN_PARAM ] ) ) {
				break;
			}

			if ( self::AUTO_LOGIN_VALUE !== $_GET[ self::AUTO_LOGIN_PARAM ] ) {
				break;
			}

			self::auto_login_admin();

		} while ( false );

	}

	/**
	 * Automatically logs in a user as 'admin' and redirects to the admin console.
	 */
	static function auto_login_admin() {

		do {

			$username = self::DEFAULT_USERNAME;

			/**
			 * Let's try the email address we plan to use: 'admin@wplib.box'
			 */
			$user = get_user_by( 'email', self::AUTO_LOGIN_EMAIL );

			if ( isset( $user->ID ) ) {
				/**
				 * We found one, let's move on to auto-logging-in.
				 */
				break;
			}

			/**
			 * Let's grab the username we plan to use: 'admin', or if modified.
			 * Then let's lookup based on the user_login
			 */
			$username = apply_filters( 'wplib:auto_login_username', self::DEFAULT_USERNAME );
			$user = get_user_by( 'login', $username );

			if ( isset( $user->roles ) ) {

				if ( is_array( $user->roles ) && in_array( 'administrator', $user->roles ) ) {
					/**
					 * We found an administrator! Let's move on to auto-logging-in.
					 */
					break;
				}
				/**
				 * If we logged in but it's not an actual administrator (psych!) then
				 * find the first administrator
				 */
				$user = self::_login_as_first_admin();
			}

			if ( isset( $user->ID ) ) {
				/**
				 * We found a user so proceed to auto-login
				 */
				break;
			}

			/**
			 * We did not find a user, let's add one
			 */
			$user_id = wp_insert_user( array(
				'user_login'    => $username,
				'user_pass'     => apply_filters( 'wplib:auto_login_password', self::DEFAULT_PASSWORD ),
				'user_nicename' => 'WPLib Box User',
				'user_email'    => self::AUTO_LOGIN_EMAIL,
				'user_url'      => 'https://wplib.github.io/wplib-box/',
				'role'          => 'administrator',
				'description'   => 'Default WPLib Box User',
			) );

			if ( is_wp_error( $user_id ) ) {
				break;
			}

			if ( ! is_numeric( $user_id ) ) {
				break;
			}

			$user = get_user_by( 'id', $user_id );

		} while ( false );

		if ( isset( $user->ID ) ) {
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true );
			do_action( 'wp_login', $username, $user );
			wp_safe_redirect( admin_url(), 302 );
			exit;
		}

	}

	/**
	 * Brute force login as the first admin, assuming we already
	 * have an `admin` but they are not an administrator role.
	 */
	private static function _login_as_first_admin() {

		for( $i = 1; $i < 1000; $i++ ) {
			$user = get_user_by( 'id', $i );

			if ( ! $user ) {
				continue;
			}
			if ( ! isset( $user->roles[0] ) ) {
				continue;
			}
			if ( 0 === count( $user->roles[0] ) ) {
				continue;
			}
			if ( ! is_array( $user->roles ) ) {
				continue;
			}
			if ( 'administrator' === $user->roles[0] ) {
				break;
			}
		}
		return $user;

	}

	/**
	 * @return string
	 */
	static function auto_login_url() {
		return admin_url( '?' . self::AUTO_LOGIN_PARAM . '=' . self::AUTO_LOGIN_VALUE );
	}

	/**
	 * @param string $message
	 * @return string
	 */
	static function _login_message( $message ) {

		$auto_login = self::auto_login_url();
		$username = apply_filters( 'wplib:auto_login_username', self::DEFAULT_USERNAME );
		$password = apply_filters( 'wplib:auto_login_password', self::DEFAULT_PASSWORD );

		$html       = <<< HTML
<style type="text/css">
.wplib-box\:login-callout {margin-top:1em; font-size:2em;}
.wplib-box\:login-helper h2 {margin-bottom:0.5em;}
.wplib-box\:login-helper {width:100%;text-align:center;float:left;}
.wplib-box\:login-helper .inner {margin:0 auto 2em;width:320px;}
.wplib-box\:login-helper .inner p {margin:1em 50px 0;}
.wplib-box\:login-helper .inner ul {margin-left:80px;text-align:left;}
.wplib-box\:login-helper .inner ul li {margin-top:0.35em;}
.wplib-box\:login-helper .inner .name {font-weight:bold;width:6em;display:inline-block;}
.wplib-box\:login-helper .inner .value {}
.wplib-box\:login-helper .inner .credentials {font-family:monospace;background:white;padding:0.13em 0.25em;}
</style>		
<div class="wplib-box:login-helper">
	<h2 class="wplib-box:login-callout">WPLib Box Users</h2>
	<div class="inner">
		<p>The <strong>after login</strong> credentials will be:</p>
		<ul>
			<li><span class="name">Username:</span> <span class="value credentials">{$username}</span></li>
			<li><span class="name">Password:</span> <span class="value credentials">{$password}</span></li>
		</ul>
		<p><a href="{$auto_login}"><strong>Click here</strong></a><br>to auto-login as <span class="credentials">{$username}</span>.</p>
	</div>
</div>
HTML;

		return "{$html}{$message}";

	}


}

WPLib_Box_Support::on_load();