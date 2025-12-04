<?php
/**
 * Plugin Name: Fraud Detection & Duplicate Order Filter
 * Plugin URI: https://yourwebsite.com/fraud-detection
 * Description: Prevents duplicate orders by detecting duplicate mobile/email, implements daily order limits, and manages blacklist/whitelist with permanent blocking capabilities.
 * Version: 1.0.0
 * Author: Mushfikur Rahman
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fraud-detection
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'FRAUD_DETECTION_VERSION', '1.0.0' );
define( 'FRAUD_DETECTION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FRAUD_DETECTION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FRAUD_DETECTION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Fraud Detection Plugin Class
 */
class Fraud_Detection_Plugin {

    /**
     * Instance of this class
     *
     * @var object
     */
    private static $instance = null;

    /**
     * Database handler
     *
     * @var Fraud_Detection_Database
     */
    public $database;

    /**
     * Fraud detector
     *
     * @var Fraud_Detection_Detector
     */
    public $detector;

    /**
     * Order tracker
     *
     * @var Fraud_Detection_Order_Tracker
     */
    public $order_tracker;

    /**
     * List manager
     *
     * @var Fraud_Detection_List_Manager
     */
    public $list_manager;

    /**
     * Admin settings
     *
     * @var Fraud_Detection_Admin_Settings
     */
    public $admin_settings;

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Declare WooCommerce HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
        
        // Check if WooCommerce is active
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        
        // Activation and deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Declare WooCommerce HPOS compatibility
     */
    public function declare_wc_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Load plugin text domain
        load_plugin_textdomain( 'fraud-detection', false, dirname( FRAUD_DETECTION_PLUGIN_BASENAME ) . '/languages' );

        // Include required files
        $this->includes();

        // Initialize components
        $this->database = new Fraud_Detection_Database();
        $this->list_manager = new Fraud_Detection_List_Manager();
        $this->order_tracker = new Fraud_Detection_Order_Tracker();
        $this->detector = new Fraud_Detection_Detector();

        // Initialize admin
        if ( is_admin() ) {
            $this->admin_settings = new Fraud_Detection_Admin_Settings();
        }

        // Hook into WooCommerce checkout
        add_action( 'woocommerce_checkout_process', array( $this->detector, 'validate_checkout' ), 10 );
        add_action( 'woocommerce_checkout_order_processed', array( $this->order_tracker, 'track_order' ), 10, 3 );
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once FRAUD_DETECTION_PLUGIN_DIR . 'includes/class-database.php';
        require_once FRAUD_DETECTION_PLUGIN_DIR . 'includes/class-device-fingerprint.php';
        require_once FRAUD_DETECTION_PLUGIN_DIR . 'includes/class-fraud-detector.php';
        require_once FRAUD_DETECTION_PLUGIN_DIR . 'includes/class-order-tracker.php';
        require_once FRAUD_DETECTION_PLUGIN_DIR . 'includes/class-list-manager.php';
        require_once FRAUD_DETECTION_PLUGIN_DIR . 'includes/helpers.php';

        if ( is_admin() ) {
            require_once FRAUD_DETECTION_PLUGIN_DIR . 'admin/class-admin-settings.php';
            
            // Include test page for debugging
            if ( file_exists( FRAUD_DETECTION_PLUGIN_DIR . 'test-plugin.php' ) ) {
                require_once FRAUD_DETECTION_PLUGIN_DIR . 'test-plugin.php';
            }
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Check for WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( FRAUD_DETECTION_PLUGIN_BASENAME );
            wp_die(
                __( 'Fraud Detection plugin requires WooCommerce to be installed and active.', 'fraud-detection' ),
                __( 'Plugin Activation Error', 'fraud-detection' ),
                array( 'back_link' => true )
            );
        }

        // Include database class
        require_once FRAUD_DETECTION_PLUGIN_DIR . 'includes/class-database.php';
        
        // Create database tables
        $database = new Fraud_Detection_Database();
        $database->create_tables();

        // Set default options
        $this->set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'fraud_detection_enabled' => 'yes',
            'fraud_detection_daily_limit' => 3,
            'fraud_detection_check_email' => 'yes',
            'fraud_detection_check_phone' => 'yes',
            'fraud_detection_normalize_phone' => 'yes',
            'fraud_detection_check_device_fingerprint' => 'yes',
            'fraud_detection_check_browser_fingerprint' => 'yes',
            'fraud_detection_device_limit' => 5,
            'fraud_detection_block_message' => __( 'Your order has been blocked due to security concerns. Please contact support.', 'fraud-detection' ),
            'fraud_detection_limit_message' => __( 'You have reached the maximum number of orders allowed per day from this phone number.', 'fraud-detection' ),
            'fraud_detection_device_limit_message' => __( 'You have reached the maximum number of orders allowed from this device.', 'fraud-detection' ),
            'fraud_detection_whitelist_bypass_limit' => 'yes',
            'fraud_detection_log_retention_days' => 30,
            'fraud_detection_admin_notifications' => 'yes',
            'fraud_detection_admin_email' => get_option( 'admin_email' ),
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: %s: WooCommerce download URL */
                        __( '<strong>Fraud Detection</strong> requires WooCommerce to be installed and active. You can download WooCommerce %s.', 'fraud-detection' ),
                        '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">' . __( 'here', 'fraud-detection' ) . '</a>'
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Initialize the plugin
 */
function fraud_detection_init() {
    return Fraud_Detection_Plugin::get_instance();
}

// Start the plugin
fraud_detection_init();
