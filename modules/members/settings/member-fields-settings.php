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
$__autofill_form_attrs = ' autocomplete="off" data-form-type="other" data-lpignore="true" data-1p-ignore="true" data-op-ignore="true"';
$__autofill_input_attrs = ' autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-form-type="other" data-lpignore="true" data-1p-ignore="true" data-op-ignore="true"';
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

	<form method="post"<?php echo $__autofill_form_attrs; ?>>
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
				<div class="tpw-table-cell">Section</div>
				<div class="tpw-table-cell">Field Type</div>
				<div class="tpw-table-cell" style="display:none;">Sort Order</div>
				<div class="tpw-table-cell">Searchable</div>
				<div class="tpw-table-cell">Basic Search</div>
				<div class="tpw-table-cell">Use in Conditions</div>
				<div class="tpw-table-cell">Download</div>
				<div class="tpw-table-cell">Delete</div>
			</div>
				<?php
				$__sections_map = get_option('tpw_member_field_sections', []);
				if (!is_array($__sections_map)) { $__sections_map = []; }
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
					$is_fixed_username_label = ( 'username' === $field['key'] );
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
						<?php if ( $is_fixed_username_label ) : ?>
							<div style="padding:6px 8px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">Username</div>
						<?php else : ?>
							<input type="text" class="tpw-autofill-guard" name="<?php echo $field['is_custom'] ? 'custom_fields' : 'fields'; ?>[<?php echo esc_attr($field['key']); ?>][custom_label]" value="<?php echo esc_attr($field['custom_label']); ?>"<?php echo $__autofill_input_attrs; ?>>
						<?php endif; ?>
					</div>
					<div class="tpw-table-cell">
						<?php $current_section = isset($__sections_map[$field['key']]) && $__sections_map[$field['key']] !== '' ? $__sections_map[$field['key']] : ''; ?>
						<input type="text" class="tpw-autofill-guard" name="<?php echo $field['is_custom'] ? 'custom_fields' : 'fields'; ?>[<?php echo esc_attr($field['key']); ?>][section]" value="<?php echo esc_attr($current_section); ?>" placeholder="General" style="max-width:140px;"<?php echo $__autofill_input_attrs; ?>>
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
							<button type="button" class="tpw-btn tpw-btn-light tpw-btn-sm tpw-search-config-btn" data-field-key="<?php echo esc_attr($field['key']); ?>" style="display: <?php echo $is_searchable ? 'inline-flex' : 'none'; ?>;" title="Edit search options">⚙️</button>
						</label>
					</div>
					<?php
					  // Determine current basic_search flag from DB config if available
					  $basic_search_val = 0;
					  if ( isset($field_config[$field['key']]) && isset($field_config[$field['key']]->basic_search) ) {
					      $basic_search_val = (int) $field_config[$field['key']]->basic_search;
					  }
					?>
					<div class="tpw-table-cell">
						<label class="tpw-resp-label" style="display:inline-flex; align-items:center; gap:6px;">
							<input type="checkbox" name="<?php echo $field['is_custom'] ? 'custom_fields' : 'fields'; ?>[<?php echo esc_attr($field['key']); ?>][basic_search]" value="1" <?php checked( $basic_search_val, 1 ); ?> />
							<span class="tpw-resp-text">Basic Search</span>
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
						<label class="tpw-resp-label" style="display:inline-flex; align-items:center; gap:6px;">
							<input type="checkbox" name="tpw_member_field_download[]" value="<?php echo esc_attr($field['key']); ?>" <?php $dl = get_option('tpw_member_field_download', []); if (!is_array($dl)) { $dl = []; } checked( in_array($field['key'], $dl, true) ); ?> />
							<span class="tpw-resp-text">Download</span>
						</label>
					</div>
					<div class="tpw-table-cell">
						<?php if ( $field['is_custom'] ): ?>
							<?php if ( $field['in_use'] ): ?>
								<button class="tpw-btn tpw-btn-light tpw-btn-sm" disabled title="This field is in use and cannot be deleted.">
									<span class="dashicons dashicons-trash"></span>
								</button>
							<?php else: ?>
								<button class="tpw-btn tpw-btn-danger tpw-btn-sm delete-button" type="submit" name="delete_custom_field[]" value="<?php echo esc_attr($field['key']); ?>" onclick="return confirm('Are you sure you want to delete this custom field? This action cannot be undone.');">
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
			<input type="text" class="tpw-autofill-guard" id="new_meta_label" name="new_meta_label"<?php echo $__autofill_input_attrs; ?>>
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
		<input type="hidden" id="new_meta_key" name="new_meta_key" readonly value="" data-lpignore="true" data-1p-ignore="true">

	<p><button type="submit" class="tpw-btn tpw-btn-primary">Save Settings</button></p>
	</form>

<!-- Search Config Modal (moved outside the main form to avoid nested forms) -->
<div id="tpw-search-config-modal" class="tpw-dir-modal" hidden>
	<div class="tpw-dir-modal__dialog" style="max-width:520px;">
		<div class="tpw-dir-modal__header">
			<h3>Search Options</h3>
			<button type="button" class="tpw-btn tpw-btn-light tpw-dir-modal-close">Close</button>
		</div>
		<div class="tpw-dir-modal__body">
			<form id="tpw-search-config-form"<?php echo $__autofill_form_attrs; ?>>
				<input type="hidden" name="field_key" id="tpw-search-field-key" value="">
				<input type="hidden" name="label" id="tpw-search-field-label" value="" data-lpignore="true" data-1p-ignore="true">
				<input type="hidden" name="depends_on" id="tpw-search-depends-on" value="">
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
				<p id="tpw-search-dependency-wrap" style="display:none;">
					<label for="tpw-depends-select"><strong>Depends on Field</strong></label><br>
					<select id="tpw-depends-select" style="margin-bottom:8px; max-width:100%;">
						<option value="">— None —</option>
					</select>
					<small class="description">Filters this field's options by the selected parent field's chosen value. One level only.</small>
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
					<textarea name="options" class="tpw-autofill-guard" id="tpw-search-options" rows="5" style="width:100%;"<?php echo $__autofill_input_attrs; ?>></textarea>
				</p>
				<p class="description" id="tpw-search-help"></p>
				<div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
					<button type="submit" class="tpw-btn tpw-btn-primary">Save</button>
					<button type="button" class="tpw-btn tpw-btn-light tpw-search-cancel">Cancel</button>
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

		const guardedFields = Array.from(document.querySelectorAll('.tpw-field-settings .tpw-autofill-guard'));
		function markAutofillBaseline(field) {
			if (!field) return;
			field.dataset.tpwAutofillBaseline = field.value;
			field.dataset.tpwAutofillDirty = '0';
		}
		function scrubAutofillField(field) {
			if (!field || field.dataset.tpwAutofillDirty === '1') return;
			const baseline = Object.prototype.hasOwnProperty.call(field.dataset, 'tpwAutofillBaseline')
				? field.dataset.tpwAutofillBaseline
				: '';
			if (field.value !== baseline) {
				field.value = baseline;
			}
		}
		function scrubAutofillFields() {
			guardedFields.forEach(scrubAutofillField);
		}
		guardedFields.forEach(function(field){
			markAutofillBaseline(field);
			field.addEventListener('input', function(event){
				if (event.isTrusted) {
					field.dataset.tpwAutofillDirty = '1';
					field.dataset.tpwAutofillBaseline = field.value;
				}
			});
			field.addEventListener('change', function(event){
				if (event.isTrusted) {
					field.dataset.tpwAutofillDirty = '1';
					field.dataset.tpwAutofillBaseline = field.value;
				}
			});
			field.addEventListener('focus', function(){
				scrubAutofillField(field);
			});
			field.addEventListener('pointerdown', function(){
				scrubAutofillField(field);
			});
		});
		window.setTimeout(scrubAutofillFields, 0);
		window.setTimeout(scrubAutofillFields, 250);
		window.setTimeout(scrubAutofillFields, 1000);
		window.setTimeout(scrubAutofillFields, 2000);
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
			markAutofillBaseline(document.getElementById('tpw-search-options'));
			document.getElementById('tpw-search-admin-only').checked = !!(conf && conf.admin_only);
				if (optSourceEl) { optSourceEl.value = conf && conf.options_source ? conf.options_source : 'static'; }
			// Dependency list: build choices of other searchable/basic-search fields excluding self
			buildDependencyChoices(key, conf && conf.depends_on ? conf.depends_on : '');
			syncDependencyVisibility();
			if (conf && conf.depends_on) {
				document.getElementById('tpw-search-depends-on').value = conf.depends_on;
				document.getElementById('tpw-depends-select').value = conf.depends_on;
			}
			syncHelp();
			openModal();
		});
	});

	function buildDependencyChoices(currentKey, selected){
		const wrap = document.getElementById('tpw-depends-select');
		if (!wrap) return;
		while (wrap.firstChild) wrap.removeChild(wrap.firstChild);
		wrap.appendChild(new Option('— None —',''));
		// Collect candidates: searchable or basic_search enabled
		const searchable = window.TPW_SEARCHABLE || {};
		const basicMap = {};
		document.querySelectorAll('input[type="checkbox"][name$="[basic_search]"]').forEach(function(cb){
			if (cb.checked){
				const fkey = cb.name.match(/\[(.*?)\]\[basic_search\]/); if (fkey && fkey[1]) basicMap[fkey[1]] = true;
			}
		});
		const used = new Set();
		Object.keys(searchable).forEach(function(k){ if (k!==currentKey) used.add(k); });
		Object.keys(basicMap).forEach(function(k){ if (k!==currentKey) used.add(k); });
		Array.from(used).sort().forEach(function(k){
			const label = (searchable[k] && searchable[k].label) ? searchable[k].label : k;
			wrap.appendChild(new Option(label, k));
		});
		if (selected) { wrap.value = selected; }
	}

	function syncDependencyVisibility(){
		const type = document.getElementById('tpw-search-type').value;
		const depWrap = document.getElementById('tpw-search-dependency-wrap');
		if (!depWrap) return;
		if (['select'].includes(type)) { // extend later for multi-select/autocomplete types
			depWrap.style.display='';
		} else {
			depWrap.style.display='none';
			document.getElementById('tpw-depends-select').value='';
			document.getElementById('tpw-search-depends-on').value='';
		}
	}

	document.getElementById('tpw-search-type').addEventListener('change', function(){
		syncDependencyVisibility();
		// If dependency selected and type becomes non-select, clear it
	});

	const dependsSelectEl = document.getElementById('tpw-depends-select');
	if (dependsSelectEl){
		dependsSelectEl.addEventListener('change', function(){
			const currentKey = document.getElementById('tpw-search-field-key').value;
			const chosen = this.value;
			if (chosen === currentKey){
				alert('A field cannot depend on itself.');
				this.value='';
			}
			// Circular check: parent depends on child
			if (chosen){
				const searchable = window.TPW_SEARCHABLE || {};
				if (searchable[chosen] && searchable[chosen].depends_on === currentKey){
					alert('Circular dependency not allowed (parent already depends on child).');
					this.value='';
				}
			}
			document.getElementById('tpw-search-depends-on').value = this.value;
			// Force dynamic source when dependency active
			if (this.value && optSourceEl){
				optSourceEl.value='dynamic';
				optSourceEl.setAttribute('disabled','disabled');
			} else if (optSourceEl) {
				optSourceEl.removeAttribute('disabled');
			}
		});
	}

	// Submit modal
	if (modal) {
		modal.querySelector('#tpw-search-config-form').addEventListener('submit', function(e){
			e.preventDefault();
			const key = document.getElementById('tpw-search-field-key').value;
			const label = document.getElementById('tpw-search-field-label').value;
			const type = document.getElementById('tpw-search-type').value;
			const options = document.getElementById('tpw-search-options').value;
			const adminOnly = document.getElementById('tpw-search-admin-only').checked ? '1' : '0';
			const dependsOn = document.getElementById('tpw-search-depends-on').value || '';
			const formData = new FormData();
			formData.append('action', 'tpw_member_save_search_config');
			formData.append('_wpnonce', nonce);
			formData.append('field_key', key);
			formData.append('label', label);
			formData.append('search_type', type);
			formData.append('options', options);
			formData.append('options_source', optSourceEl ? optSourceEl.value : 'static');
			formData.append('admin_only', adminOnly);
			formData.append('depends_on', dependsOn);
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