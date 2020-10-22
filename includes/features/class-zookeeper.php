<?php
/**
 * Background tasks handling
 *
 * Handles all background tasks.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin\Feature;

use Decalog\Plugin\Feature\Log;
use KeysMaster\System\Cache;
use KeysMaster\System\Logger;
use KeysMaster\System\Option;
use KeysMaster\System\Session;
use KeysMaster\Plugin\Feature\Schema;

/**
 * Define the zookeeper functionality.
 *
 * Handles all background tasks.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class ZooKeeper {

	/**
	 * Initialize the meta class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		add_action( 'shutdown', [ self::class, 'execute_tasks' ], 10 );
	}

	/**
	 * Execute the background tasks.
	 *
	 * @since    1.0.0
	 */
	public static function execute_tasks() {
		$semaphore = Cache::get_global( 'zookeeper/semaphore' );
		if ( isset( $semaphore ) && $semaphore && (int) $semaphore + (int) Option::network_get( 'zk_semaphore' ) < time() ) {
			return;
		}
		$lastexec = Cache::get_global( 'zookeeper/lastexec' );
		if ( isset( $lastexec ) && $lastexec && (int) $lastexec + (int) Option::network_get( 'zk_cycle' ) > time() ) {
			return;
		}
		if ( isset( $semaphore ) && $semaphore && (int) $semaphore + (int) Option::network_get( 'zk_semaphore' ) >= time() ) {
			Logger::debug( '[ZooKeeper] Destroying staled semaphore.' );
		}
		Cache::set_global( 'zookeeper/semaphore', time() );
		Logger::debug( '[ZooKeeper] Starting background tasks execution.' );
		self::terminate_sessions();
		Logger::debug( '[ZooKeeper] Ending background tasks execution.' );
		Cache::delete_global( 'zookeeper/semaphore' );
		Cache::set_global( 'zookeeper/lastexec', time() );
	}

	/**
	 * Terminates staled sessions.
	 *
	 * @since    1.0.0
	 */
	private static function terminate_sessions() {
		return;
		global $wpdb;
		Logger::debug( '[ZooKeeper] Starting "terminate_sessions" execution.' );
		$index = Cache::get_global( 'zookeeper/userindex' );
		if ( ! $index ) {
			$index = 0;
		}
		$limit = (int) Option::network_get( 'zk_tsize' );
		$cpt   = 0;
		$sql   = 'SELECT user_id, meta_value FROM ' . $wpdb->usermeta . " WHERE meta_key='session_tokens' LIMIT " . $limit . " OFFSET " . $index . ";";
		// phpcs:ignore
		$query = $wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $query ) && 0 < count( $query ) ) {
			if ( $limit > count( $query ) ) {
				$index = 0;
			} else {
				$index += $limit;
			}
			foreach ( $query as $row ) {
				$sessions = $row['meta_value'];
				if ( ! is_array( $sessions ) && is_string( $sessions ) ) {
					$sessions = maybe_unserialize( $sessions );
				}
				if ( is_array( $sessions ) ) {
					$cpt += Session::auto_terminate_session( $sessions, (int) $row['user_id'] );
				}
			}
			switch ( $cpt ) {
				case 0:
					Logger::debug( 'No session to auto-terminate.' );
					break;
				case 1:
					Logger::notice( sprintf( '%d session auto-terminated.', $cpt ) );
					break;
				default:
					Logger::notice( sprintf( '%d sessions auto-terminated.', $cpt ) );
					break;
			}
		} else {
			Logger::debug( 'No session to auto-terminate.' );
			$index = 0;
		}
		Cache::set_global( 'zookeeper/userindex', $index, 'infinite' );
		Logger::debug( '[ZooKeeper] Ending "terminate_sessions" execution.' );
	}

}