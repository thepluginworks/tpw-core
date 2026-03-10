<?php
/**
 * Fired during plugin deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Core_Deactivator {
	public static function deactivate() {
		if ( ! class_exists( 'TPW_Email_Logs' ) && defined( 'TPW_CORE_PATH' ) ) {
			$logs_file = TPW_CORE_PATH . 'modules/email/class-tpw-email-logs.php';
			if ( file_exists( $logs_file ) ) {
				require_once $logs_file;
			}
		}

		if ( class_exists( 'TPW_Email_Logs' ) ) {
			TPW_Email_Logs::unschedule_cleanup();
		}
	}
}
