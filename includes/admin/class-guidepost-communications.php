<?php
/**
 * Communications Admin functionality
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Communications class - handles email communications admin
 */
class GuidePost_Communications {

    /**
     * Single instance
     *
     * @var GuidePost_Communications
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return GuidePost_Communications
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
        add_action( 'admin_menu', array( $this, 'add_communications_menu' ), 20 );
        add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
        add_action( 'wp_ajax_guidepost_get_customer_details', array( $this, 'ajax_get_customer_details' ) );
        add_action( 'wp_ajax_guidepost_preview_email', array( $this, 'ajax_preview_email' ) );
        add_action( 'wp_ajax_guidepost_send_test_email', array( $this, 'ajax_send_test_email' ) );
    }

    /**
     * Add communications menu
     */
    public function add_communications_menu() {
        // Main Communications menu item
        add_submenu_page(
            'guidepost',
            __( 'Communications', 'guidepost' ),
            __( 'Communications', 'guidepost' ),
            'manage_options',
            'guidepost-communications',
            array( $this, 'render_communications_page' )
        );
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        // Handle send email form
        if ( isset( $_POST['guidepost_send_email_nonce'] ) && wp_verify_nonce( $_POST['guidepost_send_email_nonce'], 'guidepost_send_email' ) ) {
            $this->handle_send_email();
        }

        // Handle template save
        if ( isset( $_POST['guidepost_template_nonce'] ) && wp_verify_nonce( $_POST['guidepost_template_nonce'], 'guidepost_save_template' ) ) {
            $this->handle_save_template();
        }

        // Handle SMTP settings save
        if ( isset( $_POST['guidepost_smtp_nonce'] ) && wp_verify_nonce( $_POST['guidepost_smtp_nonce'], 'guidepost_save_smtp' ) ) {
            $this->handle_save_smtp_settings();
        }
    }

    /**
     * Handle send email form
     */
    private function handle_send_email() {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-email.php';

        $customer_id    = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
        $template_id    = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        $custom_message = isset( $_POST['custom_message'] ) ? sanitize_textarea_field( $_POST['custom_message'] ) : '';
        $recipient_email = isset( $_POST['recipient_email'] ) ? sanitize_email( $_POST['recipient_email'] ) : '';
        $recipient_name  = isset( $_POST['recipient_name'] ) ? sanitize_text_field( $_POST['recipient_name'] ) : '';

        // Get customer details if customer_id provided
        if ( $customer_id && ! $recipient_email ) {
            global $wpdb;
            $tables = GuidePost_Database::get_table_names();
            $customer = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$tables['customers']} WHERE id = %d",
                $customer_id
            ) );
            if ( $customer ) {
                $recipient_email = $customer->email;
                $recipient_name  = $customer->first_name . ' ' . $customer->last_name;
            }
        }

        if ( empty( $recipient_email ) ) {
            wp_redirect( add_query_arg( array(
                'page'  => 'guidepost-communications',
                'tab'   => 'compose',
                'error' => urlencode( __( 'Recipient email is required.', 'guidepost' ) ),
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        // Get template
        $template = GuidePost_Email::get_template( $template_id );
        if ( ! $template ) {
            wp_redirect( add_query_arg( array(
                'page'  => 'guidepost-communications',
                'tab'   => 'compose',
                'error' => urlencode( __( 'Please select a valid template.', 'guidepost' ) ),
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        // Build variables
        $variables = GuidePost_Email::get_default_variables();
        if ( $customer_id ) {
            $variables = array_merge( $variables, GuidePost_Email::get_customer_variables( $customer_id ) );
        } else {
            // Use manual recipient info
            $variables['customer_name']       = $recipient_name;
            $variables['customer_first_name'] = explode( ' ', $recipient_name )[0];
            $variables['customer_email']      = $recipient_email;
        }

        // Send email
        $result = GuidePost_Email::send( array(
            'to'              => $recipient_email,
            'to_name'         => $recipient_name,
            'subject'         => $template->subject,
            'body'            => $template->body,
            'custom_message'  => $custom_message,
            'template_id'     => $template_id,
            'notification_type' => $template->notification_type,
            'customer_id'     => $customer_id ?: null,
            'variables'       => $variables,
        ) );

        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( array(
                'page'  => 'guidepost-communications',
                'tab'   => 'compose',
                'error' => urlencode( $result->get_error_message() ),
            ), admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( array(
                'page'    => 'guidepost-communications',
                'tab'     => 'log',
                'message' => 'sent',
            ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * Handle save template
     */
    private function handle_save_template() {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        $data = array(
            'name'              => sanitize_text_field( $_POST['name'] ),
            'slug'              => sanitize_title( $_POST['slug'] ),
            'notification_type' => sanitize_text_field( $_POST['notification_type'] ),
            'subject'           => sanitize_text_field( $_POST['subject'] ),
            'body'              => wp_kses_post( $_POST['body'] ),
            'is_active'         => isset( $_POST['is_active'] ) ? 1 : 0,
        );

        if ( $template_id ) {
            $wpdb->update( $tables['email_templates'], $data, array( 'id' => $template_id ) );
            $message = 'template_updated';
        } else {
            $wpdb->insert( $tables['email_templates'], $data );
            $message = 'template_created';
        }

        wp_redirect( add_query_arg( array(
            'page'    => 'guidepost-communications',
            'tab'     => 'templates',
            'message' => $message,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle save SMTP settings
     */
    private function handle_save_smtp_settings() {
        update_option( 'guidepost_smtp_enabled', isset( $_POST['smtp_enabled'] ) ? 1 : 0 );
        update_option( 'guidepost_smtp_host', sanitize_text_field( $_POST['smtp_host'] ) );
        update_option( 'guidepost_smtp_port', absint( $_POST['smtp_port'] ) );
        update_option( 'guidepost_smtp_encryption', sanitize_text_field( $_POST['smtp_encryption'] ) );
        update_option( 'guidepost_smtp_auth', isset( $_POST['smtp_auth'] ) ? 1 : 0 );
        update_option( 'guidepost_smtp_username', sanitize_text_field( $_POST['smtp_username'] ) );

        // Only update password if provided
        if ( ! empty( $_POST['smtp_password'] ) ) {
            update_option( 'guidepost_smtp_password', $_POST['smtp_password'] );
        }

        update_option( 'guidepost_smtp_from_email', sanitize_email( $_POST['smtp_from_email'] ) );
        update_option( 'guidepost_smtp_from_name', sanitize_text_field( $_POST['smtp_from_name'] ) );
        update_option( 'guidepost_email_logo', esc_url_raw( $_POST['email_logo'] ) );

        // Clear cached settings by resetting the option
        GuidePost_Email::clear_settings_cache();

        wp_redirect( add_query_arg( array(
            'page'    => 'guidepost-communications',
            'tab'     => 'settings',
            'message' => 'settings_saved',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * AJAX: Get customer details
     */
    public function ajax_get_customer_details() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;

        if ( ! $customer_id ) {
            wp_send_json_error( array( 'message' => 'Invalid customer ID' ) );
        }

        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['customers']} WHERE id = %d",
            $customer_id
        ) );

        if ( ! $customer ) {
            wp_send_json_error( array( 'message' => 'Customer not found' ) );
        }

        // Get customer's appointment history
        $appointments = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*, s.name AS service_name
             FROM {$tables['appointments']} a
             LEFT JOIN {$tables['services']} s ON a.service_id = s.id
             WHERE a.customer_id = %d
             ORDER BY a.booking_date DESC
             LIMIT 5",
            $customer_id
        ) );

        // Get communication history
        $communications = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tables['notifications']}
             WHERE customer_id = %d
             ORDER BY created_at DESC
             LIMIT 5",
            $customer_id
        ) );

        wp_send_json_success( array(
            'customer'       => $customer,
            'appointments'   => $appointments,
            'communications' => $communications,
        ) );
    }

    /**
     * AJAX: Preview email
     */
    public function ajax_preview_email() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-email.php';

        $template_id    = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        $custom_message = isset( $_POST['custom_message'] ) ? sanitize_textarea_field( $_POST['custom_message'] ) : '';
        $customer_id    = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;

        $template = GuidePost_Email::get_template( $template_id );
        if ( ! $template ) {
            wp_send_json_error( array( 'message' => 'Template not found' ) );
        }

        // Build variables
        $variables = GuidePost_Email::get_default_variables();
        if ( $customer_id ) {
            $variables = array_merge( $variables, GuidePost_Email::get_customer_variables( $customer_id ) );
        } else {
            // Sample data
            $variables['customer_first_name'] = 'John';
            $variables['customer_last_name']  = 'Doe';
            $variables['customer_name']       = 'John Doe';
            $variables['customer_email']      = 'john@example.com';
            $variables['customer_phone']      = '(555) 123-4567';
        }

        // Sample appointment data
        $variables['appointment_date']    = date_i18n( get_option( 'date_format' ), strtotime( '+3 days' ) );
        $variables['appointment_time']    = '10:00 AM';
        $variables['appointment_end_time'] = '11:00 AM';
        $variables['service_name']        = 'Sample Service';
        $variables['service_duration']    = '60';
        $variables['service_price']       = '$100.00';
        $variables['provider_name']       = 'Jane Smith';

        // Custom message
        if ( $custom_message ) {
            $variables['custom_message'] = '<div style="background: #e8f4f8; border-left: 4px solid #c16107; padding: 15px 20px; margin: 25px 0; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; color: #333; font-size: 16px; line-height: 1.6; font-style: italic;">' . nl2br( esc_html( $custom_message ) ) . '</p>
            </div>';
        } else {
            $variables['custom_message'] = '';
        }

        $subject = GuidePost_Email::replace_variables( $template->subject, $variables );
        $body    = GuidePost_Email::replace_variables( $template->body, $variables );
        $html    = GuidePost_Email::wrap_in_template( $body, $subject );

        wp_send_json_success( array(
            'subject' => $subject,
            'html'    => $html,
        ) );
    }

    /**
     * AJAX: Send test email
     */
    public function ajax_send_test_email() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-email.php';

        $result = GuidePost_Email::test_smtp_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Test email sent successfully!', 'guidepost' ) ) );
    }

    /**
     * Render communications page
     */
    public function render_communications_page() {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-email.php';

        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'compose';

        $this->render_admin_header( __( 'Communications', 'guidepost' ) );
        $this->render_tabs( $current_tab );
        $this->render_admin_notices();

        echo '<div class="guidepost-admin-content guidepost-communications-content">';

        switch ( $current_tab ) {
            case 'compose':
                $this->render_compose_tab();
                break;
            case 'templates':
                $this->render_templates_tab();
                break;
            case 'log':
                $this->render_log_tab();
                break;
            case 'settings':
                $this->render_settings_tab();
                break;
            default:
                $this->render_compose_tab();
        }

        echo '</div>';
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
                <span class="dashicons dashicons-email-alt"></span>
                <?php echo esc_html( $title ); ?>
            </h1>
        <?php
    }

    /**
     * Render tabs
     *
     * @param string $current_tab Current tab.
     */
    private function render_tabs( $current_tab ) {
        $tabs = array(
            'compose'   => array( 'icon' => 'dashicons-edit', 'label' => __( 'Compose', 'guidepost' ) ),
            'templates' => array( 'icon' => 'dashicons-media-document', 'label' => __( 'Templates', 'guidepost' ) ),
            'log'       => array( 'icon' => 'dashicons-list-view', 'label' => __( 'Email Log', 'guidepost' ) ),
            'settings'  => array( 'icon' => 'dashicons-admin-settings', 'label' => __( 'SMTP Settings', 'guidepost' ) ),
        );
        ?>
        <nav class="guidepost-tabs">
            <?php foreach ( $tabs as $tab_key => $tab ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-communications', 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>"
                   class="guidepost-tab <?php echo $current_tab === $tab_key ? 'guidepost-tab-active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                    <?php echo esc_html( $tab['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Render admin notices
     */
    private function render_admin_notices() {
        $messages = array(
            'sent'             => __( 'Email sent successfully!', 'guidepost' ),
            'template_created' => __( 'Template created successfully.', 'guidepost' ),
            'template_updated' => __( 'Template updated successfully.', 'guidepost' ),
            'template_deleted' => __( 'Template deleted successfully.', 'guidepost' ),
            'settings_saved'   => __( 'Settings saved successfully.', 'guidepost' ),
        );

        if ( isset( $_GET['message'] ) && isset( $messages[ $_GET['message'] ] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $_GET['message'] ] ) . '</p></div>';
        }

        if ( isset( $_GET['error'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( urldecode( $_GET['error'] ) ) . '</p></div>';
        }
    }

    /**
     * Render compose tab
     */
    private function render_compose_tab() {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        // Get customers
        $customers = $wpdb->get_results(
            "SELECT id, first_name, last_name, email FROM {$tables['customers']} ORDER BY first_name, last_name"
        );

        // Get active templates
        $templates = GuidePost_Email::get_templates( array( 'is_active' => true ) );

        ?>
        <div class="guidepost-compose-container">
            <div class="guidepost-compose-form">
                <h2><?php esc_html_e( 'Send Email', 'guidepost' ); ?></h2>

                <form method="post" id="guidepost-compose-form">
                    <?php wp_nonce_field( 'guidepost_send_email', 'guidepost_send_email_nonce' ); ?>

                    <!-- Recipient Section -->
                    <div class="guidepost-form-section">
                        <h3><?php esc_html_e( 'Recipient', 'guidepost' ); ?></h3>

                        <div class="guidepost-form-row">
                            <label for="recipient_type"><?php esc_html_e( 'Send To', 'guidepost' ); ?></label>
                            <select id="recipient_type" name="recipient_type" class="guidepost-select">
                                <option value="customer"><?php esc_html_e( 'Select Customer', 'guidepost' ); ?></option>
                                <option value="manual"><?php esc_html_e( 'Enter Email Manually', 'guidepost' ); ?></option>
                            </select>
                        </div>

                        <div id="customer-select-row" class="guidepost-form-row">
                            <label for="customer_id"><?php esc_html_e( 'Customer', 'guidepost' ); ?></label>
                            <select id="customer_id" name="customer_id" class="guidepost-select guidepost-select-searchable">
                                <option value=""><?php esc_html_e( '-- Select Customer --', 'guidepost' ); ?></option>
                                <?php foreach ( $customers as $customer ) : ?>
                                    <option value="<?php echo esc_attr( $customer->id ); ?>" data-email="<?php echo esc_attr( $customer->email ); ?>">
                                        <?php echo esc_html( $customer->first_name . ' ' . $customer->last_name . ' (' . $customer->email . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="manual-email-row" class="guidepost-form-row" style="display: none;">
                            <label for="recipient_email"><?php esc_html_e( 'Email Address', 'guidepost' ); ?></label>
                            <input type="email" id="recipient_email" name="recipient_email" class="guidepost-input">
                        </div>

                        <div id="manual-name-row" class="guidepost-form-row" style="display: none;">
                            <label for="recipient_name"><?php esc_html_e( 'Recipient Name', 'guidepost' ); ?></label>
                            <input type="text" id="recipient_name" name="recipient_name" class="guidepost-input" placeholder="<?php esc_attr_e( 'First Last', 'guidepost' ); ?>">
                        </div>
                    </div>

                    <!-- Template Section -->
                    <div class="guidepost-form-section">
                        <h3><?php esc_html_e( 'Email Template', 'guidepost' ); ?></h3>

                        <div class="guidepost-form-row">
                            <label for="template_id"><?php esc_html_e( 'Select Template', 'guidepost' ); ?> *</label>
                            <select id="template_id" name="template_id" class="guidepost-select" required>
                                <option value=""><?php esc_html_e( '-- Select Template --', 'guidepost' ); ?></option>
                                <?php
                                $grouped_templates = array();
                                foreach ( $templates as $template ) {
                                    $grouped_templates[ $template->notification_type ][] = $template;
                                }
                                foreach ( $grouped_templates as $type => $type_templates ) :
                                    $type_label = ucwords( str_replace( '_', ' ', $type ) );
                                ?>
                                    <optgroup label="<?php echo esc_attr( $type_label ); ?>">
                                        <?php foreach ( $type_templates as $template ) : ?>
                                            <option value="<?php echo esc_attr( $template->id ); ?>">
                                                <?php echo esc_html( $template->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Custom Message Section -->
                    <div class="guidepost-form-section">
                        <h3><?php esc_html_e( 'Personal Message', 'guidepost' ); ?></h3>
                        <p class="description"><?php esc_html_e( 'Add a personalized message that will be inserted into the template.', 'guidepost' ); ?></p>

                        <div class="guidepost-form-row">
                            <textarea id="custom_message" name="custom_message" rows="4" class="guidepost-textarea" placeholder="<?php esc_attr_e( 'Type your personalized message here...', 'guidepost' ); ?>"></textarea>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="guidepost-form-actions">
                        <button type="button" id="preview-email-btn" class="button button-secondary">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e( 'Preview', 'guidepost' ); ?>
                        </button>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-email"></span>
                            <?php esc_html_e( 'Send Email', 'guidepost' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Customer Info Sidebar -->
            <div class="guidepost-compose-sidebar">
                <div id="customer-info-panel" class="guidepost-info-panel" style="display: none;">
                    <h3><?php esc_html_e( 'Customer Details', 'guidepost' ); ?></h3>
                    <div id="customer-info-content"></div>
                </div>

                <div class="guidepost-info-panel">
                    <h3><?php esc_html_e( 'Available Variables', 'guidepost' ); ?></h3>
                    <div class="guidepost-variables-list">
                        <?php
                        $variables = GuidePost_Email::get_available_variables();
                        foreach ( $variables as $group => $vars ) :
                        ?>
                            <div class="guidepost-variable-group">
                                <strong><?php echo esc_html( $group ); ?></strong>
                                <ul>
                                    <?php foreach ( $vars as $var => $desc ) : ?>
                                        <li>
                                            <code><?php echo esc_html( $var ); ?></code>
                                            <span class="description"><?php echo esc_html( $desc ); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div id="email-preview-modal" class="guidepost-modal" style="display: none;">
            <div class="guidepost-modal-content guidepost-modal-large">
                <div class="guidepost-modal-header">
                    <h2><?php esc_html_e( 'Email Preview', 'guidepost' ); ?></h2>
                    <button type="button" class="guidepost-modal-close">&times;</button>
                </div>
                <div class="guidepost-modal-body">
                    <div class="guidepost-preview-subject">
                        <strong><?php esc_html_e( 'Subject:', 'guidepost' ); ?></strong>
                        <span id="preview-subject"></span>
                    </div>
                    <div class="guidepost-preview-frame-container">
                        <iframe id="preview-frame" class="guidepost-preview-frame"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render templates tab
     */
    private function render_templates_tab() {
        $action      = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;

        if ( 'edit' === $action || 'add' === $action ) {
            $template = $template_id ? GuidePost_Email::get_template( $template_id ) : null;
            $this->render_template_form( $template );
            return;
        }

        $templates = GuidePost_Email::get_templates();
        ?>
        <div class="guidepost-templates-header">
            <h2><?php esc_html_e( 'Email Templates', 'guidepost' ); ?></h2>
            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-communications', 'tab' => 'templates', 'action' => 'add' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e( 'Add New Template', 'guidepost' ); ?>
            </a>
        </div>

        <?php if ( empty( $templates ) ) : ?>
            <p><?php esc_html_e( 'No templates found.', 'guidepost' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'guidepost' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'guidepost' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'guidepost' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'guidepost' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $templates as $template ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $template->name ); ?></strong>
                                <?php if ( $template->is_default ) : ?>
                                    <span class="guidepost-badge guidepost-badge-default"><?php esc_html_e( 'Default', 'guidepost' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="guidepost-template-type guidepost-template-type-<?php echo esc_attr( $template->notification_type ); ?>">
                                    <?php echo esc_html( ucwords( str_replace( '_', ' ', $template->notification_type ) ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $template->subject ); ?></td>
                            <td>
                                <span class="guidepost-status guidepost-status-<?php echo $template->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $template->is_active ? esc_html__( 'Active', 'guidepost' ) : esc_html__( 'Inactive', 'guidepost' ); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-communications', 'tab' => 'templates', 'action' => 'edit', 'template_id' => $template->id ), admin_url( 'admin.php' ) ) ); ?>">
                                    <?php esc_html_e( 'Edit', 'guidepost' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    /**
     * Render template form
     *
     * @param object|null $template Template object.
     */
    private function render_template_form( $template = null ) {
        $is_edit = ! empty( $template );
        $notification_types = array(
            'confirmation'  => __( 'Appointment Confirmation', 'guidepost' ),
            'reminder'      => __( 'Appointment Reminder', 'guidepost' ),
            'cancellation'  => __( 'Appointment Cancellation', 'guidepost' ),
            'reschedule'    => __( 'Appointment Rescheduled', 'guidepost' ),
            'receipt'       => __( 'Payment Receipt', 'guidepost' ),
            'welcome'       => __( 'Welcome Email', 'guidepost' ),
            'follow_up'     => __( 'Follow-Up Email', 'guidepost' ),
            'admin_notice'  => __( 'Admin Notification', 'guidepost' ),
            'custom'        => __( 'Custom', 'guidepost' ),
        );
        ?>
        <div class="guidepost-template-editor">
            <h2><?php echo $is_edit ? esc_html__( 'Edit Template', 'guidepost' ) : esc_html__( 'Add New Template', 'guidepost' ); ?></h2>

            <form method="post">
                <?php wp_nonce_field( 'guidepost_save_template', 'guidepost_template_nonce' ); ?>
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="template_id" value="<?php echo esc_attr( $template->id ); ?>">
                <?php endif; ?>

                <div class="guidepost-template-form-grid">
                    <div class="guidepost-template-main">
                        <table class="form-table">
                            <tr>
                                <th><label for="name"><?php esc_html_e( 'Template Name', 'guidepost' ); ?> *</label></th>
                                <td>
                                    <input type="text" name="name" id="name" class="regular-text" required
                                           value="<?php echo esc_attr( $is_edit ? $template->name : '' ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="slug"><?php esc_html_e( 'Slug', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="text" name="slug" id="slug" class="regular-text"
                                           value="<?php echo esc_attr( $is_edit ? $template->slug : '' ); ?>">
                                    <p class="description"><?php esc_html_e( 'Leave empty to auto-generate from name.', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="notification_type"><?php esc_html_e( 'Type', 'guidepost' ); ?></label></th>
                                <td>
                                    <select name="notification_type" id="notification_type">
                                        <?php foreach ( $notification_types as $type => $label ) : ?>
                                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $is_edit ? $template->notification_type : '', $type ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="subject"><?php esc_html_e( 'Subject Line', 'guidepost' ); ?> *</label></th>
                                <td>
                                    <input type="text" name="subject" id="subject" class="large-text" required
                                           value="<?php echo esc_attr( $is_edit ? $template->subject : '' ); ?>">
                                    <p class="description"><?php esc_html_e( 'You can use template variables like {{service_name}}', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="body"><?php esc_html_e( 'Email Body', 'guidepost' ); ?> *</label></th>
                                <td>
                                    <textarea name="body" id="body" rows="20" class="large-text code" required><?php echo esc_textarea( $is_edit ? $template->body : '' ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'HTML email body. Use {{custom_message}} where personalized messages should appear.', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="is_active" value="1"
                                            <?php checked( $is_edit ? $template->is_active : true ); ?>>
                                        <?php esc_html_e( 'Template is active', 'guidepost' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <?php submit_button( $is_edit ? __( 'Update Template', 'guidepost' ) : __( 'Create Template', 'guidepost' ), 'primary', 'submit', false ); ?>
                            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-communications', 'tab' => 'templates' ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                                <?php esc_html_e( 'Cancel', 'guidepost' ); ?>
                            </a>
                        </p>
                    </div>

                    <div class="guidepost-template-sidebar">
                        <div class="guidepost-info-panel">
                            <h3><?php esc_html_e( 'Available Variables', 'guidepost' ); ?></h3>
                            <div class="guidepost-variables-list guidepost-variables-compact">
                                <?php
                                $variables = GuidePost_Email::get_available_variables();
                                foreach ( $variables as $group => $vars ) :
                                ?>
                                    <div class="guidepost-variable-group">
                                        <strong><?php echo esc_html( $group ); ?></strong>
                                        <ul>
                                            <?php foreach ( $vars as $var => $desc ) : ?>
                                                <li>
                                                    <code class="guidepost-var-copy" title="<?php esc_attr_e( 'Click to copy', 'guidepost' ); ?>"><?php echo esc_html( $var ); ?></code>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render email log tab
     */
    private function render_log_tab() {
        $filter_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $filter_type   = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
        $paged         = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $per_page      = 20;

        $args = array(
            'limit'  => $per_page,
            'offset' => ( $paged - 1 ) * $per_page,
        );

        if ( $filter_status ) {
            $args['status'] = $filter_status;
        }
        if ( $filter_type ) {
            $args['notification_type'] = $filter_type;
        }

        $emails = GuidePost_Email::get_email_log( $args );
        $total  = GuidePost_Email::get_email_log_count( $args );
        $pages  = ceil( $total / $per_page );
        ?>
        <div class="guidepost-log-header">
            <h2><?php esc_html_e( 'Email Log', 'guidepost' ); ?></h2>

            <!-- Filters -->
            <form method="get" class="guidepost-log-filters">
                <input type="hidden" name="page" value="guidepost-communications">
                <input type="hidden" name="tab" value="log">

                <select name="status">
                    <option value=""><?php esc_html_e( 'All Statuses', 'guidepost' ); ?></option>
                    <option value="sent" <?php selected( $filter_status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'guidepost' ); ?></option>
                    <option value="failed" <?php selected( $filter_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'guidepost' ); ?></option>
                    <option value="pending" <?php selected( $filter_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'guidepost' ); ?></option>
                </select>

                <select name="type">
                    <option value=""><?php esc_html_e( 'All Types', 'guidepost' ); ?></option>
                    <option value="confirmation" <?php selected( $filter_type, 'confirmation' ); ?>><?php esc_html_e( 'Confirmation', 'guidepost' ); ?></option>
                    <option value="reminder" <?php selected( $filter_type, 'reminder' ); ?>><?php esc_html_e( 'Reminder', 'guidepost' ); ?></option>
                    <option value="cancellation" <?php selected( $filter_type, 'cancellation' ); ?>><?php esc_html_e( 'Cancellation', 'guidepost' ); ?></option>
                    <option value="custom" <?php selected( $filter_type, 'custom' ); ?>><?php esc_html_e( 'Custom', 'guidepost' ); ?></option>
                </select>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'guidepost' ); ?></button>

                <?php if ( $filter_status || $filter_type ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'guidepost-communications', 'tab' => 'log' ), admin_url( 'admin.php' ) ) ); ?>" class="button">
                        <?php esc_html_e( 'Clear', 'guidepost' ); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ( empty( $emails ) ) : ?>
            <p><?php esc_html_e( 'No emails found.', 'guidepost' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 180px;"><?php esc_html_e( 'Date', 'guidepost' ); ?></th>
                        <th><?php esc_html_e( 'Recipient', 'guidepost' ); ?></th>
                        <th><?php esc_html_e( 'Subject', 'guidepost' ); ?></th>
                        <th style="width: 100px;"><?php esc_html_e( 'Type', 'guidepost' ); ?></th>
                        <th style="width: 80px;"><?php esc_html_e( 'Status', 'guidepost' ); ?></th>
                        <th style="width: 100px;"><?php esc_html_e( 'Sent By', 'guidepost' ); ?></th>
                        <th style="width: 80px;"><?php esc_html_e( 'Actions', 'guidepost' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $emails as $email ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $email->created_at ) ) ); ?>
                            </td>
                            <td>
                                <?php if ( $email->customer_first_name ) : ?>
                                    <strong><?php echo esc_html( $email->customer_first_name . ' ' . $email->customer_last_name ); ?></strong><br>
                                <?php endif; ?>
                                <a href="mailto:<?php echo esc_attr( $email->recipient_email ); ?>"><?php echo esc_html( $email->recipient_email ); ?></a>
                            </td>
                            <td><?php echo esc_html( $email->subject ); ?></td>
                            <td>
                                <span class="guidepost-template-type guidepost-template-type-<?php echo esc_attr( $email->notification_type ); ?>">
                                    <?php echo esc_html( ucwords( str_replace( '_', ' ', $email->notification_type ) ) ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="guidepost-email-status guidepost-email-status-<?php echo esc_attr( $email->status ); ?>">
                                    <?php echo esc_html( ucfirst( $email->status ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $email->sent_by_name ?: '-' ); ?></td>
                            <td>
                                <button type="button" class="button button-small guidepost-view-email" data-email-id="<?php echo esc_attr( $email->id ); ?>">
                                    <?php esc_html_e( 'View', 'guidepost' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links( array(
                            'base'    => add_query_arg( 'paged', '%#%' ),
                            'format'  => '',
                            'current' => $paged,
                            'total'   => $pages,
                        ) );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Email View Modal -->
        <div id="email-view-modal" class="guidepost-modal" style="display: none;">
            <div class="guidepost-modal-content guidepost-modal-large">
                <div class="guidepost-modal-header">
                    <h2><?php esc_html_e( 'Email Details', 'guidepost' ); ?></h2>
                    <button type="button" class="guidepost-modal-close">&times;</button>
                </div>
                <div class="guidepost-modal-body">
                    <div id="email-view-content"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render SMTP settings tab
     */
    private function render_settings_tab() {
        $smtp_enabled    = get_option( 'guidepost_smtp_enabled', false );
        $smtp_host       = get_option( 'guidepost_smtp_host', '' );
        $smtp_port       = get_option( 'guidepost_smtp_port', 587 );
        $smtp_encryption = get_option( 'guidepost_smtp_encryption', 'tls' );
        $smtp_auth       = get_option( 'guidepost_smtp_auth', true );
        $smtp_username   = get_option( 'guidepost_smtp_username', '' );
        $smtp_from_email = get_option( 'guidepost_smtp_from_email', get_option( 'admin_email' ) );
        $smtp_from_name  = get_option( 'guidepost_smtp_from_name', get_bloginfo( 'name' ) );
        $email_logo      = get_option( 'guidepost_email_logo', '' );
        ?>
        <h2><?php esc_html_e( 'SMTP / Email Settings', 'guidepost' ); ?></h2>

        <form method="post">
            <?php wp_nonce_field( 'guidepost_save_smtp', 'guidepost_smtp_nonce' ); ?>

            <div class="guidepost-settings-grid">
                <div class="guidepost-settings-main">
                    <!-- Branding Section -->
                    <div class="guidepost-settings-section">
                        <h3><?php esc_html_e( 'Email Branding', 'guidepost' ); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="smtp_from_name"><?php esc_html_e( 'From Name', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="text" name="smtp_from_name" id="smtp_from_name" class="regular-text"
                                           value="<?php echo esc_attr( $smtp_from_name ); ?>">
                                    <p class="description"><?php esc_html_e( 'The name emails will appear to be from.', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="smtp_from_email"><?php esc_html_e( 'From Email', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="email" name="smtp_from_email" id="smtp_from_email" class="regular-text"
                                           value="<?php echo esc_attr( $smtp_from_email ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="email_logo"><?php esc_html_e( 'Email Logo URL', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="url" name="email_logo" id="email_logo" class="large-text"
                                           value="<?php echo esc_attr( $email_logo ); ?>"
                                           placeholder="https://example.com/logo.png">
                                    <p class="description"><?php esc_html_e( 'URL to your logo image. Leave empty to use company name as text.', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- SMTP Section -->
                    <div class="guidepost-settings-section">
                        <h3><?php esc_html_e( 'SMTP Configuration', 'guidepost' ); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( 'Enable SMTP', 'guidepost' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="smtp_enabled" value="1" <?php checked( $smtp_enabled ); ?>>
                                        <?php esc_html_e( 'Use SMTP for sending emails', 'guidepost' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'When disabled, emails are sent using WordPress default mail function.', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                            <tr class="smtp-setting">
                                <th><label for="smtp_host"><?php esc_html_e( 'SMTP Host', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="text" name="smtp_host" id="smtp_host" class="regular-text"
                                           value="<?php echo esc_attr( $smtp_host ); ?>"
                                           placeholder="smtp.gmail.com">
                                </td>
                            </tr>
                            <tr class="smtp-setting">
                                <th><label for="smtp_port"><?php esc_html_e( 'SMTP Port', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="number" name="smtp_port" id="smtp_port" class="small-text"
                                           value="<?php echo esc_attr( $smtp_port ); ?>">
                                    <p class="description"><?php esc_html_e( 'Common ports: 587 (TLS), 465 (SSL), 25 (None)', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                            <tr class="smtp-setting">
                                <th><label for="smtp_encryption"><?php esc_html_e( 'Encryption', 'guidepost' ); ?></label></th>
                                <td>
                                    <select name="smtp_encryption" id="smtp_encryption">
                                        <option value="" <?php selected( $smtp_encryption, '' ); ?>><?php esc_html_e( 'None', 'guidepost' ); ?></option>
                                        <option value="tls" <?php selected( $smtp_encryption, 'tls' ); ?>>TLS</option>
                                        <option value="ssl" <?php selected( $smtp_encryption, 'ssl' ); ?>>SSL</option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="smtp-setting">
                                <th><?php esc_html_e( 'Authentication', 'guidepost' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="smtp_auth" value="1" <?php checked( $smtp_auth ); ?>>
                                        <?php esc_html_e( 'Use SMTP Authentication', 'guidepost' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr class="smtp-setting smtp-auth-setting">
                                <th><label for="smtp_username"><?php esc_html_e( 'Username', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="text" name="smtp_username" id="smtp_username" class="regular-text"
                                           value="<?php echo esc_attr( $smtp_username ); ?>"
                                           autocomplete="off">
                                </td>
                            </tr>
                            <tr class="smtp-setting smtp-auth-setting">
                                <th><label for="smtp_password"><?php esc_html_e( 'Password', 'guidepost' ); ?></label></th>
                                <td>
                                    <input type="password" name="smtp_password" id="smtp_password" class="regular-text"
                                           placeholder="<?php esc_attr_e( 'Leave empty to keep current', 'guidepost' ); ?>"
                                           autocomplete="new-password">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p class="submit">
                        <?php submit_button( __( 'Save Settings', 'guidepost' ), 'primary', 'submit', false ); ?>
                        <button type="button" id="test-smtp-btn" class="button">
                            <span class="dashicons dashicons-email"></span>
                            <?php esc_html_e( 'Send Test Email', 'guidepost' ); ?>
                        </button>
                    </p>
                </div>

                <div class="guidepost-settings-sidebar">
                    <div class="guidepost-info-panel guidepost-smtp-presets">
                        <h3><?php esc_html_e( 'Common SMTP Presets', 'guidepost' ); ?></h3>
                        <div class="guidepost-presets-list">
                            <button type="button" class="guidepost-preset-btn" data-host="smtp.gmail.com" data-port="587" data-encryption="tls">
                                Gmail
                            </button>
                            <button type="button" class="guidepost-preset-btn" data-host="smtp.office365.com" data-port="587" data-encryption="tls">
                                Office 365
                            </button>
                            <button type="button" class="guidepost-preset-btn" data-host="smtp.mail.yahoo.com" data-port="587" data-encryption="tls">
                                Yahoo
                            </button>
                            <button type="button" class="guidepost-preset-btn" data-host="smtp.sendgrid.net" data-port="587" data-encryption="tls">
                                SendGrid
                            </button>
                            <button type="button" class="guidepost-preset-btn" data-host="email-smtp.us-east-1.amazonaws.com" data-port="587" data-encryption="tls">
                                Amazon SES
                            </button>
                            <button type="button" class="guidepost-preset-btn" data-host="smtp.mailgun.org" data-port="587" data-encryption="tls">
                                Mailgun
                            </button>
                        </div>
                        <p class="description" style="margin-top: 15px;">
                            <?php esc_html_e( 'Click to auto-fill host, port, and encryption settings.', 'guidepost' ); ?>
                        </p>
                    </div>

                    <div class="guidepost-info-panel">
                        <h3><?php esc_html_e( 'Gmail Setup', 'guidepost' ); ?></h3>
                        <p><?php esc_html_e( 'For Gmail, you need to:', 'guidepost' ); ?></p>
                        <ol style="margin-left: 1.5em; font-size: 13px;">
                            <li><?php esc_html_e( 'Enable 2-Factor Authentication', 'guidepost' ); ?></li>
                            <li><?php esc_html_e( 'Generate an App Password', 'guidepost' ); ?></li>
                            <li><?php esc_html_e( 'Use the App Password here', 'guidepost' ); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </form>
        <?php
    }
}
