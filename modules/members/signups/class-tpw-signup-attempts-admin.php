<?php
/**
 * Temporary admin debug screen for signup attempts.
 *
 * @package TPW_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Signup_Attempts_Admin {
	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'tpw-members-signups-debug';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_pages' ) );
	}

	/**
	 * Register the temporary debug screen under a Members admin area.
	 *
	 * @return void
	 */
	public static function register_admin_pages() {
		if ( ! self::members_module_enabled() || ! self::current_user_can_manage_members() ) {
			return;
		}

		add_menu_page(
			esc_html__( 'Members', 'tpw-core' ),
			esc_html__( 'Members', 'tpw-core' ),
			'read',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-groups',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			esc_html__( 'Sign Ups (Debug)', 'tpw-core' ),
			esc_html__( 'Sign Ups (Debug)', 'tpw-core' ),
			'read',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the signup attempts debug page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! self::current_user_can_manage_members() ) {
			wp_die( esc_html__( 'You do not have permission to access this screen.', 'tpw-core' ), 403 );
		}

		$attempts = TPW_Signup_Attempts_Service::get_instance()->load_attempts(
			array(
				'order_by' => 'created_at',
				'order'    => 'DESC',
			)
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Sign Ups (Debug)', 'tpw-core' ); ?></h1>
			<p><?php echo esc_html__( 'Temporary internal testing screen for draft signup attempts and the Branch 5 internal completion bridge.', 'tpw-core' ); ?></p>
			<?php self::render_notice(); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Attempt ID', 'tpw-core' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Email', 'tpw-core' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Flow Key', 'tpw-core' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Status', 'tpw-core' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Created Date', 'tpw-core' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Action', 'tpw-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $attempts ) ) : ?>
						<tr>
							<td colspan="6"><?php echo esc_html__( 'No signup attempts found.', 'tpw-core' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $attempts as $attempt ) : ?>
							<?php self::render_attempt_row( $attempt ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render one attempt row.
	 *
	 * @param array $attempt Attempt data.
	 * @return void
	 */
	private static function render_attempt_row( $attempt ) {
		$attempt_id = isset( $attempt['id'] ) ? absint( $attempt['id'] ) : 0;
		$status     = isset( $attempt['status'] ) ? sanitize_key( $attempt['status'] ) : '';
		?>
		<tr>
			<td><?php echo esc_html( (string) $attempt_id ); ?></td>
			<td><?php echo esc_html( isset( $attempt['email'] ) ? (string) $attempt['email'] : '' ); ?></td>
			<td><?php echo esc_html( isset( $attempt['flow_key'] ) ? (string) $attempt['flow_key'] : '' ); ?></td>
			<td><?php echo esc_html( $status ); ?></td>
			<td><?php echo esc_html( isset( $attempt['created_at'] ) ? (string) $attempt['created_at'] : '' ); ?></td>
			<td>
				<?php if ( 'draft' === $status && $attempt_id > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="tpw_signup_complete_internal" />
						<input type="hidden" name="attempt_id" value="<?php echo esc_attr( (string) $attempt_id ); ?>" />
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'tpw_signup_complete_internal_' . $attempt_id ) ); ?>" />
						<button type="submit" class="button button-primary button-small"><?php echo esc_html__( 'Complete Internally', 'tpw-core' ); ?></button>
					</form>
				<?php else : ?>
					<?php echo esc_html__( '—', 'tpw-core' ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a completion notice after redirect.
	 *
	 * @return void
	 */
	private static function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args are used only to render post-redirect admin notices.
		$attempt_id = isset( $_GET['tpw_signup_attempt_id'] ) ? absint( wp_unslash( $_GET['tpw_signup_attempt_id'] ) ) : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args are used only to render post-redirect admin notices.
		if ( isset( $_GET['tpw_signup_completion'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args are used only to render post-redirect admin notices.
			$status = sanitize_key( wp_unslash( $_GET['tpw_signup_completion'] ) );
			if ( 'completed' === $status ) {
				$message = $attempt_id > 0
					? sprintf( 'Signup attempt %d completed internally.', $attempt_id )
					: 'Signup attempt completed internally.';
				printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $message ) );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args are used only to render post-redirect admin notices.
		if ( isset( $_GET['tpw_signup_completion_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query args are used only to render post-redirect admin notices.
			$error_code = sanitize_key( wp_unslash( $_GET['tpw_signup_completion_error'] ) );
			$message    = $attempt_id > 0
				? sprintf( 'Signup attempt %1$d could not be completed internally. Error: %2$s.', $attempt_id, $error_code )
				: sprintf( 'Signup attempt could not be completed internally. Error: %s.', $error_code );
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * Check whether the current user can manage members.
	 *
	 * @return bool
	 */
	private static function current_user_can_manage_members() {
		if ( ! class_exists( 'TPW_Member_Access' ) ) {
			$access_file = TPW_CORE_PATH . 'modules/members/includes/class-tpw-member-access.php';
			if ( file_exists( $access_file ) ) {
				require_once $access_file;
			}
		}

		if ( ! class_exists( 'TPW_Member_Access' ) ) {
			return current_user_can( 'manage_options' );
		}

		return TPW_Member_Access::can_manage_members_current();
	}

	/**
	 * Check whether the Members module is enabled.
	 *
	 * @return bool
	 */
	private static function members_module_enabled() {
		if ( function_exists( 'tpw_members_module_enabled' ) ) {
			return true === tpw_members_module_enabled();
		}

		return true;
	}
}
