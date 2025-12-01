<?php
/**
 * Admin Settings Class
 *
 * Manages admin settings pages
 *
 * @package Fraud_Detection
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fraud Detection Admin Settings Class
 */
class Fraud_Detection_Admin_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_items' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    /**
     * Add menu items
     */
    public function add_menu_items() {
        add_submenu_page(
            'woocommerce',
            __( 'Fraud Detection', 'fraud-detection' ),
            __( 'Fraud Detection', 'fraud-detection' ),
            'manage_woocommerce',
            'fraud-detection',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_fraud-detection' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'fraud-detection-admin',
            FRAUD_DETECTION_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            FRAUD_DETECTION_VERSION
        );

        wp_enqueue_script(
            'fraud-detection-admin',
            FRAUD_DETECTION_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            FRAUD_DETECTION_VERSION,
            true
        );

        wp_localize_script(
            'fraud-detection-admin',
            'fraudDetectionAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'fraud_detection_admin' ),
            )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'fraud_detection_settings', 'fraud_detection_enabled' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_daily_limit' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_check_email' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_check_phone' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_normalize_phone' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_block_message' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_limit_message' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_whitelist_bypass_limit' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_log_retention_days' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_admin_notifications' );
        register_setting( 'fraud_detection_settings', 'fraud_detection_admin_email' );
    }

    /**
     * Handle admin actions
     */
    public function handle_actions() {
        if ( ! isset( $_POST['fraud_detection_action'] ) ) {
            return;
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'fraud_detection_action' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['fraud_detection_action'] ) );
        $plugin = Fraud_Detection_Plugin::get_instance();

        switch ( $action ) {
            case 'add_to_blacklist':
                $this->handle_add_to_blacklist();
                break;

            case 'add_to_whitelist':
                $this->handle_add_to_whitelist();
                break;

            case 'remove_from_blacklist':
                if ( isset( $_POST['entry_id'] ) ) {
                    $entry_id = absint( $_POST['entry_id'] );
                    $plugin->list_manager->remove_from_blacklist( $entry_id );
                    $this->add_admin_notice( __( 'Entry removed from blacklist.', 'fraud-detection' ), 'success' );
                }
                break;

            case 'remove_from_whitelist':
                if ( isset( $_POST['entry_id'] ) ) {
                    $entry_id = absint( $_POST['entry_id'] );
                    $plugin->list_manager->remove_from_whitelist( $entry_id );
                    $this->add_admin_notice( __( 'Entry removed from whitelist.', 'fraud-detection' ), 'success' );
                }
                break;

            case 'import_csv':
                $this->handle_csv_import();
                break;
        }
    }

    /**
     * Handle add to blacklist
     */
    private function handle_add_to_blacklist() {
        $plugin = Fraud_Detection_Plugin::get_instance();

        $entry_type = isset( $_POST['entry_type'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_type'] ) ) : 'phone';
        $entry_value = isset( $_POST['entry_value'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_value'] ) ) : '';
        $is_permanent = isset( $_POST['is_permanent'] ) && 'yes' === $_POST['is_permanent'];
        $reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

        if ( empty( $entry_value ) ) {
            $this->add_admin_notice( __( 'Entry value is required.', 'fraud-detection' ), 'error' );
            return;
        }

        $result = $plugin->list_manager->add_to_blacklist( $entry_type, $entry_value, $is_permanent, $reason );

        if ( $result ) {
            $this->add_admin_notice( __( 'Entry added to blacklist successfully.', 'fraud-detection' ), 'success' );
        } else {
            $this->add_admin_notice( __( 'Failed to add entry to blacklist.', 'fraud-detection' ), 'error' );
        }
    }

    /**
     * Handle add to whitelist
     */
    private function handle_add_to_whitelist() {
        $plugin = Fraud_Detection_Plugin::get_instance();

        $entry_type = isset( $_POST['entry_type'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_type'] ) ) : 'phone';
        $entry_value = isset( $_POST['entry_value'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_value'] ) ) : '';
        $bypass_limit = isset( $_POST['bypass_limit'] ) && 'yes' === $_POST['bypass_limit'];
        $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

        if ( empty( $entry_value ) ) {
            $this->add_admin_notice( __( 'Entry value is required.', 'fraud-detection' ), 'error' );
            return;
        }

        $result = $plugin->list_manager->add_to_whitelist( $entry_type, $entry_value, $bypass_limit, $notes );

        if ( $result ) {
            $this->add_admin_notice( __( 'Entry added to whitelist successfully.', 'fraud-detection' ), 'success' );
        } else {
            $this->add_admin_notice( __( 'Failed to add entry to whitelist.', 'fraud-detection' ), 'error' );
        }
    }

    /**
     * Handle CSV import
     */
    private function handle_csv_import() {
        if ( ! isset( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
            $this->add_admin_notice( __( 'Please select a CSV file to import.', 'fraud-detection' ), 'error' );
            return;
        }

        $list_type = isset( $_POST['list_type'] ) ? sanitize_text_field( wp_unslash( $_POST['list_type'] ) ) : 'blacklist';
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $result = $plugin->list_manager->import_from_csv( $_FILES['csv_file']['tmp_name'], $list_type );

        if ( $result['success'] ) {
            $message = sprintf(
                __( 'Successfully imported %d entries.', 'fraud-detection' ),
                $result['count']
            );
            $this->add_admin_notice( $message, 'success' );

            if ( ! empty( $result['errors'] ) ) {
                foreach ( $result['errors'] as $error ) {
                    $this->add_admin_notice( $error, 'warning' );
                }
            }
        } else {
            $this->add_admin_notice( $result['message'], 'error' );
        }
    }

    /**
     * Add admin notice
     */
    private function add_admin_notice( $message, $type = 'success' ) {
        set_transient( 'fraud_detection_admin_notice', array( 'message' => $message, 'type' => $type ), 30 );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
        
        ?>
        <div class="wrap fraud-detection-admin">
            <h1><?php esc_html_e( 'Fraud Detection & Order Filtering', 'fraud-detection' ); ?></h1>

            <?php $this->display_admin_notices(); ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=fraud-detection&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'fraud-detection' ); ?>
                </a>
                <a href="?page=fraud-detection&tab=blacklist" class="nav-tab <?php echo 'blacklist' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Blacklist', 'fraud-detection' ); ?>
                </a>
                <a href="?page=fraud-detection&tab=whitelist" class="nav-tab <?php echo 'whitelist' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Whitelist', 'fraud-detection' ); ?>
                </a>
                <a href="?page=fraud-detection&tab=logs" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Order Logs', 'fraud-detection' ); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'blacklist':
                        $this->render_blacklist_tab();
                        break;
                    case 'whitelist':
                        $this->render_whitelist_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    default:
                        $this->render_settings_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display admin notices
     */
    private function display_admin_notices() {
        $notice = get_transient( 'fraud_detection_admin_notice' );
        
        if ( $notice ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $notice['type'] ),
                esc_html( $notice['message'] )
            );
            delete_transient( 'fraud_detection_admin_notice' );
        }
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'fraud_detection_settings' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fraud_detection_enabled"><?php esc_html_e( 'Enable Fraud Detection', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="fraud_detection_enabled" id="fraud_detection_enabled" value="yes" <?php checked( get_option( 'fraud_detection_enabled', 'yes' ), 'yes' ); ?>>
                        <p class="description"><?php esc_html_e( 'Enable or disable fraud detection system.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_daily_limit"><?php esc_html_e( 'Daily Order Limit', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="fraud_detection_daily_limit" id="fraud_detection_daily_limit" value="<?php echo esc_attr( get_option( 'fraud_detection_daily_limit', 3 ) ); ?>" min="1" class="small-text">
                        <p class="description"><?php esc_html_e( 'Maximum number of orders allowed per day from the same phone number.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_check_phone"><?php esc_html_e( 'Check Phone Numbers', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="fraud_detection_check_phone" id="fraud_detection_check_phone" value="yes" <?php checked( get_option( 'fraud_detection_check_phone', 'yes' ), 'yes' ); ?>>
                        <p class="description"><?php esc_html_e( 'Enable phone number duplicate detection and daily limits.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_check_email"><?php esc_html_e( 'Check Email Addresses', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="fraud_detection_check_email" id="fraud_detection_check_email" value="yes" <?php checked( get_option( 'fraud_detection_check_email', 'yes' ), 'yes' ); ?>>
                        <p class="description"><?php esc_html_e( 'Enable email duplicate detection and daily limits.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_normalize_phone"><?php esc_html_e( 'Normalize Phone Numbers', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="fraud_detection_normalize_phone" id="fraud_detection_normalize_phone" value="yes" <?php checked( get_option( 'fraud_detection_normalize_phone', 'yes' ), 'yes' ); ?>>
                        <p class="description"><?php esc_html_e( 'Remove spaces, dashes, and country codes from phone numbers before comparison.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_block_message"><?php esc_html_e( 'Block Message', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <textarea name="fraud_detection_block_message" id="fraud_detection_block_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'fraud_detection_block_message' ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Message displayed when an order is blocked due to blacklist.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_limit_message"><?php esc_html_e( 'Limit Reached Message', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <textarea name="fraud_detection_limit_message" id="fraud_detection_limit_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'fraud_detection_limit_message' ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Message displayed when daily order limit is reached.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_log_retention_days"><?php esc_html_e( 'Log Retention Days', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="fraud_detection_log_retention_days" id="fraud_detection_log_retention_days" value="<?php echo esc_attr( get_option( 'fraud_detection_log_retention_days', 30 ) ); ?>" min="1" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of days to keep order logs. Set to 0 to keep forever.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_admin_notifications"><?php esc_html_e( 'Admin Notifications', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="fraud_detection_admin_notifications" id="fraud_detection_admin_notifications" value="yes" <?php checked( get_option( 'fraud_detection_admin_notifications', 'yes' ), 'yes' ); ?>>
                        <p class="description"><?php esc_html_e( 'Send email notifications to admin when orders are blocked.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="fraud_detection_admin_email"><?php esc_html_e( 'Admin Email', 'fraud-detection' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="fraud_detection_admin_email" id="fraud_detection_admin_email" value="<?php echo esc_attr( get_option( 'fraud_detection_admin_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Email address to receive fraud detection notifications.', 'fraud-detection' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render blacklist tab
     */
    private function render_blacklist_tab() {
        $plugin = Fraud_Detection_Plugin::get_instance();
        
        // Get current page
        $paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset = ( $paged - 1 ) * $per_page;

        // Get search term
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        $args = array(
            'search' => $search,
            'limit'  => $per_page,
            'offset' => $offset,
        );

        $entries = $plugin->list_manager->get_blacklist( $args );
        $total = $plugin->list_manager->get_blacklist_count( $args );
        $total_pages = ceil( $total / $per_page );

        ?>
        <div class="fraud-detection-blacklist">
            <h2><?php esc_html_e( 'Blacklist Management', 'fraud-detection' ); ?></h2>

            <div class="fraud-detection-actions">
                <button type="button" class="button button-primary" onclick="document.getElementById('add-blacklist-form').style.display='block';">
                    <?php esc_html_e( 'Add to Blacklist', 'fraud-detection' ); ?>
                </button>
                <button type="button" class="button" onclick="document.getElementById('import-csv-form').style.display='block';">
                    <?php esc_html_e( 'Import CSV', 'fraud-detection' ); ?>
                </button>
            </div>

            <!-- Add to Blacklist Form -->
            <div id="add-blacklist-form" style="display:none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccc;">
                <h3><?php esc_html_e( 'Add Entry to Blacklist', 'fraud-detection' ); ?></h3>
                <form method="post">
                    <?php wp_nonce_field( 'fraud_detection_action' ); ?>
                    <input type="hidden" name="fraud_detection_action" value="add_to_blacklist">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="entry_type"><?php esc_html_e( 'Type', 'fraud-detection' ); ?></label></th>
                            <td>
                                <select name="entry_type" id="entry_type">
                                    <option value="phone"><?php esc_html_e( 'Phone', 'fraud-detection' ); ?></option>
                                    <option value="email"><?php esc_html_e( 'Email', 'fraud-detection' ); ?></option>
                                    <option value="ip"><?php esc_html_e( 'IP Address', 'fraud-detection' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="entry_value"><?php esc_html_e( 'Value', 'fraud-detection' ); ?></label></th>
                            <td><input type="text" name="entry_value" id="entry_value" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="is_permanent"><?php esc_html_e( 'Permanent Block', 'fraud-detection' ); ?></label></th>
                            <td>
                                <input type="checkbox" name="is_permanent" id="is_permanent" value="yes">
                                <span class="description"><?php esc_html_e( 'Permanently block all orders from this entry.', 'fraud-detection' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="reason"><?php esc_html_e( 'Reason', 'fraud-detection' ); ?></label></th>
                            <td><textarea name="reason" id="reason" rows="3" class="large-text"></textarea></td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Add to Blacklist', 'fraud-detection' ); ?></button>
                        <button type="button" class="button" onclick="document.getElementById('add-blacklist-form').style.display='none';"><?php esc_html_e( 'Cancel', 'fraud-detection' ); ?></button>
                    </p>
                </form>
            </div>

            <!-- Import CSV Form -->
            <div id="import-csv-form" style="display:none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccc;">
                <h3><?php esc_html_e( 'Import from CSV', 'fraud-detection' ); ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'fraud_detection_action' ); ?>
                    <input type="hidden" name="fraud_detection_action" value="import_csv">
                    <input type="hidden" name="list_type" value="blacklist">
                    
                    <p>
                        <label for="csv_file"><?php esc_html_e( 'CSV File:', 'fraud-detection' ); ?></label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    </p>
                    <p class="description">
                        <?php esc_html_e( 'CSV format: type,value,reason,is_permanent (e.g., phone,1234567890,Fraudulent,yes)', 'fraud-detection' ); ?>
                    </p>
                    
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'fraud-detection' ); ?></button>
                        <button type="button" class="button" onclick="document.getElementById('import-csv-form').style.display='none';"><?php esc_html_e( 'Cancel', 'fraud-detection' ); ?></button>
                    </p>
                </form>
            </div>

            <!-- Search Form -->
            <form method="get" style="margin: 20px 0;">
                <input type="hidden" name="page" value="fraud-detection">
                <input type="hidden" name="tab" value="blacklist">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'fraud-detection' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'fraud-detection' ); ?></button>
            </form>

            <!-- Blacklist Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Value', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Permanent', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Reason', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Date Added', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'fraud-detection' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $entries ) : ?>
                        <?php foreach ( $entries as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( $entry->id ); ?></td>
                                <td><?php echo esc_html( fraud_detection_get_entry_type_label( $entry->entry_type ) ); ?></td>
                                <td><strong><?php echo esc_html( $entry->entry_value ); ?></strong></td>
                                <td><?php echo $entry->is_permanent ? '<span style="color: red;">&#10004;</span>' : '-'; ?></td>
                                <td><?php echo esc_html( $entry->reason ); ?></td>
                                <td><?php echo esc_html( fraud_detection_format_date( $entry->date_added ) ); ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'fraud_detection_action' ); ?>
                                        <input type="hidden" name="fraud_detection_action" value="remove_from_blacklist">
                                        <input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry->id ); ?>">
                                        <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'fraud-detection' ); ?>');">
                                            <?php esc_html_e( 'Remove', 'fraud-detection' ); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No entries found.', 'fraud-detection' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(
                            array(
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'prev_text' => __( '&laquo;', 'fraud-detection' ),
                                'next_text' => __( '&raquo;', 'fraud-detection' ),
                                'total'     => $total_pages,
                                'current'   => $paged,
                            )
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render whitelist tab
     */
    private function render_whitelist_tab() {
        $plugin = Fraud_Detection_Plugin::get_instance();
        
        $paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset = ( $paged - 1 ) * $per_page;
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        $args = array(
            'search' => $search,
            'limit'  => $per_page,
            'offset' => $offset,
        );

        $entries = $plugin->list_manager->get_whitelist( $args );
        $total = $plugin->list_manager->get_whitelist_count( $args );
        $total_pages = ceil( $total / $per_page );

        ?>
        <div class="fraud-detection-whitelist">
            <h2><?php esc_html_e( 'Whitelist Management', 'fraud-detection' ); ?></h2>

            <div class="fraud-detection-actions">
                <button type="button" class="button button-primary" onclick="document.getElementById('add-whitelist-form').style.display='block';">
                    <?php esc_html_e( 'Add to Whitelist', 'fraud-detection' ); ?>
                </button>
            </div>

            <!-- Add to Whitelist Form -->
            <div id="add-whitelist-form" style="display:none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccc;">
                <h3><?php esc_html_e( 'Add Entry to Whitelist', 'fraud-detection' ); ?></h3>
                <form method="post">
                    <?php wp_nonce_field( 'fraud_detection_action' ); ?>
                    <input type="hidden" name="fraud_detection_action" value="add_to_whitelist">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="entry_type"><?php esc_html_e( 'Type', 'fraud-detection' ); ?></label></th>
                            <td>
                                <select name="entry_type" id="entry_type">
                                    <option value="phone"><?php esc_html_e( 'Phone', 'fraud-detection' ); ?></option>
                                    <option value="email"><?php esc_html_e( 'Email', 'fraud-detection' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="entry_value"><?php esc_html_e( 'Value', 'fraud-detection' ); ?></label></th>
                            <td><input type="text" name="entry_value" id="entry_value" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="bypass_limit"><?php esc_html_e( 'Bypass Daily Limit', 'fraud-detection' ); ?></label></th>
                            <td>
                                <input type="checkbox" name="bypass_limit" id="bypass_limit" value="yes" checked>
                                <span class="description"><?php esc_html_e( 'Allow unlimited orders per day.', 'fraud-detection' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="notes"><?php esc_html_e( 'Notes', 'fraud-detection' ); ?></label></th>
                            <td><textarea name="notes" id="notes" rows="3" class="large-text"></textarea></td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Add to Whitelist', 'fraud-detection' ); ?></button>
                        <button type="button" class="button" onclick="document.getElementById('add-whitelist-form').style.display='none';"><?php esc_html_e( 'Cancel', 'fraud-detection' ); ?></button>
                    </p>
                </form>
            </div>

            <!-- Search Form -->
            <form method="get" style="margin: 20px 0;">
                <input type="hidden" name="page" value="fraud-detection">
                <input type="hidden" name="tab" value="whitelist">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'fraud-detection' ); ?>">
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'fraud-detection' ); ?></button>
            </form>

            <!-- Whitelist Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Value', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Bypass Limit', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Notes', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Date Added', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'fraud-detection' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $entries ) : ?>
                        <?php foreach ( $entries as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( $entry->id ); ?></td>
                                <td><?php echo esc_html( fraud_detection_get_entry_type_label( $entry->entry_type ) ); ?></td>
                                <td><strong><?php echo esc_html( $entry->entry_value ); ?></strong></td>
                                <td><?php echo $entry->bypass_daily_limit ? '<span style="color: green;">&#10004;</span>' : '-'; ?></td>
                                <td><?php echo esc_html( $entry->notes ); ?></td>
                                <td><?php echo esc_html( fraud_detection_format_date( $entry->date_added ) ); ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'fraud_detection_action' ); ?>
                                        <input type="hidden" name="fraud_detection_action" value="remove_from_whitelist">
                                        <input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry->id ); ?>">
                                        <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'fraud-detection' ); ?>');">
                                            <?php esc_html_e( 'Remove', 'fraud-detection' ); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No entries found.', 'fraud-detection' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(
                            array(
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'prev_text' => __( '&laquo;', 'fraud-detection' ),
                                'next_text' => __( '&raquo;', 'fraud-detection' ),
                                'total'     => $total_pages,
                                'current'   => $paged,
                            )
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render logs tab
     */
    private function render_logs_tab() {
        $plugin = Fraud_Detection_Plugin::get_instance();
        $logs = $plugin->order_tracker->get_recent_orders( 50 );

        ?>
        <div class="fraud-detection-logs">
            <h2><?php esc_html_e( 'Order Logs', 'fraud-detection' ); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Order ID', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'IP Address', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'fraud-detection' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'fraud-detection' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $logs ) : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td>
                                    <?php if ( $log->order_id ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log->order_id . '&action=edit' ) ); ?>">
                                            #<?php echo esc_html( $log->order_id ); ?>
                                        </a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $log->customer_email ); ?></td>
                                <td><?php echo esc_html( $log->customer_phone_normalized ); ?></td>
                                <td><?php echo esc_html( $log->customer_ip ); ?></td>
                                <td><?php echo esc_html( wc_price( $log->order_total ) ); ?></td>
                                <td>
                                    <?php if ( $log->is_blocked ) : ?>
                                        <span style="color: red;"><?php esc_html_e( 'Blocked', 'fraud-detection' ); ?></span>
                                        <?php if ( $log->block_reason ) : ?>
                                            <br><small><?php echo esc_html( $log->block_reason ); ?></small>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span style="color: green;"><?php esc_html_e( 'Allowed', 'fraud-detection' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( fraud_detection_format_date( $log->date_created ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No logs found.', 'fraud-detection' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
