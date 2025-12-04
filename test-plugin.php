<?php
/**
 * Test Plugin Functionality
 * 
 * Place this file in the plugin root and access it via:
 * wp-admin/admin.php?page=fraud-detection-test
 */

// Add admin menu for testing
add_action('admin_menu', 'fraud_detection_add_test_page');

function fraud_detection_add_test_page() {
    add_submenu_page(
        'woocommerce',
        'Fraud Detection Test',
        'FD Test',
        'manage_options',
        'fraud-detection-test',
        'fraud_detection_test_page'
    );
}

function fraud_detection_test_page() {
    global $wpdb;
    
    echo '<div class="wrap">';
    echo '<h1>Fraud Detection Plugin Test</h1>';
    
    // Check if plugin is loaded
    echo '<h2>1. Plugin Status</h2>';
    if (class_exists('Fraud_Detection_Plugin')) {
        echo '<p style="color: green;">✓ Plugin class loaded</p>';
        $plugin = Fraud_Detection_Plugin::get_instance();
        echo '<p>Database object: ' . (is_object($plugin->database) ? '✓ Loaded' : '✗ Not loaded') . '</p>';
        echo '<p>Detector object: ' . (is_object($plugin->detector) ? '✓ Loaded' : '✗ Not loaded') . '</p>';
        echo '<p>Order tracker object: ' . (is_object($plugin->order_tracker) ? '✓ Loaded' : '✗ Not loaded') . '</p>';
    } else {
        echo '<p style="color: red;">✗ Plugin class not loaded</p>';
    }
    
    // Check database tables
    echo '<h2>2. Database Tables</h2>';
    $blacklist_table = $wpdb->prefix . 'fraud_blacklist';
    $whitelist_table = $wpdb->prefix . 'fraud_whitelist';
    $order_logs_table = $wpdb->prefix . 'fraud_order_logs';
    
    $tables = array(
        'Blacklist' => $blacklist_table,
        'Whitelist' => $whitelist_table,
        'Order Logs' => $order_logs_table,
    );
    
    foreach ($tables as $name => $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            echo "<p style='color: green;'>✓ $name table exists ($count records)</p>";
        } else {
            echo "<p style='color: red;'>✗ $name table does not exist</p>";
        }
    }
    
    // Check order logs details
    echo '<h2>3. Recent Order Logs</h2>';
    $logs = $wpdb->get_results("SELECT * FROM $order_logs_table ORDER BY date_created DESC LIMIT 10");
    if ($logs) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Order ID</th><th>Phone</th><th>Email</th><th>IP</th><th>Device FP</th><th>Blocked</th><th>Date</th>';
        echo '</tr></thead><tbody>';
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log->order_id) . '</td>';
            echo '<td>' . esc_html($log->customer_phone_normalized) . '</td>';
            echo '<td>' . esc_html($log->customer_email) . '</td>';
            echo '<td>' . esc_html($log->customer_ip) . '</td>';
            echo '<td>' . esc_html(substr($log->device_fingerprint ?? '', 0, 10)) . '...</td>';
            echo '<td>' . ($log->is_blocked ? '<span style="color:red;">Blocked</span>' : '<span style="color:green;">Allowed</span>') . '</td>';
            echo '<td>' . esc_html($log->date_created) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No order logs found</p>';
    }
    
    // Check plugin settings
    echo '<h2>4. Plugin Settings</h2>';
    $settings = array(
        'fraud_detection_enabled' => get_option('fraud_detection_enabled'),
        'fraud_detection_daily_limit' => get_option('fraud_detection_daily_limit'),
        'fraud_detection_check_phone' => get_option('fraud_detection_check_phone'),
        'fraud_detection_check_email' => get_option('fraud_detection_check_email'),
        'fraud_detection_check_device_fingerprint' => get_option('fraud_detection_check_device_fingerprint'),
        'fraud_detection_device_limit' => get_option('fraud_detection_device_limit'),
    );
    
    echo '<table class="wp-list-table widefat fixed striped">';
    foreach ($settings as $key => $value) {
        echo '<tr>';
        echo '<td><strong>' . esc_html($key) . '</strong></td>';
        echo '<td>' . esc_html($value !== false ? $value : 'Not set') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Check WooCommerce hooks
    echo '<h2>5. WooCommerce Hooks</h2>';
    $hooks_check = array(
        'woocommerce_checkout_process' => has_action('woocommerce_checkout_process'),
        'woocommerce_checkout_order_processed' => has_action('woocommerce_checkout_order_processed'),
    );
    
    foreach ($hooks_check as $hook => $has_hook) {
        if ($has_hook) {
            echo "<p style='color: green;'>✓ Hook '$hook' is registered</p>";
        } else {
            echo "<p style='color: red;'>✗ Hook '$hook' is NOT registered</p>";
        }
    }
    
    echo '</div>';
}
