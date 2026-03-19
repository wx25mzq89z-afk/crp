<?php
/**
 * Risk manager for AI Crypto Trader.
 *
 * Enforces position sizing, stop-loss/take-profit rules, and portfolio risk limits.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all risk management logic.
 */
class ACT_Risk_Manager {

	/**
	 * Get plugin settings.
	 *
	 * @return array Settings.
	 */
	private static function settings() {
		return (array) get_option( 'act_settings', array() );
	}

	/**
	 * Calculate the maximum trade size in the quote currency (e.g. USDT)
	 * based on the portfolio balance and configured max_trade_pct.
	 *
	 * @param float $portfolio_value_usdt Total portfolio value in USDT.
	 * @return float Maximum trade amount in USDT.
	 */
	public static function max_trade_amount( $portfolio_value_usdt ) {
		$settings    = self::settings();
		$max_pct     = isset( $settings['max_trade_pct'] ) ? floatval( $settings['max_trade_pct'] ) : 10.0;
		$max_pct     = max( 0.1, min( 100.0, $max_pct ) );
		return $portfolio_value_usdt * ( $max_pct / 100.0 );
	}

	/**
	 * Calculate position size in base currency.
	 *
	 * Uses the Kelly criterion variant capped by max_trade_pct to size positions.
	 *
	 * @param float  $available_usdt    Available USDT for trading.
	 * @param float  $price             Current asset price in USDT.
	 * @param int    $ai_confidence_pct AI confidence (0-100).
	 * @param float  $portfolio_total   Total portfolio value.
	 * @return float Recommended buy amount in base currency units.
	 */
	public static function calculate_position_size( $available_usdt, $price, $ai_confidence_pct, $portfolio_total ) {
		if ( $price <= 0 || $portfolio_total <= 0 ) {
			return 0.0;
		}

		$settings   = self::settings();
		$max_pct    = isset( $settings['max_trade_pct'] ) ? floatval( $settings['max_trade_pct'] ) : 10.0;
		$risk_level = isset( $settings['risk_level'] ) ? $settings['risk_level'] : 'medium';

		// Scale confidence 0-100 to fraction 0-1.
		$confidence = max( 0, min( 100, (int) $ai_confidence_pct ) ) / 100.0;

		// Risk multiplier based on risk_level.
		$risk_mult = array(
			'low'    => 0.5,
			'medium' => 1.0,
			'high'   => 1.5,
		);
		$mult = isset( $risk_mult[ $risk_level ] ) ? $risk_mult[ $risk_level ] : 1.0;

		// Trade fraction = confidence * risk_mult * max_pct / 100.
		$trade_fraction = $confidence * $mult * ( $max_pct / 100.0 );
		$trade_fraction = max( 0.001, min( $max_pct / 100.0, $trade_fraction ) );

		$trade_usdt  = min( $available_usdt, $portfolio_total * $trade_fraction );
		return $trade_usdt / $price;
	}

	/**
	 * Determine if stop-loss has been triggered for an open position.
	 *
	 * @param float $avg_buy_price Average buy price.
	 * @param float $current_price Current market price.
	 * @return bool True if stop-loss should be triggered.
	 */
	public static function is_stop_loss( $avg_buy_price, $current_price ) {
		if ( $avg_buy_price <= 0 ) {
			return false;
		}
		$settings      = self::settings();
		$stop_loss_pct = isset( $settings['stop_loss_pct'] ) ? floatval( $settings['stop_loss_pct'] ) : 5.0;
		$threshold     = $avg_buy_price * ( 1.0 - $stop_loss_pct / 100.0 );
		return $current_price <= $threshold;
	}

	/**
	 * Determine if take-profit has been triggered for an open position.
	 *
	 * @param float $avg_buy_price   Average buy price.
	 * @param float $current_price   Current market price.
	 * @return bool True if take-profit should be triggered.
	 */
	public static function is_take_profit( $avg_buy_price, $current_price ) {
		if ( $avg_buy_price <= 0 ) {
			return false;
		}
		$settings         = self::settings();
		$take_profit_pct  = isset( $settings['take_profit_pct'] ) ? floatval( $settings['take_profit_pct'] ) : 15.0;
		$threshold        = $avg_buy_price * ( 1.0 + $take_profit_pct / 100.0 );
		return $current_price >= $threshold;
	}

	/**
	 * Check if adding a new position would exceed the maximum open positions limit.
	 *
	 * @param int $current_open_positions Number of currently open (non-zero) positions.
	 * @return bool True if a new position can be opened.
	 */
	public static function can_open_position( $current_open_positions ) {
		$settings    = self::settings();
		$max_pos     = isset( $settings['max_open_positions'] ) ? absint( $settings['max_open_positions'] ) : 5;
		return $current_open_positions < $max_pos;
	}

	/**
	 * Count currently open (non-zero) positions excluding the quote currency.
	 *
	 * @param string $quote Quote currency (e.g. 'USDT').
	 * @return int Number of open positions.
	 */
	public static function count_open_positions( $quote = 'USDT' ) {
		$holdings = ACT_Wallet::get_holdings();
		$count    = 0;
		foreach ( $holdings as $h ) {
			if ( strtoupper( $h['asset'] ) !== strtoupper( $quote ) && floatval( $h['amount'] ) > 0 ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Assess overall portfolio risk and return a risk summary.
	 *
	 * @param array  $prices       Array of asset => current_price.
	 * @param float  $total_value  Total portfolio value in USDT.
	 * @return array Risk assessment with keys: level, concentration, drawdown_warning.
	 */
	public static function assess_portfolio_risk( array $prices, $total_value ) {
		$holdings = ACT_Wallet::get_holdings();
		$risk     = array(
			'level'               => 'low',
			'concentration'       => array(),
			'drawdown_warning'    => false,
			'positions_at_risk'   => array(),
		);

		if ( $total_value <= 0 ) {
			return $risk;
		}

		$max_concentration = 0.0;
		foreach ( $holdings as $h ) {
			$asset  = $h['asset'];
			$amount = floatval( $h['amount'] );
			if ( $amount <= 0 || 'USDT' === strtoupper( $asset ) ) {
				continue;
			}
			$current_price = isset( $prices[ $asset ] ) ? floatval( $prices[ $asset ] ) : 0.0;
			if ( $current_price <= 0 ) {
				continue;
			}
			$value         = $amount * $current_price;
			$concentration = ( $value / $total_value ) * 100.0;
			$risk['concentration'][ $asset ] = round( $concentration, 2 );

			if ( $concentration > $max_concentration ) {
				$max_concentration = $concentration;
			}

			// Check stop-loss.
			$avg = floatval( $h['avg_buy_price'] );
			if ( self::is_stop_loss( $avg, $current_price ) ) {
				$risk['positions_at_risk'][] = $asset;
			}
		}

		// Determine overall risk level based on max concentration.
		if ( $max_concentration > 60 ) {
			$risk['level'] = 'high';
		} elseif ( $max_concentration > 35 ) {
			$risk['level'] = 'medium';
		}

		// Snapshots drawdown check.
		$snapshots = ACT_Wallet::get_snapshots( 10 );
		if ( count( $snapshots ) >= 2 ) {
			$latest = floatval( $snapshots[0]['total_value_usd'] );
			$peak   = max( array_column( $snapshots, 'total_value_usd' ) );
			if ( $peak > 0 ) {
				$drawdown = ( ( $peak - $latest ) / $peak ) * 100.0;
				if ( $drawdown > 10 ) {
					$risk['drawdown_warning'] = true;
					$risk['drawdown_pct']     = round( $drawdown, 2 );
				}
			}
		}

		return $risk;
	}
}
