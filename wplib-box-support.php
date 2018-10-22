<?php

/*
 * Plugin Name: WPLib Box Support Plugin
 * Plugin URL: https://github.com/wplib/box-support
 * Description: Plugin to provide UX support to WPLib Box
 * Version: 0.17.1
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

	const SETTINGS_OPTION = 'box_support';
	const SETTINGS_NONCE = 'box_support_settings';
	const SETTINGS_FORM_FIELD = 'box_support';
	const SETTINGS_FIELDS = array(
	    'external_base_uploads_url'
    );

	/**
	 * @var string
	 */
	private static $_settings_page_hook;

	/**
	 * @var string
	 */
	private static $_plugin_label;

	/**
	 *
	 */
	static function on_load() {

		/**
		 * To support auto login functionality
		 */
		add_action( 'init', array( __CLASS__, '_init_11' ), 11 );

		/**
		 * To support External Base Uploads URL setting
		 */
		add_action( 'admin_menu', array( __CLASS__, '_admin_menu' ) );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, '_wp_get_attachment_url_11' ), 11 );
		add_filter( 'wp_calculate_image_srcset', array( __CLASS__, '_wp_calculate_image_srcset' ) );

		self::$_plugin_label = __( 'WPLib Box Support', 'box-support' );

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
	 * uses the `'box_support:can_auto_login'` filter.
	 */
	static function _can_auto_login() {

		/**
		 * @deprecated 'wplib:can_auto_login'
		 */
		$can_auto_login = apply_filters( 'wplib:can_auto_login', isset( $_SERVER[ 'WPLIB_BOX' ] ) );
        
		$can_auto_login = apply_filters( 'box_support:can_auto_login', $can_auto_login );
		return $can_auto_login;

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
             * 
             * @deprecated 'wplib:auto_login_username'  
			 */
			$username = apply_filters( 'wplib:auto_login_username', self::DEFAULT_USERNAME );
			$username = apply_filters( 'box_support:auto_login_username', $username );

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
			 * @deprecated 'wplib:auto_login_password'
			 */
			$user_id = wp_insert_user( array(
				'user_login'    => $username,
				'user_pass'     => apply_filters( 'box_support:auto_login_password', apply_filters( 'wplib:auto_login_password', self::DEFAULT_PASSWORD ) ),
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
		/**
		 * @deprecated 'wplib:auto_login_username'
		 * @deprecated 'wplib:auto_login_password'
		 */
		$username = apply_filters( 'wplib:auto_login_username', self::DEFAULT_USERNAME );
		$password = apply_filters( 'wplib:auto_login_password', self::DEFAULT_PASSWORD );

		$username = apply_filters( 'box_support:auto_login_username', $username );
		$password = apply_filters( 'box_support:auto_login_password', $password );

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

	/**
	 * Ensures attachment URL that might actually serve correctly
	 * Called priority 11 after Roots Soil strips off home_url().
	 *
	 * @uses self::get_attachment_url()
	 *
	 * @param string $attachment_url
	 *
	 * @return string|null
	 */

	static function _wp_get_attachment_url_11( $attachment_url ) {

		return self::get_attachment_url( $attachment_url );

	}
	/**
	 * Get the attachment URL based on "Best" Uploads URL given this uploads URL.
	 *
	 * Checks to see if the upload file is currently available locally.
	 * If not it uses ::get_setting_value( 'external_base_uploads_url' )
	 * for the base of the URL and refers it to the externally URL w/o
	 * taking time to validate
	 *
	 * @param string $attachment_url
	 *
	 * @return string|null
	 */
	static function get_attachment_url( $attachment_url ) {

		if ( '/' === $attachment_url[ 0 ] ) {
			$attachment_url = home_url( $attachment_url );
		}

		$upload_dir = (object) wp_upload_dir();

		$regex = '#^' . preg_quote( $upload_dir->baseurl ) . '(.+)$#';

		if ( preg_match( $regex, $attachment_url, $match ) ) {
			$path = $match[ 1 ];
			$filepath = "{$upload_dir->basedir}{$path}";
		} else {
			$filepath = $path = null;
		}

		if ( $filepath && ! is_file( $filepath ) ) {

			$attachment_url = self::get_external_attachment_url( $path );

		}

		return $attachment_url;

	}

	/**
	 * Ensures SrcSets are generated to a URl that might actually serve correctly
	 *
	 * @uses self::get_attachment_url()
	 *
	 * @param array  $sources {
	 *     One or more arrays of source data to include in the 'srcset'.
	 *
	 *     @type array $width {
	 *         @type string $url        The URL of an image source.
	 *         @type string $descriptor The descriptor type used in the image candidate string,
	 *                                  either 'w' or 'x'.
	 *         @type int    $value      The source width if paired with a 'w' descriptor, or a
	 *                                  pixel density value if paired with an 'x' descriptor.
	 *     }
	 * }
	 *
	 * @return array
	 */
	static function _wp_calculate_image_srcset( $sources ) {

		foreach( $sources as $index => $source ) {
			$source[ 'url' ] = self::get_attachment_url( $source[ 'url' ] );
			$sources[ $index ] = $source;
		}

		return $sources;

	}

	/**
	 * @var string $path
	 * @return string
	 */
	static function get_external_attachment_url( $path ) {
		static $base_uploads_url;
		if ( ! isset( $base_uploads_url ) ) {
			$base_uploads_url = self::get_setting_value( 'external_base_uploads_url' );
			if ( empty( $base_uploads_url ) ) {
				$upload_dir       = (object) wp_upload_dir();
				$base_uploads_url = $upload_dir->baseurl;
			}
			$base_uploads_url = trim( $base_uploads_url, '/' );
		}
		return "{$base_uploads_url}/" . ltrim( $path, '/' );
	}


	/*
	 * Add Settings page in Admin menu
	 */
	static function _admin_menu() {

	    if ( apply_filters( 'box_support:do_add_admin_menu', true ) ) {

		    self::$_settings_page_hook = add_options_page(
			    self::plugin_label(),
			    self::plugin_label(),
			    apply_filters( 'box_support:settings_menu_capability_required', 'manage_options' ),
			    'box-support',
			    array( __CLASS__, 'the_settings_page' )
		    );

	    }

	}

	/**
	 * @return string
	 */
	static function plugin_label() {
		return apply_filters( 'box_support:plugin_label', self::$_plugin_label );
	}

	/**
	 * Render the admin settings edit page.
	 */
	static function the_settings_page(){

		self::_update_settings_from_POST();

		$settings = self::settings();
		?>
        <style type="text/css">
            .wrap th,.wrap td,.wrap input[type=text]{font-size:1.1em;}
            #external_base_uploads_url,.wrap .description{width:600px;}
        </style>
        <div class="wrap"><h1><?php echo self::plugin_label(); ?></h1>
            <form method="post">

                <?php wp_nonce_field( self::SETTINGS_NONCE, '_wpnonce', $referrer = true, $echo = true ); ?>

                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="external_base_uploads_url"><?php _e( 'External Images Base', 'box-support' ); ?></label>:</th>
                        <td><input name="<?php echo self::SETTINGS_FORM_FIELD; ?>[external_base_uploads_url]" type="text" id="external_base_uploads_url"
                                   value="<?php esc_attr_e( $settings->external_base_uploads_url ); ?>" aria-describedby="username-desc">
                            <div class="description" id="username-desc"><?php
                                _e( 'This URL will be used as the base for any image URLs for which a image cannot be found in your local install. You can leave blank, or assign a URL for your uploads, e.g.:', 'box-support' );
                                echo '<ul><li><pre>  &bull; <code>';
                                _e( 'https://dev-example.pantheonsite.io/wp-content/uploads', 'box-support' );
                                echo '</code></pre></li></ul>';
                                if ( self::was_setting_defaulted( 'external_base_uploads_url' ) ):
                                    _e( "<strong><em>NOTE:</em></strong> The <code>BOX_EXTERNAL_BASE_UPLOADS_URL</code> constant provided the default value of the above URL. This constant was probably defined in <code>/wp-config.php</code>. Be aware that clicking the <em>\"Save Changes\"</em> button will clear this notice.", 'box-support' );
                                endif;
                            ?></div>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="submit_settings" id="submit" class="button button-primary" value="<?php _e('Save Changes', 'box-support'); ?>">
                </p>

            </form>
        </div>
		<?php

	}

	/**
	 * @var string $setting_name
	 * @return mixed|null
	 */
	static function get_setting_value( $setting_name ) {
		$settings = self::settings();
		return apply_filters( 
            'box_support:setting_value',
            ( isset( $settings->$setting_name ) ? $settings->$setting_name : null ),
			$setting_name
        );
	}

	/**
	 * Allow constants defined in wp-config to default a
	 * settings. Constant should start with BOX_ and
	 * be the uppercase equivalent of the setting name,
	 *
	 * @example: The constant BOX_EXTERNAL_BASE_UPLOADS_URL
	 *      will default $setting->external_base_uploads_url
	 *      assuming $setting->external_base_uploads_url
	 *      is null.
     *
     * @param string $setting_name
	 * @return bool
	 */
	static function get_setting_default( $setting_name ) {
	    do {
	        $default_value = null;
		    if ( ! defined( $constant_name = 'BOX_' . strtoupper( $setting_name ) ) ) {
		        break;
		    }
		    if ( ! $value = constant( $constant_name ) ) {
		        break;
		    }
		    $default_value = $value;
	    } while ( false );
	    return $default_value;
	}

	/**
     * Returns true if setting was defaulted by a BOX_* constant
     *
	 * @return bool
	 */
	static function was_setting_defaulted( $setting_name ) {
	    do {
	        $was_defaulted = false;
	        if ( ! defined( $constant_name = 'BOX_' . strtoupper( $setting_name ) ) ) {
		        /**
		         * The necessary constant does not exist
		         */
		        break;
	        }
		    if ( is_null( constant( $constant_name ) ) ) {
			    /**
			     * The necessary constant has a null value
			     */
			    break;
		    }

		    /**
		     * We have a valid constant, so let's ASSUME it WAS defaulted.
		     */
            $was_defaulted = true;

		    /**
		     * Get the settings from the DB.
		     */
            $settings = (array) get_option( self::SETTINGS_OPTION );

            if ( ! isset( $settings[ $setting_name ] ) ) {
                /**
                 * The setting does not exist it the DB, thus defaulted.
                 */
                break;
            }
		    if ( '' === $settings[ $setting_name ] ) {
			    /**
			     * The setting equals '', thus defaulted.
			     */
			    break;
		    }
		    if ( false === $settings[ $setting_name ] ) {
			    /**
			     * The setting equals false, thus defaulted.
			     */
			    break;
		    }
		    if ( is_null( $settings[ $setting_name ] ) ) {
			    /**
			     * The setting is null, thus defaulted.
			     */
			    break;
		    }
		    /**
		     * The setting has a valid value, thus NOT defaulted.
		     */
		    $was_defaulted = false;

	    } while ( false );

	    return $was_defaulted;

	}

	/**
	 * @return object
	 */
	static function settings() {
		$settings = wp_parse_args(
			(array) get_option( self::SETTINGS_OPTION ),
			array_fill_keys( self::SETTINGS_FIELDS, null )
		);
		/**
		 * Allow constants defined in wp-config to default a
		 * settings. Constant should start with BOX_ and
		 * be the uppercase equivalent of the setting name,
		 *
		 * @example: The constant BOX_EXTERNAL_BASE_UPLOADS_URL
		 *      will default $setting->external_base_uploads_url
		 *      assuming $setting->external_base_uploads_url
		 *      is null.
		 */
		foreach( self::SETTINGS_FIELDS as $setting_name ) {
			$value = $settings[ $setting_name ];
		    if ( is_null( $value ) || "" === $value ) {
			    $settings[ $setting_name ] = self::get_setting_default( $setting_name );
		    }
		}
		return apply_filters( 'box_support:settings', (object) $settings );
	}

	/**
	 * Maybe collect, sanitize and store posted values.
	 * @return bool
	 */
	private static function _update_settings_from_POST() {

		do {

			$updated = false;

			if ( ! isset( $_POST[ 'submit_settings' ] ) ) {
				break;
			}

			if ( ! check_admin_referer( self::SETTINGS_NONCE ) ) {
				break;
			}

			$posted_settings = isset( $_POST[ self::SETTINGS_FORM_FIELD ] )
				? $_POST[ self::SETTINGS_FORM_FIELD ]
				: null;

			if ( is_null( $posted_settings )  ) {
				break;
			}

			$updated = self::update_settings(
				apply_filters( 'box_support:posted_settings', $posted_settings )
			);

		} while ( false );

		return $updated;

	}

	/**
	 * Sanitize and store settings
	 * @return bool
	 */
	static function update_settings( $settings ) {
		do {
			$updated = false;

			if ( ! $settings = self::sanitize_settings( $settings ) ) {
				break;
			}
			if ( ! $settings = apply_filters( 'box_support:update_settings', (object) $settings ) ) {
			    break;
			}
			$updated = update_option( self::SETTINGS_OPTION, (object) $settings );

		} while ( false );

		return $updated;
	}

	/**
	 * Sanitizes and validates settings.
	 *
	 * @param $settings
	 *
	 * @return array
	 */
	static function sanitize_settings( $settings ) {

		$settings = wp_parse_args(
            (array) $settings,
			array_fill_keys( self::SETTINGS_FIELDS, null )
		);

		$settings[ 'external_base_uploads_url' ] = esc_url( $settings[ 'external_base_uploads_url' ] );

		return apply_filters( 'box_support:sanitize_settings', (object) $settings );

	}
	
}

WPLib_Box_Support::on_load();