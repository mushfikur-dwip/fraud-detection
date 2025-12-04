<?php
/**
 * Order Tracker Class
 *
 * Tracks all orders for fraud detection analysis
 *
 * @package Fraud_Detection
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fraud Detection Order Tracker Class
 */
class Fraud_Detection_Order_Tracker {

    /**
     * Constructor
     */
    public function __construct() {
        // Schedule cleanup cron
        if ( ! wp_next_scheduled( 'fraud_detection_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'fraud_detection_cleanup_logs' );
        }
        
        add_action( 'fraud_detection_cleanup_logs', array( $this, 'cleanup_old_logs' ) );
    }

    /**
     * Track order after it's processed
     *
     * @param int    $order_id Order ID
     * @param array  $posted_data Posted data
     * @param object $order WC_Order object
     */
    public function track_order( $order_id, $posted_data, $order ) {
        global $wpdb;

        error_log( 'Fraud Detection: track_order() called for order #' . $order_id );

        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();

        // Get order data
        $billing_email = $order->get_billing_email();
        $billing_phone = $order->get_billing_phone();
        
        error_log( 'Fraud Detection: Tracking order - Phone=' . $billing_phone . ', Email=' . $billing_email );
        
        // Normalize phone
        $phone_normalized = '';
        if ( 'yes' === get_option( 'fraud_detection_normalize_phone', 'yes' ) ) {
            $phone_normalized = fraud_detection_normalize_phone( $billing_phone );
        } else {
            $phone_normalized = $billing_phone;
        }

        error_log( 'Fraud Detection: Normalized phone=' . $phone_normalized );

        $customer_ip = fraud_detection_get_customer_ip();
        $order_total = $order->get_total();

        // Get device fingerprint data
        $device_data = Fraud_Detection_Device_Fingerprint::get_device_data();

        // Insert log with device fingerprint data
        $result = $wpdb->insert(
            $table,
            array(
                'order_id'                   => $order_id,
                'customer_email'             => $billing_email,
                'customer_phone'             => $billing_phone,
                'customer_phone_normalized'  => $phone_normalized,
                'customer_ip'                => $customer_ip,
                'device_fingerprint'         => $device_data['fingerprint'],
                'browser_fingerprint'        => $device_data['browser_fingerprint'],
                'device_cookie'              => $device_data['device_cookie'],
                'user_agent'                 => $device_data['user_agent'],
                'device_type'                => $device_data['device_type'],
                'browser_name'               => $device_data['browser_info']['name'],
                'order_total'                => $order_total,
                'is_blocked'                 => 0,
                'date_created'               => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s' )
        );

        if ( false === $result ) {
            error_log( 'Fraud Detection: ERROR - Failed to insert order log. DB Error: ' . $wpdb->last_error );
        } else {
            error_log( 'Fraud Detection: Order tracked successfully in database' );
        }

        // Add order note with device info
        $order->add_order_note(
            sprintf(
                __( 'Fraud Detection: Order tracked. Phone: %s, IP: %s, Device: %s (%s)', 'fraud-detection' ),
                $phone_normalized,
                $customer_ip,
                $device_data['device_type'],
                $device_data['browser_info']['name']
            )
        );
    }

    /**
     * Log blocked order attempt
     *
     * @param string $email Email address
     * @param string $phone Phone number
     * @param string $reason Block reason
     */
    public function log_blocked_order( $email, $phone, $reason ) {
        global $wpdb;

        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();

        $phone_normalized = '';
        if ( 'yes' === get_option( 'fraud_detection_normalize_phone', 'yes' ) ) {
            $phone_normalized = fraud_detection_normalize_phone( $phone );
        } else {
            $phone_normalized = $phone;
        }

        $customer_ip = fraud_detection_get_customer_ip();

        // Insert log
        $wpdb->insert(
            $table,
            array(
                'order_id'                   => 0,
                'customer_email'             => $email,
                'customer_phone'             => $phone,
                'customer_phone_normalized'  => $phone_normalized,
                'customer_ip'                => $customer_ip,
                'order_total'                => 0,
                'is_blocked'                 => 1,
                'block_reason'               => $reason,
                'date_created'               => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s' )
        );
    }

    /**
     * Get order count for phone number
     *
     * @param string $phone Phone number (normalized)
     * @param string $period Period (today, week, month, all)
     * @return int Order count
     */
    public function get_order_count_by_phone( $phone, $period = 'today' ) {
        if ( empty( $phone ) ) {
            return 0;
        }

        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();

        $date_condition = '';
        switch ( $period ) {
            case 'today':
                $date_condition = 'AND DATE(date_created) = CURDATE()';
                break;
            case 'week':
                $date_condition = 'AND date_created >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $date_condition = 'AND date_created >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
                break;
            default:
                $date_condition = '';
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE customer_phone_normalized = %s 
                AND is_blocked = 0
                {$date_condition}",
                $phone
            )
        );

        return absint( $count );
    }

    /**
     * Get order count for email
     *
     * @param string $email Email address
     * @param string $period Period (today, week, month, all)
     * @return int Order count
     */
    public function get_order_count_by_email( $email, $period = 'today' ) {
        if ( empty( $email ) ) {
            return 0;
        }

        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();

        $date_condition = '';
        switch ( $period ) {
            case 'today':
                $date_condition = 'AND DATE(date_created) = CURDATE()';
                break;
            case 'week':
                $date_condition = 'AND date_created >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $date_condition = 'AND date_created >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
                break;
            default:
                $date_condition = '';
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE customer_email = %s 
                AND is_blocked = 0
                {$date_condition}",
                $email
            )
        );

        return absint( $count );
    }

    /**
     * Get recent orders
     *
     * @param int $limit Limit
     * @return array Orders
     */
    public function get_recent_orders( $limit = 50 ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY date_created DESC LIMIT %d",
                $limit
            )
        );

        return $results;
    }

    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        $plugin = Fraud_Detection_Plugin::get_instance();
        $plugin->database->cleanup_old_logs();
    }
}
