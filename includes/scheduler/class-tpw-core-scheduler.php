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
		$pre_filter_present = self::has_pre_schedule_single_filter();
		$pre_filter_count   = self::count_filter_callbacks( 'pre_as_schedule_single_action' );

		if ( false === self::init_if_needed() ) {
			self::$last_error = 'scheduler wrapper returned false';
			self::set_last_schedule_debug(
				array(
					'timestamp'                 => (int) $timestamp,
					'hook'                      => (string) $hook,
					'args'                      => is_array( $args ) ? $args : array(),
					'group'                     => (string) $group,
					'unique'                    => (bool) $unique,
					'result'                    => false,
					'raw_scheduler_return'      => false,
					'error'                     => self::$last_error,
					'branch'                    => 'wrapper_init_failed',
					'pre_filter_present'        => $pre_filter_present,
					'pre_filter_callback_count' => $pre_filter_count,
				)
			);
			return false;
		}
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			self::$last_error = 'action scheduler function unavailable';
			self::set_last_schedule_debug(
				array(
					'timestamp'                 => (int) $timestamp,
					'hook'                      => (string) $hook,
					'args'                      => is_array( $args ) ? $args : array(),
					'group'                     => (string) $group,
					'unique'                    => (bool) $unique,
					'result'                    => false,
					'raw_scheduler_return'      => false,
					'error'                     => self::$last_error,
					'branch'                    => 'wrapper_function_unavailable',
					'pre_filter_present'        => $pre_filter_present,
					'pre_filter_callback_count' => $pre_filter_count,
				)
			);
			return false;
		}
		if ( '' === (string) $hook ) {
			self::$last_error = 'invalid scheduler hook';
			self::set_last_schedule_debug(
				array(
					'timestamp'                 => (int) $timestamp,
					'hook'                      => (string) $hook,
					'args'                      => is_array( $args ) ? $args : array(),
					'group'                     => (string) $group,
					'unique'                    => (bool) $unique,
					'result'                    => false,
					'raw_scheduler_return'      => false,
					'error'                     => self::$last_error,
					'branch'                    => 'wrapper_invalid_hook',
					'pre_filter_present'        => $pre_filter_present,
					'pre_filter_callback_count' => $pre_filter_count,
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
					'timestamp'                 => $timestamp,
					'hook'                      => $hook,
					'args'                      => $args,
					'group'                     => $group,
					'unique'                    => (bool) $unique,
					'result'                    => false,
					'raw_scheduler_return'      => false,
					'error'                     => self::$last_error,
					'branch'                    => 'wrapper_invalid_timestamp',
					'pre_filter_present'        => $pre_filter_present,
					'pre_filter_callback_count' => $pre_filter_count,
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
			'branch'                 => 'wrapper_before_schedule_call',
			'raw_scheduler_return'   => null,
			'wrapper_file'           => __FILE__,
			'wrapper_file_mtime'     => @filemtime( __FILE__ ),
			'core_version'           => defined( 'TPW_CORE_VERSION' ) ? TPW_CORE_VERSION : '',
			'action_scheduler_source' => self::$source,
			'action_scheduler_version' => defined( 'ACTION_SCHEDULER_VERSION' ) ? ACTION_SCHEDULER_VERSION : '',
			'pre_filter_present'     => $pre_filter_present,
			'pre_filter_callback_count' => $pre_filter_count,
			'pre_filter_returned_non_null' => false,
			'pre_filter_return_value' => null,
			'pre_filter_short_circuited' => false,
			'action_scheduler_call'  => array(
				'hook'      => $hook,
				'args'      => $args,
				'group'     => $group,
				'timestamp' => $timestamp,
				'unique'    => false,
			),
		);

		self::maybe_log_schedule_event( 'before_call', $debug_context );

		if ( function_exists( 'apply_filters' ) ) {
			$pre = apply_filters( 'pre_as_schedule_single_action', null, $timestamp, $hook, $args, $group, 10, (bool) $unique );
			$debug_context['pre_filter_returned_non_null'] = null !== $pre;
			$debug_context['pre_filter_return_value']      = self::normalize_debug_value( $pre );
			if ( null !== $pre ) {
				$debug_context['pre_filter_short_circuited'] = true;
				$action_id = is_int( $pre ) ? $pre : 0;
				if ( $action_id <= 0 ) {
					self::$last_error = 'pre_as_schedule_single_action short-circuited scheduling with a non-success result';
					$context = array_merge( $debug_context, array( 'result' => false, 'raw_scheduler_return' => $pre, 'error' => self::$last_error, 'branch' => 'pre_filter_short_circuit_failed' ) );
					self::set_last_schedule_debug( $context );
					self::maybe_log_schedule_event( 'pre_filter_failed', $context );
					return false;
				}

				$context = array_merge( $debug_context, array( 'result' => $action_id, 'raw_scheduler_return' => $pre, 'error' => '', 'branch' => 'pre_filter_short_circuit_success' ) );
				self::set_last_schedule_debug( $context );
				self::maybe_log_schedule_event( 'pre_filter_success', $context );
				return $action_id;
			}
		}

		if ( (bool) $unique && function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, $group ) ) {
			self::$last_error = 'duplicate/unique action already exists for the same hook, args, and group';
			$context = array_merge( $debug_context, array( 'result' => false, 'raw_scheduler_return' => false, 'error' => self::$last_error, 'branch' => 'wrapper_duplicate_detection' ) );
			self::set_last_schedule_debug( $context );
			self::maybe_log_schedule_event( 'wrapper_duplicate_detection', $context );
			return false;
		}

		try {
			$call_strategy = 'as_schedule_single_action_fallback';
			if ( class_exists( 'ActionScheduler', false ) && is_callable( array( 'ActionScheduler', 'factory' ) ) ) {
				$factory   = ActionScheduler::factory();
				if ( is_object( $factory ) && is_callable( array( $factory, 'single' ) ) ) {
					$call_strategy       = 'action_scheduler_factory_single';
					$raw_scheduler_return = $factory->single( $hook, $args, $timestamp, $group );
				} else {
					$raw_scheduler_return = as_schedule_single_action( $timestamp, $hook, $args, $group, false );
				}
			} else {
				$raw_scheduler_return = as_schedule_single_action( $timestamp, $hook, $args, $group, false );
			}

			$action_id = is_int( $raw_scheduler_return ) ? $raw_scheduler_return : (int) $raw_scheduler_return;
			$debug_context['call_strategy']       = $call_strategy;
			$debug_context['raw_scheduler_return'] = $raw_scheduler_return;
			self::maybe_log_schedule_event( 'after_call', array_merge( $debug_context, array( 'result' => $action_id, 'branch' => 'wrapper_after_schedule_call', 'error' => self::$last_error ) ) );
		} catch ( Throwable $throwable ) {
			self::$last_error = self::normalize_error_message( $throwable->getMessage() );
			$context = array_merge( $debug_context, array( 'result' => false, 'error' => self::$last_error, 'branch' => 'action_scheduler_throwable', 'throwable_class' => get_class( $throwable ) ) );
			self::set_last_schedule_debug( $context );
			self::maybe_log_schedule_event( 'call_exception', $context );
			return false;
		}

		if ( $action_id <= 0 ) {
			self::$last_error = 'scheduler returned 0 without an exception message';
			$context = array_merge( $debug_context, array( 'result' => false, 'error' => self::$last_error, 'branch' => 'action_scheduler_zero_return' ) );
			self::set_last_schedule_debug( $context );
			self::maybe_log_schedule_event( 'zero_return', $context );
			return false;
		}

		$context = array_merge( $debug_context, array( 'result' => $action_id, 'error' => '', 'branch' => 'action_scheduler_success' ) );
		self::set_last_schedule_debug( $context );
		self::maybe_log_schedule_event( 'success', $context );

		return ( $action_id > 0 ) ? $action_id : false;
	}

	/**
	 * Return the last scheduler error message.
	 *
	 * @since 1.11.2
	 *
	 * @return string
	 */
	public static function get_last_error() {
		return (string) self::$last_error;
	}

	/**
	 * Return the last schedule attempt payload and result.
	 *
	 * @since 1.11.2
	 *
	 * @return array<string, mixed>
	 */
	public static function get_last_schedule_debug() {
		return is_array( self::$last_schedule_debug ) ? self::$last_schedule_debug : array();
	}

	/**
	 * Return recent schedule attempt history for the current request.
	 *
	 * @since 1.11.2
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_schedule_debug_history() {
		return is_array( self::$schedule_debug_history ) ? self::$schedule_debug_history : array();
	}

	/**
	 * Return scheduler wrapper diagnostics for support/debugging.
	 *
	 * @since 1.11.2
	 *
	 * @return array<string, mixed>
	 */
	public static function get_wrapper_diagnostics() {
		return array(
			'wrapper_file'              => __FILE__,
			'wrapper_file_mtime'        => @filemtime( __FILE__ ),
			'core_version'              => defined( 'TPW_CORE_VERSION' ) ? TPW_CORE_VERSION : '',
			'action_scheduler_source'   => (string) self::$source,
			'action_scheduler_version'  => defined( 'ACTION_SCHEDULER_VERSION' ) ? ACTION_SCHEDULER_VERSION : '',
			'pre_filter_present'        => self::has_pre_schedule_single_filter(),
			'pre_filter_callback_count' => self::count_filter_callbacks( 'pre_as_schedule_single_action' ),
			'debug_api_available'       => true,
		);
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
	 * Internal: reduce mixed values to safe debug output.
	 *
	 * @param mixed $value Value to summarize.
	 * @return mixed
	 */
	private static function normalize_debug_value( $value ) {
		if ( is_null( $value ) || is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return array(
				'type'  => 'array',
				'json'  => wp_json_encode( $value ),
			);
		}

		if ( is_object( $value ) ) {
			return array(
				'type'  => 'object',
				'class' => get_class( $value ),
			);
		}

		return array(
			'type'  => gettype( $value ),
			'value' => (string) $value,
		);
	}

	/**
	 * Internal: whether any pre single-action filter is registered.
	 *
	 * @return bool
	 */
	private static function has_pre_schedule_single_filter() {
		return false !== self::count_filter_callbacks( 'pre_as_schedule_single_action' );
	}

	/**
	 * Internal: count callbacks attached to a filter hook.
	 *
	 * @param string $hook_name Hook name.
	 * @return int|false
	 */
	private static function count_filter_callbacks( $hook_name ) {
		global $wp_filter;

		if ( ! is_string( $hook_name ) || '' === $hook_name ) {
			return false;
		}

		if ( ! isset( $wp_filter[ $hook_name ] ) ) {
			return false;
		}

		$hook = $wp_filter[ $hook_name ];

		if ( is_object( $hook ) && isset( $hook->callbacks ) && is_array( $hook->callbacks ) ) {
			$count = 0;
			foreach ( $hook->callbacks as $callbacks ) {
				$count += is_array( $callbacks ) ? count( $callbacks ) : 0;
			}

			return $count;
		}

		if ( is_array( $hook ) ) {
			$count = 0;
			foreach ( $hook as $callbacks ) {
				$count += is_array( $callbacks ) ? count( $callbacks ) : 0;
			}

			return $count;
		}

		return false;
	}

	/**
	 * Internal: optional admin-only logging for scheduler diagnostics.
	 *
	 * @param string               $stage   Diagnostic stage.
	 * @param array<string, mixed> $context Debug context.
	 * @return void
	 */
	private static function maybe_log_schedule_event( $stage, array $context ) {
		if ( ! defined( 'TPW_CORE_SCHEDULER_DEBUG_LOG' ) || ! TPW_CORE_SCHEDULER_DEBUG_LOG ) {
			return;
		}

		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return;
		}

		if ( ! function_exists( 'error_log' ) ) {
			return;
		}

		$payload = array(
			'stage'   => (string) $stage,
			'context' => $context,
		);

		error_log( 'TPW Core Scheduler Debug: ' . wp_json_encode( $payload ) );
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
