/**
 * Uninstall Script
 * 
 * Cleans up plugin data when uninstalled
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fraud_blacklist" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fraud_whitelist" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fraud_order_logs" );

// Delete plugin options
$options = array(
    'fraud_detection_enabled',
    'fraud_detection_daily_limit',
    'fraud_detection_check_email',
    'fraud_detection_check_phone',
    'fraud_detection_normalize_phone',
    'fraud_detection_block_message',
    'fraud_detection_limit_message',
    'fraud_detection_whitelist_bypass_limit',
    'fraud_detection_log_retention_days',
    'fraud_detection_admin_notifications',
    'fraud_detection_admin_email',
    'fraud_detection_db_version',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear scheduled cron jobs
wp_clear_scheduled_hook( 'fraud_detection_cleanup_logs' );

// Delete transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fraud_detection_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fraud_detection_%'" );
