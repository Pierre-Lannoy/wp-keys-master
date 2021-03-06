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

use KeysMaster\System\Environment;

?>

<div class="alignleft actions bulkactions">
    <label for="limit-selector" class="screen-reader-text"><?php esc_html_e('Number of passwords to display', 'keys-master');?></label>
    <select name="limit-<?php echo $which; ?>" id="limit-selector-<?php echo $which; ?>">
		<?php foreach ($list->get_line_number_select() as $line) { ?>
            <option <?php echo $line['selected']; ?>value="<?php echo $line['value']; ?>"><?php echo $line['text']; ?></option>
		<?php } ?>
    </select>
    <input type="submit" name="dolimit-<?php echo $which; ?>" id="dolimit-<?php echo $which; ?>" class="button action" value="<?php esc_html_e('Apply', 'keys-master');?>"  />
</div>
