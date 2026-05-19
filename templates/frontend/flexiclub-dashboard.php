<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tpw-flexiclub-dashboard__portal">
	<details class="tpw-flexiclub-dashboard__portal-sidebar-shell">
		<summary class="tpw-flexiclub-dashboard__portal-nav-toggle">
			<span class="dashicons dashicons-menu-alt3" aria-hidden="true"></span>
			<span><?php esc_html_e( 'FlexiClub navigation', 'tpw-core' ); ?></span>
		</summary>

		<aside class="tpw-flexiclub-dashboard__portal-sidebar">
			<section class="tpw-card tpw-flexiclub-dashboard__portal-brand-card">
				<?php if ( ! empty( $dashboard['logo_url'] ) ) : ?>
					<img class="tpw-flexiclub-dashboard__portal-logo" src="<?php echo esc_url( $dashboard['logo_url'] ); ?>" alt="<?php esc_attr_e( 'FlexiClub', 'tpw-core' ); ?>" />
				<?php else : ?>
					<h1><?php esc_html_e( 'FlexiClub', 'tpw-core' ); ?></h1>
				<?php endif; ?>
				<p class="tpw-flexiclub-dashboard__portal-tagline"><?php esc_html_e( 'Club workspace for member operations, setup, and connected tools.', 'tpw-core' ); ?></p>
				<?php if ( ! empty( $dashboard['version'] ) ) : ?>
					<div class="tpw-flexiclub-dashboard__version">
						<span><?php esc_html_e( 'Version', 'tpw-core' ); ?></span>
						<strong><?php echo esc_html( $dashboard['version'] ); ?></strong>
					</div>
				<?php endif; ?>
			</section>

			<section class="tpw-card tpw-flexiclub-dashboard__portal-nav-group">
				<h2><?php esc_html_e( 'Workspace', 'tpw-core' ); ?></h2>
				<nav class="tpw-flexiclub-dashboard__portal-nav-list" aria-label="<?php esc_attr_e( 'FlexiClub workspace navigation', 'tpw-core' ); ?>">
					<?php foreach ( $dashboard['portal_nav_items'] as $item ) : ?>
						<?php $item_classes = 'tpw-flexiclub-dashboard__portal-nav-link'; ?>
						<?php if ( ! empty( $item['current'] ) ) : ?>
							<?php $item_classes .= ' tpw-flexiclub-dashboard__portal-nav-link--current'; ?>
						<?php endif; ?>
						<?php if ( ! empty( $item['disabled'] ) ) : ?>
							<?php $item_classes .= ' tpw-flexiclub-dashboard__portal-nav-link--disabled'; ?>
						<?php endif; ?>
						<?php if ( ! empty( $item['disabled'] ) ) : ?>
							<span class="<?php echo esc_attr( $item_classes ); ?>"><span><?php echo esc_html( $item['label'] ); ?></span></span>
						<?php else : ?>
							<a class="<?php echo esc_attr( $item_classes ); ?>" href="<?php echo esc_url( $item['url'] ); ?>"><span><?php echo esc_html( $item['label'] ); ?></span></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
			</section>

			<section class="tpw-card tpw-flexiclub-dashboard__portal-nav-group">
				<h2><?php esc_html_e( 'On This Page', 'tpw-core' ); ?></h2>
				<nav class="tpw-flexiclub-dashboard__portal-nav-list" aria-label="<?php esc_attr_e( 'FlexiClub dashboard sections', 'tpw-core' ); ?>">
					<?php foreach ( $dashboard['section_nav_items'] as $item ) : ?>
						<a class="tpw-flexiclub-dashboard__portal-nav-link tpw-flexiclub-dashboard__portal-nav-link--section" href="<?php echo esc_url( $item['url'] ); ?>"><span><?php echo esc_html( $item['label'] ); ?></span></a>
					<?php endforeach; ?>
				</nav>
			</section>
		</aside>
	</details>

	<div class="tpw-flexiclub-dashboard__portal-stage">
		<div id="flexiclub-home" class="tpw-flexiclub-dashboard__hero tpw-card">
			<div class="tpw-flexiclub-dashboard__brand-row">
				<div class="tpw-flexiclub-dashboard__brand">
					<?php if ( ! empty( $dashboard['logo_url'] ) ) : ?>
						<img class="tpw-flexiclub-dashboard__logo" src="<?php echo esc_url( $dashboard['logo_url'] ); ?>" alt="<?php esc_attr_e( 'FlexiClub', 'tpw-core' ); ?>" />
					<?php else : ?>
						<h1><?php esc_html_e( 'FlexiClub', 'tpw-core' ); ?></h1>
					<?php endif; ?>
					<div class="tpw-flexiclub-dashboard__brand-copy">
						<p class="tpw-flexiclub-dashboard__tagline"><?php esc_html_e( 'Your front-end workspace for club administration and setup.', 'tpw-core' ); ?></p>
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
					<p><?php esc_html_e( 'Monitor setup progress, jump into the right tool, and keep the club workspace healthy from one front-end hub.', 'tpw-core' ); ?></p>
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
				<?php if ( ! empty( $dashboard['show_checklist'] ) ) : ?>
					<section id="tpw-flexiclub-checklist" class="tpw-flexiclub-dashboard__section tpw-card tpw-flexiclub-dashboard__section--checklist tpw-flexiclub-dashboard__section--checklist-full">
						<div class="tpw-flexiclub-dashboard__section-head">
							<div>
								<h2><?php esc_html_e( 'Getting Started', 'tpw-core' ); ?></h2>
								<p><?php esc_html_e( 'A launch checklist for the FlexiClub front-end workspace and connected pages.', 'tpw-core' ); ?></p>
							</div>
						</div>

						<div class="tpw-flexiclub-dashboard__checklist">
							<div class="tpw-flexiclub-dashboard__progress" style="--tpw-progress: <?php echo esc_attr( $dashboard['checklist_progress'] ); ?>%;">
								<div class="tpw-flexiclub-dashboard__progress-ring">
									<strong><?php echo esc_html( $dashboard['checklist_done'] ); ?>/<?php echo esc_html( $dashboard['checklist_total'] ); ?></strong>
								</div>
								<div class="tpw-flexiclub-dashboard__progress-copy">
									<h3><?php esc_html_e( 'Setup Progress', 'tpw-core' ); ?></h3>
									<p><?php esc_html_e( 'Complete the remaining setup tasks before handing this portal to club administrators.', 'tpw-core' ); ?></p>
								</div>
							</div>

							<div class="tpw-flexiclub-dashboard__checklist-panel">
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

								<div class="tpw-flexiclub-dashboard__checklist-actions">
									<a class="tpw-btn tpw-btn-outline" href="<?php echo esc_url( $dashboard['checklist_primary_action']['url'] ); ?>"><?php echo esc_html( $dashboard['checklist_primary_action']['label'] ); ?></a>
								</div>
							</div>
						</div>
					</section>
				<?php endif; ?>

				<section id="flexiclub-tools" class="tpw-flexiclub-dashboard__section tpw-card">
					<div class="tpw-flexiclub-dashboard__section-head">
						<div>
							<h2><?php esc_html_e( 'Club Overview', 'tpw-core' ); ?></h2>
							<p><?php esc_html_e( 'Open the current club tools without absorbing or replacing their existing plugin-owned screens.', 'tpw-core' ); ?></p>
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
								<?php if ( ! array_key_exists( 'show_action', $card ) || ! empty( $card['show_action'] ) ) : ?>
									<div class="tpw-flexiclub-dashboard__overview-action">
										<?php if ( ! empty( $card['disabled'] ) ) : ?>
											<button class="tpw-btn tpw-btn-outline" type="button" disabled><?php echo esc_html( $card['action_label'] ); ?></button>
										<?php else : ?>
											<a class="tpw-btn tpw-btn-outline" href="<?php echo esc_url( $card['action_url'] ); ?>"><?php echo esc_html( $card['action_label'] ); ?></a>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</section>

				<section id="tpw-flexiclub-extend" class="tpw-flexiclub-dashboard__section tpw-card">
					<div class="tpw-flexiclub-dashboard__section-head">
						<div>
							<h2><?php esc_html_e( 'Extend FlexiClub', 'tpw-core' ); ?></h2>
							<p><?php esc_html_e( 'Add-on products stay plugin-owned. The FlexiClub hub links out to them and reports current availability.', 'tpw-core' ); ?></p>
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
			</div>

			<aside id="flexiclub-support" class="tpw-flexiclub-dashboard__aside">
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
				</section>

				<section class="tpw-flexiclub-dashboard__section tpw-card">
					<div class="tpw-flexiclub-dashboard__section-head">
						<div>
							<h2><?php esc_html_e( 'System Status', 'tpw-core' ); ?></h2>
						</div>
					</div>

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
				</section>
			</aside>
		</div>
	</div>
</div>