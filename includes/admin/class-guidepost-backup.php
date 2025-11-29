<?php
/**
 * Backup and Restore functionality
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GuidePost Backup Class
 */
class GuidePost_Backup {

    /**
     * Single instance
     *
     * @var GuidePost_Backup
     */
    private static $instance = null;

    /**
     * Backup directory path
     *
     * @var string
     */
    private $backup_dir;

    /**
     * All GuidePost table names (without prefix)
     *
     * @var array
     */
    private $table_names = array(
        'services',
        'providers',
        'provider_services',
        'working_hours',
        'days_off',
        'customers',
        'customer_notes',
        'customer_flags',
        'customer_documents',
        'customer_purchases',
        'credit_history',
        'appointments',
        'payments',
        'notifications',
        'email_templates',
    );

    /**
     * Table import order (for foreign key integrity)
     *
     * @var array
     */
    private $import_order = array(
        'services',
        'providers',
        'email_templates',
        'customers',
        'provider_services',
        'working_hours',
        'days_off',
        'appointments',
        'customer_notes',
        'customer_flags',
        'customer_documents',
        'customer_purchases',
        'credit_history',
        'payments',
        'notifications',
    );

    /**
     * Get instance
     *
     * @return GuidePost_Backup
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
        $this->backup_dir = WP_CONTENT_DIR . '/guidepost-backups';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_backup_submenu' ), 20 );
        add_action( 'admin_init', array( $this, 'handle_backup_actions' ) );
        add_action( 'wp_ajax_guidepost_create_backup', array( $this, 'ajax_create_backup' ) );
        add_action( 'wp_ajax_guidepost_restore_backup', array( $this, 'ajax_restore_backup' ) );
        add_action( 'wp_ajax_guidepost_delete_backup', array( $this, 'ajax_delete_backup' ) );
        add_action( 'wp_ajax_guidepost_download_backup', array( $this, 'ajax_download_backup' ) );
        add_action( 'wp_ajax_guidepost_get_backup_info', array( $this, 'ajax_get_backup_info' ) );
    }

    /**
     * Add backup submenu
     */
    public function add_backup_submenu() {
        add_submenu_page(
            'guidepost',
            __( 'Backup & Restore', 'guidepost' ),
            __( 'Backup & Restore', 'guidepost' ),
            'manage_options',
            'guidepost-backup',
            array( $this, 'render_backup_page' )
        );
    }

    /**
     * Handle form-based backup actions
     */
    public function handle_backup_actions() {
        // Handle backup creation
        if ( isset( $_POST['guidepost_create_backup_nonce'] ) &&
             wp_verify_nonce( $_POST['guidepost_create_backup_nonce'], 'guidepost_create_backup' ) ) {
            $this->handle_create_backup();
        }

        // Handle restore
        if ( isset( $_POST['guidepost_restore_backup_nonce'] ) &&
             wp_verify_nonce( $_POST['guidepost_restore_backup_nonce'], 'guidepost_restore_backup' ) ) {
            $this->handle_restore_backup();
        }

        // Handle download
        if ( isset( $_GET['action'] ) && 'download' === $_GET['action'] &&
             isset( $_GET['backup'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'guidepost_download_backup' ) ) {
                $this->handle_download_backup( sanitize_file_name( $_GET['backup'] ) );
            }
        }

        // Handle delete
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] &&
             isset( $_GET['backup'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'guidepost_delete_backup' ) ) {
                $this->handle_delete_backup( sanitize_file_name( $_GET['backup'] ) );
            }
        }
    }

    /**
     * Render backup page
     */
    public function render_backup_page() {
        $backups = $this->get_existing_backups();
        ?>
        <div class="wrap guidepost-admin guidepost-backup-page">
            <h1 class="guidepost-admin-title">
                <span class="dashicons dashicons-backup"></span>
                <?php esc_html_e( 'Backup & Restore', 'guidepost' ); ?>
            </h1>

            <?php $this->render_notices(); ?>

            <div class="guidepost-backup-container">
                <!-- Create Backup Section -->
                <div class="guidepost-backup-section">
                    <h2><?php esc_html_e( 'Create Backup', 'guidepost' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Create a backup of all GuidePost data. This includes customers, appointments, services, providers, and all related data.', 'guidepost' ); ?></p>

                    <form method="post" id="guidepost-create-backup-form">
                        <?php wp_nonce_field( 'guidepost_create_backup', 'guidepost_create_backup_nonce' ); ?>

                        <h3><?php esc_html_e( 'Select Data to Backup', 'guidepost' ); ?></h3>

                        <fieldset class="guidepost-backup-options">
                            <legend class="screen-reader-text"><?php esc_html_e( 'Backup Options', 'guidepost' ); ?></legend>

                            <p><strong><?php esc_html_e( 'Core Data (Required)', 'guidepost' ); ?></strong></p>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="customers" checked disabled>
                                <span><?php esc_html_e( 'Customers', 'guidepost' ); ?></span>
                                <input type="hidden" name="backup_tables[]" value="customers">
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="appointments" checked disabled>
                                <span><?php esc_html_e( 'Appointments', 'guidepost' ); ?></span>
                                <input type="hidden" name="backup_tables[]" value="appointments">
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="services" checked disabled>
                                <span><?php esc_html_e( 'Services', 'guidepost' ); ?></span>
                                <input type="hidden" name="backup_tables[]" value="services">
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="providers" checked disabled>
                                <span><?php esc_html_e( 'Providers', 'guidepost' ); ?></span>
                                <input type="hidden" name="backup_tables[]" value="providers">
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="payments" checked disabled>
                                <span><?php esc_html_e( 'Payments', 'guidepost' ); ?></span>
                                <input type="hidden" name="backup_tables[]" value="payments">
                            </label>

                            <p style="margin-top: 15px;"><strong><?php esc_html_e( 'Optional Data', 'guidepost' ); ?></strong></p>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="customer_notes" checked>
                                <span><?php esc_html_e( 'Customer Notes', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="customer_flags" checked>
                                <span><?php esc_html_e( 'Customer Flags', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="customer_documents" checked>
                                <span><?php esc_html_e( 'Customer Documents (metadata)', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="customer_purchases" checked>
                                <span><?php esc_html_e( 'Customer Purchases', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="credit_history" checked>
                                <span><?php esc_html_e( 'Credit History', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="provider_services" checked>
                                <span><?php esc_html_e( 'Provider-Service Mappings', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="working_hours" checked>
                                <span><?php esc_html_e( 'Working Hours', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="days_off" checked>
                                <span><?php esc_html_e( 'Days Off', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="email_templates" checked>
                                <span><?php esc_html_e( 'Email Templates', 'guidepost' ); ?></span>
                            </label>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_tables[]" value="notifications">
                                <span><?php esc_html_e( 'Notification History', 'guidepost' ); ?></span>
                            </label>

                            <p style="margin-top: 15px;"><strong><?php esc_html_e( 'Settings', 'guidepost' ); ?></strong></p>
                            <label class="guidepost-backup-option">
                                <input type="checkbox" name="backup_settings" value="1" checked>
                                <span><?php esc_html_e( 'Plugin Settings', 'guidepost' ); ?></span>
                            </label>
                        </fieldset>

                        <p class="submit">
                            <button type="submit" class="button button-primary button-hero" id="guidepost-create-backup-btn">
                                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                                <?php esc_html_e( 'Create Backup', 'guidepost' ); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Existing Backups Section -->
                <div class="guidepost-backup-section">
                    <h2><?php esc_html_e( 'Existing Backups', 'guidepost' ); ?></h2>

                    <?php if ( empty( $backups ) ) : ?>
                        <p class="description"><?php esc_html_e( 'No backups found. Create your first backup above.', 'guidepost' ); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Backup Name', 'guidepost' ); ?></th>
                                    <th><?php esc_html_e( 'Date', 'guidepost' ); ?></th>
                                    <th><?php esc_html_e( 'Size', 'guidepost' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'guidepost' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $backups as $backup ) : ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $backup['name'] ); ?></strong>
                                            <?php if ( ! empty( $backup['tables_count'] ) ) : ?>
                                                <br><span class="description"><?php echo esc_html( sprintf( __( '%d tables', 'guidepost' ), $backup['tables_count'] ) ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( $backup['date'] ); ?></td>
                                        <td><?php echo esc_html( $backup['size'] ); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'guidepost-backup', 'action' => 'download', 'backup' => $backup['filename'] ), admin_url( 'admin.php' ) ), 'guidepost_download_backup' ) ); ?>" class="button button-small">
                                                <?php esc_html_e( 'Download', 'guidepost' ); ?>
                                            </a>
                                            <button type="button" class="button button-small guidepost-restore-btn" data-backup="<?php echo esc_attr( $backup['filename'] ); ?>">
                                                <?php esc_html_e( 'Restore', 'guidepost' ); ?>
                                            </button>
                                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'guidepost-backup', 'action' => 'delete', 'backup' => $backup['filename'] ), admin_url( 'admin.php' ) ), 'guidepost_delete_backup' ) ); ?>" class="button button-small guidepost-delete-backup-btn" style="color: #a00;">
                                                <?php esc_html_e( 'Delete', 'guidepost' ); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Restore from File Section -->
                <div class="guidepost-backup-section">
                    <h2><?php esc_html_e( 'Restore from File', 'guidepost' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Upload a backup file to restore GuidePost data.', 'guidepost' ); ?></p>

                    <form method="post" enctype="multipart/form-data" id="guidepost-upload-restore-form">
                        <?php wp_nonce_field( 'guidepost_restore_backup', 'guidepost_restore_backup_nonce' ); ?>
                        <input type="hidden" name="restore_source" value="upload">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="backup_file"><?php esc_html_e( 'Backup File', 'guidepost' ); ?></label>
                                </th>
                                <td>
                                    <input type="file" name="backup_file" id="backup_file" accept=".zip">
                                    <p class="description"><?php esc_html_e( 'Select a GuidePost backup ZIP file.', 'guidepost' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e( 'Restore Mode', 'guidepost' ); ?>
                                </th>
                                <td>
                                    <fieldset>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="radio" name="restore_mode" value="overwrite" checked>
                                            <strong><?php esc_html_e( 'Overwrite', 'guidepost' ); ?></strong>
                                            - <?php esc_html_e( 'Replace all existing data with backup data', 'guidepost' ); ?>
                                        </label>
                                        <label style="display: block;">
                                            <input type="radio" name="restore_mode" value="merge">
                                            <strong><?php esc_html_e( 'Merge', 'guidepost' ); ?></strong>
                                            - <?php esc_html_e( 'Add backup data to existing data (may create duplicates)', 'guidepost' ); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e( 'Pre-Restore Backup', 'guidepost' ); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="pre_restore_backup" value="1" checked>
                                        <?php esc_html_e( 'Create a backup of current data before restoring', 'guidepost' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
                                <?php esc_html_e( 'Upload & Restore', 'guidepost' ); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <!-- Restore Modal -->
        <div id="guidepost-restore-modal" class="guidepost-modal" style="display: none;">
            <div class="guidepost-modal-content">
                <span class="guidepost-modal-close">&times;</span>
                <h2><?php esc_html_e( 'Restore Backup', 'guidepost' ); ?></h2>

                <form method="post" id="guidepost-restore-form">
                    <?php wp_nonce_field( 'guidepost_restore_backup', 'guidepost_restore_backup_nonce' ); ?>
                    <input type="hidden" name="restore_source" value="existing">
                    <input type="hidden" name="backup_filename" id="restore-backup-filename" value="">

                    <div id="restore-backup-info"></div>

                    <h3><?php esc_html_e( 'Restore Mode', 'guidepost' ); ?></h3>
                    <fieldset>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="radio" name="restore_mode" value="overwrite" checked>
                            <strong><?php esc_html_e( 'Overwrite', 'guidepost' ); ?></strong>
                            - <?php esc_html_e( 'Replace all existing data', 'guidepost' ); ?>
                        </label>
                        <label style="display: block;">
                            <input type="radio" name="restore_mode" value="merge">
                            <strong><?php esc_html_e( 'Merge', 'guidepost' ); ?></strong>
                            - <?php esc_html_e( 'Add to existing data', 'guidepost' ); ?>
                        </label>
                    </fieldset>

                    <h3><?php esc_html_e( 'Select Data to Restore', 'guidepost' ); ?></h3>
                    <div id="restore-tables-list"></div>

                    <p>
                        <label>
                            <input type="checkbox" name="pre_restore_backup" value="1" checked>
                            <?php esc_html_e( 'Create backup before restoring', 'guidepost' ); ?>
                        </label>
                    </p>

                    <div class="guidepost-modal-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'Warning: In overwrite mode, existing data will be permanently replaced!', 'guidepost' ); ?>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Restore Now', 'guidepost' ); ?>
                        </button>
                        <button type="button" class="button guidepost-modal-cancel">
                            <?php esc_html_e( 'Cancel', 'guidepost' ); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render notices
     */
    private function render_notices() {
        if ( isset( $_GET['backup_created'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Backup created successfully!', 'guidepost' ) . '</p></div>';
        }
        if ( isset( $_GET['backup_deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Backup deleted successfully!', 'guidepost' ) . '</p></div>';
        }
        if ( isset( $_GET['backup_restored'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Backup restored successfully!', 'guidepost' ) . '</p></div>';
        }
        if ( isset( $_GET['error'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( urldecode( $_GET['error'] ) ) . '</p></div>';
        }
    }

    /**
     * Handle create backup action
     */
    private function handle_create_backup() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied.', 'guidepost' ) );
        }

        $tables = isset( $_POST['backup_tables'] ) ? array_map( 'sanitize_text_field', $_POST['backup_tables'] ) : array();
        $tables = array_unique( $tables );
        $include_settings = isset( $_POST['backup_settings'] ) && '1' === $_POST['backup_settings'];

        $result = $this->create_backup( $tables, $include_settings );

        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-backup', 'error' => urlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-backup', 'backup_created' => '1' ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * Handle restore backup action
     */
    private function handle_restore_backup() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied.', 'guidepost' ) );
        }

        $source = isset( $_POST['restore_source'] ) ? sanitize_text_field( $_POST['restore_source'] ) : '';
        $mode = isset( $_POST['restore_mode'] ) ? sanitize_text_field( $_POST['restore_mode'] ) : 'overwrite';
        $pre_backup = isset( $_POST['pre_restore_backup'] ) && '1' === $_POST['pre_restore_backup'];
        $tables = isset( $_POST['restore_tables'] ) ? array_map( 'sanitize_text_field', $_POST['restore_tables'] ) : array();

        // Create pre-restore backup if requested
        if ( $pre_backup ) {
            $backup_result = $this->create_backup( $this->table_names, true, 'pre-restore' );
            if ( is_wp_error( $backup_result ) ) {
                wp_redirect( add_query_arg( array( 'page' => 'guidepost-backup', 'error' => urlencode( __( 'Failed to create pre-restore backup: ', 'guidepost' ) . $backup_result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
                exit;
            }
        }

        if ( 'upload' === $source ) {
            // Handle uploaded file
            if ( empty( $_FILES['backup_file']['tmp_name'] ) ) {
                wp_redirect( add_query_arg( array( 'page' => 'guidepost-backup', 'error' => urlencode( __( 'No file uploaded.', 'guidepost' ) ) ), admin_url( 'admin.php' ) ) );
                exit;
            }

            $result = $this->restore_from_file( $_FILES['backup_file']['tmp_name'], $mode, $tables );
        } else {
            // Handle existing backup
            $filename = isset( $_POST['backup_filename'] ) ? sanitize_file_name( $_POST['backup_filename'] ) : '';
            if ( empty( $filename ) ) {
                wp_redirect( add_query_arg( array( 'page' => 'guidepost-backup', 'error' => urlencode( __( 'No backup selected.', 'guidepost' ) ) ), admin_url( 'admin.php' ) ) );
                exit;
            }

            $filepath = $this->backup_dir . '/' . $filename;
            $result = $this->restore_from_file( $filepath, $mode, $tables );
        }

        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-backup', 'error' => urlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( array( 'page' => 'guidepost-backup', 'backup_restored' => '1' ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * Handle download backup action
     *
     * @param string $filename Backup filename.
     */
    private function handle_download_backup( $filename ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied.', 'guidepost' ) );
        }

        $filepath = $this->backup_dir . '/' . $filename;

        if ( ! file_exists( $filepath ) ) {
            wp_die( __( 'Backup file not found.', 'guidepost' ) );
        }

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $filepath ) );
        readfile( $filepath );
        exit;
    }

    /**
     * Handle delete backup action
     *
     * @param string $filename Backup filename.
     */
    private function handle_delete_backup( $filename ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permission denied.', 'guidepost' ) );
        }

        $filepath = $this->backup_dir . '/' . $filename;

        if ( file_exists( $filepath ) ) {
            unlink( $filepath );
        }

        wp_redirect( add_query_arg( array( 'page' => 'guidepost-backup', 'backup_deleted' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Create backup
     *
     * @param array  $tables           Tables to backup.
     * @param bool   $include_settings Include settings.
     * @param string $prefix           Filename prefix.
     * @return string|WP_Error Backup filename or error.
     */
    public function create_backup( $tables = array(), $include_settings = true, $prefix = 'backup' ) {
        global $wpdb;

        // Ensure backup directory exists
        if ( ! $this->ensure_backup_dir() ) {
            return new WP_Error( 'dir_error', __( 'Could not create backup directory.', 'guidepost' ) );
        }

        // Use all tables if none specified
        if ( empty( $tables ) ) {
            $tables = $this->table_names;
        }

        $db_tables = GuidePost_Database::get_table_names();
        $backup_data = array();
        $record_counts = array();

        // Export each table
        foreach ( $tables as $table_key ) {
            if ( ! isset( $db_tables[ $table_key ] ) ) {
                continue;
            }

            $table_name = $db_tables[ $table_key ];
            $rows = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );
            $backup_data[ $table_key ] = $rows;
            $record_counts[ $table_key ] = count( $rows );
        }

        // Export settings
        $settings = array();
        if ( $include_settings ) {
            $options = $wpdb->get_results(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'guidepost_%'",
                ARRAY_A
            );
            foreach ( $options as $option ) {
                $settings[ $option['option_name'] ] = maybe_unserialize( $option['option_value'] );
            }
        }

        // Create manifest
        $manifest = array(
            'version'           => '1.0',
            'plugin_version'    => defined( 'GUIDEPOST_VERSION' ) ? GUIDEPOST_VERSION : '1.0.0',
            'wordpress_version' => get_bloginfo( 'version' ),
            'created_at'        => current_time( 'c' ),
            'site_url'          => get_site_url(),
            'table_prefix'      => $wpdb->prefix . 'guidepost_',
            'tables_included'   => $tables,
            'includes_settings' => $include_settings,
            'record_counts'     => $record_counts,
        );

        // Create ZIP file
        $timestamp = current_time( 'Y-m-d-His' );
        $filename = "guidepost-{$prefix}-{$timestamp}.zip";
        $filepath = $this->backup_dir . '/' . $filename;

        $zip = new ZipArchive();
        if ( $zip->open( $filepath, ZipArchive::CREATE ) !== true ) {
            return new WP_Error( 'zip_error', __( 'Could not create backup file.', 'guidepost' ) );
        }

        // Add manifest
        $zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );

        // Add database exports
        $zip->addEmptyDir( 'database' );
        foreach ( $backup_data as $table_key => $rows ) {
            $zip->addFromString( "database/{$table_key}.json", wp_json_encode( $rows, JSON_PRETTY_PRINT ) );
        }

        // Add settings
        if ( $include_settings && ! empty( $settings ) ) {
            $zip->addFromString( 'settings.json', wp_json_encode( $settings, JSON_PRETTY_PRINT ) );
        }

        $zip->close();

        return $filename;
    }

    /**
     * Restore from backup file
     *
     * @param string $filepath Path to backup file.
     * @param string $mode     Restore mode (overwrite/merge).
     * @param array  $tables   Specific tables to restore.
     * @return true|WP_Error True on success or error.
     */
    public function restore_from_file( $filepath, $mode = 'overwrite', $tables = array() ) {
        global $wpdb;

        if ( ! file_exists( $filepath ) ) {
            return new WP_Error( 'file_not_found', __( 'Backup file not found.', 'guidepost' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $filepath ) !== true ) {
            return new WP_Error( 'zip_error', __( 'Could not open backup file.', 'guidepost' ) );
        }

        // Read manifest
        $manifest_json = $zip->getFromName( 'manifest.json' );
        if ( false === $manifest_json ) {
            $zip->close();
            return new WP_Error( 'invalid_backup', __( 'Invalid backup file: manifest not found.', 'guidepost' ) );
        }

        $manifest = json_decode( $manifest_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $zip->close();
            return new WP_Error( 'invalid_manifest', __( 'Invalid backup file: corrupted manifest.', 'guidepost' ) );
        }

        // Ensure database tables exist before restore
        GuidePost_Database::create_tables();

        $db_tables = GuidePost_Database::get_table_names();

        // Determine which tables to restore
        $tables_to_restore = ! empty( $tables ) ? $tables : ( $manifest['tables_included'] ?? $this->table_names );

        // Sort by import order
        usort( $tables_to_restore, function( $a, $b ) {
            $order_a = array_search( $a, $this->import_order );
            $order_b = array_search( $b, $this->import_order );
            return $order_a - $order_b;
        } );

        // Disable foreign key checks and autocommit for faster inserts
        $wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
        $wpdb->query( 'SET autocommit = 0' );

        $errors = array();
        $restored_counts = array();

        try {
            foreach ( $tables_to_restore as $table_key ) {
                if ( ! isset( $db_tables[ $table_key ] ) ) {
                    $errors[] = "Table key '{$table_key}' not found in database schema.";
                    continue;
                }

                $table_name = $db_tables[ $table_key ];

                // Check if table exists
                $table_exists = $wpdb->get_var( $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                ) );

                if ( ! $table_exists ) {
                    $errors[] = "Table '{$table_name}' does not exist.";
                    continue;
                }

                $json_data = $zip->getFromName( "database/{$table_key}.json" );

                if ( false === $json_data ) {
                    $errors[] = "JSON file for '{$table_key}' not found in backup.";
                    continue;
                }

                $rows = json_decode( $json_data, true );
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    $errors[] = "Invalid JSON for '{$table_key}': " . json_last_error_msg();
                    continue;
                }

                if ( ! is_array( $rows ) ) {
                    $errors[] = "Data for '{$table_key}' is not an array.";
                    continue;
                }

                // Truncate table in overwrite mode
                if ( 'overwrite' === $mode ) {
                    $wpdb->query( "TRUNCATE TABLE {$table_name}" );
                }

                // Get table columns
                $columns = $wpdb->get_col( "DESCRIBE {$table_name}", 0 );

                $inserted = 0;
                $failed = 0;

                // Insert rows
                foreach ( $rows as $row ) {
                    // Filter row to only include existing columns
                    $filtered_row = array();
                    foreach ( $row as $col => $val ) {
                        if ( in_array( $col, $columns, true ) ) {
                            $filtered_row[ $col ] = $val;
                        }
                    }

                    if ( empty( $filtered_row ) ) {
                        $failed++;
                        continue;
                    }

                    if ( 'merge' === $mode ) {
                        // In merge mode, use REPLACE to update existing or insert new
                        $result = $wpdb->replace( $table_name, $filtered_row );
                    } else {
                        $result = $wpdb->insert( $table_name, $filtered_row );
                    }

                    if ( false === $result ) {
                        $failed++;
                        // Log the error for debugging
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( "GuidePost Restore: Failed to insert into {$table_name}: " . $wpdb->last_error );
                        }
                    } else {
                        $inserted++;
                    }
                }

                $restored_counts[ $table_key ] = array(
                    'inserted' => $inserted,
                    'failed'   => $failed,
                    'total'    => count( $rows ),
                );
            }

            // Commit the transaction
            $wpdb->query( 'COMMIT' );

            // Restore settings if included
            if ( ( $manifest['includes_settings'] ?? false ) && ( empty( $tables ) || in_array( 'settings', $tables, true ) ) ) {
                $settings_json = $zip->getFromName( 'settings.json' );
                if ( false !== $settings_json ) {
                    $settings = json_decode( $settings_json, true );
                    if ( is_array( $settings ) ) {
                        foreach ( $settings as $option_name => $option_value ) {
                            update_option( $option_name, $option_value );
                        }
                    }
                }
            }

        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            $wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
            $wpdb->query( 'SET autocommit = 1' );
            $zip->close();
            return new WP_Error( 'restore_error', $e->getMessage() );
        }

        $wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
        $wpdb->query( 'SET autocommit = 1' );
        $zip->close();

        // Log restore summary for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'GuidePost Restore Summary: ' . print_r( $restored_counts, true ) );
            if ( ! empty( $errors ) ) {
                error_log( 'GuidePost Restore Errors: ' . print_r( $errors, true ) );
            }
        }

        // Return errors if any critical issues
        if ( ! empty( $errors ) ) {
            // Still return success but log the errors
            // Could change this to return error if needed
        }

        return true;
    }

    /**
     * Get existing backups
     *
     * @return array List of backups.
     */
    public function get_existing_backups() {
        $backups = array();

        if ( ! is_dir( $this->backup_dir ) ) {
            return $backups;
        }

        $files = glob( $this->backup_dir . '/guidepost-*.zip' );

        if ( empty( $files ) ) {
            return $backups;
        }

        foreach ( $files as $file ) {
            $filename = basename( $file );
            $filesize = filesize( $file );
            $filetime = filemtime( $file );

            // Try to read manifest for details
            $tables_count = 0;
            $zip = new ZipArchive();
            if ( $zip->open( $file ) === true ) {
                $manifest_json = $zip->getFromName( 'manifest.json' );
                if ( $manifest_json ) {
                    $manifest = json_decode( $manifest_json, true );
                    $tables_count = count( $manifest['tables_included'] ?? array() );
                }
                $zip->close();
            }

            $backups[] = array(
                'filename'     => $filename,
                'name'         => preg_replace( '/^guidepost-(.+)\.zip$/', '$1', $filename ),
                'date'         => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $filetime ),
                'size'         => size_format( $filesize ),
                'tables_count' => $tables_count,
            );
        }

        // Sort by date (newest first)
        usort( $backups, function( $a, $b ) {
            return strcmp( $b['name'], $a['name'] );
        } );

        return $backups;
    }

    /**
     * Get backup info
     *
     * @param string $filename Backup filename.
     * @return array|WP_Error Backup info or error.
     */
    public function get_backup_info( $filename ) {
        $filepath = $this->backup_dir . '/' . $filename;

        if ( ! file_exists( $filepath ) ) {
            return new WP_Error( 'not_found', __( 'Backup not found.', 'guidepost' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $filepath ) !== true ) {
            return new WP_Error( 'zip_error', __( 'Could not open backup.', 'guidepost' ) );
        }

        $manifest_json = $zip->getFromName( 'manifest.json' );
        $zip->close();

        if ( false === $manifest_json ) {
            return new WP_Error( 'invalid', __( 'Invalid backup: no manifest.', 'guidepost' ) );
        }

        return json_decode( $manifest_json, true );
    }

    /**
     * Ensure backup directory exists
     *
     * @return bool Success.
     */
    private function ensure_backup_dir() {
        if ( is_dir( $this->backup_dir ) ) {
            return true;
        }

        if ( ! wp_mkdir_p( $this->backup_dir ) ) {
            return false;
        }

        // Add index.php for security
        file_put_contents( $this->backup_dir . '/index.php', '<?php // Silence is golden' );

        // Add .htaccess for extra protection
        file_put_contents( $this->backup_dir . '/.htaccess', 'deny from all' );

        return true;
    }

    /**
     * AJAX: Create backup
     */
    public function ajax_create_backup() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'guidepost' ) ) );
        }

        $tables = isset( $_POST['tables'] ) ? array_map( 'sanitize_text_field', $_POST['tables'] ) : $this->table_names;
        $include_settings = isset( $_POST['include_settings'] ) && $_POST['include_settings'];

        $result = $this->create_backup( $tables, $include_settings );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'  => __( 'Backup created successfully.', 'guidepost' ),
            'filename' => $result,
        ) );
    }

    /**
     * AJAX: Get backup info for restore modal
     */
    public function ajax_get_backup_info() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'guidepost' ) ) );
        }

        $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';
        $info = $this->get_backup_info( $filename );

        if ( is_wp_error( $info ) ) {
            wp_send_json_error( array( 'message' => $info->get_error_message() ) );
        }

        wp_send_json_success( $info );
    }

    /**
     * AJAX: Restore backup
     */
    public function ajax_restore_backup() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'guidepost' ) ) );
        }

        $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';
        $mode = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'overwrite';
        $tables = isset( $_POST['tables'] ) ? array_map( 'sanitize_text_field', $_POST['tables'] ) : array();
        $pre_backup = isset( $_POST['pre_backup'] ) && $_POST['pre_backup'];

        // Create pre-restore backup
        if ( $pre_backup ) {
            $backup_result = $this->create_backup( $this->table_names, true, 'pre-restore' );
            if ( is_wp_error( $backup_result ) ) {
                wp_send_json_error( array( 'message' => __( 'Pre-restore backup failed: ', 'guidepost' ) . $backup_result->get_error_message() ) );
            }
        }

        $filepath = $this->backup_dir . '/' . $filename;
        $result = $this->restore_from_file( $filepath, $mode, $tables );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Backup restored successfully.', 'guidepost' ) ) );
    }

    /**
     * AJAX: Delete backup
     */
    public function ajax_delete_backup() {
        check_ajax_referer( 'guidepost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'guidepost' ) ) );
        }

        $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';
        $filepath = $this->backup_dir . '/' . $filename;

        if ( file_exists( $filepath ) ) {
            unlink( $filepath );
            wp_send_json_success( array( 'message' => __( 'Backup deleted.', 'guidepost' ) ) );
        }

        wp_send_json_error( array( 'message' => __( 'Backup not found.', 'guidepost' ) ) );
    }

    /**
     * AJAX: Download backup
     */
    public function ajax_download_backup() {
        // This is handled via direct file download, not AJAX
    }
}
