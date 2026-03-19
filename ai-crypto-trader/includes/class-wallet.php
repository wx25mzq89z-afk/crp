<?php
/**
 * Wallet management for AI Crypto Trader.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the plugin wallet: balances, positions, PnL.
 */
class ACT_Wallet {

	/**
	 * Get all current wallet holdings.
	 *
	 * @return array Array of holding rows.
	 */
	public static function get_holdings() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}act_wallet ORDER BY asset ASC",
			ARRAY_A
		);
	}

	/**
	 * Get holding for a specific asset.
	 *
	 * @param string $asset Asset symbol (e.g. 'BTC').
	 * @return array|null Holding row or null.
	 */
	public static function get_holding( $asset ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}act_wallet WHERE asset = %s",
				strtoupper( $asset )
			),
			ARRAY_A
		);
	}

	/**
	 * Update or insert a wallet holding.
	 *
	 * @param string $asset     Asset symbol.
	 * @param float  $amount    New amount.
	 * @param float  $avg_price New average buy price.
	 * @return bool Success.
	 */
	public static function update_holding( $asset, $amount, $avg_price = 0.0 ) {
		global $wpdb;
		$asset = strtoupper( $asset );

		$existing = self::get_holding( $asset );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				"{$wpdb->prefix}act_wallet",
				array(
					'amount'        => $amount,
					'avg_buy_price' => $avg_price,
				),
				array( 'asset' => $asset ),
				array( '%f', '%f' ),
				array( '%s' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->insert(
				"{$wpdb->prefix}act_wallet",
				array(
					'asset'         => $asset,
					'amount'        => $amount,
					'avg_buy_price' => $avg_price,
				),
				array( '%s', '%f', '%f' )
			);
		}

		return false !== $result;
	}

	/**
	 * Record a trade and update the wallet accordingly.
	 *
	 * @param array $trade {
	 *   @type string $pair          Trading pair (e.g. 'BTC/USDT').
	 *   @type string $side          'buy' or 'sell'.
	 *   @type float  $amount        Asset amount.
	 *   @type float  $price         Price per unit in quote currency.
	 *   @type float  $fee           Fee paid.
	 *   @type string $strategy      Strategy name.
	 *   @type int    $ai_confidence AI confidence 0-100.
	 *   @type string $notes         Additional notes.
	 * }
	 * @return int|false Inserted trade ID or false on error.
	 */
	public static function record_trade( array $trade ) {
		global $wpdb;

		$pair      = strtoupper( $trade['pair'] );
		$side      = strtolower( $trade['side'] );
		$amount    = floatval( $trade['amount'] );
		$price     = floatval( $trade['price'] );
		$fee       = floatval( isset( $trade['fee'] ) ? $trade['fee'] : 0 );
		$strategy  = sanitize_text_field( isset( $trade['strategy'] ) ? $trade['strategy'] : 'AI' );
		$ai_conf   = absint( isset( $trade['ai_confidence'] ) ? $trade['ai_confidence'] : 0 );
		$notes     = sanitize_textarea_field( isset( $trade['notes'] ) ? $trade['notes'] : '' );

		$parts     = explode( '/', $pair );
		$base      = $parts[0];
		$quote     = isset( $parts[1] ) ? $parts[1] : 'USDT';

		$total_value  = $amount * $price;
		$profit_loss  = 0.0;

		// Update wallet balances.
		if ( 'buy' === $side ) {
			// Deduct quote currency, add base currency.
			$quote_holding = self::get_holding( $quote );
			$quote_balance = $quote_holding ? floatval( $quote_holding['amount'] ) : 0.0;
			$cost          = $total_value + $fee;
			self::update_holding( $quote, max( 0, $quote_balance - $cost ) );

			$base_holding  = self::get_holding( $base );
			$base_amount   = $base_holding ? floatval( $base_holding['amount'] ) : 0.0;
			$base_avg      = $base_holding ? floatval( $base_holding['avg_buy_price'] ) : 0.0;
			$new_amount    = $base_amount + $amount;
			$new_avg       = $new_amount > 0
				? ( ( $base_amount * $base_avg ) + ( $amount * $price ) ) / $new_amount
				: $price;
			self::update_holding( $base, $new_amount, $new_avg );

		} elseif ( 'sell' === $side ) {
			// Deduct base currency, add quote currency.
			$base_holding  = self::get_holding( $base );
			$base_amount   = $base_holding ? floatval( $base_holding['amount'] ) : 0.0;
			$base_avg      = $base_holding ? floatval( $base_holding['avg_buy_price'] ) : 0.0;
			$profit_loss   = ( $price - $base_avg ) * $amount - $fee;
			self::update_holding( $base, max( 0, $base_amount - $amount ), $base_avg );

			$quote_holding = self::get_holding( $quote );
			$quote_balance = $quote_holding ? floatval( $quote_holding['amount'] ) : 0.0;
			self::update_holding( $quote, $quote_balance + $total_value - $fee );
		}

		// Insert trade record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			"{$wpdb->prefix}act_trades",
			array(
				'trade_time'    => current_time( 'mysql' ),
				'pair'          => $pair,
				'side'          => $side,
				'amount'        => $amount,
				'price'         => $price,
				'total_value'   => $total_value,
				'fee'           => $fee,
				'profit_loss'   => $profit_loss,
				'strategy'      => $strategy,
				'ai_confidence' => $ai_conf,
				'status'        => 'executed',
				'notes'         => $notes,
			),
			array( '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get trade history.
	 *
	 * @param int $limit  Number of records.
	 * @param int $offset Offset for pagination.
	 * @return array Array of trade rows.
	 */
	public static function get_trade_history( $limit = 50, $offset = 0 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}act_trades ORDER BY trade_time DESC LIMIT %d OFFSET %d",
				absint( $limit ),
				absint( $offset )
			),
			ARRAY_A
		);
	}

	/**
	 * Get portfolio total value in USD/USDT by combining all asset balances.
	 *
	 * @param array $prices Associative array of asset => price_in_usdt.
	 * @return float Total portfolio value.
	 */
	public static function get_total_value( array $prices ) {
		$holdings = self::get_holdings();
		$total    = 0.0;
		foreach ( $holdings as $holding ) {
			$asset  = $holding['asset'];
			$amount = floatval( $holding['amount'] );
			if ( 'USDT' === $asset || 'USD' === $asset ) {
				$total += $amount;
			} elseif ( isset( $prices[ $asset ] ) ) {
				$total += $amount * floatval( $prices[ $asset ] );
			}
		}
		return $total;
	}

	/**
	 * Save a portfolio snapshot to the database.
	 *
	 * @param float $total_value_usd Total portfolio value in USD.
	 * @param array $holdings        Current holdings array.
	 */
	public static function save_snapshot( $total_value_usd, array $holdings ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			"{$wpdb->prefix}act_portfolio_snapshots",
			array(
				'snapshot_time'   => current_time( 'mysql' ),
				'total_value_usd' => $total_value_usd,
				'holdings'        => wp_json_encode( $holdings ),
			),
			array( '%s', '%f', '%s' )
		);
	}

	/**
	 * Get portfolio snapshots for charting.
	 *
	 * @param int $limit Number of snapshots.
	 * @return array Snapshots.
	 */
	public static function get_snapshots( $limit = 30 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT snapshot_time, total_value_usd FROM {$wpdb->prefix}act_portfolio_snapshots ORDER BY snapshot_time DESC LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);
	}

	/**
	 * Calculate total profit/loss from trade history.
	 *
	 * @return float Total PnL.
	 */
	public static function get_total_pnl() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->get_var(
			"SELECT SUM(profit_loss) FROM {$wpdb->prefix}act_trades WHERE side = 'sell'"
		);
		return floatval( $result );
	}
}
