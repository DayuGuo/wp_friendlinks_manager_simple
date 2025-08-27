<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Friends_Plugin
 * @subpackage Friends_Plugin/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Friends_Plugin
 * @subpackage Friends_Plugin/admin
 * @author     Your Name <email@example.com>
 */
class Friends_Plugin_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// AJAX actions
		add_action( 'wp_ajax_load_links', array( $this, 'ajax_load_links' ) );
        add_action( 'wp_ajax_add_link', array( $this, 'ajax_add_link' ) );
        add_action( 'wp_ajax_update_link', array( $this, 'ajax_update_link' ) );
        add_action( 'wp_ajax_delete_link', array( $this, 'ajax_delete_link' ) );
        add_action( 'wp_ajax_save_order', array( $this, 'ajax_save_order' ) );
        add_action( 'wp_ajax_get_link_data', array( $this, 'ajax_get_link_data' ) );
        add_action( 'wp_ajax_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_fetch_rss', array( $this, 'ajax_fetch_rss' ) );
        add_action( 'wp_ajax_import_links', array( $this, 'handle_ajax_requests' ) );

		// Admin post action for CSV export
		add_action( 'admin_post_friends_plugin_export_links', array( $this, 'export_links_csv' ) );

		// Cron job for scheduled RSS updates
		add_action( 'friends_plugin_scheduled_rss_update', array( $this, 'perform_scheduled_rss_updates' ) );

		// Ensure the cron is scheduled on plugin activation or if not already scheduled
		if ( ! wp_next_scheduled( 'friends_plugin_scheduled_rss_update' ) ) {
		    $this->reschedule_rss_updates();
		}
	}

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        wp_enqueue_style( $this->plugin_name, FRIENDS_PLUGIN_URL . 'admin/css/friends-plugin-admin.css', array(), $this->version, 'all' );

    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        wp_enqueue_script( $this->plugin_name, FRIENDS_PLUGIN_URL . 'admin/js/friends-plugin-admin.js', array( 'jquery', 'jquery-ui-sortable' ), $this->version, false );
        
        // Localize the script with translation strings
        wp_localize_script( $this->plugin_name, 'friendsPluginAjax', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'friends_plugin_ajax_nonce' ),
            'noLinksText' => __('No friend links found.', 'friends-plugin'),
            'errorLoadingText' => __('Error loading links:', 'friends-plugin'),
            'ajaxErrorText' => __('AJAX error loading links:', 'friends-plugin'),
            'unknownErrorText' => __('Unknown error', 'friends-plugin'),
            'processingText' => __('Processing...', 'friends-plugin')
        ));
        wp_localize_script( $this->plugin_name, 'friends_plugin_ajax_obj', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            '_ajax_nonce'   => wp_create_nonce( 'friends_plugin_ajax_nonce' )
        ) );

    }

    /**
     * Add options page.
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __( 'Friend Links Management', 'friends-plugin' ),
            __( 'Friend Links', 'friends-plugin' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_plugin_setup_page' ),
            'dashicons-groups',
            26
        );
    }

    /**
     * Display the plugin setup page
     */
    public function display_plugin_setup_page() {
        include_once( FRIENDS_PLUGIN_PATH . 'admin/partials/friends-plugin-admin-display.php' );
    }

    // AJAX handler to load links
    public function ajax_load_links() {
        check_ajax_referer( 'friends_plugin_ajax_nonce', '_ajax_nonce' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        $links = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY sort_order ASC" ), ARRAY_A );

        if ( $links === false ) {
            wp_send_json_error( 'Error fetching links from database.' );
        }

        wp_send_json_success( $links );
    }

    // AJAX handler to add a new link
    public function ajax_add_link() {
        check_ajax_referer( 'friends_plugin_ajax_nonce', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $name = isset($_POST['name']) ? sanitize_text_field( $_POST['name'] ) : '';
        $url = isset($_POST['url']) ? esc_url_raw( $_POST['url'] ) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field( $_POST['description'] ) : '';
        $rss_url = isset($_POST['rss_url']) ? esc_url_raw( $_POST['rss_url'] ) : '';

        if ( empty( $name ) || empty( $url ) ) {
            wp_send_json_error( 'Site Name and Site URL are required.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';

        // Check if table exists
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) != $table_name ) {
            wp_send_json_error( __('Database table does not exist. Please deactivate and reactivate the plugin.', 'friends-plugin') );
        }

        $max_sort_order = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM {$table_name}" ) );
        $sort_order = ( $max_sort_order === null ) ? 0 : $max_sort_order + 1;

        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'url' => $url,
                'description' => $description,
                'rss_url' => $rss_url,
                'sort_order' => $sort_order
            ),
            array( '%s', '%s', '%s', '%s', '%d' )
        );

        if ( $result ) {
            $link_id = $wpdb->insert_id;
            if( !empty($rss_url) ) {
                $this->fetch_single_rss_feed($link_id, $rss_url);
            }
            wp_send_json_success( array('message' => __('Link added successfully.', 'friends-plugin'), 'link_id' => $link_id) );
        } else {
            // Get the last database error for debugging
            $db_error = $wpdb->last_error ? $wpdb->last_error : __('Unknown database error', 'friends-plugin');
            error_log( 'Friends Plugin DB Error: ' . $db_error );
            wp_send_json_error( __('Error adding link to database:', 'friends-plugin') . ' ' . $db_error );
        }
    }

    // AJAX handler to update an existing link
    public function ajax_update_link() {
        check_ajax_referer( 'friends_plugin_ajax_nonce', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $id = isset($_POST['id']) ? intval( $_POST['id'] ) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field( $_POST['name'] ) : '';
        $url = isset($_POST['url']) ? esc_url_raw( $_POST['url'] ) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field( $_POST['description'] ) : '';
        $rss_url = isset($_POST['rss_url']) ? esc_url_raw( $_POST['rss_url'] ) : '';

        if ( empty($id) || empty( $name ) || empty( $url ) ) {
            wp_send_json_error( 'Link ID, Site Name, and Site URL are required.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';

        $result = $wpdb->update(
            $table_name,
            array(
                'name' => $name,
                'url' => $url,
                'description' => $description,
                'rss_url' => $rss_url,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            // Always clear old RSS data first, then fetch new data if RSS URL is provided
            $this->clear_rss_data($id);
            if( !empty($rss_url) ) {
                $this->fetch_single_rss_feed($id, $rss_url);
            }
            wp_send_json_success( 'Link updated successfully.' );
        } else {
            wp_send_json_error( 'Error updating link or no changes made.' );
        }
    }

    // AJAX handler to delete a link
    public function ajax_delete_link() {
        check_ajax_referer( 'friends_plugin_ajax_nonce', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $id = isset($_POST['id']) ? intval( $_POST['id'] ) : 0;
        if ( empty( $id ) ) {
            wp_send_json_error( 'Link ID is required.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        $result = $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );

        if ( $result ) {
            wp_send_json_success( 'Link deleted successfully.' );
        } else {
            wp_send_json_error( 'Error deleting link.' );
        }
    }

    // AJAX handler to save links order
    public function ajax_save_order() {
        check_ajax_referer( 'friends_plugin_ajax_nonce', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $order = isset($_POST['order']) ? $_POST['order'] : array();
        if ( empty( $order ) || ! is_array( $order ) ) {
            wp_send_json_error( 'Invalid order data.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        $success_count = 0;

        foreach ( $order as $index => $id ) {
            $result = $wpdb->update(
                $table_name,
                array( 'sort_order' => intval( $index ) ),
                array( 'id' => intval( $id ) ),
                array( '%d' ),
                array( '%d' )
            );
            if ( $result !== false ) {
                $success_count++;
            }
        }

        if ( $success_count == count($order) ) {
            wp_send_json_success( 'Links order saved.' );
        } else {
            wp_send_json_error( 'Error saving links order for some items.' );
        }
    }

    // AJAX handler to get single link data
    public function ajax_get_link_data() {
        check_ajax_referer( 'friends_plugin_ajax_nonce', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $id = isset($_POST['id']) ? intval( $_POST['id'] ) : 0;
        if ( empty( $id ) ) {
            wp_send_json_error( 'Link ID is required.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        $link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ), ARRAY_A );

        if ( $link ) {
            wp_send_json_success( $link );
        } else {
            wp_send_json_error( 'Link not found.' );
        }
    }

    // AJAX handler to save settings
    public function ajax_save_settings() {
        check_ajax_referer( 'friends_plugin_ajax_nonce', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $interval = isset($_POST['interval']) ? intval( $_POST['interval'] ) : 24;
        if ( $interval < 1 ) {
            $interval = 24; 
        }

        $color_mode = isset($_POST['color_mode']) ? sanitize_text_field( $_POST['color_mode'] ) : 'auto';
        if ( ! in_array( $color_mode, array( 'auto', 'light', 'dark' ) ) ) {
            $color_mode = 'auto';
        }

        update_option( 'friends_plugin_rss_update_interval', $interval );
        update_option( 'friends_plugin_color_mode', $color_mode );
        $this->reschedule_rss_updates(); 
        wp_send_json_success( 'Settings saved. RSS update interval is now ' . $interval . ' hours. Color mode set to ' . $color_mode . '.' );
    }

    // AJAX handler to fetch all RSS feeds manually
    public function ajax_fetch_rss() {
        check_ajax_referer( 'friends_plugin_ajax_nonce', '_ajax_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        $links = $wpdb->get_results( $wpdb->prepare( "SELECT id, rss_url FROM {$table_name} WHERE rss_url != '' AND rss_url IS NOT NULL" ), ARRAY_A );

        $success_count = 0;
        $failure_count = 0;
        $detailed_errors = array();

        if ( empty( $links ) ) {
            wp_send_json_success( array( 'message' => __('No links with RSS URLs found to update.', 'friends-plugin'), 'success_count' => 0, 'failure_count' => 0, 'errors' => $detailed_errors ) );
            return;
        }

        // First, clear all RSS data for links that will be updated
        foreach ( $links as $link_item ) {
            $this->clear_rss_data( $link_item['id'] );
        }
        
        foreach ( $links as $link_item ) { // Renamed $link to $link_item to avoid conflict with $link used in fetch_single_rss_feed context if any
            $result = $this->fetch_single_rss_feed( $link_item['id'], $link_item['rss_url'] );
            if ( $result === true ) {
                $success_count++;
            } else {
                $failure_count++;
                // $result will contain the error message string if fetching failed
                $detailed_errors[] = array('id' => $link_item['id'], 'rss_url' => $link_item['rss_url'], 'error' => $result);
            }
            usleep(500000); // 0.5秒延迟，减少等待时间
        }

        wp_send_json_success( array(
            'message' => sprintf( __('RSS feeds update process finished. %d succeeded, %d failed.', 'friends-plugin'), $success_count, $failure_count ),
            'success_count' => $success_count,
            'failure_count' => $failure_count,
            'errors' => $detailed_errors
        ) );
    }

    // Helper function to clear RSS data for a specific link
    private function clear_rss_data( $link_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        
        $clear_data = array(
            'latest_post_title' => '',
            'latest_post_url'   => '',
            'latest_post_date'  => '1970-01-01 00:00:00'
        );
        
        $wpdb->update( $table_name, $clear_data, array( 'id' => $link_id ), array('%s', '%s', '%s'), array('%d') );
    }

    // Helper function to fetch and update a single RSS feed
    private function fetch_single_rss_feed( $link_id, $rss_url = null ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';

        if ( is_null( $rss_url ) ) {
            $rss_url = $wpdb->get_var( $wpdb->prepare( "SELECT rss_url FROM {$table_name} WHERE id = %d", $link_id ) );
        }

        // Clear old RSS data first
        $this->clear_rss_data( $link_id );

        $update_data = array(
            'latest_post_title' => '',
            'latest_post_url'   => '',
            'latest_post_date'  => '1970-01-01 00:00:00'
        );

        if ( empty( $rss_url ) ) {
            $wpdb->update( $table_name, $update_data, array( 'id' => $link_id ), array('%s', '%s', '%s'), array('%d') );
            return 'RSS URL is empty.';
        }
        
        // SSRF防护：验证URL安全性
        if (!$this->is_safe_url($rss_url)) {
            return 'URL not allowed for security reasons.';
        }

        include_once( ABSPATH . WPINC . '/feed.php' );
        
        // Add timeout and user agent for better compatibility
        add_filter( 'wp_feed_cache_transient_lifetime', function() { return 300; } ); // 5 minutes cache
        add_filter( 'wp_feed_options', function($feed) {
            $feed->set_timeout(10); // 设置10秒超时
            return $feed;
        });
        
        $feed = fetch_feed( $rss_url );

        if ( is_wp_error( $feed ) ) {
            // If WordPress fetch_feed fails, try alternative method for XML parsing issues
            $error_message = $feed->get_error_message();
            
            if ( strpos( $error_message, 'XML error' ) !== false || strpos( $error_message, 'invalid XML' ) !== false ) {
                // Try to fetch and clean the RSS content manually
                $cleaned_feed = $this->fetch_and_clean_rss( $rss_url );
                if ( $cleaned_feed !== false && ! $cleaned_feed->error() ) {
                    $feed = $cleaned_feed;
                } else {
                    $wpdb->update( $table_name, $update_data, array( 'id' => $link_id ), array('%s', '%s', '%s'), array('%d') );
                    $additional_error = $cleaned_feed !== false ? ' Alternative parsing also failed: ' . $cleaned_feed->error() : ' Alternative parsing method failed.';
                    return $error_message . $additional_error . ' This is usually caused by invalid characters or formatting in the RSS feed. Please check the RSS URL: ' . $rss_url;
                }
            } else {
                $wpdb->update( $table_name, $update_data, array( 'id' => $link_id ), array('%s', '%s', '%s'), array('%d') );
                return $error_message;
            }
        }

        $maxitems = $feed->get_item_quantity( 1 );
        $rss_items = $feed->get_items( 0, $maxitems );

        if ( empty( $rss_items ) ) {
            $wpdb->update( $table_name, $update_data, array( 'id' => $link_id ), array('%s', '%s', '%s'), array('%d') );
            $feed->__destruct();
            unset($feed);
            return 'No items found in RSS feed.';
        }

        $latest_item = $rss_items[0];
        $update_data['latest_post_title'] = sanitize_text_field( $latest_item->get_title() );
        $update_data['latest_post_url'] = esc_url_raw( $latest_item->get_permalink() );
        $update_data['latest_post_date'] = $latest_item->get_date( 'Y-m-d H:i:s' );

        $wpdb->update( $table_name, $update_data, array( 'id' => $link_id ), array('%s', '%s', '%s'), array('%d') );
        $feed->__destruct(); 
        unset($feed);
        return true;
    }

    // Clear and Reschedule RSS updates cron
    public function reschedule_rss_updates() {
        $hook = 'friends_plugin_scheduled_rss_update';
        wp_clear_scheduled_hook( $hook );

        $interval_hours = get_option( 'friends_plugin_rss_update_interval', 24 );
        // WordPress has 'hourly', 'twicedaily', 'daily'. For custom, need to add custom schedule.
        // For simplicity, let's use 'hourly' and the perform_scheduled_rss_updates can have its own logic
        // or we create a dynamic schedule if truly needed.
        // A common approach is to run a more frequent cron (e.g. hourly) and then inside the cron function,
        // check if enough time has passed since the last actual run based on the setting.
        // However, a simpler way for fixed intervals like 'X hours' is to use a custom cron schedule if X is not 1, 12, or 24.
        // For now, we'll just schedule it based on a standard interval, closest to what's desired or use 'hourly'.
        
        $schedules = wp_get_schedules();
        $schedule_key = 'hours_' . $interval_hours;

        if ($interval_hours > 0 && !isset($schedules[$schedule_key])) {
             // If we want truly dynamic intervals, we'd add them via 'cron_schedules' filter
             // For now, let's stick to common ones or 'hourly' as a base for more frequent checks.
             // If interval is e.g. 6 hours, 'hourly' is fine, the cron job itself can decide if it's time.
        }

        // Let's use 'hourly' and the cron function can decide if it's time to run based on the setting.
        // This is simpler than managing many custom cron schedules.
        wp_schedule_event( time(), 'hourly', $hook );
    }

    // This method will be hooked to the cron event.
    public function perform_scheduled_rss_updates() {
        $last_run = get_option('friends_plugin_last_rss_update_time', 0);
        $interval_hours = get_option( 'friends_plugin_rss_update_interval', 24 );
        $interval_seconds = $interval_hours * HOUR_IN_SECONDS;

        if ( (time() - $last_run) < $interval_seconds ) {
            return; // Not time yet
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        $links = $wpdb->get_results( $wpdb->prepare( "SELECT id, rss_url FROM {$table_name} WHERE rss_url != '' AND rss_url IS NOT NULL" ), ARRAY_A );
        
        if (!empty($links)) {
            foreach($links as $link){
                $this->fetch_single_rss_feed($link['id'], $link['rss_url']);
                usleep(500000); // 0.5秒延迟
            }
        }
        update_option('friends_plugin_last_rss_update_time', time());
    }

    // Export links to JSON
    public function export_links_csv() {
        if ( ! isset( $_POST['friends_plugin_export_nonce'] ) || ! wp_verify_nonce( $_POST['friends_plugin_export_nonce'], 'friends_plugin_export_links_nonce' ) ) {
            wp_die( '安全验证失败' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '权限不足' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        $links = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY sort_order ASC" ), ARRAY_A );

        if ( empty( $links ) ) {
            wp_redirect(add_query_arg('fp_export_status', 'no_data', wp_get_referer()));
            exit;
        }

        $filename = 'friend_links_export_' . date( 'Y-m-d_H-i-s' ) . '.json';

        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Alternative RSS fetching method with XML cleaning for problematic feeds
     * 
     * @param string $rss_url The RSS URL to fetch
     * @return mixed SimplePie feed object on success, false on failure
     */
    private function fetch_and_clean_rss( $rss_url ) {
        // Use WordPress HTTP API to fetch the RSS content
        $response = wp_remote_get( $rss_url, array(
            'timeout' => 10, // 减少超时时间到10秒
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress RSS Reader)'
        ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return false;
        }
        
        // Clean the XML content to remove problematic characters
        $cleaned_body = $this->clean_xml_content( $body );
        
        // Try to parse the cleaned content with SimplePie
        include_once( ABSPATH . WPINC . '/class-simplepie.php' );
        
        $feed = new SimplePie();
        $feed->set_raw_data( $cleaned_body );
        $feed->enable_cache( false ); // Disable cache for this manual fetch
        $feed->init();
        
        if ( $feed->error() ) {
            return false;
        }
        
        return $feed;
    }
    
    /**
     * Clean XML content to remove invalid characters
     * 
     * @param string $xml_content The raw XML content
     * @return string Cleaned XML content
     */
    private function clean_xml_content( $xml_content ) {
        // Remove BOM if present (UTF-8, UTF-16, UTF-32)
        $xml_content = preg_replace( '/^\xEF\xBB\xBF|^\xFF\xFE|^\xFE\xFF|^\x00\x00\xFE\xFF|^\xFF\xFE\x00\x00/', '', $xml_content );
        
        // Remove any leading whitespace before XML declaration - this is the main cause of "Reserved XML Name" error
        $xml_content = ltrim( $xml_content );
        
        // Find the XML declaration and ensure it's at the very beginning
        if ( preg_match( '/<\?xml[^>]*\?>/', $xml_content, $matches, PREG_OFFSET_CAPTURE ) ) {
            $xml_declaration = $matches[0][0];
            $xml_position = $matches[0][1];
            
            // If XML declaration is not at position 0, move it to the beginning
            if ( $xml_position > 0 ) {
                $xml_content = substr_replace( $xml_content, '', $xml_position, strlen( $xml_declaration ) );
                $xml_content = $xml_declaration . ltrim( $xml_content );
            }
        }
        
        // Remove control characters except tab, newline, and carriage return
        $xml_content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml_content );
        
        // Fix common XML encoding issues
        $xml_content = str_replace( array( '&amp;amp;', '&amp;#' ), array( '&amp;', '&#' ), $xml_content );
        
        // Ensure proper encoding
        if ( ! mb_check_encoding( $xml_content, 'UTF-8' ) ) {
            $xml_content = mb_convert_encoding( $xml_content, 'UTF-8', 'auto' );
        }
        
        return $xml_content;
    }

    public function handle_ajax_requests() {
        // 检查 nonce
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'friends_plugin_ajax_nonce')) {
            wp_send_json_error(array('message' => '安全验证失败'));
        }

        $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';

        switch ($action) {
            case 'import_links':
                $this->handle_import_links();
                break;

            default:
                // ... existing cases ...
                break;
        }
    }

    // Handle import links
    private function handle_import_links() {
        // 权限检查
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'friends-plugin'));
            return;
        }
        
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload failed', 'friends-plugin'));
            return;
        }

        $file = $_FILES['import_file'];
        
        // 验证文件扩展名
        $allowed_extensions = array('json');
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions)) {
            wp_send_json_error(__('Please upload a JSON file', 'friends-plugin'));
            return;
        }
        
        // 限制文件大小（2MB）
        $max_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(__('File size exceeds 2MB limit', 'friends-plugin'));
            return;
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            wp_send_json_error(__('Unable to read file content', 'friends-plugin'));
            return;
        }

        $links = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(sprintf(__('JSON format error: %s', 'friends-plugin'), json_last_error_msg()));
            return;
        }

        if (!is_array($links)) {
            wp_send_json_error(__('Invalid data format', 'friends-plugin'));
            return;
        }
        
        // 限制导入数量（最多100条）
        if (count($links) > 100) {
            wp_send_json_error(__('Too many links. Maximum 100 links allowed per import', 'friends-plugin'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'friends_links';
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );

        foreach ($links as $link) {
            if (empty($link['name']) || empty($link['url'])) {
                $results['failed']++;
                $results['errors'][] = __('Missing required fields (name or URL)', 'friends-plugin');
                continue;
            }
            
            // URL验证
            if (!filter_var($link['url'], FILTER_VALIDATE_URL)) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Invalid URL: %s', 'friends-plugin'), esc_html($link['url']));
                continue;
            }
            
            // 协议验证
            $allowed_protocols = array('http', 'https');
            $parsed_url = parse_url($link['url']);
            if (!isset($parsed_url['scheme']) || !in_array($parsed_url['scheme'], $allowed_protocols)) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Invalid URL protocol: %s', 'friends-plugin'), esc_html($link['url']));
                continue;
            }

            // 检查是否已存在相同URL的记录
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM %i WHERE url = %s",
                $table_name,
                $link['url']
            ));

            if ($existing) {
                // 更新现有记录
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'name' => $link['name'],
                        'description' => isset($link['description']) ? $link['description'] : '',
                        'rss_url' => isset($link['rss_url']) ? $link['rss_url'] : '',
                        'latest_post_title' => isset($link['latest_post_title']) ? $link['latest_post_title'] : '',
                        'latest_post_url' => isset($link['latest_post_url']) ? $link['latest_post_url'] : '',
                        'latest_post_date' => isset($link['latest_post_date']) ? $link['latest_post_date'] : '',
                        'sort_order' => isset($link['sort_order']) ? $link['sort_order'] : 0
                    ),
                    array('id' => $existing)
                );
            } else {
                // 插入新记录
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'name' => $link['name'],
                        'url' => $link['url'],
                        'description' => isset($link['description']) ? $link['description'] : '',
                        'rss_url' => isset($link['rss_url']) ? $link['rss_url'] : '',
                        'latest_post_title' => isset($link['latest_post_title']) ? $link['latest_post_title'] : '',
                        'latest_post_url' => isset($link['latest_post_url']) ? $link['latest_post_url'] : '',
                        'latest_post_date' => isset($link['latest_post_date']) ? $link['latest_post_date'] : '',
                        'sort_order' => isset($link['sort_order']) ? $link['sort_order'] : 0
                    )
                );
            }

            if ($result === false) {
                $results['failed']++;
                $results['errors'][] = "处理 {$link['name']} 时出错: " . $wpdb->last_error;
            } else {
                $results['success']++;
            }
        }

        wp_send_json_success($results);
    }
    
    /**
     * 验证URL是否安全，防止SSRF攻击
     */
    private function is_safe_url($url) {
        $parsed = parse_url($url);
        
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }
        
        $host = $parsed['host'];
        
        // 禁止内部IP和私有IP地址
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }
        
        // 禁止localhost和相关域名
        $blocked_hosts = array(
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
            'metadata.google.internal',
            '169.254.169.254'
        );
        
        if (in_array(strtolower($host), $blocked_hosts)) {
            return false;
        }
        
        // 只允许HTTP和HTTPS协议
        if (!in_array($parsed['scheme'], array('http', 'https'))) {
            return false;
        }
        
        return true;
    }

}