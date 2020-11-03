<?php
/**
 * Session handling
 *
 * Handles all session operations and detection.
 *
 * @package System
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\System;

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
use KeysMaster\Plugin\Feature\LimiterTypes;
use KeysMaster\System\IP;

/**
 * Define the session functionality.
 *
 * Handles all session operations and detection.
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
	 * The user's distinct APs IP.
	 *
	 * @since  1.1.0
	 * @var    array    $ip    The user's distinct APs IP.
	 */
	private $ip = [];

	/**
	 * The current token.
	 *
	 * @since  1.0.0
	 * @var    string    $token    The current token.
	 */
	private $token = '';

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
	 * Verify if the ip range is allowed.
	 *
	 * @param string  $block The ip block ode.
	 * @return string 'allow' or 'disallow'.
	 * @since 1.0.0
	 */
	private function verify_ip_range( $block ) {
		if ( ! in_array( $block, [ 'none', 'external', 'local' ], true ) ) {
			Logger::warning( 'IP range limitation set to "Allow For All".', 202 );
			return 'allow';
		}
		if ( 'none' === $block ) {
			return 'allow';
		}
		if ( 'external' === $block && IP::is_current_private() ) {
			return 'allow';
		}
		if ( 'local' === $block && IP::is_current_public() ) {
			return 'allow';
		}
		return 'disallow';
	}

	/**
	 * Verify if the max number of ip.
	 *
	 * @param integer  $maxip The ip max number.
	 * @return string 'allow' or 'disallow'.
	 * @since 1.1.0
	 */
	private function verify_ip_max( $maxip ) {
		if ( 0 === $maxip || in_array( IP::get_current(), $this->ip, true ) ) {
			return 'allow';
		}
		if ( $maxip > count( $this->ip ) ) {
			return 'allow';
		}
		return 'disallow';
	}

	/**
	 * Verify if the maximum allowed is reached.
	 *
	 * @param integer  $limit The maximum allowed.
	 * @return string 'allow' or the token of the overridable if maximum is reached.
	 * @since 1.0.0
	 */
	private function verify_per_user_limit( $limit ) {
		if ( is_array( $this->passwords ) && $limit > count( $this->passwords ) ) {
			return 'allow';
		}
		if ( ! is_array( $this->passwords ) ) {
			return 'allow';
		}
		uasort(
			$this->passwords,
			function ( $a, $b ) {
				if ( $a['login'] === $b['login'] ) {
					return 0;
				} return ( $a['login'] < $b['login'] ) ? -1 : 1;
			}
		);
		if ( $limit < count( $this->passwords ) ) {
			$this->passwords = array_slice( $this->passwords, 1 );
			do_action( 'opkm_force_terminate', $this->user_id );
			self::delete_user_password( $this->passwords, $this->user_id );
			return $this->verify_per_user_limit( $limit );
		}
		return array_key_first( $this->passwords );
	}

	/**
	 * Verify if the maximum allowed is reached.
	 *
	 * @param integer  $limit The maximum allowed.
	 * @return string 'allow' or the token of the overridable if maximum is reached.
	 * @since 1.0.0
	 */
	private function verify_per_ip_limit( $limit ) {
		if ( ! is_array( $this->passwords ) ) {
			return 'allow';
		}
		$ip      = IP::get_current();
		$compare = [];
		$buffer  = [];
		foreach ( $this->passwords as $token => $session ) {
			if ( IP::expand( $session['ip'] ) === $ip ) {
				$compare[ $token ] = $session;
			} else {
				$buffer[ $token ] = $session;
			}
		}
		if ( $limit > count( $compare ) ) {
			return 'allow';
		}
		uasort(
			$compare,
			function ( $a, $b ) {
				if ( $a['login'] === $b['login'] ) {
					return 0;
				} return ( $a['login'] < $b['login'] ) ? -1 : 1;
			}
		);
		if ( $limit < count( $compare ) ) {
			$compare = array_slice( $compare, 1 );
			do_action( 'opkm_force_terminate', $this->user_id );
			$this->passwords = array_merge( $compare, $buffer );
			self::delete_user_password( $this->passwords, $this->user_id );
			return $this->verify_per_user_limit( $limit );
		}
		return array_key_first( $compare );
	}

	/**
	 * Verify if the maximum allowed is reached.
	 *
	 * @param integer  $limit The maximum allowed.
	 * @return string 'allow' or the token of the overridable if maximum is reached.
	 * @since 1.0.0
	 */
	private function verify_per_country_limit( $limit ) {
		if ( ! is_array( $this->passwords ) ) {
			return 'allow';
		}
		$ip      = IP::get_current();
		$geo     = new GeoIP();
		$country = $geo->get_iso3166_alpha2( $ip );
		$compare = [];
		$buffer  = [];
		foreach ( $this->passwords as $token => $session ) {
			if ( $country === $geo->get_iso3166_alpha2( $session['ip'] ) ) {
				$compare[ $token ] = $session;
			} else {
				$buffer[ $token ] = $session;
			}
		}
		if ( $limit > count( $compare ) ) {
			return 'allow';
		}
		uasort(
			$compare,
			function ( $a, $b ) {
				if ( $a['login'] === $b['login'] ) {
					return 0;
				} return ( $a['login'] < $b['login'] ) ? -1 : 1;
			}
		);
		if ( $limit < count( $compare ) ) {
			$compare = array_slice( $compare, 1 );
			do_action( 'opkm_force_terminate', $this->user_id );
			$this->passwords = array_merge( $compare, $buffer );
			self::delete_user_password( $this->passwords, $this->user_id );
			return $this->verify_per_user_limit( $limit );
		}
		return array_key_first( $compare );
	}

	/**
	 * Verify if the maximum allowed is reached.
	 *
	 * @param string   $ua The user agent.
	 * @param string   $selector The selector ('device-class', 'device-type', 'device-client',...).
	 * @return string The requested ID.
	 * @since 1.0.0
	 */
	private function get_device_id( $ua, $selector ) {
		$device = UserAgent::get( $ua );
		switch ( $selector ) {
			case 'device-class':
				if ( $device->class_is_bot ) {
					return 'bot';
				}
				if ( $device->class_is_mobile ) {
					return 'mobile';
				}
				if ( $device->class_is_desktop ) {
					return 'desktop';
				}
				return 'other';
			case 'device-type':
				if ( $device->device_is_smartphone ) {
					return 'smartphone';
				}
				if ( $device->device_is_featurephone ) {
					return 'featurephone';
				}
				if ( $device->device_is_tablet ) {
					return 'tablet';
				}
				if ( $device->device_is_phablet ) {
					return 'phablet';
				}
				if ( $device->device_is_console ) {
					return 'console';
				}
				if ( $device->device_is_portable_media_player ) {
					return 'portable-media-player';
				}
				if ( $device->device_is_car_browser ) {
					return 'car-browser';
				}
				if ( $device->device_is_tv ) {
					return 'tv';
				}
				if ( $device->device_is_smart_display ) {
					return 'smart-display';
				}
				if ( $device->device_is_camera ) {
					return 'camera';
				}
				return 'other';
			case 'device-client':
				if ( $device->client_is_browser ) {
					return 'browser';
				}
				if ( $device->client_is_feed_reader ) {
					return 'feed-reader';
				}
				if ( $device->client_is_mobile_app ) {
					return 'mobile-app';
				}
				if ( $device->client_is_pim ) {
					return 'pim';
				}
				if ( $device->client_is_library ) {
					return 'library';
				}
				if ( $device->client_is_media_player ) {
					return 'media-payer';
				}
				return 'other';
			case 'device-browser':
				return $device->client_short_name;
			case 'device-os':
				return $device->os_short_name;
		}
		return '';
	}

	/**
	 * Verify if the maximum allowed is reached.
	 *
	 * @param string   $selector The selector ('device-class', 'device-type', 'device-client',...).
	 * @param integer  $limit    The maximum allowed.
	 * @return string 'allow' or the token of the overridable if maximum is reached.
	 * @since 1.0.0
	 */
	private function verify_per_device_limit( $selector, $limit ) {
		if ( ! is_array( $this->passwords ) ) {
			return 'allow';
		}
		$device  = $this->get_device_id( '', $selector );
		$compare = [];
		$buffer  = [];
		foreach ( $this->passwords as $token => $session ) {
			if ( $device === $this->get_device_id( $session['ua'], $selector ) ) {
				$compare[ $token ] = $session;
			} else {
				$buffer[ $token ] = $session;
			}
		}
		if ( $limit > count( $compare ) ) {
			return 'allow';
		}
		uasort(
			$compare,
			function ( $a, $b ) {
				if ( $a['login'] === $b['login'] ) {
					return 0;
				} return ( $a['login'] < $b['login'] ) ? -1 : 1;
			}
		);
		if ( $limit < count( $compare ) ) {
			$compare = array_slice( $compare, 1 );
			do_action( 'opkm_force_terminate', $this->user_id );
			$this->passwords = array_merge( $compare, $buffer );
			self::delete_user_password( $this->passwords, $this->user_id );
			return $this->verify_per_user_limit( $limit );
		}
		return array_key_first( $compare );
	}

	/**
	 * Enforce AP limitation if needed.
	 *
	 * @param string  $message  The error message.
	 * @param integer $error    The error code.
	 * @since 1.0.0
	 */
	private function die( $message, $error ) {
		Capture::login_block( $this->user_id );
		wp_die( $message, $error );
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
		$roles    = [];
		$modes    = [];
		$method   = 'block';
		$settings = Option::roles_get();

		$allow = 'none';
		$maxip = 0;
		foreach ( $roles as $role ) {
			// Allowed IP type
			switch ( $settings[ $role ]['block'] ) {
				case 'none':
					$allow = 'all';
					break;
				case 'external':
					if ( 'local' === $allow ) {
						$allow = 'all';
					} elseif ( 'none' === $allow ) {
						$allow = 'external';
					}
					break;
				case 'local':
					if ( 'external' === $allow ) {
						$allow = 'all';
					} elseif ( 'none' === $allow ) {
						$allow = 'local';
					}
					break;
			}
			if ( 0 === (int) Option::network_get( 'rolemode' ) ) { // Cumulative privileges.

				if ( array_key_exists( $role, $settings ) ) {
					if ( 'none' === $settings[ $role ]['limit'] ) {
						$mode  = 'none';
						$l = PHP_INT_MAX;
					} else {
						foreach ( LimiterTypes::$selector_names as $key => $name ) {
							if ( 0 === strpos( $settings[ $role ]['limit'], $key ) ) {
								$mode  = $key;
								$limit = (int) substr( $settings[ $role ]['limit'], strlen( $key ) + 1 );
								break;
							}
						}
					}
				}
			} else { // Least privileges.
			}

		}
		if ( 'none' === $allow ) {
			Logger::critical( sprintf( 'Misconfiguration: user ID %s not allowed to connect from private or public IP ranges. Temporarily set to "allow=all". Please fix it!', $user->ID ), 666 );
			$allow = 'all';
		}
		$modes['allow'] = $allow;
		if ( 0 === $maxip ) {
			Logger::critical( sprintf( 'Misconfiguration: user ID %s not allowed to connect because ip-quota is set to 0. Temporarily set to "ip-quota=max". Please fix it!', $user->ID ), 666 );
			$maxip = PHP_INT_MAX;
		}
		$result['roles']  = $roles;
		$result['modes']  = $modes;
		$result['method'] = $method;
		return $result;
	}

	/**
	 * Computes privileges for a user.
	 *
	 * @param integer   $user_id         The user for who the privileges must be computed.
	 * @return array    The privileges.
	 * @since 2.0.0
	 */
	public function get_privileges_for_user( $user_id ) {
		if ( Role::SUPER_ADMIN === Role::admin_type( $user_id ) || Role::SINGLE_ADMIN === Role::admin_type( $user_id ) || Role::LOCAL_ADMIN === Role::admin_type( $user_id ) ) {
			$roles[] = 'administrator';
		} else {
			foreach ( Role::get_all() as $key => $detail ) {
				if ( in_array( $key, $this->user->roles, true ) ) {
					$roles[] = $key;
					break;
				}
			}
		}
		return $this->get_privileges_for_roles( $roles );
	}

	/**
	 * Enforce AP limitation if needed.
	 *
	 * @param mixed   $user         WP_User if the user is authenticated, WP_Error or null otherwise.
	 * @param string  $username     Username or email address.
	 * @param string  $password     User password.
	 * @param boolean $force_403    Optional. Force a 403 error if needed (in place of 'default' method).
	 * @return mixed WP_User if the user is allowed, WP_Error or null otherwise.
	 * @since 1.0.0
	 */
	public function limit_logins( $user, $username, $password, $force_403 = false ) {
		if ( -1 === (int) Option::network_get( 'rolemode' ) ) {
			return $user;
		}
		if ( $user instanceof \WP_User ) {
			$this->user_id   = $user->ID;
			$this->user      = $user;
			$this->passwords = self::get_user_passwords( $this->user_id );
			$role            = '';
			$this->ip        = [];
			foreach ( $this->passwords as $session ) {
				$ip = IP::expand( $session['ip'] );
				if ( ! in_array( $ip, $this->ip, true ) ) {
					$this->ip[] = $ip;
				}
			}
			foreach ( Role::get_all() as $key => $detail ) {
				if ( in_array( $key, $this->user->roles, true ) ) {
					$role = $key;
					break;
				}
			}
			$settings = Option::roles_get();
			if ( array_key_exists( $role, $settings ) ) {
				$method = $settings[ $role ]['method'];
				$mode   = '';
				$limit  = 0;
				if ( 'none' === $settings[ $role ]['limit'] ) {
					$mode  = 'none';
					$limit = PHP_INT_MAX;
				} else {
					foreach ( LimiterTypes::$selector_names as $key => $name ) {
						if ( 0 === strpos( $settings[ $role ]['limit'], $key ) ) {
							$mode  = $key;
							$limit = (int) substr( $settings[ $role ]['limit'], strlen( $key ) + 1 );
							break;
						}
					}
				}
				if ( '' === $mode || 0 === $limit ) {
					if ( 1 === (int) Option::network_get( 'rolemode' ) ) {
						Logger::alert( sprintf( 'No session policy found for %s.', User::get_user_string( $this->user_id ) ), 500 );
						$this->die( __( '<strong>ERROR</strong>: ', 'keys-master' ) . apply_filters( 'internal_error_message', __( 'Something went wrong, it is not possible to continue.', 'keys-master' ) ), 500 );
					} else {
						Logger::critical( sprintf( 'No session policy found for %s.', User::get_user_string( $this->user_id ) ), 202 );
					}
				} else {
					if ( ! LimiterTypes::is_selector_available( $mode ) ) {
						Logger::critical( sprintf( 'No matching session policy for %s.', User::get_user_string( $this->user_id ) ), 202 );
						Logger::warning( sprintf( 'Session policy for %1%s downgraded from "%2$s" to "No limit".', User::get_user_string( $this->user_id ), sprintf( '%d session(s) per user and per %s', User::get_user_string( $this->user_id ), str_replace( '-', ' ', $mode ) ) ), 202 );
						$mode = 'none';
					}
					$result = $this->verify_ip_range( $settings[ $role ]['block'] );
					if ( 'allow' === $result ) {
						$result = $this->verify_ip_max( (int) $settings[ $role ]['maxip'] );
					}
					if ( 'allow' === $result ) {
						switch ( $mode ) {
							case 'none':
								$result = 'allow';
								break;
							case 'user':
								$result = $this->verify_per_user_limit( $limit );
								break;
							case 'ip':
								$result = $this->verify_per_ip_limit( $limit );
								break;
							case 'country':
								$result = $this->verify_per_country_limit( $limit );
								break;
							case 'device-class':
							case 'device-type':
							case 'device-client':
							case 'device-browser':
							case 'device-os':
								$result = $this->verify_per_device_limit( $mode, $limit );
								break;
							default:
								if ( 1 === (int) Option::network_get( 'rolemode' ) ) {
									Logger::alert( 'Unknown session policy.', 501 );
									$this->die( __( '<strong>ERROR</strong>: ', 'keys-master' ) . apply_filters( 'internal_error_message', __( 'Something went wrong, it is not possible to continue.', 'keys-master' ) ), 501 );
								} else {
									Logger::critical( 'Unknown session policy.', 202 );
									$result = 'allow';
									Logger::debug( sprintf( 'New session allowed for %s.', User::get_user_string( $this->user_id ) ), 200 );
								}
						}
					} else {
						Logger::warning( sprintf( 'New session not allowed for %s. Reason: IP range or max used IP.', User::get_user_string( $this->user_id ) ), 403 );
						$this->die( __( '<strong>FORBIDDEN</strong>: ', 'keys-master' ) . apply_filters( 'opkm_bad_ip_message', __( 'You\'re not allowed to initiate a new session from your current IP address.', 'keys-master' ) ), 403 );
					}
					if ( 'allow' !== $result ) {
						if ( $force_403 && 'default' === $method ) {
							$method = 'forced_403';
						}
						switch ( $method ) {
							case 'override':
								if ( '' !== $result ) {
									if ( array_key_exists( $result, $this->passwords) ) {
										unset( $this->passwords[ $result ] );
										do_action( 'opkm_force_terminate', $this->user_id );
										self::delete_user_password( $this->passwords, $this->user_id );
										Logger::notice( sprintf( 'Session overridden for %s. Reason: %s.', User::get_user_string( $this->user_id ), str_replace( 'device-', ' ', $mode ) ) );
									}
								}
								break;
							case 'default':
								Logger::warning( sprintf( 'New session not allowed for %s. Reason: %s.', User::get_user_string( $this->user_id ), str_replace( 'device-', ' ', $mode ) ), 403 );
								Capture::login_block( $this->user_id, true );
								return new \WP_Error( '403', __( '<strong>ERROR</strong>: ', 'keys-master' ) . apply_filters( 'opkm_blocked_message', __( 'You\'re not allowed to initiate a new session because your maximum number of active sessions has been reached.', 'keys-master' ) ) );
							default:
								Logger::warning( sprintf( 'New session not allowed for %s. Reason: %s.', User::get_user_string( $this->user_id ), str_replace( 'device-', ' ', $mode ) ), 403 );
								$this->die( __( '<strong>FORBIDDEN</strong>: ', 'keys-master' ) . apply_filters( 'opkm_blocked_message', __( 'You\'re not allowed to initiate a new session because your maximum number of active sessions has been reached.', 'keys-master' ) ), 403 );
						}
					} else {
						Logger::debug( sprintf( 'New session allowed for %s.', User::get_user_string( $this->user_id ) ), 200 );
					}
				}
			}
		}
		return $user;
	}

	/**
	 * Set the idle field if needed.
	 *
	 * @return boolean  True if the features are needed, false otherwise.
	 * @since 1.0.0
	 */
	private function set_idle() {
		if ( ! $this->is_needed() || ! isset( $this->user ) ) {
			return false;
		}
		if ( ! array_key_exists( $this->token, $this->passwords ) ) {
			return false;
		}
		$role = '';
		foreach ( Role::get_all() as $key => $detail ) {
			if ( in_array( $key, $this->user->roles, true ) ) {
				$role = $key;
				break;
			}
		}
		$settings = Option::roles_get();
		if ( ! array_key_exists( $role, $settings ) ) {
			return false;
		}
		if ( 0 === (int) $settings[ $role ]['idle'] ) {
			if ( array_key_exists( 'session_idle', $this->passwords[ $this->token ] ) ) {
				unset( $this->passwords[ $this->token ]['session_idle'] );
				self::delete_user_password( $this->passwords, $this->user_id );
			}
			return false;
		}
		$this->passwords[ $this->token ]['session_idle'] = time() + (int) $settings[ $role ]['idle'] * HOUR_IN_SECONDS;
		self::delete_user_password( $this->passwords, $this->user_id );
		return true;
	}

	/**
	 * Set the ip field if needed.
	 *
	 * @return boolean  True if the features are needed, false otherwise.
	 * @since 1.0.0
	 */
	private function set_ip() {
		if ( ! Option::network_get( 'followip' ) ) {
			return false;
		}
		if ( ! $this->is_needed() || ! isset( $this->user ) ) {
			return false;
		}
		if ( ! array_key_exists( $this->token, $this->passwords ) ) {
			return false;
		}
		$this->passwords[ $this->token ]['ip'] = IP::expand( $_SERVER['REMOTE_ADDR'] );
		self::delete_user_password( $this->passwords, $this->user_id );
		return true;
	}

	/**
	 * Get the limits as printable text.
	 *
	 * @return string  The limits, ready to print.
	 * @since 1.0.0
	 */
	public function get_limits_as_text() {
		$result = '';
		$role   = '';
		foreach ( Role::get_all() as $key => $detail ) {
			if ( in_array( $key, $this->user->roles, true ) ) {
				$role = $key;
				break;
			}
		}
		$settings = Option::roles_get();
		if ( array_key_exists( $role, $settings ) ) {
			if ( 'external' === $settings[ $role ]['block'] ) {
				$result .= esc_html__( 'Login allowed only from private IP ranges.', 'keys-master' );
			} elseif ( 'local' === $settings[ $role ]['block'] ) {
				$result .= esc_html__( 'Login allowed only from public IP ranges.', 'keys-master' );
			}
			$method = $settings[ $role ]['method'];
			$mode   = '';
			$limit  = 0;
			if ( 'none' === $settings[ $role ]['limit'] ) {
				$mode = 'none';
			} else {
				foreach ( LimiterTypes::$selector_names as $key => $name ) {
					if ( 0 === strpos( $settings[ $role ]['limit'], $key ) ) {
						$mode  = $key;
						$limit = (int) substr( $settings[ $role ]['limit'], strlen( $key ) + 1 );
						break;
					}
				}
			}
			$r = '';
			switch ( $mode ) {
				case 'user':
					$r = esc_html( sprintf( _n( '%d concurrent session.', '%d concurrent sessions.', $limit, 'keys-master' ), $limit ) );
					break;
				case 'ip':
				case 'country':
				case 'device-class':
				case 'device-type':
				case 'device-client':
				case 'device-browser':
				case 'device-os':
					$r = esc_html( sprintf( _n( '%d concurrent session per %s.', '%d concurrent sessions per %s.', $limit, 'keys-master' ), $limit, LimiterTypes::$selector_names[ $mode ] ) );
					break;
			}
			if ( '' !== $r ) {
				if ( '' !== $result ) {
					$result .= ' ';
				}
				$result .= $r;
			}
			if ( 0 !== (int) $settings[ $role ]['idle'] ) {
				$r = esc_html( sprintf( _n( 'Sessions expire after %d hour of inactivity.', 'Sessions expire after %d hours of inactivity.', $settings[ $role ]['idle'], 'keys-master' ), $settings[ $role ]['idle'] ) );
				if ( '' !== $r ) {
					if ( '' !== $result ) {
						$result .= ' ';
					}
					$result .= $r;
				}
			}
		}
		if ( '' === $result ) {
			$result = esc_html__( 'No restrictions.', 'keys-master' );
		}
		return $result;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		if ( Option::network_get( 'forceip' ) ) {
			$_SERVER['REMOTE_ADDR'] = IP::get_current();
		}
		add_action( 'init', [ self::class, 'initialize' ], PHP_INT_MAX );
	}

	/**
	 * Initialize properties if needed.
	 *
	 * @since    1.0.0
	 */
	public function init_if_needed() {
		if ( $this->is_needed() ) {
			$this->token = Hash::simple_hash( wp_get_session_token(), false );
			$this->set_idle();
			$this->set_ip();
		}
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
		self::$instance->init_if_needed();
		add_action( 'updated_user_meta', 'change_user_nickname', 20, 4 );
		//add_filter( 'auth_cookie_expiration', [ self::$instance, 'cookie_expiration' ], PHP_INT_MAX, 3 );
		//add_filter( 'authenticate', [ self::$instance, 'limit_logins' ], PHP_INT_MAX, 3 );
		//add_filter( 'jetpack_sso_handle_login', [ self::$instance, 'jetpack_sso_handle_login' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Get sessions.
	 *
	 * @param   mixed $user_id  Optional. The user ID.
	 * @return  array  The list of sessions.
	 * @since   1.0.0
	 */
	public static function get_user_passwords( $user_id = false ) {
		$result = [];
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id || ! is_int( $user_id ) ) {
			return $result;
		}
		return \WP_Application_Passwords::get_user_application_passwords( $user_id );
	}

	/**
	 * Get all sessions.
	 *
	 * @return  array  The details of sessions.
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
	 * Set sessions.
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
	 * Terminate sessions needing to be terminated.
	 *
	 * @param   array   $sessions The sessions records.
	 * @param   integer   $user_id  The user ID.
	 * @return  integer   Number of terminated sessions.
	 * @since   1.0.0
	 */
	public static function auto_terminate_session( $sessions, $user_id ) {
		$idle = [];
		$exp  = [];
		foreach ( $sessions as $token => $session ) {
			if ( array_key_exists( 'session_idle', $session ) && time() > $session['session_idle'] ) {
				$idle[] = $token;
			} elseif ( array_key_exists( 'expiration', $session ) && time() > $session['expiration'] ) {
				$exp[] = $token;
			}
		}
		foreach ( $idle as $token ) {
			unset( $sessions[ $token ] );
			do_action( 'opkm_after_idle_terminate', $user_id );
		}
		foreach ( $exp as $token ) {
			unset( $sessions[ $token ] );
			do_action( 'opkm_after_expired_terminate', $user_id );
		}
		self::delete_user_password( $sessions, $user_id );
		return count( $idle ) + count( $exp );
	}

	/**
	 * Delete remaining sessions.
	 *
	 * @return int|bool False if it was not possible, otherwise the number of deleted sessions.
	 * @since    1.0.0
	 */
	public static function delete_remaining_sessions() {
		if ( Role::SUPER_ADMIN === Role::admin_type() || Role::SINGLE_ADMIN === Role::admin_type() ) {
			$user_id   = get_current_user_id();
			$selftoken = Hash::simple_hash( wp_get_session_token(), false );
			if ( isset( $user_id ) && is_integer( $user_id ) && 0 < $user_id ) {
				$sessions = self::get_user_passwords( $user_id );
				$cpt      = count( $sessions ) - 1;
				if ( is_array( $sessions ) ) {
					foreach ( array_diff_key( array_keys( $sessions ), [ $selftoken ] ) as $key ) {
						unset( $sessions[ $key ] );
					}
					self::delete_user_password( $sessions, $user_id );
					return $cpt;
				} else {
					return 0;
				}
			} else {
				Logger::alert( 'An unknown user attempted to delete all active sessions.' );
				return false;
			}
		} else {
			Logger::alert( 'A non authorized user attempted to delete all active sessions.' );
			return false;
		}
	}

	/**
	 * Delete selected sessions.
	 *
	 * @param array   $bulk   The sessions to delete.
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
