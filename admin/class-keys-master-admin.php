<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin;

use KeysMaster\Plugin\Feature\Analytics;
use KeysMaster\Plugin\Feature\AnalyticsFactory;
use KeysMaster\System\Assets;
use KeysMaster\System\Environment;

use KeysMaster\System\Role;
use KeysMaster\System\Option;
use KeysMaster\System\Form;
use KeysMaster\System\Blog;
use KeysMaster\System\Date;
use KeysMaster\System\Timezone;
use KeysMaster\System\GeoIP;
use PerfOpsOne\AdminMenus;
use KeysMaster\System\Statistics;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Keys_Master_Admin {

	/**
	 * The assets manager that's responsible for handling all assets of the plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Assets    $assets    The plugin assets manager.
	 */
	protected $assets;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->assets = new Assets();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		$this->assets->register_style( POKM_ASSETS_ID, POKM_ADMIN_URL, 'css/keys-master.min.css' );
		$this->assets->register_style( 'pokm-daterangepicker', POKM_ADMIN_URL, 'css/daterangepicker.min.css' );
		$this->assets->register_style( 'pokm-switchery', POKM_ADMIN_URL, 'css/switchery.min.css' );
		$this->assets->register_style( 'pokm-tooltip', POKM_ADMIN_URL, 'css/tooltip.min.css' );
		$this->assets->register_style( 'pokm-chartist', POKM_ADMIN_URL, 'css/chartist.min.css' );
		$this->assets->register_style( 'pokm-chartist-tooltip', POKM_ADMIN_URL, 'css/chartist-plugin-tooltip.min.css' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		$this->assets->register_script( POKM_ASSETS_ID, POKM_ADMIN_URL, 'js/keys-master.min.js', [ 'jquery' ] );
		$this->assets->register_script( 'pokm-moment-with-locale', POKM_ADMIN_URL, 'js/moment-with-locales.min.js', [ 'jquery' ] );
		$this->assets->register_script( 'pokm-daterangepicker', POKM_ADMIN_URL, 'js/daterangepicker.min.js', [ 'jquery' ] );
		$this->assets->register_script( 'pokm-switchery', POKM_ADMIN_URL, 'js/switchery.min.js', [ 'jquery' ] );
		$this->assets->register_script( 'pokm-chartist', POKM_ADMIN_URL, 'js/chartist.min.js', [ 'jquery' ] );
		$this->assets->register_script( 'pokm-chartist-tooltip', POKM_ADMIN_URL, 'js/chartist-plugin-tooltip.min.js', [ 'pokm-chartist' ] );
	}

	/**
	 * Init PerfOps admin menus.
	 *
	 * @param array $perfops    The already declared menus.
	 * @return array    The completed menus array.
	 * @since 1.0.0
	 */
	public function init_perfops_admin_menus( $perfops ) {
		if ( Role::SUPER_ADMIN === Role::admin_type() || Role::SINGLE_ADMIN === Role::admin_type() ) {
			$perfops['analytics'][] = [
				'name'          => esc_html__( 'Application Passwords', 'keys-master' ),
				'description'   => sprintf( esc_html__( 'View application passwords usage on your %s.', 'keys-master' ), Environment::is_wordpress_multisite() ? esc_html__( 'network', 'keys-master' ) : esc_html__( 'website', 'keys-master' ) ),
				'icon_callback' => [ \KeysMaster\Plugin\Core::class, 'get_base64_logo' ],
				'slug'          => 'pokm-viewer',
				'page_title'    => esc_html__( 'Application Passwords Analytics', 'keys-master' ),
				'menu_title'    => esc_html__( 'App. Passwords', 'keys-master' ),
				'capability'    => 'manage_options',
				'callback'      => [ $this, 'get_viewer_page' ],
				'position'      => 50,
				'plugin'        => POKM_SLUG,
				'activated'     => Option::network_get( 'analytics' ),
				'remedy'        => esc_url( admin_url( 'admin.php?page=pokm-settings&tab=misc' ) ),
			];
			$perfops['tools'][]     = [
				'name'          => esc_html__( 'Application Passwords', 'keys-master' ),
				'description'   => sprintf( esc_html__( 'Browse and manage application passwords on your %s.', 'keys-master' ), Environment::is_wordpress_multisite() ? esc_html__( 'network', 'keys-master' ) : esc_html__( 'website', 'keys-master' ) ),
				'icon_callback' => [ \KeysMaster\Plugin\Core::class, 'get_base64_logo' ],
				'slug'          => 'pokm-manager',
				'page_title'    => esc_html__( 'Application Passwords Management', 'keys-master' ),
				'menu_title'    => esc_html__( 'App. Passwords', 'keys-master' ),
				'capability'    => 'manage_options',
				'callback'      => [ $this, 'get_manager_page' ],
				'position'      => 50,
				'plugin'        => POKM_SLUG,
				'activated'     => true,
				'remedy'        => '',
			];
			$perfops['settings'][]  = [
				'name'          => POKM_PRODUCT_NAME,
				'description'   => '',
				'icon_callback' => [ \KeysMaster\Plugin\Core::class, 'get_base64_logo' ],
				'slug'          => 'pokm-settings',
				/* translators: as in the sentence "Keys Master Settings" or "WordPress Settings" */
				'page_title'    => sprintf( esc_html__( '%s Settings', 'keys-master' ), POKM_PRODUCT_NAME ),
				'menu_title'    => POKM_PRODUCT_NAME,
				'capability'    => 'manage_options',
				'callback'      => [ $this, 'get_settings_page' ],
				'position'      => 50,
				'plugin'        => POKM_SLUG,
				'version'       => POKM_VERSION,
				'activated'     => true,
				'remedy'        => '',
				'statistics'    => [ '\KeysMaster\System\Statistics', 'sc_get_raw' ],
			];
		}
		return $perfops;
	}

	/**
	 * Set the items in the settings menu.
	 *
	 * @since 1.0.0
	 */
	public function init_admin_menus() {
		add_filter( 'init_perfops_admin_menus', [ $this, 'init_perfops_admin_menus' ] );
		AdminMenus::initialize();
	}

	/**
	 * Initializes settings sections.
	 *
	 * @since 1.0.0
	 */
	public function init_settings_sections() {
		add_settings_section( 'pokm_plugin_features_section', esc_html__( 'Plugin Features', 'keys-master' ), [ $this, 'plugin_features_section_callback' ], 'pokm_plugin_features_section' );
		//add_settings_section( 'pokm_privacy_options_section', esc_html__( 'Privacy options', 'keys-master' ), [ $this, 'privacy_options_section_callback' ], 'pokm_privacy_options_section' );
		add_settings_section( 'pokm_plugin_options_section', esc_html__( 'Plugin options', 'keys-master' ), [ $this, 'plugin_options_section_callback' ], 'pokm_plugin_options_section' );
		add_settings_section( 'pokm_plugin_roles_section', '', [ $this, 'plugin_roles_section_callback' ], 'pokm_plugin_roles_section' );
	}

	/**
	 * Add links in the "Actions" column on the plugins view page.
	 *
	 * @param string[] $actions     An array of plugin action links. By default this can include 'activate',
	 *                              'deactivate', and 'delete'.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array    $plugin_data An array of plugin data. See `get_plugin_data()`.
	 * @param string   $context     The plugin context. By default this can include 'all', 'active', 'inactive',
	 *                              'recently_activated', 'upgrade', 'mustuse', 'dropins', and 'search'.
	 * @return array Extended list of links to print in the "Actions" column on the Plugins page.
	 * @since 1.0.0
	 */
	public function add_actions_links( $actions, $plugin_file, $plugin_data, $context ) {
		$actions[] = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=pokm-settings' ) ), esc_html__( 'Settings', 'keys-master' ) );
		if ( Option::network_get( 'analytics' ) ) {
			$actions[] = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=pokm-viewer' ) ), esc_html__( 'Statistics', 'keys-master' ) );
		}
		return $actions;
	}

	/**
	 * Add links in the "Description" column on the plugins view page.
	 *
	 * @param array  $links List of links to print in the "Description" column on the Plugins page.
	 * @param string $file Path to the plugin file relative to the plugins directory.
	 * @return array Extended list of links to print in the "Description" column on the Plugins page.
	 * @since 1.0.0
	 */
	public function add_row_meta( $links, $file ) {
		if ( 0 === strpos( $file, POKM_SLUG . '/' ) ) {
			$links[] = '<a href="https://wordpress.org/support/plugin/' . POKM_SLUG . '/">' . __( 'Support', 'keys-master' ) . '</a>';
			$links[] = '<a href="https://github.com/Pierre-Lannoy/wp-keys-master">' . __( 'GitHub repository', 'keys-master' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Get the content of the viewer page.
	 *
	 * @since 1.0.0
	 */
	public function get_viewer_page() {
		$analytics = AnalyticsFactory::get_analytics();
		include POKM_ADMIN_DIR . 'partials/keys-master-admin-view-analytics.php';
	}

	/**
	 * Get the content of the manager page.
	 *
	 * @since 1.0.0
	 */
	public function get_manager_page() {
		include POKM_ADMIN_DIR . 'partials/keys-master-admin-tools.php';
	}

	/**
	 * Get the content of the settings page.
	 *
	 * @since 1.0.0
	 */
	public function get_settings_page() {
		if ( ! ( $tab = filter_input( INPUT_GET, 'tab' ) ) ) {
			$tab = filter_input( INPUT_POST, 'tab' );
		}
		if ( ! ( $action = filter_input( INPUT_GET, 'action' ) ) ) {
			$action = filter_input( INPUT_POST, 'action' );
		}
		if ( $action && $tab ) {
			switch ( $tab ) {
				case 'misc':
					switch ( $action ) {
						case 'do-save':
							if ( Role::SUPER_ADMIN === Role::admin_type() || Role::SINGLE_ADMIN === Role::admin_type() ) {
								if ( ! empty( $_POST ) && array_key_exists( 'submit', $_POST ) ) {
									$this->save_options();
								} elseif ( ! empty( $_POST ) && array_key_exists( 'reset-to-defaults', $_POST ) ) {
									$this->reset_options();
								}
							}
							break;
					}
					break;
				case 'roles':
					switch ( $action ) {
						case 'do-save':
							$this->save_roles_options();
							break;
					}
					break;
			}
		}
		include POKM_ADMIN_DIR . 'partials/keys-master-admin-settings-main.php';
	}

	/**
	 * Save the core plugin options.
	 *
	 * @since 1.0.0
	 */
	private function save_roles_options() {
		if ( ! empty( $_POST ) ) {
			if ( array_key_exists( '_wpnonce', $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'pokm-plugin-options' ) ) {
				$settings = Option::roles_get();
				foreach ( Role::get_all() as $role => $detail ) {
					foreach ( Option::$specific as $spec ) {
						if ( array_key_exists( 'pokm_plugin_roles_' . $spec . '_' . $role, $_POST ) ) {
							$settings[ $role ][ $spec ] = filter_input( INPUT_POST, 'pokm_plugin_roles_' . $spec . '_' . $role );
						}
					}
				}
				Option::roles_set( $settings );
				$message  = esc_html__( 'Plugin settings have been saved.', 'keys-master' );
				$code     = 0;
				add_settings_error( 'pokm_no_error', $code, $message, 'updated' );
				\DecaLog\Engine::eventsLogger( POKM_SLUG )->info( 'Plugin settings updated.', [ 'code' => $code ] );
			} else {
				$message = esc_html__( 'Plugin settings have not been saved. Please try again.', 'keys-master' );
				$code    = 2;
				add_settings_error( 'pokm_nonce_error', $code, $message, 'error' );
				\DecaLog\Engine::eventsLogger( POKM_SLUG )->warning( 'Plugin settings not updated.', [ 'code' => $code ] );
			}
		}
	}

	/**
	 * Save the plugin options.
	 *
	 * @since 1.0.0
	 */
	private function save_options() {
		if ( ! empty( $_POST ) ) {
			if ( array_key_exists( '_wpnonce', $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'pokm-plugin-options' ) ) {
				Option::network_set( 'use_cdn', array_key_exists( 'pokm_plugin_options_usecdn', $_POST ) ? (bool) filter_input( INPUT_POST, 'pokm_plugin_options_usecdn' ) : false );
				Option::network_set( 'display_nag', array_key_exists( 'pokm_plugin_options_nag', $_POST ) ? (bool) filter_input( INPUT_POST, 'pokm_plugin_options_nag' ) : false );
				Option::network_set( 'download_favicons', array_key_exists( 'pokm_plugin_options_favicons', $_POST ) ? (bool) filter_input( INPUT_POST, 'pokm_plugin_options_favicons' ) : false );
				Option::network_set( 'analytics', array_key_exists( 'pokm_plugin_features_analytics', $_POST ) ? (bool) filter_input( INPUT_POST, 'pokm_plugin_features_analytics' ) : false );
				Option::network_set( 'obfuscation', array_key_exists( 'pokm_privacy_options_obfuscation', $_POST ) ? (bool) filter_input( INPUT_POST, 'pokm_privacy_options_obfuscation' ) : false );
				Option::network_set( 'history', array_key_exists( 'pokm_plugin_features_history', $_POST ) ? (string) filter_input( INPUT_POST, 'pokm_plugin_features_history', FILTER_SANITIZE_NUMBER_INT ) : Option::network_get( 'history' ) );
				Option::network_set( 'rolemode', array_key_exists( 'pokm_plugin_features_rolemode', $_POST ) ? (string) filter_input( INPUT_POST, 'pokm_plugin_features_rolemode', FILTER_SANITIZE_NUMBER_INT ) : Option::network_get( 'rolemode' ) );
				$message = esc_html__( 'Plugin settings have been saved.', 'keys-master' );
				$code    = 0;
				add_settings_error( 'pokm_no_error', $code, $message, 'updated' );
				\DecaLog\Engine::eventsLogger( POKM_SLUG )->info( 'Plugin settings updated.', [ 'code' => $code ] );
			} else {
				$message = esc_html__( 'Plugin settings have not been saved. Please try again.', 'keys-master' );
				$code    = 2;
				add_settings_error( 'pokm_nonce_error', $code, $message, 'error' );
				\DecaLog\Engine::eventsLogger( POKM_SLUG )->warning( 'Plugin settings not updated.', [ 'code' => $code ] );
			}
		}
	}

	/**
	 * Reset the plugin options.
	 *
	 * @since 1.0.0
	 */
	private function reset_options() {
		if ( ! empty( $_POST ) ) {
			if ( array_key_exists( '_wpnonce', $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'pokm-plugin-options' ) ) {
				Option::reset_to_defaults();
				$message = esc_html__( 'Plugin settings have been reset to defaults.', 'keys-master' );
				$code    = 0;
				add_settings_error( 'pokm_no_error', $code, $message, 'updated' );
				\DecaLog\Engine::eventsLogger( POKM_SLUG )->info( 'Plugin settings reset to defaults.', [ 'code' => $code ] );
			} else {
				$message = esc_html__( 'Plugin settings have not been reset to defaults. Please try again.', 'keys-master' );
				$code    = 2;
				add_settings_error( 'pokm_nonce_error', $code, $message, 'error' );
				\DecaLog\Engine::eventsLogger( POKM_SLUG )->warning( 'Plugin settings not reset to defaults.', [ 'code' => $code ] );
			}
		}
	}

	/**
	 * Callback for plugin options section.
	 *
	 * @since 1.0.0
	 */
	public function plugin_options_section_callback() {
		$form = new Form();
		add_settings_field(
			'pokm_plugin_options_favicons',
			__( 'Favicons', 'keys-master' ),
			[ $form, 'echo_field_checkbox' ],
			'pokm_plugin_options_section',
			'pokm_plugin_options_section',
			[
				'text'        => esc_html__( 'Download and display', 'keys-master' ),
				'id'          => 'pokm_plugin_options_favicons',
				'checked'     => Option::network_get( 'download_favicons' ),
				'description' => esc_html__( 'If checked, Keys Master will download favicons of websites to display them in reports.', 'keys-master' ) . '<br/>' . esc_html__( 'Note: This feature uses the (free) Google Favicon Service.', 'keys-master' ),
				'full_width'  => false,
				'enabled'     => true,
			]
		);
		register_setting( 'pokm_plugin_options_section', 'pokm_plugin_options_favicons' );
		if ( defined( 'DECALOG_VERSION' ) ) {
			$help  = '<img style="width:16px;vertical-align:text-bottom;" src="' . \Feather\Icons::get_base64( 'thumbs-up', 'none', '#00C800' ) . '" />&nbsp;';
			$help .= sprintf( esc_html__( 'Your site is currently using %s.', 'keys-master' ), '<em>DecaLog v' . DECALOG_VERSION . '</em>' );
		} else {
			$help  = '<img style="width:16px;vertical-align:text-bottom;" src="' . \Feather\Icons::get_base64( 'alert-triangle', 'none', '#FF8C00' ) . '" />&nbsp;';
			$help .= sprintf( esc_html__( 'Your site does not use any logging plugin. To log all events triggered in Keys Master, I recommend you to install the excellent (and free) %s. But it is not mandatory.', 'keys-master' ), '<a href="https://wordpress.org/plugins/decalog/">DecaLog</a>' );
		}
		add_settings_field(
			'pokm_plugin_options_logger',
			esc_html__( 'Logging', 'keys-master' ),
			[ $form, 'echo_field_simple_text' ],
			'pokm_plugin_options_section',
			'pokm_plugin_options_section',
			[
				'text' => $help,
			]
		);
		register_setting( 'pokm_plugin_options_section', 'pokm_plugin_options_logger' );
		$geo_ip = new GeoIP();
		if ( $geo_ip->is_installed() ) {
			$help  = '<img style="width:16px;vertical-align:text-bottom;" src="' . \Feather\Icons::get_base64( 'thumbs-up', 'none', '#00C800' ) . '" />&nbsp;';
			$help .= sprintf( esc_html__( 'Your site is currently using %s.', 'keys-master' ), '<em>' . $geo_ip->get_full_name() . '</em>' );
		} else {
			$help  = '<img style="width:16px;vertical-align:text-bottom;" src="' . \Feather\Icons::get_base64( 'alert-triangle', 'none', '#FF8C00' ) . '" />&nbsp;';
			$help .= sprintf( esc_html__( 'Your site does not use any IP geographic information plugin. To allow country differentiation in Keys Master analytics, I recommend you to install the excellent (and free) %s. But it is not mandatory.', 'keys-master' ), '<a href="https://wordpress.org/plugins/ip-locator/">IP Locator</a>' );
		}
		add_settings_field(
			'pokm_plugin_options_geoip',
			__( 'IP information', 'keys-master' ),
			[ $form, 'echo_field_simple_text' ],
			'pokm_plugin_options_section',
			'pokm_plugin_options_section',
			[
				'text' => $help,
			]
		);
		register_setting( 'pokm_plugin_options_section', 'pokm_plugin_options_geoip' );
		if ( class_exists( 'PODeviceDetector\API\Device' ) ) {
			$help  = '<img style="width:16px;vertical-align:text-bottom;" src="' . \Feather\Icons::get_base64( 'thumbs-up', 'none', '#00C800' ) . '" />&nbsp;';
			$help .= sprintf( esc_html__( 'Your site is currently using %s.', 'keys-master' ), '<em>Device Detector v' . PODD_VERSION . '</em>' );
		} else {
			$help  = '<img style="width:16px;vertical-align:text-bottom;" src="' . \Feather\Icons::get_base64( 'alert-triangle', 'none', '#FF8C00' ) . '" />&nbsp;';
			$help .= sprintf( esc_html__( 'Your site does not use any device detection mechanism. To allow device differentiation in Keys Master analytics, I recommend you to install the excellent (and free) %s. But it is not mandatory.', 'keys-master' ), '<a href="https://wordpress.org/plugins/device-detector/">Device Detector</a>' );
		}
		add_settings_field(
			'pokm_plugin_options_podd',
			__( 'Device detection', 'keys-master' ),
			[ $form, 'echo_field_simple_text' ],
			'pokm_plugin_options_section',
			'pokm_plugin_options_section',
			[
				'text' => $help,
			]
		);
		register_setting( 'pokm_plugin_options_section', 'pokm_plugin_options_podd' );
		/*if ( defined( 'TRAFFIC_VERSION' ) ) {
			$help  = '<img style="width:16px;vertical-align:text-bottom;" src="' . \Feather\Icons::get_base64( 'thumbs-up', 'none', '#00C800' ) . '" />&nbsp;';
			$help .= sprintf( esc_html__( 'Your site is currently using %s.', 'keys-master' ), '<em>Traffic v' . TRAFFIC_VERSION . '</em>' );
		} else {
			$help  = '<img style="width:16px;vertical-align:text-bottom;" src="' . \Feather\Icons::get_base64( 'alert-triangle', 'none', '#FF8C00' ) . '" />&nbsp;';
			$help .= sprintf( esc_html__( 'Your site does not analyze API traffic. To allow usage correlation in Keys Master, I recommend you to install the excellent (and free) %s. But it is not mandatory.', 'keys-master' ), '<a href="https://wordpress.org/plugins/traffic/">Traffic</a>' );
		}
		add_settings_field(
			'pokm_plugin_options_traffic',
			__( 'API analytics', 'keys-master' ),
			[ $form, 'echo_field_simple_text' ],
			'pokm_plugin_options_section',
			'pokm_plugin_options_section',
			[
				'text' => $help,
			]
		);*/
		//register_setting( 'pokm_plugin_options_section', 'pokm_plugin_options_traffic' );
		add_settings_field(
			'pokm_plugin_options_usecdn',
			esc_html__( 'Resources', 'keys-master' ),
			[ $form, 'echo_field_checkbox' ],
			'pokm_plugin_options_section',
			'pokm_plugin_options_section',
			[
				'text'        => esc_html__( 'Use public CDN', 'keys-master' ),
				'id'          => 'pokm_plugin_options_usecdn',
				'checked'     => Option::network_get( 'use_cdn' ),
				'description' => esc_html__( 'If checked, Keys Master will use a public CDN (jsDelivr) to serve scripts and stylesheets.', 'keys-master' ),
				'full_width'  => false,
				'enabled'     => true,
			]
		);
		register_setting( 'pokm_plugin_options_section', 'pokm_plugin_options_usecdn' );
		add_settings_field(
			'pokm_plugin_options_nag',
			esc_html__( 'Admin notices', 'keys-master' ),
			[ $form, 'echo_field_checkbox' ],
			'pokm_plugin_options_section',
			'pokm_plugin_options_section',
			[
				'text'        => esc_html__( 'Display', 'keys-master' ),
				'id'          => 'pokm_plugin_options_nag',
				'checked'     => Option::network_get( 'display_nag' ),
				'description' => esc_html__( 'Allows Keys Master to display admin notices throughout the admin dashboard.', 'keys-master' ) . '<br/>' . esc_html__( 'Note: Keys Master respects DISABLE_NAG_NOTICES flag.', 'keys-master' ),
				'full_width'  => false,
				'enabled'     => true,
			]
		);
		register_setting( 'pokm_plugin_options_section', 'pokm_plugin_options_nag' );
	}

	/**
	 * Get the available history retentions.
	 *
	 * @return array An array containing the history modes.
	 * @since  1.0.0
	 */
	protected function get_retentions_array() {
		$result = [];
		for ( $i = 1; $i < 7; $i++ ) {
			// phpcs:ignore
			$result[] = [ (int) ( 30 * $i ), esc_html( sprintf( _n( '%d month', '%d months', $i, 'keys-master' ), $i ) ) ];
		}
		for ( $i = 1; $i < 7; $i++ ) {
			// phpcs:ignore
			$result[] = [ (int) ( 365 * $i ), esc_html( sprintf( _n( '%d year', '%d years', $i, 'keys-master' ), $i ) ) ];
		}
		return $result;
	}

	/**
	 * Callback for plugin features section.
	 *
	 * @since 1.0.0
	 */
	public function plugin_features_section_callback() {
		$form   = new Form();
		$mode   = [];
		$mode[] = [ -1, esc_html__( 'Disabled - Don\'t limit application passwords usage by roles', 'keys-master' ) ];
		$mode[] = [ 0, esc_html__( 'Enabled - Cumulative privileges', 'keys-master' ) ];
		$mode[] = [ 1, esc_html__( 'Enabled - Least privileges', 'keys-master' ) ];
		add_settings_field(
			'pokm_plugin_features_rolemode',
			esc_html__( 'Settings by roles', 'keys-master' ),
			[ $form, 'echo_field_select' ],
			'pokm_plugin_features_section',
			'pokm_plugin_features_section',
			[
				'list'        => $mode,
				'id'          => 'pokm_plugin_features_rolemode',
				'value'       => Option::network_get( 'rolemode' ),
				'description' => esc_html__( 'Operation mode of application passwords control.', 'keys-master' ) . '<br/><em>' . esc_html__( 'Note: administrators having other roles are not affected by the privileges computation; this privileges computation only applies on non-administrator users having multiple roles.', 'keys-master' ) . '</em>',
				'full_width'  => false,
				'enabled'     => true,
			]
		);
		register_setting( 'pokm_plugin_features_section', 'pokm_plugin_features_rolemode' );
		add_settings_field(
			'pokm_plugin_features_analytics',
			esc_html__( 'Analytics', 'keys-master' ),
			[ $form, 'echo_field_checkbox' ],
			'pokm_plugin_features_section',
			'pokm_plugin_features_section',
			[
				'text'        => esc_html__( 'Activated', 'keys-master' ),
				'id'          => 'pokm_plugin_features_analytics',
				'checked'     => Option::network_get( 'analytics' ),
				'description' => esc_html__( 'If checked, Keys Master will store statistics about application passwords usages.', 'keys-master' ),
				'full_width'  => false,
				'enabled'     => true,
			]
		);
		register_setting( 'pokm_plugin_features_section', 'pokm_plugin_features_analytics' );
		add_settings_field(
			'pokm_plugin_features_history',
			esc_html__( 'Historical data', 'keys-master' ),
			[ $form, 'echo_field_select' ],
			'pokm_plugin_features_section',
			'pokm_plugin_features_section',
			[
				'list'        => $this->get_retentions_array(),
				'id'          => 'pokm_plugin_features_history',
				'value'       => Option::network_get( 'history' ),
				'description' => esc_html__( 'Maximum age of data to keep for statistics.', 'keys-master' ),
				'full_width'  => false,
				'enabled'     => true,
			]
		);
		register_setting( 'pokm_plugin_features_section', 'pokm_plugin_features_history' );
	}
	/**
	 * Callback for privacy options section.
	 *
	 * @since 1.0.0
	 */
	public function privacy_options_section_callback() {
		$form = new Form();
		add_settings_field(
			'pokm_privacy_options_obfuscation',
			esc_html__( 'Remote IPs', 'keys-master' ),
			[ $form, 'echo_field_checkbox' ],
			'pokm_privacy_options_section',
			'pokm_privacy_options_section',
			[
				'text'        => esc_html__( 'Obfuscation', 'keys-master' ),
				'id'          => 'pokm_privacy_options_obfuscation',
				'checked'     => Option::network_get( 'obfuscation' ),
				'description' => esc_html__( 'If checked, hashes will be stored instead of real IPs.', 'keys-master' ),
				'full_width'  => false,
				'enabled'     => true,
			]
		);
		register_setting( 'pokm_privacy_options_section', 'pokm_privacy_options_obfuscation' );
	}

	/**
	 * Callback for plugin roles modification section.
	 *
	 * @since 1.0.0
	 */
	public function plugin_roles_section_callback() {
		$settings = Option::roles_get();
		$blocks   = [];
		$blocks[] = [ 'full', esc_html__( 'Full (authentication, creation and revocation)', 'keys-master' ) ];
		$blocks[] = [ 'limited', esc_html__( 'Limited (authentication and revocation)', 'keys-master' ) ];
		$blocks[] = [ 'none', esc_html__( 'None', 'keys-master' ) ];
		$idle     = [];
		$idle[]   = [ 0, esc_html__( 'Never revoke', 'keys-master' ) ];
		foreach ( [ 7, 14, 30, 60 ]  as $h ) {
			// phpcs:ignore
			$idle[] = [ $h, esc_html( sprintf( __( 'Revoke when unused for more than %d days', 'keys-master' ), $h ) ) ];
		}
		$maxap = [];
		foreach ( [ 1, 2, 3, 4, 5, 6, 7, 8, 9 ]  as $h ) {
			// phpcs:ignore
			$maxap[] = [ $h, esc_html( sprintf( _n( '%d application password', '%d application passwords', $h, 'keys-master' ), $h ) ) ];
		}
		$maxap[] = [ PHP_INT_MAX, esc_html__( 'Unlimited', 'keys-master' ) ];
		$form    = new Form();
		foreach ( Role::get_all() as $role => $detail ) {
			add_settings_field(
				'pokm_plugin_roles_allow_' . $role,
				$detail['l10n_name'],
				[ $form, 'echo_field_select' ],
				'pokm_plugin_roles_section',
				'pokm_plugin_roles_section',
				[
					'list'        => $blocks,
					'id'          => 'pokm_plugin_roles_allow_' . $role,
					'value'       => $settings[ $role ]['allow'],
					'description' => esc_html__( 'Allowed application passwords usage.', 'keys-master' ),
					'full_width'  => false,
					'enabled'     => true,
				]
			);
			register_setting( 'pokm_plugin_roles_section', 'pokm_plugin_roles_allow_' . $role );
			add_settings_field(
				'pokm_plugin_roles_maxap_' . $role,
				'',
				[ $form, 'echo_field_select' ],
				'pokm_plugin_roles_section',
				'pokm_plugin_roles_section',
				[
					'list'        => $maxap,
					'id'          => 'pokm_plugin_roles_maxap_' . $role,
					'value'       => $settings[ $role ]['maxap'],
					'description' => esc_html__( 'Maximal number of application passwords per user.', 'keys-master' ),
					'full_width'  => false,
					'enabled'     => true,
				]
			);
			register_setting( 'pokm_plugin_roles_section', 'pokm_plugin_roles_maxap_' . $role );
			add_settings_field(
				'pokm_plugin_roles_idle_' . $role,
				'',
				[ $form, 'echo_field_select' ],
				'pokm_plugin_roles_section',
				'pokm_plugin_roles_section',
				[
					'list'        => $idle,
					'id'          => 'pokm_plugin_roles_idle_' . $role,
					'value'       => $settings[ $role ]['idle'],
					'description' => esc_html__( 'Unused application passwords supervision.', 'keys-master' ),
					'full_width'  => false,
					'enabled'     => true,
				]
			);
			register_setting( 'pokm_plugin_roles_section', 'pokm_plugin_roles_idle_' . $role );
		}
	}

}
