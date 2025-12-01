<?php
/**
 * Helper Functions
 *
 * @package Fraud_Detection
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Normalize phone number to digits only
 *
 * @param string $phone Phone number to normalize
 * @return string Normalized phone number
 */
function fraud_detection_normalize_phone( $phone ) {
    if ( empty( $phone ) ) {
        return '';
    }

    // Remove all non-numeric characters
    $normalized = preg_replace( '/[^0-9]/', '', $phone );

    // Remove leading country code if present (common patterns)
    // For Bangladesh: +880 or 880
    $normalized = preg_replace( '/^880/', '', $normalized );
    
    // For other common country codes, you can add more patterns
    // Example: Remove +1 for US/Canada
    // $normalized = preg_replace( '/^1/', '', $normalized );

    return $normalized;
}

/**
 * Get customer IP address
 *
 * @return string Customer IP address
 */
function fraud_detection_get_customer_ip() {
    $ip = '';

    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
    } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
    } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }

    // Handle multiple IPs (proxy chain)
    if ( strpos( $ip, ',' ) !== false ) {
        $ips = explode( ',', $ip );
        $ip = trim( $ips[0] );
    }

    return $ip;
}

/**
 * Validate email address
 *
 * @param string $email Email to validate
 * @return bool True if valid
 */
function fraud_detection_is_valid_email( $email ) {
    return is_email( $email );
}

/**
 * Validate phone number
 *
 * @param string $phone Phone to validate
 * @return bool True if valid
 */
function fraud_detection_is_valid_phone( $phone ) {
    if ( empty( $phone ) ) {
        return false;
    }

    $normalized = fraud_detection_normalize_phone( $phone );
    
    // Check if phone has at least 10 digits (adjust as needed)
    return strlen( $normalized ) >= 10;
}

/**
 * Send admin notification email
 *
 * @param string $subject Email subject
 * @param string $message Email message
 * @return bool True if email sent
 */
function fraud_detection_send_admin_notification( $subject, $message ) {
    $enabled = get_option( 'fraud_detection_admin_notifications', 'yes' );
    
    if ( 'yes' !== $enabled ) {
        return false;
    }

    $admin_email = get_option( 'fraud_detection_admin_email', get_option( 'admin_email' ) );
    
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    
    $email_message = sprintf(
        '<html><body><h2>%s</h2><p>%s</p><hr><p><small>%s</small></p></body></html>',
        esc_html( $subject ),
        wp_kses_post( $message ),
        esc_html__( 'This is an automated notification from Fraud Detection plugin.', 'fraud-detection' )
    );

    return wp_mail( $admin_email, $subject, $email_message, $headers );
}

/**
 * Format date for display
 *
 * @param string $date Date string
 * @return string Formatted date
 */
function fraud_detection_format_date( $date ) {
    if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
        return '-';
    }

    return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) );
}

/**
 * Get entry type label
 *
 * @param string $type Entry type
 * @return string Label
 */
function fraud_detection_get_entry_type_label( $type ) {
    $types = array(
        'phone' => __( 'Phone', 'fraud-detection' ),
        'email' => __( 'Email', 'fraud-detection' ),
        'ip'    => __( 'IP Address', 'fraud-detection' ),
    );

    return isset( $types[ $type ] ) ? $types[ $type ] : $type;
}
