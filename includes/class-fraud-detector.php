<?php
/**
 * Fraud Detector Class
 *
 * Main validation logic for fraud detection
 *
 * @package Fraud_Detection
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fraud Detection Detector Class
 */
class Fraud_Detection_Detector {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook will be added from main class
    }

    /**
     * Validate checkout before order is created
     */
    public function validate_checkout() {
        // Check if fraud detection is enabled
        if ( 'yes' !== get_option( 'fraud_detection_enabled', 'yes' ) ) {
            return;
        }

        // Get checkout data
        $billing_email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
        $billing_phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';

        // Normalize phone if enabled
        $phone_normalized = '';
        if ( 'yes' === get_option( 'fraud_detection_normalize_phone', 'yes' ) ) {
            $phone_normalized = fraud_detection_normalize_phone( $billing_phone );
        } else {
            $phone_normalized = $billing_phone;
        }

        $customer_ip = fraud_detection_get_customer_ip();

        // Get device fingerprint data
        $device_data = Fraud_Detection_Device_Fingerprint::get_device_data();
        $device_fingerprint = $device_data['fingerprint'];
        $browser_fingerprint = $device_data['browser_fingerprint'];
        $device_cookie = $device_data['device_cookie'];

        // Check whitelist first (whitelist bypasses all checks)
        if ( $this->is_whitelisted( $billing_email, $phone_normalized ) ) {
            return; // Whitelisted, allow checkout
        }

        // Check blacklist
        $blacklist_result = $this->check_blacklist( $billing_email, $phone_normalized, $customer_ip );
        if ( $blacklist_result['blocked'] ) {
            $message = get_option( 'fraud_detection_block_message', __( 'Your order has been blocked due to security concerns. Please contact support.', 'fraud-detection' ) );
            
            // Send admin notification
            $this->send_block_notification( $billing_email, $phone_normalized, $blacklist_result['reason'] );
            
            // Log blocked attempt with device info
            $this->log_blocked_attempt( $billing_email, $phone_normalized, $customer_ip, $device_data, $blacklist_result['reason'] );
            
            wc_add_notice( $message, 'error' );
            return;
        }

        // Check device fingerprint limit (if enabled)
        if ( 'yes' === get_option( 'fraud_detection_check_device_fingerprint', 'yes' ) ) {
            $device_limit_result = $this->check_device_limit( $device_fingerprint );
            if ( $device_limit_result['exceeded'] ) {
                $message = get_option( 'fraud_detection_device_limit_message', __( 'You have reached the maximum number of orders allowed from this device.', 'fraud-detection' ) );
                
                // Send admin notification
                $this->send_device_limit_notification( $billing_email, $phone_normalized, $device_fingerprint, $device_limit_result['count'] );
                
                // Log blocked attempt
                $this->log_blocked_attempt( $billing_email, $phone_normalized, $customer_ip, $device_data, 'Device limit exceeded' );
                
                wc_add_notice( $message, 'error' );
                return;
            }
        }

        // Check browser fingerprint (prevent same browser with different phone numbers)
        if ( 'yes' === get_option( 'fraud_detection_check_browser_fingerprint', 'yes' ) ) {
            $browser_limit_result = $this->check_browser_fingerprint_limit( $browser_fingerprint );
            if ( $browser_limit_result['exceeded'] ) {
                $message = __( 'Multiple orders from the same browser detected. Please contact support if you believe this is an error.', 'fraud-detection' );
                
                // Log blocked attempt
                $this->log_blocked_attempt( $billing_email, $phone_normalized, $customer_ip, $device_data, 'Browser fingerprint limit exceeded' );
                
                wc_add_notice( $message, 'error' );
                return;
            }
        }

        // Check daily order limit by phone
        if ( 'yes' === get_option( 'fraud_detection_check_phone', 'yes' ) && ! empty( $phone_normalized ) ) {
            $limit_result = $this->check_daily_limit( $phone_normalized );
            if ( $limit_result['exceeded'] ) {
                $message = get_option( 'fraud_detection_limit_message', __( 'You have reached the maximum number of orders allowed per day from this phone number.', 'fraud-detection' ) );
                
                // Send admin notification
                $this->send_limit_notification( $billing_email, $phone_normalized, $limit_result['count'], $limit_result['limit'] );
                
                // Log blocked attempt
                $this->log_blocked_attempt( $billing_email, $phone_normalized, $customer_ip, $device_data, 'Daily phone limit exceeded' );
                
                wc_add_notice( $message, 'error' );
                return;
            }
        }

        // Check duplicate email (if enabled)
        if ( 'yes' === get_option( 'fraud_detection_check_email', 'yes' ) && ! empty( $billing_email ) ) {
            $email_result = $this->check_duplicate_email( $billing_email );
            if ( $email_result['duplicate'] ) {
                $message = sprintf(
                    __( 'An order with this email address has already been placed today. Maximum allowed: %d orders per day.', 'fraud-detection' ),
                    absint( get_option( 'fraud_detection_daily_limit', 3 ) )
                );
                
                wc_add_notice( $message, 'error' );
                return;
            }
        }
    }

    /**
     * Check if email or phone is whitelisted
     *
     * @param string $email Email address
     * @param string $phone Phone number (normalized)
     * @return bool True if whitelisted
     */
    private function is_whitelisted( $email, $phone ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_whitelist_table();

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE (entry_type = 'email' AND entry_value = %s) OR (entry_type = 'phone' AND entry_value = %s)",
            $email,
            $phone
        );

        $count = $wpdb->get_var( $query );

        return $count > 0;
    }

    /**
     * Check blacklist for email, phone, or IP
     *
     * @param string $email Email address
     * @param string $phone Phone number (normalized)
     * @param string $ip IP address
     * @return array Result with blocked status and reason
     */
    private function check_blacklist( $email, $phone, $ip ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_blacklist_table();

        // Check email
        if ( ! empty( $email ) ) {
            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT reason, is_permanent FROM {$table} WHERE entry_type = 'email' AND entry_value = %s",
                    $email
                )
            );

            if ( $result ) {
                return array(
                    'blocked' => true,
                    'reason'  => sprintf( __( 'Email address is blacklisted: %s', 'fraud-detection' ), $result->reason ),
                    'permanent' => (bool) $result->is_permanent,
                );
            }
        }

        // Check phone
        if ( ! empty( $phone ) ) {
            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT reason, is_permanent FROM {$table} WHERE entry_type = 'phone' AND entry_value = %s",
                    $phone
                )
            );

            if ( $result ) {
                return array(
                    'blocked' => true,
                    'reason'  => sprintf( __( 'Phone number is blacklisted: %s', 'fraud-detection' ), $result->reason ),
                    'permanent' => (bool) $result->is_permanent,
                );
            }
        }

        // Check IP
        if ( ! empty( $ip ) ) {
            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT reason, is_permanent FROM {$table} WHERE entry_type = 'ip' AND entry_value = %s",
                    $ip
                )
            );

            if ( $result ) {
                return array(
                    'blocked' => true,
                    'reason'  => sprintf( __( 'IP address is blacklisted: %s', 'fraud-detection' ), $result->reason ),
                    'permanent' => (bool) $result->is_permanent,
                );
            }
        }

        return array(
            'blocked' => false,
            'reason'  => '',
            'permanent' => false,
        );
    }

    /**
     * Check daily order limit for phone number
     *
     * @param string $phone Phone number (normalized)
     * @return array Result with exceeded status, count, and limit
     */
    private function check_daily_limit( $phone ) {
        if ( empty( $phone ) ) {
            return array(
                'exceeded' => false,
                'count'    => 0,
                'limit'    => 0,
            );
        }

        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();
        $limit = absint( get_option( 'fraud_detection_daily_limit', 3 ) );

        // Count orders from this phone number today
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE customer_phone_normalized = %s 
                AND is_blocked = 0
                AND DATE(date_created) = CURDATE()",
                $phone
            )
        );

        return array(
            'exceeded' => $count >= $limit,
            'count'    => absint( $count ),
            'limit'    => $limit,
        );
    }

    /**
     * Check duplicate email orders today
     *
     * @param string $email Email address
     * @return array Result with duplicate status and count
     */
    private function check_duplicate_email( $email ) {
        if ( empty( $email ) ) {
            return array(
                'duplicate' => false,
                'count'     => 0,
            );
        }

        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();
        $limit = absint( get_option( 'fraud_detection_daily_limit', 3 ) );

        // Count orders from this email today
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE customer_email = %s 
                AND is_blocked = 0
                AND DATE(date_created) = CURDATE()",
                $email
            )
        );

        return array(
            'duplicate' => $count >= $limit,
            'count'     => absint( $count ),
        );
    }

    /**
     * Send block notification to admin
     *
     * @param string $email Email address
     * @param string $phone Phone number
     * @param string $reason Block reason
     */
    private function send_block_notification( $email, $phone, $reason ) {
        $subject = __( 'Order Blocked - Fraud Detection', 'fraud-detection' );
        
        $message = sprintf(
            __( '<strong>An order has been blocked.</strong><br><br><strong>Email:</strong> %s<br><strong>Phone:</strong> %s<br><strong>Reason:</strong> %s<br><strong>Time:</strong> %s', 'fraud-detection' ),
            esc_html( $email ),
            esc_html( $phone ),
            esc_html( $reason ),
            current_time( 'mysql' )
        );

        fraud_detection_send_admin_notification( $subject, $message );
    }

    /**
     * Send limit notification to admin
     *
     * @param string $email Email address
     * @param string $phone Phone number
     * @param int    $count Current count
     * @param int    $limit Daily limit
     */
    private function send_limit_notification( $email, $phone, $count, $limit ) {
        $subject = __( 'Daily Order Limit Reached - Fraud Detection', 'fraud-detection' );
        
        $message = sprintf(
            __( '<strong>Daily order limit reached.</strong><br><br><strong>Email:</strong> %s<br><strong>Phone:</strong> %s<br><strong>Orders Today:</strong> %d<br><strong>Daily Limit:</strong> %d<br><strong>Time:</strong> %s', 'fraud-detection' ),
            esc_html( $email ),
            esc_html( $phone ),
            $count,
            $limit,
            current_time( 'mysql' )
        );

        fraud_detection_send_admin_notification( $subject, $message );
    }

    /**
     * Check device fingerprint limit
     *
     * @param string $device_fingerprint Device fingerprint hash
     * @return array Result with exceeded status and count
     */
    private function check_device_limit( $device_fingerprint ) {
        if ( empty( $device_fingerprint ) ) {
            return array(
                'exceeded' => false,
                'count'    => 0,
            );
        }

        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();
        $limit = absint( get_option( 'fraud_detection_device_limit', 5 ) );

        // Count orders from this device today
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE device_fingerprint = %s 
                AND is_blocked = 0
                AND DATE(date_created) = CURDATE()",
                $device_fingerprint
            )
        );

        return array(
            'exceeded' => $count >= $limit,
            'count'    => absint( $count ),
        );
    }

    /**
     * Check browser fingerprint limit
     *
     * @param string $browser_fingerprint Browser fingerprint hash
     * @return array Result with exceeded status and count
     */
    private function check_browser_fingerprint_limit( $browser_fingerprint ) {
        if ( empty( $browser_fingerprint ) ) {
            return array(
                'exceeded' => false,
                'count'    => 0,
            );
        }

        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();
        $limit = absint( get_option( 'fraud_detection_device_limit', 5 ) );

        // Count orders from this browser fingerprint today
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE browser_fingerprint = %s 
                AND is_blocked = 0
                AND DATE(date_created) = CURDATE()",
                $browser_fingerprint
            )
        );

        return array(
            'exceeded' => $count >= $limit,
            'count'    => absint( $count ),
        );
    }

    /**
     * Send device limit notification to admin
     *
     * @param string $email Email address
     * @param string $phone Phone number
     * @param string $device_fingerprint Device fingerprint
     * @param int    $count Current count
     */
    private function send_device_limit_notification( $email, $phone, $device_fingerprint, $count ) {
        $subject = __( 'Device Order Limit Reached - Fraud Detection', 'fraud-detection' );
        
        $message = sprintf(
            __( '<strong>Device order limit reached.</strong><br><br><strong>Email:</strong> %s<br><strong>Phone:</strong> %s<br><strong>Device ID:</strong> %s<br><strong>Orders from Device:</strong> %d<br><strong>Time:</strong> %s', 'fraud-detection' ),
            esc_html( $email ),
            esc_html( $phone ),
            esc_html( substr( $device_fingerprint, 0, 12 ) . '...' ),
            $count,
            current_time( 'mysql' )
        );

        fraud_detection_send_admin_notification( $subject, $message );
    }

    /**
     * Log blocked attempt with device data
     *
     * @param string $email Email address
     * @param string $phone Phone number
     * @param string $ip IP address
     * @param array  $device_data Device data
     * @param string $reason Block reason
     */
    private function log_blocked_attempt( $email, $phone, $ip, $device_data, $reason ) {
        global $wpdb;

        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_order_logs_table();

        $wpdb->insert(
            $table,
            array(
                'order_id'                   => 0,
                'customer_email'             => $email,
                'customer_phone'             => $phone,
                'customer_phone_normalized'  => fraud_detection_normalize_phone( $phone ),
                'customer_ip'                => $ip,
                'device_fingerprint'         => $device_data['fingerprint'],
                'browser_fingerprint'        => $device_data['browser_fingerprint'],
                'device_cookie'              => $device_data['device_cookie'],
                'user_agent'                 => $device_data['user_agent'],
                'device_type'                => $device_data['device_type'],
                'browser_name'               => $device_data['browser_info']['name'],
                'order_total'                => 0,
                'is_blocked'                 => 1,
                'block_reason'               => $reason,
                'date_created'               => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s' )
        );
    }
}
