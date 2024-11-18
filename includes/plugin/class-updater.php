<?php
/**
 * Plugin updates handling.
 *
 * @package Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin;

use KeysMaster\System\Nag;
use KeysMaster\System\Option;
use Exception;

use KeysMaster\System\Cache;
use KeysMaster\Plugin\Feature\Schema;
use KeysMaster\System\Markdown;

/**
 * Plugin updates handling.
 *
 * This class defines all code necessary to handle the plugin's updates.
 *
 * @package Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Updater {

	private $name = POKM_PRODUCT_NAME;
	private $slug = POKM_SLUG;
	private $version = POKM_VERSION;

	/**
	 * Initializes the class, set its properties and performs
	 * post-update processes if needed.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$old = Option::network_get( 'version' );
		Option::network_set( 'version', POKM_VERSION );
		if ( POKM_VERSION !== $old ) {
			if ( '0.0.0' === $old ) {
				$this->install();
				// phpcs:ignore
				$message = sprintf( esc_html__( '%1$s has been correctly installed.', 'keys-master' ), POKM_PRODUCT_NAME );
			} else {
				$this->update( $old );
				// phpcs:ignore
				$message = sprintf( esc_html__( '%1$s has been correctly updated from version %2$s to version %3$s.', 'keys-master' ), POKM_PRODUCT_NAME, $old, POKM_VERSION );
				\DecaLog\Engine::eventsLogger( POKM_SLUG )->notice( $message );
				// phpcs:ignore
				$message .= ' ' . sprintf( __( 'See <a href="%s">what\'s new</a>.', 'keys-master' ), admin_url( 'admin.php?page=pokm-settings&tab=about' ) );
			}
			Nag::add( 'update', 'info', $message );
		}
		if ( ! ( defined( 'POO_SELFUPDATE_BYPASS' ) && POO_SELFUPDATE_BYPASS ) ) {
			//add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
			//add_filter( 'site_transient_update_plugins', [ $this, 'info_update' ] );
			//add_action( 'upgrader_process_complete', [ $this, 'info_reset' ], 10, 2 );
		}


	}

	/**
	 * Performs post-installation processes.
	 *
	 * @since 1.0.0
	 */
	private function install() {

	}

	/**
	 * Performs post-update processes.
	 *
	 * @param string $from The version from which the plugin is updated.
	 *
	 * @since 1.0.0
	 */
	private function update( $from ) {
		$schema = new Schema();
		$schema->update();
		Cache::delete_global( 'data/*' );
		\DecaLog\Engine::eventsLogger( POKM_SLUG )->notice( 'Cache purged.' );
	}

	/**
	 * Get the changelog.
	 *
	 * @param array $attributes 'style' => 'markdown', 'html'.
	 *                             'mode'  => 'raw', 'clean'.
	 *
	 * @return  string  The output of the shortcode, ready to print.
	 * @since 1.0.0
	 */
	public function sc_get_changelog( $attributes ) {
		$md = new Markdown();
		return $md->get_shortcode( 'CHANGELOG.md', $attributes );
	}

	/**
	 * Acquires infos about update
	 *
	 * @return  object   The remote info.
	 */
	private function gather_info(){
		$remote = get_transient( 'update-' . $this->slug );
		if( false === $remote ) {
			$remote = wp_remote_get(
				'https://rudrastyh.com/wp-content/uploads/updater/info.json',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json'
					)
				)
			);
			if( is_wp_error( $remote ) || 200 !== wp_remote_retrieve_response_code( $remote ) || empty( wp_remote_retrieve_body( $remote ) ) ) {
				return false;
			}
			set_transient( 'update-' . $this->slug, $remote, DAY_IN_SECONDS );
		}
		$remote = json_decode( wp_remote_retrieve_body( $remote ) );
		return $remote;


/*
		$res->name = $this->name;
		$res->slug = $this->slug;

		$res->version = $remote->version;
		$res->tested = $remote->tested;
		$res->requires = $remote->requires;
		$res->author = $remote->author;
		$res->author_profile = $remote->author_profile;
		$res->requires_php = $remote->requires_php;
		$res->last_updated = $remote->last_updated;

		$res->sections = array(
			'description' => $remote->sections->description,
			'installation' => $remote->sections->installation,
			'changelog' => $remote->sections->changelog
		);




		$res->download_link = $remote->download_url;
		$res->trunk = $remote->download_url;*/
	}

	/**
	 * Updates infos transient
	 *
	 * @param Transient $transient The transient to update.
	 *
	 * @return  Transient   The updated transient.
	 */
	public function info_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$remote = $this->gather_info();
		if ( $remote && version_compare( $this->version, $remote->version, '<' ) && version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' ) && version_compare( $remote->requires_php, PHP_VERSION, '<' ) ) {
			$res                                 = new stdClass();
			$res->slug                           = $this->slug;
			$res->plugin                         = plugin_basename( __FILE__ );
			$res->new_version                    = $remote->version;
			$res->tested                         = $remote->tested;
			$res->package                        = $remote->download_url;
			$transient->response[ $res->plugin ] = $res;
		}

		return $transient;
	}

	/**
	 * Reset update infos
	 *
	 * @param Plugin_Upgrader $upgrader Upgrader instance.
	 * @param array $options Array of bulk item update data.
	 */
	public function info_reset( $upgrader, $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( 'update-' . $this->slug );
		}
	}
}
