<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @package Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin;

use KeysMaster\Plugin\Feature\Analytics;
use KeysMaster\System\Environment;
use KeysMaster\System\Loader;
use KeysMaster\System\I18n;
use KeysMaster\System\Assets;
use KeysMaster\Library\Libraries;
use KeysMaster\System\Option;
use KeysMaster\System\Nag;
use KeysMaster\System\Password;
use KeysMaster\System\Cache;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->loader = new Loader();
		$this->define_global_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		if ( \DecaLog\Engine::isDecalogActivated() && Option::network_get( 'metrics' ) && Environment::exec_mode_for_metrics() && Option::network_get( 'analytics' ) ) {
			$this->define_metrics();
		}
	}


	/**
	 * Register all of the hooks related to the features of the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_global_hooks() {
		$bootstrap = new Initializer();
		$assets    = new Assets();
		$updater   = new Updater();
		$libraries = new Libraries();
		$this->loader->add_action( 'init', 'KeysMaster\Plugin\Integration\Databeam', 'init' );
		$this->loader->add_filter( 'perfopsone_plugin_info', self::class, 'perfopsone_plugin_info' );
		$this->loader->add_action( 'init', $bootstrap, 'initialize' );
		$this->loader->add_action( 'init', $bootstrap, 'late_initialize', PHP_INT_MAX );
		$this->loader->add_action( 'wp_head', $assets, 'prefetch' );
		add_shortcode( 'pokm-changelog', [ $updater, 'sc_get_changelog' ] );
		add_shortcode( 'pokm-libraries', [ $libraries, 'sc_get_list' ] );
		add_shortcode( 'pokm-statistics', [ 'KeysMaster\System\Statistics', 'sc_get_raw' ] );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Keys_Master_Admin();
		$nag          = new Nag();
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'init_admin_menus' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'init_settings_sections' );
		$this->loader->add_filter( 'plugin_action_links_' . plugin_basename( POKM_PLUGIN_DIR . POKM_SLUG . '.php' ), $plugin_admin, 'add_actions_links', 10, 4 );
		$this->loader->add_filter( 'plugin_row_meta', $plugin_admin, 'add_row_meta', 10, 2 );
		$this->loader->add_action( 'admin_notices', $nag, 'display' );
		$this->loader->add_action( 'wp_ajax_hide_pokm_nag', $nag, 'hide_callback' );
		$this->loader->add_action( 'wp_ajax_pokm_get_stats', 'KeysMaster\Plugin\Feature\AnalyticsFactory', 'get_stats_callback' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_public_hooks() {
		$plugin_public = new Keys_Master_Public();
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Register all metrics of the plugin.
	 *
	 * @since  1.2.0
	 * @access private
	 */
	private function define_metrics() {
		$span      = \DecaLog\Engine::tracesLogger( POKM_SLUG )->start_span( 'Metrics collation' );
		$cache_id  = 'metrics/lastcheck';
		$analytics = Cache::get_global( $cache_id );
		if ( ! isset( $analytics ) ) {
			$analytics = Analytics::get_status_kpi_collection( [ 'site_id' => 0 ] );
			Cache::set_global( $cache_id, $analytics, 'metrics' );
		}
		if ( isset( $analytics ) ) {
			$metrics = \DecaLog\Engine::metricsLogger( POKM_SLUG );
			if ( array_key_exists( 'data', $analytics ) ) {
				foreach ( $analytics['data'] as $kpi ) {
					$m = $kpi['metrics'] ?? null;
					if ( isset( $m ) ) {
						switch ( $m['type'] ) {
							case 'gauge':
								$metrics->createProdGauge( $m['name'], $m['value'], $m['desc'] );
								break;
							case 'counter':
								$metrics->createProdCounter( $m['name'], $m['desc'] );
								$metrics->incProdCounter( $m['name'], $m['value'] );
								break;
						}
					}
				}
			}
		}
		\DecaLog\Engine::tracesLogger( POKM_SLUG )->end_span( $span );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since  1.0.0
	 * @return Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Adds full plugin identification.
	 *
	 * @param array $plugin The already set identification information.
	 * @return array The extended identification information.
	 * @since 1.0.0
	 */
	public static function perfopsone_plugin_info( $plugin ) {
		$plugin[ POKM_SLUG ] = [
			'name'    => POKM_PRODUCT_NAME,
			'code'    => POKM_CODENAME,
			'version' => POKM_VERSION,
			'url'     => POKM_PRODUCT_URL,
			'icon'    => self::get_base64_logo(),
		];
		return $plugin;
	}

	/**
	 * Returns a base64 svg resource for the plugin logo.
	 *
	 * @return string The svg resource as a base64.
	 * @since 1.0.0
	 */
	public static function get_base64_logo() {
		$source  = '<svg width="100%" height="100%" viewBox="0 0 1001 1001" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">';
		$source .= '<g id="Keys-Master" serif:id="Keys Master" transform="matrix(10.0067,0,0,10.0067,0,0)">';
		$source .= '<rect x="0" y="0" width="100" height="100" style="fill:none;"/>';
		$source .= '<clipPath id="_clip1">';
		$source .= '<rect x="0" y="0" width="100" height="100"/>';
		$source .= '</clipPath>';
		$source .= '<g clip-path="url(#_clip1)">';
		$source .= '<g id="Icon" transform="matrix(0.964549,0,0,0.964549,-0.63865,1.78035)">';
		$source .= '<g transform="matrix(1.01391,0,0,1.01391,84.7473,70.1597)">';
		$source .= '<path d="M0,-2.5L0,0L-2.5,0" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear2);stroke-width:0.85px;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(-1.06727,0,0,1.01391,26.0788,70.1597)">';
		$source .= '<path d="M-50.091,0L0,0" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear3);stroke-width:0.83px;stroke-dasharray:2.19,2.19;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(1.01391,0,0,1.01391,23.4056,67.6249)">';
		$source .= '<path d="M0,2.5L-2.5,2.5L-2.5,0" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear4);stroke-width:0.85px;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(-5.89191e-18,-1.11014,-1.01391,-5.89191e-18,35.609,48.9262)">';
		$source .= '<path d="M-14.536,14.536L14.536,14.536" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear5);stroke-width:0.81px;stroke-dasharray:2.15,2.15;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(1.01391,0,0,1.01391,20.8708,27.5754)">';
		$source .= '<path d="M0,2.5L0,0L2.5,0" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear6);stroke-width:0.85px;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(1.01391,0,0,1.01391,28.7514,27.5754)">';
		$source .= '<path d="M0,0L50.091,0" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear7);stroke-width:0.85px;stroke-dasharray:2.25,2.25;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(1.01391,0,0,1.01391,82.2125,30.1101)">';
		$source .= '<path d="M0,-2.5L2.5,-2.5L2.5,0" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear8);stroke-width:0.85px;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(0,1.01391,1.01391,0,99.4856,50.2075)">';
		$source .= '<path d="M-14.536,-14.536L14.536,-14.536" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear9);stroke-width:0.85px;stroke-dasharray:2.25,2.25;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(0,106.221,106.221,0,52.4976,-19.9011)">';
		$source .= '<path d="M0.42,-0.324C0.421,-0.324 0.421,-0.324 0.421,-0.324C0.431,-0.398 0.495,-0.456 0.572,-0.456C0.656,-0.456 0.724,-0.388 0.724,-0.305L0.724,0.293C0.725,0.383 0.651,0.456 0.56,0.456C0.48,0.456 0.414,0.399 0.399,0.323C0.356,0.291 0.328,0.241 0.328,0.183C0.328,0.156 0.335,0.13 0.346,0.106C0.26,0.076 0.197,-0.006 0.197,-0.103L0.197,-0.103C0.197,-0.225 0.297,-0.324 0.42,-0.324Z" style="fill:url(#_Linear10);fill-rule:nonzero;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(0,1,1,0,60.0193,87.9577)">';
		$source .= '<path d="M-7.5,-7.5L7.5,-7.5" style="fill:none;fill-rule:nonzero;stroke:rgb(87,159,244);stroke-width:1.73px;stroke-linecap:butt;stroke-linejoin:miter;stroke-miterlimit:10;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(1,0,0,1,7.51934,95.4577)">';
		$source .= '<path d="M0,0L90,0" style="fill:none;fill-rule:nonzero;stroke:rgb(87,159,244);stroke-width:1.73px;stroke-linecap:butt;stroke-linejoin:miter;stroke-miterlimit:10;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(-1,0,0,1,103.001,83.2866)">';
		$source .= '<rect x="44" y="9" width="13" height="6" style="fill:rgb(171,207,249);stroke:rgb(171,207,249);stroke-width:1.73px;stroke-miterlimit:10;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(0.623231,0,0,0.589201,42.2141,36.2807)">';
		$source .= '<path d="M0,28.5L0,16.25C0,7.275 7.275,0 16.25,0L16.75,0C25.725,0 33,7.275 33,16.25L33,27.5" style="fill:none;fill-rule:nonzero;stroke:url(#_Linear11);stroke-width:4.75px;stroke-linecap:butt;stroke-linejoin:miter;stroke-miterlimit:10;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(0,-35.4563,-37.5042,0,52.4974,82.6311)">';
		$source .= '<path d="M0.95,0.344C0.95,0.374 0.925,0.399 0.895,0.399L0.191,0.399C0.16,0.399 0.136,0.374 0.136,0.344L0.136,-0.344C0.136,-0.374 0.16,-0.399 0.191,-0.399L0.895,-0.399C0.925,-0.399 0.95,-0.374 0.95,-0.344L0.95,0.344Z" style="fill:url(#_Linear12);fill-rule:nonzero;"/>';
		$source .= '</g>';
		$source .= '<g transform="matrix(0.103606,0,0,0.103606,0.662123,-1.84579)">';
		$source .= '<path d="M485.272,629.34C472.848,623.947 464.218,612.081 464.218,598.314C464.218,579.473 480.381,564.192 500.31,564.192C520.239,564.192 536.403,579.473 536.403,598.314C536.403,612.081 527.773,623.947 515.349,629.34L515.349,694.991L485.272,694.991L485.272,629.34Z" style="fill:url(#_Radial13);fill-rule:nonzero;"/>';
		$source .= '</g>';
		$source .= '</g>';
		$source .= '</g>';
		$source .= '</g>';
		$source .= '<defs>';
		$source .= '<linearGradient id="_Linear2" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(4.52619e-12,44,-44,4.52619e-12,-1.25,-43)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear3" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(-4.52619e-12,44,44,4.52619e-12,-25.0453,-43)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear4" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(4.52619e-12,44,-44,4.52619e-12,-1.25,-40.5)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear5" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(-44,-4.52619e-12,-4.52619e-12,44,20.6784,14.536)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear6" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(4.52619e-12,44,-44,4.52619e-12,1.25,-1)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear7" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(4.52619e-12,44,-44,4.52619e-12,25.0457,-1)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear8" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(4.52619e-12,44,-44,4.52619e-12,1.25,-3.5)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear9" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(44,4.52619e-12,4.52619e-12,-44,-23.3216,-14.536)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear10" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(1,0,0,-1,0,-2.11822e-06)"><stop offset="0" style="stop-color:rgb(248,247,252);stop-opacity:1"/><stop offset="0.08" style="stop-color:rgb(248,247,252);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(65,172,255);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear11" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(3.27463e-12,-31.8333,31.8333,3.27463e-12,16.5,28.5)"><stop offset="0" style="stop-color:rgb(65,172,255);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(145,202,252);stop-opacity:1"/></linearGradient>';
		$source .= '<linearGradient id="_Linear12" x1="0" y1="0" x2="1" y2="0" gradientUnits="userSpaceOnUse" gradientTransform="matrix(1,0,0,-1,0,0)"><stop offset="0" style="stop-color:rgb(25,39,131);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(65,172,255);stop-opacity:1"/></linearGradient>';
		$source .= '<radialGradient id="_Radial13" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="matrix(36.7625,0,0,-81.8738,497.971,629.591)"><stop offset="0" style="stop-color:rgb(255,216,111);stop-opacity:1"/><stop offset="0.51" style="stop-color:rgb(255,199,86);stop-opacity:1"/><stop offset="1" style="stop-color:rgb(255,147,8);stop-opacity:1"/></radialGradient>';
		$source .= '</defs>';
		$source .= '</svg>';
		// phpcs:ignore
		return 'data:image/svg+xml;base64,' . base64_encode( $source );
	}

}
