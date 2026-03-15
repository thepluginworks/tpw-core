<?php
/**
 * Schema-driven Join form renderer.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Form_Renderer {
	/**
	 * Render the disabled state.
	 *
	 * @return string
	 */
	public function render_disabled() {
		ob_start();
		?>
		<div class="tpw-signup-form tpw-signup-form--disabled">
			<div class="tpw-card tpw-settings-card">
				<p><?php echo esc_html__( 'Join requests are not available at the moment.', 'tpw-core' ); ?></p>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the success state.
	 *
	 * @param array $attempt Signup attempt.
	 * @return string
	 */
	public function render_success( $attempt ) {
		$reference = '';

		if ( ! empty( $attempt['public_token'] ) ) {
			$reference = strtoupper( substr( sanitize_text_field( $attempt['public_token'] ), 0, 8 ) );
		}

		ob_start();
		?>
		<div class="tpw-signup-form tpw-signup-form--success">
			<div class="tpw-card tpw-settings-card">
				<h2><?php echo esc_html__( 'Thank you for joining us', 'tpw-core' ); ?></h2>
				<p><?php echo esc_html__( 'We\'ve received your details successfully.', 'tpw-core' ); ?></p>
				<p><?php echo esc_html__( 'We\'ll review your request and be in touch if anything further is needed. If you contact us about this request, please quote the reference below.', 'tpw-core' ); ?></p>
				<?php if ( '' !== $reference ) : ?>
					<p><strong><?php echo esc_html__( 'Reference', 'tpw-core' ); ?>:</strong> <?php echo esc_html( $reference ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the form.
	 *
	 * @param array $schema Public signup schema.
	 * @param array $state Form state.
	 * @return string
	 */
	public function render_form( $schema, $state = array() ) {
		$state = wp_parse_args(
			$state,
			array(
				'values'     => array(),
				'errors'     => array(),
				'form_error' => '',
			)
		);

		$nodes = isset( $schema['nodes'] ) && is_array( $schema['nodes'] ) ? $schema['nodes'] : array();

		ob_start();
		?>
		<div class="tpw-signup-form">
			<div class="tpw-card tpw-settings-card">
				<h2><?php echo esc_html( isset( $schema['title'] ) ? $schema['title'] : __( 'Join', 'tpw-core' ) ); ?></h2>
				<?php if ( ! empty( $state['form_error'] ) ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( $state['form_error'] ); ?></p></div>
				<?php endif; ?>
				<?php if ( ! empty( $state['errors'] ) ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html__( 'Please correct the highlighted fields and try again.', 'tpw-core' ); ?></p></div>
				<?php endif; ?>
				<form method="post" class="tpw-signup-form__form" novalidate>
					<?php wp_nonce_field( 'tpw_join_submit', 'tpw_join_nonce' ); ?>
					<input type="hidden" name="tpw_signup_action" value="join_submit" />
					<div class="tpw-signup-form__sections">
						<?php $this->render_nodes( $nodes, $state ); ?>
					</div>
					<p class="tpw-signup-form__actions">
						<button type="submit" class="button button-primary"><?php echo esc_html__( 'Submit Join Request', 'tpw-core' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Recursively render schema nodes.
	 *
	 * @param array $nodes Schema nodes.
	 * @param array $state Form state.
	 * @return void
	 */
	private function render_nodes( $nodes, $state ) {
		foreach ( $nodes as $node ) {
			$node_type = isset( $node['node_type'] ) ? (string) $node['node_type'] : 'field';

			switch ( $node_type ) {
				case 'section':
					$this->render_section( $node, $state );
					break;

				case 'group':
					$this->render_group( $node, $state );
					break;

				case 'repeater':
					$this->render_repeater( $node, $state );
					break;

				case 'field':
				default:
					$this->render_field( $node, $state );
			}
		}
	}

	/**
	 * Render a section node.
	 *
	 * @param array $node Section node.
	 * @param array $state Form state.
	 * @return void
	 */
	private function render_section( $node, $state ) {
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
		?>
		<section class="tpw-signup-form__section">
			<h3><?php echo esc_html( isset( $node['label'] ) ? $node['label'] : '' ); ?></h3>
			<div class="tpw-signup-form__section-fields">
				<?php $this->render_nodes( $children, $state ); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render a group node.
	 *
	 * @param array $node Group node.
	 * @param array $state Form state.
	 * @return void
	 */
	private function render_group( $node, $state ) {
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
		?>
		<fieldset class="tpw-signup-form__group">
			<?php if ( ! empty( $node['label'] ) ) : ?>
				<legend><?php echo esc_html( $node['label'] ); ?></legend>
			<?php endif; ?>
			<?php $this->render_nodes( $children, $state ); ?>
		</fieldset>
		<?php
	}

	/**
	 * Render a repeater node.
	 *
	 * @param array $node Repeater node.
	 * @param array $state Form state.
	 * @return void
	 */
	private function render_repeater( $node, $state ) {
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
		?>
		<div class="tpw-signup-form__repeater">
			<?php if ( ! empty( $node['label'] ) ) : ?>
				<h4><?php echo esc_html( $node['label'] ); ?></h4>
			<?php endif; ?>
			<?php $this->render_nodes( $children, $state ); ?>
		</div>
		<?php
	}

	/**
	 * Render one field node.
	 *
	 * @param array $field Field node.
	 * @param array $state Form state.
	 * @return void
	 */
	private function render_field( $field, $state ) {
		$key      = isset( $field['key'] ) ? sanitize_key( $field['key'] ) : '';
		$label    = isset( $field['label'] ) ? (string) $field['label'] : '';
		$type     = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$value    = isset( $state['values'][ $key ] ) ? $state['values'][ $key ] : '';
		$error    = isset( $state['errors'][ $key ] ) ? $state['errors'][ $key ] : '';
		$options  = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$required = ! empty( $field['signup_required'] );

		if ( '' === $key ) {
			return;
		}

		$field_id = 'tpw-signup-' . $key;
		$class    = 'tpw-signup-form__field';
		$invalid  = '' !== $error ? 'true' : 'false';
		$required_attr = $required ? ' required' : '';
		if ( '' !== $error ) {
			$class .= ' tpw-signup-form__field--error';
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<?php if ( 'checkbox' !== $type ) : ?>
				<label for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $label ); ?>
					<?php if ( $required ) : ?>
						<span aria-hidden="true">*</span>
					<?php endif; ?>
				</label>
			<?php endif; ?>

			<?php if ( 'textarea' === $type ) : ?>
				<textarea id="<?php echo esc_attr( $field_id ); ?>" name="tpw_signup[<?php echo esc_attr( $key ); ?>]" rows="4" aria-invalid="<?php echo esc_attr( $invalid ); ?>"<?php echo esc_attr( $required_attr ); ?>><?php echo esc_textarea( $value ); ?></textarea>
			<?php elseif ( 'select' === $type ) : ?>
				<select id="<?php echo esc_attr( $field_id ); ?>" name="tpw_signup[<?php echo esc_attr( $key ); ?>]" aria-invalid="<?php echo esc_attr( $invalid ); ?>"<?php echo esc_attr( $required_attr ); ?>>
					<option value=""><?php echo esc_html__( 'Select…', 'tpw-core' ); ?></option>
					<?php foreach ( $options as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'checkbox' === $type ) : ?>
				<label for="<?php echo esc_attr( $field_id ); ?>" class="tpw-signup-form__checkbox-label">
					<input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>" name="tpw_signup[<?php echo esc_attr( $key ); ?>]" value="1" aria-invalid="<?php echo esc_attr( $invalid ); ?>" <?php checked( '1', $value ); ?> />
					<span><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></span>
				</label>
			<?php else : ?>
				<input type="<?php echo esc_attr( $this->normalize_input_type( $type ) ); ?>" id="<?php echo esc_attr( $field_id ); ?>" name="tpw_signup[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" aria-invalid="<?php echo esc_attr( $invalid ); ?>"<?php echo esc_attr( $required_attr ); ?> />
			<?php endif; ?>

			<?php if ( '' !== $error ) : ?>
				<p class="tpw-signup-form__error"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Normalize supported HTML input types.
	 *
	 * @param string $type Field type.
	 * @return string
	 */
	private function normalize_input_type( $type ) {
		$allowed = array( 'text', 'email', 'tel', 'date', 'datetime-local', 'number' );

		return in_array( $type, $allowed, true ) ? $type : 'text';
	}
}