<?php
/**
 * Shortcodes for AI Crypto Trader.
 *
 * Provides the following shortcodes:
 *  [act_portfolio]     – Display current portfolio holdings and value.
 *  [act_trade_history] – Display recent trade history.
 *  [act_signals]       – Display latest AI trading signals.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles frontend shortcodes.
 */
class ACT_Shortcodes {

	/**
	 * Register all shortcodes.
	 */
	public static function register() {
		add_shortcode( 'act_portfolio', array( __CLASS__, 'portfolio_shortcode' ) );
		add_shortcode( 'act_trade_history', array( __CLASS__, 'trade_history_shortcode' ) );
		add_shortcode( 'act_signals', array( __CLASS__, 'signals_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend CSS.
	 */
	public static function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && (
			has_shortcode( $post->post_content, 'act_portfolio' ) ||
			has_shortcode( $post->post_content, 'act_trade_history' ) ||
			has_shortcode( $post->post_content, 'act_signals' )
		) ) {
			wp_enqueue_style(
				'act-frontend',
				ACT_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				ACT_VERSION
			);
		}
	}

	/**
	 * [act_portfolio] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function portfolio_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_chart' => 'yes',
				'limit'      => 30,
			),
			$atts,
			'act_portfolio'
		);

		ob_start();
		include ACT_PLUGIN_DIR . 'templates/portfolio.php';
		return ob_get_clean();
	}

	/**
	 * [act_trade_history] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function trade_history_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 20,
			),
			$atts,
			'act_trade_history'
		);

		ob_start();
		include ACT_PLUGIN_DIR . 'templates/trade-history.php';
		return ob_get_clean();
	}

	/**
	 * [act_signals] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function signals_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 10,
			),
			$atts,
			'act_signals'
		);

		ob_start();
		include ACT_PLUGIN_DIR . 'templates/signals.php';
		return ob_get_clean();
	}
}

// Register shortcodes on init.
add_action( 'init', array( 'ACT_Shortcodes', 'register' ) );
