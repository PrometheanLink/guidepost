<?php
/**
 * Plugin Name: GuidePost
 * Plugin URI: https://prometheanlink.com/guidepost
 * Description: Appointment booking and scheduling system with calendar UI and WooCommerce integration.
 * Version: 1.0.0
 * Author: PrometheanLink
 * Author URI: https://prometheanlink.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: guidepost
 * Domain Path: /languages
 *
 * @package GuidePost
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'GUIDEPOST_VERSION', '1.0.0' );
define( 'GUIDEPOST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GUIDEPOST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GUIDEPOST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main GuidePost class
 */
final class GuidePost {

    /**
     * Single instance
     *
     * @var GuidePost
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return GuidePost
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-database.php';
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-activator.php';
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-availability.php';
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-email.php';

        // WooCommerce integration - always load class, let it check if WC is available
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-woocommerce.php';

        // Admin classes - always load so menu can register
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-admin.php';
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-communications.php';
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-customers.php';
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-backup.php';

        // Frontend classes
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/frontend/class-guidepost-shortcodes.php';

        // API classes
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/api/class-guidepost-rest-api.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook( __FILE__, array( 'GuidePost_Activator', 'activate' ) );
        register_deactivation_hook( __FILE__, array( 'GuidePost_Activator', 'deactivate' ) );

        // Initialize components
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'plugins_loaded', array( $this, 'admin_init' ) );

        // Check for database upgrades on every load
        add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_database' ), 5 );

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // REST API
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain( 'guidepost', false, dirname( GUIDEPOST_PLUGIN_BASENAME ) . '/languages' );

        // Initialize shortcodes
        GuidePost_Shortcodes::init();
    }

    /**
     * Admin initialization (runs on plugins_loaded)
     */
    public function admin_init() {
        GuidePost_Admin::get_instance();
        GuidePost_Communications::get_instance();
        GuidePost_Customers::get_instance();
        GuidePost_Backup::get_instance();

        // Initialize WooCommerce integration (WooCommerce is loaded by now)
        if ( class_exists( 'WooCommerce' ) ) {
            GuidePost_WooCommerce::init();
        }
    }

    /**
     * Check if database needs upgrade and run it
     * This runs on every page load to ensure schema is up to date
     */
    public function maybe_upgrade_database() {
        $installed_version = get_option( 'guidepost_db_version', '0' );

        // If version is different, run the upgrade
        if ( version_compare( $installed_version, GUIDEPOST_VERSION, '<' ) ) {
            GuidePost_Database::create_tables();
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        GuidePost_REST_API::register_routes();
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with our shortcode
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'guidepost_booking' ) ) {
            // CSS
            wp_enqueue_style( 'dashicons' );
            wp_enqueue_style(
                'guidepost-frontend',
                GUIDEPOST_PLUGIN_URL . 'assets/css/frontend.css',
                array( 'dashicons' ),
                GUIDEPOST_VERSION
            );

            // JavaScript
            wp_enqueue_script(
                'guidepost-frontend',
                GUIDEPOST_PLUGIN_URL . 'assets/js/frontend.js',
                array( 'jquery' ),
                GUIDEPOST_VERSION,
                true
            );

            // Localize script - use query string format for REST API (more compatible)
            wp_localize_script( 'guidepost-frontend', 'guidepost', array(
                'ajax_url'  => admin_url( 'admin-ajax.php' ),
                'rest_base' => home_url( '/' ),
                'nonce'     => wp_create_nonce( 'wp_rest' ),
            ) );
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin pages
        if ( strpos( $hook, 'guidepost' ) === false ) {
            return;
        }

        // Check if we're on appointments page with calendar view
        $is_calendar_view = ( strpos( $hook, 'guidepost-appointments' ) !== false && isset( $_GET['view'] ) && 'calendar' === $_GET['view'] );

        // FullCalendar library (only on calendar view)
        if ( $is_calendar_view ) {
            wp_enqueue_style(
                'fullcalendar',
                'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
                array(),
                '6.1.10'
            );

            wp_enqueue_script(
                'fullcalendar',
                'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
                array(),
                '6.1.10',
                true
            );
        }

        // CSS
        wp_enqueue_style(
            'guidepost-admin',
            GUIDEPOST_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GUIDEPOST_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'guidepost-admin',
            GUIDEPOST_PLUGIN_URL . 'assets/js/admin.js',
            $is_calendar_view ? array( 'jquery', 'fullcalendar' ) : array( 'jquery' ),
            GUIDEPOST_VERSION,
            true
        );

        // Localize script
        wp_localize_script( 'guidepost-admin', 'guidepost_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'guidepost_admin_nonce' ),
        ) );
    }
}

/**
 * Initialize plugin
 */
function guidepost() {
    return GuidePost::get_instance();
}

// Start the plugin
guidepost();
