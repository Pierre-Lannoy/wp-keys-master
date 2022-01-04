<?php
/**
 * Keys Master schema
 *
 * Handles all schema operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin\Feature;

use KeysMaster\System\Option;
use KeysMaster\System\Database;
use KeysMaster\System\Environment;
use KeysMaster\System\Favicon;

use KeysMaster\System\Cache;
use KeysMaster\System\Timezone;
use KeysMaster\Plugin\Feature\Capture;
use KeysMaster\System\Password;

/**
 * Define the schema functionality.
 *
 * Handles all schema operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Schema {

	/**
	 * Statistics table name.
	 *
	 * @since  1.0.0
	 * @var    string    $statistics    The statistics table name.
	 */
	private static $statistics = 'password_statistics';

	/**
	 * Usages table name.
	 *
	 * @since  1.0.0
	 * @var    string    $usages    The usages table name.
	 */
	private static $usages = 'password_usage';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize static properties and hooks.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		add_action( 'shutdown', [ self::class, 'write' ], 11, 0 );
	}

	/**
	 * Write all buffers to database.
	 *
	 * @param boolean   $purge Optional. Purge od dates too.
	 * @since    1.0.0
	 */
	public static function write( $purge = true ) {
		if ( Option::network_get( 'analytics', false ) ) {
			self::write_current_to_database( Capture::get_stats() );
			self::write_usage_to_database( Capture::get_usage() );
		}
		if ( $purge ) {
			self::purge();
		}
	}

	/**
	 * Effectively write a buffer element in the database.
	 *
	 * @param array $record The buffer to write.
	 * @since    1.0.0
	 */
	private static function write_current_to_database( $record ) {
		$record = self::maybe_add_stats( $record );
		if ( 0 === count( $record ) ) {
			return;
		}
		$datetime            = new \DateTime( 'now', Timezone::network_get() );
		$record['timestamp'] = $datetime->format( 'Y-m-d' );
		$field_insert        = [];
		$value_insert        = [];
		$value_update        = [];
		foreach ( $record as $k => $v ) {
			$field_insert[] = '`' . $k . '`';
			if ( 'timestamp' === $k ) {
				$value_insert[] = "'" . $v . "'";
			} else {
				$value_insert[] = (int) $v;
				$value_update[] = '`' . $k . '`=`' . $k . '` + ' . (int) $v;
			}
		}
		if ( count( $field_insert ) > 1 ) {
			global $wpdb;
			$sql  = 'INSERT INTO `' . $wpdb->base_prefix . self::$statistics . '` ';
			$sql .= '(' . implode( ',', $field_insert ) . ') ';
			$sql .= 'VALUES (' . implode( ',', $value_insert ) . ') ';
			$sql .= 'ON DUPLICATE KEY UPDATE ' . implode( ',', $value_update ) . ';';
			// phpcs:ignore
			$wpdb->query( $sql );
		}
	}

	/**
	 * Effectively write the usage in the database.
	 *
	 * @param array $record The usage to write.
	 * @since    1.0.0
	 */
	private static function write_usage_to_database( $record ) {
		if ( 0 === count( $record ) ) {
			return;
		}
		$datetime            = new \DateTime( 'now', Timezone::network_get() );
		$record['timestamp'] = $datetime->format( 'Y-m-d' );
		$field_insert        = [];
		$value_insert        = [];
		$value_update        = [];
		foreach ( $record as $k => $v ) {
			$field_insert[] = '`' . $k . '`';
			if ( is_integer( $v ) ) {
				$value_insert[] = (int) $v;
				if ( in_array( $k, [ 'success', 'fail' ], true ) ) {
					$value_update[] = '`' . $k . '`=`' . $k . '` + ' . (int) $v;
				}
			} else {
				$value_insert[] = "'" . $v . "'";
			}
		}
		if ( count( $field_insert ) > 1 ) {
			global $wpdb;
			$sql  = 'INSERT INTO `' . $wpdb->base_prefix . self::$usages . '` ';
			$sql .= '(' . implode( ',', $field_insert ) . ') ';
			$sql .= 'VALUES (' . implode( ',', $value_insert ) . ') ';
			$sql .= 'ON DUPLICATE KEY UPDATE ' . implode( ',', $value_update ) . ';';
			// phpcs:ignore
			$wpdb->query( $sql );
		}
	}

	/**
	 * Adds misc stats to a buffer, if needed.
	 *
	 * @param array $record The buffer to write.
	 * @return  array   The completed buffer if needed.
	 * @since    1.0.0
	 */
	private static function maybe_add_stats( $record ) {
		$check = Cache::get_global( 'data/statcheck' );
		if ( isset( $check ) && $check && (int) $check + 6 * HOUR_IN_SECONDS > time() ) {
			return $record;
		}
		$record['cnt']      = 1;
		$record['user']     = 0;
		$record['adopt']    = 0;
		$record['password'] = 0;
		global $wpdb;
		$sql = 'SELECT COUNT(*) as u_cnt FROM ' . $wpdb->users;
		// phpcs:ignore
		$query = $wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $query ) && 0 < count( $query ) ) {
			$record['user'] = $query[0]['u_cnt'];
		}
		$sql = "SELECT COUNT(*) AS users, SUM( CAST( SUBSTRING(`meta_value`,3,POSITION('{' IN `meta_value`) - 4) AS UNSIGNED)) AS sessions FROM " . $wpdb->usermeta . " WHERE `meta_key`='" . Password::$meta_key . "' and `meta_value`<>'' and `meta_value`<>'a:0:{}'";
		// phpcs:ignore
		$query = $wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $query ) && 0 < count( $query ) ) {
			$record['adopt']    = $query[0]['users'];
			$record['password'] = $query[0]['sessions'];
		}
		\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( 'Misc stats added.' );
		Cache::set_global( 'data/statcheck', time(), 'infinite' );
		return $record;
	}

	/**
	 * Initialize the schema.
	 *
	 * @since    1.1.0
	 */
	public function initialize() {
		global $wpdb;
		try {
			$this->create_table();
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( sprintf( 'Table "%s" created.', $wpdb->base_prefix . self::$statistics ) );
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->info( 'Schema installed.' );
			Option::network_set( 'analytics', true );
		} catch ( \Throwable $e ) {
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->alert( sprintf( 'Unable to create "%s" table: %s', $wpdb->base_prefix . self::$statistics, $e->getMessage() ), $e->getCode() );
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->alert( 'Schema not installed.', [ 'code' => $e->getCode() ] );
		}
	}

	/**
	 * Update the schema.
	 *
	 * @since    1.1.0
	 */
	public function update() {
		global $wpdb;
		try {
			$this->create_table();
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( sprintf( 'Table "%s" updated.', $wpdb->base_prefix . self::$statistics ) );
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->info( 'Schema updated.' );
		} catch ( \Throwable $e ) {
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->alert( sprintf( 'Unable to update "%s" table: %s', $wpdb->base_prefix . self::$statistics, $e->getMessage() ), [ 'code' => $e->getCode() ] );
		}
	}

	/**
	 * Purge old records.
	 *
	 * @since    1.0.0
	 */
	private static function purge() {
		$days = (int) Option::network_get( 'history' );
		if ( ! is_numeric( $days ) || 30 > $days ) {
			$days = 30;
			Option::network_set( 'history', $days );
		}
		$database = new Database();
		$count    = $database->purge( self::$statistics, 'timestamp', 24 * $days );
		$count   += $database->purge( self::$usages, 'timestamp', 24 * $days );
		if ( 0 === $count ) {
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( 'No old records to delete.' );
		} elseif ( 1 === $count ) {
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( '1 old record deleted.' );
			Cache::delete_global( 'data/oldestdate' );
		} else {
			\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( sprintf( '%1$s old records deleted.', $count ) );
			Cache::delete_global( 'data/oldestdate' );
		}
	}

	/**
	 * Create the table.
	 *
	 * @since    1.0.0
	 */
	private function create_table() {
		global $wpdb;
		$charset_collate = 'DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci';
		$sql             = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->base_prefix . self::$statistics;
		$sql            .= " (`timestamp` date NOT NULL DEFAULT '0000-00-00',";
		$sql            .= " `cnt` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `user` bigint UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `adopt` bigint UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `password` bigint UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `revoked` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `created` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `success` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `fail` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= ' UNIQUE KEY ap_stat (timestamp)';
		$sql            .= ") $charset_collate;";
		// phpcs:ignore
		$wpdb->query( $sql );
		global $wpdb;
		$charset_collate = 'DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci';
		$sql             = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->base_prefix . self::$usages;
		$sql            .= " (`timestamp` date NOT NULL DEFAULT '0000-00-00',";
		$sql            .= " `channel` enum('xmlrpc','api','unknown') NOT NULL DEFAULT 'unknown',";
		$sql            .= " `site` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `country` varchar(2) NOT NULL DEFAULT '00',";
		$sql            .= " `device` varchar(512) NOT NULL DEFAULT '-',";
		$sql            .= " `success` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= " `fail` int(11) UNSIGNED NOT NULL DEFAULT '0',";
		$sql            .= ' UNIQUE KEY ap_usage (timestamp, channel, site, country, device)';
		$sql            .= ") $charset_collate;";
		// phpcs:ignore
		$wpdb->query( $sql );
	}

	/**
	 * Finalize the schema.
	 *
	 * @since    1.0.0
	 */
	public function finalize() {
		global $wpdb;
		$sql = 'DROP TABLE IF EXISTS ' . $wpdb->base_prefix . self::$statistics;
		// phpcs:ignore
		$wpdb->query( $sql );
		\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( sprintf( 'Table "%s" removed.', $wpdb->base_prefix . self::$statistics ) );
		$sql = 'DROP TABLE IF EXISTS ' . $wpdb->base_prefix . self::$usages;
		// phpcs:ignore
		$wpdb->query( $sql );
		\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( sprintf( 'Table "%s" removed.', $wpdb->base_prefix . self::$usages ) );
		\DecaLog\Engine::eventsLogger( POKM_SLUG )->debug( 'Schema destroyed.' );
	}

	/**
	 * Get "where" clause of a query.
	 *
	 * @param array $filters Optional. An array of filters.
	 * @return string The "where" clause.
	 * @since 1.0.0
	 */
	private static function get_where_clause( $filters = [] ) {
		$result = '';
		if ( 0 < count( $filters ) ) {
			$w = [];
			foreach ( $filters as $key => $filter ) {
				if ( is_array( $filter ) ) {
					$w[] = '`' . $key . '` IN (' . implode( ',', $filter ) . ')';
				} else {
					$w[] = '`' . $key . '`="' . $filter . '"';
				}
			}
			$result = 'WHERE (' . implode( ' AND ', $w ) . ')';
		}
		return $result;
	}

	/**
	 * Get the oldest date.
	 *
	 * @return  string   The oldest timestamp in the statistics table.
	 * @since    1.0.0
	 */
	public static function get_oldest_date() {
		$result = Cache::get_global( 'data/oldestdate' );
		if ( $result ) {
			return $result;
		}
		global $wpdb;
		$sql = 'SELECT * FROM ' . $wpdb->base_prefix . self::$statistics . ' ORDER BY `timestamp` ASC LIMIT 1';
		// phpcs:ignore
		$result = $wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $result ) && 0 < count( $result ) && array_key_exists( 'timestamp', $result[0] ) ) {
			Cache::set_global( 'data/oldestdate', $result[0]['timestamp'], 'infinite' );
			return $result[0]['timestamp'];
		}
		return '';
	}

	/**
	 * Get the standard KPIs.
	 *
	 * @param   array   $filter      The filter of the query.
	 * @param   string  $group       Optional. The group of the query.
	 * @param   boolean $cache       Optional. Has the query to be cached.
	 * @return  array   The grouped KPIs.
	 * @since    1.0.0
	 */
	public static function get_grouped_kpi( $filter, $group = '', $cache = true ) {
		// phpcs:ignore
		$id = Cache::id( __FUNCTION__ . serialize( $filter ) . $group );
		if ( $cache ) {
			$result = Cache::get_global( $id );
			if ( $result ) {
				return $result;
			}
		}
		if ( '' !== $group ) {
			$group = ' GROUP BY ' . $group;
		}
		global $wpdb;
		$sql = 'SELECT * FROM ' . $wpdb->base_prefix . self::$statistics . ' WHERE (' . implode( ' AND ', $filter ) . ')' . $group;
		// phpcs:ignore
		$result = $wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $result ) ) {
			if ( $cache ) {
				Cache::set_global( $id, $result, 'infinite' );
			}
			return $result;
		}
		return [];
	}

	/**
	 * Get a time series.
	 *
	 * @param   array   $filter      The filter of the query.
	 * @param   boolean $cache       Has the query to be cached.
	 * @param   string  $extra_field Optional. The extra field to filter.
	 * @param   array   $extras      Optional. The extra values to match.
	 * @param   boolean $not         Optional. Exclude extra filter.
	 * @param   integer $limit       Optional. The number of results to return.
	 * @return  array   The time series.
	 * @since    1.0.0
	 */
	public static function get_time_series( $filter, $cache = true, $extra_field = '', $extras = [], $not = false, $limit = 0 ) {
		return self::get_grouped_list( $filter, '', $cache, $extra_field, $extras, $not, 'ORDER BY timestamp ASC', $limit );
	}

	/**
	 * Get the a grouped list.
	 *
	 * @param   array   $filter      The filter of the query.
	 * @param   string  $group       Optional. The group of the query.
	 * @param   boolean $cache       Optional. Has the query to be cached.
	 * @param   string  $extra_field Optional. The extra field to filter.
	 * @param   array   $extras      Optional. The extra values to match.
	 * @param   boolean $not         Optional. Exclude extra filter.
	 * @param   string  $order       Optional. The sort order of results.
	 * @param   integer $limit       Optional. The number of results to return.
	 * @param   boolean $statistics  Optional. The table to query.
	 * @return  array   The grouped list.
	 * @since    1.0.0
	 */
	public static function get_grouped_list( $filter, $group = '', $cache = true, $extra_field = '', $extras = [], $not = false, $order = '', $limit = 0, $statistics = true ) {
		if ( $statistics ) {
			$table  = self::$statistics;
			$fields = '*';
		} else {
			$table  = self::$usages;
			$fields = '*, SUM(success) + SUM(fail) AS sum_call, SUM(success) AS sum_success, SUM(fail) AS sum_fail';
		}
		// phpcs:ignore
		$id = Cache::id( __FUNCTION__ . $table . serialize( $filter ) . $group . $extra_field . serialize( $extras ) . ( $not ? 'no' : 'yes') . $order . (string) $limit);
		if ( $cache ) {
			$result = Cache::get_global( $id );
			if ( $result ) {
				return $result;
			}
		}
		if ( '' !== $group ) {
			$group = ' GROUP BY ' . $group;
		}
		$where_extra = '';
		if ( 0 < count( $extras ) && '' !== $extra_field ) {
			$where_extra = ' AND ' . $extra_field . ( $not ? ' NOT' : '' ) . " IN ( '" . implode( "', '", $extras ) . "' )";
		}
		global $wpdb;
		$sql = 'SELECT ' . $fields . ' FROM ' . $wpdb->base_prefix . $table . ' WHERE (' . implode( ' AND ', $filter ) . ')' . $where_extra . ' ' . $group . ' ' . $order . ( $limit > 0 ? ' LIMIT ' . $limit : '') .';';
		// phpcs:ignore
		$result = $wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $result ) ) {
			if ( $cache ) {
				Cache::set_global( $id, $result, 'infinite' );
			}
			return $result;
		}
		return [];
	}
}
