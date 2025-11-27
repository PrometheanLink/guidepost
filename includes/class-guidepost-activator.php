<?php
/**
 * Plugin activation and deactivation
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Activator class
 */
class GuidePost_Activator {

    /**
     * Plugin activation
     */
    public static function activate() {
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( GUIDEPOST_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'GuidePost requires PHP 7.4 or higher.', 'guidepost' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }

        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
            deactivate_plugins( GUIDEPOST_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'GuidePost requires WordPress 5.8 or higher.', 'guidepost' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }

        // Create database tables
        GuidePost_Database::create_tables();

        // Set default options
        self::set_default_options();

        // Clear rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option( 'guidepost_activated', true );
        update_option( 'guidepost_version', GUIDEPOST_VERSION );
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'guidepost_daily_cleanup' );
        wp_clear_scheduled_hook( 'guidepost_send_reminders' );

        // Clear rewrite rules
        flush_rewrite_rules();

        // Remove activation flag (keep data)
        delete_option( 'guidepost_activated' );
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = array(
            'guidepost_timezone'           => wp_timezone_string(),
            'guidepost_date_format'        => get_option( 'date_format' ),
            'guidepost_time_format'        => get_option( 'time_format' ),
            'guidepost_time_slot_duration' => 30,
            'guidepost_buffer_before'      => 0,
            'guidepost_buffer_after'       => 0,
            'guidepost_email_notifications' => true,
            'guidepost_admin_email'        => get_option( 'admin_email' ),
            'guidepost_woocommerce_enabled' => false,
        );

        foreach ( $defaults as $option => $value ) {
            if ( false === get_option( $option ) ) {
                update_option( $option, $value );
            }
        }
    }

    /**
     * Uninstall - called when plugin is deleted
     */
    public static function uninstall() {
        // Only run if uninstall constant is defined
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            return;
        }

        // Remove all options
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'guidepost_%'" );

        // Drop tables (optional - comment out to preserve data)
        // GuidePost_Database::drop_tables();
    }
}
