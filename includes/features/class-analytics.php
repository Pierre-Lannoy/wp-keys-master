<?php
/**
 * Device detector analytics
 *
 * Handles all analytics operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin\Feature;

use KeysMaster\System\GeoIP;
use KeysMaster\Plugin\Feature\Schema;
use KeysMaster\System\Blog;
use KeysMaster\System\Cache;
use KeysMaster\System\Date;
use KeysMaster\System\Conversion;
use KeysMaster\System\Role;

use KeysMaster\System\L10n;
use KeysMaster\System\Http;
use KeysMaster\System\Favicon;
use KeysMaster\System\Timezone;
use KeysMaster\System\UUID;
use Feather;
use KeysMaster\System\Environment;


/**
 * Define the analytics functionality.
 *
 * Handles all analytics operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Analytics {

	/**
	 * The dashboard type.
	 *
	 * @since  1.0.0
	 * @var    string    $title    The dashboard type.
	 */
	public $type = '';

	/**
	 * The start date.
	 *
	 * @since  1.0.0
	 * @var    string    $start    The start date.
	 */
	private $start = '';

	/**
	 * The end date.
	 *
	 * @since  1.0.0
	 * @var    string    $end    The end date.
	 */
	private $end = '';

	/**
	 * The period duration in days.
	 *
	 * @since  1.0.0
	 * @var    integer    $duration    The period duration in days.
	 */
	private $duration = 0;

	/**
	 * The timezone.
	 *
	 * @since  1.0.0
	 * @var    string    $timezone    The timezone.
	 */
	private $timezone = 'UTC';

	/**
	 * The main query filter.
	 *
	 * @since  1.0.0
	 * @var    array    $filter    The main query filter.
	 */
	private $filter = [];

	/**
	 * The query filter fro the previous range.
	 *
	 * @since  1.0.0
	 * @var    array    $previous    The query filter fro the previous range.
	 */
	private $previous = [];

	/**
	 * Is the start date today's date.
	 *
	 * @since  1.0.0
	 * @var    boolean    $today    Is the start date today's date.
	 */
	private $is_today = false;

	/**
	 * Colors for graphs.
	 *
	 * @since  1.0.0
	 * @var    array    $colors    The colors array.
	 */
	private $colors = [ '#73879C', '#3398DB', '#9B59B6', '#B2C326', '#FFA5A5', '#A5F8D3', '#FEE440', '#BDC3C6' ];

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param   string  $type    The type of analytics ().
	 * @param   string  $start   The start date.
	 * @param   string  $end     The end date.
	 * @param   boolean $reload  Is it a reload of an already displayed analytics.
	 * @since    1.0.0
	 */
	public function __construct( $type, $start, $end, $reload ) {
		if ( $start === $end ) {
			$this->filter[] = "timestamp='" . $start . "'";
		} else {
			$this->filter[] = "timestamp>='" . $start . "' and timestamp<='" . $end . "'";
		}
		$this->start    = $start;
		$this->end      = $end;
		$this->type     = $type;
		$this->timezone = Timezone::network_get();
		$datetime       = new \DateTime( 'now', $this->timezone );
		$this->is_today = ( $this->start === $datetime->format( 'Y-m-d' ) || $this->end === $datetime->format( 'Y-m-d' ) );
		$start          = new \DateTime( $this->start, $this->timezone );
		$end            = new \DateTime( $this->end, $this->timezone );
		$start->sub( new \DateInterval( 'P1D' ) );
		$end->sub( new \DateInterval( 'P1D' ) );
		$delta = $start->diff( $end, true );
		if ( $delta ) {
			$start->sub( $delta );
			$end->sub( $delta );
		}
		$this->duration = $delta->days + 1;
		if ( $start === $end ) {
			$this->previous[] = "timestamp='" . $start->format( 'Y-m-d' ) . "'";
		} else {
			$this->previous[] = "timestamp>='" . $start->format( 'Y-m-d' ) . "' and timestamp<='" . $end->format( 'Y-m-d' ) . "'";
		}
	}

	/**
	 * Query statistics table.
	 *
	 * @param   string $query   The query type.
	 * @param   mixed  $queried The query params.
	 * @return array  The result of the query, ready to encode.
	 * @since    1.0.0
	 */
	public function query( $query, $queried ) {
		switch ( $query ) {
			case 'kpi':
				return $this->query_kpi( $queried );
			case 'sites':
				return $this->query_list( 'sites' );
			case 'countries':
				return $this->query_list( 'countries' );
			case 'protos':
			case 'devices':
				return $this->query_pie( $query, (int) $queried );
			case 'main-chart':
				return $this->query_chart();
		}
		return [];
	}

	/**
	 * Query statistics pie.
	 *
	 * @param   string  $type    The type of pie.
	 * @param   integer $limit  The number to display.
	 * @return array  The result of the query, ready to encode.
	 * @since    1.0.0
	 */
	private function query_pie( $type, $limit ) {
		$uuid = UUID::generate_unique_id( 5 );
		switch ( $type ) {
			case 'protos':
				$group = 'channel';
				break;
			case 'devices':
				$group = 'device';
				break;
		}
		$data = Schema::get_grouped_list( $this->filter, $group, ! $this->is_today, '', [], false, 'ORDER BY sum_call DESC', 0, false );
		if ( $limit > count( $data ) ) {
			$limit = count( $data );
		}
		$val = 0;
		if ( 0 < count( $data ) ) {
			foreach ( $data as $row ) {
				$val += (int) $row['sum_call'];
			}
		}
		if ( 0 < count( $data ) && 0 !== $val ) {
			$total  = 0;
			$other  = 0;
			$values = [];
			$names  = [];
			foreach ( $data as $i => $row ) {
				$total        = $total + $row['sum_call'];
				$values[ $i ] = $row['sum_call'];
				if ( $limit <= $i ) {
					$other = $other + $row['sum_call'];
				}
				switch ( $type ) {
					case 'protos':
						$names[ $i ] = esc_html__( 'Rest API', 'keys-master' );
						if ( 'xmlrpc' === $row['channel'] ) {
							$names[ $i ] = esc_html__( 'XML-RPC', 'keys-master' );
						}
						break;
					case 'devices':
						if ( class_exists( '\PODeviceDetector\API\Device' ) ) {
							$device = \PODeviceDetector\API\Device::get( $row['device'] )->get_as_full_array();
							if ( array_key_exists( 'bot', $device ) ) {
								$names[ $i ] = $device['bot']['name'];
							} else {
								if ( array_key_exists( $device['client']['id'], $device ) ) {
									$names[ $i ] = $device[ $device['client']['id'] ]['name'] . ' ' . $device[ $device['client']['id'] ]['version'];
								} else {
									$names[ $i ] = esc_html__( 'Unknown', 'keys-master' );
								}
							}
						} else {
							$names[ $i ] = esc_html__( 'Unknown', 'keys-master' ) . ' (' . substr( $row['device'], 0, 20 ) . '…)';
						}
						break;
				}
			}
			$cpt    = 0;
			$labels = [];
			$series = [];
			while ( $cpt < $limit ) {
				if ( 0 < $total ) {
					$percent = round( 100 * $values[ $cpt ] / $total, 1 );
				} else {
					$percent = 100;
				}
				if ( 0.1 > $percent ) {
					$percent = 0.1;
				}
				$labels[] = $names [ $cpt ];
				$series[] = [
					'meta'  => $names [ $cpt ],
					'value' => (float) $percent,
				];
				++$cpt;
			}
			if ( 0 < $other ) {
				if ( 0 < $total ) {
					$percent = round( 100 * $other / $total, 1 );
				} else {
					$percent = 100;
				}
				if ( 0.1 > $percent ) {
					$percent = 0.1;
				}
				$labels[] = esc_html__( 'Other', 'keys-master' );
				$series[] = [
					'meta'  => esc_html__( 'Other', 'keys-master' ),
					'value' => (float) $percent,
				];
			}
			$result  = '<div class="pokm-pie-box">';
			$result .= '<div class="pokm-pie-graph">';
			$result .= '<div class="pokm-pie-graph-handler-120" id="pokm-pie-' . $type . '"></div>';
			$result .= '</div>';
			$result .= '<div class="pokm-pie-legend">';
			foreach ( $labels as $key => $label ) {
				$icon    = '<img style="width:12px;vertical-align:baseline;" src="' . Feather\Icons::get_base64( 'square', $this->colors[ $key ], $this->colors[ $key ] ) . '" />';
				$result .= '<div class="pokm-pie-legend-item">' . $icon . '&nbsp;&nbsp;' . $label . '</div>';
			}
			$result .= '';
			$result .= '</div>';
			$result .= '</div>';
			$result .= '<script>';
			$result .= 'jQuery(function ($) {';
			$result .= ' var data' . $uuid . ' = ' . wp_json_encode(
				[
					'labels' => $labels,
					'series' => $series,
				]
			) . ';';
			$result .= ' var tooltip' . $uuid . ' = Chartist.plugins.tooltip({percentage: true, appendToBody: true});';
			$result .= ' var option' . $uuid . ' = {width: 180, height: 180, showLabel: false, donut: true, donutWidth: "40%", startAngle: 270, plugins: [tooltip' . $uuid . ']};';
			$result .= ' new Chartist.Pie("#pokm-pie-' . $type . '", data' . $uuid . ', option' . $uuid . ');';
			$result .= '});';
			$result .= '</script>';
		} else {
			$result  = '<div class="pokm-pie-box">';
			$result .= '<div class="pokm-pie-graph" style="margin:0 !important;">';
			$result .= '<div class="pokm-pie-graph-nodata-handler-120" id="pokm-pie-' . $type . '"><span style="position: relative; top: 47px;">-&nbsp;' . esc_html__( 'No Data', 'keys-master' ) . '&nbsp;-</span></div>';
			$result .= '</div>';
			$result .= '';
			$result .= '</div>';
			$result .= '</div>';
		}
		return [ 'pokm-' . $type => $result ];
	}

	/**
	 * Query statistics chart.
	 *
	 * @return array The result of the query, ready to encode.
	 * @since    1.0.0
	 */
	private function query_chart() {
		$uuid                   = UUID::generate_unique_id( 5 );
		$query                  = Schema::get_time_series( $this->filter, ! $this->is_today, '', [], false );
		$item                   = [];
		$item['authentication'] = [ 'success', 'fail' ];
		$item['password']       = [ 'password' ];
		$item['turnover']       = [ 'created', 'revoked' ];
		$data                   = [];
		$series                 = [];
		$labels                 = [];
		$boundaries             = [];
		$json                   = [];
		foreach ( $item as $selector => $array ) {
			$boundaries[ $selector ] = [
				'max'    => 0,
				'factor' => 1,
				'order'  => $item[ $selector ],
			];
		}
		// Data normalization.
		if ( 0 !== count( $query ) ) {
			foreach ( $query as  $row ) {
				$datetime = new \DateTime( $row['timestamp'], new \DateTimeZone( 'UTC' ) );
				$datetime->setTimezone( $this->timezone );
				$record = [];
				foreach ( $row as $k => $v ) {
					if ( 'password' === $k ) {
						if ( 0 < $row['cnt'] ) {
							$record[ $k ] = (int) round( $v / $row['cnt'] );
						} else {
							$record[ $k ] = 0;
						}
					} elseif ( 'cnt' !== $k && 'timestamp' !== $k ) {
						$record[ $k ] = (int) $v;
					}
				}
				$data[ strtotime( $datetime->format( 'Y-m-d' ) . ' 12:00:00' ) ] = $record;
			}
			// Boundaries computation.
			foreach ( $data as $datum ) {
				foreach ( array_merge( $item['authentication'], $item['turnover'], $item['password'] ) as $field ) {
					foreach ( $item as $selector => $array ) {
						if ( in_array( $field, $array, true ) ) {
							if ( $boundaries[ $selector ]['max'] < $datum[ $field ] ) {
								$boundaries[ $selector ]['max'] = $datum[ $field ];
								if ( 1100 < $datum[ $field ] ) {
									$boundaries[ $selector ]['factor'] = 1000;
								}
								if ( 1100000 < $datum[ $field ] ) {
									$boundaries[ $selector ]['factor'] = 1000000;
								}
								$boundaries[ $selector ]['order'] = array_diff( $boundaries[ $selector ]['order'], [ $field ] );
								array_unshift( $boundaries[ $selector ]['order'], $field );
							}
							continue 2;
						}
					}
				}
			}
			// Series computation.
			foreach ( $data as $timestamp => $datum ) {
				// Series.
				$ts = 'new Date(' . (string) $timestamp . '000)';
				foreach ( array_merge( $item['authentication'], $item['turnover'], $item['password'] ) as $key ) {
					foreach ( $item as $selector => $array ) {
						if ( in_array( $key, $array, true ) ) {
							$series[ $key ][] = [
								'x' => $ts,
								'y' => round( $datum[ $key ] / $boundaries[ $selector ]['factor'], ( 1 === $boundaries[ $selector ]['factor'] ? 0 : 2 ) ),
							];
							continue 2;
						}
					}
				}
				// Labels.
				$labels[] = 'moment(' . $timestamp . '000).format("ll")';
			}
			// Result encoding.
			$shift    = 86400;
			$datetime = new \DateTime( $this->start . ' 00:00:00', $this->timezone );
			$offset   = $this->timezone->getOffset( $datetime );
			$datetime = $datetime->getTimestamp() + $offset;
			array_unshift( $labels, 'moment(' . (string) ( $datetime - $shift ) . '000).format("ll")' );
			$before   = [
				'x' => 'new Date(' . (string) ( $datetime - $shift ) . '000)',
				'y' => 'null',
			];
			$datetime = new \DateTime( $this->end . ' 23:59:59', $this->timezone );
			$offset   = $this->timezone->getOffset( $datetime );
			$datetime = $datetime->getTimestamp() + $offset;
			$after    = [
				'x' => 'new Date(' . (string) ( $datetime + $shift ) . '000)',
				'y' => 'null',
			];
			foreach ( array_merge( $item['authentication'], $item['turnover'], $item['password'] ) as $key ) {
				array_unshift( $series[ $key ], $before );
				$series[ $key ][] = $after;
			}
			// Users.
			foreach ( $item as $selector => $array ) {
				$serie = [];
				foreach ( $boundaries[ $selector ]['order'] as $field ) {
					switch ( $field ) {
						case 'success':
							$name = esc_html__( 'Successful authentications', 'keys-master' );
							break;
						case 'fail':
							$name = esc_html__( 'Failed authentications', 'keys-master' );
							break;
						case 'password':
							if ( Environment::is_wordpress_multisite() ) {
								$name = esc_html__( 'Network application passwords', 'keys-master' );
							} else {
								$name = esc_html__( 'Site application passwords', 'keys-master' );
							}
							break;
						case 'created':
							$name = esc_html__( 'Created', 'keys-master' );
							break;
						case 'revoked':
							$name = esc_html__( 'Revoked', 'keys-master' );
							break;
						default:
							$name = esc_html__( 'Unknown', 'keys-master' );
					}
					$serie[] = [
						'name' => $name,
						'data' => $series[ $field ],
					];
				}
				if ( 'turnover' === $selector ) {
					$json[ $selector ] = wp_json_encode(
						[
							'labels' => $labels,
							'series' => $serie,
						]
					);
				} else {
					$json[ $selector ] = wp_json_encode( [ 'series' => $serie ] );
				}
				$json[ $selector ] = str_replace( '"x":"new', '"x":new', $json[ $selector ] );
				$json[ $selector ] = str_replace( ')","y"', '),"y"', $json[ $selector ] );
				$json[ $selector ] = str_replace( '"null"', 'null', $json[ $selector ] );
				$json[ $selector ] = str_replace( '"labels":["moment', '"labels":[moment', $json[ $selector ] );
				$json[ $selector ] = str_replace( '","moment', ',moment', $json[ $selector ] );
				$json[ $selector ] = str_replace( '"],"series":', '],"series":', $json[ $selector ] );
				$json[ $selector ] = str_replace( '\\"', '"', $json[ $selector ] );
			}

			// Rendering.
			$divisor = $this->duration + 1;
			while ( 11 < $divisor ) {
				foreach ( [ 2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47, 53, 59, 61, 67, 71, 73, 79, 83, 89, 97, 101, 103, 107, 109, 113, 127, 131, 137, 139, 149, 151, 157, 163, 167, 173, 179, 181, 191, 193, 197, 199, 211, 223, 227, 229, 233, 239, 241, 251, 257, 263, 269, 271, 277, 281, 283, 293, 307, 311, 313, 317, 331, 337, 347, 349, 353, 359, 367, 373, 379, 383, 389, 397 ] as $divider ) {
					if ( 0 === $divisor % $divider ) {
						$divisor = $divisor / $divider;
						break;
					}
				}
			}
			$result  = '<div class="pokm-multichart-handler">';
			$result .= '<div class="pokm-multichart-item active" id="pokm-chart-authentication">';
			$result .= '</div>';
			$result .= '<script>';
			$result .= 'jQuery(function ($) {';
			$result .= ' var authentication_data' . $uuid . ' = ' . $json['authentication'] . ';';
			$result .= ' var authentication_tooltip' . $uuid . ' = Chartist.plugins.tooltip({percentage: false, appendToBody: true});';
			$result .= ' var authentication_option' . $uuid . ' = {';
			$result .= '  height: 300,';
			$result .= '  fullWidth: true,';
			$result .= '  showArea: true,';
			$result .= '  showLine: true,';
			$result .= '  showPoint: false,';
			$result .= '  plugins: [authentication_tooltip' . $uuid . '],';
			$result .= '  axisX: {labelOffset: {x: 3,y: 0},scaleMinSpace: 100, type: Chartist.FixedScaleAxis, divisor:' . $divisor . ', labelInterpolationFnc: function (value) {return moment(value).format("YYYY-MM-DD");}},';
			$result .= '  axisY: {type: Chartist.AutoScaleAxis, low: 0, labelInterpolationFnc: function (value) {return value.toString() + " ' . Conversion::number_shorten( $boundaries['authentication']['factor'], 0, true )['abbreviation'] . '";}},';
			$result .= ' };';
			$result .= ' new Chartist.Line("#pokm-chart-authentication", authentication_data' . $uuid . ', authentication_option' . $uuid . ');';
			$result .= '});';
			$result .= '</script>';
			$result .= '<div class="pokm-multichart-item" id="pokm-chart-password">';
			$result .= '</div>';
			$result .= '<script>';
			$result .= 'jQuery(function ($) {';
			$result .= ' var password_data' . $uuid . ' = ' . $json['password'] . ';';
			$result .= ' var password_tooltip' . $uuid . ' = Chartist.plugins.tooltip({percentage: false, appendToBody: true});';
			$result .= ' var password_option' . $uuid . ' = {';
			$result .= '  height: 300,';
			$result .= '  fullWidth: true,';
			$result .= '  showArea: true,';
			$result .= '  showLine: true,';
			$result .= '  showPoint: false,';
			$result .= '  plugins: [password_tooltip' . $uuid . '],';
			$result .= '  axisX: {labelOffset: {x: 3,y: 0},scaleMinSpace: 100, type: Chartist.FixedScaleAxis, divisor:' . $divisor . ', labelInterpolationFnc: function (value) {return moment(value).format("YYYY-MM-DD");}},';
			$result .= '  axisY: {type: Chartist.AutoScaleAxis, low: 0, labelInterpolationFnc: function (value) {return value.toString() + " ' . Conversion::number_shorten( $boundaries['password']['factor'], 0, true )['abbreviation'] . '";}},';
			$result .= ' };';
			$result .= ' new Chartist.Line("#pokm-chart-password", password_data' . $uuid . ', password_option' . $uuid . ');';
			$result .= '});';
			$result .= '</script>';
			$result .= '<div class="pokm-multichart-small-item" id="pokm-chart-turnover">';
			$result .= '<style>';
			$result .= '.pokm-multichart-small-item .ct-bar {stroke-width: 6px !important;stroke-opacity: 0.8 !important;}';
			$result .= '</style>';
			$result .= '</div>';
			$result .= '<script>';
			$result .= 'jQuery(function ($) {';
			$result .= ' var turnover_data' . $uuid . ' = ' . $json['turnover'] . ';';
			$result .= ' var turnover_tooltip' . $uuid . ' = Chartist.plugins.tooltip({justvalue: true, appendToBody: true});';
			$result .= ' var turnover_option' . $uuid . ' = {';
			$result .= '  height: 300,';
			$result .= '  seriesBarDistance: 8,';
			$result .= '  plugins: [turnover_tooltip' . $uuid . '],';
			$result .= '  axisX: {showGrid: false, labelOffset: {x: 18,y: 0}},';
			$result .= '  axisY: {showGrid: true, labelInterpolationFnc: function (value) {return value.toString() + " ' . Conversion::number_shorten( $boundaries['turnover']['factor'], 0, true )['abbreviation'] . '";}},';
			$result .= ' };';
			$result .= ' new Chartist.Bar("#pokm-chart-turnover", turnover_data' . $uuid . ', turnover_option' . $uuid . ');';
			$result .= '});';
			$result .= '</script>';
		} else {
			$result  = '<div class="pokm-multichart-handler">';
			$result .= '<div class="pokm-multichart-item active" id="pokm-chart-authentication">';
			$result .= $this->get_graph_placeholder_nodata( 274 );
			$result .= '</div>';
			$result .= '<div class="pokm-multichart-item" id="pokm-chart-password">';
			$result .= $this->get_graph_placeholder_nodata( 274 );
			$result .= '</div>';
			$result .= '<div class="pokm-multichart-item" id="pokm-chart-turnover">';
			$result .= $this->get_graph_placeholder_nodata( 274 );
			$result .= '</div>';
		}
		return [ 'pokm-main-chart' => $result ];
	}

	/**
	 * Query statistics table.
	 *
	 * @param   string $type    The type of list.
	 * @return array  The result of the query, ready to encode.
	 * @since    1.0.0
	 */
	private function query_list( $type ) {
		switch ( $type ) {
			case 'sites':
				$group = 'site';
				break;
			case 'countries':
				$group = 'country';
				break;
		}
		$data      = Schema::get_grouped_list( $this->filter, $group, ! $this->is_today, '', [], false, 'ORDER BY sum_call DESC', 0, false );
		$result    = '<table class="pokm-table">';
		$result   .= '<tr>';
		$result   .= '<th>&nbsp;</th>';
		$result   .= '<th>' . esc_html__( 'Success', 'keys-master' ) . '</th>';
		$result   .= '<th>' . esc_html__( 'Fail', 'keys-master' ) . '</th>';
		$result   .= '<th>' . esc_html__( 'TOTAL', 'keys-master' ) . '</th>';
		$result   .= '</tr>';
		$other_str = '';
		$geoip     = new GeoIP();
		foreach ( $data as $key => $row ) {
			switch ( $type ) {
				case 'sites':
					$name = '<img style="width:16px;vertical-align:bottom;" src="' . Favicon::get_base64( Blog::get_blog_url( $row['site'] ) ) . '" />&nbsp;&nbsp;<span class="pokm-table-text">' . Blog::get_blog_name( $row['site'] ) . '</span>';
					break;
				case 'countries':
					switch ( $row['country'] ) {
						case '--':
						case '00':
							$c = __( 'Undetectable', 'keys-master' );
							break;
						case '01':
							$c = __( 'Loopback', 'keys-master' );
							break;
						case 'A0':
							$c = __( 'Private network', 'keys-master' );
							break;
						case 'A1':
							$c = __( 'Anonymous proxy', 'keys-master' );
							break;
						case 'A2':
							$c = __( 'Satellite provider', 'keys-master' );
							break;
						default:
							$c = L10n::get_country_name( $row['country'] );
					}
					if ( $c === '' ) {
						$c = __( 'Unknown', 'keys-master' );
					}
					$name = $geoip->get_flag_from_country_code( $row['country'], '', 'width:14px;') . '&nbsp;&nbsp;<span class="pokm-table-text">' . $c . '</span>';
					break;
			}
			$result .= '<tr>';
			$result .= '<td data-th="">' . $name . '</td>';
			$result .= '<td data-th="' . esc_html__( 'Success', 'keys-master' ) . '">' . Conversion::number_shorten( $row['sum_success'], 2, false, '&nbsp;' ) . '</td>';
			$result .= '<td data-th="' . esc_html__( 'Fail', 'keys-master' ) . '">' . Conversion::number_shorten( $row['sum_fail'], 2, false, '&nbsp;' ) . '</td>';
			$result .= '<td data-th="' . esc_html__( 'TOTAL', 'keys-master' ) . '">' . Conversion::number_shorten( $row['sum_call'], 2, false, '&nbsp;' ) . '</td>';
			$result .= '</tr>';
		}
		$result .= $other_str . '</table>';
		return [ 'pokm-' . $type => $result ];
	}

	/**
	 * Query all kpis in statistics table.
	 *
	 * @param   array   $args   Optional. The needed args.
	 * @return array  The KPIs ready to send.
	 * @since    1.0.0
	 */
	public static function get_status_kpi_collection( $args = [] ) {
		$result['meta'] = [
			'plugin' => POKM_PRODUCT_NAME . ' ' . POKM_VERSION,
			'period' => date( 'Y-m-d' ),
		];
		$result['data'] = [];
		$kpi            = new static( 'summary', date( 'Y-m-d' ), date( 'Y-m-d' ), false );
		foreach ( [ 'success', 'password', 'created', 'revoked', 'adoption', 'rate' ] as $query ) {
			$data = $kpi->query_kpi( $query, false );
			switch ( $query ) {
				case 'success':
					$val                       = Conversion::number_shorten( $data['kpi-bottom-success'], 0, true );
					$result['data']['success'] = [
						'name'        => esc_html_x( 'Auth. Success', 'Noun - Number of successful authentications.', 'keys-master' ),
						'short'       => esc_html_x( 'Auth', 'Noun - Short (max 4 char) - Number of successful authentications.', 'keys-master' ),
						'description' => esc_html__( 'Successful authentications.', 'keys-master' ),
						'dimension'   => 'none',
						'ratio'       => [
							'raw'      => round( $data['kpi-main-success'] / 100, 6 ),
							'percent'  => round( $data['kpi-main-success'] ?? 0, 2 ),
							'permille' => round( $data['kpi-main-success'] * 10, 2 ),
						],
						'variation'   => [
							'raw'      => round( $data['kpi-index-success'] / 100, 6 ),
							'percent'  => round( $data['kpi-index-success'] ?? 0, 2 ),
							'permille' => round( $data['kpi-index-success'] * 10, 2 ),
						],
						'value'       => [
							'raw'   => $data['kpi-bottom-success'],
							'human' => $val['value'] . $val['abbreviation'],
						],
						'metrics'     => [
							'name'  => 'authentication_success_today',
							'desc'  => 'Ratio of successful authentication today - [percent]',
							'value' => (float) ( $data['kpi-main-success'] / 100.0 ),
							'type'  => 'gauge',
						],
					];
					break;
				case 'password':
					$val                        = Conversion::number_shorten( $data['kpi-main-password'], 0, true );
					$result['data']['password'] = [
						'name'        => esc_html_x( 'Passwords', 'Noun - Number of application passwords.', 'keys-master' ),
						'short'       => esc_html_x( 'Pwd.', 'Noun - Short (max 4 char) - Number of application passwords.', 'keys-master' ),
						'description' => esc_html__( 'Application passwords.', 'keys-master' ),
						'dimension'   => 'none',
						'ratio'       => null,
						'variation'   => [
							'raw'      => round( $data['kpi-index-password'] / 100, 6 ),
							'percent'  => round( $data['kpi-index-password'] ?? 0, 2 ),
							'permille' => round( $data['kpi-index-password'] * 10, 2 ),
						],
						'value'       => [
							'raw'   => $data['kpi-main-password'],
							'human' => $val['value'] . $val['abbreviation'],
						],
						'metrics'     => [
							'name'  => 'password_total',
							'desc'  => 'Number of registered application passwords - [count]',
							'value' => (int) $data['kpi-main-password'],
							'type'  => 'gauge',
						],
					];
					break;
				case 'revoked':
					$val                       = Conversion::number_shorten( $data['kpi-main-revoked'], 0, true );
					$result['data']['revoked'] = [
						'name'        => esc_html_x( 'Revoked', 'Noun - Number of revoked application passwords.', 'keys-master' ),
						'short'       => esc_html_x( 'Rvk.', 'Noun - Short (max 4 char) - Number of revoked application passwords.', 'keys-master' ),
						'description' => esc_html__( 'Revoked application passwords.', 'keys-master' ),
						'dimension'   => 'none',
						'ratio'       => null,
						'variation'   => [
							'raw'      => round( $data['kpi-index-revoked'] / 100, 6 ),
							'percent'  => round( $data['kpi-index-revoked'] ?? 0, 2 ),
							'permille' => round( $data['kpi-index-revoked'] * 10, 2 ),
						],
						'value'       => [
							'raw'   => $data['kpi-main-revoked'],
							'human' => $val['value'] . $val['abbreviation'],
						],
						'metrics'     => [
							'name'  => 'password_revoked_today',
							'desc'  => 'Number of revoked application passwords today - [count]',
							'value' => (int) $data['kpi-main-revoked'],
							'type'  => 'counter',
						],
					];
					break;
				case 'created':
					$val                       = Conversion::number_shorten( $data['kpi-main-created'], 0, true );
					$result['data']['created'] = [
						'name'        => esc_html_x( 'Created', 'Noun - Number of created application passwords.' ),
						'short'       => esc_html_x( 'Crd.', 'Noun - Short (max 4 char) - Number of created application passwords.', 'keys-master' ),
						'description' => esc_html__( 'Created application passwords.', 'keys-master' ),
						'dimension'   => 'none',
						'ratio'       => null,
						'variation'   => [
							'raw'      => round( $data['kpi-index-created'] / 100, 6 ),
							'percent'  => round( $data['kpi-index-created'] ?? 0, 2 ),
							'permille' => round( $data['kpi-index-created'] * 10, 2 ),
						],
						'value'       => [
							'raw'   => $data['kpi-main-created'],
							'human' => $val['value'] . $val['abbreviation'],
						],
						'metrics'     => [
							'name'  => 'password_created_today',
							'desc'  => 'Number of created application passwords today - [count]',
							'value' => (int) $data['kpi-main-created'],
							'type'  => 'counter',
						],
					];
					break;
				case 'adoption':
					$val                        = Conversion::number_shorten( $data['kpi-bottom-adoption'], 0, true );
					$result['data']['adoption'] = [
						'name'        => esc_html_x( 'Adoption', 'Noun - Application passwords adoption.', 'keys-master' ),
						'short'       => esc_html_x( 'Adp.', 'Noun - Short (max 4 char) - Application passwords adoption.', 'keys-master' ),
						'description' => esc_html__( 'Users having set at least one application password.', 'keys-master' ),
						'dimension'   => 'none',
						'ratio'       => [
							'raw'      => round( $data['kpi-main-adoption'] / 100, 6 ),
							'percent'  => round( $data['kpi-main-adoption'] ?? 0, 2 ),
							'permille' => round( $data['kpi-main-adoption'] * 10, 2 ),
						],
						'variation'   => [
							'raw'      => round( $data['kpi-main-adoption'] / 100, 6 ),
							'percent'  => round( $data['kpi-main-adoption'] ?? 0, 2 ),
							'permille' => round( $data['kpi-main-adoption'] * 10, 2 ),
						],
						'value'       => [
							'raw'   => $data['kpi-bottom-adoption'],
							'human' => $val['value'] . $val['abbreviation'],
						],
						'metrics'     => [
							'name'  => 'password_adoption',
							'desc'  => 'Ratio of users having set at least one application password - [percent]',
							'value' => (float) ( $data['kpi-main-adoption'] / 100.0 ),
							'type'  => 'gauge',
						],
					];
					break;
				case 'rate':
					$val                    = Conversion::number_shorten( $data['kpi-bottom-rate'], 0, true );
					$result['data']['rate'] = [
						'name'        => esc_html_x( 'Usage', 'Noun - Number of application passwords usage.', 'keys-master' ),
						'short'       => esc_html_x( 'Usg.', 'Noun - Short (max 4 char) - Number of application passwords usage.', 'keys-master' ),
						'description' => esc_html__( 'Application passwords usage.', 'keys-master' ),
						'dimension'   => 'none',
						'ratio'       => null,
						'variation'   => [
							'raw'      => round( $data['kpi-index-rate'] / 100, 6 ),
							'percent'  => round( $data['kpi-index-rate'] ?? 0, 2 ),
							'permille' => round( $data['kpi-index-rate'] * 10, 2 ),
						],
						'value'       => [
							'raw'   => $data['kpi-bottom-rate'],
							'human' => $val['value'] . $val['abbreviation'],
						],
						'metrics'     => [
							'name'  => 'password_usage_today',
							'desc'  => 'Application passwords usage today - [count]',
							'value' => (int) $data['kpi-bottom-rate'],
							'type'  => 'counter',
						],
					];
					break;
			}
		}
		$result['assets'] = [];
		return $result;
	}

	/**
	 * Query statistics table.
	 *
	 * @param   mixed       $queried The query params.
	 * @param   boolean     $chart   Optional, return the chart if true, only the data if false;
	 * @return array  The result of the query, ready to encode.
	 * @since    1.0.0
	 */
	public function query_kpi( $queried, $chart = true ) {
		$result = [];
		$data   = Schema::get_grouped_kpi( $this->filter, '', ! $this->is_today );
		$pdata  = Schema::get_grouped_kpi( $this->previous );
		// RATES
		if ( 'rate' === $queried ) {
			$current  = 0.0;
			$total    = 0.0;
			$previous = 0.0;
			$datetime = new \DateTime( 'now', Timezone::network_get() );
			$today    = $datetime->format( 'Y-m-d' );
			$ratio    = 24.0 / ( (float) $datetime->format( 'H' ) + ( (float) $datetime->format( 'i' ) / 60 ) );
			foreach ( $data as $row ) {
				if ( $today === $row['timestamp'] ) {
					$current = $current + ( $ratio * ( (float) $row['success'] + (float) $row['fail'] ) );
				} else {
					$current = $current + (float) $row['success'] + (float) $row['fail'];
				}
				$total = $total + (float) $row['success'] + (float) $row['fail'];
			}
			foreach ( $pdata as $row ) {
				$previous = $previous + (float) $row['success'] + (float) $row['fail'];
			}
			if ( 0 !== $this->duration ) {
				$current  = $current / $this->duration;
				$previous = $previous / $this->duration;
			}
			if ( ! $chart ) {
				$result[ 'kpi-main-' . $queried ] = (int) $current;
				if ( 0.0 !== $current && 0.0 !== $previous ) {
					$result[ 'kpi-index-' . $queried ] = round( 100 * ( $current - $previous ) / $previous, 4 );
				} else {
					$result[ 'kpi-index-' . $queried ] = null;
				}
				$result[ 'kpi-bottom-' . $queried ] = (int) $current;
				return $result;
			}
			$val = (int) $total;
			if ( 0 === $val ) {
				$txt = esc_html__( 'no call', 'keys-master' );
			} else {
				$txt = sprintf( esc_html( _n( '%s call', '%s calls', $val ) ), Conversion::number_shorten( $val, 0, false, '&nbsp;' ) );
			}
			$result[ 'kpi-bottom-' . $queried ] = '<span class="pokm-kpi-large-bottom-text">' . $txt . '</span>';
			$main                               = Conversion::rate_shorten( $current, 2, true );
			$result[ 'kpi-main-' . $queried ]   = $main['value'] . '&nbsp;<span class="pokm-kpi-large-bottom-sup">/' . $main['abbreviation'] . '</span>';
			if ( 0.0 !== $current && 0.0 !== $previous ) {
				$percent = round( 100 * ( $current - $previous ) / $previous, 1 );
				if ( 0.1 > abs( $percent ) ) {
					$percent = 0;
				}
				$result[ 'kpi-index-' . $queried ] = '<span style="color:' . ( 0 <= $percent ? '#18BB9C' : '#E74C3C' ) . ';">' . ( 0 < $percent ? '+' : '' ) . $percent . '&nbsp;%</span>';
			} elseif ( 0.0 === $previous && 0.0 !== $current ) {
				$result[ 'kpi-index-' . $queried ] = '<span style="color:#18BB9C;">+∞</span>';
			} elseif ( 0.0 !== $previous && 100.0 !== $previous && 0.0 === $current ) {
				$result[ 'kpi-index-' . $queried ] = '<span style="color:#E74C3C;">-∞</span>';
			}
		}
		// COUNTS
		if ( 'password' === $queried || 'created' === $queried || 'revoked' === $queried ) {
			$current  = 0.0;
			$previous = 0.0;
			switch ( $queried ) {
				case 'password':
					foreach ( $data as $row ) {
						$current = $current + (float) ceil( $row['password'] / ( 0 < $row['cnt'] ? $row['cnt'] : 1 ) );
					}
					foreach ( $pdata as $row ) {
						$previous = $previous + (float) ceil( $row['password'] / ( 0 < $row['cnt'] ? $row['cnt'] : 1 ) );
					}
					if ( 0 < count( $data ) ) {
						$current = $current / count( $data );
					}
					if ( 0 < count( $pdata ) ) {
						$previous = $previous / count( $pdata );
					}
					break;
				default:
					foreach ( $data as $row ) {
						$current = $current + (float) $row[ $queried ];
					}
					foreach ( $pdata as $row ) {
						$previous = $previous + (float) $row[ $queried ];
					}
					break;
			}
			$current  = (int) ceil( $current );
			$previous = (int) ceil( $previous );
			if ( ! $chart ) {
				$result[ 'kpi-main-' . $queried ] = (int) $current;
				if ( 0 !== $current && 0 !== $previous ) {
					$result[ 'kpi-index-' . $queried ] = round( 100 * ( $current - $previous ) / $previous, 4 );
				} else {
					$result[ 'kpi-index-' . $queried ] = null;
				}
				$result[ 'kpi-bottom-' . $queried ] = null;
				return $result;
			}
			$result[ 'kpi-main-' . $queried ] = Conversion::number_shorten( (int) $current, 1, false, '&nbsp;' );
			if ( 0 !== $current && 0 !== $previous ) {
				$percent = round( 100 * ( $current - $previous ) / $previous, 1 );
				if ( 0.1 > abs( $percent ) ) {
					$percent = 0;
				}
				$result[ 'kpi-index-' . $queried ] = '<span style="color:' . ( 0 <= $percent ? '#18BB9C' : '#E74C3C' ) . ';">' . ( 0 < $percent ? '+' : '' ) . $percent . '&nbsp;%</span>';
			} elseif ( 0 === $previous && 0 !== $current ) {
				$result[ 'kpi-index-' . $queried ] = '<span style="color:#18BB9C;">+∞</span>';
			} elseif ( 0 !== $previous && 100 !== $previous && 0 === $current ) {
				$result[ 'kpi-index-' . $queried ] = '<span style="color:#E74C3C;">-∞</span>';
			}
		}
		// RATIOS
		if ( 'success' === $queried || 'adoption' === $queried ) {
			$base_value  = 0.0;
			$pbase_value = 0.0;
			$data_value  = 0.0;
			$pdata_value = 0.0;
			$current     = 0.0;
			$previous    = 0.0;
			$val         = null;
			switch ( $queried ) {
				case 'success':
					foreach ( $data as $row ) {
						$base_value = $base_value + (float) $row['success'] + (float) $row['fail'];
						$data_value = $data_value + (float) $row['success'];
					}
					foreach ( $pdata as $row ) {
						$pbase_value = $pbase_value + (float) $row['success'] + (float) $row['fail'];
						$pdata_value = $pdata_value + (float) $row['success'];
					}
					$val = (int) $data_value;
					if ( 0 === $val ) {
						$txt = esc_html__( 'no successful authentication', 'keys-master' );
					} else {
						$txt = sprintf( esc_html( _n( '%s successful auth.', '%s successful auth.', $val, 'keys-master' ) ), Conversion::number_shorten( $val, 2, false, '&nbsp;' ) );
					}
					$result[ 'kpi-bottom-' . $queried ] = '<span class="pokm-kpi-large-bottom-text">' . $txt . '</span>';
					break;
				case 'adoption':
					foreach ( $data as $row ) {
						$base_value = $base_value + ( 0 !== (int) $row['cnt'] ? (float) $row['user'] / (float) $row['cnt'] : 0.0 );
						$data_value = $data_value + ( 0 !== (int) $row['cnt'] ? (float) $row['adopt'] / (float) $row['cnt'] : 0.0 );
					}
					foreach ( $pdata as $row ) {
						$pbase_value = $pbase_value + ( 0 !== (int) $row['cnt'] ? (float) $row['user'] / (float) $row['cnt'] : 0.0 );
						$pdata_value = $pdata_value + ( 0 !== (int) $row['cnt'] ? (float) $row['adopt'] / (float) $row['cnt'] : 0.0 );
					}
					$val = (int) $data_value;
					if ( 0 === $val ) {
						$txt = esc_html__( 'no adoption', 'keys-master' );
					} else {
						$txt = sprintf( esc_html( _n( '%s user', '%s users', (int) ( $val / $this->duration ), 'keys-master' ) ), Conversion::number_shorten( $val / $this->duration, 2, false, '&nbsp;' ) );
					}
					$result[ 'kpi-bottom-' . $queried ] = '<span class="pokm-kpi-large-bottom-text">' . $txt . '</span>';
					break;
			}
			if ( 0.0 !== $base_value && 0.0 !== $data_value ) {
				$current                          = 100 * $data_value / $base_value;
				$result[ 'kpi-main-' . $queried ] = round( $current, $chart ? 1 : 4 );
			} else {
				if ( 0.0 !== $data_value ) {
					$result[ 'kpi-main-' . $queried ] = 100;
				} elseif ( 0.0 !== $base_value ) {
					$result[ 'kpi-main-' . $queried ] = 0;
				} else {
					$result[ 'kpi-main-' . $queried ] = null;
				}
			}
			if ( 0.0 !== $pbase_value && 0.0 !== $pdata_value ) {
				$previous = 100 * $pdata_value / $pbase_value;
			} else {
				if ( 0.0 !== $pdata_value ) {
					$previous = 100.0;
				}
			}
			if ( 0.0 !== $current && 0.0 !== $previous ) {
				$result[ 'kpi-index-' . $queried ] = round( 100 * ( $current - $previous ) / $previous, 4 );
			} else {
				$result[ 'kpi-index-' . $queried ] = null;
			}
			if ( ! $chart ) {
				$result[ 'kpi-bottom-' . $queried ] = $val;
				return $result;
			}
			if ( isset( $result[ 'kpi-main-' . $queried ] ) ) {
				$result[ 'kpi-main-' . $queried ] = $result[ 'kpi-main-' . $queried ] . '&nbsp;%';
			} else {
				$result[ 'kpi-main-' . $queried ] = '-';
			}
			if ( 0.0 !== $current && 0.0 !== $previous ) {
				$percent = round( 100 * ( $current - $previous ) / $previous, 1 );
				if ( 0.1 > abs( $percent ) ) {
					$percent = 0;
				}
				$result[ 'kpi-index-' . $queried ] = '<span style="color:' . ( 0 <= $percent ? '#18BB9C' : '#E74C3C' ) . ';">' . ( 0 < $percent ? '+' : '' ) . $percent . '&nbsp;%</span>';
			} elseif ( 0.0 === $previous && 0.0 !== $current ) {
				$result[ 'kpi-index-' . $queried ] = '<span style="color:#18BB9C;">+∞</span>';
			} elseif ( 0.0 !== $previous && 100 !== $previous && 0.0 === $current ) {
				$result[ 'kpi-index-' . $queried ] = '<span style="color:#E74C3C;">-∞</span>';
			}
		}
		return $result;
	}

	/**
	 * Get the title bar.
	 *
	 * @return string  The bar ready to print.
	 * @since    1.0.0
	 */
	public function get_title_bar() {
		$subtitle = '';
		$title    = esc_html__( 'Main Summary', 'keys-master' );
		$result   = '<div class="pokm-box pokm-box-full-line">';
		$result  .= '<span class="pokm-title">' . $title . '</span>';
		$result  .= '<span class="pokm-subtitle">' . $subtitle . '</span>';
		$result  .= '<span class="pokm-datepicker">' . $this->get_date_box() . '</span>';
		$result  .= '</div>';
		return $result;
	}

	/**
	 * Get the KPI bar.
	 *
	 * @return string  The bar ready to print.
	 * @since    1.0.0
	 */
	public function get_kpi_bar() {
		$result  = '<div class="pokm-box pokm-box-full-line">';
		$result .= '<div class="pokm-kpi-bar">';
		$result .= '<div class="pokm-kpi-large">' . $this->get_large_kpi( 'success' ) . '</div>';
		$result .= '<div class="pokm-kpi-large">' . $this->get_large_kpi( 'password' ) . '</div>';
		$result .= '<div class="pokm-kpi-large">' . $this->get_large_kpi( 'created' ) . '</div>';
		$result .= '<div class="pokm-kpi-large">' . $this->get_large_kpi( 'revoked' ) . '</div>';
		$result .= '<div class="pokm-kpi-large">' . $this->get_large_kpi( 'adoption' ) . '</div>';
		$result .= '<div class="pokm-kpi-large">' . $this->get_large_kpi( 'rate' ) . '</div>';
		$result .= '</div>';
		$result .= '</div>';
		return $result;
	}

	/**
	 * Get the main chart.
	 *
	 * @return string  The main chart ready to print.
	 * @since    1.0.0
	 */
	public function get_main_chart() {
		if ( 1 < $this->duration ) {
			$help_authentication = esc_html__( 'Authentications.', 'keys-master' );
			$help_password       = esc_html__( 'Application passwords.', 'keys-master' );
			$help_turnover       = esc_html__( 'Operations.', 'keys-master' );
			$detail              = '<span class="pokm-chart-button not-ready left" id="pokm-chart-button-authentication" data-position="left" data-tooltip="' . $help_authentication . '"><img style="width:12px;vertical-align:baseline;" src="' . Feather\Icons::get_base64( 'log-in', 'none', '#73879C' ) . '" /></span>';
			$detail             .= '&nbsp;&nbsp;&nbsp;<span class="pokm-chart-button not-ready left" id="pokm-chart-button-password" data-position="left" data-tooltip="' . $help_password . '"><img style="width:12px;vertical-align:baseline;" src="' . Feather\Icons::get_base64( 'key', 'none', '#73879C' ) . '" /></span>';
			$detail             .= '&nbsp;&nbsp;&nbsp;<span class="pokm-chart-button not-ready left" id="pokm-chart-button-turnover" data-position="left" data-tooltip="' . $help_turnover . '"><img style="width:12px;vertical-align:baseline;" src="' . Feather\Icons::get_base64( 'shield', 'none', '#73879C' ) . '" /></span>';
			$result              = '<div class="pokm-row">';
			$result             .= '<div class="pokm-box pokm-box-full-line">';
			$result             .= '<div class="pokm-module-title-bar"><span class="pokm-module-title">' . esc_html__( 'Metrics Variations', 'keys-master' ) . '<span class="pokm-module-more">' . $detail . '</span></span></div>';
			$result             .= '<div class="pokm-module-content" id="pokm-main-chart">' . $this->get_graph_placeholder( 274 ) . '</div>';
			$result             .= '</div>';
			$result             .= '</div>';
			$result             .= $this->get_refresh_script(
				[
					'query'   => 'main-chart',
					'queried' => 0,
				]
			);
			return $result;
		} else {
			return '';
		}
	}

	/**
	 * Get a large kpi box.
	 *
	 * @param   string $kpi     The kpi to render.
	 * @return string  The box ready to print.
	 * @since    1.0.0
	 */
	private function get_large_kpi( $kpi ) {
		switch ( $kpi ) {
			case 'success':
				$icon  = Feather\Icons::get_base64( 'log-in', 'none', '#73879C' );
				$title = esc_html_x( 'Auth. Success', 'Noun - Number of successful authentication.', 'keys-master' );
				$help  = esc_html__( 'Ratio of successful authentication.', 'keys-master' );
				break;
			case 'password':
				$icon  = Feather\Icons::get_base64( 'key', 'none', '#73879C' );
				$title = esc_html_x( 'Passwords', 'Noun - Number of application passwords.', 'keys-master' );
				$help  = esc_html__( 'Number of application passwords.', 'keys-master' );
				break;
			case 'created':
				$icon  = Feather\Icons::get_base64( 'shield', 'none', '#73879C' );
				$title = esc_html_x( 'Created', 'Noun - Number of created application passwords.', 'keys-master' );
				$help  = esc_html__( 'Number of created application passwords.', 'keys-master' );
				break;
			case 'revoked':
				$icon  = Feather\Icons::get_base64( 'shield-off', 'none', '#73879C' );
				$title = esc_html_x( 'Revoked', 'Noun - Number of revoked application passwords.', 'keys-master' );
				$help  = esc_html__( 'Number of revoked application passwords.', 'keys-master' );
				break;
			case 'adoption':
				$icon  = Feather\Icons::get_base64( 'user-check', 'none', '#73879C' );
				$title = esc_html_x( 'Adoption', 'Noun - Application passwords adoption.', 'keys-master' );
				$help  = esc_html__( 'Ratio of users having set at least one application password.', 'keys-master' );
				break;
			case 'rate':
				$icon  = Feather\Icons::get_base64( 'activity', 'none', '#73879C' );
				$title = esc_html_x( 'Usage Rate', 'Noun - Number of application passwords usage.', 'keys-master' );
				$help  = esc_html__( 'Number of application passwords usage.', 'keys-master' );
				break;
		}
		$top       = '<img style="width:12px;vertical-align:baseline;" src="' . $icon . '" />&nbsp;&nbsp;<span style="cursor:help;" class="pokm-kpi-large-top-text bottom" data-position="bottom" data-tooltip="' . $help . '">' . $title . '</span>';
		$indicator = '&nbsp;';
		$bottom    = '<span class="pokm-kpi-large-bottom-text">&nbsp;</span>';
		$result    = '<div class="pokm-kpi-large-top">' . $top . '</div>';
		$result   .= '<div class="pokm-kpi-large-middle"><div class="pokm-kpi-large-middle-left" id="kpi-main-' . $kpi . '">' . $this->get_value_placeholder() . '</div><div class="pokm-kpi-large-middle-right" id="kpi-index-' . $kpi . '">' . $indicator . '</div></div>';
		$result   .= '<div class="pokm-kpi-large-bottom" id="kpi-bottom-' . $kpi . '">' . $bottom . '</div>';
		$result   .= $this->get_refresh_script(
			[
				'query'   => 'kpi',
				'queried' => $kpi,
			]
		);
		return $result;
	}

	/**
	 * Get the proto box.
	 *
	 * @return string  The box ready to print.
	 * @since    1.0.0
	 */
	public function get_proto_pie() {
		$result  = '<div class="pokm-50-module-left">';
		$result .= '<div class="pokm-module-title-bar"><span class="pokm-module-title">' . esc_html__( 'Channels', 'keys-master' ) . '</span></div>';
		$result .= '<div class="pokm-module-content" id="pokm-protos">' . $this->get_graph_placeholder( 200 ) . '</div>';
		$result .= '</div>';
		$result .= $this->get_refresh_script(
			[
				'query'   => 'protos',
				'queried' => 2,
			]
		);
		return $result;
	}

	/**
	 * Get the device box.
	 *
	 * @return string  The box ready to print.
	 * @since    1.0.0
	 */
	public function get_device_pie() {
		$result  = '<div class="pokm-50-module-right">';
		$result .= '<div class="pokm-module-title-bar"><span class="pokm-module-title">' . esc_html__( 'Top clients', 'keys-master' ) . '</span></div>';
		$result .= '<div class="pokm-module-content" id="pokm-devices">' . $this->get_graph_placeholder( 200 ) . '</div>';
		$result .= '</div>';
		$result .= $this->get_refresh_script(
			[
				'query'   => 'devices',
				'queried' => 7,
			]
		);
		return $result;
	}

	/**
	 * Get the sites list.
	 *
	 * @return string  The table ready to print.
	 * @since    1.0.0
	 */
	public function get_sites_list() {
		$result  = '<div class="pokm-box pokm-box-full-line">';
		$result .= '<div class="pokm-module-title-bar"><span class="pokm-module-title">' . esc_html__( 'Sites Breakdown', 'keys-master' ) . '</span></div>';
		$result .= '<div class="pokm-module-content" id="pokm-sites">' . $this->get_graph_placeholder( 200 ) . '</div>';
		$result .= '</div>';
		$result .= $this->get_refresh_script(
			[
				'query'   => 'sites',
				'queried' => 0,
			]
		);
		return $result;
	}

	/**
	 * Get the countries list.
	 *
	 * @return string  The table ready to print.
	 * @since    1.0.0
	 */
	public function get_countries_list() {
		$result  = '<div class="pokm-box pokm-box-full-line">';
		$result .= '<div class="pokm-module-title-bar"><span class="pokm-module-title">' . esc_html__( 'Countries Breakdown', 'keys-master' ) . '</span></div>';
		$result .= '<div class="pokm-module-content" id="pokm-countries">' . $this->get_graph_placeholder( 200 ) . '</div>';
		$result .= '</div>';
		$result .= $this->get_refresh_script(
			[
				'query'   => 'countries',
				'queried' => 0,
			]
		);
		return $result;
	}

	/**
	 * Get a placeholder for graph.
	 *
	 * @param   integer $height The height of the placeholder.
	 * @return string  The placeholder, ready to print.
	 * @since    1.0.0
	 */
	private function get_graph_placeholder( $height ) {
		return '<p style="text-align:center;line-height:' . $height . 'px;"><img style="width:40px;vertical-align:middle;" src="' . POKM_ADMIN_URL . 'medias/bars.svg" /></p>';
	}

	/**
	 * Get a placeholder for graph with no data.
	 *
	 * @param   integer $height The height of the placeholder.
	 * @return string  The placeholder, ready to print.
	 * @since    1.0.0
	 */
	private function get_graph_placeholder_nodata( $height ) {
		return '<p style="color:#73879C;text-align:center;line-height:' . $height . 'px;">' . esc_html__( 'No Data', 'keys-master' ) . '</p>';
	}

	/**
	 * Get a placeholder for value.
	 *
	 * @return string  The placeholder, ready to print.
	 * @since    1.0.0
	 */
	private function get_value_placeholder() {
		return '<img style="width:26px;vertical-align:middle;" src="' . POKM_ADMIN_URL . 'medias/three-dots.svg" />';
	}

	/**
	 * Get refresh script.
	 *
	 * @param   array $args Optional. The args for the ajax call.
	 * @return string  The script, ready to print.
	 * @since    1.0.0
	 */
	private function get_refresh_script( $args = [] ) {
		$result  = '<script>';
		$result .= 'jQuery(document).ready( function($) {';
		$result .= ' var data = {';
		$result .= '  action:"pokm_get_stats",';
		$result .= '  nonce:"' . wp_create_nonce( 'ajax_pose' ) . '",';
		foreach ( $args as $key => $val ) {
			$s = '  ' . $key . ':';
			if ( is_string( $val ) ) {
				$s .= '"' . $val . '"';
			} elseif ( is_numeric( $val ) ) {
				$s .= $val;
			} elseif ( is_bool( $val ) ) {
				$s .= $val ? 'true' : 'false';
			}
			$result .= $s . ',';
		}
		$result .= '  type:"' . $this->type . '",';
		$result .= '  start:"' . $this->start . '",';
		$result .= '  end:"' . $this->end . '",';
		$result .= ' };';
		$result .= ' $.post(ajaxurl, data, function(response) {';
		$result .= ' var val = JSON.parse(response);';
		$result .= ' $.each(val, function(index, value) {$("#" + index).html(value);});';
		if ( array_key_exists( 'query', $args ) && 'main-chart' === $args['query'] ) {
			$result .= '$(".pokm-chart-button").removeClass("not-ready");';
			$result .= '$("#pokm-chart-button-authentication").addClass("active");';
		}
		$result .= ' });';
		$result .= '});';
		$result .= '</script>';
		return $result;
	}

	/**
	 * Get the url.
	 *
	 * @param   array   $exclude Optional. The args to exclude.
	 * @param   array   $replace Optional. The args to replace or add.
	 * @param   boolean $escape  Optional. Forces url escaping.
	 * @return string  The url.
	 * @since    1.0.0
	 */
	private function get_url( $exclude = [], $replace = [], $escape = true ) {
		$params          = [];
		$params['type']  = $this->type;
		$params['start'] = $this->start;
		$params['end']   = $this->end;
		foreach ( $exclude as $arg ) {
			unset( $params[ $arg ] );
		}
		foreach ( $replace as $key => $arg ) {
			$params[ $key ] = $arg;
		}
		$url = admin_url( 'admin.php?page=pokm-viewer' );
		foreach ( $params as $key => $arg ) {
			if ( '' !== $arg ) {
				$url .= '&' . $key . '=' . rawurlencode( $arg );
			}
		}
		$url = str_replace( '"', '\'\'', $url );
		if ( $escape ) {
			$url = esc_url( $url );
		}
		return $url;
	}

	/**
	 * Get a date picker box.
	 *
	 * @return string  The box ready to print.
	 * @since    1.0.0
	 */
	private function get_date_box() {
		$result  = '<img style="width:13px;vertical-align:middle;" src="' . Feather\Icons::get_base64( 'calendar', 'none', '#5A738E' ) . '" />&nbsp;&nbsp;<span class="pokm-datepicker-value"></span>';
		$result .= '<script>';
		$result .= 'jQuery(function ($) {';
		$result .= ' moment.locale("' . L10n::get_display_locale() . '");';
		$result .= ' var start = moment("' . $this->start . '");';
		$result .= ' var end = moment("' . $this->end . '");';
		$result .= ' function changeDate(start, end) {';
		$result .= '  $("span.pokm-datepicker-value").html(start.format("LL") + " - " + end.format("LL"));';
		$result .= ' }';
		$result .= ' $(".pokm-datepicker").daterangepicker({';
		$result .= '  opens: "left",';
		$result .= '  startDate: start,';
		$result .= '  endDate: end,';
		$result .= '  minDate: moment("' . Schema::get_oldest_date() . '"),';
		$result .= '  maxDate: moment(),';
		$result .= '  showCustomRangeLabel: true,';
		$result .= '  alwaysShowCalendars: true,';
		$result .= '  locale: {customRangeLabel: "' . esc_html__( 'Custom Range', 'keys-master' ) . '",cancelLabel: "' . esc_html__( 'Cancel', 'keys-master' ) . '", applyLabel: "' . esc_html__( 'Apply', 'keys-master' ) . '"},';
		$result .= '  ranges: {';
		$result .= '    "' . esc_html__( 'Today', 'keys-master' ) . '": [moment(), moment()],';
		$result .= '    "' . esc_html__( 'Yesterday', 'keys-master' ) . '": [moment().subtract(1, "days"), moment().subtract(1, "days")],';
		$result .= '    "' . esc_html__( 'This Month', 'keys-master' ) . '": [moment().startOf("month"), moment().endOf("month")],';
		$result .= '    "' . esc_html__( 'Last Month', 'keys-master' ) . '": [moment().subtract(1, "month").startOf("month"), moment().subtract(1, "month").endOf("month")],';
		$result .= '  }';
		$result .= ' }, changeDate);';
		$result .= ' changeDate(start, end);';
		$result .= ' $(".pokm-datepicker").on("apply.daterangepicker", function(ev, picker) {';
		$result .= '  var url = "' . $this->get_url( [ 'start', 'end' ], [], false ) . '" + "&start=" + picker.startDate.format("YYYY-MM-DD") + "&end=" + picker.endDate.format("YYYY-MM-DD");';
		$result .= '  $(location).attr("href", url);';
		$result .= ' });';
		$result .= '});';
		$result .= '</script>';
		return $result;
	}

}
