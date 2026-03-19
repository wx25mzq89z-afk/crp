<?php
/**
 * Cron scheduler for AI Crypto Trader.
 *
 * Registers WordPress cron events for:
 *  - Periodic AI trading analysis
 *  - Daily portfolio snapshots
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages scheduled WordPress cron events.
 */
class ACT_Scheduler {

	/**
	 * Cron hook for the trading cycle.
	 */
	const TRADING_HOOK = 'act_trading_cycle';

	/**
	 * Cron hook for the daily snapshot.
	 */
	const SNAPSHOT_HOOK = 'act_daily_snapshot';

	/**
	 * Register custom cron intervals and bind action hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_intervals' ) );
		add_action( self::TRADING_HOOK, array( 'ACT_Trade_Executor', 'run_trading_cycle' ) );
		add_action( self::SNAPSHOT_HOOK, array( __CLASS__, 'daily_snapshot' ) );
	}

	/**
	 * Add custom cron recurrence intervals.
	 *
	 * @param array $schedules Existing WP cron schedules.
	 * @return array Modified schedules.
	 */
	public static function add_cron_intervals( $schedules ) {
		$schedules['act_every_15_min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'ai-crypto-trader' ),
		);
		$schedules['act_every_30_min'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes', 'ai-crypto-trader' ),
		);
		$schedules['act_every_4_hours'] = array(
			'interval' => 4 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 4 hours', 'ai-crypto-trader' ),
		);
		return $schedules;
	}

	/**
	 * Schedule trading cycle and snapshot events on plugin activation.
	 */
	public static function schedule_events() {
		$settings = (array) get_option( 'act_settings', array() );
		$interval = isset( $settings['analysis_interval'] ) ? $settings['analysis_interval'] : 'hourly';
		$interval = self::map_interval( $interval );

		if ( ! wp_next_scheduled( self::TRADING_HOOK ) ) {
			wp_schedule_event( time(), $interval, self::TRADING_HOOK );
		}
		if ( ! wp_next_scheduled( self::SNAPSHOT_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::SNAPSHOT_HOOK );
		}
	}

	/**
	 * Clear all scheduled events on plugin deactivation.
	 */
	public static function clear_events() {
		$timestamp = wp_next_scheduled( self::TRADING_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::TRADING_HOOK );
		}
		$timestamp = wp_next_scheduled( self::SNAPSHOT_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::SNAPSHOT_HOOK );
		}
	}

	/**
	 * Reschedule the trading cycle with a new interval.
	 *
	 * @param string $interval Interval key ('15min', 'hourly', '4hours', 'daily').
	 */
	public static function reschedule( $interval ) {
		self::clear_events();
		$wp_interval = self::map_interval( $interval );
		wp_schedule_event( time(), $wp_interval, self::TRADING_HOOK );
		wp_schedule_event( time(), 'daily', self::SNAPSHOT_HOOK );
	}

	/**
	 * Map a human-readable interval name to a WP cron recurrence string.
	 *
	 * @param string $interval Interval key.
	 * @return string WP cron schedule key.
	 */
	private static function map_interval( $interval ) {
		$map = array(
			'15min'  => 'act_every_15_min',
			'30min'  => 'act_every_30_min',
			'hourly' => 'hourly',
			'4hours' => 'act_every_4_hours',
			'daily'  => 'daily',
		);
		return isset( $map[ $interval ] ) ? $map[ $interval ] : 'hourly';
	}

	/**
	 * Daily snapshot callback: saves current portfolio value.
	 */
	public static function daily_snapshot() {
		$settings = (array) get_option( 'act_settings', array() );
		$pairs    = isset( $settings['trading_pairs'] ) ? (array) $settings['trading_pairs'] : array();

		$prices   = array();
		foreach ( $pairs as $pair ) {
			$parts    = explode( '/', $pair );
			$base     = strtoupper( $parts[0] );
			$coin_id  = ACT_Market_Data::symbol_to_coingecko_id( $base );
			$raw      = ACT_Market_Data::get_crypto_prices( array( $coin_id ) );
			if ( isset( $raw[ $coin_id ]['price'] ) ) {
				$prices[ $base ] = $raw[ $coin_id ]['price'];
			}
		}

		$total = ACT_Wallet::get_total_value( $prices );
		ACT_Wallet::save_snapshot( $total, ACT_Wallet::get_holdings() );
	}
}
