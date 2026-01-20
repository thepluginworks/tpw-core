<?php
/**
 * TPW Core Scheduler Manager
 *
 * Provides a single, stable scheduling API for TPW plugins using Action Scheduler.
 *
 * IMPORTANT: Only TPW Core may load Action Scheduler. Other TPW plugins must call
 * these Core wrappers (and should not bundle their own copies).
 *
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Core_Scheduler {
	/**
	 * Whether we've attempted initialization.
	 *
	 * @var bool
	 */
	private static $did_init = false;

	/**
	 * Whether Action Scheduler is available.
	 *
	 * @var bool
	 */
	private static $is_available = false;

	/**
	 * Where the active Action Scheduler came from.
	 *
	 * Values: 'unknown' | 'external' | 'core-bundled'.
	 *
	 * @var string
	 */
	private static $source = 'unknown';

	/**
	 * Whether TPW Core included its bundled copy during this request.
	 *
	 * @var bool
	 */
	private static $core_included = false;

	/**
	 * Detect and load Action Scheduler if needed.
	 *
	 * This should be called by other TPW plugins early (e.g. on plugins_loaded)
	 * when they need scheduling.
	 *
	 * @since 1.7.0
	 *
	 * @return bool True if scheduling is available, false otherwise.
	 */
	public static function init_if_needed() {
		if ( true === self::$did_init ) {
			return self::$is_available;
		}

		self::$did_init = true;

		// If another plugin (e.g. WooCommerce) has already loaded Action Scheduler, use it.
		if ( self::action_scheduler_is_loaded() ) {
			self::$is_available = true;
			self::$source       = 'external';
			return true;
		}

		// Load Core's bundled copy (guarded so it can't run twice from Core).
		if ( false === self::$core_included ) {
			$loader_file = self::core_path() . 'includes/scheduler/action-scheduler/action-scheduler.php';
			if ( file_exists( $loader_file ) ) {
				self::$core_included = true;
				// Marker for internal debugging/support.
				if ( ! defined( 'TPW_CORE_ACTION_SCHEDULER_LOADED' ) ) {
					define( 'TPW_CORE_ACTION_SCHEDULER_LOADED', true );
				}
				require_once $loader_file;
			}
		}

		self::$is_available = self::action_scheduler_is_loaded();
		self::$source       = self::$is_available ? 'core-bundled' : 'unknown';

		return self::$is_available;
	}

	/**
	 * Whether Action Scheduler is available.
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	public static function is_available() {
		// Allow checking without forcing a load.
		return self::action_scheduler_is_loaded() || self::$is_available;
	}

	/**
	 * Schedule a single action.
	 *
	 * @since 1.7.0
	 *
	 * @param int    $timestamp When the action should run (UTC timestamp).
	 * @param string $hook      Hook to trigger.
	 * @param array  $args      Args passed to the hook.
	 * @param string $group     Action group. Defaults to 'tpw'.
	 * @param bool   $unique    Whether the action should be unique.
	 *
	 * @return int|false Action ID on success, false on failure/unavailable.
	 */
	public static function schedule_single( $timestamp, $hook, $args = array(), $group = 'tpw', $unique = true ) {
		if ( false === self::init_if_needed() ) {
			return false;
		}
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}
		if ( '' === (string) $hook ) {
			return false;
		}

		$timestamp = (int) $timestamp;
		$hook      = (string) $hook;
		$group     = (string) $group;
		$args      = is_array( $args ) ? $args : array();

		$action_id = (int) as_schedule_single_action( $timestamp, $hook, $args, $group, (bool) $unique );
		return ( $action_id > 0 ) ? $action_id : false;
	}

	/**
	 * Schedule a recurring action.
	 *
	 * @since 1.7.0
	 *
	 * @param int    $timestamp           When the first instance should run (UTC timestamp).
	 * @param int    $interval_in_seconds Interval between runs.
	 * @param string $hook                Hook to trigger.
	 * @param array  $args                Args passed to the hook.
	 * @param string $group               Action group. Defaults to 'tpw'.
	 * @param bool   $unique              Whether the action should be unique.
	 *
	 * @return int|false Action ID on success, false on failure/unavailable.
	 */
	public static function schedule_recurring( $timestamp, $interval_in_seconds, $hook, $args = array(), $group = 'tpw', $unique = true ) {
		if ( false === self::init_if_needed() ) {
			return false;
		}
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return false;
		}
		if ( '' === (string) $hook ) {
			return false;
		}

		$timestamp           = (int) $timestamp;
		$interval_in_seconds = (int) $interval_in_seconds;
		$hook                = (string) $hook;
		$group               = (string) $group;
		$args                = is_array( $args ) ? $args : array();

		$action_id = (int) as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args, $group, (bool) $unique );
		return ( $action_id > 0 ) ? $action_id : false;
	}

	/**
	 * Unschedule matching actions.
	 *
	 * Cancels all pending actions matching hook/args/group.
	 *
	 * @since 1.7.0
	 *
	 * @param string $hook  Hook name.
	 * @param array  $args  Args (must match scheduled args).
	 * @param string $group Group.
	 *
	 * @return int|false Number of actions unscheduled/cancelled, or false if unavailable.
	 */
	public static function unschedule( $hook, $args = array(), $group = 'tpw' ) {
		if ( false === self::init_if_needed() ) {
			return false;
		}
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return false;
		}

		$hook  = (string) $hook;
		$group = (string) $group;
		$args  = is_array( $args ) ? $args : array();

		$before = self::count_matching_pending( $hook, $args, $group );
		as_unschedule_all_actions( $hook, $args, $group );
		$after = self::count_matching_pending( $hook, $args, $group );

		if ( false === $before || false === $after ) {
			return 0;
		}

		return max( 0, (int) $before - (int) $after );
	}

	/**
	 * Check if a matching action is scheduled (pending or running).
	 *
	 * @since 1.7.0
	 *
	 * @param string $hook  Hook name.
	 * @param array  $args  Args.
	 * @param string $group Group.
	 *
	 * @return bool
	 */
	public static function has_scheduled( $hook, $args = array(), $group = 'tpw' ) {
		if ( false === self::init_if_needed() ) {
			return false;
		}
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			return (bool) as_has_scheduled_action( $hook, $args, $group );
		}
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$next = as_next_scheduled_action( $hook, $args, $group );
			return ( false !== $next && 0 !== (int) $next );
		}

		return false;
	}

	/**
	 * Get scheduled actions.
	 *
	 * @since 1.7.0
	 *
	 * @param string $hook   Hook name.
	 * @param array  $args   Args.
	 * @param string $group  Group.
	 * @param string $status Status (e.g. 'pending', 'in-progress', 'failed', 'complete').
	 *
	 * @return array|false Array of action IDs (or action objects, depending on AS config), or false if unavailable.
	 */
	public static function get_scheduled_actions( $hook, $args = array(), $group = 'tpw', $status = 'pending' ) {
		if ( false === self::init_if_needed() ) {
			return false;
		}
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}

		$hook   = (string) $hook;
		$group  = (string) $group;
		$status = (string) $status;
		$args   = is_array( $args ) ? $args : array();

		$query = array(
			'hook'   => $hook,
			'group'  => $group,
			'status' => $status,
		);

		if ( ! empty( $args ) ) {
			$query['args'] = $args;
		}

		return as_get_scheduled_actions( $query );
	}

	/**
	 * Lightweight status snapshot for diagnostics/support.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_status() {
		$available = self::is_available();
		$status    = array(
			'available' => (bool) $available,
			'source'    => (string) self::$source,
			'version'   => defined( 'ACTION_SCHEDULER_VERSION' ) ? ACTION_SCHEDULER_VERSION : '',
		);

		if ( true === $available ) {
			$pending_count = self::count_pending_actions();
			if ( false !== $pending_count ) {
				$status['pending_count'] = (int) $pending_count;
			}
		}

		return $status;
	}

	/**
	 * Internal: detect whether Action Scheduler has been loaded.
	 *
	 * @return bool
	 */
	private static function action_scheduler_is_loaded() {
		return function_exists( 'as_schedule_single_action' ) || class_exists( 'ActionScheduler', false );
	}

	/**
	 * Internal: get TPW Core absolute path.
	 *
	 * @return string
	 */
	private static function core_path() {
		if ( defined( 'TPW_CORE_PATH' ) ) {
			return TPW_CORE_PATH;
		}

		$root = dirname( __DIR__, 2 );
		return trailingslashit( $root );
	}

	/**
	 * Internal: count all pending actions (best-effort).
	 *
	 * @return int|false
	 */
	private static function count_pending_actions() {
		if ( ! class_exists( 'ActionScheduler_Store', false ) ) {
			return false;
		}
		if ( ! is_callable( array( 'ActionScheduler_Store', 'instance' ) ) ) {
			return false;
		}

		$store = ActionScheduler_Store::instance();
		if ( ! is_object( $store ) || ! is_callable( array( $store, 'query_actions' ) ) ) {
			return false;
		}

		try {
			return (int) $store->query_actions(
				array(
					'status' => ActionScheduler_Store::STATUS_PENDING,
				),
				'count'
			);
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Internal: count pending actions matching a hook/args/group.
	 *
	 * @param string $hook  Hook.
	 * @param array  $args  Args.
	 * @param string $group Group.
	 * @return int|false
	 */
	private static function count_matching_pending( $hook, $args, $group ) {
		if ( ! class_exists( 'ActionScheduler_Store', false ) ) {
			return false;
		}
		if ( ! is_callable( array( 'ActionScheduler_Store', 'instance' ) ) ) {
			return false;
		}

		$store = ActionScheduler_Store::instance();
		if ( ! is_object( $store ) || ! is_callable( array( $store, 'query_actions' ) ) ) {
			return false;
		}

		$query = array(
			'status' => ActionScheduler_Store::STATUS_PENDING,
			'hook'   => (string) $hook,
			'group'  => (string) $group,
		);

		if ( ! empty( $args ) ) {
			$query['args'] = $args;
		}

		try {
			return (int) $store->query_actions( $query, 'count' );
		} catch ( Exception $e ) {
			return false;
		}
	}
}
