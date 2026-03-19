<?php
/**
 * AI Trader – core intelligence engine for AI Crypto Trader.
 *
 * Uses the OpenAI Chat Completions API (GPT-4o by default) to:
 *  1. Analyse market data, technical indicators, and news.
 *  2. Return a structured trading signal: buy | sell | hold.
 *  3. Determine confidence level and reasoning.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives AI-based trading decisions.
 */
class ACT_AI_Trader {

	/**
	 * OpenAI API endpoint.
	 */
	const OPENAI_API = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Get plugin settings.
	 *
	 * @return array
	 */
	private static function settings() {
		return (array) get_option( 'act_settings', array() );
	}

	/**
	 * Analyse a trading pair and return a signal.
	 *
	 * Builds a detailed prompt from market data + news and calls OpenAI.
	 *
	 * @param string $pair          Trading pair (e.g. 'BTC/USDT').
	 * @param array  $market_data   Associative array of market information.
	 * @param string $news_context  Pre-formatted news context string.
	 * @return array {
	 *   @type string $signal      'buy' | 'sell' | 'hold'.
	 *   @type int    $confidence  0-100.
	 *   @type string $reasoning   Explanation.
	 *   @type string $strategy    Strategy name used.
	 * }
	 */
	public static function analyse( $pair, array $market_data, $news_context = '' ) {
		$settings = self::settings();
		$api_key  = isset( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : '';

		// If no API key, fall back to a simple rule-based decision.
		if ( ! $api_key ) {
			return self::fallback_analysis( $pair, $market_data );
		}

		$model  = isset( $settings['ai_model'] ) ? $settings['ai_model'] : 'gpt-4o';
		$prompt = self::build_prompt( $pair, $market_data, $news_context );

		$response = wp_remote_post(
			self::OPENAI_API,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body' => wp_json_encode(
					array(
						'model'       => $model,
						'temperature' => 0.2,
						'max_tokens'  => 500,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => self::system_prompt(),
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'response_format' => array( 'type' => 'json_object' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::fallback_analysis( $pair, $market_data );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return self::fallback_analysis( $pair, $market_data );
		}

		$body   = wp_remote_retrieve_body( $response );
		$parsed = json_decode( $body, true );

		if ( ! isset( $parsed['choices'][0]['message']['content'] ) ) {
			return self::fallback_analysis( $pair, $market_data );
		}

		$content = json_decode( $parsed['choices'][0]['message']['content'], true );
		if ( ! is_array( $content ) ) {
			return self::fallback_analysis( $pair, $market_data );
		}

		return self::parse_ai_response( $content );
	}

	/**
	 * Build the user-facing analysis prompt.
	 *
	 * @param string $pair        Trading pair.
	 * @param array  $market_data Market data.
	 * @param string $news        News context.
	 * @return string Prompt text.
	 */
	private static function build_prompt( $pair, array $market_data, $news = '' ) {
		$parts = explode( '/', $pair );
		$base  = $parts[0];
		$quote = isset( $parts[1] ) ? $parts[1] : 'USDT';

		$price        = isset( $market_data['price'] ) ? $market_data['price'] : 'unknown';
		$change_24h   = isset( $market_data['change_24h'] ) ? $market_data['change_24h'] . '%' : 'unknown';
		$market_cap   = isset( $market_data['market_cap'] ) ? '$' . number_format( $market_data['market_cap'] ) : 'unknown';
		$rsi          = isset( $market_data['rsi'] ) ? $market_data['rsi'] : 'unknown';
		$sma_20       = isset( $market_data['sma_20'] ) ? $market_data['sma_20'] : 'unknown';
		$sma_50       = isset( $market_data['sma_50'] ) ? $market_data['sma_50'] : 'unknown';
		$volume_trend = isset( $market_data['volume_trend'] ) ? $market_data['volume_trend'] : 'unknown';
		$macd_signal  = isset( $market_data['macd_signal'] ) ? $market_data['macd_signal'] : 'unknown';
		$holding_amt  = isset( $market_data['holding_amount'] ) ? $market_data['holding_amount'] : 0;
		$avg_buy      = isset( $market_data['avg_buy_price'] ) ? $market_data['avg_buy_price'] : 0;
		$global_btc_dom = isset( $market_data['btc_dominance'] ) ? $market_data['btc_dominance'] . '%' : 'unknown';

		$prompt = <<<EOT
Analyse the following trading data for {$pair} and provide a trading decision.

ASSET: {$base}
QUOTE: {$quote}
CURRENT PRICE: {$price} {$quote}
24H CHANGE: {$change_24h}
MARKET CAP: {$market_cap}
BTC DOMINANCE: {$global_btc_dom}

TECHNICAL INDICATORS:
- RSI (14): {$rsi}
- SMA 20: {$sma_20}
- SMA 50: {$sma_50}
- MACD Signal: {$macd_signal}
- Volume Trend: {$volume_trend}

CURRENT POSITION:
- Holding: {$holding_amt} {$base}
- Average Buy Price: {$avg_buy} {$quote}

NEWS AND MARKET CONTEXT:
{$news}

Based on all the above data, provide a trading signal.
EOT;

		return $prompt;
	}

	/**
	 * Return the system prompt for the AI model.
	 *
	 * @return string System prompt.
	 */
	private static function system_prompt() {
		return <<<'EOT'
You are an expert quantitative trading analyst with deep knowledge of cryptocurrency, forex, stocks, and commodities markets.

Your task is to analyse market data and news to produce a trading signal.

You MUST respond with a valid JSON object containing EXACTLY these fields:
{
  "signal": "buy" | "sell" | "hold",
  "confidence": <integer 0-100>,
  "strategy": "<short strategy name, e.g. RSI Oversold, MACD Crossover, Trend Following, News Sentiment>",
  "reasoning": "<2-3 sentences explaining the key factors behind your decision>",
  "risk_note": "<brief risk note if any>"
}

Guidelines:
- Only recommend "buy" when confidence >= 60.
- Only recommend "sell" when confidence >= 55.
- Default to "hold" when signals are mixed or uncertain.
- Consider RSI < 30 as oversold (potential buy), RSI > 70 as overbought (potential sell).
- Weight recent news heavily; negative regulatory news overrides positive technicals.
- NEVER recommend using more than the configured position size.
EOT;
	}

	/**
	 * Parse and validate the AI response array.
	 *
	 * @param array $content Decoded AI JSON response.
	 * @return array Normalised signal.
	 */
	private static function parse_ai_response( array $content ) {
		$valid_signals = array( 'buy', 'sell', 'hold' );

		$signal     = isset( $content['signal'] ) ? strtolower( $content['signal'] ) : 'hold';
		$confidence = isset( $content['confidence'] ) ? absint( $content['confidence'] ) : 0;
		$strategy   = isset( $content['strategy'] ) ? sanitize_text_field( $content['strategy'] ) : 'AI Analysis';
		$reasoning  = isset( $content['reasoning'] ) ? sanitize_textarea_field( $content['reasoning'] ) : '';
		$risk_note  = isset( $content['risk_note'] ) ? sanitize_textarea_field( $content['risk_note'] ) : '';

		if ( ! in_array( $signal, $valid_signals, true ) ) {
			$signal = 'hold';
		}
		$confidence = max( 0, min( 100, $confidence ) );

		// Enforce minimum confidence thresholds.
		if ( 'buy' === $signal && $confidence < 60 ) {
			$signal = 'hold';
		}
		if ( 'sell' === $signal && $confidence < 55 ) {
			$signal = 'hold';
		}

		return array(
			'signal'     => $signal,
			'confidence' => $confidence,
			'strategy'   => $strategy,
			'reasoning'  => $reasoning,
			'risk_note'  => $risk_note,
		);
	}

	/**
	 * Fallback rule-based analysis when no API key is configured.
	 *
	 * Uses RSI, SMA crossovers, and news sentiment.
	 *
	 * @param string $pair        Trading pair.
	 * @param array  $market_data Market data.
	 * @return array Signal array.
	 */
	public static function fallback_analysis( $pair, array $market_data ) {
		$signal     = 'hold';
		$confidence = 50;
		$reasons    = array();

		$rsi          = isset( $market_data['rsi'] ) ? floatval( $market_data['rsi'] ) : null;
		$price        = isset( $market_data['price'] ) ? floatval( $market_data['price'] ) : 0;
		$sma_20       = isset( $market_data['sma_20'] ) ? floatval( $market_data['sma_20'] ) : null;
		$sma_50       = isset( $market_data['sma_50'] ) ? floatval( $market_data['sma_50'] ) : null;
		$change_24h   = isset( $market_data['change_24h'] ) ? floatval( $market_data['change_24h'] ) : 0;
		$news_sent    = isset( $market_data['news_sentiment'] ) ? floatval( $market_data['news_sentiment'] ) : 0;

		$buy_signals  = 0;
		$sell_signals = 0;

		// RSI rule.
		if ( null !== $rsi ) {
			if ( $rsi < 30 ) {
				$buy_signals += 2;
				$reasons[]    = 'RSI oversold (' . $rsi . ')';
			} elseif ( $rsi < 40 ) {
				$buy_signals++;
				$reasons[]    = 'RSI approaching oversold (' . $rsi . ')';
			} elseif ( $rsi > 70 ) {
				$sell_signals += 2;
				$reasons[]     = 'RSI overbought (' . $rsi . ')';
			} elseif ( $rsi > 60 ) {
				$sell_signals++;
				$reasons[]     = 'RSI approaching overbought (' . $rsi . ')';
			}
		}

		// SMA crossover rule.
		if ( null !== $sma_20 && null !== $sma_50 && $price > 0 ) {
			if ( $sma_20 > $sma_50 && $price > $sma_20 ) {
				$buy_signals++;
				$reasons[] = 'Price above SMA20 > SMA50 (golden cross)';
			} elseif ( $sma_20 < $sma_50 && $price < $sma_20 ) {
				$sell_signals++;
				$reasons[] = 'Price below SMA20 < SMA50 (death cross)';
			}
		}

		// 24h change momentum.
		if ( $change_24h > 5 ) {
			$buy_signals++;
			$reasons[] = '24h gain ' . $change_24h . '%';
		} elseif ( $change_24h < -5 ) {
			$sell_signals++;
			$reasons[] = '24h loss ' . $change_24h . '%';
		}

		// News sentiment.
		if ( $news_sent > 0.3 ) {
			$buy_signals++;
			$reasons[] = 'Positive news sentiment';
		} elseif ( $news_sent < -0.3 ) {
			$sell_signals++;
			$reasons[] = 'Negative news sentiment';
		}

		$total = $buy_signals + $sell_signals;
		if ( $total > 0 ) {
			if ( $buy_signals > $sell_signals ) {
				$signal     = 'buy';
				$confidence = (int) ( 50 + ( ( $buy_signals - $sell_signals ) / $total ) * 50 );
			} elseif ( $sell_signals > $buy_signals ) {
				$signal     = 'sell';
				$confidence = (int) ( 50 + ( ( $sell_signals - $buy_signals ) / $total ) * 50 );
			}
		}

		// Enforce minimum thresholds.
		if ( 'buy' === $signal && $confidence < 60 ) {
			$signal = 'hold';
		}
		if ( 'sell' === $signal && $confidence < 55 ) {
			$signal = 'hold';
		}

		return array(
			'signal'     => $signal,
			'confidence' => $confidence,
			'strategy'   => 'Rule-Based (No AI Key)',
			'reasoning'  => implode( '; ', $reasons ),
			'risk_note'  => 'Using fallback analysis — configure OpenAI API key for AI-powered signals.',
		);
	}

	/**
	 * Log an analysis result to the database.
	 *
	 * @param string $asset        Asset symbol.
	 * @param array  $signal       Signal array from analyse().
	 * @param float  $price        Price at analysis time.
	 * @param string $news_summary News summary text.
	 * @param array  $technical    Technical data array.
	 */
	public static function log_analysis( $asset, array $signal, $price, $news_summary = '', array $technical = array() ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			"{$wpdb->prefix}act_analysis_log",
			array(
				'analysis_time'    => current_time( 'mysql' ),
				'asset'            => strtoupper( $asset ),
				'signal'           => $signal['signal'],
				'confidence'       => $signal['confidence'],
				'price_at_analysis'=> $price,
				'reasoning'        => $signal['reasoning'],
				'news_summary'     => $news_summary,
				'technical_data'   => wp_json_encode( $technical ),
			),
			array( '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get recent analysis logs.
	 *
	 * @param int $limit Number of records.
	 * @return array Log rows.
	 */
	public static function get_analysis_log( $limit = 20 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}act_analysis_log ORDER BY analysis_time DESC LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);
	}
}
