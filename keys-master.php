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
 * Plugin URI:        https://github.com/Pierre-Lannoy/wp-keys-master
 * Description:       Powerful sessions manager for WordPress with sessions limiter and full analytics reporting capabilities.
 * Version:           1.2.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Pierre Lannoy
 * Author URI:        https://pierre.lannoy.fr
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
require_once __DIR__ . '/includes/system/class-option.php';
require_once __DIR__ . '/includes/system/class-environment.php';
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/libraries/class-libraries.php';
require_once __DIR__ . '/includes/libraries/autoload.php';

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
	// It is needed to do these inits here because some plugins make very early die()
	\KeysMaster\System\Logger::init();
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
