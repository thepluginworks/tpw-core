<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$workspace      = isset( $dashboard['system_pages_workspace'] ) && is_array( $dashboard['system_pages_workspace'] ) ? $dashboard['system_pages_workspace'] : [];
$rows           = isset( $workspace['rows'] ) && is_array( $workspace['rows'] ) ? $workspace['rows'] : [];
$summary_cards  = isset( $workspace['summary_cards'] ) && is_array( $workspace['summary_cards'] ) ? $workspace['summary_cards'] : [];
$dashboard_url  = isset( $workspace['dashboard_url'] ) ? (string) $workspace['dashboard_url'] : '';
$ajax_url       = isset( $workspace['ajax_url'] ) ? (string) $workspace['ajax_url'] : admin_url( 'admin-ajax.php' );
$ajax_nonce     = isset( $workspace['ajax_nonce'] ) ? (string) $workspace['ajax_nonce'] : wp_create_nonce( 'tpw_system_pages_ajax' );
$notice_tone    = isset( $workspace['notice_tone'] ) ? (string) $workspace['notice_tone'] : 'info';
$notice_text    = isset( $workspace['notice_text'] ) ? (string) $workspace['notice_text'] : '';
?>
<section id="flexiclub-system-pages-overview" class="tpw-flexiclub-dashboard__hero tpw-card tpw-flexiclub-system-pages__hero">
	<div class="tpw-flexiclub-dashboard__brand-row tpw-flexiclub-system-pages__hero-head">
		<div class="tpw-flexiclub-dashboard__welcome tpw-flexiclub-system-pages__hero-copy">
			<h2><?php esc_html_e( 'System Pages Workspace', 'tpw-core' ); ?></h2>
			<p><?php esc_html_e( 'Review every registered FlexiClub and add-on page, then repair or recreate only the pages that actually need intervention.', 'tpw-core' ); ?></p>
		</div>
		<div class="tpw-flexiclub-system-pages__hero-actions">
			<?php if ( '' !== $dashboard_url ) : ?>
				<a class="tpw-btn tpw-btn-outline" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Back to dashboard', 'tpw-core' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="tpw-flexiclub-system-pages__summary-grid">
		<?php foreach ( $summary_cards as $card ) : ?>
			<div class="tpw-flexiclub-system-pages__summary-card">
				<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--<?php echo esc_attr( $card['tone'] ); ?>"><?php echo esc_html( $card['label'] ); ?></span>
				<strong><?php echo esc_html( $card['value'] ); ?></strong>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<section id="flexiclub-system-pages-list" class="tpw-flexiclub-dashboard__section tpw-card tpw-flexiclub-system-pages__section tpw-flexiclub-system-pages__section--full">
	<div class="tpw-flexiclub-dashboard__section-head tpw-flexiclub-system-pages__section-head">
		<div>
			<h2><?php esc_html_e( 'Registered Pages', 'tpw-core' ); ?></h2>
			<p><?php esc_html_e( 'Each row shows the registration state, current page health, and the actions you can take from this workspace.', 'tpw-core' ); ?></p>
		</div>
		<details class="tpw-flexiclub-system-pages__help-shell">
			<summary class="tpw-btn tpw-btn-outline tpw-flexiclub-system-pages__help-toggle"><?php esc_html_e( 'How this works', 'tpw-core' ); ?></summary>
			<div id="flexiclub-system-pages-help" class="tpw-flexiclub-system-pages__help-panel">
				<h3><?php esc_html_e( 'What the actions mean', 'tpw-core' ); ?></h3>
				<ul class="tpw-flexiclub-system-pages__guide-list">
					<li><?php esc_html_e( 'View opens the current front-end page. Edit opens the underlying WordPress page so you can fix content or shortcode issues directly.', 'tpw-core' ); ?></li>
					<li><?php esc_html_e( 'Unlink uses the existing System Pages unlink action. It clears the stored mapping without deleting the WordPress page itself.', 'tpw-core' ); ?></li>
					<li><?php esc_html_e( 'Recreate uses the existing System Pages recreate action. Use it when a registered page is missing or when the current logic can safely repair an unpublished page.', 'tpw-core' ); ?></li>
					<li><?php esc_html_e( 'If a published page is missing the expected shortcode, edit that page to repair it. The current System Pages logic does not overwrite published content automatically.', 'tpw-core' ); ?></li>
					<li><?php esc_html_e( 'Only authorised FlexiClub admins and member managers can access this workspace and run these actions.', 'tpw-core' ); ?></li>
				</ul>
			</div>
		</details>
	</div>

	<?php if ( '' !== $notice_text ) : ?>
		<div class="tpw-flexiclub-system-pages__notice tpw-flexiclub-system-pages__notice--<?php echo esc_attr( $notice_tone ); ?>">
			<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--<?php echo esc_attr( $notice_tone ); ?>"><?php echo esc_html( ucfirst( str_replace( '-', ' ', $notice_tone ) ) ); ?></span>
			<p><?php echo esc_html( $notice_text ); ?></p>
		</div>
	<?php endif; ?>

	<div id="tpw-flexiclub-system-pages-feedback" class="tpw-flexiclub-system-pages__feedback" aria-live="polite"></div>

	<?php if ( empty( $rows ) ) : ?>
		<p class="tpw-flexiclub-system-pages__empty"><?php esc_html_e( 'No system pages are currently registered.', 'tpw-core' ); ?></p>
	<?php else : ?>
		<div class="tpw-table-container tpw-flexiclub-system-pages__table" role="table" aria-label="<?php esc_attr_e( 'Registered system pages', 'tpw-core' ); ?>">
			<div class="table-row tpw-flexiclub-system-pages__row tpw-flexiclub-system-pages__row--head" role="row">
				<div class="table-cell" role="columnheader"><?php esc_html_e( 'Page', 'tpw-core' ); ?></div>
				<div class="table-cell" role="columnheader"><?php esc_html_e( 'Required', 'tpw-core' ); ?></div>
				<div class="table-cell" role="columnheader"><?php esc_html_e( 'Page Status', 'tpw-core' ); ?></div>
				<div class="table-cell" role="columnheader"><?php esc_html_e( 'Shortcode', 'tpw-core' ); ?></div>
				<div class="table-cell" role="columnheader"><?php esc_html_e( 'Linked Page', 'tpw-core' ); ?></div>
				<div class="table-cell" role="columnheader"><?php esc_html_e( 'Action State', 'tpw-core' ); ?></div>
				<div class="table-cell" role="columnheader"><?php esc_html_e( 'Actions', 'tpw-core' ); ?></div>
			</div>

			<?php foreach ( $rows as $row ) : ?>
				<div class="table-row tpw-flexiclub-system-pages__row" role="row">
					<div class="table-cell tpw-flexiclub-system-pages__cell" role="cell" data-label="<?php esc_attr_e( 'Page', 'tpw-core' ); ?>">
						<div class="tpw-flexiclub-system-pages__page-title"><?php echo esc_html( $row['title'] ); ?></div>
						<div class="tpw-flexiclub-system-pages__page-meta">
							<span class="tpw-flexiclub-system-pages__page-chip tpw-flexiclub-system-pages__page-chip--plugin"><?php echo esc_html( $row['plugin'] ); ?></span>
							<?php if ( ! empty( $row['legacy_label'] ) ) : ?>
								<span class="tpw-flexiclub-system-pages__page-chip tpw-flexiclub-system-pages__page-chip--legacy"><?php echo esc_html( $row['legacy_label'] ); ?></span>
							<?php endif; ?>
						</div>
						<p class="tpw-flexiclub-system-pages__page-slug"><?php echo esc_html( '/' . $row['slug'] . '/' ); ?></p>
					</div>

					<div class="table-cell tpw-flexiclub-system-pages__cell" role="cell" data-label="<?php esc_attr_e( 'Required', 'tpw-core' ); ?>">
						<span class="tpw-flexiclub-system-pages__page-chip tpw-flexiclub-system-pages__page-chip--<?php echo esc_attr( $row['required'] ? 'required' : 'optional' ); ?>"><?php echo esc_html( $row['required_label'] ); ?></span>
					</div>

					<div class="table-cell tpw-flexiclub-system-pages__cell" role="cell" data-label="<?php esc_attr_e( 'Page Status', 'tpw-core' ); ?>">
						<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--<?php echo esc_attr( $row['status_tone'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
					</div>

					<div class="table-cell tpw-flexiclub-system-pages__cell" role="cell" data-label="<?php esc_attr_e( 'Shortcode', 'tpw-core' ); ?>">
						<code class="tpw-flexiclub-system-pages__shortcode"><?php echo esc_html( $row['shortcode'] ); ?></code>
					</div>

					<div class="table-cell tpw-flexiclub-system-pages__cell" role="cell" data-label="<?php esc_attr_e( 'Linked Page', 'tpw-core' ); ?>">
						<div class="tpw-flexiclub-system-pages__linked-title"><?php echo esc_html( $row['linked_page_text'] ); ?></div>
						<p class="tpw-flexiclub-system-pages__linked-meta"><?php echo esc_html( $row['linked_page_meta'] ); ?></p>
					</div>

					<div class="table-cell tpw-flexiclub-system-pages__cell" role="cell" data-label="<?php esc_attr_e( 'Action State', 'tpw-core' ); ?>">
						<span class="tpw-flexiclub-dashboard__status tpw-flexiclub-dashboard__status--<?php echo esc_attr( $row['action_tone'] ); ?>"><?php echo esc_html( $row['action_label'] ); ?></span>
						<p class="tpw-flexiclub-system-pages__action-copy"><?php echo esc_html( $row['action_message'] ); ?></p>
					</div>

					<div class="table-cell tpw-flexiclub-system-pages__cell" role="cell" data-label="<?php esc_attr_e( 'Actions', 'tpw-core' ); ?>">
						<div class="tpw-flexiclub-system-pages__actions">
							<?php if ( ! empty( $row['linked_page_url'] ) ) : ?>
								<a class="tpw-btn tpw-btn-outline" href="<?php echo esc_url( $row['linked_page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'tpw-core' ); ?></a>
							<?php endif; ?>
							<?php if ( ! empty( $row['edit_url'] ) ) : ?>
								<a class="tpw-btn tpw-btn-secondary" href="<?php echo esc_url( $row['edit_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit', 'tpw-core' ); ?></a>
							<?php endif; ?>
							<?php if ( ! empty( $row['can_unlink'] ) ) : ?>
								<button class="tpw-btn tpw-btn-light js-tpw-flexiclub-system-page-unlink" type="button" data-slug="<?php echo esc_attr( $row['slug'] ); ?>" data-nonce="<?php echo esc_attr( $ajax_nonce ); ?>" data-confirm="<?php echo esc_attr( sprintf( __( 'Clear the current System Pages link for %s?', 'tpw-core' ), $row['title'] ) ); ?>"><?php esc_html_e( 'Unlink', 'tpw-core' ); ?></button>
							<?php endif; ?>
							<?php if ( ! empty( $row['can_recreate'] ) ) : ?>
								<button class="tpw-btn tpw-btn-primary js-tpw-flexiclub-system-page-recreate" type="button" data-slug="<?php echo esc_attr( $row['slug'] ); ?>" data-nonce="<?php echo esc_attr( $ajax_nonce ); ?>" data-confirm="<?php echo esc_attr( sprintf( __( 'Recreate or repair the page for %s?', 'tpw-core' ), $row['title'] ) ); ?>"><?php echo esc_html( $row['recreate_label'] ); ?></button>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<script>
(function () {
	var table = document.querySelector('.tpw-flexiclub-system-pages__table');
	var feedback = document.getElementById('tpw-flexiclub-system-pages-feedback');
	var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;

	function reloadWithNotice(type, message) {
		var url = new URL(window.location.href);

		url.searchParams.set('tpw_system_pages_notice', type);
		url.searchParams.set('tpw_system_pages_message', message);
		window.location.assign(url.toString());
	}

	function setFeedback(type, message) {
		if (!feedback) {
			return;
		}

		feedback.className = 'tpw-flexiclub-system-pages__feedback is-visible tpw-flexiclub-system-pages__feedback--' + type;
		feedback.textContent = message;
	}

	function request(body) {
		return fetch(ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			credentials: 'same-origin',
			body: new URLSearchParams(body).toString()
		}).then(function (response) {
			return response.json();
		});
	}

	if (!table) {
		return;
	}

	table.addEventListener('click', function (event) {
		var button = event.target.closest('.js-tpw-flexiclub-system-page-recreate, .js-tpw-flexiclub-system-page-unlink');
		var action;
		var waitingText;
		var slug;

		if (!button) {
			return;
		}

		event.preventDefault();
		slug = button.getAttribute('data-slug') || '';

		if (!slug) {
			setFeedback('error', 'Missing page slug.');
			return;
		}

		if (!window.confirm(button.getAttribute('data-confirm') || 'Continue?')) {
			return;
		}

		action = button.classList.contains('js-tpw-flexiclub-system-page-unlink') ? 'tpw_system_page_unlink' : 'tpw_system_page_recreate';
		waitingText = action === 'tpw_system_page_unlink'
			? 'Clearing the existing System Pages link...'
			: 'Running the existing System Pages recreate action...';

		button.disabled = true;
		setFeedback('info', waitingText);

		request({
			action: action,
			slug: slug,
			nonce: button.getAttribute('data-nonce') || ''
		}).then(function (result) {
			if (result && result.success) {
				reloadWithNotice('success', (result.data && result.data.message) ? result.data.message : 'System page updated.');
				return;
			}

			if (result && result.data && result.data.message) {
				setFeedback('error', result.data.message);
			} else if (result && typeof result.data === 'string') {
				setFeedback('error', result.data);
			} else {
				setFeedback('error', 'The request did not complete successfully.');
			}
		}).catch(function () {
			setFeedback('error', 'The request failed before WordPress returned a response.');
		}).finally(function () {
			button.disabled = false;
		});
	});
}());
</script>