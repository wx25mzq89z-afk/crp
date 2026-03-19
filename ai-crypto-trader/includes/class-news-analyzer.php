<?php
/**
 * News analyzer for AI Crypto Trader.
 *
 * Fetches financial news from NewsAPI and CryptoCompare and prepares
 * summaries for AI consumption.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches and summarizes financial/crypto news.
 */
class ACT_News_Analyzer {

	/**
	 * NewsAPI base URL.
	 */
	const NEWSAPI_BASE = 'https://newsapi.org/v2';

	/**
	 * CryptoCompare news base URL.
	 */
	const CRYPTOCOMPARE_BASE = 'https://min-api.cryptocompare.com/data/v2/news/';

	/**
	 * Get plugin settings.
	 *
	 * @return array Settings.
	 */
	private static function settings() {
		return (array) get_option( 'act_settings', array() );
	}

	/**
	 * Fetch crypto-related news headlines for a given asset.
	 *
	 * @param string $asset  Asset symbol (e.g. 'BTC').
	 * @param int    $limit  Maximum number of articles.
	 * @return array Array of article arrays with keys: title, description, url, publishedAt, source.
	 */
	public static function get_crypto_news( $asset, $limit = 10 ) {
		$settings = self::settings();
		$articles = array();

		// 1. Try NewsAPI.
		$newsapi_key = isset( $settings['newsapi_key'] ) ? $settings['newsapi_key'] : '';
		if ( $newsapi_key ) {
			$query = sanitize_text_field( $asset ) . ' cryptocurrency';
			$url   = self::NEWSAPI_BASE . '/everything?' . http_build_query(
				array(
					'q'        => $query,
					'language' => 'en',
					'sortBy'   => 'publishedAt',
					'pageSize' => min( absint( $limit ), 20 ),
					'apiKey'   => $newsapi_key,
				)
			);
			$data = ACT_Market_Data::remote_get( $url, 15, 1800 );
			if ( ! is_wp_error( $data ) && isset( $data['articles'] ) ) {
				foreach ( $data['articles'] as $article ) {
					$articles[] = array(
						'title'       => sanitize_text_field( $article['title'] ),
						'description' => sanitize_textarea_field( isset( $article['description'] ) ? $article['description'] : '' ),
						'url'         => esc_url_raw( $article['url'] ),
						'publishedAt' => sanitize_text_field( $article['publishedAt'] ),
						'source'      => sanitize_text_field( $article['source']['name'] ),
					);
				}
			}
		}

		// 2. Fall back to CryptoCompare (free, no key required).
		if ( empty( $articles ) ) {
			$url  = self::CRYPTOCOMPARE_BASE . '?lang=EN&categories=' . rawurlencode( strtoupper( $asset ) );
			$data = ACT_Market_Data::remote_get( $url, 15, 1800 );
			if ( ! is_wp_error( $data ) && isset( $data['Data'] ) ) {
				foreach ( array_slice( $data['Data'], 0, $limit ) as $article ) {
					$articles[] = array(
						'title'       => sanitize_text_field( $article['title'] ),
						'description' => sanitize_textarea_field( isset( $article['body'] ) ? wp_trim_words( $article['body'], 40 ) : '' ),
						'url'         => esc_url_raw( $article['url'] ),
						'publishedAt' => gmdate( 'c', absint( $article['published_on'] ) ),
						'source'      => sanitize_text_field( $article['source'] ),
					);
				}
			}
		}

		return $articles;
	}

	/**
	 * Fetch general financial market news (stocks, forex, commodities).
	 *
	 * @param int $limit Max articles.
	 * @return array Array of article arrays.
	 */
	public static function get_financial_news( $limit = 10 ) {
		$settings    = self::settings();
		$newsapi_key = isset( $settings['newsapi_key'] ) ? $settings['newsapi_key'] : '';
		$articles    = array();

		if ( $newsapi_key ) {
			$url = self::NEWSAPI_BASE . '/top-headlines?' . http_build_query(
				array(
					'category' => 'business',
					'language' => 'en',
					'pageSize' => min( absint( $limit ), 20 ),
					'apiKey'   => $newsapi_key,
				)
			);
			$data = ACT_Market_Data::remote_get( $url, 15, 1800 );
			if ( ! is_wp_error( $data ) && isset( $data['articles'] ) ) {
				foreach ( $data['articles'] as $article ) {
					$articles[] = array(
						'title'       => sanitize_text_field( $article['title'] ),
						'description' => sanitize_textarea_field( isset( $article['description'] ) ? $article['description'] : '' ),
						'url'         => esc_url_raw( $article['url'] ),
						'publishedAt' => sanitize_text_field( $article['publishedAt'] ),
						'source'      => sanitize_text_field( $article['source']['name'] ),
					);
				}
			}
		} else {
			// Fallback: CryptoCompare general market news.
			$url  = self::CRYPTOCOMPARE_BASE . '?lang=EN&categories=Market,Trading,Regulation,Technology';
			$data = ACT_Market_Data::remote_get( $url, 15, 1800 );
			if ( ! is_wp_error( $data ) && isset( $data['Data'] ) ) {
				foreach ( array_slice( $data['Data'], 0, $limit ) as $article ) {
					$articles[] = array(
						'title'       => sanitize_text_field( $article['title'] ),
						'description' => sanitize_textarea_field( isset( $article['body'] ) ? wp_trim_words( $article['body'], 40 ) : '' ),
						'url'         => esc_url_raw( $article['url'] ),
						'publishedAt' => gmdate( 'c', absint( $article['published_on'] ) ),
						'source'      => sanitize_text_field( $article['source'] ),
					);
				}
			}
		}

		return $articles;
	}

	/**
	 * Build a compact news context string for inclusion in an AI prompt.
	 *
	 * Includes both asset-specific and general financial news.
	 *
	 * @param string $asset  Asset symbol.
	 * @param int    $limit  Max articles to include.
	 * @return string Formatted news context.
	 */
	public static function build_news_context( $asset, $limit = 5 ) {
		$crypto_news    = self::get_crypto_news( $asset, $limit );
		$financial_news = self::get_financial_news( $limit );

		$lines = array();

		if ( ! empty( $crypto_news ) ) {
			$lines[] = "=== Recent {$asset} News ===";
			foreach ( $crypto_news as $article ) {
				$lines[] = sprintf(
					'[%s] %s: %s',
					substr( $article['publishedAt'], 0, 10 ),
					$article['source'],
					$article['title']
				);
				if ( $article['description'] ) {
					$lines[] = '  ' . $article['description'];
				}
			}
		}

		if ( ! empty( $financial_news ) ) {
			$lines[] = '';
			$lines[] = '=== General Financial News ===';
			foreach ( $financial_news as $article ) {
				$lines[] = sprintf(
					'[%s] %s: %s',
					substr( $article['publishedAt'], 0, 10 ),
					$article['source'],
					$article['title']
				);
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Perform a simple sentiment score on a text snippet.
	 *
	 * Returns a float between -1 (very negative) and +1 (very positive).
	 * This is a lightweight keyword-based fallback when AI is not available.
	 *
	 * @param string $text Text to score.
	 * @return float Sentiment score.
	 */
	public static function simple_sentiment( $text ) {
		$positive_words = array(
			'bullish', 'surge', 'rally', 'gain', 'rise', 'soar', 'climb', 'high',
			'record', 'growth', 'profit', 'positive', 'strong', 'buy', 'opportunity',
			'adoption', 'partnership', 'launch', 'upgrade', 'approve', 'boost',
		);
		$negative_words = array(
			'bearish', 'crash', 'drop', 'fall', 'plunge', 'decline', 'loss', 'low',
			'risk', 'ban', 'hack', 'fraud', 'scam', 'weak', 'sell', 'dump',
			'concern', 'lawsuit', 'fine', 'penalty', 'warning', 'fear', 'crisis',
		);

		$text  = strtolower( $text );
		$score = 0;
		foreach ( $positive_words as $w ) {
			$score += substr_count( $text, $w );
		}
		foreach ( $negative_words as $w ) {
			$score -= substr_count( $text, $w );
		}
		// Normalize to -1..+1.
		$max = max( 1, abs( $score ) );
		return max( -1.0, min( 1.0, $score / $max ) );
	}
}
