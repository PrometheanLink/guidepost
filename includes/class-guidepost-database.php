<?php
/**
 * Database table creation and management
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database class
 */
class GuidePost_Database {

    /**
     * Table names
     */
    public static function get_table_names() {
        global $wpdb;

        return array(
            'services'            => $wpdb->prefix . 'guidepost_services',
            'providers'           => $wpdb->prefix . 'guidepost_providers',
            'provider_services'   => $wpdb->prefix . 'guidepost_provider_services',
            'working_hours'       => $wpdb->prefix . 'guidepost_working_hours',
            'days_off'            => $wpdb->prefix . 'guidepost_days_off',
            'customers'           => $wpdb->prefix . 'guidepost_customers',
            'customer_notes'      => $wpdb->prefix . 'guidepost_customer_notes',
            'customer_purchases'  => $wpdb->prefix . 'guidepost_customer_purchases',
            'customer_documents'  => $wpdb->prefix . 'guidepost_customer_documents',
            'customer_flags'      => $wpdb->prefix . 'guidepost_customer_flags',
            'credit_history'      => $wpdb->prefix . 'guidepost_credit_history',
            'appointments'        => $wpdb->prefix . 'guidepost_appointments',
            'payments'            => $wpdb->prefix . 'guidepost_payments',
            'notifications'       => $wpdb->prefix . 'guidepost_notifications',
            'email_templates'     => $wpdb->prefix . 'guidepost_email_templates',
        );
    }

    /**
     * Create all tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_table_names();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Services table (with appointment mode support)
        $sql = "CREATE TABLE {$tables['services']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            duration int(11) NOT NULL DEFAULT 60,
            price decimal(10,2) DEFAULT 0.00,
            deposit_amount decimal(10,2) DEFAULT 0.00,
            deposit_type enum('fixed','percentage') DEFAULT 'fixed',
            color varchar(7) DEFAULT '#c16107',
            status enum('active','inactive','hidden') DEFAULT 'active',
            min_capacity int(11) DEFAULT 1,
            max_capacity int(11) DEFAULT 1,
            buffer_before int(11) DEFAULT 0,
            buffer_after int(11) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            appointment_mode enum('in_person','virtual','hybrid') DEFAULT 'in_person',
            default_location text,
            default_meeting_platform enum('google_meet','zoom','teams','other') DEFAULT NULL,
            default_meeting_link varchar(500) DEFAULT NULL,
            meeting_instructions text,
            requires_credit tinyint(1) DEFAULT 0,
            credits_required int(11) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY sort_order (sort_order),
            KEY appointment_mode (appointment_mode)
        ) $charset_collate;";
        dbDelta( $sql );

        // Providers table
        $sql = "CREATE TABLE {$tables['providers']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50),
            bio text,
            photo_url varchar(500),
            timezone varchar(50) DEFAULT 'America/New_York',
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );

        // Provider-Service assignments
        $sql = "CREATE TABLE {$tables['provider_services']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) UNSIGNED NOT NULL,
            service_id bigint(20) UNSIGNED NOT NULL,
            custom_price decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY provider_service (provider_id, service_id),
            KEY service_id (service_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Working hours
        $sql = "CREATE TABLE {$tables['working_hours']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) UNSIGNED NOT NULL,
            day_of_week tinyint(1) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY provider_day (provider_id, day_of_week, is_active)
        ) $charset_collate;";
        dbDelta( $sql );

        // Days off
        $sql = "CREATE TABLE {$tables['days_off']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) UNSIGNED NOT NULL,
            date_start date NOT NULL,
            date_end date NOT NULL,
            reason varchar(255),
            PRIMARY KEY (id),
            KEY provider_dates (provider_id, date_start, date_end)
        ) $charset_collate;";
        dbDelta( $sql );

        // Customers table (enhanced for Customer Manager)
        $sql = "CREATE TABLE {$tables['customers']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50),
            notes text,
            status enum('active','paused','vip','inactive','prospect') DEFAULT 'active',
            avatar_url varchar(500) DEFAULT NULL,
            google_drive_url varchar(500) DEFAULT NULL,
            first_contact_date date DEFAULT NULL,
            tags text DEFAULT NULL,
            source varchar(100) DEFAULT NULL,
            last_booking_date date DEFAULT NULL,
            next_booking_date date DEFAULT NULL,
            total_spent decimal(10,2) DEFAULT 0.00,
            total_appointments int(11) DEFAULT 0,
            total_credits int(11) DEFAULT 0,
            birthday date DEFAULT NULL,
            company varchar(255) DEFAULT NULL,
            job_title varchar(255) DEFAULT NULL,
            preferred_contact enum('email','phone','sms') DEFAULT 'email',
            timezone varchar(50) DEFAULT NULL,
            project_journey_id int(11) DEFAULT NULL,
            project_journey_user_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY user_id (user_id),
            KEY status (status),
            KEY last_booking_date (last_booking_date),
            KEY project_journey_id (project_journey_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Customer notes table
        $sql = "CREATE TABLE {$tables['customer_notes']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            note_text text NOT NULL,
            note_type enum('general','session','follow_up','alert','private') DEFAULT 'general',
            is_pinned tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY user_id (user_id),
            KEY note_type (note_type),
            KEY is_pinned (is_pinned)
        ) $charset_collate;";
        dbDelta( $sql );

        // Customer purchases table (beyond WooCommerce)
        $sql = "CREATE TABLE {$tables['customer_purchases']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            wc_order_id bigint(20) UNSIGNED DEFAULT NULL,
            purchase_type enum('service','package','credit','product','other') NOT NULL,
            description varchar(500) NOT NULL,
            amount decimal(10,2) NOT NULL,
            credits_granted int(11) DEFAULT 0,
            quantity int(11) DEFAULT 1,
            metadata text DEFAULT NULL,
            purchase_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY wc_order_id (wc_order_id),
            KEY purchase_type (purchase_type),
            KEY purchase_date (purchase_date)
        ) $charset_collate;";
        dbDelta( $sql );

        // Customer documents table
        $sql = "CREATE TABLE {$tables['customer_documents']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            appointment_id bigint(20) UNSIGNED DEFAULT NULL,
            filename varchar(255) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_type varchar(100) DEFAULT NULL,
            file_size bigint(20) DEFAULT NULL,
            uploaded_by enum('customer','admin') DEFAULT 'customer',
            uploaded_by_user_id bigint(20) UNSIGNED DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY appointment_id (appointment_id),
            KEY uploaded_by (uploaded_by)
        ) $charset_collate;";
        dbDelta( $sql );

        // Customer flags table (alerts and follow-ups)
        $sql = "CREATE TABLE {$tables['customer_flags']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            flag_type enum('follow_up','inactive','birthday','vip_check','payment_due','custom') NOT NULL,
            message varchar(500) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            is_dismissed tinyint(1) DEFAULT 0,
            dismissed_by bigint(20) UNSIGNED DEFAULT NULL,
            dismissed_at datetime DEFAULT NULL,
            trigger_date date DEFAULT NULL,
            auto_generated tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY flag_type (flag_type),
            KEY is_active (is_active),
            KEY trigger_date (trigger_date)
        ) $charset_collate;";
        dbDelta( $sql );

        // Credit history table (audit trail)
        $sql = "CREATE TABLE {$tables['credit_history']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            delta int(11) NOT NULL,
            reason varchar(500) NOT NULL,
            old_balance int(11) NOT NULL,
            new_balance int(11) NOT NULL,
            reference_type varchar(50) DEFAULT NULL,
            reference_id bigint(20) UNSIGNED DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY reference_type (reference_type, reference_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta( $sql );

        // Appointments table (with meeting link support)
        $sql = "CREATE TABLE {$tables['appointments']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id bigint(20) UNSIGNED NOT NULL,
            provider_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            end_time time NOT NULL,
            status enum('pending','approved','canceled','completed','no_show') DEFAULT 'pending',
            appointment_mode enum('in_person','virtual') DEFAULT 'in_person',
            location text DEFAULT NULL,
            meeting_platform varchar(50) DEFAULT NULL,
            meeting_link varchar(500) DEFAULT NULL,
            meeting_password varchar(100) DEFAULT NULL,
            internal_notes text,
            customer_notes text,
            admin_notes text,
            follow_up_date date DEFAULT NULL,
            follow_up_notes text DEFAULT NULL,
            credits_used int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date (booking_date),
            KEY idx_provider_date (provider_id, booking_date),
            KEY idx_customer (customer_id),
            KEY idx_status (status),
            KEY idx_appointment_mode (appointment_mode),
            KEY idx_follow_up (follow_up_date)
        ) $charset_collate;";
        dbDelta( $sql );

        // Payments table
        $sql = "CREATE TABLE {$tables['payments']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id bigint(20) UNSIGNED NOT NULL,
            amount decimal(10,2) NOT NULL,
            status enum('pending','paid','partially_paid','refunded','failed') DEFAULT 'pending',
            gateway enum('on_site','woocommerce','stripe','paypal') DEFAULT 'on_site',
            wc_order_id bigint(20) UNSIGNED DEFAULT NULL,
            wc_order_item_id bigint(20) UNSIGNED DEFAULT NULL,
            transaction_id varchar(255),
            payment_data text,
            paid_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id),
            KEY wc_order_id (wc_order_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );

        // Notifications log (enhanced for communications)
        $sql = "CREATE TABLE {$tables['notifications']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id bigint(20) UNSIGNED DEFAULT NULL,
            customer_id bigint(20) UNSIGNED DEFAULT NULL,
            template_id bigint(20) UNSIGNED DEFAULT NULL,
            recipient_email varchar(255) NOT NULL,
            recipient_name varchar(255) DEFAULT NULL,
            notification_type enum('confirmation','reminder','cancellation','reschedule','admin_notice','follow_up','custom','receipt','welcome') NOT NULL,
            subject varchar(255),
            body longtext,
            custom_message text,
            sent_by bigint(20) UNSIGNED DEFAULT NULL,
            status enum('sent','failed','pending','queued') DEFAULT 'pending',
            error_message text,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id),
            KEY customer_id (customer_id),
            KEY template_id (template_id),
            KEY status (status),
            KEY notification_type (notification_type),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        dbDelta( $sql );

        // Email templates
        $sql = "CREATE TABLE {$tables['email_templates']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(100) NOT NULL,
            notification_type enum('confirmation','reminder','cancellation','reschedule','admin_notice','follow_up','custom','receipt','welcome') NOT NULL,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY notification_type (notification_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta( $sql );

        // Store DB version
        update_option( 'guidepost_db_version', GUIDEPOST_VERSION );

        // Insert default email templates
        self::insert_default_templates();
    }

    /**
     * Insert default email templates
     */
    public static function insert_default_templates() {
        global $wpdb;
        $tables = self::get_table_names();

        // Check if templates already exist
        $existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['email_templates']}" );
        if ( $existing > 0 ) {
            return;
        }

        $templates = array(
            array(
                'name'              => 'Appointment Confirmation',
                'slug'              => 'appointment-confirmation',
                'notification_type' => 'confirmation',
                'subject'           => 'Your Appointment is Confirmed - {{service_name}}',
                'body'              => self::get_default_confirmation_template(),
                'is_default'        => 1,
            ),
            array(
                'name'              => 'Appointment Reminder',
                'slug'              => 'appointment-reminder',
                'notification_type' => 'reminder',
                'subject'           => 'Reminder: Your Appointment Tomorrow - {{service_name}}',
                'body'              => self::get_default_reminder_template(),
                'is_default'        => 1,
            ),
            array(
                'name'              => 'Appointment Cancellation',
                'slug'              => 'appointment-cancellation',
                'notification_type' => 'cancellation',
                'subject'           => 'Appointment Cancelled - {{service_name}}',
                'body'              => self::get_default_cancellation_template(),
                'is_default'        => 1,
            ),
            array(
                'name'              => 'Appointment Rescheduled',
                'slug'              => 'appointment-rescheduled',
                'notification_type' => 'reschedule',
                'subject'           => 'Appointment Rescheduled - {{service_name}}',
                'body'              => self::get_default_reschedule_template(),
                'is_default'        => 1,
            ),
            array(
                'name'              => 'Payment Receipt',
                'slug'              => 'payment-receipt',
                'notification_type' => 'receipt',
                'subject'           => 'Payment Receipt - {{service_name}}',
                'body'              => self::get_default_receipt_template(),
                'is_default'        => 1,
            ),
            array(
                'name'              => 'Welcome Email',
                'slug'              => 'welcome-email',
                'notification_type' => 'welcome',
                'subject'           => 'Welcome to {{company_name}}!',
                'body'              => self::get_default_welcome_template(),
                'is_default'        => 1,
            ),
            array(
                'name'              => 'Follow-Up Email',
                'slug'              => 'follow-up',
                'notification_type' => 'follow_up',
                'subject'           => 'Thank You for Your Visit - {{company_name}}',
                'body'              => self::get_default_followup_template(),
                'is_default'        => 1,
            ),
            array(
                'name'              => 'Admin Notification',
                'slug'              => 'admin-notification',
                'notification_type' => 'admin_notice',
                'subject'           => 'New Booking: {{customer_name}} - {{service_name}}',
                'body'              => self::get_default_admin_template(),
                'is_default'        => 1,
            ),
        );

        foreach ( $templates as $template ) {
            $wpdb->insert( $tables['email_templates'], $template );
        }
    }

    /**
     * Default confirmation email template
     */
    private static function get_default_confirmation_template() {
        return '<!-- CONFIRMATION_TEMPLATE -->
<h2 style="color: #1e262d; margin: 0 0 20px;">Your Appointment is Confirmed!</h2>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Hi {{customer_first_name}},
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Great news! Your appointment has been confirmed. Here are the details:
</p>

<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #666; width: 120px;">Service:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{service_name}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Date:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{appointment_date}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Time:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{appointment_time}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Duration:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{service_duration}} minutes</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Provider:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{provider_name}}</td>
        </tr>
    </table>
</div>

{{custom_message}}

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    If you need to make any changes to your appointment, please contact us.
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    We look forward to seeing you!
</p>';
    }

    /**
     * Default reminder email template
     */
    private static function get_default_reminder_template() {
        return '<!-- REMINDER_TEMPLATE -->
<h2 style="color: #1e262d; margin: 0 0 20px;">Appointment Reminder</h2>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Hi {{customer_first_name}},
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    This is a friendly reminder about your upcoming appointment:
</p>

<div style="background: linear-gradient(135deg, #c16107 0%, #a85206 100%); border-radius: 8px; padding: 25px; margin: 25px 0; color: #fff;">
    <p style="margin: 0 0 10px; font-size: 18px; font-weight: 600;">{{service_name}}</p>
    <p style="margin: 0; font-size: 24px; font-weight: 700;">{{appointment_date}} at {{appointment_time}}</p>
</div>

<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #666; width: 120px;">Provider:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{provider_name}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Duration:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{service_duration}} minutes</td>
        </tr>
    </table>
</div>

{{custom_message}}

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Please arrive a few minutes early. If you need to reschedule, please contact us as soon as possible.
</p>';
    }

    /**
     * Default cancellation email template
     */
    private static function get_default_cancellation_template() {
        return '<!-- CANCELLATION_TEMPLATE -->
<h2 style="color: #1e262d; margin: 0 0 20px;">Appointment Cancelled</h2>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Hi {{customer_first_name}},
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Your appointment has been cancelled. Here were the details:
</p>

<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; margin: 25px 0; border-left: 4px solid #dc3545;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #666; width: 120px;">Service:</td>
            <td style="padding: 8px 0; color: #333; text-decoration: line-through;">{{service_name}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Date:</td>
            <td style="padding: 8px 0; color: #333; text-decoration: line-through;">{{appointment_date}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Time:</td>
            <td style="padding: 8px 0; color: #333; text-decoration: line-through;">{{appointment_time}}</td>
        </tr>
    </table>
</div>

{{custom_message}}

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    If you would like to reschedule, please visit our booking page or contact us directly.
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{booking_url}}" style="display: inline-block; background: #c16107; color: #fff; padding: 14px 30px; border-radius: 6px; text-decoration: none; font-weight: 600;">Book New Appointment</a>
</div>';
    }

    /**
     * Default reschedule email template
     */
    private static function get_default_reschedule_template() {
        return '<!-- RESCHEDULE_TEMPLATE -->
<h2 style="color: #1e262d; margin: 0 0 20px;">Appointment Rescheduled</h2>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Hi {{customer_first_name}},
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Your appointment has been rescheduled. Here are your new appointment details:
</p>

<div style="background: #d4edda; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <p style="margin: 0 0 5px; color: #155724; font-weight: 600;">NEW DATE & TIME:</p>
    <p style="margin: 0; font-size: 20px; color: #155724; font-weight: 700;">{{appointment_date}} at {{appointment_time}}</p>
</div>

<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #666; width: 120px;">Service:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{service_name}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Provider:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{provider_name}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Duration:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{service_duration}} minutes</td>
        </tr>
    </table>
</div>

{{custom_message}}

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Please make a note of this change. We look forward to seeing you!
</p>';
    }

    /**
     * Default receipt email template
     */
    private static function get_default_receipt_template() {
        return '<!-- RECEIPT_TEMPLATE -->
<h2 style="color: #1e262d; margin: 0 0 20px;">Payment Receipt</h2>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Hi {{customer_first_name}},
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Thank you for your payment. Here is your receipt:
</p>

<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; margin: 25px 0; border: 2px solid #ddd;">
    <div style="text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px dashed #ccc;">
        <p style="margin: 0; color: #666; font-size: 14px;">Receipt #{{payment_id}}</p>
        <p style="margin: 5px 0 0; color: #333; font-size: 12px;">{{payment_date}}</p>
    </div>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #333;">{{service_name}}</td>
            <td style="padding: 8px 0; color: #333; text-align: right; font-weight: 600;">{{service_price}}</td>
        </tr>
        <tr style="border-top: 2px solid #333;">
            <td style="padding: 12px 0; color: #333; font-weight: 700;">Total Paid</td>
            <td style="padding: 12px 0; color: #c16107; text-align: right; font-weight: 700; font-size: 18px;">{{payment_amount}}</td>
        </tr>
    </table>
</div>

{{custom_message}}

<p style="color: #666; font-size: 14px; line-height: 1.6;">
    Payment Method: {{payment_method}}<br>
    Transaction ID: {{transaction_id}}
</p>';
    }

    /**
     * Default welcome email template
     */
    private static function get_default_welcome_template() {
        return '<!-- WELCOME_TEMPLATE -->
<h2 style="color: #1e262d; margin: 0 0 20px;">Welcome to {{company_name}}!</h2>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Hi {{customer_first_name}},
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Welcome! We are thrilled to have you join us. Thank you for choosing {{company_name}} for your needs.
</p>

<div style="background: linear-gradient(135deg, #c16107 0%, #a85206 100%); border-radius: 8px; padding: 30px; margin: 25px 0; text-align: center; color: #fff;">
    <p style="margin: 0 0 15px; font-size: 18px;">Ready to book your first appointment?</p>
    <a href="{{booking_url}}" style="display: inline-block; background: #fff; color: #c16107; padding: 14px 30px; border-radius: 6px; text-decoration: none; font-weight: 600;">Book Now</a>
</div>

{{custom_message}}

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    If you have any questions, feel free to reach out. We are here to help!
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Best regards,<br>
    The {{company_name}} Team
</p>';
    }

    /**
     * Default follow-up email template
     */
    private static function get_default_followup_template() {
        return '<!-- FOLLOWUP_TEMPLATE -->
<h2 style="color: #1e262d; margin: 0 0 20px;">Thank You for Your Visit!</h2>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Hi {{customer_first_name}},
</p>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    Thank you for your recent visit with us! We hope you had a great experience.
</p>

<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <p style="margin: 0 0 10px; color: #666;">Your Recent Appointment:</p>
    <p style="margin: 0; font-weight: 600; color: #333;">{{service_name}}</p>
    <p style="margin: 5px 0 0; color: #666;">{{appointment_date}} with {{provider_name}}</p>
</div>

{{custom_message}}

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    We would love to see you again soon!
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{booking_url}}" style="display: inline-block; background: #c16107; color: #fff; padding: 14px 30px; border-radius: 6px; text-decoration: none; font-weight: 600;">Book Another Appointment</a>
</div>';
    }

    /**
     * Default admin notification template
     */
    private static function get_default_admin_template() {
        return '<!-- ADMIN_TEMPLATE -->
<h2 style="color: #1e262d; margin: 0 0 20px;">New Booking Received</h2>

<p style="color: #333; font-size: 16px; line-height: 1.6;">
    A new appointment has been booked. Here are the details:
</p>

<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <h3 style="margin: 0 0 15px; color: #c16107;">Customer Information</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #666; width: 120px;">Name:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{customer_name}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Email:</td>
            <td style="padding: 8px 0; color: #333;">{{customer_email}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Phone:</td>
            <td style="padding: 8px 0; color: #333;">{{customer_phone}}</td>
        </tr>
    </table>
</div>

<div style="background: #fff3cd; border-radius: 8px; padding: 20px; margin: 25px 0; border-left: 4px solid #ffc107;">
    <h3 style="margin: 0 0 15px; color: #856404;">Appointment Details</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #666; width: 120px;">Service:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{service_name}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Date:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{appointment_date}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Time:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{appointment_time}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Provider:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{provider_name}}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Price:</td>
            <td style="padding: 8px 0; color: #333; font-weight: 600;">{{service_price}}</td>
        </tr>
    </table>
</div>

{{custom_message}}

<div style="text-align: center; margin: 30px 0;">
    <a href="{{admin_url}}" style="display: inline-block; background: #c16107; color: #fff; padding: 14px 30px; border-radius: 6px; text-decoration: none; font-weight: 600;">View in Dashboard</a>
</div>';
    }

    /**
     * Drop all tables (use with caution!)
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = self::get_table_names();

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
        }

        delete_option( 'guidepost_db_version' );
    }

    /**
     * Check if tables exist
     *
     * @return bool
     */
    public static function tables_exist() {
        global $wpdb;

        $tables = self::get_table_names();

        foreach ( $tables as $table ) {
            $result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $result !== $table ) {
                return false;
            }
        }

        return true;
    }
}
