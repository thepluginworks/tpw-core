<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once TPW_CORE_PATH . 'modules/members/includes/admin-settings-tabs.php';

wp_enqueue_script(
  'sortablejs',
  'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
  array(),
  null,
  true
);

$__searchable_opt = get_option('tpw_member_searchable_fields', []);
if (!is_array($__searchable_opt)) { $__searchable_opt = []; }
?>
<script>
window.TPW_SEARCHABLE = <?php echo wp_json_encode($__searchable_opt); ?>;
</script>

<style>
tr[data-custom="1"] {
  color: #0073aa;
}
/* Responsive labels for Field Settings */
.tpw-resp-text { display: none; color:#555; margin-left:6px; }
@media (max-width: 880px) {
	.tpw-field-settings .tpw-table-header { display:none; }
	.tpw-field-settings .tpw-table-row { display:block; padding:10px; margin:10px 0; border:1px solid #eee; border-radius:8px; background:#fafafa; }
	.tpw-field-settings .tpw-table-cell { display:flex; align-items:center; justify-content:space-between; padding:6px 4px; }
	.tpw-field-settings .tpw-table-cell:first-child { font-weight:600; }
	.tpw-field-settings .tpw-resp-text { display:inline; }
}
</style>
<div class="tpw-admin-wrapper">
<div class="tpw-field-settings tpw-settings-card">
	<h2>Member Settings</h2>
	<?php tpw_members_render_settings_tabs( 'field_settings' ); ?>
	<h3>Member Field Settings</h3>

	<?php if ( isset($_GET['updated']) && $_GET['updated'] == '1' ): ?>
		<div class="notice notice-success"><p>Settings saved successfully.</p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'tpw_save_field_settings', 'tpw_field_settings_nonce' ); ?>

		<?php
			// Support multiple selected conditionals as an array
			$conditional_selected = get_option('tpw_conditional_fields', []);
			if ( ! is_array($conditional_selected) ) {
				// Back-compat: if legacy single option exists, fold it in
				$legacy = get_option('tpw_conditional_field', '');
				$conditional_selected = $legacy ? [ $legacy ] : [];
			}
		?>
		<div class="tpw-table" id="sortable-fields">
			<div class="tpw-table-header">
				<div class="tpw-table-cell">Field</div>
				<div class="tpw-table-cell">Enabled</div>
				<div class="tpw-table-cell">Label</div>
				<div class="tpw-table-cell">Field Type</div>
				<div class="tpw-table-cell" style="display:none;">Sort Order</div>
				<div class="tpw-table-cell">Searchable</div>
				<div class="tpw-table-cell">Use in Conditions</div>
				<div class="tpw-table-cell">Delete</div>
			</div>
				<?php
				// Merge and normalize core and custom fields into a single array
				$all_fields = [];

				// Add core fields
				foreach ( $core_fields as $key => $fieldInfo ) {
					if ( $key === 'password_hash' ) continue;

					// $core_fields values can be arrays with ['label'=>..., 'type'=>...]
					$resolved_label = is_array($fieldInfo)
						? ( $fieldInfo['label'] ?? ucfirst( str_replace('_',' ', $key ) ) )
						: ( is_string($fieldInfo) ? $fieldInfo : ucfirst( str_replace('_',' ', $key ) ) );
					$config = isset($field_config[$key]) ? $field_config[$key] : null;
					$all_fields[] = [
						'key'         => $key,
						'label'       => $resolved_label,
						'is_enabled'  => $config ? (int)$config->is_enabled : 1,
						'custom_label'=> $config && !empty($config->custom_label) ? $config->custom_label : $resolved_label,
						'sort_order'  => isset($config->sort_order) ? intval($config->sort_order) : 0,
						'is_custom'   => false,
					];
				}

				// Add custom fields
				if ( ! empty($custom_fields) ) {
					foreach ( $custom_fields as $field ) {
						$key = $field['key'];
						$config = isset($field_config[$key]) ? $field_config[$key] : null;

						$all_fields[] = [
							'key'         => $key,
							'label'       => $field['label'],
							'is_enabled'  => isset($config->is_enabled) ? (int)$config->is_enabled : 1,
							'custom_label'=> $field['label'],
							'sort_order'  => isset($config->sort_order) ? intval($config->sort_order) : 999,
							'type'        => isset($field['type']) ? $field['type'] : 'text',
							'is_custom'   => true,
							'in_use'      => isset($field['in_use']) && $field['in_use'],
						];
					}
				}

				// Sort all fields by sort_order
				usort($all_fields, function($a, $b) {
					return $a['sort_order'] <=> $b['sort_order'];
				});
				?>

				<?php foreach ( $all_fields as $field ):
					$protected_keys = ['username', 'first_name', 'surname', 'status'];
					$is_protected = in_array($field['key'], $protected_keys);
				?>
				<div class="tpw-table-row"<?php echo $field['is_custom'] ? ' data-custom="1"' : ''; ?>>
					<div class="tpw-table-cell">
						<span class="dashicons dashicons-move tpw-sort-handle" style="cursor: move;"></span>
						<span style="<?php echo $field['is_custom'] ? 'color: #0073aa;' : ''; ?>">
							<?php echo esc_html($field['key']); ?>
						</span>
						<input type="hidden" name="<?php echo $field['is_custom'] ? 'custom_fields' : 'fields'; ?>[<?php echo esc_attr($field['key']); ?>][key]" value="<?php echo esc_attr($field['key']); ?>">
					</div>
					<?php
					$protected_keys = ['username', 'first_name', 'surname', 'status'];
					$is_protected = in_array($field['key'], $protected_keys);
					?>
					<div class="tpw-table-cell"<?php echo $is_protected ? ' style="opacity: 0.6;"' : ''; ?> >
						<label class="tpw-resp-label" style="display:inline-flex; align-items:center; gap:6px;">
							<input type="checkbox"
								name="<?php echo $field['is_custom'] ? 'custom_fields' : 'fields'; ?>[<?php echo esc_attr($field['key']); ?>][is_enabled]"
								value="1"
								<?php checked($field['is_enabled'], 1); ?>
								<?php echo $is_protected ? 'disabled title="This field is required and cannot be disabled."' : ''; ?>>
							<span class="tpw-resp-text">Enabled</span>
						</label>
					</div>
					<div class="tpw-table-cell">
						<input type="text" name="<?php echo $field['is_custom'] ? 'custom_fields' : 'fields'; ?>[<?php echo esc_attr($field['key']); ?>][custom_label]" value="<?php echo esc_attr($field['custom_label']); ?>">
					</div>
					<div class="tpw-table-cell">
						<input type="text" value="<?php echo esc_attr($field['type'] ?? ($field['is_custom'] ? 'text' : 'core')); ?>" readonly style="width:100px; background:#f9f9f9;">
					</div>
					<div class="tpw-table-cell" style="display:none;">
						<input type="number" name="<?php echo $field['is_custom'] ? 'custom_fields' : 'fields'; ?>[<?php echo esc_attr($field['key']); ?>][sort_order]" value="<?php echo esc_attr($field['sort_order']); ?>" style="width:60px;" class="sort-order-input">
					</div>

					<div class="tpw-table-cell">
						<?php
						  $searchable_config = get_option('tpw_member_searchable_fields', []);
						  $is_searchable = is_array($searchable_config) && isset($searchable_config[$field['key']]);
						?>
						<label class="tpw-resp-label" style="display:inline-flex; align-items:center; gap:6px;">
							<input type="checkbox" class="tpw-searchable-toggle" data-field-key="<?php echo esc_attr($field['key']); ?>" data-field-label="<?php echo esc_attr($field['custom_label']); ?>" <?php checked( $is_searchable ); ?> />
							<span class="tpw-resp-text">Searchable</span>
							<button type="button" class="button button-small tpw-search-config-btn" data-field-key="<?php echo esc_attr($field['key']); ?>" style="display: <?php echo $is_searchable ? 'inline-flex' : 'none'; ?>;" title="Edit search options">⚙️</button>
						</label>
					</div>
					<div class="tpw-table-cell">
						<label class="tpw-resp-label" style="display:inline-flex; align-items:center; gap:6px;">
							<input type="checkbox" name="tpw_conditional_fields[]" value="<?php echo esc_attr($field['key']); ?>" <?php checked( in_array($field['key'], $conditional_selected, true) ); ?> />
							<span class="tpw-resp-text">Use in Conditions</span>
							<span class="screen-reader-text">Use in conditions</span>
						</label>
					</div>
					<div class="tpw-table-cell">
						<?php if ( $field['is_custom'] ): ?>
							<?php if ( $field['in_use'] ): ?>
								<button class="button" disabled title="This field is in use and cannot be deleted.">
									<span class="dashicons dashicons-trash"></span>
								</button>
							<?php else: ?>
								<button class="button delete-button" type="submit" name="delete_custom_field[]" value="<?php echo esc_attr($field['key']); ?>" onclick="return confirm('Are you sure you want to delete this custom field? This action cannot be undone.');">
									<span class="dashicons dashicons-trash"></span>
								</button>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
		</div> <!-- .tpw-table -->

		<h3>Add Custom Field</h3>
		<p>
			<label for="new_meta_label">Field Name:</label>
			<input type="text" id="new_meta_label" name="new_meta_label">
		</p>
		<p>
			<label for="new_meta_type">Field Type:</label>
			<select id="new_meta_type" name="new_meta_type">
				<option value="text">Text</option>
				<option value="textarea">Textarea</option>
				<option value="select">Dropdown</option>
				<option value="checkbox">Checkbox</option>
				<option value="date">Date</option>
			</select>
		</p>
		<input type="hidden" id="new_meta_key" name="new_meta_key" readonly value="">

		<p><button type="submit" class="button button-primary">Save Settings</button></p>
	</form>

<!-- Search Config Modal (moved outside the main form to avoid nested forms) -->
<div id="tpw-search-config-modal" class="tpw-dir-modal" hidden>
	<div class="tpw-dir-modal__dialog" style="max-width:520px;">
		<div class="tpw-dir-modal__header">
			<h3>Search Options</h3>
			<button type="button" class="button tpw-dir-modal-close">Close</button>
		</div>
		<div class="tpw-dir-modal__body">
			<form id="tpw-search-config-form">
				<input type="hidden" name="field_key" id="tpw-search-field-key" value="">
				<input type="hidden" name="label" id="tpw-search-field-label" value="">
				<p>
					<label for="tpw-search-type"><strong>Search Type</strong></label><br>
					<select name="search_type" id="tpw-search-type">
						<option value="text">Text</option>
						<option value="select">Select</option>
						<option value="date_range">Date range</option>
						<option value="has_value">Has Value (not empty)</option>
							<option value="checkbox">Checkbox</option>
					</select>
				</p>
				<p>
					<label style="display:inline-flex; align-items:center; gap:8px;">
						<input type="checkbox" id="tpw-search-admin-only" name="admin_only" value="1">
						<span>For Admins Only</span>
					</label>
				</p>
				<p id="tpw-search-options-wrap" style="display:none;">
						<label for="tpw-options-source"><strong>Options Source</strong></label><br>
						<select id="tpw-options-source" name="options_source" style="margin-bottom:8px;">
							<option value="static">Static (enter manually)</option>
							<option value="dynamic">Dynamic (load from existing values)</option>
						</select>
						<label for="tpw-search-options"><strong>Options</strong> (comma or newline separated)</label><br>
					<textarea name="options" id="tpw-search-options" rows="5" style="width:100%;"></textarea>
				</p>
				<p class="description" id="tpw-search-help"></p>
				<div style="margin-top:10px;">
					<button type="submit" class="button button-primary">Save</button>
					<button type="button" class="button tpw-search-cancel">Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  new Sortable(document.getElementById('sortable-fields'), {
    animation: 150,
    handle: '.tpw-sort-handle',
    onEnd: function () {
      let index = 1;
      document.querySelectorAll('#sortable-fields .tpw-table-row').forEach((row) => {
        const input = row.querySelector('.sort-order-input');
        if (input) {
          input.value = index++;
        }
      });
    }
  });

	// Searchable toggle logic
	const nonce = '<?php echo esc_js( wp_create_nonce('tpw_member_searchable_nonce') ); ?>';
	const ajaxUrl = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
	document.querySelectorAll('.tpw-searchable-toggle').forEach(function(cb){
		cb.addEventListener('change', function(){
			const key = this.getAttribute('data-field-key');
			const label = this.getAttribute('data-field-label') || key;
			const checked = this.checked ? '1' : '0';
			const row = this.closest('.tpw-table-row');
			const gear = row ? row.querySelector('.tpw-search-config-btn') : null;
			if (gear) gear.style.display = this.checked ? 'inline-flex' : 'none';
			const formData = new FormData();
			formData.append('action', 'tpw_member_toggle_searchable');
			formData.append('_wpnonce', nonce);
			formData.append('field_key', key);
			formData.append('label', label);
			formData.append('searchable', checked);
			fetch(ajaxUrl, { method:'POST', body: formData })
				.then(r=>r.json())
				.then(data=>{
					if (!data || !data.success) {
						alert((data && data.data && data.data.message) ? data.data.message : 'Failed to update');
						// revert UI
						this.checked = !this.checked;
						if (gear) gear.style.display = this.checked ? 'inline-flex' : 'none';
						return;
					}
					// Keep in-memory config in sync so modal pre-fills correctly
					window.TPW_SEARCHABLE = window.TPW_SEARCHABLE || {};
					if (checked === '1') {
						// Server returns the saved config for this field
						if (data.data && data.data.config) {
							window.TPW_SEARCHABLE[key] = data.data.config;
						} else {
							// Fallback minimal config
							window.TPW_SEARCHABLE[key] = { label: label, searchable: true, search_type: 'text', options: [], admin_only: false };
						}
					} else {
						delete window.TPW_SEARCHABLE[key];
					}
				})
				.catch(()=>{
					alert('Network error');
					this.checked = !this.checked;
					if (gear) gear.style.display = this.checked ? 'inline-flex' : 'none';
				});
		});
	});

	// Modal helpers
	const modal = document.getElementById('tpw-search-config-modal');
	const closeBtns = modal ? modal.querySelectorAll('.tpw-dir-modal-close, .tpw-search-cancel') : [];
	// Options source select element (used across handlers)
	let optSourceEl = null;
	if (modal) { optSourceEl = modal.querySelector('#tpw-options-source'); }
	function openModal(){ modal.removeAttribute('hidden'); }
	function closeModal(){ modal.setAttribute('hidden',''); }
	closeBtns.forEach(btn=> btn.addEventListener('click', closeModal));

		function syncHelp(){
		const type = document.getElementById('tpw-search-type').value;
		const help = document.getElementById('tpw-search-help');
			const optionsWrap = document.getElementById('tpw-search-options-wrap');
				if (type === 'select') {
			optionsWrap.style.display = '';
			help.textContent = 'Select: renders a dropdown. Choose Static to enter options, or Dynamic to load distinct values from members.';
			} else if (type === 'date_range') {
			optionsWrap.style.display = 'none';
			help.textContent = 'Date range: renders From/To date inputs and filters between them.';
			} else if (type === 'has_value') {
				optionsWrap.style.display = 'none';
				help.textContent = 'Has Value: renders a single checkbox to require non-empty values.';
			} else if (type === 'checkbox') {
				optionsWrap.style.display = 'none';
				help.textContent = 'Checkbox: renders a boolean checkbox. When checked, filter results to column = 1; when unchecked, do not filter.';
		} else {
			optionsWrap.style.display = 'none';
			help.textContent = 'Text: renders a text input with partial match.';
		}
	}
	if (modal) {
		document.getElementById('tpw-search-type').addEventListener('change', syncHelp);
		syncHelp();
	}

	// Open modal and load existing config if any
	document.querySelectorAll('.tpw-search-config-btn').forEach(function(btn){
		btn.addEventListener('click', function(){
			const key = this.getAttribute('data-field-key');
			const row = this.closest('.tpw-table-row');
			const labelInput = row ? row.querySelector('input[type="text"][name$="[custom_label]"]') : null;
			const label = labelInput ? labelInput.value : (row ? row.querySelector('span')?.textContent.trim() : key);
			document.getElementById('tpw-search-field-key').value = key;
			document.getElementById('tpw-search-field-label').value = label;
			// Pre-fill from option printed server-side
			const conf = (window.TPW_SEARCHABLE || {} )[key] || null;
			document.getElementById('tpw-search-type').value = conf && conf.search_type ? conf.search_type : 'text';
			document.getElementById('tpw-search-options').value = (conf && conf.options && conf.options.length) ? conf.options.join('\n') : '';
			document.getElementById('tpw-search-admin-only').checked = !!(conf && conf.admin_only);
				if (optSourceEl) { optSourceEl.value = conf && conf.options_source ? conf.options_source : 'static'; }
			syncHelp();
			openModal();
		});
	});

	// Submit modal
	if (modal) {
		modal.querySelector('#tpw-search-config-form').addEventListener('submit', function(e){
			e.preventDefault();
			const key = document.getElementById('tpw-search-field-key').value;
			const label = document.getElementById('tpw-search-field-label').value;
			const type = document.getElementById('tpw-search-type').value;
			const options = document.getElementById('tpw-search-options').value;
			const adminOnly = document.getElementById('tpw-search-admin-only').checked ? '1' : '0';
			const formData = new FormData();
			formData.append('action', 'tpw_member_save_search_config');
			formData.append('_wpnonce', nonce);
			formData.append('field_key', key);
			formData.append('label', label);
			formData.append('search_type', type);
			formData.append('options', options);
			formData.append('options_source', optSourceEl ? optSourceEl.value : 'static');
			formData.append('admin_only', adminOnly);
			fetch(ajaxUrl, { method:'POST', body: formData })
				.then(r=>r.json())
				.then(data=>{
					if (!data || !data.success) {
						alert((data && data.data && data.data.message) ? data.data.message : 'Failed to save');
						return;
					}
					// keep config in memory for re-open
					window.TPW_SEARCHABLE = window.TPW_SEARCHABLE || {};
					window.TPW_SEARCHABLE[key] = data.data.config;
					closeModal();
				})
				.catch(()=> alert('Network error'));
		});
	}
});
</script>
</div>
</div>