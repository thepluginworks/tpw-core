<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$workspace        = isset( $dashboard['settings_workspace'] ) && is_array( $dashboard['settings_workspace'] ) ? $dashboard['settings_workspace'] : [];
$tabs             = isset( $workspace['tabs'] ) && is_array( $workspace['tabs'] ) ? $workspace['tabs'] : [];
$active_tab       = isset( $workspace['active_tab'] ) ? (string) $workspace['active_tab'] : 'member-menu';
$active_label     = isset( $workspace['active_label'] ) ? (string) $workspace['active_label'] : __( 'Settings Area', 'tpw-core' );
$dashboard_url    = isset( $workspace['dashboard_url'] ) ? (string) $workspace['dashboard_url'] : '';
$system_pages_url = isset( $workspace['system_pages_url'] ) ? (string) $workspace['system_pages_url'] : '';
$workspace_url    = isset( $workspace['workspace_url'] ) ? (string) $workspace['workspace_url'] : '';
$current_url      = isset( $workspace['current_url'] ) ? (string) $workspace['current_url'] : $workspace_url;
$active_tab_slug  = '' !== $active_tab ? sanitize_html_class( $active_tab ) : 'member-menu';
$panel_classes    = 'tpw-flexiclub-settings__panel tpw-admin-ui tpw-flexiclub-settings__panel--' . $active_tab_slug;
$tab_shell_class  = 'tpw-flexiclub-settings__tab-shell tpw-admin-ui wp-core-ui tpw-flexiclub-settings__tab-shell--' . $active_tab_slug;

if ( function_exists( 'tpw_core_set_settings_view_context' ) ) {
	tpw_core_set_settings_view_context(
		[
			'mode'          => 'frontend',
			'base_url'      => $workspace_url,
			'tab_query_arg' => 'settings-tab',
			'return_url'    => $current_url,
		]
	);
}
?>
<section id="flexiclub-settings-overview" class="tpw-flexiclub-dashboard__hero tpw-card tpw-flexiclub-settings__hero">
	<div class="tpw-flexiclub-dashboard__brand-row tpw-flexiclub-settings__hero-head">
		<div class="tpw-flexiclub-dashboard__welcome tpw-flexiclub-settings__hero-copy">
			<h2><?php esc_html_e( 'Settings Workspace', 'tpw-core' ); ?></h2>
			<p><?php esc_html_e( 'Manage the existing FlexiClub settings from the portal using the same shared forms, validation, and save flows that already power the wp-admin settings screen.', 'tpw-core' ); ?></p>
		</div>
		<div class="tpw-flexiclub-settings__hero-actions">
			<?php if ( '' !== $dashboard_url ) : ?>
				<a class="tpw-btn tpw-btn-outline" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Back to dashboard', 'tpw-core' ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $system_pages_url ) : ?>
				<a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( $system_pages_url ); ?>"><?php esc_html_e( 'Open System Pages workspace', 'tpw-core' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>

<section id="flexiclub-settings-tabs" class="tpw-flexiclub-dashboard__section tpw-card tpw-flexiclub-settings__section">
	<div class="tpw-flexiclub-dashboard__section-head tpw-flexiclub-settings__section-head">
		<div>
			<h2><?php esc_html_e( 'Settings Areas', 'tpw-core' ); ?></h2>
			<p><?php esc_html_e( 'Switch between the existing FlexiClub settings areas without leaving the front-end workspace.', 'tpw-core' ); ?></p>
		</div>
	</div>

	<nav class="tpw-flexiclub-settings__tabs" aria-label="<?php esc_attr_e( 'FlexiClub settings areas', 'tpw-core' ); ?>">
		<?php foreach ( $tabs as $item ) : ?>
			<?php
			$item_classes = 'tpw-flexiclub-settings__tab-link';
			if ( ! empty( $item['current'] ) ) {
				$item_classes .= ' tpw-flexiclub-settings__tab-link--current';
			}
			if ( ! empty( $item['disabled'] ) ) {
				$item_classes .= ' tpw-flexiclub-settings__tab-link--disabled';
			}
			if ( ! empty( $item['external'] ) ) {
				$item_classes .= ' tpw-flexiclub-settings__tab-link--external';
			}
			?>
			<?php if ( ! empty( $item['disabled'] ) ) : ?>
				<span class="<?php echo esc_attr( $item_classes ); ?>"><?php echo esc_html( $item['label'] ); ?></span>
			<?php else : ?>
				<a class="<?php echo esc_attr( $item_classes ); ?>" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo ! empty( $item['current'] ) ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>

	<div id="flexiclub-settings-panel" class="<?php echo esc_attr( $panel_classes ); ?>" style="<?php echo esc_attr( function_exists( 'tpw_core_build_ui_theme_style_attr' ) ? tpw_core_build_ui_theme_style_attr() : '' ); ?>">
		<div class="tpw-flexiclub-settings__panel-head">
			<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--neutral"><?php esc_html_e( 'Portal Settings', 'tpw-core' ); ?></span>
			<h3><?php echo esc_html( $active_label ); ?></h3>
		</div>

		<?php if ( function_exists( 'tpw_core_render_settings_request_notices' ) ) : ?>
			<?php tpw_core_render_settings_request_notices( $active_tab ); ?>
		<?php endif; ?>

		<div class="tpw-flexiclub-settings__panel-body">
			<div class="<?php echo esc_attr( $tab_shell_class ); ?>" data-settings-tab="<?php echo esc_attr( $active_tab_slug ); ?>">
				<?php if ( function_exists( 'tpw_core_render_settings_tab_content' ) ) : ?>
					<?php tpw_core_render_settings_tab_content( $active_tab ); ?>
				<?php else : ?>
					<p><?php esc_html_e( 'The shared FlexiClub settings renderer is unavailable on this request.', 'tpw-core' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<?php if ( function_exists( 'tpw_core_reset_settings_view_context' ) ) : ?>
	<?php tpw_core_reset_settings_view_context(); ?>
<?php endif; ?>