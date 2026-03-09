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
	 * Last scheduler error message for debugging failed schedule attempts.
	 *
	 * @var string
	 */
	private static $last_error = '';

	/**
	 * Last schedule attempt context for diagnostics.
	 *
	 * @var array<string, mixed>
	 */
	private static $last_schedule_debug = array();

	/**
	 * Recent schedule attempt history for diagnostics.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $schedule_debug_history = array();

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
	 * @param bool   $unique    Whether the action should be unique by hook + args + group.
	 *
	 * @return int|false Action ID on success, false on failure/unavailable.
	 */
	public static function schedule_single( $timestamp, $hook, $args = array(), $group = 'tpw', $unique = true ) {
		self::$last_error = '';

		if ( false === self::init_if_needed() ) {
			self::$last_error = 'scheduler wrapper returned false';
			self::set_last_schedule_debug(
				array(
					'timestamp' => (int) $timestamp,
					'hook'      => (string) $hook,
					'args'      => is_array( $args ) ? $args : array(),
					'group'     => (string) $group,
					'unique'    => (bool) $unique,
					'result'    => false,
					'error'     => self::$last_error,
				)
			);
			return false;
		}
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			self::$last_error = 'action scheduler function unavailable';
			self::set_last_schedule_debug(
				array(
					'timestamp' => (int) $timestamp,
					'hook'      => (string) $hook,
					'args'      => is_array( $args ) ? $args : array(),
					'group'     => (string) $group,
					'unique'    => (bool) $unique,
					'result'    => false,
					'error'     => self::$last_error,
				)
			);
			return false;
		}
		if ( '' === (string) $hook ) {
			self::$last_error = 'invalid scheduler hook';
			self::set_last_schedule_debug(
				array(
					'timestamp' => (int) $timestamp,
					'hook'      => (string) $hook,
					'args'      => is_array( $args ) ? $args : array(),
					'group'     => (string) $group,
					'unique'    => (bool) $unique,
					'result'    => false,
					'error'     => self::$last_error,
				)
			);
			return false;
		}

		$timestamp = (int) $timestamp;
		$hook      = (string) $hook;
		$group     = (string) $group;
		$args      = is_array( $args ) ? $args : array();

		if ( $timestamp <= 0 ) {
			self::$last_error = 'invalid timestamp';
			self::set_last_schedule_debug(
				array(
					'timestamp' => $timestamp,
					'hook'      => $hook,
					'args'      => $args,
					'group'     => $group,
					'unique'    => (bool) $unique,
					'result'    => false,
					'error'     => self::$last_error,
				)
			);
			return false;
		}

		$debug_context = array(
			'timestamp'              => $timestamp,
			'hook'                   => $hook,
			'args'                   => $args,
			'group'                  => $group,
			'unique'                 => (bool) $unique,
			'action_scheduler_call'  => array(
				'hook'      => $hook,
				'args'      => $args,
				'group'     => $group,
				'timestamp' => $timestamp,
				'unique'    => false,
			),
		);

		if ( function_exists( 'apply_filters' ) ) {
			$pre = apply_filters( 'pre_as_schedule_single_action', null, $timestamp, $hook, $args, $group, 10, (bool) $unique );
			if ( null !== $pre ) {
				$action_id = is_int( $pre ) ? $pre : 0;
				if ( $action_id <= 0 ) {
					self::$last_error = 'pre_as_schedule_single_action short-circuited scheduling with a non-success result';
					self::set_last_schedule_debug( array_merge( $debug_context, array( 'result' => false, 'error' => self::$last_error ) ) );
					return false;
				}

				self::set_last_schedule_debug( array_merge( $debug_context, array( 'result' => $action_id, 'error' => '' ) ) );
				return $action_id;
			}
		}

		if ( (bool) $unique && function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, $group ) ) {
			self::$last_error = 'duplicate/unique action already exists for the same hook, args, and group';
			self::set_last_schedule_debug( array_merge( $debug_context, array( 'result' => false, 'error' => self::$last_error ) ) );
			return false;
		}

		try {
			if ( class_exists( 'ActionScheduler', false ) && is_callable( array( 'ActionScheduler', 'factory' ) ) ) {
				$factory   = ActionScheduler::factory();
				$action_id = is_object( $factory ) && is_callable( array( $factory, 'single' ) )
					? (int) $factory->single( $hook, $args, $timestamp, $group )
					: (int) as_schedule_single_action( $timestamp, $hook, $args, $group, false );
			} else {
				$action_id = (int) as_schedule_single_action( $timestamp, $hook, $args, $group, false );
			}
		} catch ( Throwable $throwable ) {
			self::$last_error = self::normalize_error_message( $throwable->getMessage() );
			self::set_last_schedule_debug( array_merge( $debug_context, array( 'result' => false, 'error' => self::$last_error ) ) );
			return false;
		}

		if ( $action_id <= 0 ) {
			self::$last_error = 'scheduler returned 0 without an exception message';
			self::set_last_schedule_debug( array_merge( $debug_context, array( 'result' => false, 'error' => self::$last_error ) ) );
			return false;
		}

		self::set_last_schedule_debug( array_merge( $debug_context, array( 'result' => $action_id, 'error' => '' ) ) );

		return ( $action_id > 0 ) ? $action_id : false;
	}

	/**
	 * Return the last scheduler error message.
	 *
	 * @since 1.11.1
	 *
	 * @return string
	 */
	public static function get_last_error() {
		return (string) self::$last_error;
	}

	/**
	 * Return the last schedule attempt payload and result.
	 *
	 * @since 1.11.1
	 *
	 * @return array<string, mixed>
	 */
	public static function get_last_schedule_debug() {
		return is_array( self::$last_schedule_debug ) ? self::$last_schedule_debug : array();
	}

	/**
	 * Return recent schedule attempt history for the current request.
	 *
	 * @since 1.11.1
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_schedule_debug_history() {
		return is_array( self::$schedule_debug_history ) ? self::$schedule_debug_history : array();
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

	/**
	 * Internal: count pending or running actions matching a hook/group, regardless of args.
	 *
	 * @param string $hook  Hook.
	 * @param string $group Group.
	 * @return int|false
	 */
	private static function count_matching_pending_for_hook_group( $hook, $group ) {
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
					'status' => array(
						ActionScheduler_Store::STATUS_PENDING,
						ActionScheduler_Store::STATUS_RUNNING,
					),
					'hook'   => (string) $hook,
					'group'  => (string) $group,
				),
				'count'
			);
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Internal: normalize exception text from Action Scheduler/store failures.
	 *
	 * @param string $message Raw error message.
	 * @return string
	 */
	private static function normalize_error_message( $message ) {
		$message = trim( (string) $message );

		if ( '' === $message ) {
			return 'scheduler exception with empty message';
		}

		return $message;
	}

	/**
	 * Internal: store last schedule attempt details.
	 *
	 * @param array<string, mixed> $debug_context Attempt context.
	 * @return void
	 */
	private static function set_last_schedule_debug( array $debug_context ) {
		self::$last_schedule_debug = $debug_context;
		self::$schedule_debug_history[] = $debug_context;

		if ( count( self::$schedule_debug_history ) > 100 ) {
			self::$schedule_debug_history = array_slice( self::$schedule_debug_history, -100 );
		}
	}
}
