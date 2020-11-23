<?php
/**
 * APs handling
 *
 * Handles all APs operations and detection.
 *
 * @package System
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\System;

use Decalog\Plugin\Feature\Log;
use KeysMaster\System\Role;
use KeysMaster\System\Option;
use KeysMaster\System\Logger;
use KeysMaster\System\Hash;
use KeysMaster\System\User;
use KeysMaster\System\Environment;
use KeysMaster\System\GeoIP;
use KeysMaster\System\UserAgent;
use KeysMaster\Plugin\Feature\Schema;
use KeysMaster\Plugin\Feature\Capture;
use KeysMaster\System\IP;

/**
 * Define the APs functionality.
 *
 * Handles all APs operations and detection.
 *
 * @package System
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Password {

	/**
	 * The current user ID.
	 *
	 * @since  1.0.0
	 * @var    integer    $user_id    The current user ID.
	 */
	private $user_id = 0;

	/**
	 * The current user.
	 *
	 * @since  1.0.0
	 * @var    \WP_User    $user    The current user.
	 */
	private $user = null;

	/**
	 * The user's APs.
	 *
	 * @since  1.0.0
	 * @var    array    $passwords    The user's APs.
	 */
	private $passwords = [];

	/**
	 * The class instance.
	 *
	 * @since  1.0.0
	 * @var    $object    $instance    The class instance.
	 */
	private static $instance = null;

	/**
	 * The application passwords meta key name.
	 *
	 * @since  1.0.0
	 * @var    string    $meta_key    The application passwords meta key name.
	 */
	public static $meta_key = '_application_passwords';

	/**
	 * Create an instance.
	 *
	 * @param mixed $user  Optional, the user or user ID.
	 * @since 1.0.0
	 */
	public function __construct( $user = null ) {
		$this->load_user( $user );
	}

	/**
	 * Create an instance.
	 *
	 * @param mixed $user  Optional, the user or user ID.
	 * @since 1.0.0
	 */
	private function load_user( $user = null ) {
		if ( ! isset( $user ) ) {
			$this->user_id = get_current_user_id();
		} else {
			if ( $user instanceof \WP_User ) {
				$this->user_id = $user->ID;
			} elseif ( is_int( $user ) ) {
				$this->user_id = $user;
			} else {
				$this->user_id = 0;
			}
		}
		$this->passwords = self::get_user_passwords( $this->user_id );
		if ( $this->is_needed() ) {
			$this->user = get_user_by( 'id', $this->user_id );
			if ( ! $this->user ) {
				$this->user = null;
			}
		}
	}

	/**
	 * Verify if the instance is needed.
	 *
	 * @return boolean  True if the features are needed, false otherwise.
	 * @since 1.0.0
	 */
	public function is_needed() {
		return is_int( $this->user_id ) && 0 < $this->user_id;
	}

	/**
	 * Verify if APs are available for the user.
	 *
	 * @param bool     $available   Optional. True if available, false otherwise.
	 * @param null|\WP_User $user   Optional. The user to check.
	 * @return boolean  True if AP is available for this user, false otherwise.
	 * @since 1.0.0
	 */
	public function is_available( $available = true, $user = null ) {
		if ( ! isset( $user ) ) {
			$user = $this->user;
		}
		if ( $user instanceof \WP_User && 0 < $user->ID ) {
			$privileges = $this->get_privileges_for_user( $user->ID );
			if ( ! isset( $privileges['modes']['allow'] ) || 'none' === $privileges['modes']['allow'] ) {
				return false;
			}
		}
		return $available;
	}

	/**
	 * Get the number of APs.
	 *
	 * @return integer  The number of APs.
	 * @since 1.0.0
	 */
	public function get_opkm_count() {
		if ( isset( $this->passwords ) ) {
			return count( $this->passwords );
		}
		return 0;
	}

	/**
	 * Get the user id.
	 *
	 * @return integer  The user id.
	 * @since 1.0.0
	 */
	public function get_user_id() {
		return $this->user_id;
	}

	/**
	 * Limits AP creation if needed.
	 *
	 * @param bool      $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  ID of the object metadata is for.
	 * @param string    $meta_key   Metadata key.
	 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param mixed     $prev_value Optional. Previous value to check before updating.
	 *                              If specified, only update existing metadata entries with
	 *                              this value. Otherwise, update all entries.
	 * @return boolean  True if allowed, false otherwise.
	 */
	public function limit_management( $check, $object_id, $meta_key, $meta_value, $prev_value = '' ) {
		if ( self::$meta_key === $meta_key ) {
			if ( is_array( $meta_value ) ) {
				$privileges = $this->get_privileges_for_user( $object_id );
				$old        = get_user_meta( $object_id, self::$meta_key, true );
				if ( isset( $old ) && count( $old ) > count( $meta_value ) ) {
					return $check;
				}
				if ( count( $meta_value ) > $privileges['modes']['maxap'] ) {
					return false;
				}
				if ( 'full' !== $privileges['modes']['allow'] ) {
					return false;
				}
			}
		}
		return $check;
	}

	/**
	 * Computes privileges for a set of roles.
	 *
	 * @param array     $roles  The set of roles for which the privileges must be computed.
	 * @return array    The privileges.
	 * @since 2.0.0
	 */
	private function get_privileges_for_roles( $roles ) {
		$result   = [];
		$settings = Option::roles_get();
		if ( 0 === (int) Option::network_get( 'rolemode' ) ) { // Cumulative privileges.
			$allow = 'none';
			$maxap = 0;
			$idle  = -1;
			foreach ( $roles as $role ) {
				// Allowed usages
				switch ( $settings[ $role ]['allow'] ) {
					case 'full':
						$allow = 'full';
						break;
					case 'limited':
						if ( 'none' === $allow ) {
							$allow = 'limited';
						}
						break;
					case 'none':
						// no downscale with "cumulative privileges"
						break;
				}
				// Max number of APs
				if ( $settings[ $role ]['maxap'] > $maxap ) {
					$maxap = $settings[ $role ]['maxap'];
				}
				// Max idle days
				if ( 0 === $settings[ $role ]['idle'] ) {
					$idle = 0;
				} elseif ( $settings[ $role ]['idle'] > $idle && 0 !== $idle ) {
					$idle = $settings[ $role ]['idle'];
				}
			}
		} else { // Least privileges.
			$allow = 'full';
			$maxap = PHP_INT_MAX;
			$idle  = PHP_INT_MAX;
			foreach ( $roles as $role ) {
				// Allowed usages
				switch ( $settings[ $role ]['allow'] ) {
					case 'none':
						$allow = 'none';
						break;
					case 'limited':
						if ( 'full' === $allow ) {
							$allow = 'limited';
						}
						break;
					case 'full':
						// no upscale with "least privileges"
						break;
				}
				// Max number of APs
				if ( $settings[ $role ]['maxap'] < $maxap ) {
					$maxap = $settings[ $role ]['maxap'];
				}
				// Max idle days
				if ( $settings[ $role ]['idle'] < $idle && 0 !== $settings[ $role ]['idle'] ) {
					$idle = $settings[ $role ]['idle'];
				} elseif ( 0 === $settings[ $role ]['idle'] && PHP_INT_MAX === $idle ) {
					$idle = 0;
				}
			}
		}
		$modes['allow']  = $allow;
		$modes['maxap']  = $maxap;
		$modes['idle']   = $idle;
		$result['roles'] = $roles;
		$result['modes'] = $modes;
		return $result;
	}

	/**
	 * Computes privileges for a user.
	 *
	 * @param null|integer   $user_id         Optional. The user for who the privileges must be computed.
	 * @return array    The privileges.
	 * @since 2.0.0
	 */
	public function get_privileges_for_user( $user_id = null ) {
		if ( isset( $user_id ) ) {
			$user = get_userdata( $user_id );
		} else {
			$user    = $this->user;
			$user_id = $user->ID;
		}
		if ( Role::SUPER_ADMIN === Role::admin_type( $user_id ) || Role::SINGLE_ADMIN === Role::admin_type( $user_id ) || Role::LOCAL_ADMIN === Role::admin_type( $user_id ) ) {
			$roles[] = 'administrator';
		} else {
			foreach ( Role::get_all() as $key => $detail ) {
				if ( in_array( $key, $user->roles, true ) ) {
					$roles[] = $key;
					break;
				}
			}
		}
		return $this->get_privileges_for_roles( $roles );
	}

	/**
	 * Get the limits as printable text.
	 *
	 * @param null|integer   $user_id         Optional. The user for who the privileges must be computed.
	 * @return string  The limits, ready to print.
	 * @since 1.0.0
	 */
	public function get_limits_as_self_text( $user_id = null ) {
		if ( ! isset( $user_id ) ) {
			$user_id = $this->user->ID;
		}
		$privileges = $this->get_privileges_for_user( $user_id );
		if ( 'full' === $privileges['modes']['allow'] ) {
			$text = '';
			if ( 1000 > $privileges['modes']['maxap'] ) {
				$text = sprintf( esc_html__( 'You can manage up to %d passwords.', 'keys-master' ), $privileges['modes']['maxap'] );
			}
			if ( 0 < $privileges['modes']['idle'] ) {
				$text .= ( '' === $text ? '' : ' ' ) . sprintf( esc_html__( 'Passwords are revoked after %d days of inactivity.', 'keys-master' ), $privileges['modes']['idle'] );
			}
			if ( '' !== $text ) {
				$result  = '<script>';
				$result .= 'jQuery(document).ready( function($) {';
				$result .= "$('.application-passwords p:eq(0)').after('" . $text . "');";
				$result .= '});';
				$result .= '</script>';
			}

		} else {
			$text    = '<p>' . esc_html__( 'To create an application password, please, ask your administrator.', 'keys-master' ) ;
			$result  = '<script>';
			$result .= 'jQuery(document).ready( function($) {';
			if ( Role::SUPER_ADMIN !== Role::admin_type() && Role::SINGLE_ADMIN !== Role::admin_type() ) {
				$result .= "$('.create-application-password').hide();";
			}
			$result .= "$('.application-passwords p:eq(0)').after('" . $text . "');";
			$result .= '});';
			$result .= '</script>';
		}
		return $result;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		add_action( 'init', [ self::class, 'initialize' ], PHP_INT_MAX );
	}

	/**
	 * Initialize static properties.
	 *
	 * @since    1.0.0
	 */
	public static function initialize() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
		}
		if ( -1 !== (int) Option::network_get( 'rolemode' ) ) {
			add_action( 'update_user_metadata', [ self::$instance, 'limit_management' ], PHP_INT_MAX, 5 );
			add_filter( 'wp_is_application_passwords_available_for_user', [ self::$instance, 'is_available' ], 10, 2 );
		}
	}

	/**
	 * Get APs.
	 *
	 * @param   mixed $user_id  Optional. The user ID.
	 * @return  array  The list of APs.
	 * @since   1.0.0
	 */
	public static function get_user_passwords( $user_id = false ) {
		$result = [];
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id || ! ( is_int( $user_id ) && 0 < $user_id ) ) {
			return $result;
		}
		return \WP_Application_Passwords::get_user_application_passwords( $user_id );
	}

	/**
	 * Get APs.
	 *
	 * @param   string $uuid  The password UUID.
	 * @return  array  The list of APs.
	 * @since   1.0.0
	 */
	public static function get_uuid_passwords( $uuid ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . $wpdb->usermeta . " WHERE meta_key = '" . self::$meta_key . "' AND meta_value LIKE '%" . $uuid . "%'ORDER BY user_id DESC LIMIT " . (int) Option::network_get( 'buffer_limit' );
		// phpcs:ignore
		$result = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $result as &$record ) {
			if ( ! is_array( $record['meta_value'] ) && is_string( $record['meta_value'] ) ) {
				$record['meta_value'] = maybe_unserialize( $record['meta_value'] );
			}
		}
		return $result;
	}

	/**
	 * Get all APs.
	 *
	 * @return  array  The details of APs.
	 * @since   1.0.0
	 */
	public static function get_all_passwords() {
		global $wpdb;
		$sql = 'SELECT * FROM ' . $wpdb->usermeta . " WHERE meta_key = '" . self::$meta_key . "' ORDER BY user_id DESC LIMIT " . (int) Option::network_get( 'buffer_limit' );
		// phpcs:ignore
		$result = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $result as &$record ) {
			if ( ! is_array( $record['meta_value'] ) && is_string( $record['meta_value'] ) ) {
				$record['meta_value'] = maybe_unserialize( $record['meta_value'] );
			}
		}
		return $result;
	}

	/**
	 * Set APs.
	 *
	 * @param   string   $password The password uuid.
	 * @param   mixed   $user_id  Optional. The user ID.
	 * @return  boolean   True if the operation was successful, false otherwise.
	 * @since   1.0.0
	 */
	public static function delete_user_password( $password, $user_id = false ) {
		$result = false;
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id || ! is_int( $user_id ) ) {
			return $result;
		}
		return true === \WP_Application_Passwords::delete_application_password( $user_id, $password );
	}

	/**
	 * Revokes passwords needing it.
	 *
	 * @param   array   $passwords The APs records.
	 * @param   integer   $user_id  The user ID.
	 * @return  integer   Number of revoked passwords.
	 * @since   1.0.0
	 */
	public static function auto_revoke_password( $passwords, $user_id ) {
		$privileges = self::$instance->get_privileges_for_user( $user_id );
		if ( isset( $privileges['modes']['idle'] ) ) {
			$idle = (int) $privileges['modes']['idle'];
		}
		$del = [];
		if ( 0 < $idle ) {
			$stop = time() - ( $idle * DAY_IN_SECONDS );
			foreach ( $passwords as $password ) {
				if ( isset( $password['last_used'] ) ) {
					if ( $password['last_used'] < $stop ) {
						$del[] = $password['uuid'];
					}
				} elseif ( isset( $password['created'] ) ) {
					if ( $password['created'] < $stop ) {
						$del[] = $password['uuid'];
					}
				}
			}
		}
		foreach ( $del as $uuid ) {
			self::delete_user_password( $uuid, $user_id );
			do_action( 'opkm_after_idle_terminate', $user_id );
		}
		return count( $del );
	}

	/**
	 * Delete selected APs.
	 *
	 * @param array   $bulk   The APs to delete.
	 * @return int|bool False if it was not possible, otherwise the number of deleted meta.
	 * @since    1.0.0
	 */
	public static function delete_selected_passwords( $bulk ) {
		if ( Role::SUPER_ADMIN === Role::admin_type() || Role::SINGLE_ADMIN === Role::admin_type() ) {
			$count = 0;
			foreach ( $bulk as $id ) {
				$val = explode( ':', $id );
				if ( 2 === count( $val ) ) {
					if ( self::delete_user_password( (string) $val[1], (int) $val[0] ) ) {
						++$count;
					}
				}
			}
			if ( 0 === $count ) {
				Logger::notice( 'No passwords to revoke.' );
			} else {
				do_action( 'opkm_force_admin_terminate', $count );
				Logger::notice( sprintf( 'All selected passwords have been revoked (%d revoked passwords).', $count ) );
			}
			return $count;
		} else {
			Logger::alert( 'A non authorized user attempted to revoke some passwords.' );
			return false;
		}
	}

}
