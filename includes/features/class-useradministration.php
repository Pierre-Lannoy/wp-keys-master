<?php
/**
 * User admin handling.
 *
 * Handles all user admin operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin\Feature;
use KeysMaster\System\Password;

/**
 * Define the user admin functionality.
 *
 * Handles all user admin operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class UserAdministration {

	/**
	 * Initialize the meta class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		add_action( 'wp_create_application_password_form', [ self::class, 'user_profile' ], PHP_INT_MAX, 1 );
	}

	/**
	 * Echo the APs section of the user profile.
	 *
	 * @param \WP_User  $user   The user.
	 * @since    1.0.0
	 */
	public static function user_profile( $user ) {
		$password = new Password( $user );
		if ( $password->is_needed() && $password->is_available() ) {
			echo $password->get_limits_as_self_text();
		}
	}

}