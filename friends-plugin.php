<?php
/**
 * Plugin Name:       Friend Links Manager
 * Plugin URI:        https://veryjack.com
 * Description:       A plugin to manage and display friend links along with their latest blog posts, fetched via RSS.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            阿杰 Jack & Dayu
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       friends-plugin
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Define plugin constants
 */
define( 'FRIENDS_PLUGIN_VERSION', '1.0.0' );
define( 'FRIENDS_PLUGIN_NAME', 'friends-plugin' ); // Added for consistency, though FRIENDS_PLUGIN_PATH is used more
define( 'FRIENDS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'FRIENDS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require FRIENDS_PLUGIN_PATH . 'includes/class-friends-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function run_friends_plugin() {
    $plugin = new Friends_Plugin();
    $plugin->run();
}
run_friends_plugin();

// Activation hook
register_activation_hook( __FILE__, 'activate_friends_plugin_hook' );

// Deactivation hook
register_deactivation_hook( __FILE__, 'deactivate_friends_plugin_hook' );

/**
 * The code that runs during plugin activation.
 */
function activate_friends_plugin_hook() {
    require_once FRIENDS_PLUGIN_PATH . 'includes/class-friends-plugin-activator.php';
    Friends_Plugin_Activator::activate();

    // Schedule cron job if not already scheduled after activation
    if ( ! wp_next_scheduled( 'friends_plugin_scheduled_rss_update' ) ) {
        // Use 'hourly' as the recurrence and the callback will check the actual interval setting
        wp_schedule_event( time(), 'hourly', 'friends_plugin_scheduled_rss_update' );
    }
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_friends_plugin_hook() {
    require_once FRIENDS_PLUGIN_PATH . 'includes/class-friends-plugin-deactivator.php';
    Friends_Plugin_Deactivator::deactivate();

    // Clear scheduled cron job
    wp_clear_scheduled_hook( 'friends_plugin_scheduled_rss_update' );
}

/**
 * Add custom cron schedules if needed, though 'hourly' with internal check is often simpler.
 * This function demonstrates how to add a truly custom interval if the setting is not 1, 12, or 24.
 * However, the current implementation uses 'hourly' and checks the interval in the cron callback.
 * If you want to use truly dynamic schedules based on the setting, you would use this filter
 * and then use the dynamic schedule key (e.g., 'hours_X') in wp_schedule_event.
 */
function friends_plugin_custom_cron_schedules( $schedules ) {
    $interval_hours = get_option( 'friends_plugin_rss_update_interval', 24 ); // Default to 24 hours
    $schedule_key = 'hours_' . $interval_hours;

    // Only add if it's a custom interval not already defined by WordPress or another plugin
    if ( $interval_hours > 0 && ! isset( $schedules[ $schedule_key ] ) ) {
        if (!in_array($interval_hours, array(1, 12, 24))) { // Standard WordPress intervals are hourly, twicedaily, daily
            $schedules[ $schedule_key ] = array(
                'interval' => $interval_hours * HOUR_IN_SECONDS,
                'display'  => sprintf( esc_html__( 'Every %d hours', 'friends-plugin' ), $interval_hours ),
            );
        }
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'friends_plugin_custom_cron_schedules' );