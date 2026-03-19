<?php
/**
 * Uninstall script for AI Crypto Trader.
 *
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes all plugin data: database tables and options.
 *
 * @package AI_Crypto_Trader
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

ACT_Database::drop_tables();

delete_option( 'act_settings' );
delete_option( 'act_db_version' );

// Clear all transients with the act_ prefix.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_act_%'
	    OR option_name LIKE '_transient_timeout_act_%'"
);
