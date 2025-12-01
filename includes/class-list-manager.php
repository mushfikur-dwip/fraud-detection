<?php
/**
 * List Manager Class
 *
 * Manages blacklist and whitelist entries
 *
 * @package Fraud_Detection
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fraud Detection List Manager Class
 */
class Fraud_Detection_List_Manager {

    /**
     * Add entry to blacklist
     *
     * @param string $type Entry type (phone, email, ip)
     * @param string $value Entry value
     * @param bool   $is_permanent Is permanent block
     * @param string $reason Reason for blocking
     * @return bool|int False on failure, entry ID on success
     */
    public function add_to_blacklist( $type, $value, $is_permanent = false, $reason = '' ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_blacklist_table();

        // Normalize value if it's a phone
        if ( 'phone' === $type && 'yes' === get_option( 'fraud_detection_normalize_phone', 'yes' ) ) {
            $value = fraud_detection_normalize_phone( $value );
        }

        // Check if already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE entry_type = %s AND entry_value = %s",
                $type,
                $value
            )
        );

        if ( $exists ) {
            // Update existing entry
            $result = $wpdb->update(
                $table,
                array(
                    'is_permanent' => $is_permanent ? 1 : 0,
                    'reason'       => $reason,
                    'added_by'     => get_current_user_id(),
                ),
                array(
                    'id' => $exists,
                ),
                array( '%d', '%s', '%d' ),
                array( '%d' )
            );

            return $exists;
        }

        // Insert new entry
        $result = $wpdb->insert(
            $table,
            array(
                'entry_type'   => $type,
                'entry_value'  => $value,
                'is_permanent' => $is_permanent ? 1 : 0,
                'reason'       => $reason,
                'added_by'     => get_current_user_id(),
                'date_added'   => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%d', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Add entry to whitelist
     *
     * @param string $type Entry type (phone, email)
     * @param string $value Entry value
     * @param bool   $bypass_limit Bypass daily limit
     * @param string $notes Notes
     * @return bool|int False on failure, entry ID on success
     */
    public function add_to_whitelist( $type, $value, $bypass_limit = true, $notes = '' ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_whitelist_table();

        // Normalize value if it's a phone
        if ( 'phone' === $type && 'yes' === get_option( 'fraud_detection_normalize_phone', 'yes' ) ) {
            $value = fraud_detection_normalize_phone( $value );
        }

        // Check if already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE entry_type = %s AND entry_value = %s",
                $type,
                $value
            )
        );

        if ( $exists ) {
            // Update existing entry
            $result = $wpdb->update(
                $table,
                array(
                    'bypass_daily_limit' => $bypass_limit ? 1 : 0,
                    'notes'              => $notes,
                    'added_by'           => get_current_user_id(),
                ),
                array(
                    'id' => $exists,
                ),
                array( '%d', '%s', '%d' ),
                array( '%d' )
            );

            return $exists;
        }

        // Insert new entry
        $result = $wpdb->insert(
            $table,
            array(
                'entry_type'         => $type,
                'entry_value'        => $value,
                'bypass_daily_limit' => $bypass_limit ? 1 : 0,
                'notes'              => $notes,
                'added_by'           => get_current_user_id(),
                'date_added'         => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%d', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Remove entry from blacklist
     *
     * @param int $entry_id Entry ID
     * @return bool True on success
     */
    public function remove_from_blacklist( $entry_id ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_blacklist_table();

        $result = $wpdb->delete(
            $table,
            array( 'id' => $entry_id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Remove entry from whitelist
     *
     * @param int $entry_id Entry ID
     * @return bool True on success
     */
    public function remove_from_whitelist( $entry_id ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_whitelist_table();

        $result = $wpdb->delete(
            $table,
            array( 'id' => $entry_id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Get blacklist entries
     *
     * @param array $args Query arguments
     * @return array Entries
     */
    public function get_blacklist( $args = array() ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_blacklist_table();

        $defaults = array(
            'type'        => '',
            'permanent'   => '',
            'search'      => '',
            'orderby'     => 'date_added',
            'order'       => 'DESC',
            'limit'       => 50,
            'offset'      => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );

        if ( ! empty( $args['type'] ) ) {
            $where[] = $wpdb->prepare( 'entry_type = %s', $args['type'] );
        }

        if ( '' !== $args['permanent'] ) {
            $where[] = $wpdb->prepare( 'is_permanent = %d', $args['permanent'] ? 1 : 0 );
        }

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = $wpdb->prepare( '(entry_value LIKE %s OR reason LIKE %s)', $search, $search );
        }

        $where_sql = implode( ' AND ', $where );
        $orderby = esc_sql( $args['orderby'] );
        $order = esc_sql( $args['order'] );

        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

        $results = $wpdb->get_results(
            $wpdb->prepare( $query, $args['limit'], $args['offset'] )
        );

        return $results;
    }

    /**
     * Get whitelist entries
     *
     * @param array $args Query arguments
     * @return array Entries
     */
    public function get_whitelist( $args = array() ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_whitelist_table();

        $defaults = array(
            'type'      => '',
            'search'    => '',
            'orderby'   => 'date_added',
            'order'     => 'DESC',
            'limit'     => 50,
            'offset'    => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );

        if ( ! empty( $args['type'] ) ) {
            $where[] = $wpdb->prepare( 'entry_type = %s', $args['type'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = $wpdb->prepare( '(entry_value LIKE %s OR notes LIKE %s)', $search, $search );
        }

        $where_sql = implode( ' AND ', $where );
        $orderby = esc_sql( $args['orderby'] );
        $order = esc_sql( $args['order'] );

        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

        $results = $wpdb->get_results(
            $wpdb->prepare( $query, $args['limit'], $args['offset'] )
        );

        return $results;
    }

    /**
     * Get blacklist count
     *
     * @param array $args Query arguments
     * @return int Count
     */
    public function get_blacklist_count( $args = array() ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_blacklist_table();

        $where = array( '1=1' );

        if ( ! empty( $args['type'] ) ) {
            $where[] = $wpdb->prepare( 'entry_type = %s', $args['type'] );
        }

        if ( '' !== $args['permanent'] ) {
            $where[] = $wpdb->prepare( 'is_permanent = %d', $args['permanent'] ? 1 : 0 );
        }

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = $wpdb->prepare( '(entry_value LIKE %s OR reason LIKE %s)', $search, $search );
        }

        $where_sql = implode( ' AND ', $where );

        return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ) );
    }

    /**
     * Get whitelist count
     *
     * @param array $args Query arguments
     * @return int Count
     */
    public function get_whitelist_count( $args = array() ) {
        global $wpdb;
        
        $plugin = Fraud_Detection_Plugin::get_instance();
        $table = $plugin->database->get_whitelist_table();

        $where = array( '1=1' );

        if ( ! empty( $args['type'] ) ) {
            $where[] = $wpdb->prepare( 'entry_type = %s', $args['type'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = $wpdb->prepare( '(entry_value LIKE %s OR notes LIKE %s)', $search, $search );
        }

        $where_sql = implode( ' AND ', $where );

        return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ) );
    }

    /**
     * Import entries from CSV
     *
     * @param string $file_path CSV file path
     * @param string $list_type List type (blacklist, whitelist)
     * @return array Result with success count and errors
     */
    public function import_from_csv( $file_path, $list_type = 'blacklist' ) {
        if ( ! file_exists( $file_path ) ) {
            return array(
                'success' => false,
                'message' => __( 'File not found.', 'fraud-detection' ),
            );
        }

        $success_count = 0;
        $errors = array();

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return array(
                'success' => false,
                'message' => __( 'Could not open file.', 'fraud-detection' ),
            );
        }

        // Skip header row
        fgetcsv( $handle );

        while ( ( $data = fgetcsv( $handle ) ) !== false ) {
            if ( count( $data ) < 2 ) {
                continue;
            }

            $type = sanitize_text_field( $data[0] );
            $value = sanitize_text_field( $data[1] );
            $extra = isset( $data[2] ) ? sanitize_textarea_field( $data[2] ) : '';

            if ( 'blacklist' === $list_type ) {
                $is_permanent = isset( $data[3] ) && 'yes' === strtolower( $data[3] );
                $result = $this->add_to_blacklist( $type, $value, $is_permanent, $extra );
            } else {
                $bypass_limit = ! isset( $data[3] ) || 'yes' === strtolower( $data[3] );
                $result = $this->add_to_whitelist( $type, $value, $bypass_limit, $extra );
            }

            if ( $result ) {
                $success_count++;
            } else {
                $errors[] = sprintf( __( 'Failed to add: %s - %s', 'fraud-detection' ), $type, $value );
            }
        }

        fclose( $handle );

        return array(
            'success' => true,
            'count'   => $success_count,
            'errors'  => $errors,
        );
    }
}
