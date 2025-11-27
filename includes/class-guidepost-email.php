<?php
/**
 * Email handler with SMTP support
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Email class - handles all email sending with SMTP/IMAP support
 */
class GuidePost_Email {

    /**
     * SMTP settings
     *
     * @var array
     */
    private static $smtp_settings = null;

    /**
     * Clear settings cache
     */
    public static function clear_settings_cache() {
        self::$smtp_settings = null;
    }

    /**
     * Get SMTP settings
     *
     * @return array
     */
    public static function get_smtp_settings() {
        if ( null === self::$smtp_settings ) {
            self::$smtp_settings = array(
                'enabled'     => get_option( 'guidepost_smtp_enabled', false ),
                'host'        => get_option( 'guidepost_smtp_host', '' ),
                'port'        => get_option( 'guidepost_smtp_port', 587 ),
                'encryption'  => get_option( 'guidepost_smtp_encryption', 'tls' ),
                'auth'        => get_option( 'guidepost_smtp_auth', true ),
                'username'    => get_option( 'guidepost_smtp_username', '' ),
                'password'    => get_option( 'guidepost_smtp_password', '' ),
                'from_email'  => get_option( 'guidepost_smtp_from_email', get_option( 'admin_email' ) ),
                'from_name'   => get_option( 'guidepost_smtp_from_name', get_bloginfo( 'name' ) ),
            );
        }
        return self::$smtp_settings;
    }

    /**
     * Configure PHPMailer for SMTP
     *
     * @param PHPMailer $phpmailer PHPMailer instance.
     */
    public static function configure_smtp( $phpmailer ) {
        $settings = self::get_smtp_settings();

        if ( ! $settings['enabled'] ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $settings['host'];
        $phpmailer->Port       = $settings['port'];
        $phpmailer->SMTPSecure = $settings['encryption'];
        $phpmailer->SMTPAuth   = $settings['auth'];

        if ( $settings['auth'] ) {
            $phpmailer->Username = $settings['username'];
            $phpmailer->Password = $settings['password'];
        }

        $phpmailer->From     = $settings['from_email'];
        $phpmailer->FromName = $settings['from_name'];
    }

    /**
     * Send email
     *
     * @param array $args Email arguments.
     * @return bool|WP_Error
     */
    public static function send( $args ) {
        $defaults = array(
            'to'              => '',
            'to_name'         => '',
            'subject'         => '',
            'body'            => '',
            'custom_message'  => '',
            'template_id'     => null,
            'notification_type' => 'custom',
            'appointment_id'  => null,
            'customer_id'     => null,
            'variables'       => array(),
            'log'             => true,
        );

        $args = wp_parse_args( $args, $defaults );

        // Validate required fields
        if ( empty( $args['to'] ) || empty( $args['subject'] ) ) {
            return new WP_Error( 'missing_data', __( 'Email address and subject are required.', 'guidepost' ) );
        }

        // Get template if specified
        if ( $args['template_id'] ) {
            $template = self::get_template( $args['template_id'] );
            if ( $template ) {
                $args['subject'] = $template->subject;
                $args['body']    = $template->body;
                $args['notification_type'] = $template->notification_type;
            }
        }

        // Build variables array
        $variables = self::get_default_variables();
        $variables = array_merge( $variables, $args['variables'] );

        // Add custom message to variables
        if ( ! empty( $args['custom_message'] ) ) {
            $variables['custom_message'] = '<div style="background: #e8f4f8; border-left: 4px solid #c16107; padding: 15px 20px; margin: 25px 0; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; color: #333; font-size: 16px; line-height: 1.6; font-style: italic;">' . nl2br( esc_html( $args['custom_message'] ) ) . '</p>
            </div>';
        } else {
            $variables['custom_message'] = '';
        }

        // Replace variables in subject and body
        $subject = self::replace_variables( $args['subject'], $variables );
        $body    = self::replace_variables( $args['body'], $variables );

        // Wrap body in branded HTML template
        $html_body = self::wrap_in_template( $body, $subject );

        // Configure SMTP if enabled
        $settings = self::get_smtp_settings();
        if ( $settings['enabled'] ) {
            add_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );
        }

        // Set content type
        add_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );

        // Set from headers
        $headers = array();
        if ( ! empty( $settings['from_name'] ) && ! empty( $settings['from_email'] ) ) {
            $headers[] = 'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>';
        }
        $headers[] = 'Reply-To: ' . $settings['from_email'];

        // Send email
        $sent = wp_mail( $args['to'], $subject, $html_body, $headers );

        // Remove filters
        remove_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );
        if ( $settings['enabled'] ) {
            remove_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );
        }

        // Log the email
        if ( $args['log'] ) {
            self::log_email( array(
                'appointment_id'    => $args['appointment_id'],
                'customer_id'       => $args['customer_id'],
                'template_id'       => $args['template_id'],
                'recipient_email'   => $args['to'],
                'recipient_name'    => $args['to_name'],
                'notification_type' => $args['notification_type'],
                'subject'           => $subject,
                'body'              => $html_body,
                'custom_message'    => $args['custom_message'],
                'status'            => $sent ? 'sent' : 'failed',
                'error_message'     => $sent ? null : __( 'wp_mail() returned false', 'guidepost' ),
            ) );
        }

        if ( ! $sent ) {
            return new WP_Error( 'send_failed', __( 'Failed to send email.', 'guidepost' ) );
        }

        return true;
    }

    /**
     * Set HTML content type
     *
     * @return string
     */
    public static function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Get template by ID
     *
     * @param int $template_id Template ID.
     * @return object|null
     */
    public static function get_template( $template_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['email_templates']} WHERE id = %d",
            $template_id
        ) );
    }

    /**
     * Get template by slug
     *
     * @param string $slug Template slug.
     * @return object|null
     */
    public static function get_template_by_slug( $slug ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['email_templates']} WHERE slug = %s",
            $slug
        ) );
    }

    /**
     * Get default template by notification type
     *
     * @param string $type Notification type.
     * @return object|null
     */
    public static function get_default_template( $type ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['email_templates']} WHERE notification_type = %s AND is_default = 1",
            $type
        ) );
    }

    /**
     * Get all templates
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_templates( $args = array() ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $where = array( '1=1' );
        $values = array();

        if ( isset( $args['is_active'] ) ) {
            $where[] = 'is_active = %d';
            $values[] = $args['is_active'] ? 1 : 0;
        }

        if ( isset( $args['notification_type'] ) ) {
            $where[] = 'notification_type = %s';
            $values[] = $args['notification_type'];
        }

        $where_clause = implode( ' AND ', $where );
        $query = "SELECT * FROM {$tables['email_templates']} WHERE {$where_clause} ORDER BY name ASC";

        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( $query, $values );
        }

        return $wpdb->get_results( $query );
    }

    /**
     * Get default variables
     *
     * @return array
     */
    public static function get_default_variables() {
        return array(
            'company_name'   => get_bloginfo( 'name' ),
            'company_email'  => get_option( 'guidepost_admin_email', get_option( 'admin_email' ) ),
            'site_url'       => home_url(),
            'booking_url'    => home_url( '/book' ),
            'admin_url'      => admin_url( 'admin.php?page=guidepost-appointments' ),
            'current_year'   => date( 'Y' ),
            'current_date'   => date_i18n( get_option( 'date_format' ) ),
        );
    }

    /**
     * Build variables from appointment
     *
     * @param int $appointment_id Appointment ID.
     * @return array
     */
    public static function get_appointment_variables( $appointment_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*,
                    s.name AS service_name, s.duration AS service_duration, s.price AS service_price,
                    p.name AS provider_name, p.email AS provider_email,
                    c.first_name AS customer_first_name, c.last_name AS customer_last_name,
                    c.email AS customer_email, c.phone AS customer_phone
             FROM {$tables['appointments']} a
             LEFT JOIN {$tables['services']} s ON a.service_id = s.id
             LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
             LEFT JOIN {$tables['customers']} c ON a.customer_id = c.id
             WHERE a.id = %d",
            $appointment_id
        ) );

        if ( ! $appointment ) {
            return array();
        }

        $price_formatted = function_exists( 'wc_price' )
            ? strip_tags( wc_price( $appointment->service_price ) )
            : '$' . number_format( $appointment->service_price, 2 );

        return array(
            'appointment_id'      => $appointment->id,
            'appointment_date'    => date_i18n( get_option( 'date_format' ), strtotime( $appointment->booking_date ) ),
            'appointment_time'    => date_i18n( get_option( 'time_format' ), strtotime( $appointment->booking_time ) ),
            'appointment_end_time' => date_i18n( get_option( 'time_format' ), strtotime( $appointment->end_time ) ),
            'appointment_status'  => ucfirst( $appointment->status ),
            'service_name'        => $appointment->service_name,
            'service_duration'    => $appointment->service_duration,
            'service_price'       => $price_formatted,
            'provider_name'       => $appointment->provider_name,
            'provider_email'      => $appointment->provider_email,
            'customer_first_name' => $appointment->customer_first_name,
            'customer_last_name'  => $appointment->customer_last_name,
            'customer_name'       => $appointment->customer_first_name . ' ' . $appointment->customer_last_name,
            'customer_email'      => $appointment->customer_email,
            'customer_phone'      => $appointment->customer_phone ?: 'N/A',
        );
    }

    /**
     * Build variables from customer
     *
     * @param int $customer_id Customer ID.
     * @return array
     */
    public static function get_customer_variables( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['customers']} WHERE id = %d",
            $customer_id
        ) );

        if ( ! $customer ) {
            return array();
        }

        return array(
            'customer_first_name' => $customer->first_name,
            'customer_last_name'  => $customer->last_name,
            'customer_name'       => $customer->first_name . ' ' . $customer->last_name,
            'customer_email'      => $customer->email,
            'customer_phone'      => $customer->phone ?: 'N/A',
        );
    }

    /**
     * Replace variables in text
     *
     * @param string $text Text with placeholders.
     * @param array  $variables Variables to replace.
     * @return string
     */
    public static function replace_variables( $text, $variables ) {
        foreach ( $variables as $key => $value ) {
            $text = str_replace( '{{' . $key . '}}', $value, $text );
        }
        return $text;
    }

    /**
     * Wrap content in branded HTML template
     *
     * @param string $content Email content.
     * @param string $subject Email subject.
     * @return string
     */
    public static function wrap_in_template( $content, $subject = '' ) {
        $company_name = get_bloginfo( 'name' );
        $site_url     = home_url();
        $year         = date( 'Y' );
        $logo_url     = get_option( 'guidepost_email_logo', '' );

        // Logo section
        $logo_html = '';
        if ( $logo_url ) {
            $logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $company_name ) . '" style="max-width: 180px; height: auto;">';
        } else {
            $logo_html = '<span style="font-family: \'Crimson Text\', Georgia, serif; font-size: 28px; font-weight: 700; color: #c16107;">' . esc_html( $company_name ) . '</span>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>' . esc_html( $subject ) . '</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: \'Nunito Sans\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, sans-serif;">
    <!-- Preheader text (hidden) -->
    <div style="display: none; max-height: 0; overflow: hidden;">
        ' . wp_strip_all_tags( substr( $content, 0, 150 ) ) . '...
    </div>

    <!-- Main wrapper -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 20px;">

                <!-- Email container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">

                    <!-- Header with gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e262d 0%, #2d3740 100%); padding: 30px 40px; text-align: center;">
                            ' . $logo_html . '
                        </td>
                    </tr>

                    <!-- Accent bar -->
                    <tr>
                        <td style="height: 4px; background: linear-gradient(90deg, #c16107 0%, #c18f5f 50%, #95c93d 100%);"></td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            ' . $content . '
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: #f8f8f8; padding: 30px 40px; border-top: 1px solid #eee;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="margin: 0 0 10px; color: #666; font-size: 14px;">
                                            ' . esc_html( $company_name ) . '
                                        </p>
                                        <p style="margin: 0 0 15px; color: #999; font-size: 12px;">
                                            <a href="' . esc_url( $site_url ) . '" style="color: #c16107; text-decoration: none;">Visit our website</a>
                                        </p>
                                        <p style="margin: 0; color: #999; font-size: 11px;">
                                            &copy; ' . esc_html( $year ) . ' ' . esc_html( $company_name ) . '. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
                <!-- /Email container -->

                <!-- Unsubscribe note -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td style="padding: 20px 40px; text-align: center;">
                            <p style="margin: 0; color: #999; font-size: 11px;">
                                This email was sent by ' . esc_html( $company_name ) . '.
                                If you have questions, please contact us.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Log email to database
     *
     * @param array $data Email data.
     * @return int|false Insert ID or false on failure.
     */
    public static function log_email( $data ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $insert_data = array(
            'appointment_id'    => $data['appointment_id'],
            'customer_id'       => $data['customer_id'],
            'template_id'       => $data['template_id'],
            'recipient_email'   => $data['recipient_email'],
            'recipient_name'    => $data['recipient_name'],
            'notification_type' => $data['notification_type'],
            'subject'           => $data['subject'],
            'body'              => $data['body'],
            'custom_message'    => $data['custom_message'],
            'sent_by'           => get_current_user_id(),
            'status'            => $data['status'],
            'error_message'     => isset( $data['error_message'] ) ? $data['error_message'] : null,
            'sent_at'           => 'sent' === $data['status'] ? current_time( 'mysql' ) : null,
        );

        $result = $wpdb->insert( $tables['notifications'], $insert_data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get email log
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_email_log( $args = array() ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $defaults = array(
            'customer_id'       => null,
            'appointment_id'    => null,
            'notification_type' => null,
            'status'            => null,
            'limit'             => 50,
            'offset'            => 0,
            'orderby'           => 'created_at',
            'order'             => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $values = array();

        if ( $args['customer_id'] ) {
            $where[] = 'n.customer_id = %d';
            $values[] = $args['customer_id'];
        }

        if ( $args['appointment_id'] ) {
            $where[] = 'n.appointment_id = %d';
            $values[] = $args['appointment_id'];
        }

        if ( $args['notification_type'] ) {
            $where[] = 'n.notification_type = %s';
            $values[] = $args['notification_type'];
        }

        if ( $args['status'] ) {
            $where[] = 'n.status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] ) ?: 'created_at DESC';

        $query = "SELECT n.*,
                         c.first_name AS customer_first_name, c.last_name AS customer_last_name,
                         t.name AS template_name,
                         u.display_name AS sent_by_name
                  FROM {$tables['notifications']} n
                  LEFT JOIN {$tables['customers']} c ON n.customer_id = c.id
                  LEFT JOIN {$tables['email_templates']} t ON n.template_id = t.id
                  LEFT JOIN {$wpdb->users} u ON n.sent_by = u.ID
                  WHERE {$where_clause}
                  ORDER BY {$orderby}
                  LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        return $wpdb->get_results( $wpdb->prepare( $query, $values ) );
    }

    /**
     * Get email log count
     *
     * @param array $args Query arguments.
     * @return int
     */
    public static function get_email_log_count( $args = array() ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $where = array( '1=1' );
        $values = array();

        if ( isset( $args['customer_id'] ) && $args['customer_id'] ) {
            $where[] = 'customer_id = %d';
            $values[] = $args['customer_id'];
        }

        if ( isset( $args['status'] ) && $args['status'] ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );
        $query = "SELECT COUNT(*) FROM {$tables['notifications']} WHERE {$where_clause}";

        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( $query, $values );
        }

        return (int) $wpdb->get_var( $query );
    }

    /**
     * Send appointment confirmation
     *
     * @param int    $appointment_id Appointment ID.
     * @param string $custom_message Optional custom message.
     * @return bool|WP_Error
     */
    public static function send_confirmation( $appointment_id, $custom_message = '' ) {
        $template  = self::get_default_template( 'confirmation' );
        $variables = array_merge(
            self::get_default_variables(),
            self::get_appointment_variables( $appointment_id )
        );

        if ( ! isset( $variables['customer_email'] ) ) {
            return new WP_Error( 'no_customer', __( 'No customer found for this appointment.', 'guidepost' ) );
        }

        return self::send( array(
            'to'              => $variables['customer_email'],
            'to_name'         => $variables['customer_name'],
            'subject'         => $template->subject,
            'body'            => $template->body,
            'custom_message'  => $custom_message,
            'template_id'     => $template->id,
            'notification_type' => 'confirmation',
            'appointment_id'  => $appointment_id,
            'customer_id'     => null, // Will be fetched from appointment
            'variables'       => $variables,
        ) );
    }

    /**
     * Send appointment reminder
     *
     * @param int    $appointment_id Appointment ID.
     * @param string $custom_message Optional custom message.
     * @return bool|WP_Error
     */
    public static function send_reminder( $appointment_id, $custom_message = '' ) {
        $template  = self::get_default_template( 'reminder' );
        $variables = array_merge(
            self::get_default_variables(),
            self::get_appointment_variables( $appointment_id )
        );

        if ( ! isset( $variables['customer_email'] ) ) {
            return new WP_Error( 'no_customer', __( 'No customer found for this appointment.', 'guidepost' ) );
        }

        return self::send( array(
            'to'              => $variables['customer_email'],
            'to_name'         => $variables['customer_name'],
            'subject'         => $template->subject,
            'body'            => $template->body,
            'custom_message'  => $custom_message,
            'template_id'     => $template->id,
            'notification_type' => 'reminder',
            'appointment_id'  => $appointment_id,
            'variables'       => $variables,
        ) );
    }

    /**
     * Send cancellation notice
     *
     * @param int    $appointment_id Appointment ID.
     * @param string $custom_message Optional custom message.
     * @return bool|WP_Error
     */
    public static function send_cancellation( $appointment_id, $custom_message = '' ) {
        $template  = self::get_default_template( 'cancellation' );
        $variables = array_merge(
            self::get_default_variables(),
            self::get_appointment_variables( $appointment_id )
        );

        if ( ! isset( $variables['customer_email'] ) ) {
            return new WP_Error( 'no_customer', __( 'No customer found for this appointment.', 'guidepost' ) );
        }

        return self::send( array(
            'to'              => $variables['customer_email'],
            'to_name'         => $variables['customer_name'],
            'subject'         => $template->subject,
            'body'            => $template->body,
            'custom_message'  => $custom_message,
            'template_id'     => $template->id,
            'notification_type' => 'cancellation',
            'appointment_id'  => $appointment_id,
            'variables'       => $variables,
        ) );
    }

    /**
     * Send admin notification for new booking
     *
     * @param int $appointment_id Appointment ID.
     * @return bool|WP_Error
     */
    public static function send_admin_notification( $appointment_id ) {
        $template  = self::get_default_template( 'admin_notice' );
        $variables = array_merge(
            self::get_default_variables(),
            self::get_appointment_variables( $appointment_id )
        );

        $admin_email = get_option( 'guidepost_admin_email', get_option( 'admin_email' ) );

        return self::send( array(
            'to'              => $admin_email,
            'to_name'         => 'Admin',
            'subject'         => $template->subject,
            'body'            => $template->body,
            'template_id'     => $template->id,
            'notification_type' => 'admin_notice',
            'appointment_id'  => $appointment_id,
            'variables'       => $variables,
        ) );
    }

    /**
     * Test SMTP connection
     *
     * @return bool|WP_Error
     */
    public static function test_smtp_connection() {
        $settings = self::get_smtp_settings();

        if ( ! $settings['enabled'] ) {
            return new WP_Error( 'smtp_disabled', __( 'SMTP is not enabled.', 'guidepost' ) );
        }

        // Send test email
        $result = self::send( array(
            'to'              => $settings['from_email'],
            'subject'         => __( 'GuidePost SMTP Test', 'guidepost' ),
            'body'            => '<p>' . __( 'This is a test email from GuidePost. If you received this, your SMTP settings are configured correctly.', 'guidepost' ) . '</p>',
            'notification_type' => 'custom',
            'log'             => false,
        ) );

        return $result;
    }

    /**
     * Get available template variables
     *
     * @return array
     */
    public static function get_available_variables() {
        return array(
            'General' => array(
                '{{company_name}}'   => __( 'Company/Site name', 'guidepost' ),
                '{{company_email}}'  => __( 'Admin email address', 'guidepost' ),
                '{{site_url}}'       => __( 'Website URL', 'guidepost' ),
                '{{booking_url}}'    => __( 'Booking page URL', 'guidepost' ),
                '{{admin_url}}'      => __( 'Admin dashboard URL', 'guidepost' ),
                '{{current_year}}'   => __( 'Current year', 'guidepost' ),
                '{{current_date}}'   => __( 'Current date', 'guidepost' ),
                '{{custom_message}}' => __( 'Custom message block', 'guidepost' ),
            ),
            'Customer' => array(
                '{{customer_first_name}}' => __( 'Customer first name', 'guidepost' ),
                '{{customer_last_name}}'  => __( 'Customer last name', 'guidepost' ),
                '{{customer_name}}'       => __( 'Customer full name', 'guidepost' ),
                '{{customer_email}}'      => __( 'Customer email', 'guidepost' ),
                '{{customer_phone}}'      => __( 'Customer phone', 'guidepost' ),
            ),
            'Appointment' => array(
                '{{appointment_id}}'       => __( 'Appointment ID', 'guidepost' ),
                '{{appointment_date}}'     => __( 'Appointment date', 'guidepost' ),
                '{{appointment_time}}'     => __( 'Appointment time', 'guidepost' ),
                '{{appointment_end_time}}' => __( 'Appointment end time', 'guidepost' ),
                '{{appointment_status}}'   => __( 'Appointment status', 'guidepost' ),
            ),
            'Service' => array(
                '{{service_name}}'     => __( 'Service name', 'guidepost' ),
                '{{service_duration}}' => __( 'Service duration (minutes)', 'guidepost' ),
                '{{service_price}}'    => __( 'Service price', 'guidepost' ),
            ),
            'Provider' => array(
                '{{provider_name}}'  => __( 'Provider name', 'guidepost' ),
                '{{provider_email}}' => __( 'Provider email', 'guidepost' ),
            ),
            'Payment' => array(
                '{{payment_id}}'     => __( 'Payment ID', 'guidepost' ),
                '{{payment_amount}}' => __( 'Payment amount', 'guidepost' ),
                '{{payment_method}}' => __( 'Payment method', 'guidepost' ),
                '{{payment_date}}'   => __( 'Payment date', 'guidepost' ),
                '{{transaction_id}}' => __( 'Transaction ID', 'guidepost' ),
            ),
        );
    }
}
