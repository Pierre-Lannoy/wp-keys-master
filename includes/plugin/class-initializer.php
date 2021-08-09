<?php
/**
 * Plugin initialization handling.
 *
 * @package Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin;

/**
 * Fired after 'plugins_loaded' hook.
 *
 * This class defines all code necessary to run during the plugin's initialization.
 *
 * @package Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Initializer {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since   1.0.0
	 */
	public function __construct() {

	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public function initialize() {
		\KeysMaster\System\Cache::init();
		\KeysMaster\System\Sitehealth::init();
		\KeysMaster\System\APCu::init();
		\KeysMaster\Plugin\Feature\UserAdministration::init();
		\KeysMaster\Plugin\Feature\ZooKeeper::init();
		if ( 'en_US' !== determine_locale() ) {
			unload_textdomain( POKM_SLUG );
			load_plugin_textdomain( POKM_SLUG );
		}
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public function late_initialize() {
		\KeysMaster\Plugin\Feature\Capture::late_init();
		require_once POKM_PLUGIN_DIR . 'perfopsone/init.php';
	}

}
