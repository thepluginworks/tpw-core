<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_script(
	'sortablejs',
	'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
	array(),
	null,
	true
);

$signup_settings = TPW_Signup_Field_Schema::get_members_signup_settings();
$signup_fields   = TPW_Signup_Field_Schema::get_signup_field_settings_rows();
$signup_sections = TPW_Signup_Sections::get_sections();
$join_providers  = class_exists( 'TPW_Join_Page' ) ? TPW_Join_Page::get_registered_providers() : array();
?>
<p class="description">Configure whether Members sign-ups are enabled, which existing member fields are allowed on the public Join form, and which page is managed as the Join entry point.</p>

<p>
	<label>
		<input type="checkbox" name="tpw_members_settings[enable_signups]" value="1" <?php checked( $signup_settings['enable_signups'], '1' ); ?> />
		<?php echo esc_html__( 'Enable Sign-Ups', 'tpw-core' ); ?>
	</label>
</p>

<p>
	<label for="tpw_members_signup_page_id"><strong><?php echo esc_html__( 'Join Page', 'tpw-core' ); ?></strong></label><br>
	<?php
	wp_dropdown_pages(
		array(
			'name'              => 'tpw_members_settings[signup_page_id]',
			'id'                => 'tpw_members_signup_page_id',
			'selected'          => $signup_settings['signup_page_id'],
			'show_option_none'  => __( '— Select —', 'tpw-core' ),
			'option_none_value' => '0',
		)
	);
	?>
	<br>
	<small class="description"><?php echo esc_html__( 'When Sign-Ups are enabled, TPW Core will provision or reuse a Join page automatically and store it here. You can also point the feature at an existing page and the Join shortcode will be added if needed.', 'tpw-core' ); ?></small>
</p>

<p>
	<label for="tpw_members_join_provider_key"><strong><?php echo esc_html__( 'Active Join Provider', 'tpw-core' ); ?></strong></label><br>
	<select name="tpw_members_settings[join_provider_key]" id="tpw_members_join_provider_key">
		<?php foreach ( $join_providers as $provider ) : ?>
			<option value="<?php echo esc_attr( $provider['key'] ); ?>" <?php selected( $signup_settings['join_provider_key'], $provider['key'] ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
		<?php endforeach; ?>
	</select>
	<br>
	<small class="description"><?php echo esc_html__( 'Select which registered provider tpw_join_form dispatches to on the managed Join page. The page content itself is not rewritten.', 'tpw-core' ); ?></small>
</p>

<hr>

<p><strong><?php echo esc_html__( 'Field Configuration', 'tpw-core' ); ?></strong></p>
<p class="description"><?php echo esc_html__( 'Only public-safe fields are shown here. Enable the fields you want on the Join form, choose whether they are required, and place them into the fixed Core section registry.', 'tpw-core' ); ?></p>
<p class="description"><?php echo esc_html__( 'Drag rows to change field order. Ordering is saved within each section.', 'tpw-core' ); ?></p>

<style>
.tpw-signups-resp {
	display: none;
	color: #555;
	margin-left: 6px;
}

.tpw-signups-sort-handle {
	cursor: move;
	color: #646970;
	margin-right: 8px;
	vertical-align: middle;
}

.tpw-signups-sortable .tpw-table-row {
	transition: background-color 120ms ease;
}

.tpw-signups-sortable .tpw-table-row:hover {
	background: #fcfcfd;
}

.tpw-signups-sortable .sortable-ghost {
	opacity: 0.55;
	background: #f0f6fc;
}

@media (max-width: 980px) {
	.tpw-member-settings .tpw-table-header {
		display: none;
	}

	.tpw-member-settings .tpw-table-row {
		display: block;
		padding: 10px;
		margin: 10px 0;
		border: 1px solid #eee;
		border-radius: 8px;
		background: #fafafa;
	}

	.tpw-member-settings .tpw-table-cell {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 6px 4px;
	}

	.tpw-member-settings .tpw-table-cell:first-child {
		font-weight: 600;
	}

	.tpw-signups-resp {
		display: inline;
	}
	}
</style>

<div class="tpw-table">
	<div class="tpw-table-header">
		<div class="tpw-table-cell"><?php echo esc_html__( 'Field', 'tpw-core' ); ?></div>
		<div class="tpw-table-cell"><?php echo esc_html__( 'Label', 'tpw-core' ); ?></div>
		<div class="tpw-table-cell"><?php echo esc_html__( 'Enabled', 'tpw-core' ); ?></div>
		<div class="tpw-table-cell"><?php echo esc_html__( 'Required', 'tpw-core' ); ?></div>
		<div class="tpw-table-cell"><?php echo esc_html__( 'Section', 'tpw-core' ); ?></div>
	</div>
	<div id="tpw-signup-fields-sortable" class="tpw-signups-sortable">
	<?php foreach ( $signup_fields as $field ) : ?>
		<div class="tpw-table-row" data-field-key="<?php echo esc_attr( $field['key'] ); ?>">
			<div class="tpw-table-cell">
				<span class="dashicons dashicons-move tpw-signups-sort-handle" aria-hidden="true"></span>
				<code><?php echo esc_html( $field['key'] ); ?></code>
			</div>
			<div class="tpw-table-cell"><?php echo esc_html( $field['label'] ); ?></div>
			<div class="tpw-table-cell">
				<label style="display:inline-flex; align-items:center; gap:6px;">
					<input type="checkbox" name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_enabled]" value="1" <?php checked( $field['signup_enabled'] ); ?> />
					<span class="tpw-signups-resp"><?php echo esc_html__( 'Enabled', 'tpw-core' ); ?></span>
				</label>
			</div>
			<div class="tpw-table-cell">
				<label style="display:inline-flex; align-items:center; gap:6px;">
					<input type="checkbox" name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_required]" value="1" <?php checked( $field['signup_required'] ); ?> />
					<span class="tpw-signups-resp"><?php echo esc_html__( 'Required', 'tpw-core' ); ?></span>
				</label>
			</div>
			<div class="tpw-table-cell">
				<select name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_section]" class="tpw-signup-section-select">
					<option value=""><?php echo esc_html__( '— Select —', 'tpw-core' ); ?></option>
					<?php foreach ( $signup_sections as $section_key => $section_label ) : ?>
						<option value="<?php echo esc_attr( $section_key ); ?>" <?php selected( $field['signup_section'], $section_key ); ?>><?php echo esc_html( $section_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<input type="hidden" name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_order]" value="<?php echo esc_attr( $field['signup_order'] ); ?>" class="tpw-signup-order-input" />
		</div>
	<?php endforeach; ?>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	const sortableRoot = document.getElementById('tpw-signup-fields-sortable');
	if (!sortableRoot || typeof Sortable === 'undefined') {
		return;
	}

	let orderDirty = false;

	const recalculateSectionOrder = function () {
		const counters = {};
		sortableRoot.querySelectorAll('.tpw-table-row').forEach(function (row) {
			const sectionSelect = row.querySelector('.tpw-signup-section-select');
			const orderInput = row.querySelector('.tpw-signup-order-input');
			if (!sectionSelect || !orderInput) {
				return;
			}

			const section = sectionSelect.value || '__unsectioned__';
			if (!counters[section]) {
				counters[section] = 10;
			}

			orderInput.value = counters[section];
			counters[section] += 10;
		});
	};

	new Sortable(sortableRoot, {
		animation: 150,
		handle: '.tpw-signups-sort-handle',
		draggable: '.tpw-table-row',
		onEnd: function () {
			orderDirty = true;
			recalculateSectionOrder();
		}
	});

	sortableRoot.querySelectorAll('.tpw-signup-section-select').forEach(function (select) {
		select.addEventListener('change', function () {
			orderDirty = true;
			recalculateSectionOrder();
		});
	});

	const form = sortableRoot.closest('form');
	if (form) {
		form.addEventListener('submit', function () {
			if (orderDirty) {
				recalculateSectionOrder();
			}
		});
	}
});
</script>