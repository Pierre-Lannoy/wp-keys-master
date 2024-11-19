<?php
/**
 * Main plugin file.
 *
 * @package Bootstrap
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Keys Master
 * Plugin URI:        https://perfops.one/keys-master
 * Description:       Powerful application passwords manager for WordPress with role-based usage control and full analytics reporting capabilities.
 * Version:           2.1.2
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            Pierre Lannoy / PerfOps One
 * Author URI:        https://perfops.one
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Network:           true
 * Text Domain:       keys-master
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/system/class-option.php';
require_once __DIR__ . '/includes/system/class-environment.php';
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/libraries/class-libraries.php';
require_once __DIR__ . '/includes/libraries/autoload.php';
require_once __DIR__ . '/includes/features/class-wpcli.php';

/**
 * The code that runs during plugin activation.
 *
 * @since 1.0.0
 */
function pokm_activate() {
	KeysMaster\Plugin\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * @since 1.0.0
 */
function pokm_deactivate() {
	KeysMaster\Plugin\Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstallation.
 *
 * @since 1.0.0
 */
function pokm_uninstall() {
	KeysMaster\Plugin\Uninstaller::uninstall();
}

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function pokm_run() {
	\DecaLog\Engine::initPlugin( POKM_SLUG, POKM_PRODUCT_NAME, POKM_VERSION, \KeysMaster\Plugin\Core::get_base64_logo() );
	// It is needed to do these inits here because some plugins make very early die()
	\KeysMaster\System\Password::init();
	\KeysMaster\Plugin\Feature\Capture::init();
	\KeysMaster\Plugin\Feature\Schema::init();
	$plugin = new KeysMaster\Plugin\Core();
	$plugin->run();
}

register_activation_hook( __FILE__, 'pokm_activate' );
register_deactivation_hook( __FILE__, 'pokm_deactivate' );
register_uninstall_hook( __FILE__, 'pokm_uninstall' );
pokm_run();
