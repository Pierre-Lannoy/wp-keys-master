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

$link = '';
if ( Role::SUPER_ADMIN === Role::admin_type() || Role::SINGLE_ADMIN === Role::admin_type() ) {
	$args         = [];
	$args['page'] = 'pokm-manager';
	$args['id']   = $session->get_user_id();
	$link         = '<br/>' . '<a href="' . add_query_arg( $args, admin_url( 'admin.php' ) ) . '">' . esc_html__( 'Manage', 'keys-master' ) . '</a>';
}

$conf = '';
if ( Role::SUPER_ADMIN === Role::admin_type() || Role::SINGLE_ADMIN === Role::admin_type() ) {
	$args         = [];
	$args['page'] = 'pokm-settings';
	$args['tab']  = 'roles';
	$conf         = '<br/>' . '<a href="' . add_query_arg( $args, admin_url( 'admin.php' ) ) . '">' . esc_html__( 'Settings', 'keys-master' ) . '</a>';
}

?>

<h2 id="sessions"><?php esc_html_e( 'Sessions Management', 'keys-master' ); ?></h2>
<table class="form-table">
    <tbody>
        <tr id="keysmaster-limit">
            <th><label for="keys-master-limits-title"><?php esc_html_e( 'Current limits', 'keys-master' ); ?></label></th>
            <td><?php echo $session->get_limits_as_text() . $conf; ?></td>
        </tr>
        <tr id="keysmaster-count">
            <th><label for="keys-master-count-title"><?php esc_html_e( 'Active sessions', 'keys-master' ); ?></label></th>
            <td><?php echo '<strong>' . $session->get_sessions_count() . '</strong>' . $link; ?></td>
        </tr>
    </tbody>
</table>
