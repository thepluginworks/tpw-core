<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$signup_settings = TPW_Signup_Field_Schema::get_members_signup_settings();
$signup_fields   = TPW_Signup_Field_Schema::get_signup_field_settings_rows();
$signup_sections = TPW_Signup_Sections::get_sections();
?>
<p class="description">Configure whether Members sign-ups are enabled and which existing member fields are allowed on future public sign-up forms.</p>

<p>
	<label>
		<input type="checkbox" name="tpw_members_settings[enable_signups]" value="1" <?php checked( $signup_settings['enable_signups'], '1' ); ?> />
		<?php echo esc_html__( 'Enable Sign-Ups', 'tpw-core' ); ?>
	</label>
</p>

<p>
	<label for="tpw_members_signup_page_id"><strong><?php echo esc_html__( 'Sign-Up Page', 'tpw-core' ); ?></strong></label><br>
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
	<small class="description"><?php echo esc_html__( 'Stores the WordPress page that will host the public sign-up form in a later branch.', 'tpw-core' ); ?></small>
</p>

<hr>

<p><strong><?php echo esc_html__( 'Field Configuration', 'tpw-core' ); ?></strong></p>
<p class="description"><?php echo esc_html__( 'Only fields marked as signup-safe can later be exposed on public sign-up forms. Section values are restricted to the fixed Core section registry for Branch 2.', 'tpw-core' ); ?></p>

<style>
.tpw-signups-resp {
	display: none;
	color: #555;
	margin-left: 6px;
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
		<div class="tpw-table-cell"><?php echo esc_html__( 'Signup Safe', 'tpw-core' ); ?></div>
		<div class="tpw-table-cell"><?php echo esc_html__( 'Enabled', 'tpw-core' ); ?></div>
		<div class="tpw-table-cell"><?php echo esc_html__( 'Required', 'tpw-core' ); ?></div>
		<div class="tpw-table-cell"><?php echo esc_html__( 'Section', 'tpw-core' ); ?></div>
		<div class="tpw-table-cell"><?php echo esc_html__( 'Order', 'tpw-core' ); ?></div>
	</div>
	<?php foreach ( $signup_fields as $field ) : ?>
		<div class="tpw-table-row">
			<div class="tpw-table-cell"><code><?php echo esc_html( $field['key'] ); ?></code></div>
			<div class="tpw-table-cell"><?php echo esc_html( $field['label'] ); ?></div>
			<div class="tpw-table-cell">
				<label style="display:inline-flex; align-items:center; gap:6px;">
					<input
						type="checkbox"
						name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_safe]"
						value="1"
						<?php checked( $field['signup_safe'] ); ?>
						<?php disabled( ! $field['signup_safe_editable'] ); ?>
					/>
					<span class="tpw-signups-resp"><?php echo esc_html__( 'Signup Safe', 'tpw-core' ); ?></span>
				</label>
				<?php if ( ! $field['signup_safe_editable'] ) : ?>
					<em style="color:#777; margin-left:6px;"><?php echo esc_html__( 'Locked', 'tpw-core' ); ?></em>
				<?php endif; ?>
			</div>
			<div class="tpw-table-cell">
				<label style="display:inline-flex; align-items:center; gap:6px;">
					<input type="checkbox" name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_enabled]" value="1" <?php checked( $field['signup_enabled'] ); ?> <?php disabled( ! $field['signup_safe_editable'] && ! $field['signup_safe'] ); ?> />
					<span class="tpw-signups-resp"><?php echo esc_html__( 'Enabled', 'tpw-core' ); ?></span>
				</label>
			</div>
			<div class="tpw-table-cell">
				<label style="display:inline-flex; align-items:center; gap:6px;">
					<input type="checkbox" name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_required]" value="1" <?php checked( $field['signup_required'] ); ?> <?php disabled( ! $field['signup_safe_editable'] && ! $field['signup_safe'] ); ?> />
					<span class="tpw-signups-resp"><?php echo esc_html__( 'Required', 'tpw-core' ); ?></span>
				</label>
			</div>
			<div class="tpw-table-cell">
				<select name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_section]">
					<option value=""><?php echo esc_html__( '— Select —', 'tpw-core' ); ?></option>
					<?php foreach ( $signup_sections as $section_key => $section_label ) : ?>
						<option value="<?php echo esc_attr( $section_key ); ?>" <?php selected( $field['signup_section'], $section_key ); ?>><?php echo esc_html( $section_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="tpw-table-cell">
				<input type="number" min="0" step="1" name="signup_fields[<?php echo esc_attr( $field['key'] ); ?>][signup_order]" value="<?php echo esc_attr( $field['signup_order'] ); ?>" style="width:90px;" />
			</div>
		</div>
	<?php endforeach; ?>
</div>