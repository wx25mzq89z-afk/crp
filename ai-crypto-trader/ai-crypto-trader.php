<?php
/**
 * Plugin Name: AI Crypto Trader
 * Plugin URI:  https://github.com/wx25mzq89z-afk/crp
 * Description: An AI-powered wallet manager that automatically buys and sells cryptocurrencies, commodities, and forex based on market data, news analysis, and technical indicators.
 * Version:     1.0.0
 * Author:      AI Crypto Trader
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-crypto-trader
 * Domain Path: /languages
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'ACT_VERSION', '1.0.0' );
define( 'ACT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ACT_DB_VERSION', '1.0.0' );

// Include core classes.
require_once ACT_PLUGIN_DIR . 'includes/class-database.php';
require_once ACT_PLUGIN_DIR . 'includes/class-wallet.php';
require_once ACT_PLUGIN_DIR . 'includes/class-market-data.php';
require_once ACT_PLUGIN_DIR . 'includes/class-news-analyzer.php';
require_once ACT_PLUGIN_DIR . 'includes/class-risk-manager.php';
require_once ACT_PLUGIN_DIR . 'includes/class-ai-trader.php';
require_once ACT_PLUGIN_DIR . 'includes/class-trade-executor.php';
require_once ACT_PLUGIN_DIR . 'includes/class-scheduler.php';

// Include admin classes.
if ( is_admin() ) {
	require_once ACT_PLUGIN_DIR . 'admin/class-admin.php';
}

// Include frontend classes.
require_once ACT_PLUGIN_DIR . 'includes/class-shortcodes.php';

/**
 * Main plugin class.
 */
final class AI_Crypto_Trader {

	/**
	 * Single instance of the plugin.
	 *
	 * @var AI_Crypto_Trader
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return AI_Crypto_Trader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Plugin activation: create DB tables, schedule cron, set defaults.
	 */
	public function activate() {
		ACT_Database::create_tables();
		ACT_Scheduler::schedule_events();
		if ( ! get_option( 'act_settings' ) ) {
			$defaults = array(
				'openai_api_key'           => '',
				'coingecko_api_key'        => '',
				'alpha_vantage_api_key'    => '',
				'newsapi_key'              => '',
				'exchange_api_key'         => '',
				'exchange_api_secret'      => '',
				'exchange_name'            => 'paper',
				'risk_level'               => 'medium',
				'max_trade_pct'            => 10,
				'stop_loss_pct'            => 5,
				'take_profit_pct'          => 15,
				'ai_model'                 => 'gpt-4o',
				'trading_pairs'            => array( 'BTC/USDT', 'ETH/USDT', 'XRP/USDT' ),
				'trading_enabled'          => false,
				'analysis_interval'        => 'hourly',
				'initial_balance'          => 1000,
				'currency'                 => 'USDT',
				'max_open_positions'       => 5,
				'enable_notifications'     => true,
				'notification_email'       => get_option( 'admin_email' ),
			);
			update_option( 'act_settings', $defaults );
		}
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation: clear scheduled events.
	 */
	public function deactivate() {
		ACT_Scheduler::clear_events();
		flush_rewrite_rules();
	}

	/**
	 * Initialize the plugin after all plugins are loaded.
	 */
	public function init() {
		// Initialize components.
		ACT_Scheduler::init();
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ai-crypto-trader',
			false,
			dirname( ACT_PLUGIN_BASENAME ) . '/languages'
		);
	}
}

// Boot the plugin.
AI_Crypto_Trader::get_instance();
