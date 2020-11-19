<?php
/**
 * Provide a admin-facing view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package    Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

use KeysMaster\System\Role;
use KeysMaster\System\GeoIP;

$geoip = new GeoIP();

wp_enqueue_script( 'pokm-moment-with-locale' );
wp_enqueue_script( 'pokm-daterangepicker' );
wp_enqueue_script( 'pokm-chartist' );
wp_enqueue_script( 'pokm-chartist-tooltip' );
wp_enqueue_script( POKM_ASSETS_ID );
wp_enqueue_style( POKM_ASSETS_ID );
wp_enqueue_style( 'pokm-daterangepicker' );
wp_enqueue_style( 'pokm-tooltip' );
wp_enqueue_style( 'pokm-chartist' );
wp_enqueue_style( 'pokm-chartist-tooltip' );


?>

<div class="wrap">
	<div class="pokm-dashboard">
		<div class="pokm-row">
			<?php echo $analytics->get_title_bar() ?>
		</div>
        <div class="pokm-row">
	        <?php echo $analytics->get_kpi_bar() ?>
        </div>
        <div class="pokm-row">
			<?php echo $analytics->get_main_chart() ?>
        </div>
        <div class="pokm-row">
            <div class="pokm-box pokm-box-50-50-line">
				<?php /*echo $analytics->get_login_pie() ?>
				<?php echo $analytics->get_clean_pie() */ ?>
            </div>
        </div>
		<?php if ( $geoip->is_installed() ) { ?>
            <div class="pokm-row">
				<?php echo $analytics->get_countries_list() ?>
            </div>
		<?php } ?>
		<?php if ( true || Role::SUPER_ADMIN === Role::admin_type() ) { ?>
            <div class="pokm-row">
				<?php echo $analytics->get_sites_list() ?>
            </div>
		<?php } ?>
	</div>
</div>
