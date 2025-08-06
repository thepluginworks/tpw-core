<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="tpw-field-settings">
	<h2>Member Field Settings</h2>

	<?php if ( isset($_GET['updated']) && $_GET['updated'] == '1' ): ?>
		<div class="notice notice-success"><p>Settings saved successfully.</p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'tpw_save_field_settings', 'tpw_field_settings_nonce' ); ?>

		<h3>Core Fields</h3>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th>Field</th>
					<th>Enabled</th>
					<th>Label</th>
					<th>Sort Order</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $core_fields as $key => $label ): 
					$config = isset($field_config[$key]) ? $field_config[$key] : null;
					$enabled = $config ? (int)$config->enabled : 1;
					$custom_label = $config && !empty($config->label) ? $config->label : $label;
					$sort_order = $config ? intval($config->sort_order) : 0;
				?>
				<tr>
					<td><?php echo esc_html($label); ?> <input type="hidden" name="fields[<?php echo esc_attr($key); ?>][key]" value="<?php echo esc_attr($key); ?>"></td>
					<td><input type="checkbox" name="fields[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($enabled, 1); ?>></td>
					<td><input type="text" name="fields[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($custom_label); ?>"></td>
					<td><input type="number" name="fields[<?php echo esc_attr($key); ?>][sort_order]" value="<?php echo esc_attr($sort_order); ?>" style="width:60px;"></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3>Add Custom Field</h3>
		<p>
			<label for="new_meta_key">Meta Key:</label>
			<input type="text" id="new_meta_key" name="new_meta_key" required>
		</p>
		<p>
			<label for="new_meta_label">Label:</label>
			<input type="text" id="new_meta_label" name="new_meta_label" required>
		</p>

		<p><button type="submit" class="button button-primary">Save Settings</button></p>
	</form>

	<?php if ( ! empty($custom_fields) ): ?>
		<h3>Existing Custom Fields</h3>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th>Meta Key</th>
					<th>Label</th>
					<th>Delete</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $custom_fields as $field ): ?>
				<tr>
					<td><?php echo esc_html($field['key']); ?></td>
					<td>
						<input type="text" name="custom_fields[<?php echo esc_attr($field['key']); ?>][label]" value="<?php echo esc_attr($field['label']); ?>">
					</td>
					<td>
<?php
// Assuming $field['in_use'] will be set to true by the backend later
$in_use = isset($field['in_use']) && $field['in_use'];
?>
<input type="checkbox"
	name="custom_fields[<?php echo esc_attr($field['key']); ?>][delete]"
	value="1"
	<?php echo $in_use ? 'disabled' : ''; ?>
	onclick="return confirm('Are you sure you want to delete this custom field? This action cannot be undone.');"
>
<?php if ( $in_use ): ?>
	<span class="description">Field is in use and cannot be deleted.</span>
<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>