<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider whether any options, custom tables,
 * or other data should be removed.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Friends_Plugin
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete options
$options_to_delete = array(
    'friends_plugin_db_version',
    'friends_plugin_rss_update_interval',
    'friends_plugin_last_rss_update_time',
    // Add any other options your plugin creates
);

foreach ( $options_to_delete as $option_name ) {
    delete_option( $option_name );
    // For site options in Multisite
    // delete_site_option( $option_name );
}

// Delete custom tables
$table_name = $wpdb->prefix . 'friends_links';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Note: No need to remove files here, WordPress handles plugin file deletion.
// If you created custom directories outside the plugin folder (not recommended),
// you would handle their removal here.