<?php
/**
 * Database management for AI Crypto Trader.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database table creation and upgrades.
 */
class ACT_Database {

	/**
	 * Create all required plugin database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Wallet holdings table.
		$sql_wallet = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}act_wallet (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			asset        VARCHAR(20)         NOT NULL,
			amount       DECIMAL(20,8)       NOT NULL DEFAULT '0.00000000',
			avg_buy_price DECIMAL(20,8)      NOT NULL DEFAULT '0.00000000',
			last_updated DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY asset (asset)
		) $charset_collate;";

		// Trade history table.
		$sql_trades = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}act_trades (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			trade_time    DATETIME            NOT NULL,
			pair          VARCHAR(20)         NOT NULL,
			side          ENUM('buy','sell')  NOT NULL,
			amount        DECIMAL(20,8)       NOT NULL,
			price         DECIMAL(20,8)       NOT NULL,
			total_value   DECIMAL(20,8)       NOT NULL,
			fee           DECIMAL(20,8)       NOT NULL DEFAULT '0.00000000',
			profit_loss   DECIMAL(20,8)       NOT NULL DEFAULT '0.00000000',
			strategy      VARCHAR(100)        NOT NULL DEFAULT '',
			ai_confidence TINYINT(3)          NOT NULL DEFAULT '0',
			status        VARCHAR(20)         NOT NULL DEFAULT 'executed',
			notes         TEXT,
			PRIMARY KEY   (id),
			KEY trade_time (trade_time),
			KEY pair       (pair)
		) $charset_collate;";

		// AI analysis log table.
		$sql_analysis = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}act_analysis_log (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			analysis_time DATETIME            NOT NULL,
			asset         VARCHAR(20)         NOT NULL,
			signal        ENUM('buy','sell','hold') NOT NULL,
			confidence    TINYINT(3)          NOT NULL DEFAULT '0',
			price_at_analysis DECIMAL(20,8)   NOT NULL DEFAULT '0.00000000',
			reasoning     TEXT,
			news_summary  TEXT,
			technical_data LONGTEXT,
			PRIMARY KEY   (id),
			KEY analysis_time (analysis_time),
			KEY asset         (asset)
		) $charset_collate;";

		// Portfolio snapshots table.
		$sql_snapshots = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}act_portfolio_snapshots (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			snapshot_time   DATETIME            NOT NULL,
			total_value_usd DECIMAL(20,8)       NOT NULL DEFAULT '0.00000000',
			holdings        LONGTEXT,
			PRIMARY KEY     (id),
			KEY snapshot_time (snapshot_time)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_wallet );
		dbDelta( $sql_trades );
		dbDelta( $sql_analysis );
		dbDelta( $sql_snapshots );

		update_option( 'act_db_version', ACT_DB_VERSION );
	}

	/**
	 * Drop all plugin tables (used on uninstall).
	 */
	public static function drop_tables() {
		global $wpdb;
		$tables = array(
			"{$wpdb->prefix}act_wallet",
			"{$wpdb->prefix}act_trades",
			"{$wpdb->prefix}act_analysis_log",
			"{$wpdb->prefix}act_portfolio_snapshots",
		);
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
		delete_option( 'act_db_version' );
	}
}
