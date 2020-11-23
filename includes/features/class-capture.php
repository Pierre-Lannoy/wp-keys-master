<?php
/**
 * Keys Master event capture
 *
 * Handles all captures operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin\Feature;

use KeysMaster\System\Logger;
use KeysMaster\System\User;
use KeysMaster\Plugin\Feature\Schema;
use KeysMaster\System\Environment;
use KeysMaster\System\Blog;
use KeysMaster\System\IP;
use KeysMaster\System\Option;
use KeysMaster\System\Hash;
use KeysMaster\System\GeoIP;

/**
 * Define the captures functionality.
 *
 * Handles all captures operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Capture {

	/**
	 * The number of created APs.
	 *
	 * @since  1.0.0
	 * @var    integer    $created    The number of created APs.
	 */
	private static $created = 0;

	/**
	 * The number of revoked APs.
	 *
	 * @since  1.0.0
	 * @var    integer    $revoked    The number of revoked APs.
	 */
	private static $revoked = 0;

	/**
	 * The number of successful logins.
	 *
	 * @since  1.0.0
	 * @var    integer    $login_success    The number of successful logins.
	 */
	private static $login_success = 0;

	/**
	 * The number of failed logins.
	 *
	 * @since  1.0.0
	 * @var    integer    $login_fail    The number of failed logins.
	 */
	private static $login_fail = 0;

	/**
	 * The call usage.
	 *
	 * @since  1.0.0
	 * @var    array    $usage    The call usage.
	 */
	private static $usage = [];

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize the meta class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		add_action( 'wp_create_application_password', [ self::class, 'wp_create_application_password' ], 10, 4 );
		add_action( 'wp_delete_application_password', [ self::class, 'wp_delete_application_password' ], 10, 2 );
		add_action( 'application_password_failed_authentication', [ self::class, 'application_password_failed_authentication' ], 10, 1 );
		add_action( 'application_password_did_authenticate', [ self::class, 'application_password_did_authenticate' ], 10, 2 );
	}

	/**
	 * Initialize the meta class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public static function late_init() {
	}

	/**
	 * Get the statistics.
	 *
	 * @return array The current statistics.
	 * @since    1.0.0
	 */
	public static function get_stats() {
		$result = [];
		if ( 0 < self::$created ) {
			$result['created'] = self::$created;
		}
		if ( 0 < self::$revoked ) {
			$result['revoked'] = self::$revoked;
		}
		if ( 0 < self::$login_success ) {
			$result['success'] = self::$login_success;
		}
		if ( 0 < self::$login_fail ) {
			$result['fail'] = self::$login_fail;
		}
		return $result;
	}

	/**
	 * Get the usage.
	 *
	 * @return array The current usage.
	 * @since    1.0.0
	 */
	public static function get_usage() {
		return self::$usage;
	}

	/**
	 * Set the usage.
	 *
	 * @param   boolean     $success    Optional. Set it as a successful call.
	 * @since    1.0.0
	 */
	private static function set_usage( $success = true ) {
		switch ( Environment::exec_mode() ) {
			case 4:
				self::$usage['channel'] = 'xmlrpc';
				break;
			case 5:
				self::$usage['channel'] = 'api';
				break;
			default:
				self::$usage['channel'] = 'unknown';
		}
		self::$usage['site'] = Blog::get_current_blog_id( 0 );
		$geo_ip              = new GeoIP();
		$country             = $geo_ip->get_iso3166_alpha2( IP::get_current() );
		if ( ! empty( $country ) && 2 === strlen( $country ) ) {
			self::$usage['country'] = $country;
		} else {
			self::$usage['country'] = '00';
		}
		if ( array_key_exists( 'HTTP_USER_AGENT', $_SERVER ) ) {
			self::$usage['device'] = substr( filter_input( INPUT_SERVER, 'HTTP_USER_AGENT' ), 0, 512 );
		} else {
			self::$usage['device'] = '-';
		}
		if ( $success ) {
			self::$usage['success'] = 1;
			self::$usage['fail']    = 0;
		} else {
			self::$usage['success'] = 0;
			self::$usage['fail']    = 1;
		}
	}

	/**
	 * "wp_create_application_password" event.
	 *
	 * @since    1.0.0
	 */
	public static function wp_create_application_password( $user_id, $new_item, $new_password = '', $args = [] ) {
		self::$created ++;
	}

	/**
	 * "wp_delete_application_password" event.
	 *
	 * @since    1.0.0
	 */
	public static function wp_delete_application_password( $user_id, $item ) {
		self::$revoked ++;
	}

	/**
	 * "application_password_failed_authentication" event.
	 *
	 * @since    1.0.0
	 */
	public static function application_password_failed_authentication( $error ) {
		self::set_usage( false );
		self::$login_fail ++;
	}

	/**
	 * "application_password_did_authenticate" event.
	 *
	 * @since    1.0.0
	 */
	public static function application_password_did_authenticate( $user, $item ) {
		self::set_usage( true );
		self::$login_success ++;
	}

}
