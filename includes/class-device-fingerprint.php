<?php
/**
 * Device Fingerprint Class
 *
 * Advanced device fingerprinting using browser, cookies, IP, and user agent
 *
 * @package Fraud_Detection
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fraud Detection Device Fingerprint Class
 */
class Fraud_Detection_Device_Fingerprint {

    /**
     * Cookie name for device tracking
     */
    const COOKIE_NAME = 'fraud_detection_device_id';

    /**
     * Cookie expiry (1 year)
     */
    const COOKIE_EXPIRY = 31536000;

    /**
     * Generate device fingerprint
     *
     * @return string Device fingerprint hash
     */
    public static function generate_fingerprint() {
        $components = array();

        // Get IP address
        $components['ip'] = self::get_client_ip();

        // Get user agent
        $components['user_agent'] = self::get_user_agent();

        // Get browser fingerprint from cookie if exists
        $cookie_id = self::get_device_cookie();
        if ( $cookie_id ) {
            $components['cookie_id'] = $cookie_id;
        }

        // Get accept language
        $components['accept_language'] = self::get_accept_language();

        // Get screen resolution from JavaScript (will be set via AJAX)
        $components['screen'] = isset( $_COOKIE['fraud_detection_screen'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['fraud_detection_screen'] ) ) : '';

        // Get timezone offset
        $components['timezone'] = isset( $_COOKIE['fraud_detection_tz'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['fraud_detection_tz'] ) ) : '';

        // Get canvas fingerprint from JavaScript
        $components['canvas'] = isset( $_COOKIE['fraud_detection_canvas'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['fraud_detection_canvas'] ) ) : '';

        // Generate hash
        $fingerprint = md5( wp_json_encode( $components ) );

        return $fingerprint;
    }

    /**
     * Get or create device cookie
     *
     * @return string Device cookie ID
     */
    public static function get_device_cookie() {
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
        }

        // Generate new device ID
        $device_id = wp_generate_uuid4();
        
        // Set cookie
        self::set_device_cookie( $device_id );

        return $device_id;
    }

    /**
     * Set device cookie
     *
     * @param string $device_id Device ID
     */
    public static function set_device_cookie( $device_id ) {
        if ( ! headers_sent() ) {
            setcookie(
                self::COOKIE_NAME,
                $device_id,
                time() + self::COOKIE_EXPIRY,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true // HTTP only
            );
        }
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    public static function get_client_ip() {
        $ip = fraud_detection_get_customer_ip();
        return $ip;
    }

    /**
     * Get user agent
     *
     * @return string User agent
     */
    public static function get_user_agent() {
        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }
        return '';
    }

    /**
     * Get accept language
     *
     * @return string Accept language
     */
    public static function get_accept_language() {
        if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );
        }
        return '';
    }

    /**
     * Get browser info
     *
     * @return array Browser information
     */
    public static function get_browser_info() {
        $user_agent = self::get_user_agent();
        
        $browser = array(
            'name'    => 'Unknown',
            'version' => 'Unknown',
            'platform' => 'Unknown',
        );

        // Detect browser
        if ( preg_match( '/MSIE ([0-9.]+)/', $user_agent, $matches ) ) {
            $browser['name'] = 'Internet Explorer';
            $browser['version'] = $matches[1];
        } elseif ( preg_match( '/Edge\/([0-9.]+)/', $user_agent, $matches ) ) {
            $browser['name'] = 'Edge';
            $browser['version'] = $matches[1];
        } elseif ( preg_match( '/Chrome\/([0-9.]+)/', $user_agent, $matches ) ) {
            $browser['name'] = 'Chrome';
            $browser['version'] = $matches[1];
        } elseif ( preg_match( '/Safari\/([0-9.]+)/', $user_agent, $matches ) ) {
            $browser['name'] = 'Safari';
            $browser['version'] = $matches[1];
        } elseif ( preg_match( '/Firefox\/([0-9.]+)/', $user_agent, $matches ) ) {
            $browser['name'] = 'Firefox';
            $browser['version'] = $matches[1];
        }

        // Detect platform
        if ( preg_match( '/Windows/', $user_agent ) ) {
            $browser['platform'] = 'Windows';
        } elseif ( preg_match( '/Macintosh|Mac OS X/', $user_agent ) ) {
            $browser['platform'] = 'Mac';
        } elseif ( preg_match( '/Linux/', $user_agent ) ) {
            $browser['platform'] = 'Linux';
        } elseif ( preg_match( '/Android/', $user_agent ) ) {
            $browser['platform'] = 'Android';
        } elseif ( preg_match( '/iPhone|iPad|iPod/', $user_agent ) ) {
            $browser['platform'] = 'iOS';
        }

        return $browser;
    }

    /**
     * Check if device is mobile
     *
     * @return bool True if mobile
     */
    public static function is_mobile() {
        $user_agent = self::get_user_agent();
        return preg_match( '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent );
    }

    /**
     * Get device type
     *
     * @return string Device type (mobile, tablet, desktop)
     */
    public static function get_device_type() {
        $user_agent = self::get_user_agent();

        if ( preg_match( '/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $user_agent ) ) {
            return 'tablet';
        }

        if ( self::is_mobile() ) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Generate browser fingerprint hash
     *
     * @return string Browser fingerprint
     */
    public static function generate_browser_fingerprint() {
        $components = array(
            'user_agent'      => self::get_user_agent(),
            'accept_language' => self::get_accept_language(),
            'platform'        => self::get_browser_info()['platform'],
            'device_type'     => self::get_device_type(),
        );

        // Add JavaScript-based fingerprints if available
        if ( isset( $_COOKIE['fraud_detection_plugins'] ) ) {
            $components['plugins'] = sanitize_text_field( wp_unslash( $_COOKIE['fraud_detection_plugins'] ) );
        }

        if ( isset( $_COOKIE['fraud_detection_fonts'] ) ) {
            $components['fonts'] = sanitize_text_field( wp_unslash( $_COOKIE['fraud_detection_fonts'] ) );
        }

        if ( isset( $_COOKIE['fraud_detection_webgl'] ) ) {
            $components['webgl'] = sanitize_text_field( wp_unslash( $_COOKIE['fraud_detection_webgl'] ) );
        }

        return md5( wp_json_encode( $components ) );
    }

    /**
     * Get comprehensive device data
     *
     * @return array Device data
     */
    public static function get_device_data() {
        return array(
            'fingerprint'         => self::generate_fingerprint(),
            'browser_fingerprint' => self::generate_browser_fingerprint(),
            'device_cookie'       => self::get_device_cookie(),
            'ip_address'          => self::get_client_ip(),
            'user_agent'          => self::get_user_agent(),
            'browser_info'        => self::get_browser_info(),
            'device_type'         => self::get_device_type(),
            'is_mobile'           => self::is_mobile(),
            'accept_language'     => self::get_accept_language(),
        );
    }

    /**
     * Enqueue fingerprinting script
     */
    public static function enqueue_fingerprint_script() {
        if ( is_checkout() || is_cart() ) {
            wp_enqueue_script(
                'fraud-detection-fingerprint',
                FRAUD_DETECTION_PLUGIN_URL . 'assets/js/fingerprint.js',
                array( 'jquery' ),
                FRAUD_DETECTION_VERSION,
                true
            );
        }
    }
}

// Hook to enqueue script
add_action( 'wp_enqueue_scripts', array( 'Fraud_Detection_Device_Fingerprint', 'enqueue_fingerprint_script' ) );

// Initialize device cookie on page load
add_action( 'init', array( 'Fraud_Detection_Device_Fingerprint', 'get_device_cookie' ) );
