<?php
/**
 * Database Handler Class
 *
 * Manages custom database tables for fraud detection
 *
 * @package Fraud_Detection
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fraud Detection Database Class
 */
class Fraud_Detection_Database {

    /**
     * Blacklist table name
     *
     * @var string
     */
    private $blacklist_table;

    /**
     * Whitelist table name
     *
     * @var string
     */
    private $whitelist_table;

    /**
     * Order logs table name
     *
     * @var string
     */
    private $order_logs_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->blacklist_table = $wpdb->prefix . 'fraud_blacklist';
        $this->whitelist_table = $wpdb->prefix . 'fraud_whitelist';
        $this->order_logs_table = $wpdb->prefix . 'fraud_order_logs';
    }

    /**
     * Get blacklist table name
     */
    public function get_blacklist_table() {
        return $this->blacklist_table;
    }

    /**
     * Get whitelist table name
     */
    public function get_whitelist_table() {
        return $this->whitelist_table;
    }

    /**
     * Get order logs table name
     */
    public function get_order_logs_table() {
        return $this->order_logs_table;
    }

    /**
     * Create custom database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();

        // Blacklist table
        $blacklist_sql = "CREATE TABLE IF NOT EXISTS {$this->blacklist_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_type varchar(20) NOT NULL DEFAULT 'phone',
            entry_value varchar(255) NOT NULL,
            is_permanent tinyint(1) NOT NULL DEFAULT 0,
            reason text,
            added_by bigint(20) UNSIGNED,
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entry_unique (entry_type, entry_value),
            KEY entry_type (entry_type),
            KEY entry_value (entry_value),
            KEY is_permanent (is_permanent)
        ) $charset_collate;";

        // Whitelist table
        $whitelist_sql = "CREATE TABLE IF NOT EXISTS {$this->whitelist_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_type varchar(20) NOT NULL DEFAULT 'phone',
            entry_value varchar(255) NOT NULL,
            bypass_daily_limit tinyint(1) NOT NULL DEFAULT 1,
            notes text,
            added_by bigint(20) UNSIGNED,
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entry_unique (entry_type, entry_value),
            KEY entry_type (entry_type),
            KEY entry_value (entry_value)
        ) $charset_collate;";

        // Order logs table
        $order_logs_sql = "CREATE TABLE IF NOT EXISTS {$this->order_logs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED,
            customer_email varchar(100),
            customer_phone varchar(50),
            customer_phone_normalized varchar(50),
            customer_ip varchar(50),
            device_fingerprint varchar(32),
            browser_fingerprint varchar(32),
            device_cookie varchar(255),
            user_agent text,
            device_type varchar(20),
            browser_name varchar(50),
            order_total decimal(10,2),
            is_blocked tinyint(1) NOT NULL DEFAULT 0,
            block_reason text,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY customer_email (customer_email),
            KEY customer_phone_normalized (customer_phone_normalized),
            KEY customer_ip (customer_ip),
            KEY device_fingerprint (device_fingerprint),
            KEY browser_fingerprint (browser_fingerprint),
            KEY device_cookie (device_cookie),
            KEY is_blocked (is_blocked),
            KEY date_created (date_created)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta( $blacklist_sql );
        dbDelta( $whitelist_sql );
        dbDelta( $order_logs_sql );

        // Update database version
        update_option( 'fraud_detection_db_version', FRAUD_DETECTION_VERSION );
    }

    /**
     * Drop all custom tables (for uninstall)
     */
    public function drop_tables() {
        global $wpdb;
        
        $wpdb->query( "DROP TABLE IF EXISTS {$this->blacklist_table}" );
        $wpdb->query( "DROP TABLE IF EXISTS {$this->whitelist_table}" );
        $wpdb->query( "DROP TABLE IF EXISTS {$this->order_logs_table}" );
        
        delete_option( 'fraud_detection_db_version' );
    }

    /**
     * Clean old order logs based on retention days
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = absint( get_option( 'fraud_detection_log_retention_days', 30 ) );
        
        if ( $retention_days > 0 ) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->order_logs_table} WHERE date_created < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $retention_days
                )
            );
        }
    }
}
