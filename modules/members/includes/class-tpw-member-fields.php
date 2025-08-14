<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class TPW_Member_Fields {

	public function __construct() {
		add_shortcode( 'tpw_field_settings', [ $this, 'render_settings_page' ] );
		add_action( 'init', [ $this, 'handle_form_submission' ] );
	}

	/**
	 * Load and render the field settings admin page
	 */
	public function render_settings_page() {
		$core_fields  = $this->get_core_fields();
		$field_config = $this->get_field_settings();
		$custom_fields = $this->get_custom_fields();

		ob_start();
		$this->render_settings_template( $core_fields, $field_config, $custom_fields );
		return ob_get_clean();
	}

	/**
	 * Render the field settings template
	 */
	public function render_settings_template( $core_fields, $field_config, $custom_fields ) {
		include TPW_CORE_PATH . 'modules/members/templates/fields-settings.php';
	}

	/**
	 * Fetch all core fields from the database or define statically
	 */
	private function get_core_fields() {
		// TODO: You could define these manually or introspect the members table schema
		return [
			'first_name' => 'First Name',
			'last_name'  => 'Last Name',
			'email'      => 'Email',
			// Add more fields as needed
		];
	}

	/**
	 * Load field config from tpw_field_settings table
	 */
	private function get_field_settings() {
		global $wpdb;
		$table = $wpdb->prefix . 'tpw_field_settings';
		$results = $wpdb->get_results( "SELECT * FROM $table", OBJECT_K );
		return $results;
	}

	/**
	 * Load any custom fields from tpw_member_meta and join with field_settings for labels,
	 * also add an in_use flag by counting entries for each meta_key in tpw_member_meta.
	 */
	private function get_custom_fields() {
		global $wpdb;
		$meta_table = $wpdb->prefix . 'tpw_member_meta';
		$settings_table = $wpdb->prefix . 'tpw_field_settings';

		$results = $wpdb->get_results("
			SELECT DISTINCT m.meta_key, s.label,
				(SELECT COUNT(*) FROM $meta_table WHERE meta_key = m.meta_key) as usage_count
			FROM $meta_table m
			LEFT JOIN $settings_table s ON m.meta_key = s.field_key
		");

		$custom_fields = [];
		foreach ( $results as $row ) {
			$custom_fields[] = [
				'key'      => $row->meta_key,
				'label'    => $row->label ?: $row->meta_key,
				'in_use'   => intval($row->usage_count) > 0
			];
		}
		return $custom_fields;
	}

	/**
	 * Handle POST requests to save settings
	 */
	public function handle_form_submission() {
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || empty( $_POST['tpw_field_settings_nonce'] ) ) return;
		if ( ! wp_verify_nonce( $_POST['tpw_field_settings_nonce'], 'tpw_save_field_settings' ) ) return;

		global $wpdb;
		$table = $wpdb->prefix . 'tpw_field_settings';

		// Save core field settings
		if ( isset($_POST['fields']) && is_array($_POST['fields']) ) {
			foreach ( $_POST['fields'] as $key => $field ) {
				$key = sanitize_key($key);
				$enabled = isset($field['enabled']) ? 1 : 0;
				$label = sanitize_text_field($field['label']);
				$sort_order = intval($field['sort_order']);

				$wpdb->replace(
					$table,
					[
						'field_key'   => $key,
						'enabled'     => $enabled,
						'label'       => $label,
						'sort_order'  => $sort_order,
					],
					[
						'%s', '%d', '%s', '%d'
					]
				);
			}
		}

		// Save new custom field label, if provided
		if ( ! empty($_POST['new_meta_key']) && ! empty($_POST['new_meta_label']) ) {
			$meta_key = sanitize_key($_POST['new_meta_key']);
			$label = sanitize_text_field($_POST['new_meta_label']);

			$wpdb->insert(
				$table,
				[
					'field_key'   => $meta_key,
					'enabled'     => 1,
					'label'       => $label,
					'sort_order'  => 999,
				],
				[
					'%s', '%d', '%s', '%d'
				]
			);
		}

		// Edit or delete custom fields
		if ( isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ) {
			foreach ($_POST['custom_fields'] as $meta_key => $field) {
				$meta_key = sanitize_key($meta_key);

				$in_use = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}tpw_member_meta WHERE meta_key = %s", $meta_key
				) );

				if ( isset($field['delete']) && $field['delete'] === '1' ) {
					if ( $in_use > 0 ) {
						continue; // prevent deletion if in use
					}

					$wpdb->delete(
						$table,
						[ 'field_key' => $meta_key ],
						[ '%s' ]
					);
				} else {
					$new_label = sanitize_text_field($field['label']);
					$wpdb->update(
						$table,
						[ 'label' => $new_label ],
						[ 'field_key' => $meta_key ],
						[ '%s' ],
						[ '%s' ]
					);
				}
			}
		}

		// Redirect to avoid resubmission
		wp_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
		exit;
	}
}