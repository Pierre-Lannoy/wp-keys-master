<?php
/**
 * Provide a admin-facing tools for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package    Plugin
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

use KeysMaster\Plugin\Feature\Passwords;

$scripts = new Passwords();
$scripts->prepare_items();

wp_enqueue_script( POKM_ASSETS_ID );
wp_enqueue_style( POKM_ASSETS_ID );

?>

<div class="wrap">
	<h2><?php echo esc_html__( 'Application Passwords Management', 'keys-master' ); ?></h2>
	<?php settings_errors(); ?>
	<?php $scripts->views(); ?>
    <form id="keys-master-tools" method="post" action="<?php echo $scripts->get_url(); ?>">
        <input type="hidden" name="page" value="keys-master-tools" />
	    <?php $scripts->display(); ?>
    </form>
</div>
