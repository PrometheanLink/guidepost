<?php
/**
 * Admin functionality
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class
 */
class GuidePost_Admin {

    /**
     * Single instance
     *
     * @var GuidePost_Admin
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return GuidePost_Admin
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );

        // AJAX handlers
        add_action( 'wp_ajax_guidepost_update_appointment_status', array( $this, 'ajax_update_appointment_status' ) );
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        // Handle service form
        if ( isset( $_POST['guidepost_service_nonce'] ) && wp_verify_nonce( $_POST['guidepost_service_nonce'], 'guidepost_save_service' ) ) {
            $this->handle_service_form();
        }

        // Handle service deletion
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['service_id'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_service_' . $_GET['service_id'] ) ) {
                $this->handle_service_delete( absint( $_GET['service_id'] ) );
            }
        }

        // Handle provider form
        if ( isset( $_POST['guidepost_provider_nonce'] ) && wp_verify_nonce( $_POST['guidepost_provider_nonce'], 'guidepost_save_provider' ) ) {
            $this->handle_provider_form();
        }

        // Handle provider deletion
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['provider_id'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_provider_' . $_GET['provider_id'] ) ) {
                $this->handle_provider_delete( absint( $_GET['provider_id'] ) );
            }
        }
    }

    /**
     * Handle service form submission
     */
    private function handle_service_form() {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-services.php';

        $data = array(
            'name'           => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
            'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '',
            'duration'       => isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : 60,
            'price'          => isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0,
            'deposit_amount' => isset( $_POST['deposit_amount'] ) ? floatval( $_POST['deposit_amount'] ) : 0,
            'deposit_type'   => isset( $_POST['deposit_type'] ) ? sanitize_text_field( $_POST['deposit_type'] ) : 'fixed',
            'color'          => isset( $_POST['color'] ) ? sanitize_hex_color( $_POST['color'] ) : '#c16107',
            'status'         => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active',
            'buffer_before'  => isset( $_POST['buffer_before'] ) ? absint( $_POST['buffer_before'] ) : 0,
            'buffer_after'   => isset( $_POST['buffer_after'] ) ? absint( $_POST['buffer_after'] ) : 0,
            'sort_order'     => isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0,
        );

        $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;

        if ( $service_id ) {
            $result = GuidePost_Services::update_service( $service_id, $data );
            $message = 'updated';
        } else {
            $result = GuidePost_Services::create_service( $data );
            $message = 'created';
        }

        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-services', 'error' => urlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-services', 'message' => $message ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * Handle service deletion
     */
    private function handle_service_delete( $service_id ) {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-services.php';

        $result = GuidePost_Services::delete_service( $service_id );

        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-services', 'error' => urlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-services', 'message' => 'deleted' ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * Handle provider form submission
     */
    private function handle_provider_form() {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-providers.php';

        $data = array(
            'name'     => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
            'email'    => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
            'phone'    => isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '',
            'bio'      => isset( $_POST['bio'] ) ? sanitize_textarea_field( $_POST['bio'] ) : '',
            'timezone' => isset( $_POST['timezone'] ) ? sanitize_text_field( $_POST['timezone'] ) : 'America/New_York',
            'status'   => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active',
            'services' => isset( $_POST['services'] ) ? array_map( 'absint', $_POST['services'] ) : array(),
        );

        // Handle working hours
        if ( isset( $_POST['working_hours'] ) ) {
            $data['working_hours'] = $_POST['working_hours'];
        }

        $provider_id = isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0;

        if ( $provider_id ) {
            $result = GuidePost_Providers::update_provider( $provider_id, $data );
            $message = 'updated';
        } else {
            $result = GuidePost_Providers::create_provider( $data );
            $message = 'created';
        }

        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-providers', 'error' => urlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-providers', 'message' => $message ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * Handle provider deletion
     */
    private function handle_provider_delete( $provider_id ) {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-providers.php';

        $result = GuidePost_Providers::delete_provider( $provider_id );

        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-providers', 'error' => urlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-providers', 'message' => 'deleted' ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __( 'GuidePost', 'guidepost' ),
            __( 'GuidePost', 'guidepost' ),
            'manage_options',
            'guidepost',
            array( $this, 'render_dashboard_page' ),
            'dashicons-calendar-alt',
            30
        );

        // Dashboard submenu (same as main)
        add_submenu_page(
            'guidepost',
            __( 'Dashboard', 'guidepost' ),
            __( 'Dashboard', 'guidepost' ),
            'manage_options',
            'guidepost',
            array( $this, 'render_dashboard_page' )
        );

        // Appointments
        add_submenu_page(
            'guidepost',
            __( 'Appointments', 'guidepost' ),
            __( 'Appointments', 'guidepost' ),
            'manage_options',
            'guidepost-appointments',
            array( $this, 'render_appointments_page' )
        );

        // Services
        add_submenu_page(
            'guidepost',
            __( 'Services', 'guidepost' ),
            __( 'Services', 'guidepost' ),
            'manage_options',
            'guidepost-services',
            array( $this, 'render_services_page' )
        );

        // Providers
        add_submenu_page(
            'guidepost',
            __( 'Providers', 'guidepost' ),
            __( 'Providers', 'guidepost' ),
            'manage_options',
            'guidepost-providers',
            array( $this, 'render_providers_page' )
        );

        // Customers - handled by GuidePost_Customers class

        // Settings
        add_submenu_page(
            'guidepost',
            __( 'Settings', 'guidepost' ),
            __( 'Settings', 'guidepost' ),
            'manage_options',
            'guidepost-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General settings section
        add_settings_section(
            'guidepost_general',
            __( 'General Settings', 'guidepost' ),
            array( $this, 'render_general_section' ),
            'guidepost-settings'
        );

        // Time settings
        register_setting( 'guidepost_settings', 'guidepost_timezone' );
        register_setting( 'guidepost_settings', 'guidepost_date_format' );
        register_setting( 'guidepost_settings', 'guidepost_time_format' );
        register_setting( 'guidepost_settings', 'guidepost_time_slot_duration' );

        // Notification settings
        register_setting( 'guidepost_settings', 'guidepost_email_notifications' );
        register_setting( 'guidepost_settings', 'guidepost_admin_email' );

        // WooCommerce settings
        register_setting( 'guidepost_settings', 'guidepost_woocommerce_enabled' );

        // Provider access settings
        register_setting( 'guidepost_settings', 'guidepost_provider_customer_access' );
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . esc_html__( 'Configure general plugin settings.', 'guidepost' ) . '</p>';
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        // Get stats
        $today = date( 'Y-m-d' );
        $today_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['appointments']} WHERE booking_date = %s AND status NOT IN ('canceled')",
            $today
        ) );
        $pending_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['appointments']} WHERE status = 'pending'"
        );
        $customer_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['customers']}" );
        $service_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']} WHERE status = 'active'" );

        // Active flags count
        $flags_count = 0;
        if ( isset( $tables['customer_flags'] ) ) {
            $flags_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$tables['customer_flags']} WHERE is_active = 1"
            );
        }

        // Upcoming appointments (next 7 days)
        $upcoming = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*, s.name AS service_name, s.color AS service_color,
                    p.name AS provider_name, c.first_name, c.last_name
             FROM {$tables['appointments']} a
             LEFT JOIN {$tables['services']} s ON a.service_id = s.id
             LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
             LEFT JOIN {$tables['customers']} c ON a.customer_id = c.id
             WHERE a.booking_date >= %s AND a.booking_date <= DATE_ADD(%s, INTERVAL 7 DAY)
             AND a.status IN ('pending', 'approved')
             ORDER BY a.booking_date ASC, a.booking_time ASC
             LIMIT 10",
            $today,
            $today
        ) );

        // Get active flags for dashboard widget
        $active_flags = array();
        if ( isset( $tables['customer_flags'] ) ) {
            $active_flags = $wpdb->get_results(
                "SELECT f.*, c.first_name, c.last_name
                 FROM {$tables['customer_flags']} f
                 LEFT JOIN {$tables['customers']} c ON f.customer_id = c.id
                 WHERE f.is_active = 1
                 ORDER BY FIELD(f.flag_type, 'payment_due', 'follow_up', 'inactive', 'vip_check', 'birthday', 'custom'), f.created_at DESC
                 LIMIT 10"
            );
        }

        $this->render_admin_header( __( 'Dashboard', 'guidepost' ) );
        ?>
        <div class="guidepost-admin-content">
            <div class="guidepost-dashboard-widgets">
                <div class="guidepost-widget">
                    <h3><?php esc_html_e( 'Today\'s Appointments', 'guidepost' ); ?></h3>
                    <p class="guidepost-widget-number"><?php echo esc_html( $today_count ); ?></p>
                </div>
                <div class="guidepost-widget guidepost-widget-warning">
                    <h3><?php esc_html_e( 'Pending Approval', 'guidepost' ); ?></h3>
                    <p class="guidepost-widget-number"><?php echo esc_html( $pending_count ); ?></p>
                </div>
                <div class="guidepost-widget">
                    <h3><?php esc_html_e( 'Total Customers', 'guidepost' ); ?></h3>
                    <p class="guidepost-widget-number"><?php echo esc_html( $customer_count ); ?></p>
                </div>
                <div class="guidepost-widget">
                    <h3><?php esc_html_e( 'Active Services', 'guidepost' ); ?></h3>
                    <p class="guidepost-widget-number"><?php echo esc_html( $service_count ); ?></p>
                </div>
                <?php if ( $flags_count > 0 ) : ?>
                <div class="guidepost-widget guidepost-widget-alert">
                    <h3><?php esc_html_e( 'Active Flags', 'guidepost' ); ?></h3>
                    <p class="guidepost-widget-number"><?php echo esc_html( $flags_count ); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $active_flags ) ) : ?>
            <div class="guidepost-dashboard-section guidepost-flags-section">
                <h2>
                    <span class="dashicons dashicons-flag" style="color: #dc3545;"></span>
                    <?php esc_html_e( 'Customer Alerts & Flags', 'guidepost' ); ?>
                </h2>
                <div class="guidepost-flags-dashboard-list">
                    <?php foreach ( $active_flags as $flag ) :
                        // Determine priority class based on flag type
                        $priority_class = 'medium';
                        if ( in_array( $flag->flag_type, array( 'payment_due', 'follow_up' ), true ) ) {
                            $priority_class = 'high';
                        } elseif ( in_array( $flag->flag_type, array( 'birthday', 'custom' ), true ) ) {
                            $priority_class = 'low';
                        }
                    ?>
                        <div class="guidepost-flag-dashboard-item priority-<?php echo esc_attr( $priority_class ); ?>">
                            <div class="guidepost-flag-dashboard-content">
                                <span class="guidepost-flag-dashboard-customer">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-customers&action=view&customer_id=' . $flag->customer_id ) ); ?>">
                                        <?php echo esc_html( $flag->first_name . ' ' . $flag->last_name ); ?>
                                    </a>
                                </span>
                                <?php if ( ! empty( $flag->message ) ) : ?>
                                    <p class="guidepost-flag-dashboard-desc"><?php echo esc_html( wp_trim_words( $flag->message, 15 ) ); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="guidepost-flag-dashboard-meta">
                                <span class="guidepost-flag-type"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $flag->flag_type ) ) ); ?></span>
                                <span class="guidepost-flag-date"><?php echo esc_html( human_time_diff( strtotime( $flag->created_at ), current_time( 'timestamp' ) ) ); ?> ago</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-customers' ) ); ?>"><?php esc_html_e( 'View all customers', 'guidepost' ); ?> &rarr;</a></p>
            </div>
            <?php endif; ?>

            <div class="guidepost-dashboard-section">
                <h2><?php esc_html_e( 'Upcoming Appointments', 'guidepost' ); ?></h2>
                <?php if ( empty( $upcoming ) ) : ?>
                    <p><?php esc_html_e( 'No upcoming appointments in the next 7 days.', 'guidepost' ); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 8px;"></th>
                                <th><?php esc_html_e( 'Date & Time', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Customer', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Service', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Provider', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $upcoming as $apt ) : ?>
                                <tr>
                                    <td><span class="guidepost-color-indicator" style="background-color: <?php echo esc_attr( $apt->service_color ?: '#999' ); ?>"></span></td>
                                    <td>
                                        <strong><?php echo esc_html( date_i18n( 'D, M j', strtotime( $apt->booking_date ) ) ); ?></strong><br>
                                        <span class="description"><?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $apt->booking_time ) ) ); ?></span>
                                    </td>
                                    <td><?php echo esc_html( $apt->first_name . ' ' . $apt->last_name ); ?></td>
                                    <td><?php echo esc_html( $apt->service_name ); ?></td>
                                    <td><?php echo esc_html( $apt->provider_name ); ?></td>
                                    <td><span class="guidepost-status guidepost-status-<?php echo esc_attr( str_replace( '_', '-', $apt->status ) ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $apt->status ) ) ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-appointments' ) ); ?>"><?php esc_html_e( 'View all appointments', 'guidepost' ); ?> &rarr;</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render appointments page
     */
    public function render_appointments_page() {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        // Handle status update
        if ( isset( $_POST['appointment_status_nonce'] ) && wp_verify_nonce( $_POST['appointment_status_nonce'], 'update_appointment_status' ) ) {
            $apt_id = absint( $_POST['appointment_id'] );
            $new_status = sanitize_text_field( $_POST['new_status'] );
            $valid_statuses = array( 'pending', 'approved', 'canceled', 'completed', 'no_show' );

            if ( in_array( $new_status, $valid_statuses, true ) ) {
                $wpdb->update(
                    $tables['appointments'],
                    array( 'status' => $new_status ),
                    array( 'id' => $apt_id ),
                    array( '%s' ),
                    array( '%d' )
                );
                do_action( 'guidepost_appointment_status_changed', $apt_id, $new_status );
            }
        }

        // Get view mode (list or calendar)
        $view_mode = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'list';

        // Get filters
        $filter_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $filter_date = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : '';
        $filter_provider = isset( $_GET['provider'] ) ? absint( $_GET['provider'] ) : 0;
        $filter_service = isset( $_GET['service'] ) ? absint( $_GET['service'] ) : 0;

        // Build query
        $where = array( '1=1' );
        $values = array();

        if ( $filter_status ) {
            $where[] = 'a.status = %s';
            $values[] = $filter_status;
        }
        if ( $filter_date ) {
            $where[] = 'a.booking_date = %s';
            $values[] = $filter_date;
        }
        if ( $filter_provider ) {
            $where[] = 'a.provider_id = %d';
            $values[] = $filter_provider;
        }
        if ( $filter_service ) {
            $where[] = 'a.service_id = %d';
            $values[] = $filter_service;
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT a.*,
                         s.name AS service_name, s.color AS service_color, s.duration, s.price,
                         p.name AS provider_name,
                         c.first_name, c.last_name, c.email, c.phone
                  FROM {$tables['appointments']} a
                  LEFT JOIN {$tables['services']} s ON a.service_id = s.id
                  LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
                  LEFT JOIN {$tables['customers']} c ON a.customer_id = c.id
                  WHERE {$where_clause}
                  ORDER BY a.booking_date DESC, a.booking_time ASC";

        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( $query, $values );
        }

        $appointments = $wpdb->get_results( $query );

        // Get providers for filter
        $providers = $wpdb->get_results( "SELECT id, name FROM {$tables['providers']} WHERE status = 'active' ORDER BY name" );

        // Get services for filter
        $services = $wpdb->get_results( "SELECT id, name, color FROM {$tables['services']} WHERE status = 'active' ORDER BY name" );

        // Count by status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$tables['appointments']} GROUP BY status",
            OBJECT_K
        );

        $this->render_admin_header( __( 'Appointments', 'guidepost' ) );
        ?>
        <div class="guidepost-admin-content">
            <!-- View Toggle & Filters Row -->
            <div class="guidepost-appointments-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <!-- View Toggle -->
                <div class="guidepost-view-toggle">
                    <a href="<?php echo esc_url( add_query_arg( 'view', 'list' ) ); ?>"
                       class="button <?php echo 'list' === $view_mode ? 'button-primary' : ''; ?>">
                        <span class="dashicons dashicons-list-view" style="vertical-align: middle;"></span>
                        <?php esc_html_e( 'List', 'guidepost' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'view', 'calendar' ) ); ?>"
                       class="button <?php echo 'calendar' === $view_mode ? 'button-primary' : ''; ?>">
                        <span class="dashicons dashicons-calendar-alt" style="vertical-align: middle;"></span>
                        <?php esc_html_e( 'Calendar', 'guidepost' ); ?>
                    </a>
                </div>

                <!-- Filters -->
                <form method="get" class="guidepost-filters" style="display: flex; gap: 8px; align-items: center;">
                    <input type="hidden" name="page" value="guidepost-appointments">
                    <input type="hidden" name="view" value="<?php echo esc_attr( $view_mode ); ?>">
                    <?php if ( $filter_status ) : ?>
                        <input type="hidden" name="status" value="<?php echo esc_attr( $filter_status ); ?>">
                    <?php endif; ?>

                    <select name="provider" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e( 'All Providers', 'guidepost' ); ?></option>
                        <?php foreach ( $providers as $provider ) : ?>
                            <option value="<?php echo esc_attr( $provider->id ); ?>" <?php selected( $filter_provider, $provider->id ); ?>>
                                <?php echo esc_html( $provider->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="service" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e( 'All Services', 'guidepost' ); ?></option>
                        <?php foreach ( $services as $service ) : ?>
                            <option value="<?php echo esc_attr( $service->id ); ?>" <?php selected( $filter_service, $service->id ); ?>>
                                <?php echo esc_html( $service->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ( 'list' === $view_mode ) : ?>
                        <input type="date" name="date" value="<?php echo esc_attr( $filter_date ); ?>">
                        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'guidepost' ); ?></button>
                    <?php endif; ?>

                    <?php if ( $filter_date || $filter_provider || $filter_service ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-appointments', 'view' => $view_mode ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                            <?php esc_html_e( 'Clear', 'guidepost' ); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ( 'list' === $view_mode ) : ?>
            <!-- Status Tabs (List View Only) -->
            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url( add_query_arg( array( 'view' => 'list' ), remove_query_arg( 'status' ) ) ); ?>" <?php echo empty( $filter_status ) ? 'class="current"' : ''; ?>>
                        <?php esc_html_e( 'All', 'guidepost' ); ?>
                        <span class="count">(<?php echo array_sum( array_column( (array) $status_counts, 'count' ) ); ?>)</span>
                    </a> |
                </li>
                <?php
                $statuses = array(
                    'pending'   => __( 'Pending', 'guidepost' ),
                    'approved'  => __( 'Approved', 'guidepost' ),
                    'completed' => __( 'Completed', 'guidepost' ),
                    'canceled'  => __( 'Canceled', 'guidepost' ),
                    'no_show'   => __( 'No Show', 'guidepost' ),
                );
                $i = 0;
                foreach ( $statuses as $status_key => $status_label ) :
                    $count = isset( $status_counts[ $status_key ] ) ? $status_counts[ $status_key ]->count : 0;
                    $i++;
                ?>
                <li>
                    <a href="<?php echo esc_url( add_query_arg( array( 'status' => $status_key, 'view' => 'list' ) ) ); ?>" <?php echo $filter_status === $status_key ? 'class="current"' : ''; ?>>
                        <?php echo esc_html( $status_label ); ?>
                        <span class="count">(<?php echo esc_html( $count ); ?>)</span>
                    </a><?php echo $i < count( $statuses ) ? ' |' : ''; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <div style="clear: both; margin-bottom: 15px;"></div>
            <?php endif; ?>

            <?php if ( 'calendar' === $view_mode ) : ?>
                <!-- Calendar View -->
                <div id="guidepost-calendar"></div>

                <!-- Calendar Events Data -->
                <script type="text/javascript">
                    var guidepostCalendarEvents = <?php
                        $calendar_events = array();
                        foreach ( $appointments as $apt ) {
                            $start_datetime = $apt->booking_date . 'T' . $apt->booking_time;
                            $end_datetime = $apt->booking_date . 'T' . $apt->end_time;

                            $calendar_events[] = array(
                                'id'              => $apt->id,
                                'title'           => $apt->first_name . ' ' . $apt->last_name . ' - ' . $apt->service_name,
                                'start'           => $start_datetime,
                                'end'             => $end_datetime,
                                'backgroundColor' => $apt->service_color ?: '#c16107',
                                'borderColor'     => $apt->service_color ?: '#c16107',
                                'extendedProps'   => array(
                                    'customer'     => $apt->first_name . ' ' . $apt->last_name,
                                    'email'        => $apt->email,
                                    'phone'        => $apt->phone,
                                    'service'      => $apt->service_name,
                                    'provider'     => $apt->provider_name,
                                    'status'       => $apt->status,
                                    'duration'     => $apt->duration,
                                    'price'        => $apt->price,
                                ),
                            );
                        }
                        echo wp_json_encode( $calendar_events );
                    ?>;
                    var guidepostCalendarFilters = {
                        provider: <?php echo (int) $filter_provider; ?>,
                        service: <?php echo (int) $filter_service; ?>
                    };
                </script>

            <?php else : ?>
                <!-- List View -->
                <?php
                $statuses = array(
                    'pending'   => __( 'Pending', 'guidepost' ),
                    'approved'  => __( 'Approved', 'guidepost' ),
                    'completed' => __( 'Completed', 'guidepost' ),
                    'canceled'  => __( 'Canceled', 'guidepost' ),
                    'no_show'   => __( 'No Show', 'guidepost' ),
                );
                ?>
                <?php if ( empty( $appointments ) ) : ?>
                    <p><?php esc_html_e( 'No appointments found.', 'guidepost' ); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 8px;"></th>
                                <th><?php esc_html_e( 'Date & Time', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Customer', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Service', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Provider', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Price', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'guidepost' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $appointments as $apt ) : ?>
                                <tr>
                                    <td>
                                        <span class="guidepost-color-indicator" style="background-color: <?php echo esc_attr( $apt->service_color ?: '#999' ); ?>"></span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $apt->booking_date ) ) ); ?></strong><br>
                                        <span class="description">
                                            <?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $apt->booking_time ) ) ); ?>
                                            - <?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $apt->end_time ) ) ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $apt->first_name . ' ' . $apt->last_name ); ?></strong><br>
                                        <a href="mailto:<?php echo esc_attr( $apt->email ); ?>" class="description"><?php echo esc_html( $apt->email ); ?></a>
                                        <?php if ( $apt->phone ) : ?>
                                            <br><span class="description"><?php echo esc_html( $apt->phone ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $apt->service_name ); ?><br>
                                        <span class="description"><?php echo esc_html( $apt->duration ); ?> <?php esc_html_e( 'min', 'guidepost' ); ?></span>
                                    </td>
                                    <td><?php echo esc_html( $apt->provider_name ); ?></td>
                                    <td>
                                        <?php
                                        if ( function_exists( 'wc_price' ) ) {
                                            echo wc_price( $apt->price );
                                        } else {
                                            echo '$' . number_format( $apt->price, 2 );
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="guidepost-status guidepost-status-<?php echo esc_attr( str_replace( '_', '-', $apt->status ) ); ?>">
                                            <?php echo esc_html( ucfirst( str_replace( '_', ' ', $apt->status ) ) ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field( 'update_appointment_status', 'appointment_status_nonce' ); ?>
                                            <input type="hidden" name="appointment_id" value="<?php echo esc_attr( $apt->id ); ?>">
                                            <select name="new_status" onchange="this.form.submit();" style="width: auto;">
                                                <option value=""><?php esc_html_e( 'Change...', 'guidepost' ); ?></option>
                                                <?php foreach ( $statuses as $status_key => $status_label ) : ?>
                                                    <?php if ( $status_key !== $apt->status ) : ?>
                                                        <option value="<?php echo esc_attr( $status_key ); ?>">
                                                            <?php echo esc_html( $status_label ); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render services page
     */
    public function render_services_page() {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-services.php';

        $action     = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $service_id = isset( $_GET['service_id'] ) ? absint( $_GET['service_id'] ) : 0;

        // Show form for add/edit
        if ( 'add' === $action || 'edit' === $action ) {
            $service = $service_id ? GuidePost_Services::get_service( $service_id ) : null;
            $title   = $service ? __( 'Edit Service', 'guidepost' ) : __( 'Add Service', 'guidepost' );

            $this->render_admin_header( $title );
            $this->render_service_form( $service );
            return;
        }

        // Show list
        $this->render_admin_header( __( 'Services', 'guidepost' ) );
        $this->render_admin_notices();
        ?>
        <div class="guidepost-admin-content">
            <p>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-services', 'action' => 'add' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Add New Service', 'guidepost' ); ?>
                </a>
            </p>

            <?php
            $services = GuidePost_Services::get_services();

            if ( empty( $services ) ) {
                echo '<p>' . esc_html__( 'No services found. Add your first service to get started.', 'guidepost' ) . '</p>';
            } else {
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 8px;"></th>
                            <th><?php esc_html_e( 'Name', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Duration', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Price', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'guidepost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $services as $service ) : ?>
                            <tr>
                                <td><span class="guidepost-color-indicator" style="background-color: <?php echo esc_attr( $service->color ); ?>"></span></td>
                                <td>
                                    <strong><?php echo esc_html( $service->name ); ?></strong>
                                    <?php if ( $service->description ) : ?>
                                        <br><span class="description"><?php echo esc_html( wp_trim_words( $service->description, 10 ) ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $service->duration ); ?> <?php esc_html_e( 'min', 'guidepost' ); ?></td>
                                <td>
                                    <?php
                                    if ( function_exists( 'wc_price' ) ) {
                                        echo wc_price( $service->price );
                                    } else {
                                        echo '$' . number_format( $service->price, 2 );
                                    }
                                    ?>
                                </td>
                                <td><span class="guidepost-status guidepost-status-<?php echo esc_attr( str_replace( '_', '-', $service->status ) ); ?>"><?php echo esc_html( ucfirst( $service->status ) ); ?></span></td>
                                <td>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-services', 'action' => 'edit', 'service_id' => $service->id ), admin_url( 'admin.php' ) ) ); ?>">
                                        <?php esc_html_e( 'Edit', 'guidepost' ); ?>
                                    </a> |
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'guidepost-services', 'action' => 'delete', 'service_id' => $service->id ), admin_url( 'admin.php' ) ), 'delete_service_' . $service->id ) ); ?>" class="guidepost-delete-btn" style="color: #a00;">
                                        <?php esc_html_e( 'Delete', 'guidepost' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render service form
     *
     * @param object|null $service Service object or null for new.
     */
    private function render_service_form( $service = null ) {
        $is_edit = ! empty( $service );
        ?>
        <div class="guidepost-admin-content">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-services' ) ); ?>">
                <?php wp_nonce_field( 'guidepost_save_service', 'guidepost_service_nonce' ); ?>
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="service_id" value="<?php echo esc_attr( $service->id ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name"><?php esc_html_e( 'Service Name', 'guidepost' ); ?> *</label></th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required
                                   value="<?php echo esc_attr( $is_edit ? $service->name : '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php esc_html_e( 'Description', 'guidepost' ); ?></label></th>
                        <td>
                            <textarea name="description" id="description" rows="4" class="large-text"><?php echo esc_textarea( $is_edit ? $service->description : '' ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="duration"><?php esc_html_e( 'Duration (minutes)', 'guidepost' ); ?></label></th>
                        <td>
                            <select name="duration" id="duration">
                                <?php
                                $durations = array( 15, 30, 45, 60, 90, 120, 150, 180, 240 );
                                $current   = $is_edit ? $service->duration : 60;
                                foreach ( $durations as $d ) {
                                    printf( '<option value="%d" %s>%d minutes</option>', $d, selected( $current, $d, false ), $d );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="price"><?php esc_html_e( 'Price', 'guidepost' ); ?></label></th>
                        <td>
                            <input type="number" name="price" id="price" step="0.01" min="0" class="small-text"
                                   value="<?php echo esc_attr( $is_edit ? $service->price : '0.00' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deposit_amount"><?php esc_html_e( 'Deposit Amount', 'guidepost' ); ?></label></th>
                        <td>
                            <input type="number" name="deposit_amount" id="deposit_amount" step="0.01" min="0" class="small-text"
                                   value="<?php echo esc_attr( $is_edit ? $service->deposit_amount : '0.00' ); ?>">
                            <select name="deposit_type" id="deposit_type">
                                <option value="fixed" <?php selected( $is_edit ? $service->deposit_type : 'fixed', 'fixed' ); ?>><?php esc_html_e( 'Fixed Amount', 'guidepost' ); ?></option>
                                <option value="percentage" <?php selected( $is_edit ? $service->deposit_type : '', 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'guidepost' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="color"><?php esc_html_e( 'Color', 'guidepost' ); ?></label></th>
                        <td>
                            <input type="color" name="color" id="color"
                                   value="<?php echo esc_attr( $is_edit ? $service->color : '#c16107' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="buffer_before"><?php esc_html_e( 'Buffer Time', 'guidepost' ); ?></label></th>
                        <td>
                            <input type="number" name="buffer_before" id="buffer_before" min="0" class="small-text"
                                   value="<?php echo esc_attr( $is_edit ? $service->buffer_before : 0 ); ?>">
                            <?php esc_html_e( 'minutes before', 'guidepost' ); ?>
                            &nbsp;&nbsp;
                            <input type="number" name="buffer_after" id="buffer_after" min="0" class="small-text"
                                   value="<?php echo esc_attr( $is_edit ? $service->buffer_after : 0 ); ?>">
                            <?php esc_html_e( 'minutes after', 'guidepost' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status"><?php esc_html_e( 'Status', 'guidepost' ); ?></label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected( $is_edit ? $service->status : 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'guidepost' ); ?></option>
                                <option value="inactive" <?php selected( $is_edit ? $service->status : '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'guidepost' ); ?></option>
                                <option value="hidden" <?php selected( $is_edit ? $service->status : '', 'hidden' ); ?>><?php esc_html_e( 'Hidden', 'guidepost' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sort_order"><?php esc_html_e( 'Sort Order', 'guidepost' ); ?></label></th>
                        <td>
                            <input type="number" name="sort_order" id="sort_order" min="0" class="small-text"
                                   value="<?php echo esc_attr( $is_edit ? $service->sort_order : 0 ); ?>">
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php submit_button( $is_edit ? __( 'Update Service', 'guidepost' ) : __( 'Add Service', 'guidepost' ), 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-services' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'guidepost' ); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render admin notices
     */
    private function render_admin_notices() {
        if ( isset( $_GET['message'] ) ) {
            $messages = array(
                'created' => __( 'Item created successfully.', 'guidepost' ),
                'updated' => __( 'Item updated successfully.', 'guidepost' ),
                'deleted' => __( 'Item deleted successfully.', 'guidepost' ),
            );
            $msg = sanitize_text_field( $_GET['message'] );
            if ( isset( $messages[ $msg ] ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $msg ] ) . '</p></div>';
            }
        }

        if ( isset( $_GET['error'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( urldecode( $_GET['error'] ) ) . '</p></div>';
        }
    }

    /**
     * Render providers page
     */
    public function render_providers_page() {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-providers.php';
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-services.php';

        $action      = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $provider_id = isset( $_GET['provider_id'] ) ? absint( $_GET['provider_id'] ) : 0;

        // Show form for add/edit
        if ( 'add' === $action || 'edit' === $action ) {
            $provider = $provider_id ? GuidePost_Providers::get_provider( $provider_id ) : null;
            $title    = $provider ? __( 'Edit Provider', 'guidepost' ) : __( 'Add Provider', 'guidepost' );

            $this->render_admin_header( $title );
            $this->render_provider_form( $provider );
            return;
        }

        // Show list
        $this->render_admin_header( __( 'Providers', 'guidepost' ) );
        $this->render_admin_notices();
        ?>
        <div class="guidepost-admin-content">
            <p>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-providers', 'action' => 'add' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Add New Provider', 'guidepost' ); ?>
                </a>
            </p>

            <?php
            $providers = GuidePost_Providers::get_providers();

            if ( empty( $providers ) ) {
                echo '<p>' . esc_html__( 'No providers found. Add your first provider to get started.', 'guidepost' ) . '</p>';
            } else {
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Phone', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Services', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'guidepost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $providers as $provider ) : ?>
                            <?php $services = GuidePost_Providers::get_provider_services( $provider->id ); ?>
                            <tr>
                                <td><strong><?php echo esc_html( $provider->name ); ?></strong></td>
                                <td><?php echo esc_html( $provider->email ); ?></td>
                                <td><?php echo esc_html( $provider->phone ); ?></td>
                                <td><?php echo esc_html( count( $services ) ); ?> <?php esc_html_e( 'services', 'guidepost' ); ?></td>
                                <td><span class="guidepost-status guidepost-status-<?php echo esc_attr( str_replace( '_', '-', $provider->status ) ); ?>"><?php echo esc_html( ucfirst( $provider->status ) ); ?></span></td>
                                <td>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-providers', 'action' => 'edit', 'provider_id' => $provider->id ), admin_url( 'admin.php' ) ) ); ?>">
                                        <?php esc_html_e( 'Edit', 'guidepost' ); ?>
                                    </a> |
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'guidepost-providers', 'action' => 'delete', 'provider_id' => $provider->id ), admin_url( 'admin.php' ) ), 'delete_provider_' . $provider->id ) ); ?>" class="guidepost-delete-btn" style="color: #a00;">
                                        <?php esc_html_e( 'Delete', 'guidepost' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render provider form
     *
     * @param object|null $provider Provider object or null for new.
     */
    private function render_provider_form( $provider = null ) {
        $is_edit        = ! empty( $provider );
        $services       = GuidePost_Services::get_services( array( 'status' => 'active' ) );
        $provider_services = $is_edit ? GuidePost_Providers::get_provider_services( $provider->id ) : array();
        $working_hours  = $is_edit ? GuidePost_Providers::get_working_hours( $provider->id ) : array();

        $days = array(
            0 => __( 'Sunday', 'guidepost' ),
            1 => __( 'Monday', 'guidepost' ),
            2 => __( 'Tuesday', 'guidepost' ),
            3 => __( 'Wednesday', 'guidepost' ),
            4 => __( 'Thursday', 'guidepost' ),
            5 => __( 'Friday', 'guidepost' ),
            6 => __( 'Saturday', 'guidepost' ),
        );
        ?>
        <div class="guidepost-admin-content">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-providers' ) ); ?>">
                <?php wp_nonce_field( 'guidepost_save_provider', 'guidepost_provider_nonce' ); ?>
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="provider_id" value="<?php echo esc_attr( $provider->id ); ?>">
                <?php endif; ?>

                <h3><?php esc_html_e( 'Basic Information', 'guidepost' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name"><?php esc_html_e( 'Name', 'guidepost' ); ?> *</label></th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required
                                   value="<?php echo esc_attr( $is_edit ? $provider->name : '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email"><?php esc_html_e( 'Email', 'guidepost' ); ?> *</label></th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" required
                                   value="<?php echo esc_attr( $is_edit ? $provider->email : '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="phone"><?php esc_html_e( 'Phone', 'guidepost' ); ?></label></th>
                        <td>
                            <input type="tel" name="phone" id="phone" class="regular-text"
                                   value="<?php echo esc_attr( $is_edit ? $provider->phone : '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bio"><?php esc_html_e( 'Bio', 'guidepost' ); ?></label></th>
                        <td>
                            <textarea name="bio" id="bio" rows="4" class="large-text"><?php echo esc_textarea( $is_edit ? $provider->bio : '' ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="timezone"><?php esc_html_e( 'Timezone', 'guidepost' ); ?></label></th>
                        <td>
                            <select name="timezone" id="timezone">
                                <?php echo wp_timezone_choice( $is_edit ? $provider->timezone : 'America/New_York' ); ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status"><?php esc_html_e( 'Status', 'guidepost' ); ?></label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected( $is_edit ? $provider->status : 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'guidepost' ); ?></option>
                                <option value="inactive" <?php selected( $is_edit ? $provider->status : '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'guidepost' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e( 'Assigned Services', 'guidepost' ); ?></h3>
                <?php if ( empty( $services ) ) : ?>
                    <p><?php esc_html_e( 'No services available. Create services first.', 'guidepost' ); ?></p>
                <?php else : ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Services', 'guidepost' ); ?></th>
                            <td>
                                <?php foreach ( $services as $service ) : ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="services[]" value="<?php echo esc_attr( $service->id ); ?>"
                                            <?php checked( in_array( $service->id, $provider_services ) ); ?>>
                                        <span class="guidepost-color-indicator" style="background-color: <?php echo esc_attr( $service->color ); ?>"></span>
                                        <?php echo esc_html( $service->name ); ?>
                                        (<?php echo esc_html( $service->duration ); ?> min)
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <h3><?php esc_html_e( 'Working Hours', 'guidepost' ); ?></h3>
                <table class="form-table guidepost-working-hours">
                    <?php foreach ( $days as $day_num => $day_name ) : ?>
                        <?php
                        $day_hours = isset( $working_hours[ $day_num ] ) ? $working_hours[ $day_num ] : array(
                            'enabled' => false,
                            'start'   => '09:00:00',
                            'end'     => '17:00:00',
                        );
                        ?>
                        <tr>
                            <th scope="row" style="width: 150px;">
                                <label>
                                    <input type="checkbox" name="working_hours[<?php echo esc_attr( $day_num ); ?>][enabled]" value="1"
                                        <?php checked( $day_hours['enabled'] ); ?>>
                                    <?php echo esc_html( $day_name ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="time" name="working_hours[<?php echo esc_attr( $day_num ); ?>][start]"
                                       value="<?php echo esc_attr( substr( $day_hours['start'], 0, 5 ) ); ?>">
                                <?php esc_html_e( 'to', 'guidepost' ); ?>
                                <input type="time" name="working_hours[<?php echo esc_attr( $day_num ); ?>][end]"
                                       value="<?php echo esc_attr( substr( $day_hours['end'], 0, 5 ) ); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <p class="submit">
                    <?php submit_button( $is_edit ? __( 'Update Provider', 'guidepost' ) : __( 'Add Provider', 'guidepost' ), 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-providers' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'guidepost' ); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $this->render_admin_header( __( 'Settings', 'guidepost' ) );
        ?>
        <div class="guidepost-admin-content">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'guidepost_settings' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="guidepost_timezone"><?php esc_html_e( 'Timezone', 'guidepost' ); ?></label>
                        </th>
                        <td>
                            <select name="guidepost_timezone" id="guidepost_timezone">
                                <?php
                                $current_timezone = get_option( 'guidepost_timezone', wp_timezone_string() );
                                echo wp_timezone_choice( $current_timezone );
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="guidepost_time_slot_duration"><?php esc_html_e( 'Time Slot Duration (minutes)', 'guidepost' ); ?></label>
                        </th>
                        <td>
                            <select name="guidepost_time_slot_duration" id="guidepost_time_slot_duration">
                                <?php
                                $durations = array( 15, 30, 45, 60, 90, 120 );
                                $current   = get_option( 'guidepost_time_slot_duration', 30 );
                                foreach ( $durations as $duration ) {
                                    printf(
                                        '<option value="%d" %s>%d minutes</option>',
                                        $duration,
                                        selected( $current, $duration, false ),
                                        $duration
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="guidepost_admin_email"><?php esc_html_e( 'Admin Email', 'guidepost' ); ?></label>
                        </th>
                        <td>
                            <input type="email" name="guidepost_admin_email" id="guidepost_admin_email"
                                   value="<?php echo esc_attr( get_option( 'guidepost_admin_email', get_option( 'admin_email' ) ) ); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Email Notifications', 'guidepost' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="guidepost_email_notifications" value="1"
                                    <?php checked( get_option( 'guidepost_email_notifications', true ) ); ?>>
                                <?php esc_html_e( 'Send email notifications for new bookings', 'guidepost' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'WooCommerce Integration', 'guidepost' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="guidepost_woocommerce_enabled" value="1"
                                    <?php checked( get_option( 'guidepost_woocommerce_enabled', false ) ); ?>
                                    <?php disabled( ! class_exists( 'WooCommerce' ) ); ?>>
                                <?php esc_html_e( 'Enable WooCommerce for payments', 'guidepost' ); ?>
                            </label>
                            <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                                <p class="description"><?php esc_html_e( 'WooCommerce is not installed.', 'guidepost' ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Provider Customer Access', 'guidepost' ); ?>
                        </th>
                        <td>
                            <?php $provider_access = get_option( 'guidepost_provider_customer_access', 'all' ); ?>
                            <fieldset>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="radio" name="guidepost_provider_customer_access" value="all"
                                        <?php checked( $provider_access, 'all' ); ?>>
                                    <?php esc_html_e( 'All customers - Providers can view all customer records', 'guidepost' ); ?>
                                </label>
                                <label style="display: block;">
                                    <input type="radio" name="guidepost_provider_customer_access" value="own"
                                        <?php checked( $provider_access, 'own' ); ?>>
                                    <?php esc_html_e( 'Own customers only - Providers can only view customers they have appointments with', 'guidepost' ); ?>
                                </label>
                            </fieldset>
                            <p class="description"><?php esc_html_e( 'Controls which customer records providers can access when logged into the admin.', 'guidepost' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render admin header
     *
     * @param string $title Page title.
     */
    private function render_admin_header( $title ) {
        ?>
        <div class="wrap guidepost-admin">
            <h1 class="guidepost-admin-title">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo esc_html( $title ); ?>
            </h1>
        <?php
    }

    /**
     * AJAX: Update appointment status
     */
    public function ajax_update_appointment_status() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'guidepost' ) ) );
        }

        $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
        $new_status     = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

        if ( ! $appointment_id || ! $new_status ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'guidepost' ) ) );
        }

        // Validate status
        $valid_statuses = array( 'pending', 'approved', 'completed', 'canceled', 'no_show' );
        if ( ! in_array( $new_status, $valid_statuses, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid status.', 'guidepost' ) ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $updated = $wpdb->update(
            $tables['appointments'],
            array(
                'status'     => $new_status,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $appointment_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'guidepost' ) ) );
        }

        // Fire action for status change
        do_action( 'guidepost_appointment_status_changed', $appointment_id, $new_status );

        wp_send_json_success( array(
            'message' => __( 'Status updated successfully.', 'guidepost' ),
            'status'  => $new_status,
        ) );
    }
}
