<?php
/**
 * Trade executor for AI Crypto Trader.
 *
 * Orchestrates the full analysis-to-trade cycle:
 *  1. Fetch market data + news.
 *  2. Run technical indicator calculations.
 *  3. Call the AI engine for a signal.
 *  4. Apply risk management.
 *  5. Execute the trade (paper or live via CCXT-compatible REST bridge).
 *  6. Notify admin.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes trades based on AI signals.
 */
class ACT_Trade_Executor {

	/**
	 * Get plugin settings.
	 *
	 * @return array Settings.
	 */
	private static function settings() {
		return (array) get_option( 'act_settings', array() );
	}

	/**
	 * Run the full trading cycle for all configured pairs.
	 *
	 * Called by the cron scheduler.
	 *
	 * @return array Results summary indexed by pair.
	 */
	public static function run_trading_cycle() {
		$settings = self::settings();

		if ( empty( $settings['trading_enabled'] ) ) {
			return array( 'skipped' => 'Trading is disabled.' );
		}

		$pairs   = isset( $settings['trading_pairs'] ) ? (array) $settings['trading_pairs'] : array();
		$results = array();

		foreach ( $pairs as $pair ) {
			$result = self::analyse_and_trade( sanitize_text_field( $pair ) );
			$results[ $pair ] = $result;
		}

		// Save portfolio snapshot.
		$prices = self::get_current_prices( $pairs );
		$total  = ACT_Wallet::get_total_value( self::flatten_prices( $prices ) );
		ACT_Wallet::save_snapshot( $total, ACT_Wallet::get_holdings() );

		return $results;
	}

	/**
	 * Analyse a single trading pair and execute trade if warranted.
	 *
	 * @param string $pair Trading pair string (e.g. 'BTC/USDT').
	 * @return array Result array with keys: signal, action, reason.
	 */
	public static function analyse_and_trade( $pair ) {
		$pair   = strtoupper( $pair );
		$parts  = explode( '/', $pair );
		$base   = $parts[0];
		$quote  = isset( $parts[1] ) ? $parts[1] : 'USDT';

		// -------------------------------------------------------------------
		// 1. Fetch market data.
		// -------------------------------------------------------------------
		$coin_id    = ACT_Market_Data::symbol_to_coingecko_id( $base );
		$raw_prices = ACT_Market_Data::get_crypto_prices( array( $coin_id ) );
		$price_data = isset( $raw_prices[ $coin_id ] ) ? $raw_prices[ $coin_id ] : array();
		$price      = isset( $price_data['price'] ) ? floatval( $price_data['price'] ) : 0.0;

		if ( $price <= 0 ) {
			return array(
				'signal' => 'hold',
				'action' => 'skip',
				'reason' => 'Could not fetch price data for ' . $pair,
			);
		}

		// OHLCV for technical indicators (14 days).
		$ohlcv   = ACT_Market_Data::get_crypto_ohlcv( $coin_id, 'usd', 30 );
		$closes  = array_column( $ohlcv, 4 ); // index 4 = close price.
		$closes  = array_map( 'floatval', $closes );

		$rsi    = ACT_Market_Data::compute_rsi( $closes, 14 );
		$sma_20 = ACT_Market_Data::compute_sma( $closes, 20 );
		$sma_50 = ACT_Market_Data::compute_sma( $closes, 50 );

		// Volume trend (compare last 5 vs previous 5 if enough data).
		$volume_trend = 'neutral';
		$volumes = array_column( $ohlcv, 5 );
		if ( count( $volumes ) >= 10 ) {
			$recent   = array_sum( array_slice( $volumes, -5 ) );
			$previous = array_sum( array_slice( $volumes, -10, 5 ) );
			if ( $previous > 0 ) {
				$vol_change = ( $recent - $previous ) / $previous;
				if ( $vol_change > 0.1 ) {
					$volume_trend = 'increasing';
				} elseif ( $vol_change < -0.1 ) {
					$volume_trend = 'decreasing';
				}
			}
		}

		// Global market data.
		$global      = ACT_Market_Data::get_global_market_data();
		$btc_dom     = isset( $global['market_cap_percentage']['btc'] )
			? round( floatval( $global['market_cap_percentage']['btc'] ), 2 )
			: null;

		// -------------------------------------------------------------------
		// 2. Fetch news and compute sentiment.
		// -------------------------------------------------------------------
		$news_context = ACT_News_Analyzer::build_news_context( $base, 5 );
		$news_sent    = ACT_News_Analyzer::simple_sentiment( $news_context );

		// -------------------------------------------------------------------
		// 3. Build market_data array for AI.
		// -------------------------------------------------------------------
		$holding     = ACT_Wallet::get_holding( $base );
		$market_data = array(
			'price'           => $price,
			'change_24h'      => isset( $price_data['change_24h'] ) ? $price_data['change_24h'] : 0,
			'market_cap'      => isset( $price_data['market_cap'] ) ? $price_data['market_cap'] : 0,
			'rsi'             => $rsi,
			'sma_20'          => $sma_20,
			'sma_50'          => $sma_50,
			'volume_trend'    => $volume_trend,
			'macd_signal'     => null, // Not computed in free tier.
			'btc_dominance'   => $btc_dom,
			'news_sentiment'  => $news_sent,
			'holding_amount'  => $holding ? floatval( $holding['amount'] ) : 0,
			'avg_buy_price'   => $holding ? floatval( $holding['avg_buy_price'] ) : 0,
		);

		// -------------------------------------------------------------------
		// 4. Get AI signal.
		// -------------------------------------------------------------------
		$signal = ACT_AI_Trader::analyse( $pair, $market_data, $news_context );
		ACT_AI_Trader::log_analysis( $base, $signal, $price, $news_context, $market_data );

		// -------------------------------------------------------------------
		// 5. Risk management checks.
		// -------------------------------------------------------------------
		$quote_holding  = ACT_Wallet::get_holding( $quote );
		$available_quote = $quote_holding ? floatval( $quote_holding['amount'] ) : 0.0;
		$prices_map      = array( $base => $price );
		if ( $quote_holding ) {
			$prices_map[ $quote ] = 1.0;
		}
		$total_value    = ACT_Wallet::get_total_value( $prices_map );
		$risk            = ACT_Risk_Manager::assess_portfolio_risk( $prices_map, $total_value );

		// Override sell signal from risk manager (stop-loss / take-profit).
		if ( $holding && floatval( $holding['amount'] ) > 0 ) {
			$avg    = floatval( $holding['avg_buy_price'] );
			if ( ACT_Risk_Manager::is_stop_loss( $avg, $price ) ) {
				$signal['signal']     = 'sell';
				$signal['confidence'] = 90;
				$signal['strategy']   = 'Stop-Loss';
				$signal['reasoning']  = sprintf(
					'Price %f fell below stop-loss threshold (avg buy %f, stop %s%%).',
					$price,
					$avg,
					(string) ( isset( self::settings()['stop_loss_pct'] ) ? self::settings()['stop_loss_pct'] : 5 )
				);
			} elseif ( ACT_Risk_Manager::is_take_profit( $avg, $price ) ) {
				$signal['signal']     = 'sell';
				$signal['confidence'] = 85;
				$signal['strategy']   = 'Take-Profit';
				$signal['reasoning']  = sprintf(
					'Price %f hit take-profit target (avg buy %f, target %s%%).',
					$price,
					$avg,
					(string) ( isset( self::settings()['take_profit_pct'] ) ? self::settings()['take_profit_pct'] : 15 )
				);
			}
		}

		// -------------------------------------------------------------------
		// 6. Execute trade.
		// -------------------------------------------------------------------
		$action = 'hold';
		$trade_result = null;

		if ( 'buy' === $signal['signal'] ) {
			if ( ACT_Risk_Manager::can_open_position( ACT_Risk_Manager::count_open_positions( $quote ) ) ) {
				$amount = ACT_Risk_Manager::calculate_position_size(
					$available_quote,
					$price,
					$signal['confidence'],
					$total_value
				);
				if ( $amount > 0 ) {
					$trade_result = self::execute_trade( $pair, 'buy', $amount, $price, $signal );
					$action       = 'buy';
				}
			} else {
				$action = 'skip_max_positions';
			}
		} elseif ( 'sell' === $signal['signal'] ) {
			$current_amount = $holding ? floatval( $holding['amount'] ) : 0.0;
			if ( $current_amount > 0 ) {
				$trade_result = self::execute_trade( $pair, 'sell', $current_amount, $price, $signal );
				$action       = 'sell';
			} else {
				$action = 'skip_no_holding';
			}
		}

		// -------------------------------------------------------------------
		// 7. Notify.
		// -------------------------------------------------------------------
		if ( 'hold' !== $action && null !== $trade_result ) {
			self::notify_admin( $pair, $signal, $action, $price, $trade_result );
		}

		return array(
			'pair'     => $pair,
			'signal'   => $signal['signal'],
			'action'   => $action,
			'reason'   => $signal['reasoning'],
			'price'    => $price,
			'strategy' => $signal['strategy'],
		);
	}

	/**
	 * Execute a trade (paper or live).
	 *
	 * For paper trading, directly updates the wallet database.
	 * For live trading, calls the configured exchange REST API.
	 *
	 * @param string $pair   Trading pair.
	 * @param string $side   'buy' or 'sell'.
	 * @param float  $amount Amount of base currency.
	 * @param float  $price  Current price.
	 * @param array  $signal Signal data.
	 * @return int|false Trade ID or false on error.
	 */
	public static function execute_trade( $pair, $side, $amount, $price, array $signal ) {
		$settings      = self::settings();
		$exchange_name = isset( $settings['exchange_name'] ) ? $settings['exchange_name'] : 'paper';

		$trade = array(
			'pair'          => $pair,
			'side'          => $side,
			'amount'        => $amount,
			'price'         => $price,
			'fee'           => $amount * $price * 0.001, // Default 0.1% fee.
			'strategy'      => $signal['strategy'],
			'ai_confidence' => $signal['confidence'],
			'notes'         => $signal['reasoning'],
		);

		if ( 'paper' === $exchange_name ) {
			return ACT_Wallet::record_trade( $trade );
		}

		// Live exchange execution via REST API.
		$result = self::call_exchange_api( $exchange_name, $trade, $settings );
		if ( is_wp_error( $result ) ) {
			// Log error but don't crash.
			error_log( '[AI Crypto Trader] Exchange API error: ' . $result->get_error_message() );
			return false;
		}

		// Record the confirmed trade.
		if ( isset( $result['filled_price'] ) ) {
			$trade['price'] = $result['filled_price'];
		}
		if ( isset( $result['fee'] ) ) {
			$trade['fee'] = $result['fee'];
		}
		return ACT_Wallet::record_trade( $trade );
	}

	/**
	 * Call a live exchange REST API to place an order.
	 *
	 * Supports basic order placement for exchanges with a compatible REST API.
	 * Currently handles Binance-compatible API format.
	 *
	 * @param string $exchange_name Exchange identifier.
	 * @param array  $trade         Trade parameters.
	 * @param array  $settings      Plugin settings.
	 * @return array|WP_Error Order result or WP_Error.
	 */
	private static function call_exchange_api( $exchange_name, array $trade, array $settings ) {
		$api_key    = isset( $settings['exchange_api_key'] ) ? $settings['exchange_api_key'] : '';
		$api_secret = isset( $settings['exchange_api_secret'] ) ? $settings['exchange_api_secret'] : '';

		if ( ! $api_key || ! $api_secret ) {
			return new WP_Error( 'no_credentials', 'Exchange API credentials not configured.' );
		}

		// Determine the REST endpoint based on exchange.
		$endpoints = array(
			'binance'  => 'https://api.binance.com/api/v3/order',
			'coinbase' => 'https://api.coinbase.com/api/v3/brokerage/orders',
			'kraken'   => 'https://api.kraken.com/0/private/AddOrder',
		);

		if ( ! isset( $endpoints[ strtolower( $exchange_name ) ] ) ) {
			return new WP_Error( 'unsupported_exchange', 'Exchange not supported for live trading.' );
		}

		$symbol    = str_replace( '/', '', $trade['pair'] );
		$timestamp = round( microtime( true ) * 1000 );
		$params    = array(
			'symbol'    => $symbol,
			'side'      => strtoupper( $trade['side'] ),
			'type'      => 'MARKET',
			'quantity'  => round( $trade['amount'], 6 ),
			'timestamp' => $timestamp,
		);

		$query_string = http_build_query( $params );
		$signature    = hash_hmac( 'sha256', $query_string, $api_secret );
		$params['signature'] = $signature;

		$response = wp_remote_post(
			$endpoints[ strtolower( $exchange_name ) ],
			array(
				'timeout' => 30,
				'headers' => array(
					'X-MBX-APIKEY' => $api_key,
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body' => $params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $body['msg'] ) ? $body['msg'] : 'Unknown exchange error';
			return new WP_Error( 'exchange_error', $msg );
		}

		return array(
			'order_id'     => isset( $body['orderId'] ) ? $body['orderId'] : '',
			'filled_price' => isset( $body['fills'][0]['price'] ) ? floatval( $body['fills'][0]['price'] ) : $trade['price'],
			'fee'          => isset( $body['fills'][0]['commission'] ) ? floatval( $body['fills'][0]['commission'] ) : $trade['fee'],
			'status'       => 'executed',
		);
	}

	/**
	 * Fetch current prices for multiple pairs.
	 *
	 * @param array $pairs Array of pair strings.
	 * @return array Keyed by CoinGecko coin ID.
	 */
	private static function get_current_prices( array $pairs ) {
		$coin_ids = array();
		foreach ( $pairs as $pair ) {
			$parts    = explode( '/', $pair );
			$base     = $parts[0];
			$coin_ids[] = ACT_Market_Data::symbol_to_coingecko_id( $base );
		}
		return ACT_Market_Data::get_crypto_prices( array_unique( $coin_ids ) );
	}

	/**
	 * Flatten the nested CoinGecko price response to a simple asset => price map.
	 *
	 * @param array $coingecko_prices CoinGecko price response.
	 * @return array Flat array.
	 */
	private static function flatten_prices( array $coingecko_prices ) {
		$flat = array();
		$reverse_map = array(
			'bitcoin'      => 'BTC',
			'ethereum'     => 'ETH',
			'ripple'       => 'XRP',
			'cardano'      => 'ADA',
			'solana'       => 'SOL',
			'dogecoin'     => 'DOGE',
			'litecoin'     => 'LTC',
			'chainlink'    => 'LINK',
			'polkadot'     => 'DOT',
			'avalanche-2'  => 'AVAX',
		);
		foreach ( $coingecko_prices as $coin_id => $data ) {
			$symbol          = isset( $reverse_map[ $coin_id ] ) ? $reverse_map[ $coin_id ] : strtoupper( $coin_id );
			$flat[ $symbol ] = isset( $data['price'] ) ? $data['price'] : 0;
		}
		return $flat;
	}

	/**
	 * Send an email notification to the admin about a completed trade.
	 *
	 * @param string $pair        Trading pair.
	 * @param array  $signal      Signal data.
	 * @param string $action      Action taken.
	 * @param float  $price       Execution price.
	 * @param int    $trade_id    Trade database ID.
	 */
	private static function notify_admin( $pair, array $signal, $action, $price, $trade_id ) {
		$settings = self::settings();
		if ( empty( $settings['enable_notifications'] ) ) {
			return;
		}

		$email   = isset( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
		$subject = sprintf(
			'[AI Crypto Trader] %s %s @ %s',
			strtoupper( $action ),
			$pair,
			number_format( $price, 2 )
		);

		$message = sprintf(
			"A %s order was placed for %s.\n\nPrice: %s\nStrategy: %s\nConfidence: %d%%\nReasoning: %s\n\nTrade ID: #%d\nTime: %s\n\nLog in to view more: %s",
			strtoupper( $action ),
			$pair,
			number_format( $price, 2 ),
			$signal['strategy'],
			$signal['confidence'],
			$signal['reasoning'],
			absint( $trade_id ),
			current_time( 'mysql' ),
			admin_url( 'admin.php?page=ai-crypto-trader' )
		);

		wp_mail( $email, $subject, $message );
	}
}
