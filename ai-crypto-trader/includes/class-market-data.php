<?php
/**
 * Market data fetching for AI Crypto Trader.
 *
 * Integrates with CoinGecko (crypto), Alpha Vantage (stocks/forex/commodities),
 * and provides a unified interface for price and OHLCV data.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches market data from multiple sources.
 */
class ACT_Market_Data {

	/**
	 * CoinGecko public API base URL.
	 */
	const COINGECKO_BASE = 'https://api.coingecko.com/api/v3';

	/**
	 * Alpha Vantage API base URL.
	 */
	const ALPHAVANTAGE_BASE = 'https://www.alphavantage.co/query';

	/**
	 * Get plugin settings.
	 *
	 * @return array Settings.
	 */
	private static function settings() {
		return (array) get_option( 'act_settings', array() );
	}

	// -------------------------------------------------------------------------
	// Crypto data via CoinGecko
	// -------------------------------------------------------------------------

	/**
	 * Get current prices for a list of crypto coin IDs in a given currency.
	 *
	 * @param array  $coin_ids Array of CoinGecko coin IDs (e.g. ['bitcoin','ethereum']).
	 * @param string $vs_currency Quote currency (default: 'usd').
	 * @return array Associative array coin_id => price.
	 */
	public static function get_crypto_prices( array $coin_ids, $vs_currency = 'usd' ) {
		$settings  = self::settings();
		$api_key   = isset( $settings['coingecko_api_key'] ) ? $settings['coingecko_api_key'] : '';
		$ids       = implode( ',', array_map( 'sanitize_text_field', $coin_ids ) );
		$currency  = sanitize_text_field( $vs_currency );

		$url  = self::COINGECKO_BASE . '/simple/price';
		$url .= '?ids=' . rawurlencode( $ids );
		$url .= '&vs_currencies=' . rawurlencode( $currency );
		$url .= '&include_24hr_change=true&include_market_cap=true';
		if ( $api_key ) {
			$url .= '&x_cg_pro_api_key=' . rawurlencode( $api_key );
		}

		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! is_array( $data ) ) {
			return array();
		}

		$prices = array();
		foreach ( $data as $coin_id => $values ) {
			$prices[ $coin_id ] = array(
				'price'        => isset( $values[ $currency ] ) ? floatval( $values[ $currency ] ) : 0.0,
				'change_24h'   => isset( $values[ $currency . '_24h_change' ] ) ? floatval( $values[ $currency . '_24h_change' ] ) : 0.0,
				'market_cap'   => isset( $values[ $currency . '_market_cap' ] ) ? floatval( $values[ $currency . '_market_cap' ] ) : 0.0,
			);
		}
		return $prices;
	}

	/**
	 * Get OHLCV data for a crypto coin.
	 *
	 * @param string $coin_id    CoinGecko coin ID.
	 * @param string $vs_currency Quote currency.
	 * @param int    $days       Number of days of history (1, 7, 14, 30, 90, 180, 365).
	 * @return array Array of [timestamp, open, high, low, close, volume] entries.
	 */
	public static function get_crypto_ohlcv( $coin_id, $vs_currency = 'usd', $days = 14 ) {
		$settings  = self::settings();
		$api_key   = isset( $settings['coingecko_api_key'] ) ? $settings['coingecko_api_key'] : '';
		$coin_id   = sanitize_text_field( $coin_id );
		$currency  = sanitize_text_field( $vs_currency );
		$days      = absint( $days );

		$url  = self::COINGECKO_BASE . "/coins/{$coin_id}/ohlc";
		$url .= "?vs_currency={$currency}&days={$days}";
		if ( $api_key ) {
			$url .= '&x_cg_pro_api_key=' . rawurlencode( $api_key );
		}

		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! is_array( $data ) ) {
			return array();
		}
		return $data; // [[timestamp, open, high, low, close], ...]
	}

	/**
	 * Get trending coins from CoinGecko.
	 *
	 * @return array Array of trending coin data.
	 */
	public static function get_trending_coins() {
		$settings = self::settings();
		$api_key  = isset( $settings['coingecko_api_key'] ) ? $settings['coingecko_api_key'] : '';
		$url      = self::COINGECKO_BASE . '/search/trending';
		if ( $api_key ) {
			$url .= '?x_cg_pro_api_key=' . rawurlencode( $api_key );
		}

		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! isset( $data['coins'] ) ) {
			return array();
		}
		return $data['coins'];
	}

	/**
	 * Get global crypto market data.
	 *
	 * @return array Market data array.
	 */
	public static function get_global_market_data() {
		$url  = self::COINGECKO_BASE . '/global';
		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! isset( $data['data'] ) ) {
			return array();
		}
		return $data['data'];
	}

	// -------------------------------------------------------------------------
	// Forex / Stocks / Commodities via Alpha Vantage
	// -------------------------------------------------------------------------

	/**
	 * Get Forex exchange rate.
	 *
	 * @param string $from_currency Base currency (e.g. 'EUR').
	 * @param string $to_currency   Quote currency (e.g. 'USD').
	 * @return array|null Rate data or null on error.
	 */
	public static function get_forex_rate( $from_currency, $to_currency = 'USD' ) {
		$settings  = self::settings();
		$api_key   = isset( $settings['alpha_vantage_api_key'] ) ? $settings['alpha_vantage_api_key'] : 'demo';

		$url = add_query_arg(
			array(
				'function'     => 'CURRENCY_EXCHANGE_RATE',
				'from_currency' => strtoupper( $from_currency ),
				'to_currency'   => strtoupper( $to_currency ),
				'apikey'        => $api_key,
			),
			self::ALPHAVANTAGE_BASE
		);

		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! isset( $data['Realtime Currency Exchange Rate'] ) ) {
			return null;
		}
		$rate = $data['Realtime Currency Exchange Rate'];
		return array(
			'from'  => $rate['1. From_Currency Code'],
			'to'    => $rate['3. To_Currency Code'],
			'rate'  => floatval( $rate['5. Exchange Rate'] ),
			'time'  => $rate['6. Last Refreshed'],
		);
	}

	/**
	 * Get daily time series for a stock symbol.
	 *
	 * @param string $symbol Stock symbol (e.g. 'AAPL').
	 * @param string $outputsize 'compact' (last 100 data points) or 'full'.
	 * @return array OHLCV time series.
	 */
	public static function get_stock_daily( $symbol, $outputsize = 'compact' ) {
		$settings = self::settings();
		$api_key  = isset( $settings['alpha_vantage_api_key'] ) ? $settings['alpha_vantage_api_key'] : 'demo';

		$url = add_query_arg(
			array(
				'function'   => 'TIME_SERIES_DAILY',
				'symbol'     => strtoupper( $symbol ),
				'outputsize' => $outputsize,
				'apikey'     => $api_key,
			),
			self::ALPHAVANTAGE_BASE
		);

		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! isset( $data['Time Series (Daily)'] ) ) {
			return array();
		}
		return $data['Time Series (Daily)'];
	}

	/**
	 * Get commodity price (e.g. WTI oil, natural gas, copper, wheat).
	 *
	 * @param string $commodity Commodity function name (e.g. 'WTI', 'BRENT', 'NATURAL_GAS', 'COPPER', 'WHEAT').
	 * @param string $interval  'monthly' | 'quarterly' | 'annual' | 'daily' | 'weekly'.
	 * @return array Commodity price data.
	 */
	public static function get_commodity( $commodity, $interval = 'monthly' ) {
		$settings = self::settings();
		$api_key  = isset( $settings['alpha_vantage_api_key'] ) ? $settings['alpha_vantage_api_key'] : 'demo';

		$url = add_query_arg(
			array(
				'function' => strtoupper( $commodity ),
				'interval' => $interval,
				'apikey'   => $api_key,
			),
			self::ALPHAVANTAGE_BASE
		);

		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! isset( $data['data'] ) ) {
			return array();
		}
		return $data['data'];
	}

	/**
	 * Get RSI indicator for a symbol.
	 *
	 * @param string $symbol     Ticker symbol.
	 * @param string $interval   Time interval ('daily','weekly','monthly').
	 * @param int    $time_period RSI period.
	 * @param string $series_type Price series type ('close','open','high','low').
	 * @return array RSI data series.
	 */
	public static function get_rsi( $symbol, $interval = 'daily', $time_period = 14, $series_type = 'close' ) {
		$settings = self::settings();
		$api_key  = isset( $settings['alpha_vantage_api_key'] ) ? $settings['alpha_vantage_api_key'] : 'demo';

		$url = add_query_arg(
			array(
				'function'    => 'RSI',
				'symbol'      => strtoupper( $symbol ),
				'interval'    => $interval,
				'time_period' => absint( $time_period ),
				'series_type' => $series_type,
				'apikey'      => $api_key,
			),
			self::ALPHAVANTAGE_BASE
		);

		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! isset( $data['Technical Analysis: RSI'] ) ) {
			return array();
		}
		return $data['Technical Analysis: RSI'];
	}

	/**
	 * Get MACD indicator for a symbol.
	 *
	 * @param string $symbol      Ticker symbol.
	 * @param string $interval    Time interval.
	 * @param string $series_type Price series type.
	 * @return array MACD data series.
	 */
	public static function get_macd( $symbol, $interval = 'daily', $series_type = 'close' ) {
		$settings = self::settings();
		$api_key  = isset( $settings['alpha_vantage_api_key'] ) ? $settings['alpha_vantage_api_key'] : 'demo';

		$url = add_query_arg(
			array(
				'function'       => 'MACD',
				'symbol'         => strtoupper( $symbol ),
				'interval'       => $interval,
				'series_type'    => $series_type,
				'fastperiod'     => 12,
				'slowperiod'     => 26,
				'signalperiod'   => 9,
				'apikey'         => $api_key,
			),
			self::ALPHAVANTAGE_BASE
		);

		$data = self::remote_get( $url );
		if ( is_wp_error( $data ) || ! isset( $data['Technical Analysis: MACD'] ) ) {
			return array();
		}
		return $data['Technical Analysis: MACD'];
	}

	/**
	 * Compute simple moving averages from an OHLCV array.
	 *
	 * @param array $closes  Array of closing prices (oldest first).
	 * @param int   $period  SMA period.
	 * @return float|null Latest SMA value or null if insufficient data.
	 */
	public static function compute_sma( array $closes, $period = 20 ) {
		if ( count( $closes ) < $period ) {
			return null;
		}
		$slice = array_slice( $closes, - $period );
		return array_sum( $slice ) / $period;
	}

	/**
	 * Compute RSI from closing prices.
	 *
	 * @param array $closes Array of closing prices (oldest first).
	 * @param int   $period RSI period (default 14).
	 * @return float|null RSI value 0-100 or null if insufficient data.
	 */
	public static function compute_rsi( array $closes, $period = 14 ) {
		if ( count( $closes ) < $period + 1 ) {
			return null;
		}
		$gains  = 0.0;
		$losses = 0.0;
		for ( $i = count( $closes ) - $period; $i < count( $closes ); $i++ ) {
			$change = $closes[ $i ] - $closes[ $i - 1 ];
			if ( $change > 0 ) {
				$gains += $change;
			} else {
				$losses += abs( $change );
			}
		}
		if ( 0.0 === $losses ) {
			return 100.0;
		}
		$rs  = ( $gains / $period ) / ( $losses / $period );
		$rsi = 100.0 - ( 100.0 / ( 1.0 + $rs ) );
		return round( $rsi, 2 );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Perform a cached remote GET request and decode JSON response.
	 *
	 * @param string $url     Request URL.
	 * @param int    $timeout Timeout in seconds.
	 * @param int    $cache   Cache duration in seconds (0 = no cache).
	 * @return array|WP_Error Decoded array or WP_Error.
	 */
	public static function remote_get( $url, $timeout = 15, $cache = 300 ) {
		$cache_key = 'act_market_' . md5( $url );

		if ( $cache > 0 ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'AI-Crypto-Trader-WP-Plugin/' . ACT_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new WP_Error( 'http_error', sprintf( 'HTTP %d from %s', $code, $url ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json_error', 'Failed to parse JSON response.' );
		}

		if ( $cache > 0 ) {
			set_transient( $cache_key, $data, $cache );
		}

		return $data;
	}

	/**
	 * Map a trading pair symbol to a CoinGecko coin ID.
	 *
	 * @param string $symbol E.g. 'BTC', 'ETH', 'XRP'.
	 * @return string CoinGecko ID.
	 */
	public static function symbol_to_coingecko_id( $symbol ) {
		$map = array(
			'BTC'  => 'bitcoin',
			'ETH'  => 'ethereum',
			'XRP'  => 'ripple',
			'ADA'  => 'cardano',
			'SOL'  => 'solana',
			'DOT'  => 'polkadot',
			'DOGE' => 'dogecoin',
			'AVAX' => 'avalanche-2',
			'MATIC'=> 'matic-network',
			'LINK' => 'chainlink',
			'UNI'  => 'uniswap',
			'LTC'  => 'litecoin',
			'BCH'  => 'bitcoin-cash',
			'ATOM' => 'cosmos',
			'XLM'  => 'stellar',
			'TRX'  => 'tron',
			'ETC'  => 'ethereum-classic',
			'FIL'  => 'filecoin',
			'ALGO' => 'algorand',
			'VET'  => 'vechain',
		);
		$symbol = strtoupper( $symbol );
		return isset( $map[ $symbol ] ) ? $map[ $symbol ] : strtolower( $symbol );
	}
}
