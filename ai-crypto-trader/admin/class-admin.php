<?php
/**
 * Admin interface for AI Crypto Trader.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the WordPress admin dashboard pages and AJAX endpoints.
 */
class ACT_Admin {

	/**
	 * Constructor – registers all admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_act_run_cycle', array( $this, 'ajax_run_cycle' ) );
		add_action( 'wp_ajax_act_get_portfolio', array( $this, 'ajax_get_portfolio' ) );
		add_action( 'wp_ajax_act_reset_wallet', array( $this, 'ajax_reset_wallet' ) );
		add_action( 'wp_ajax_act_manual_trade', array( $this, 'ajax_manual_trade' ) );
		add_filter( 'plugin_action_links_' . ACT_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	// -------------------------------------------------------------------------
	// Menu & pages
	// -------------------------------------------------------------------------

	/**
	 * Register admin menu pages.
	 */
	public function add_menu_pages() {
		add_menu_page(
			__( 'AI Crypto Trader', 'ai-crypto-trader' ),
			__( 'AI Trader', 'ai-crypto-trader' ),
			'manage_options',
			'ai-crypto-trader',
			array( $this, 'page_dashboard' ),
			'dashicons-chart-line',
			56
		);

		add_submenu_page(
			'ai-crypto-trader',
			__( 'Dashboard', 'ai-crypto-trader' ),
			__( 'Dashboard', 'ai-crypto-trader' ),
			'manage_options',
			'ai-crypto-trader',
			array( $this, 'page_dashboard' )
		);

		add_submenu_page(
			'ai-crypto-trader',
			__( 'Trade History', 'ai-crypto-trader' ),
			__( 'Trade History', 'ai-crypto-trader' ),
			'manage_options',
			'ai-crypto-trader-trades',
			array( $this, 'page_trades' )
		);

		add_submenu_page(
			'ai-crypto-trader',
			__( 'AI Signals Log', 'ai-crypto-trader' ),
			__( 'AI Signals Log', 'ai-crypto-trader' ),
			'manage_options',
			'ai-crypto-trader-signals',
			array( $this, 'page_signals' )
		);

		add_submenu_page(
			'ai-crypto-trader',
			__( 'Settings', 'ai-crypto-trader' ),
			__( 'Settings', 'ai-crypto-trader' ),
			'manage_options',
			'ai-crypto-trader-settings',
			array( $this, 'page_settings' )
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin CSS and JS.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$act_pages = array(
			'toplevel_page_ai-crypto-trader',
			'ai-trader_page_ai-crypto-trader-trades',
			'ai-trader_page_ai-crypto-trader-signals',
			'ai-trader_page_ai-crypto-trader-settings',
		);

		if ( ! in_array( $hook, $act_pages, true ) ) {
			return;
		}

		// Chart.js from CDN.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_style(
			'act-admin',
			ACT_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			ACT_VERSION
		);

		wp_enqueue_script(
			'act-admin',
			ACT_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery', 'chartjs' ),
			ACT_VERSION,
			true
		);

		wp_localize_script(
			'act-admin',
			'actAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'act_admin_nonce' ),
				'strings' => array(
					'running'     => __( 'Running analysis...', 'ai-crypto-trader' ),
					'done'        => __( 'Done!', 'ai-crypto-trader' ),
					'error'       => __( 'Error occurred.', 'ai-crypto-trader' ),
					'confirm_reset' => __( 'Are you sure? This will clear all wallet data.', 'ai-crypto-trader' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Settings registration
	// -------------------------------------------------------------------------

	/**
	 * Register plugin settings with the WordPress Settings API.
	 */
	public function register_settings() {
		register_setting(
			'act_settings_group',
			'act_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize the settings array before saving.
	 *
	 * @param array $input Raw settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$clean = array();

		$text_fields = array(
			'openai_api_key', 'coingecko_api_key', 'alpha_vantage_api_key',
			'newsapi_key', 'exchange_api_key', 'exchange_api_secret',
			'exchange_name', 'risk_level', 'ai_model', 'currency',
			'analysis_interval',
		);
		foreach ( $text_fields as $field ) {
			$clean[ $field ] = isset( $input[ $field ] )
				? sanitize_text_field( $input[ $field ] )
				: '';
		}

		$float_fields = array( 'max_trade_pct', 'stop_loss_pct', 'take_profit_pct', 'initial_balance' );
		foreach ( $float_fields as $field ) {
			$clean[ $field ] = isset( $input[ $field ] ) ? floatval( $input[ $field ] ) : 0.0;
		}

		$int_fields = array( 'max_open_positions' );
		foreach ( $int_fields as $field ) {
			$clean[ $field ] = isset( $input[ $field ] ) ? absint( $input[ $field ] ) : 5;
		}

		$clean['trading_enabled']    = ! empty( $input['trading_enabled'] );
		$clean['enable_notifications'] = ! empty( $input['enable_notifications'] );

		$clean['notification_email'] = isset( $input['notification_email'] )
			? sanitize_email( $input['notification_email'] )
			: get_option( 'admin_email' );

		// Trading pairs: one per line or comma-separated.
		if ( isset( $input['trading_pairs'] ) ) {
			$raw_pairs = is_array( $input['trading_pairs'] )
				? $input['trading_pairs']
				: preg_split( '/[\r\n,]+/', $input['trading_pairs'] );
			$clean['trading_pairs'] = array_values(
				array_filter(
					array_map(
						function ( $p ) {
							return strtoupper( sanitize_text_field( trim( $p ) ) );
						},
						$raw_pairs
					)
				)
			);
		} else {
			$clean['trading_pairs'] = array( 'BTC/USDT', 'ETH/USDT' );
		}

		// Reschedule if interval changed.
		$old = (array) get_option( 'act_settings', array() );
		if ( isset( $old['analysis_interval'] ) && $old['analysis_interval'] !== $clean['analysis_interval'] ) {
			ACT_Scheduler::reschedule( $clean['analysis_interval'] );
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/**
	 * Render the dashboard page.
	 */
	public function page_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ai-crypto-trader' ) );
		}
		include ACT_PLUGIN_DIR . 'admin/partials/dashboard.php';
	}

	/**
	 * Render the trade history page.
	 */
	public function page_trades() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ai-crypto-trader' ) );
		}
		include ACT_PLUGIN_DIR . 'admin/partials/trades.php';
	}

	/**
	 * Render the AI signals log page.
	 */
	public function page_signals() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ai-crypto-trader' ) );
		}
		include ACT_PLUGIN_DIR . 'admin/partials/signals.php';
	}

	/**
	 * Render the settings page.
	 */
	public function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ai-crypto-trader' ) );
		}
		include ACT_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Manually trigger a trading cycle.
	 */
	public function ajax_run_cycle() {
		check_ajax_referer( 'act_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$results = ACT_Trade_Executor::run_trading_cycle();
		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Get current portfolio data for the dashboard chart.
	 */
	public function ajax_get_portfolio() {
		check_ajax_referer( 'act_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$snapshots = ACT_Wallet::get_snapshots( 30 );
		$holdings  = ACT_Wallet::get_holdings();
		$trades    = ACT_Wallet::get_trade_history( 5 );

		wp_send_json_success(
			array(
				'snapshots' => $snapshots,
				'holdings'  => $holdings,
				'trades'    => $trades,
				'total_pnl' => ACT_Wallet::get_total_pnl(),
			)
		);
	}

	/**
	 * AJAX: Reset paper wallet to initial state.
	 */
	public function ajax_reset_wallet() {
		check_ajax_referer( 'act_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}act_wallet" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}act_trades" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}act_portfolio_snapshots" );

		// Re-seed initial balance.
		$settings = (array) get_option( 'act_settings', array() );
		$initial  = isset( $settings['initial_balance'] ) ? floatval( $settings['initial_balance'] ) : 1000.0;
		$currency = isset( $settings['currency'] ) ? $settings['currency'] : 'USDT';
		ACT_Wallet::update_holding( $currency, $initial, 1.0 );

		wp_send_json_success( array( 'message' => 'Wallet reset successfully.' ) );
	}

	/**
	 * AJAX: Execute a manual trade.
	 */
	public function ajax_manual_trade() {
		check_ajax_referer( 'act_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$pair   = isset( $_POST['pair'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['pair'] ) ) ) : '';
		$side   = isset( $_POST['side'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['side'] ) ) ) : '';
		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0.0;

		if ( ! $pair || ! in_array( $side, array( 'buy', 'sell' ), true ) || $amount <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid trade parameters.' ) );
		}

		// Fetch current price.
		$parts   = explode( '/', $pair );
		$base    = $parts[0];
		$coin_id = ACT_Market_Data::symbol_to_coingecko_id( $base );
		$raw     = ACT_Market_Data::get_crypto_prices( array( $coin_id ) );
		$price   = isset( $raw[ $coin_id ]['price'] ) ? floatval( $raw[ $coin_id ]['price'] ) : 0.0;

		if ( $price <= 0 ) {
			wp_send_json_error( array( 'message' => 'Could not fetch price for ' . esc_html( $pair ) ) );
		}

		$signal = array(
			'signal'     => $side,
			'confidence' => 100,
			'strategy'   => 'Manual',
			'reasoning'  => 'Manually placed via admin panel.',
		);

		$trade_id = ACT_Trade_Executor::execute_trade( $pair, $side, $amount, $price, $signal );

		if ( false === $trade_id ) {
			wp_send_json_error( array( 'message' => 'Trade execution failed.' ) );
		}

		wp_send_json_success(
			array(
				'trade_id' => $trade_id,
				'message'  => sprintf( 'Manual %s of %g %s @ $%s executed.', $side, $amount, $base, number_format( $price, 2 ) ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Plugin links
	// -------------------------------------------------------------------------

	/**
	 * Add Settings link on the Plugins page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified links.
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=ai-crypto-trader-settings' ),
			__( 'Settings', 'ai-crypto-trader' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}

// Instantiate the admin class.
new ACT_Admin();
