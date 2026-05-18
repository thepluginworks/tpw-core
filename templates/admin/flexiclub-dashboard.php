<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tpw-flexiclub-dashboard__hero tpw-card">
	<div class="tpw-flexiclub-dashboard__brand-row">
		<div class="tpw-flexiclub-dashboard__brand">
			<?php if ( ! empty( $dashboard['logo_url'] ) ) : ?>
				<img class="tpw-flexiclub-dashboard__logo" src="<?php echo esc_url( $dashboard['logo_url'] ); ?>" alt="<?php esc_attr_e( 'FlexiClub', 'tpw-core' ); ?>" />
			<?php else : ?>
				<h1><?php esc_html_e( 'FlexiClub', 'tpw-core' ); ?></h1>
			<?php endif; ?>
			<div class="tpw-flexiclub-dashboard__brand-copy">
				<p class="tpw-flexiclub-dashboard__tagline"><?php esc_html_e( 'Your club. Your members. Your community.', 'tpw-core' ); ?></p>
			</div>
		</div>
		<?php if ( ! empty( $dashboard['version'] ) ) : ?>
			<div class="tpw-flexiclub-dashboard__version">
				<span><?php esc_html_e( 'Version', 'tpw-core' ); ?></span>
				<strong><?php echo esc_html( $dashboard['version'] ); ?></strong>
			</div>
		<?php endif; ?>
	</div>

	<div class="tpw-flexiclub-dashboard__hero-content">
		<div class="tpw-flexiclub-dashboard__welcome">
			<h2>
				<?php
				printf(
					/* translators: %s: current user display name */
					esc_html__( 'Welcome back, %s', 'tpw-core' ),
					esc_html( $dashboard['welcome_name'] )
				);
				?>
			</h2>
			<p><?php esc_html_e( 'Here’s what’s happening across your club operations today.', 'tpw-core' ); ?></p>
		</div>

		<div class="tpw-flexiclub-dashboard__summary-grid">
			<?php foreach ( $dashboard['summary_cards'] as $card ) : ?>
				<?php if ( ! empty( $card['action_url'] ) ) : ?>
					<a class="tpw-flexiclub-dashboard__summary-card tpw-flexiclub-dashboard__summary-card--link" href="<?php echo esc_url( $card['action_url'] ); ?>" aria-label="<?php echo esc_attr( $card['action_label'] ?? $card['title'] ); ?>">
				<?php else : ?>
					<div class="tpw-flexiclub-dashboard__summary-card">
				<?php endif; ?>
					<div class="tpw-flexiclub-dashboard__summary-copy">
						<span class="tpw-flexiclub-dashboard__summary-label"><?php echo esc_html( $card['title'] ); ?></span>
						<div class="tpw-flexiclub-dashboard__metric"><?php echo esc_html( $card['value'] ); ?></div>
					</div>
				<?php if ( ! empty( $card['action_url'] ) ) : ?>
					</a>
				<?php else : ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<div class="tpw-flexiclub-dashboard__layout">
	<div class="tpw-flexiclub-dashboard__main">
		<section class="tpw-flexiclub-dashboard__section tpw-card">
			<div class="tpw-flexiclub-dashboard__section-head">
				<div>
					<h2><?php esc_html_e( 'Club Overview', 'tpw-core' ); ?></h2>
					<p><?php esc_html_e( 'A snapshot of your key management areas.', 'tpw-core' ); ?></p>
				</div>
			</div>

			<div class="tpw-flexiclub-dashboard__overview-grid">
				<?php foreach ( $dashboard['overview_cards'] as $card ) : ?>
					<div class="tpw-flexiclub-dashboard__overview-card tpw-flexiclub-dashboard__overview-card--<?php echo esc_attr( $card['tone'] ?? 'default' ); ?>">
						<div class="tpw-flexiclub-dashboard__overview-icon tpw-flexiclub-dashboard__overview-icon--<?php echo esc_attr( $card['tone'] ?? 'default' ); ?>">
							<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
						</div>
						<div class="tpw-flexiclub-dashboard__overview-body">
							<div class="tpw-flexiclub-dashboard__overview-head">
								<h3><?php echo esc_html( $card['title'] ); ?></h3>
								<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--<?php echo esc_attr( $card['status_tone'] ); ?>">
									<?php echo esc_html( $card['status_label'] ); ?>
								</span>
							</div>
							<div class="tpw-flexiclub-dashboard__overview-metric"><?php echo esc_html( $card['metric'] ); ?></div>
							<p><?php echo esc_html( $card['description'] ); ?></p>
						</div>
						<div class="tpw-flexiclub-dashboard__overview-action">
							<?php if ( ! empty( $card['disabled'] ) ) : ?>
								<button class="tpw-btn tpw-btn-outline" type="button" disabled><?php echo esc_html( $card['action_label'] ); ?></button>
							<?php else : ?>
								<a class="tpw-btn tpw-btn-outline" href="<?php echo esc_url( $card['action_url'] ); ?>"><?php echo esc_html( $card['action_label'] ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<div class="tpw-flexiclub-dashboard__bottom-grid">
			<section class="tpw-flexiclub-dashboard__section tpw-card tpw-flexiclub-dashboard__section--checklist">
				<div class="tpw-flexiclub-dashboard__section-head">
					<div>
						<h2><?php esc_html_e( 'Getting Started', 'tpw-core' ); ?></h2>
						<p><?php esc_html_e( 'A simple checklist for launching your club workspace.', 'tpw-core' ); ?></p>
					</div>
				</div>

				<div class="tpw-flexiclub-dashboard__checklist">
					<?php
					$progress = 0;
					if ( ! empty( $dashboard['checklist_total'] ) ) {
						$progress = ( (int) $dashboard['checklist_done'] / (int) $dashboard['checklist_total'] ) * 100;
					}
					?>
					<div class="tpw-flexiclub-dashboard__progress" style="--tpw-progress: <?php echo esc_attr( $progress ); ?>%;">
						<div class="tpw-flexiclub-dashboard__progress-ring">
							<strong><?php echo esc_html( $dashboard['checklist_done'] ); ?>/<?php echo esc_html( $dashboard['checklist_total'] ); ?></strong>
						</div>
						<div class="tpw-flexiclub-dashboard__progress-copy">
							<h3><?php esc_html_e( 'Setup Progress', 'tpw-core' ); ?></h3>
							<p><?php esc_html_e( 'Almost there.', 'tpw-core' ); ?></p>
						</div>
					</div>

					<div class="tpw-flexiclub-dashboard__checklist-items">
						<?php foreach ( $dashboard['checklist_items'] as $item ) : ?>
							<div class="tpw-flexiclub-dashboard__checklist-item">
								<span class="tpw-flexiclub-dashboard__checkmark tpw-flexiclub-dashboard__checkmark--<?php echo ! empty( $item['done'] ) ? 'done' : 'pending'; ?>" aria-hidden="true">
									<?php echo ! empty( $item['done'] ) ? '✓' : '○'; ?>
								</span>
								<div>
									<div class="tpw-flexiclub-dashboard__checklist-title">
										<?php echo esc_html( $item['label'] ); ?>
										<?php if ( ! empty( $item['optional'] ) ) : ?>
											<span class="tpw-flexiclub-dashboard__optional"><?php esc_html_e( 'Optional', 'tpw-core' ); ?></span>
										<?php endif; ?>
									</div>
									<p><?php echo esc_html( $item['description'] ); ?></p>
								</div>
								<a href="<?php echo esc_url( $item['url'] ); ?>"><?php esc_html_e( 'Open', 'tpw-core' ); ?></a>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>

			<section class="tpw-flexiclub-dashboard__section tpw-card tpw-flexiclub-dashboard__section--activity">
				<div class="tpw-flexiclub-dashboard__section-head">
					<div>
						<h2><?php esc_html_e( 'Recent Activity', 'tpw-core' ); ?></h2>
						<p><?php esc_html_e( 'Latest operational updates across members, notices, and logs.', 'tpw-core' ); ?></p>
					</div>
				</div>

				<div class="tpw-flexiclub-dashboard__activity-list">
					<?php foreach ( $dashboard['activity_items'] as $item ) : ?>
						<div class="tpw-flexiclub-dashboard__activity-item">
							<div class="tpw-flexiclub-dashboard__activity-copy">
								<h3><?php echo esc_html( $item['title'] ); ?></h3>
								<p><?php echo esc_html( $item['meta'] ); ?></p>
							</div>
							<span class="tpw-flexiclub-dashboard__activity-time"><?php echo esc_html( $item['time'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="tpw-flexiclub-dashboard__system-status">
					<h3><?php esc_html_e( 'System Status', 'tpw-core' ); ?></h3>
					<div class="tpw-flexiclub-dashboard__system-grid">
						<?php foreach ( $dashboard['system_items'] as $item ) : ?>
							<div class="tpw-flexiclub-dashboard__system-item">
								<span class="tpw-flexiclub-dashboard__system-label"><?php echo esc_html( $item['label'] ); ?></span>
								<strong class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--<?php echo esc_attr( $item['tone'] ); ?>">
									<?php echo esc_html( $item['value'] ); ?>
								</strong>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		</div>
	</div>

	<aside class="tpw-flexiclub-dashboard__aside">
		<section class="tpw-flexiclub-dashboard__section tpw-card">
			<div class="tpw-flexiclub-dashboard__section-head">
				<div>
					<h2><?php esc_html_e( 'Quick Actions', 'tpw-core' ); ?></h2>
				</div>
			</div>

			<div class="tpw-flexiclub-dashboard__action-list">
				<?php foreach ( $dashboard['quick_actions'] as $action ) : ?>
					<?php if ( ! empty( $action['disabled'] ) ) : ?>
						<div class="tpw-flexiclub-dashboard__action-link tpw-flexiclub-dashboard__action-link--disabled">
							<span><?php echo esc_html( $action['label'] ); ?></span>
							<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						</div>
					<?php else : ?>
						<a class="tpw-flexiclub-dashboard__action-link" href="<?php echo esc_url( $action['url'] ); ?>">
							<span><?php echo esc_html( $action['label'] ); ?></span>
							<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>

		<section id="tpw-flexiclub-extend" class="tpw-flexiclub-dashboard__section tpw-card">
			<div class="tpw-flexiclub-dashboard__section-head">
				<div>
					<h2><?php esc_html_e( 'Extend FlexiClub', 'tpw-core' ); ?></h2>
					<p><?php esc_html_e( 'Add powerful add-ons to grow your club.', 'tpw-core' ); ?></p>
				</div>
			</div>

			<div class="tpw-flexiclub-dashboard__extend-grid">
				<?php foreach ( $dashboard['extend_cards'] as $card ) : ?>
					<div class="tpw-flexiclub-dashboard__extend-card">
						<?php if ( ! empty( $card['icon_url'] ) ) : ?>
							<img src="<?php echo esc_url( $card['icon_url'] ); ?>" alt="" />
						<?php else : ?>
							<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
						<?php endif; ?>
						<div class="tpw-flexiclub-dashboard__extend-head">
							<h3><?php echo esc_html( $card['name'] ); ?></h3>
							<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--<?php echo esc_attr( $card['status_tone'] ); ?>">
								<?php echo esc_html( $card['status_label'] ); ?>
							</span>
						</div>
						<p><?php echo esc_html( $card['description'] ); ?></p>
						<?php if ( ! empty( $card['action_url'] ) ) : ?>
							<div class="tpw-flexiclub-dashboard__extend-action">
								<a class="tpw-btn tpw-btn-outline" href="<?php echo esc_url( $card['action_url'] ); ?>"><?php echo esc_html( $card['action_label'] ); ?></a>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	</aside>
</div>
