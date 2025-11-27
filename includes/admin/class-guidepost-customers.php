<?php
/**
 * Customer Manager Admin functionality
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load customer helper methods.
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-customer-helpers.php';

/**
 * Customers class - handles customer management admin
 */
class GuidePost_Customers {

    /**
     * Single instance
     *
     * @var GuidePost_Customers
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return GuidePost_Customers
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
        add_action( 'admin_menu', array( $this, 'add_customers_menu' ), 20 );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );

        // AJAX handlers
        add_action( 'wp_ajax_guidepost_add_customer_note', array( $this, 'ajax_add_note' ) );
        add_action( 'wp_ajax_guidepost_delete_customer_note', array( $this, 'ajax_delete_note' ) );
        add_action( 'wp_ajax_guidepost_toggle_note_pin', array( $this, 'ajax_toggle_note_pin' ) );
        add_action( 'wp_ajax_guidepost_add_customer_flag', array( $this, 'ajax_add_flag' ) );
        add_action( 'wp_ajax_guidepost_dismiss_flag', array( $this, 'ajax_dismiss_flag' ) );
        add_action( 'wp_ajax_guidepost_adjust_credits', array( $this, 'ajax_adjust_credits' ) );
        add_action( 'wp_ajax_guidepost_update_customer_status', array( $this, 'ajax_update_status' ) );
        add_action( 'wp_ajax_guidepost_save_customer_field', array( $this, 'ajax_save_field' ) );
        add_action( 'wp_ajax_guidepost_get_active_flags_count', array( $this, 'ajax_get_flags_count' ) );
        add_action( 'wp_ajax_guidepost_export_ics', array( $this, 'ajax_export_ics' ) );
    }

    /**
     * Add customers menu
     */
    public function add_customers_menu() {
        $flags_count = GuidePost_Customer_Helpers::get_active_flags_count();
        $badge = $flags_count > 0 ? ' <span class="awaiting-mod">' . $flags_count . '</span>' : '';

        add_submenu_page(
            'guidepost',
            __( 'Customers', 'guidepost' ),
            __( 'Customers', 'guidepost' ) . $badge,
            'manage_options',
            'guidepost-customers',
            array( $this, 'render_customers_page' )
        );
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        // Handle customer save
        if ( isset( $_POST['guidepost_customer_nonce'] ) && wp_verify_nonce( $_POST['guidepost_customer_nonce'], 'guidepost_save_customer' ) ) {
            $this->handle_save_customer();
        }

        // Handle customer delete
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['customer_id'] ) ) {
            if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_customer_' . $_GET['customer_id'] ) ) {
                $this->handle_delete_customer( absint( $_GET['customer_id'] ) );
            }
        }
    }

    /**
     * Handle save customer
     */
    private function handle_save_customer() {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;

        $data = array(
            'first_name'        => sanitize_text_field( $_POST['first_name'] ),
            'last_name'         => sanitize_text_field( $_POST['last_name'] ),
            'email'             => sanitize_email( $_POST['email'] ),
            'phone'             => sanitize_text_field( $_POST['phone'] ?? '' ),
            'status'            => sanitize_text_field( $_POST['status'] ?? 'active' ),
            'company'           => sanitize_text_field( $_POST['company'] ?? '' ),
            'job_title'         => sanitize_text_field( $_POST['job_title'] ?? '' ),
            'birthday'          => ! empty( $_POST['birthday'] ) ? sanitize_text_field( $_POST['birthday'] ) : null,
            'timezone'          => sanitize_text_field( $_POST['timezone'] ?? '' ),
            'preferred_contact' => sanitize_text_field( $_POST['preferred_contact'] ?? 'email' ),
            'source'            => sanitize_text_field( $_POST['source'] ?? '' ),
            'google_drive_url'  => esc_url_raw( $_POST['google_drive_url'] ?? '' ),
            'notes'             => sanitize_textarea_field( $_POST['notes'] ?? '' ),
            'tags'              => sanitize_text_field( $_POST['tags'] ?? '' ),
        );

        // Handle first contact date
        if ( ! $customer_id && empty( $_POST['first_contact_date'] ) ) {
            $data['first_contact_date'] = current_time( 'Y-m-d' );
        } elseif ( ! empty( $_POST['first_contact_date'] ) ) {
            $data['first_contact_date'] = sanitize_text_field( $_POST['first_contact_date'] );
        }

        // 30-60-90 integration
        if ( isset( $_POST['project_journey_id'] ) ) {
            $data['project_journey_id'] = absint( $_POST['project_journey_id'] ) ?: null;
        }
        if ( isset( $_POST['project_journey_user_id'] ) ) {
            $data['project_journey_user_id'] = absint( $_POST['project_journey_user_id'] ) ?: null;
        }

        if ( $customer_id ) {
            $wpdb->update( $tables['customers'], $data, array( 'id' => $customer_id ) );
            $message = 'customer_updated';
        } else {
            $wpdb->insert( $tables['customers'], $data );
            $customer_id = $wpdb->insert_id;
            $message = 'customer_created';
        }

        wp_redirect( add_query_arg( array(
            'page'        => 'guidepost-customers',
            'action'      => 'view',
            'customer_id' => $customer_id,
            'message'     => $message,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle delete customer
     *
     * @param int $customer_id Customer ID.
     */
    private function handle_delete_customer( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        // Delete related records first
        $wpdb->delete( $tables['customer_notes'], array( 'customer_id' => $customer_id ) );
        $wpdb->delete( $tables['customer_purchases'], array( 'customer_id' => $customer_id ) );
        $wpdb->delete( $tables['customer_documents'], array( 'customer_id' => $customer_id ) );
        $wpdb->delete( $tables['customer_flags'], array( 'customer_id' => $customer_id ) );
        $wpdb->delete( $tables['credit_history'], array( 'customer_id' => $customer_id ) );

        // Delete customer
        $wpdb->delete( $tables['customers'], array( 'id' => $customer_id ) );

        wp_redirect( add_query_arg( array(
            'page'    => 'guidepost-customers',
            'message' => 'customer_deleted',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Render customers page
     */
    public function render_customers_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        echo '<div class="wrap guidepost-admin guidepost-customers-page">';

        switch ( $action ) {
            case 'view':
                $this->render_customer_detail();
                break;
            case 'edit':
            case 'add':
                $this->render_customer_form();
                break;
            default:
                $this->render_customer_list();
        }

        echo '</div>';
    }

    /**
     * Render customer list
     */
    private function render_customer_list() {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        // Filters
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
        $order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( $_GET['order'] ) ) : 'DESC';

        // Build query
        $where = array( '1=1' );
        $values = array();

        if ( $search ) {
            $where[] = "(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.company LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $search ) . '%';
            $values = array_merge( $values, array( $search_term, $search_term, $search_term, $search_term ) );
        }

        if ( $status ) {
            $where[] = 'c.status = %s';
            $values[] = $status;
        }

        $where_clause = implode( ' AND ', $where );
        $order_clause = sanitize_sql_orderby( "c.$orderby $order" ) ?: 'c.created_at DESC';

        // Query with calculated stats from appointments and payments
        $query = "SELECT c.*,
                         COUNT(DISTINCT a.id) as calc_total_appointments,
                         COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) as calc_total_spent,
                         MAX(CASE WHEN a.status IN ('completed', 'approved') THEN a.booking_date ELSE NULL END) as calc_last_booking_date
                  FROM {$tables['customers']} c
                  LEFT JOIN {$tables['appointments']} a ON c.id = a.customer_id
                  LEFT JOIN {$tables['payments']} p ON a.id = p.appointment_id
                  WHERE {$where_clause}
                  GROUP BY c.id
                  ORDER BY {$order_clause}";
        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( $query, $values );
        }

        $customers = $wpdb->get_results( $query );

        // Use calculated values, falling back to stored values
        foreach ( $customers as $customer ) {
            $customer->total_appointments = $customer->calc_total_appointments ?: $customer->total_appointments;
            $customer->total_spent = $customer->calc_total_spent ?: $customer->total_spent;
            $customer->last_booking_date = $customer->calc_last_booking_date ?: $customer->last_booking_date;
        }

        // Get status counts
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$tables['customers']} GROUP BY status",
            OBJECT_K
        );

        $this->render_admin_notices();
        ?>
        <h1 class="guidepost-admin-title">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e( 'Customers', 'guidepost' ); ?>
            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-customers', 'action' => 'add' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Add New', 'guidepost' ); ?>
            </a>
        </h1>

        <!-- Main Content Area with White Background -->
        <div class="guidepost-admin-content">
            <!-- Status Tabs & Search Row -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 15px 0;">
                <ul class="subsubsub" style="margin: 0; float: none;">
                    <li>
                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-customers' ), admin_url( 'admin.php' ) ) ); ?>" <?php echo empty( $status ) ? 'class="current"' : ''; ?>>
                            <?php esc_html_e( 'All', 'guidepost' ); ?>
                            <span class="count">(<?php echo array_sum( wp_list_pluck( $status_counts, 'count' ) ); ?>)</span>
                        </a> |
                    </li>
                    <?php
                    $statuses = array(
                        'active'   => __( 'Active', 'guidepost' ),
                        'vip'      => __( 'VIP', 'guidepost' ),
                        'paused'   => __( 'Paused', 'guidepost' ),
                        'inactive' => __( 'Inactive', 'guidepost' ),
                        'prospect' => __( 'Prospect', 'guidepost' ),
                    );
                    $visible_statuses = array();
                    foreach ( $statuses as $s => $label ) {
                        $count = isset( $status_counts[ $s ] ) ? $status_counts[ $s ]->count : 0;
                        if ( $count > 0 ) {
                            $visible_statuses[ $s ] = array( 'label' => $label, 'count' => $count );
                        }
                    }
                    $i = 0;
                    $total = count( $visible_statuses );
                    foreach ( $visible_statuses as $s => $data ) :
                        $i++;
                    ?>
                    <li>
                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-customers', 'status' => $s ), admin_url( 'admin.php' ) ) ); ?>" <?php echo $status === $s ? 'class="current"' : ''; ?>>
                            <?php echo esc_html( $data['label'] ); ?>
                            <span class="count">(<?php echo esc_html( $data['count'] ); ?>)</span>
                        </a><?php echo $i < $total ? ' |' : ''; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Search Box -->
                <form method="get" class="search-box" style="margin: 0;">
                    <input type="hidden" name="page" value="guidepost-customers">
                    <?php if ( $status ) : ?>
                        <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                    <?php endif; ?>
                    <label class="screen-reader-text" for="customer-search-input"><?php esc_html_e( 'Search Customers', 'guidepost' ); ?></label>
                    <input type="search" id="customer-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search customers...', 'guidepost' ); ?>">
                    <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', 'guidepost' ); ?>">
                </form>
            </div>

            <!-- Customer List -->
            <?php if ( empty( $customers ) ) : ?>
                <p><?php esc_html_e( 'No customers found.', 'guidepost' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped guidepost-customers-table">
                    <thead>
                        <tr>
                            <th class="column-customer"><?php esc_html_e( 'Customer', 'guidepost' ); ?></th>
                            <th class="column-contact"><?php esc_html_e( 'Contact', 'guidepost' ); ?></th>
                            <th class="column-status"><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                            <th class="column-appointments"><?php esc_html_e( 'Appointments', 'guidepost' ); ?></th>
                            <th class="column-spent"><?php esc_html_e( 'Total Spent', 'guidepost' ); ?></th>
                            <th class="column-credits"><?php esc_html_e( 'Credits', 'guidepost' ); ?></th>
                            <th class="column-last-visit"><?php esc_html_e( 'Last Visit', 'guidepost' ); ?></th>
                            <th class="column-actions"><?php esc_html_e( 'Actions', 'guidepost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $customers as $customer ) : ?>
                            <?php
                            $initials = strtoupper( substr( $customer->first_name, 0, 1 ) . substr( $customer->last_name, 0, 1 ) );
                            $view_url = add_query_arg( array(
                                'page'        => 'guidepost-customers',
                                'action'      => 'view',
                                'customer_id' => $customer->id,
                            ), admin_url( 'admin.php' ) );
                            $edit_url = add_query_arg( array(
                                'page'        => 'guidepost-customers',
                                'action'      => 'edit',
                                'customer_id' => $customer->id,
                            ), admin_url( 'admin.php' ) );
                            $delete_url = wp_nonce_url(
                                add_query_arg( array(
                                    'page'        => 'guidepost-customers',
                                    'action'      => 'delete',
                                    'customer_id' => $customer->id,
                                ), admin_url( 'admin.php' ) ),
                                'delete_customer_' . $customer->id
                            );
                            $flags = GuidePost_Customer_Helpers::get_customer_flags( $customer->id, true );
                            ?>
                            <tr>
                                <td class="column-customer">
                                    <div class="customer-info">
                                        <div class="customer-avatar" style="background-color: <?php echo esc_attr( GuidePost_Customer_Helpers::get_status_color( $customer->status ) ); ?>">
                                            <?php echo esc_html( $initials ); ?>
                                        </div>
                                        <div class="customer-details">
                                            <a href="<?php echo esc_url( $view_url ); ?>" class="customer-name">
                                                <?php echo esc_html( $customer->first_name . ' ' . $customer->last_name ); ?>
                                            </a>
                                            <?php if ( $customer->company ) : ?>
                                                <span class="customer-company"><?php echo esc_html( $customer->company ); ?></span>
                                            <?php endif; ?>
                                            <?php if ( count( $flags ) > 0 ) : ?>
                                                <span class="customer-flag-indicator" title="<?php echo esc_attr( count( $flags ) . ' active flags' ); ?>">
                                                    <span class="dashicons dashicons-flag"></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="column-contact">
                                    <a href="mailto:<?php echo esc_attr( $customer->email ); ?>"><?php echo esc_html( $customer->email ); ?></a>
                                    <?php if ( $customer->phone ) : ?>
                                        <br><span class="phone"><?php echo esc_html( $customer->phone ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <span class="guidepost-status guidepost-status-<?php echo esc_attr( str_replace( '_', '-', $customer->status ) ); ?>">
                                        <?php echo esc_html( ucfirst( $customer->status ) ); ?>
                                    </span>
                                </td>
                                <td class="column-appointments">
                                    <?php echo esc_html( $customer->total_appointments ); ?>
                                </td>
                                <td class="column-spent">
                                    <?php echo esc_html( '$' . number_format( $customer->total_spent, 2 ) ); ?>
                                </td>
                                <td class="column-credits">
                                    <?php if ( $customer->total_credits > 0 ) : ?>
                                        <span class="credit-badge"><?php echo esc_html( $customer->total_credits ); ?></span>
                                    <?php else : ?>
                                        <span class="no-credits">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-last-visit">
                                    <?php
                                    if ( $customer->last_booking_date ) {
                                        echo esc_html( date_i18n( 'M j, Y', strtotime( $customer->last_booking_date ) ) );
                                    } else {
                                        echo '<span class="no-data">—</span>';
                                    }
                                    ?>
                                </td>
                                <td class="column-actions">
                                    <div class="row-actions-buttons">
                                        <a href="<?php echo esc_url( $view_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'View customer details', 'guidepost' ); ?>">
                                            <?php esc_html_e( 'View', 'guidepost' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'Edit customer', 'guidepost' ); ?>">
                                            <?php esc_html_e( 'Edit', 'guidepost' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this customer? This action cannot be undone.', 'guidepost' ); ?>');" title="<?php esc_attr_e( 'Delete customer', 'guidepost' ); ?>">
                                            <?php esc_html_e( 'Delete', 'guidepost' ); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render customer detail view
     */
    private function render_customer_detail() {
        $customer_id = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
        $customer = GuidePost_Customer_Helpers::get_customer( $customer_id );

        if ( ! $customer ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Customer not found.', 'guidepost' ) . '</p></div>';
            return;
        }

        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';
        $initials = strtoupper( substr( $customer->first_name, 0, 1 ) . substr( $customer->last_name, 0, 1 ) );

        $this->render_admin_notices();
        ?>
        <div class="guidepost-customer-detail-layout">
        <div class="guidepost-customer-header">
            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-customers' ), admin_url( 'admin.php' ) ) ); ?>" class="guidepost-back-link">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Customers', 'guidepost' ); ?>
            </a>

            <div class="guidepost-customer-profile">
                <div class="customer-avatar-large" style="background-color: <?php echo esc_attr( GuidePost_Customer_Helpers::get_status_color( $customer->status ) ); ?>">
                    <?php echo esc_html( $initials ); ?>
                </div>
                <div class="customer-profile-info">
                    <h1 class="customer-name">
                        <?php echo esc_html( $customer->first_name . ' ' . $customer->last_name ); ?>
                        <span class="guidepost-status guidepost-status-<?php echo esc_attr( str_replace( '_', '-', $customer->status ) ); ?>">
                            <?php echo esc_html( ucfirst( $customer->status ) ); ?>
                        </span>
                    </h1>
                    <div class="customer-meta">
                        <?php if ( $customer->company ) : ?>
                            <span class="meta-item">
                                <span class="dashicons dashicons-building"></span>
                                <?php echo esc_html( $customer->company ); ?>
                                <?php if ( $customer->job_title ) : ?>
                                    — <?php echo esc_html( $customer->job_title ); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <span class="meta-item">
                            <span class="dashicons dashicons-email"></span>
                            <a href="mailto:<?php echo esc_attr( $customer->email ); ?>"><?php echo esc_html( $customer->email ); ?></a>
                        </span>
                        <?php if ( $customer->phone ) : ?>
                            <span class="meta-item">
                                <span class="dashicons dashicons-phone"></span>
                                <?php echo esc_html( $customer->phone ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ( $customer->tags ) : ?>
                        <div class="customer-tags">
                            <?php
                            $tags = array_map( 'trim', explode( ',', $customer->tags ) );
                            foreach ( $tags as $tag ) :
                                if ( $tag ) :
                            ?>
                                <span class="customer-tag"><?php echo esc_html( $tag ); ?></span>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="customer-actions">
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-communications', 'tab' => 'compose', 'customer_id' => $customer->id ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php esc_html_e( 'Send Email', 'guidepost' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-customers', 'action' => 'edit', 'customer_id' => $customer->id ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Edit', 'guidepost' ); ?>
                    </a>
                    <?php if ( $customer->google_drive_url ) : ?>
                        <a href="<?php echo esc_url( $customer->google_drive_url ); ?>" class="button" target="_blank">
                            <span class="dashicons dashicons-portfolio"></span>
                            <?php esc_html_e( 'Drive Folder', 'guidepost' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="guidepost-customer-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html( $customer->total_appointments ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Appointments', 'guidepost' ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html( '$' . number_format( $customer->total_spent, 2 ) ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Total Spent', 'guidepost' ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html( $customer->total_credits ); ?></div>
                    <div class="stat-label"><?php esc_html_e( 'Credits', 'guidepost' ); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php
                        if ( $customer->first_contact_date ) {
                            $days = floor( ( time() - strtotime( $customer->first_contact_date ) ) / DAY_IN_SECONDS );
                            echo esc_html( $days );
                        } else {
                            echo '—';
                        }
                        ?>
                    </div>
                    <div class="stat-label"><?php esc_html_e( 'Days as Customer', 'guidepost' ); ?></div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <nav class="guidepost-customer-tabs">
            <?php
            $tabs = array(
                'overview'       => array( 'icon' => 'dashicons-admin-home', 'label' => __( 'Overview', 'guidepost' ) ),
                'appointments'   => array( 'icon' => 'dashicons-calendar-alt', 'label' => __( 'Appointments', 'guidepost' ) ),
                'purchases'      => array( 'icon' => 'dashicons-cart', 'label' => __( 'Purchases', 'guidepost' ) ),
                'documents'      => array( 'icon' => 'dashicons-media-document', 'label' => __( 'Documents', 'guidepost' ) ),
                'communications' => array( 'icon' => 'dashicons-email', 'label' => __( 'Communications', 'guidepost' ) ),
                'notes'          => array( 'icon' => 'dashicons-edit-page', 'label' => __( 'Notes', 'guidepost' ) ),
            );
            foreach ( $tabs as $tab_key => $tab ) :
                $url = add_query_arg( array(
                    'page'        => 'guidepost-customers',
                    'action'      => 'view',
                    'customer_id' => $customer->id,
                    'tab'         => $tab_key,
                ), admin_url( 'admin.php' ) );
            ?>
                <a href="<?php echo esc_url( $url ); ?>"
                   class="guidepost-tab <?php echo $current_tab === $tab_key ? 'guidepost-tab-active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                    <?php echo esc_html( $tab['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Tab Content -->
        <div class="guidepost-customer-content">
            <div class="guidepost-customer-main">
                <?php
                switch ( $current_tab ) {
                    case 'appointments':
                        $this->render_appointments_tab( $customer );
                        break;
                    case 'purchases':
                        $this->render_purchases_tab( $customer );
                        break;
                    case 'documents':
                        $this->render_documents_tab( $customer );
                        break;
                    case 'communications':
                        $this->render_communications_tab( $customer );
                        break;
                    case 'notes':
                        $this->render_notes_tab( $customer );
                        break;
                    default:
                        $this->render_overview_tab( $customer );
                }
                ?>
            </div>
            <div class="guidepost-customer-sidebar">
                <?php $this->render_sidebar( $customer ); ?>
            </div>
        </div>

        <!-- Add Flag Modal -->
        <div id="add-flag-modal" class="guidepost-modal" style="display: none;">
            <div class="guidepost-modal-content">
                <div class="guidepost-modal-header">
                    <h2><?php esc_html_e( 'Add Flag', 'guidepost' ); ?></h2>
                    <button type="button" class="guidepost-modal-close">&times;</button>
                </div>
                <div class="guidepost-modal-body">
                    <form id="guidepost-add-flag-form">
                        <input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer->id ); ?>">

                        <div class="guidepost-form-row">
                            <label for="flag_type"><?php esc_html_e( 'Flag Type', 'guidepost' ); ?></label>
                            <select name="flag_type" id="flag_type" required>
                                <option value="follow_up"><?php esc_html_e( 'Follow Up', 'guidepost' ); ?></option>
                                <option value="payment_due"><?php esc_html_e( 'Payment Due', 'guidepost' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'guidepost' ); ?></option>
                                <option value="vip_check"><?php esc_html_e( 'VIP Check-in', 'guidepost' ); ?></option>
                                <option value="birthday"><?php esc_html_e( 'Birthday', 'guidepost' ); ?></option>
                                <option value="custom"><?php esc_html_e( 'Custom', 'guidepost' ); ?></option>
                            </select>
                        </div>

                        <div class="guidepost-form-row">
                            <label for="flag_message"><?php esc_html_e( 'Message', 'guidepost' ); ?></label>
                            <input type="text" name="message" id="flag_message" required placeholder="<?php esc_attr_e( 'Enter flag message...', 'guidepost' ); ?>">
                        </div>

                        <div class="guidepost-form-row">
                            <label for="flag_trigger_date"><?php esc_html_e( 'Trigger Date (optional)', 'guidepost' ); ?></label>
                            <input type="date" name="trigger_date" id="flag_trigger_date">
                        </div>

                        <div class="guidepost-modal-footer">
                            <button type="button" class="button guidepost-modal-close"><?php esc_html_e( 'Cancel', 'guidepost' ); ?></button>
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Flag', 'guidepost' ); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Credits Modal -->
        <div id="credits-modal" class="guidepost-modal" style="display: none;">
            <div class="guidepost-modal-content">
                <div class="guidepost-modal-header">
                    <h2><?php esc_html_e( 'Adjust Credits', 'guidepost' ); ?></h2>
                    <button type="button" class="guidepost-modal-close">&times;</button>
                </div>
                <div class="guidepost-modal-body">
                    <form id="guidepost-credits-form">
                        <input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer->id ); ?>">

                        <div class="guidepost-form-row">
                            <label><?php esc_html_e( 'Action', 'guidepost' ); ?></label>
                            <div class="guidepost-radio-group">
                                <label>
                                    <input type="radio" name="credit_type" value="add" checked>
                                    <?php esc_html_e( 'Add Credits', 'guidepost' ); ?>
                                </label>
                                <label>
                                    <input type="radio" name="credit_type" value="subtract">
                                    <?php esc_html_e( 'Subtract Credits', 'guidepost' ); ?>
                                </label>
                            </div>
                        </div>

                        <div class="guidepost-form-row">
                            <label for="credit_amount"><?php esc_html_e( 'Amount', 'guidepost' ); ?></label>
                            <input type="number" name="amount" id="credit_amount" min="1" value="1" required>
                        </div>

                        <div class="guidepost-form-row">
                            <label for="credit_reason"><?php esc_html_e( 'Reason', 'guidepost' ); ?></label>
                            <input type="text" name="reason" id="credit_reason" placeholder="<?php esc_attr_e( 'e.g., Package purchase, Session used', 'guidepost' ); ?>">
                        </div>

                        <div class="guidepost-form-row">
                            <p class="description">
                                <?php printf( esc_html__( 'Current balance: %d credits', 'guidepost' ), $customer->total_credits ); ?>
                            </p>
                        </div>

                        <div class="guidepost-modal-footer">
                            <button type="button" class="button guidepost-modal-close"><?php esc_html_e( 'Cancel', 'guidepost' ); ?></button>
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Update Credits', 'guidepost' ); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Status Modal -->
        <div id="status-modal" class="guidepost-modal" style="display: none;">
            <div class="guidepost-modal-content">
                <div class="guidepost-modal-header">
                    <h2><?php esc_html_e( 'Change Status', 'guidepost' ); ?></h2>
                    <button type="button" class="guidepost-modal-close">&times;</button>
                </div>
                <div class="guidepost-modal-body">
                    <form id="guidepost-status-form">
                        <input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer->id ); ?>">

                        <div class="guidepost-form-row">
                            <label for="new_status"><?php esc_html_e( 'New Status', 'guidepost' ); ?></label>
                            <select name="status" id="new_status" required>
                                <option value="active" <?php selected( $customer->status, 'active' ); ?>><?php esc_html_e( 'Active', 'guidepost' ); ?></option>
                                <option value="vip" <?php selected( $customer->status, 'vip' ); ?>><?php esc_html_e( 'VIP', 'guidepost' ); ?></option>
                                <option value="paused" <?php selected( $customer->status, 'paused' ); ?>><?php esc_html_e( 'Paused', 'guidepost' ); ?></option>
                                <option value="inactive" <?php selected( $customer->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'guidepost' ); ?></option>
                                <option value="prospect" <?php selected( $customer->status, 'prospect' ); ?>><?php esc_html_e( 'Prospect', 'guidepost' ); ?></option>
                            </select>
                        </div>

                        <div class="guidepost-modal-footer">
                            <button type="button" class="button guidepost-modal-close"><?php esc_html_e( 'Cancel', 'guidepost' ); ?></button>
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Update Status', 'guidepost' ); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div><!-- .guidepost-customer-detail-layout -->
        <?php
    }

    /**
     * Render overview tab
     *
     * @param object $customer Customer object.
     */
    private function render_overview_tab( $customer ) {
        ?>
        <!-- Timeline -->
        <div class="guidepost-section">
            <h2><?php esc_html_e( 'Customer Journey', 'guidepost' ); ?></h2>
            <?php $this->render_timeline( $customer ); ?>
        </div>

        <!-- Recent Appointments -->
        <div class="guidepost-section">
            <h2><?php esc_html_e( 'Recent Appointments', 'guidepost' ); ?></h2>
            <?php
            $appointments = GuidePost_Customer_Helpers::get_customer_appointments( $customer->id, 5 );
            if ( empty( $appointments ) ) :
            ?>
                <p class="no-data"><?php esc_html_e( 'No appointments yet.', 'guidepost' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Service', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Provider', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $appointments as $apt ) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $apt->booking_date ) ) ); ?>
                                    <br><small><?php echo esc_html( date_i18n( 'g:i A', strtotime( $apt->booking_time ) ) ); ?></small>
                                </td>
                                <td><?php echo esc_html( $apt->service_name ); ?></td>
                                <td><?php echo esc_html( $apt->provider_name ); ?></td>
                                <td>
                                    <?php if ( 'virtual' === $apt->appointment_mode ) : ?>
                                        <span class="appointment-mode appointment-mode-virtual">
                                            <span class="dashicons dashicons-video-alt3"></span>
                                            <?php esc_html_e( 'Virtual', 'guidepost' ); ?>
                                        </span>
                                        <?php if ( $apt->meeting_link ) : ?>
                                            <a href="<?php echo esc_url( $apt->meeting_link ); ?>" class="meeting-link" target="_blank" title="<?php esc_attr_e( 'Join Meeting', 'guidepost' ); ?>">
                                                <span class="dashicons dashicons-external"></span>
                                            </a>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="appointment-mode appointment-mode-in_person">
                                            <span class="dashicons dashicons-location"></span>
                                            <?php esc_html_e( 'In-Person', 'guidepost' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="guidepost-status guidepost-status-<?php echo esc_attr( str_replace( '_', '-', $apt->status ) ); ?>">
                                        <?php echo esc_html( ucfirst( str_replace( '_', ' ', $apt->status ) ) ); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pinned Notes -->
        <?php
        $pinned_notes = GuidePost_Customer_Helpers::get_customer_notes( $customer->id, array( 'is_pinned' => 1, 'limit' => 3 ) );
        if ( ! empty( $pinned_notes ) ) :
        ?>
            <div class="guidepost-section">
                <h2><?php esc_html_e( 'Pinned Notes', 'guidepost' ); ?></h2>
                <div class="guidepost-notes-list">
                    <?php foreach ( $pinned_notes as $note ) : ?>
                        <div class="note-item note-pinned">
                            <div class="note-header">
                                <span class="note-type note-type-<?php echo esc_attr( $note->note_type ); ?>">
                                    <?php echo esc_html( ucfirst( str_replace( '_', ' ', $note->note_type ) ) ); ?>
                                </span>
                                <span class="note-meta">
                                    <?php echo esc_html( $note->author_name ); ?> &middot;
                                    <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $note->created_at ) ) ); ?>
                                </span>
                            </div>
                            <div class="note-content"><?php echo wp_kses_post( nl2br( $note->note_text ) ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 30-60-90 Project Link -->
        <?php if ( $customer->project_journey_id ) : ?>
            <div class="guidepost-section">
                <h2><?php esc_html_e( '30-60-90 Project Journey', 'guidepost' ); ?></h2>
                <div class="project-journey-link">
                    <p>
                        <?php esc_html_e( 'This customer is linked to a Project Journey.', 'guidepost' ); ?>
                    </p>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => '30-60-90-project-journey', 'project_id' => $customer->project_journey_id ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                        <span class="dashicons dashicons-portfolio"></span>
                        <?php esc_html_e( 'View Project Journey', 'guidepost' ); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render appointments tab
     *
     * @param object $customer Customer object.
     */
    private function render_appointments_tab( $customer ) {
        // Pagination
        $per_page = 10;
        $appt_page = isset( $_GET['appt_page'] ) ? max( 1, intval( $_GET['appt_page'] ) ) : 1;
        $offset = ( $appt_page - 1 ) * $per_page;

        $total_appointments = GuidePost_Customer_Helpers::get_customer_appointments_count( $customer->id );
        $total_pages = ceil( $total_appointments / $per_page );

        $appointments = GuidePost_Customer_Helpers::get_customer_appointments( $customer->id, $per_page, $offset );
        ?>
        <div class="guidepost-section">
            <div class="section-header">
                <h2><?php esc_html_e( 'Appointments', 'guidepost' ); ?>
                    <?php if ( $total_appointments > 0 ) : ?>
                        <span class="count">(<?php echo esc_html( $total_appointments ); ?>)</span>
                    <?php endif; ?>
                </h2>
                <div class="section-actions">
                    <button type="button" class="button" id="export-appointments-ics">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Export ICS', 'guidepost' ); ?>
                    </button>
                </div>
            </div>

            <?php if ( empty( $appointments ) ) : ?>
                <p class="no-data"><?php esc_html_e( 'No appointments found.', 'guidepost' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date & Time', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Service', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Provider', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Meeting Link', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'guidepost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $appointments as $apt ) : ?>
                            <tr class="<?php echo strtotime( $apt->booking_date ) >= strtotime( 'today' ) ? 'upcoming' : 'past'; ?>">
                                <td>
                                    <strong><?php echo esc_html( date_i18n( 'l, F j, Y', strtotime( $apt->booking_date ) ) ); ?></strong>
                                    <br><?php echo esc_html( date_i18n( 'g:i A', strtotime( $apt->booking_time ) ) . ' - ' . date_i18n( 'g:i A', strtotime( $apt->end_time ) ) ); ?>
                                </td>
                                <td>
                                    <span class="service-color" style="background-color: <?php echo esc_attr( $apt->service_color ); ?>"></span>
                                    <?php echo esc_html( $apt->service_name ); ?>
                                </td>
                                <td><?php echo esc_html( $apt->provider_name ); ?></td>
                                <td>
                                    <?php if ( 'virtual' === $apt->appointment_mode ) : ?>
                                        <span class="appointment-mode appointment-mode-virtual">
                                            <span class="dashicons dashicons-video-alt3"></span>
                                            <?php echo esc_html( ucfirst( $apt->meeting_platform ?: 'Virtual' ) ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="appointment-mode appointment-mode-in_person">
                                            <span class="dashicons dashicons-location"></span>
                                            <?php esc_html_e( 'In-Person', 'guidepost' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $apt->meeting_link ) : ?>
                                        <a href="<?php echo esc_url( $apt->meeting_link ); ?>" class="button button-small" target="_blank">
                                            <span class="dashicons dashicons-video-alt3"></span>
                                            <?php esc_html_e( 'Join', 'guidepost' ); ?>
                                        </a>
                                        <button type="button" class="button button-small copy-link" data-link="<?php echo esc_attr( $apt->meeting_link ); ?>" title="<?php esc_attr_e( 'Copy Link', 'guidepost' ); ?>">
                                            <span class="dashicons dashicons-admin-page"></span>
                                        </button>
                                    <?php else : ?>
                                        <span class="no-data">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="guidepost-status guidepost-status-<?php echo esc_attr( str_replace( '_', '-', $apt->status ) ); ?>">
                                        <?php echo esc_html( ucfirst( str_replace( '_', ' ', $apt->status ) ) ); ?>
                                    </span>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-appointments', 'action' => 'view', 'id' => $apt->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'View Details', 'guidepost' ); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </a>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-appointments', 'action' => 'edit', 'id' => $apt->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small button-primary" title="<?php esc_attr_e( 'Edit Appointment', 'guidepost' ); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $total_pages > 1 ) : ?>
                    <div class="guidepost-pagination">
                        <?php
                        $base_url = add_query_arg( array(
                            'page'        => 'guidepost-customers',
                            'action'      => 'view',
                            'customer_id' => $customer->id,
                            'tab'         => 'appointments',
                        ), admin_url( 'admin.php' ) );

                        if ( $appt_page > 1 ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'appt_page', $appt_page - 1, $base_url ) ); ?>" class="button">&laquo; <?php esc_html_e( 'Previous', 'guidepost' ); ?></a>
                        <?php endif; ?>

                        <span class="pagination-info">
                            <?php printf( esc_html__( 'Page %1$d of %2$d', 'guidepost' ), $appt_page, $total_pages ); ?>
                        </span>

                        <?php if ( $appt_page < $total_pages ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'appt_page', $appt_page + 1, $base_url ) ); ?>" class="button"><?php esc_html_e( 'Next', 'guidepost' ); ?> &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render purchases tab
     *
     * @param object $customer Customer object.
     */
    private function render_purchases_tab( $customer ) {
        $purchases = GuidePost_Customer_Helpers::get_customer_purchases( $customer->id );
        ?>
        <div class="guidepost-section">
            <div class="section-header">
                <h2><?php esc_html_e( 'Purchases', 'guidepost' ); ?></h2>
                <div class="section-actions">
                    <button type="button" class="button" id="add-purchase-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'Add Purchase', 'guidepost' ); ?>
                    </button>
                </div>
            </div>

            <?php if ( empty( $purchases ) ) : ?>
                <p class="no-data"><?php esc_html_e( 'No purchases recorded.', 'guidepost' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Description', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Credits', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Source', 'guidepost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $purchases as $purchase ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $purchase->purchase_date ) ) ); ?></td>
                                <td><?php echo esc_html( $purchase->description ); ?></td>
                                <td>
                                    <span class="purchase-type purchase-type-<?php echo esc_attr( $purchase->purchase_type ); ?>">
                                        <?php echo esc_html( ucfirst( $purchase->purchase_type ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( '$' . number_format( $purchase->amount, 2 ) ); ?></td>
                                <td>
                                    <?php if ( $purchase->credits_granted > 0 ) : ?>
                                        <span class="credit-badge">+<?php echo esc_html( $purchase->credits_granted ); ?></span>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $purchase->wc_order_id ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $purchase->wc_order_id . '&action=edit' ) ); ?>" target="_blank">
                                            <?php printf( esc_html__( 'Order #%d', 'guidepost' ), $purchase->wc_order_id ); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php esc_html_e( 'Manual', 'guidepost' ); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong><?php esc_html_e( 'Total', 'guidepost' ); ?></strong></td>
                            <td><strong><?php echo esc_html( '$' . number_format( array_sum( wp_list_pluck( $purchases, 'amount' ) ), 2 ) ); ?></strong></td>
                            <td><strong><?php echo esc_html( array_sum( wp_list_pluck( $purchases, 'credits_granted' ) ) ); ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>

        <!-- Credit History -->
        <div class="guidepost-section">
            <h2><?php esc_html_e( 'Credit History', 'guidepost' ); ?></h2>
            <?php
            $credit_history = GuidePost_Customer_Helpers::get_credit_history( $customer->id );
            if ( empty( $credit_history ) ) :
            ?>
                <p class="no-data"><?php esc_html_e( 'No credit history.', 'guidepost' ); ?></p>
            <?php else : ?>
                <table class="widefat striped credit-history-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Change', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Reason', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Balance', 'guidepost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $credit_history as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $entry->created_at ) ) ); ?></td>
                                <td class="<?php echo $entry->delta > 0 ? 'credit-add' : 'credit-subtract'; ?>">
                                    <?php echo $entry->delta > 0 ? '+' : ''; ?><?php echo esc_html( $entry->delta ); ?>
                                </td>
                                <td><?php echo esc_html( $entry->reason ); ?></td>
                                <td><?php echo esc_html( $entry->new_balance ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render documents tab
     *
     * @param object $customer Customer object.
     */
    private function render_documents_tab( $customer ) {
        $documents = GuidePost_Customer_Helpers::get_customer_documents( $customer->id );
        ?>
        <div class="guidepost-section">
            <div class="section-header">
                <h2><?php esc_html_e( 'Documents', 'guidepost' ); ?></h2>
                <div class="section-actions">
                    <button type="button" class="button" id="upload-document-btn">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e( 'Upload Document', 'guidepost' ); ?>
                    </button>
                </div>
            </div>

            <?php if ( $customer->google_drive_url ) : ?>
                <div class="google-drive-link-box">
                    <span class="dashicons dashicons-portfolio"></span>
                    <div>
                        <strong><?php esc_html_e( 'Google Drive Folder', 'guidepost' ); ?></strong>
                        <p><a href="<?php echo esc_url( $customer->google_drive_url ); ?>" target="_blank"><?php esc_html_e( 'Open Customer Folder', 'guidepost' ); ?> <span class="dashicons dashicons-external"></span></a></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( empty( $documents ) ) : ?>
                <p class="no-data"><?php esc_html_e( 'No documents uploaded.', 'guidepost' ); ?></p>
            <?php else : ?>
                <div class="documents-grid">
                    <?php foreach ( $documents as $doc ) : ?>
                        <div class="document-card">
                            <div class="document-icon">
                                <?php echo esc_html( GuidePost_Customer_Helpers::get_file_icon( $doc->file_type ) ); ?>
                            </div>
                            <div class="document-info">
                                <a href="<?php echo esc_url( $doc->file_url ); ?>" class="document-name" target="_blank">
                                    <?php echo esc_html( $doc->filename ); ?>
                                </a>
                                <div class="document-meta">
                                    <?php echo esc_html( GuidePost_Customer_Helpers::format_file_size( $doc->file_size ) ); ?> &middot;
                                    <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $doc->created_at ) ) ); ?>
                                    <br>
                                    <?php echo 'customer' === $doc->uploaded_by ? esc_html__( 'Uploaded by customer', 'guidepost' ) : esc_html__( 'Uploaded by admin', 'guidepost' ); ?>
                                </div>
                                <?php if ( $doc->description ) : ?>
                                    <p class="document-description"><?php echo esc_html( $doc->description ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render communications tab
     *
     * @param object $customer Customer object.
     */
    private function render_communications_tab( $customer ) {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-email.php';

        // Pagination
        $per_page = 10;
        $comm_page = isset( $_GET['comm_page'] ) ? max( 1, intval( $_GET['comm_page'] ) ) : 1;
        $offset = ( $comm_page - 1 ) * $per_page;

        $total_emails = GuidePost_Email::get_email_log_count( array( 'customer_id' => $customer->id ) );
        $total_pages = ceil( $total_emails / $per_page );

        $emails = GuidePost_Email::get_email_log( array(
            'customer_id' => $customer->id,
            'limit'       => $per_page,
            'offset'      => $offset,
        ) );
        ?>
        <div class="guidepost-section">
            <div class="section-header">
                <h2><?php esc_html_e( 'Communication History', 'guidepost' ); ?>
                    <?php if ( $total_emails > 0 ) : ?>
                        <span class="count">(<?php echo esc_html( $total_emails ); ?>)</span>
                    <?php endif; ?>
                </h2>
                <div class="section-actions">
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-communications', 'tab' => 'compose', 'customer_id' => $customer->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php esc_html_e( 'Send Email', 'guidepost' ); ?>
                    </a>
                </div>
            </div>

            <?php if ( empty( $emails ) ) : ?>
                <p class="no-data"><?php esc_html_e( 'No communications sent yet.', 'guidepost' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Subject', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                            <th><?php esc_html_e( 'Sent By', 'guidepost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $emails as $email ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $email->created_at ) ) ); ?></td>
                                <td><?php echo esc_html( $email->subject ); ?></td>
                                <td>
                                    <span class="guidepost-template-type guidepost-template-type-<?php echo esc_attr( str_replace( '_', '-', $email->notification_type ) ); ?>">
                                        <?php echo esc_html( ucwords( str_replace( '_', ' ', $email->notification_type ) ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="guidepost-email-status <?php echo esc_attr( $email->status ); ?>">
                                        <?php echo esc_html( ucfirst( $email->status ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $email->sent_by_name ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $total_pages > 1 ) : ?>
                    <div class="guidepost-pagination">
                        <?php
                        $base_url = add_query_arg( array(
                            'page'        => 'guidepost-customers',
                            'action'      => 'view',
                            'customer_id' => $customer->id,
                            'tab'         => 'communications',
                        ), admin_url( 'admin.php' ) );

                        if ( $comm_page > 1 ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'comm_page', $comm_page - 1, $base_url ) ); ?>" class="button">&laquo; <?php esc_html_e( 'Previous', 'guidepost' ); ?></a>
                        <?php endif; ?>

                        <span class="pagination-info">
                            <?php printf( esc_html__( 'Page %1$d of %2$d', 'guidepost' ), $comm_page, $total_pages ); ?>
                        </span>

                        <?php if ( $comm_page < $total_pages ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'comm_page', $comm_page + 1, $base_url ) ); ?>" class="button"><?php esc_html_e( 'Next', 'guidepost' ); ?> &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render notes tab
     *
     * @param object $customer Customer object.
     */
    private function render_notes_tab( $customer ) {
        $notes = GuidePost_Customer_Helpers::get_customer_notes( $customer->id );
        ?>
        <div class="guidepost-section">
            <div class="section-header">
                <h2><?php esc_html_e( 'Notes', 'guidepost' ); ?></h2>
            </div>

            <!-- Add Note Form -->
            <div class="add-note-form">
                <textarea id="new-note-text" placeholder="<?php esc_attr_e( 'Add a note...', 'guidepost' ); ?>" rows="3"></textarea>
                <div class="note-form-footer">
                    <select id="new-note-type">
                        <option value="general"><?php esc_html_e( 'General', 'guidepost' ); ?></option>
                        <option value="session"><?php esc_html_e( 'Session', 'guidepost' ); ?></option>
                        <option value="follow_up"><?php esc_html_e( 'Follow-up', 'guidepost' ); ?></option>
                        <option value="alert"><?php esc_html_e( 'Alert', 'guidepost' ); ?></option>
                        <option value="private"><?php esc_html_e( 'Private', 'guidepost' ); ?></option>
                    </select>
                    <button type="button" class="button button-primary" id="add-note-btn" data-customer-id="<?php echo esc_attr( $customer->id ); ?>">
                        <?php esc_html_e( 'Add Note', 'guidepost' ); ?>
                    </button>
                </div>
            </div>

            <!-- Notes List -->
            <div class="guidepost-notes-list" id="notes-list">
                <?php if ( empty( $notes ) ) : ?>
                    <p class="no-data no-notes-message"><?php esc_html_e( 'No notes yet.', 'guidepost' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $notes as $note ) : ?>
                        <div class="guidepost-note-item <?php echo $note->is_pinned ? 'note-pinned' : ''; ?>" data-note-id="<?php echo esc_attr( $note->id ); ?>">
                            <div class="note-header">
                                <span class="note-type note-type-<?php echo esc_attr( $note->note_type ); ?>">
                                    <?php echo esc_html( ucfirst( str_replace( '_', ' ', $note->note_type ) ) ); ?>
                                </span>
                                <span class="note-meta">
                                    <?php echo esc_html( $note->author_name ); ?> &middot;
                                    <?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $note->created_at ) ) ); ?>
                                </span>
                                <div class="note-actions">
                                    <button type="button" class="guidepost-note-pin" data-note-id="<?php echo esc_attr( $note->id ); ?>" data-pinned="<?php echo $note->is_pinned ? '1' : '0'; ?>" title="<?php echo $note->is_pinned ? esc_attr__( 'Unpin', 'guidepost' ) : esc_attr__( 'Pin', 'guidepost' ); ?>">
                                        <span class="dashicons <?php echo $note->is_pinned ? 'dashicons-star-filled' : 'dashicons-star-empty'; ?>"></span>
                                    </button>
                                    <button type="button" class="guidepost-note-delete" data-note-id="<?php echo esc_attr( $note->id ); ?>" title="<?php esc_attr_e( 'Delete', 'guidepost' ); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="note-content"><?php echo wp_kses_post( nl2br( $note->note_text ) ); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render sidebar
     *
     * @param object $customer Customer object.
     */
    private function render_sidebar( $customer ) {
        $flags = GuidePost_Customer_Helpers::get_customer_flags( $customer->id, true );
        ?>
        <!-- Flags Section -->
        <div class="guidepost-sidebar-section">
            <div class="section-header">
                <h3><?php esc_html_e( 'Flags & Alerts', 'guidepost' ); ?></h3>
                <button type="button" class="button button-small" id="add-flag-btn" data-customer-id="<?php echo esc_attr( $customer->id ); ?>">
                    <span class="dashicons dashicons-flag"></span>
                </button>
            </div>
            <div class="flags-list" id="flags-list">
                <?php if ( empty( $flags ) ) : ?>
                    <p class="no-data"><?php esc_html_e( 'No active flags.', 'guidepost' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $flags as $flag ) : ?>
                        <div class="guidepost-flag-item flag-type-<?php echo esc_attr( $flag->flag_type ); ?>" data-flag-id="<?php echo esc_attr( $flag->id ); ?>">
                            <div class="flag-icon">
                                <span class="dashicons <?php echo esc_attr( GuidePost_Customer_Helpers::get_flag_icon( $flag->flag_type ) ); ?>"></span>
                            </div>
                            <div class="flag-content">
                                <span class="flag-message"><?php echo esc_html( $flag->message ); ?></span>
                                <?php if ( $flag->trigger_date ) : ?>
                                    <span class="flag-date"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $flag->trigger_date ) ) ); ?></span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="dismiss-flag-btn" data-flag-id="<?php echo esc_attr( $flag->id ); ?>" title="<?php esc_attr_e( 'Dismiss', 'guidepost' ); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="guidepost-sidebar-section">
            <h3><?php esc_html_e( 'Quick Actions', 'guidepost' ); ?></h3>
            <div class="quick-actions">
                <button type="button" class="button quick-action-btn" id="adjust-credits-btn" data-customer-id="<?php echo esc_attr( $customer->id ); ?>">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e( 'Adjust Credits', 'guidepost' ); ?>
                </button>
                <button type="button" class="button quick-action-btn" id="change-status-btn" data-customer-id="<?php echo esc_attr( $customer->id ); ?>" data-current-status="<?php echo esc_attr( $customer->status ); ?>">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e( 'Change Status', 'guidepost' ); ?>
                </button>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="guidepost-sidebar-section">
            <h3><?php esc_html_e( 'Details', 'guidepost' ); ?></h3>
            <dl class="customer-details-list">
                <dt><?php esc_html_e( 'Member Since', 'guidepost' ); ?></dt>
                <dd><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $customer->first_contact_date ?: $customer->created_at ) ) ); ?></dd>

                <?php if ( $customer->birthday ) : ?>
                    <dt><?php esc_html_e( 'Birthday', 'guidepost' ); ?></dt>
                    <dd><?php echo esc_html( date_i18n( 'F j', strtotime( $customer->birthday ) ) ); ?></dd>
                <?php endif; ?>

                <?php if ( $customer->source ) : ?>
                    <dt><?php esc_html_e( 'Source', 'guidepost' ); ?></dt>
                    <dd><?php echo esc_html( $customer->source ); ?></dd>
                <?php endif; ?>

                <?php if ( $customer->preferred_contact ) : ?>
                    <dt><?php esc_html_e( 'Preferred Contact', 'guidepost' ); ?></dt>
                    <dd><?php echo esc_html( ucfirst( $customer->preferred_contact ) ); ?></dd>
                <?php endif; ?>

                <?php if ( $customer->timezone ) : ?>
                    <dt><?php esc_html_e( 'Timezone', 'guidepost' ); ?></dt>
                    <dd><?php echo esc_html( $customer->timezone ); ?></dd>
                <?php endif; ?>

                <?php if ( $customer->last_booking_date ) : ?>
                    <dt><?php esc_html_e( 'Last Visit', 'guidepost' ); ?></dt>
                    <dd><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $customer->last_booking_date ) ) ); ?></dd>
                <?php endif; ?>

                <?php if ( $customer->next_booking_date ) : ?>
                    <dt><?php esc_html_e( 'Next Appointment', 'guidepost' ); ?></dt>
                    <dd><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $customer->next_booking_date ) ) ); ?></dd>
                <?php endif; ?>
            </dl>
        </div>
        <?php
    }

    /**
     * Render timeline
     *
     * @param object $customer Customer object.
     */
    private function render_timeline( $customer ) {
        $events = GuidePost_Customer_Helpers::get_timeline_events( $customer );
        ?>
        <div class="customer-timeline">
            <?php foreach ( $events as $event ) : ?>
                <div class="timeline-item timeline-<?php echo esc_attr( $event['type'] ); ?>">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <span class="timeline-date"><?php echo esc_html( $event['date'] ); ?></span>
                        <span class="timeline-label"><?php echo esc_html( $event['label'] ); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render customer form
     */
    private function render_customer_form() {
        $customer_id = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
        $customer = $customer_id ? GuidePost_Customer_Helpers::get_customer( $customer_id ) : null;
        $is_edit = ! empty( $customer );

        $this->render_admin_notices();
        ?>
        <h1 class="guidepost-admin-title">
            <span class="dashicons dashicons-admin-users"></span>
            <?php echo $is_edit ? esc_html__( 'Edit Customer', 'guidepost' ) : esc_html__( 'Add New Customer', 'guidepost' ); ?>
        </h1>

        <div class="guidepost-admin-content">
            <form method="post" class="guidepost-customer-form">
                <?php wp_nonce_field( 'guidepost_save_customer', 'guidepost_customer_nonce' ); ?>
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer->id ); ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <!-- Basic Info -->
                    <div class="form-section">
                        <h2><?php esc_html_e( 'Basic Information', 'guidepost' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="first_name"><?php esc_html_e( 'First Name', 'guidepost' ); ?> *</label></th>
                                <td><input type="text" name="first_name" id="first_name" class="regular-text" required value="<?php echo esc_attr( $is_edit ? $customer->first_name : '' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="last_name"><?php esc_html_e( 'Last Name', 'guidepost' ); ?> *</label></th>
                                <td><input type="text" name="last_name" id="last_name" class="regular-text" required value="<?php echo esc_attr( $is_edit ? $customer->last_name : '' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="email"><?php esc_html_e( 'Email', 'guidepost' ); ?> *</label></th>
                                <td><input type="email" name="email" id="email" class="regular-text" required value="<?php echo esc_attr( $is_edit ? $customer->email : '' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="phone"><?php esc_html_e( 'Phone', 'guidepost' ); ?></label></th>
                                <td><input type="tel" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr( $is_edit ? $customer->phone : '' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="status"><?php esc_html_e( 'Status', 'guidepost' ); ?></label></th>
                                <td>
                                    <select name="status" id="status">
                                        <option value="active" <?php selected( $is_edit ? $customer->status : '', 'active' ); ?>><?php esc_html_e( 'Active', 'guidepost' ); ?></option>
                                        <option value="vip" <?php selected( $is_edit ? $customer->status : '', 'vip' ); ?>><?php esc_html_e( 'VIP', 'guidepost' ); ?></option>
                                        <option value="paused" <?php selected( $is_edit ? $customer->status : '', 'paused' ); ?>><?php esc_html_e( 'Paused', 'guidepost' ); ?></option>
                                        <option value="inactive" <?php selected( $is_edit ? $customer->status : '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'guidepost' ); ?></option>
                                        <option value="prospect" <?php selected( $is_edit ? $customer->status : '', 'prospect' ); ?>><?php esc_html_e( 'Prospect', 'guidepost' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Additional Info -->
                    <div class="form-section">
                        <h2><?php esc_html_e( 'Additional Information', 'guidepost' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="company"><?php esc_html_e( 'Company', 'guidepost' ); ?></label></th>
                                <td><input type="text" name="company" id="company" class="regular-text" value="<?php echo esc_attr( $is_edit ? $customer->company : '' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="job_title"><?php esc_html_e( 'Job Title', 'guidepost' ); ?></label></th>
                                <td><input type="text" name="job_title" id="job_title" class="regular-text" value="<?php echo esc_attr( $is_edit ? $customer->job_title : '' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="birthday"><?php esc_html_e( 'Birthday', 'guidepost' ); ?></label></th>
                                <td><input type="date" name="birthday" id="birthday" value="<?php echo esc_attr( $is_edit ? $customer->birthday : '' ); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="source"><?php esc_html_e( 'Source', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="text" name="source" id="source" class="regular-text" value="<?php echo esc_attr( $is_edit ? $customer->source : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., Referral, Website, Social Media', 'guidepost' ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="preferred_contact"><?php esc_html_e( 'Preferred Contact', 'guidepost' ); ?></label></th>
                                <td>
                                    <select name="preferred_contact" id="preferred_contact">
                                        <option value="email" <?php selected( $is_edit ? $customer->preferred_contact : '', 'email' ); ?>><?php esc_html_e( 'Email', 'guidepost' ); ?></option>
                                        <option value="phone" <?php selected( $is_edit ? $customer->preferred_contact : '', 'phone' ); ?>><?php esc_html_e( 'Phone', 'guidepost' ); ?></option>
                                        <option value="sms" <?php selected( $is_edit ? $customer->preferred_contact : '', 'sms' ); ?>><?php esc_html_e( 'SMS', 'guidepost' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="tags"><?php esc_html_e( 'Tags', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="text" name="tags" id="tags" class="regular-text" value="<?php echo esc_attr( $is_edit ? $customer->tags : '' ); ?>" placeholder="<?php esc_attr_e( 'Comma-separated tags', 'guidepost' ); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- External Links -->
                    <div class="form-section">
                        <h2><?php esc_html_e( 'External Links', 'guidepost' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="google_drive_url"><?php esc_html_e( 'Google Drive Folder', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="url" name="google_drive_url" id="google_drive_url" class="large-text" value="<?php echo esc_attr( $is_edit ? $customer->google_drive_url : '' ); ?>" placeholder="https://drive.google.com/drive/folders/...">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- 30-60-90 Integration -->
                    <div class="form-section">
                        <h2><?php esc_html_e( '30-60-90 Project Journey', 'guidepost' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="project_journey_id"><?php esc_html_e( 'Project ID', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="number" name="project_journey_id" id="project_journey_id" class="small-text" value="<?php echo esc_attr( $is_edit ? $customer->project_journey_id : '' ); ?>">
                                    <p class="description"><?php esc_html_e( 'Link to a 30-60-90 Project Journey for this customer.', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="project_journey_user_id"><?php esc_html_e( 'Journey User ID', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="number" name="project_journey_user_id" id="project_journey_user_id" class="small-text" value="<?php echo esc_attr( $is_edit ? $customer->project_journey_user_id : '' ); ?>">
                                    <p class="description"><?php esc_html_e( 'WordPress user ID for progress tracking.', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Notes -->
                    <div class="form-section form-section-full">
                        <h2><?php esc_html_e( 'Notes', 'guidepost' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <td colspan="2">
                                    <textarea name="notes" id="notes" rows="4" class="large-text"><?php echo esc_textarea( $is_edit ? $customer->notes : '' ); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit">
                    <?php submit_button( $is_edit ? __( 'Update Customer', 'guidepost' ) : __( 'Add Customer', 'guidepost' ), 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-customers' ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                        <?php esc_html_e( 'Cancel', 'guidepost' ); ?>
                    </a>
                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'guidepost-customers', 'action' => 'delete', 'customer_id' => $customer->id ), admin_url( 'admin.php' ) ), 'delete_customer_' . $customer->id ) ); ?>" class="button guidepost-delete-btn" style="color: #dc3545;">
                            <?php esc_html_e( 'Delete Customer', 'guidepost' ); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render admin notices
     */
    private function render_admin_notices() {
        $messages = array(
            'customer_created' => __( 'Customer created successfully.', 'guidepost' ),
            'customer_updated' => __( 'Customer updated successfully.', 'guidepost' ),
            'customer_deleted' => __( 'Customer deleted successfully.', 'guidepost' ),
        );

        if ( isset( $_GET['message'] ) && isset( $messages[ $_GET['message'] ] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $_GET['message'] ] ) . '</p></div>';
        }
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    /**
     * AJAX: Add note
     */
    public function ajax_add_note() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $customer_id = absint( $_POST['customer_id'] );
        $note_text   = sanitize_textarea_field( $_POST['note_text'] );
        $note_type   = sanitize_text_field( $_POST['note_type'] );

        if ( empty( $note_text ) ) {
            wp_send_json_error( array( 'message' => 'Note text is required' ) );
        }

        $wpdb->insert(
            $tables['customer_notes'],
            array(
                'customer_id' => $customer_id,
                'user_id'     => get_current_user_id(),
                'note_text'   => $note_text,
                'note_type'   => $note_type,
            ),
            array( '%d', '%d', '%s', '%s' )
        );

        $note_id = $wpdb->insert_id;
        $user = wp_get_current_user();

        wp_send_json_success( array(
            'message'     => 'Note added',
            'note_id'     => $note_id,
            'author_name' => $user->display_name,
            'created_at'  => current_time( 'mysql' ),
        ) );
    }

    /**
     * AJAX: Delete note
     */
    public function ajax_delete_note() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $note_id = absint( $_POST['note_id'] );
        $wpdb->delete( $tables['customer_notes'], array( 'id' => $note_id ) );

        wp_send_json_success( array( 'message' => 'Note deleted' ) );
    }

    /**
     * AJAX: Toggle note pin
     */
    public function ajax_toggle_note_pin() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $note_id = absint( $_POST['note_id'] );
        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_pinned FROM {$tables['customer_notes']} WHERE id = %d",
            $note_id
        ) );

        $wpdb->update(
            $tables['customer_notes'],
            array( 'is_pinned' => $current ? 0 : 1 ),
            array( 'id' => $note_id )
        );

        wp_send_json_success( array( 'is_pinned' => ! $current ) );
    }

    /**
     * AJAX: Add flag
     */
    public function ajax_add_flag() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $customer_id  = absint( $_POST['customer_id'] );
        $flag_type    = sanitize_text_field( $_POST['flag_type'] );
        $message      = sanitize_text_field( $_POST['message'] );
        $trigger_date = ! empty( $_POST['trigger_date'] ) ? sanitize_text_field( $_POST['trigger_date'] ) : null;

        $wpdb->insert(
            $tables['customer_flags'],
            array(
                'customer_id'  => $customer_id,
                'flag_type'    => $flag_type,
                'message'      => $message,
                'trigger_date' => $trigger_date,
            ),
            array( '%d', '%s', '%s', '%s' )
        );

        wp_send_json_success( array(
            'message' => 'Flag added',
            'flag_id' => $wpdb->insert_id,
        ) );
    }

    /**
     * AJAX: Dismiss flag
     */
    public function ajax_dismiss_flag() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $flag_id = absint( $_POST['flag_id'] );

        $wpdb->update(
            $tables['customer_flags'],
            array(
                'is_dismissed' => 1,
                'dismissed_by' => get_current_user_id(),
                'dismissed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $flag_id )
        );

        wp_send_json_success( array( 'message' => 'Flag dismissed' ) );
    }

    /**
     * AJAX: Adjust credits
     */
    public function ajax_adjust_credits() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $customer_id = absint( $_POST['customer_id'] );
        $delta       = intval( $_POST['delta'] );
        $reason      = sanitize_text_field( $_POST['reason'] );

        // Get current balance
        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT total_credits FROM {$tables['customers']} WHERE id = %d",
            $customer_id
        ) );

        $new_balance = max( 0, $current + $delta );

        // Update customer
        $wpdb->update(
            $tables['customers'],
            array( 'total_credits' => $new_balance ),
            array( 'id' => $customer_id )
        );

        // Log history
        $wpdb->insert(
            $tables['credit_history'],
            array(
                'customer_id' => $customer_id,
                'delta'       => $delta,
                'reason'      => $reason,
                'old_balance' => $current,
                'new_balance' => $new_balance,
                'reference_type' => 'manual',
                'created_by'  => get_current_user_id(),
            ),
            array( '%d', '%d', '%s', '%d', '%d', '%s', '%d' )
        );

        wp_send_json_success( array(
            'message'     => 'Credits adjusted',
            'new_balance' => $new_balance,
        ) );
    }

    /**
     * AJAX: Update status
     */
    public function ajax_update_status() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $customer_id = absint( $_POST['customer_id'] );
        $status      = sanitize_text_field( $_POST['status'] );

        $wpdb->update(
            $tables['customers'],
            array( 'status' => $status ),
            array( 'id' => $customer_id )
        );

        wp_send_json_success( array( 'message' => 'Status updated' ) );
    }

    /**
     * AJAX: Save field
     */
    public function ajax_save_field() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $customer_id = absint( $_POST['customer_id'] );
        $field       = sanitize_text_field( $_POST['field'] );
        $value       = sanitize_text_field( $_POST['value'] );

        // Whitelist of editable fields
        $allowed_fields = array( 'google_drive_url', 'tags', 'notes', 'status' );
        if ( ! in_array( $field, $allowed_fields, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid field' ) );
        }

        $wpdb->update(
            $tables['customers'],
            array( $field => $value ),
            array( 'id' => $customer_id )
        );

        wp_send_json_success( array( 'message' => 'Field updated' ) );
    }

    /**
     * AJAX: Get active flags count
     */
    public function ajax_get_flags_count() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        wp_send_json_success( array( 'count' => GuidePost_Customer_Helpers::get_active_flags_count() ) );
    }

    /**
     * AJAX: Export appointment as ICS file
     */
    public function ajax_export_ics() {
        // Verify nonce - allow GET request for downloads
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'guidepost_admin_nonce' ) ) {
            wp_die( 'Security check failed' );
        }

        if ( ! isset( $_GET['appointment_id'] ) ) {
            wp_die( 'Appointment ID required' );
        }

        $appointment_id = absint( $_GET['appointment_id'] );

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        // Get appointment with all related data
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*,
                    s.name AS service_name, s.description AS service_description, s.duration,
                    p.name AS provider_name, p.email AS provider_email,
                    c.first_name, c.last_name, c.email AS customer_email, c.phone AS customer_phone
             FROM {$tables['appointments']} a
             LEFT JOIN {$tables['services']} s ON a.service_id = s.id
             LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
             LEFT JOIN {$tables['customers']} c ON a.customer_id = c.id
             WHERE a.id = %d",
            $appointment_id
        ) );

        if ( ! $appointment ) {
            wp_die( 'Appointment not found' );
        }

        // Generate ICS content
        $ics = $this->generate_ics( $appointment );

        // Set headers for file download
        $filename = sanitize_file_name( sprintf(
            'appointment-%s-%s.ics',
            $appointment->first_name . '-' . $appointment->last_name,
            $appointment->booking_date
        ) );

        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $ics ) );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );

        echo $ics;
        exit;
    }

    /**
     * Generate ICS content for appointment
     *
     * @param object $appointment Appointment data with related info.
     * @return string ICS file content.
     */
    private function generate_ics( $appointment ) {
        // Date/time formatting for ICS
        $start_datetime = $appointment->booking_date . ' ' . $appointment->start_time;
        $end_datetime = $appointment->booking_date . ' ' . $appointment->end_time;

        $dtstart = gmdate( 'Ymd\THis\Z', strtotime( $start_datetime ) );
        $dtend = gmdate( 'Ymd\THis\Z', strtotime( $end_datetime ) );
        $dtstamp = gmdate( 'Ymd\THis\Z' );
        $created = gmdate( 'Ymd\THis\Z', strtotime( $appointment->created_at ) );

        // Generate unique ID
        $uid = sprintf( 'guidepost-%d-%s@%s', $appointment->id, md5( $start_datetime ), wp_parse_url( home_url(), PHP_URL_HOST ) );

        // Build summary
        $summary = sprintf(
            '%s - %s with %s',
            $appointment->service_name,
            $appointment->first_name . ' ' . $appointment->last_name,
            $appointment->provider_name
        );

        // Build rich description
        $description_parts = array();
        $description_parts[] = 'Service: ' . $appointment->service_name;
        $description_parts[] = 'Duration: ' . $appointment->duration . ' minutes';
        $description_parts[] = '';
        $description_parts[] = 'Customer: ' . $appointment->first_name . ' ' . $appointment->last_name;
        $description_parts[] = 'Email: ' . $appointment->customer_email;
        if ( $appointment->customer_phone ) {
            $description_parts[] = 'Phone: ' . $appointment->customer_phone;
        }
        $description_parts[] = '';
        $description_parts[] = 'Provider: ' . $appointment->provider_name;
        if ( $appointment->provider_email ) {
            $description_parts[] = 'Provider Email: ' . $appointment->provider_email;
        }

        // Add meeting link if virtual
        if ( ! empty( $appointment->meeting_link ) ) {
            $description_parts[] = '';
            $description_parts[] = '--- Meeting Information ---';
            $platform_names = array(
                'google_meet' => 'Google Meet',
                'zoom'        => 'Zoom',
                'teams'       => 'Microsoft Teams',
            );
            $platform = isset( $platform_names[ $appointment->meeting_platform ] ) ? $platform_names[ $appointment->meeting_platform ] : 'Virtual Meeting';
            $description_parts[] = 'Platform: ' . $platform;
            $description_parts[] = 'Join Link: ' . $appointment->meeting_link;
            if ( ! empty( $appointment->meeting_password ) ) {
                $description_parts[] = 'Password: ' . $appointment->meeting_password;
            }
        }

        // Add appointment mode
        if ( ! empty( $appointment->appointment_mode ) ) {
            $mode_labels = array(
                'virtual'   => 'Virtual (Online)',
                'in_person' => 'In-Person',
            );
            $description_parts[] = '';
            $description_parts[] = 'Appointment Type: ' . ( isset( $mode_labels[ $appointment->appointment_mode ] ) ? $mode_labels[ $appointment->appointment_mode ] : $appointment->appointment_mode );
        }

        // Add notes if present
        if ( ! empty( $appointment->notes ) ) {
            $description_parts[] = '';
            $description_parts[] = '--- Notes ---';
            $description_parts[] = $appointment->notes;
        }

        // Add service description
        if ( ! empty( $appointment->service_description ) ) {
            $description_parts[] = '';
            $description_parts[] = '--- Service Description ---';
            $description_parts[] = $appointment->service_description;
        }

        $description = implode( '\n', $description_parts );

        // Location - meeting link for virtual, or business address
        $location = '';
        if ( ! empty( $appointment->meeting_link ) && $appointment->appointment_mode === 'virtual' ) {
            $location = $appointment->meeting_link;
        } else {
            // Get business address from settings
            $business_address = get_option( 'guidepost_business_address', '' );
            if ( $business_address ) {
                $location = $business_address;
            }
        }

        // Build ICS content
        $ics_lines = array();
        $ics_lines[] = 'BEGIN:VCALENDAR';
        $ics_lines[] = 'VERSION:2.0';
        $ics_lines[] = 'PRODID:-//GuidePost//Booking System//EN';
        $ics_lines[] = 'CALSCALE:GREGORIAN';
        $ics_lines[] = 'METHOD:PUBLISH';
        $ics_lines[] = 'X-WR-CALNAME:' . $this->ics_escape( get_bloginfo( 'name' ) . ' Appointments' );
        $ics_lines[] = 'BEGIN:VEVENT';
        $ics_lines[] = 'UID:' . $uid;
        $ics_lines[] = 'DTSTAMP:' . $dtstamp;
        $ics_lines[] = 'DTSTART:' . $dtstart;
        $ics_lines[] = 'DTEND:' . $dtend;
        $ics_lines[] = 'CREATED:' . $created;
        $ics_lines[] = 'SUMMARY:' . $this->ics_escape( $summary );
        $ics_lines[] = 'DESCRIPTION:' . $this->ics_escape( $description );
        if ( $location ) {
            $ics_lines[] = 'LOCATION:' . $this->ics_escape( $location );
        }
        if ( ! empty( $appointment->meeting_link ) ) {
            $ics_lines[] = 'URL:' . $appointment->meeting_link;
        }
        $ics_lines[] = 'STATUS:' . ( $appointment->status === 'canceled' ? 'CANCELLED' : 'CONFIRMED' );
        $ics_lines[] = 'ORGANIZER;CN=' . $this->ics_escape( $appointment->provider_name ) . ':mailto:' . ( $appointment->provider_email ?: get_option( 'admin_email' ) );
        $ics_lines[] = 'ATTENDEE;CN=' . $this->ics_escape( $appointment->first_name . ' ' . $appointment->last_name ) . ';RSVP=TRUE:mailto:' . $appointment->customer_email;

        // Add alarm/reminder 1 hour before
        $ics_lines[] = 'BEGIN:VALARM';
        $ics_lines[] = 'ACTION:DISPLAY';
        $ics_lines[] = 'DESCRIPTION:Reminder: ' . $this->ics_escape( $summary );
        $ics_lines[] = 'TRIGGER:-PT1H';
        $ics_lines[] = 'END:VALARM';

        // Add alarm/reminder 1 day before
        $ics_lines[] = 'BEGIN:VALARM';
        $ics_lines[] = 'ACTION:DISPLAY';
        $ics_lines[] = 'DESCRIPTION:Tomorrow: ' . $this->ics_escape( $summary );
        $ics_lines[] = 'TRIGGER:-P1D';
        $ics_lines[] = 'END:VALARM';

        $ics_lines[] = 'END:VEVENT';
        $ics_lines[] = 'END:VCALENDAR';

        return implode( "\r\n", $ics_lines );
    }

    /**
     * Escape text for ICS format
     *
     * @param string $text Text to escape.
     * @return string Escaped text.
     */
    private function ics_escape( $text ) {
        $text = str_replace( '\\', '\\\\', $text );
        $text = str_replace( ',', '\,', $text );
        $text = str_replace( ';', '\;', $text );
        $text = str_replace( "\n", '\n', $text );
        return $text;
    }
}
