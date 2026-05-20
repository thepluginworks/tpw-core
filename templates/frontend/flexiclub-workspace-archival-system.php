<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$workspace = isset( $dashboard['archival_system_workspace'] ) && is_array( $dashboard['archival_system_workspace'] ) ? $dashboard['archival_system_workspace'] : [];
$dashboard_url = isset( $workspace['dashboard_url'] ) ? (string) $workspace['dashboard_url'] : '';
$dedicated_url = isset( $workspace['dedicated_url'] ) ? (string) $workspace['dedicated_url'] : '';
$legacy_url = isset( $workspace['legacy_url'] ) ? (string) $workspace['legacy_url'] : '';
?>
<section id="flexiclub-archival-system-overview" class="tpw-flexiclub-dashboard__hero tpw-card tpw-flexiclub-control-workspace__hero">
	<div class="tpw-flexiclub-dashboard__brand-row tpw-flexiclub-control-workspace__hero-head">
		<div class="tpw-flexiclub-dashboard__welcome tpw-flexiclub-control-workspace__hero-copy">
			<h2><?php echo esc_html( $workspace['hero_title'] ?? __( 'Archival System Workspace', 'tpw-core' ) ); ?></h2>
			<p><?php echo esc_html( $workspace['hero_copy'] ?? __( 'Manage the current front-end archive tools from the FlexiClub portal.', 'tpw-core' ) ); ?></p>
		</div>
		<div class="tpw-flexiclub-control-workspace__hero-actions">
			<?php if ( '' !== $dashboard_url ) : ?>
				<a class="tpw-btn tpw-btn-outline" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Back to dashboard', 'tpw-core' ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $dedicated_url ) : ?>
				<a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( $dedicated_url ); ?>"><?php echo esc_html( $workspace['dedicated_label'] ?? __( 'Open dedicated Archival System page', 'tpw-core' ) ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="tpw-flexiclub-control-workspace__summary-grid">
		<div class="tpw-flexiclub-control-workspace__summary-card">
			<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--<?php echo esc_attr( $workspace['status_tone'] ?? 'neutral' ); ?>"><?php echo esc_html( $workspace['status_label'] ?? __( 'Ready', 'tpw-core' ) ); ?></span>
			<strong><?php echo esc_html( $workspace['metric_value'] ?? __( 'Unavailable', 'tpw-core' ) ); ?></strong>
			<p><?php echo esc_html( $workspace['metric_text'] ?? '' ); ?></p>
		</div>
	</div>
</section>

<section id="flexiclub-archival-system-legacy" class="tpw-flexiclub-dashboard__section tpw-card tpw-flexiclub-control-workspace__notice-card">
	<div class="tpw-flexiclub-control-workspace__notice tpw-flexiclub-control-workspace__notice--legacy">
		<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--warning"><?php echo esc_html( $workspace['legacy_notice_label'] ?? __( 'Legacy Workspace', 'tpw-core' ) ); ?></span>
		<p><?php echo esc_html( $workspace['legacy_notice_text'] ?? __( 'FlexiClub Control remains available during the transition.', 'tpw-core' ) ); ?></p>
		<?php if ( '' !== $legacy_url ) : ?>
			<a class="tpw-btn tpw-btn-light" href="<?php echo esc_url( $legacy_url ); ?>"><?php esc_html_e( 'Open legacy FlexiClub Control', 'tpw-core' ); ?></a>
		<?php endif; ?>
	</div>
</section>

<section id="flexiclub-archival-system-tool" class="tpw-flexiclub-dashboard__section tpw-card tpw-flexiclub-control-workspace__section">
	<div class="tpw-flexiclub-dashboard__section-head tpw-flexiclub-control-workspace__section-head">
		<div>
			<h2><?php echo esc_html( $workspace['tool_heading'] ?? __( 'Current Archival System tool', 'tpw-core' ) ); ?></h2>
			<p><?php echo esc_html( $workspace['tool_copy'] ?? __( 'This workspace embeds the existing front-end archive section inside the FlexiClub portal shell.', 'tpw-core' ) ); ?></p>
		</div>
	</div>

	<div class="tpw-flexiclub-control-workspace__embed">
		<?php TPW_FlexiClub_Admin_Menu::render_frontend_tpw_control_section( $workspace['section_key'] ?? 'upload-pages' ); ?>
	</div>
</section>
