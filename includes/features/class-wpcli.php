<?php
/**
 * WP-CLI for Keys Master.
 *
 * Adds WP-CLI commands to Keys Master
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin\Feature;

use KeysMaster\System\Environment;
use KeysMaster\System\Logger;
use KeysMaster\System\Option;
use KeysMaster\System\Markdown;
use KeysMaster\Plugin\Feature\Analytics;
use KeysMaster\Plugin\Feature\Schema;
use KeysMaster\System\Password;
use KeysMaster\System\Timezone;
use KeysMaster\System\GeoIP;
use KeysMaster\System\User;
use KeysMaster\System\IP;
use Spyc;

/**
 * Manages application passwords and get details about their use.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Wpcli {

	/**
	 * List of exit codes.
	 *
	 * @since    1.0.0
	 * @var array $exit_codes Exit codes.
	 */
	private $exit_codes = [
		0   => 'operation successful.',
		1   => 'unrecognized setting.',
		2   => 'unrecognized action.',
		3   => 'analytics are disabled.',
		4   => 'unrecognized mode.',
		5   => 'invalid application password uuid supplied.',
		6   => 'user doesn\'t exist.',
		255 => 'unknown error.',
	];

	/**
	 * Write ids as clean stdout.
	 *
	 * @param   array   $ids   The ids.
	 * @param   string  $field  Optional. The field to output.
	 * @since   1.0.0
	 */
	private function write_ids( $ids, $field = '' ) {
		$result = '';
		$last   = end( $ids );
		foreach ( $ids as $key => $id ) {
			if ( '' === $field ) {
				$result .= $key;
			} else {
				$result .= $id[$field];
			}
			if ( $id !== $last ) {
				$result .= ' ';
			}
		}
		// phpcs:ignore
		fwrite( STDOUT, $result );
	}

	/**
	 * Write an error.
	 *
	 * @param   integer  $code      Optional. The error code.
	 * @param   boolean  $stdout    Optional. Clean stdout output.
	 * @since   1.0.0
	 */
	private function error( $code = 255, $stdout = false ) {
		if ( \WP_CLI\Utils\isPiped() ) {
			// phpcs:ignore
			fwrite( STDOUT, '' );
			// phpcs:ignore
			exit( $code );
		} elseif ( $stdout ) {
			// phpcs:ignore
			fwrite( STDERR, ucfirst( $this->exit_codes[ $code ] ) );
			// phpcs:ignore
			exit( $code );
		} else {
			\WP_CLI::error( $this->exit_codes[ $code ] );
		}
	}

	/**
	 * Write an error from a WP_Error object.
	 *
	 * @param   \WP_Error  $err     The error object.
	 * @param   boolean  $stdout    Optional. Clean stdout output.
	 * @since   1.0.0
	 */
	private function error_from_object( $err, $stdout = false ) {
		$msg = $this->exit_codes[255];
		if ( is_wp_error( $err ) ) {
			$msg = $err->get_error_message();
		}
		if ( \WP_CLI\Utils\isPiped() ) {
			// phpcs:ignore
			fwrite( STDOUT, '' );
			// phpcs:ignore
			exit( 255 );
		} elseif ( $stdout ) {
			// phpcs:ignore
			fwrite( STDERR, ucfirst( $msg ) );
			// phpcs:ignore
			exit( 255 );
		} else {
			\WP_CLI::error( $msg );
		}
	}

	/**
	 * Write a warning.
	 *
	 * @param   string   $msg       The message.
	 * @param   string   $result    Optional. The result.
	 * @param   boolean  $stdout    Optional. Clean stdout output.
	 * @since   1.0.0
	 */
	private function warning( $msg, $result = '', $stdout = false ) {
		if ( \WP_CLI\Utils\isPiped() || $stdout ) {
			// phpcs:ignore
			fwrite( STDOUT, $result );
		} else {
			\WP_CLI::warning( $msg );
		}
	}

	/**
	 * Write a success.
	 *
	 * @param   string   $msg       The message.
	 * @param   string   $result    Optional. The result.
	 * @param   boolean  $stdout    Optional. Clean stdout output.
	 * @since   1.0.0
	 */
	private function success( $msg, $result = '', $stdout = false ) {
		if ( \WP_CLI\Utils\isPiped() || $stdout ) {
			// phpcs:ignore
			fwrite( STDOUT, $result );
		} else {
			\WP_CLI::success( $msg );
		}
	}

	/**
	 * Write a wimple line.
	 *
	 * @param   string   $msg       The message.
	 * @param   string   $result    Optional. The result.
	 * @param   boolean  $stdout    Optional. Clean stdout output.
	 * @since   1.0.0
	 */
	private function line( $msg, $result = '', $stdout = false ) {
		if ( \WP_CLI\Utils\isPiped() || $stdout ) {
			// phpcs:ignore
			fwrite( STDOUT, $result );
		} else {
			\WP_CLI::line( $msg );
		}
	}

	/**
	 * Write a wimple log line.
	 *
	 * @param   string   $msg       The message.
	 * @param   boolean  $stdout    Optional. Clean stdout output.
	 * @since   1.0.0
	 */
	private function log( $msg, $stdout = false ) {
		if ( ! \WP_CLI\Utils\isPiped() && ! $stdout ) {
			\WP_CLI::log( $msg );
		}
	}

	/**
	 * Get params from command line.
	 *
	 * @param   array   $args   The command line parameters.
	 * @return  array The true parameters.
	 * @since   1.0.0
	 */
	private function get_params( $args ) {
		$result = '';
		if ( array_key_exists( 'settings', $args ) ) {
			$result = \json_decode( $args['settings'], true );
		}
		if ( ! $result || ! is_array( $result ) ) {
			$result = [];
		}
		return $result;
	}

	/**
	 * Get Keys Master details and operation modes.
	 *
	 * ## EXAMPLES
	 *
	 * wp apwd status
	 *
	 *
	 *     === For other examples and recipes, visit https://github.com/Pierre-Lannoy/wp-keys-master/blob/master/WP-CLI.md ===
	 *
	 */
	public function status( $args, $assoc_args ) {
		\WP_CLI::line( sprintf( '%s is running.', Environment::plugin_version_text() ) );
		switch ( Option::network_get( 'rolemode' ) ) {
			case -1:
				\WP_CLI::line( 'Operation mode: no role limitation.' );
				break;
			case 0:
				\WP_CLI::line( 'Operation mode: role limitation with cumulative privileges.' );
				break;
			case 1:
				\WP_CLI::line( 'Operation mode: role limitation with least privileges.' );
				break;
				
		}
		if ( Option::network_get( 'analytics' ) ) {
			\WP_CLI::line( 'Analytics: enabled.' );
		} else {
			\WP_CLI::line( 'Analytics: disabled.' );
		}
		if ( defined( 'DECALOG_VERSION' ) ) {
			\WP_CLI::line( 'Logging support: yes (DecaLog v' . DECALOG_VERSION . ').');
		} else {
			\WP_CLI::line( 'Logging support: no.' );
		}
		$geo = new GeoIP();
		if ( $geo->is_installed() ) {
			\WP_CLI::line( 'IP information support: yes (' . $geo->get_full_name() . ').');
		} else {
			\WP_CLI::line( 'IP information support: no.' );
		}
		if ( defined( 'PODD_VERSION' ) ) {
			\WP_CLI::line( 'Device detection support: yes (Device Detector v' . PODD_VERSION . ').');
		} else {
			\WP_CLI::line( 'Device detection support: no.' );
		}
	}

	/**
	 * Modify Keys Master main settings.
	 *
	 * ## OPTIONS
	 *
	 * <enable|disable>
	 * : The action to take.
	 *
	 * <analytics>
	 * : The setting to change.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message, if any.
	 *
	 * [--stdout]
	 * : Use clean STDOUT output to use results in scripts. Unnecessary when piping commands because piping is detected by Keys Master.
	 *
	 * ## EXAMPLES
	 *
	 * wp apwd settings disable analytics --yes
	 *
	 *
	 *     === For other examples and recipes, visit https://github.com/Pierre-Lannoy/wp-keys-master/blob/master/WP-CLI.md ===
	 *
	 */
	public function settings( $args, $assoc_args ) {
		$stdout  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'stdout', false );
		$action  = isset( $args[0] ) ? (string) $args[0] : '';
		$setting = isset( $args[1] ) ? (string) $args[1] : '';
		switch ( $action ) {
			case 'enable':
				switch ( $setting ) {
					case 'analytics':
						Option::network_set( 'analytics', true );
						$this->success( 'analytics are now activated.', '', $stdout );
						break;
					default:
						$this->error( 1, $stdout );
				}
				break;
			case 'disable':
				switch ( $setting ) {
					case 'analytics':
						\WP_CLI::confirm( 'Are you sure you want to deactivate analytics?', $assoc_args );
						Option::network_set( 'analytics', false );
						$this->success( 'analytics are now deactivated.', '', $stdout );
						break;
					default:
						$this->error( 1, $stdout );
				}
				break;
			default:
				$this->error( 2, $stdout );
		}
	}

	/**
	 * Modify Keys Master operation mode.
	 *
	 * ## OPTIONS
	 *
	 * <set>
	 * : The action to take.
	 *
	 * <none|cumulative|least>
	 * : The mode to set.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message, if any.
	 *
	 * [--stdout]
	 * : Use clean STDOUT output to use results in scripts. Unnecessary when piping commands because piping is detected by Keys Master.
	 *
	 * ## EXAMPLES
	 *
	 * wp apwd mode set none --yes
	 *
	 *
	 *     === For other examples and recipes, visit https://github.com/Pierre-Lannoy/wp-keys-master/blob/master/WP-CLI.md ===
	 *
	 */
	public function mode( $args, $assoc_args ) {
		$stdout = \WP_CLI\Utils\get_flag_value( $assoc_args, 'stdout', false );
		$action = isset( $args[0] ) ? (string) $args[0] : '';
		$mode   = isset( $args[1] ) ? (string) $args[1] : '';
		switch ( $action ) {
			case 'set':
				switch ( $mode ) {
					case 'none':
						\WP_CLI::confirm( 'Are you sure you want to deactivate role-mode?', $assoc_args );
						Option::network_set( 'rolemode', -1 );
						$this->success( 'operation mode is now "no role limitation".', '', $stdout );
						break;
					case 'cumulative':
						Option::network_set( 'rolemode', 0 );
						$this->success( 'operation mode is now "role limitation with cumulative privileges".', '', $stdout );
						break;
					case 'least':
						Option::network_set( 'rolemode', 1 );
						$this->success( 'operation mode is now "role limitation with least privileges".', '', $stdout );
						break;
					default:
						$this->error( 4, $stdout );
						break;
				}
				break;
			default:
				$this->error( 2, $stdout );
		}
	}

	/**
	 * Get application password analytics for today.
	 *
	 * ## OPTIONS
	 *
	 * [--site=<site_id>]
	 * : The site for which to display analytics. May be 0 (all network) or an integer site id. Only useful with multisite environments.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--format=<format>]
	 * : Set the output format. Note if json is chosen: full metadata is outputted too.
	 * ---
	 * default: table
	 * options:
	 *  - table
	 *  - json
	 *  - csv
	 *  - yaml
	 *  - count
	 * ---
	 *
	 * [--stdout]
	 * : Use clean STDOUT output to use results in scripts. Unnecessary when piping commands because piping is detected by Keys Master.
	 *
	 * ## EXAMPLES
	 *
	 * wp apwd analytics
	 *
	 *
	 *    === For other examples and recipes, visit https://github.com/Pierre-Lannoy/wp-keys-master/blob/master/WP-CLI.md ===
	 *
	 */
	public function analytics( $args, $assoc_args ) {
		$stdout = \WP_CLI\Utils\get_flag_value( $assoc_args, 'stdout', false );
		$site   = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'site', 0 );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		if ( ! Option::network_get( 'analytics' ) ) {
			$this->error( 3, $stdout );
		}
		$analytics = Analytics::get_status_kpi_collection( [ 'site_id' => $site ] );
		$result    = [];
		if ( array_key_exists( 'data', $analytics ) ) {
			foreach ( $analytics['data'] as $kpi ) {
				$item                = [];
				$item['kpi']         = $kpi['name'];
				$item['description'] = $kpi['description'];
				$item['value']       = $kpi['value']['human'];
				if ( array_key_exists( 'ratio', $kpi ) && isset( $kpi['ratio'] ) ) {
					$item['ratio'] = $kpi['ratio']['percent'] . '%';
				} else {
					$item['ratio'] = '-';
				}
				$item['variation'] = ( 0 < $kpi['variation']['percent'] ? '+' : '' ) . (string) $kpi['variation']['percent'] . '%';
				$result[]          = $item;
			}
		}
		if ( 'json' === $format ) {
			$detail = wp_json_encode( $analytics );
			$this->line( $detail, $detail, $stdout );
		} elseif ( 'yaml' === $format ) {
			unset( $analytics['assets'] );
			$detail = Spyc::YAMLDump( $analytics, true, true, true );
			$this->line( $detail, $detail, $stdout );
		} else {
			\WP_CLI\Utils\format_items( $assoc_args['format'], $result, [ 'kpi', 'description', 'value', 'ratio', 'variation' ] );
		}
	}

	/**
	 * Get information on exit codes.
	 *
	 * ## OPTIONS
	 *
	 * <list>
	 * : The action to take.
	 * ---
	 * options:
	 *  - list
	 * ---
	 *
	 * [--format=<format>]
	 * : Allows overriding the output of the command when listing exit codes.
	 * ---
	 * default: table
	 * options:
	 *  - table
	 *  - json
	 *  - csv
	 *  - yaml
	 *  - ids
	 *  - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * Lists available exit codes:
	 * + wp apwd exitcode list
	 * + wp apwd exitcode list --format=json
	 *
	 *
	 *   === For other examples and recipes, visit https://github.com/Pierre-Lannoy/wp-keys-master/blob/master/WP-CLI.md ===
	 *
	 */
	public function exitcode( $args, $assoc_args ) {
		$stdout = \WP_CLI\Utils\get_flag_value( $assoc_args, 'stdout', false );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$action = isset( $args[0] ) ? $args[0] : 'list';
		$codes  = [];
		foreach ( $this->exit_codes as $key => $msg ) {
			$codes[ $key ] = [ 'code' => $key, 'meaning' => ucfirst( $msg ) ];
		}
		switch ( $action ) {
			case 'list':
				if ( 'ids' === $format ) {
					$this->write_ids( $codes );
				} else {
					\WP_CLI\Utils\format_items( $format, $codes, [ 'code', 'meaning' ] );
				}
				break;
		}
	}

	/**
	 * Get the WP-CLI help file.
	 *
	 * @param   array $attributes  'style' => 'markdown', 'html'.
	 *                             'mode'  => 'raw', 'clean'.
	 * @return  string  The output of the shortcode, ready to print.
	 * @since 1.0.0
	 */
	public static function sc_get_helpfile( $attributes ) {
		$md = new Markdown();
		return $md->get_shortcode(  'WP-CLI.md', $attributes  );
	}

	/**
	 * Manage application passwords.
	 *
	 * ## OPTIONS
	 *
	 * <list|create|revoke>
	 * : The action to take.
	 * ---
	 * options:
	 *  - list
	 *  - create
	 *  - revoke
	 * ---
	 *
	 * [<uuid|user_id>]
	 * : The uuid of the password or the id of the user to perform an action/search on.
	 *
	 * [--settings=<settings>]
	 * : The settings needed by "create" action.
	 * MUST be a string containing a json configuration.
	 * ---
	 * default: '{}'
	 * example: '{"name": "New app key"}'
	 * ---
	 *
	 * [--detail=<detail>]
	 * : The details of the output when listing application passwords.
	 * ---
	 * default: short
	 * options:
	 *  - short
	 *  - full
	 * ---
	 *
	 * [--format=<format>]
	 * : Allows overriding the output of the command when listing application passwords. Note if json or yaml is chosen: full metadata is outputted too.
	 * ---
	 * default: table
	 * options:
	 *  - table
	 *  - json
	 *  - csv
	 *  - yaml
	 *  - ids
	 *  - count
	 * ---
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message, if any.
	 *
	 * [--stdout]
	 * : Use clean STDOUT output to use results in scripts. Unnecessary when piping commands because piping is detected by Keys Master.
	 *
	 * ## EXAMPLES
	 *
	 * List some application passwords:
	 * + wp apwd password list
	 * + wp apwd password list ed0f775f-2271-4570-a28b-0bc11fba2b27
	 * + wp apwd password list 1
	 * + wp apwd password list --detail=full
	 * + wp apwd password list --format=json
	 *
	 * Create an application password:
	 * + wp apwd password create 125
	 * + wp apwd password create 1 --settings='{"name":"My Application Password"}'
	 *
	 * Revoke an application password:
	 * + wp apwd password revoke 5d3f949d-d135-4c19-a621-7a47b6c0f83b
	 * + wp apwd password revoke 5d3f949d-d135-4c19-a621-7a47b6c0f83b --yes
	 *
	 *
	 *   === For other examples and recipes, visit https://github.com/Pierre-Lannoy/wp-keys-master/blob/master/WP-CLI.md ===
	 *
	 */
	public function password( $args, $assoc_args ) {
		$stdout = \WP_CLI\Utils\get_flag_value( $assoc_args, 'stdout', false );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$detail = \WP_CLI\Utils\get_flag_value( $assoc_args, 'detail', 'short' );
		$params = $this->get_params( $assoc_args );
		$uuid   = '';
		$id     = '';
		$action = isset( $args[0] ) ? $args[0] : 'list';
		if ( isset( $args[1] ) ) {
			$arg = $args[1];
			if ( false !== strpos( $arg, '-' ) ) {
				$uuid = $arg;
			} else {
				$id = (int) $arg;
				if ( false === get_userdata( $id ) ) {
					$this->error( 6, $stdout );
				}
			}
		}
		if ( 'create' === $action && '' === $id ) {
			$this->error( 6, $stdout );
		}
		if ( 'revoke' === $action && '' === $uuid ) {
			$this->error( 5, $stdout );
		}
		switch ( $action ) {
			case 'list':
				$passwords = [];
				$list      = [];
				$tz        = Timezone::network_get();
				if ( '' !== $id ) {
					$tmp    = Password::get_user_passwords( $id );
					$list[] = [
						'user_id'    => $id,
						'meta_value' => $tmp,
					];
				} elseif ( '' !== $uuid ) {
					foreach ( Password::get_uuid_passwords( $uuid ) as $password_list ) {
						foreach ( $password_list['meta_value'] as $password ) {
							if ( $uuid === $password['uuid'] ) {
								$list[] = [
									'user_id'    => (int) $password_list['user_id'],
									'meta_value' => [ $password ],
								];
								break 2;
							}
						}
					}
				} else {
					$list = Password::get_all_passwords();
				}
				foreach ( $list as $password_list ) {
					foreach ( $password_list['meta_value'] as $password ) {
						$passwords[ $password['uuid'] ]['uuid']    = $password['uuid'];
						$passwords[ $password['uuid'] ]['user-id'] = (int) $password_list['user_id'];
						$passwords[ $password['uuid'] ]['user']    = User::get_user_string( $password_list['user_id'] );
						$passwords[ $password['uuid'] ]['name']    = substr( $password['name'], 0, 20 );
						if ( isset( $password['last_used'] ) ) {
							$datetime = new \DateTime( date( 'Y-m-d H:i:s', $password['last_used'] ) );
							$datetime->setTimezone( $tz );
							$passwords[ $password['uuid'] ]['last-used'] = $datetime->format( 'Y-m-d' );
						} else {
							$passwords[ $password['uuid'] ]['last-used'] = 'never';
						}
						$datetime = new \DateTime( date( 'Y-m-d H:i:s', $password['created'] ) );
						$datetime->setTimezone( $tz );
						$passwords[ $password['uuid'] ]['created'] = $datetime->format( 'Y-m-d' );
						if ( isset( $password['last_used'] ) ) {
							$passwords[ $password['uuid'] ]['last-ip'] = IP::expand( $password['last_ip'] );
						} else {
							$passwords[ $password['uuid'] ]['last-ip'] = '-';
						}
					}
				}
				usort(
					$passwords,
					function ( $a, $b ) {
						return strcmp( strtolower( $a[ 'user' ] ), strtolower( $b[ 'user' ] ) );
					}
				);
				if ( 'full' === $detail ) {
					$detail = [ 'uuid', 'user', 'name', 'created', 'last-used', 'last-ip' ];
				} else {
					$detail = [ 'uuid', 'user', 'name', 'last-used' ];
				}
				if ( 'ids' === $format ) {
					$this->write_ids( $passwords, 'uuid' );
				} elseif ( 'yaml' === $format ) {
					$details = Spyc::YAMLDump( $passwords, true, true, true );
					$this->line( $details, $details, $stdout );
				}  elseif ( 'json' === $format ) {
					$details = wp_json_encode( $passwords );
					$this->line( $details, $details, $stdout );
				} else {
					\WP_CLI\Utils\format_items( $format, $passwords, $detail );
				}
				break;
			case 'create':
				if ( ! array_key_exists( 'name', $params ) ) {
					$params['name'] = 'Application password created via Keys Master';
				}
				$created = \WP_Application_Passwords::create_new_application_password( $id, $params );
				if ( is_wp_error( $created ) && ! is_array( $created ) ) {
					$this->error_from_object( $created );
				}
				$this->success( 'the new password is ' . \WP_CLI::colorize( '%8' ) . $created[0] . \WP_CLI::colorize( '%n.' ) . ' Be sure to save this in a safe location, you will not be able to retrieve it.', $created[0], $stdout );
				break;
			case 'revoke':
				$meta = Password::get_uuid_passwords( $uuid );
				if ( 0 < $meta && array_key_exists( 'user_id', $meta[0] ) ) {
					\WP_CLI::confirm( 'Are you sure you want to revoke this password?', $assoc_args );
					$revoked = \WP_Application_Passwords::delete_application_password( (int) $meta[0]['user_id'], $uuid );
					if ( is_wp_error( $revoked ) && ! is_array( $revoked ) ) {
						$this->error_from_object( $revoked );
					}
					$this->success( 'password ' . \WP_CLI::colorize( '%8' ) . $uuid . \WP_CLI::colorize( '%n' ) . ' revoked.', $uuid, $stdout );
				} else {
					$this->error( 5, $stdout );
				}
				break;
		}
	}

}

add_shortcode( 'pokm-wpcli', [ 'KeysMaster\Plugin\Feature\Wpcli', 'sc_get_helpfile' ] );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'apwd', 'KeysMaster\Plugin\Feature\Wpcli' );
}