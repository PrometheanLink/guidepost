<?php
/**
 * Customer Helper Methods - Data Access Utilities
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Customer Helpers class - stateless utility methods for data access
 */
class GuidePost_Customer_Helpers {

    /**
     * Get customer by ID
     *
     * @param int $customer_id Customer ID.
     * @return object|null
     */
    public static function get_customer( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['customers']} WHERE id = %d",
            $customer_id
        ) );
    }

    /**
     * Get customer appointments
     *
     * @param int $customer_id Customer ID.
     * @param int $limit       Limit.
     * @param int $offset      Offset.
     * @return array
     */
    public static function get_customer_appointments( $customer_id, $limit = 100, $offset = 0 ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*, s.name AS service_name, s.color AS service_color, p.name AS provider_name
             FROM {$tables['appointments']} a
             LEFT JOIN {$tables['services']} s ON a.service_id = s.id
             LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
             WHERE a.customer_id = %d
             ORDER BY a.booking_date DESC, a.booking_time DESC
             LIMIT %d OFFSET %d",
            $customer_id,
            $limit,
            $offset
        ) );
    }

    /**
     * Get customer appointments count
     *
     * @param int $customer_id Customer ID.
     * @return int
     */
    public static function get_customer_appointments_count( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['appointments']} WHERE customer_id = %d",
            $customer_id
        ) );
    }

    /**
     * Get customer purchases
     *
     * @param int $customer_id Customer ID.
     * @return array
     */
    public static function get_customer_purchases( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tables['customer_purchases']}
             WHERE customer_id = %d
             ORDER BY purchase_date DESC",
            $customer_id
        ) );
    }

    /**
     * Get customer documents
     *
     * @param int $customer_id Customer ID.
     * @return array
     */
    public static function get_customer_documents( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tables['customer_documents']}
             WHERE customer_id = %d
             ORDER BY created_at DESC",
            $customer_id
        ) );
    }

    /**
     * Get customer notes
     *
     * @param int   $customer_id Customer ID.
     * @param array $args        Arguments.
     * @return array
     */
    public static function get_customer_notes( $customer_id, $args = array() ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $defaults = array(
            'is_pinned' => null,
            'limit'     => 100,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( 'n.customer_id = %d' );
        $values = array( $customer_id );

        if ( null !== $args['is_pinned'] ) {
            $where[] = 'n.is_pinned = %d';
            $values[] = $args['is_pinned'];
        }

        $where_clause = implode( ' AND ', $where );
        $values[] = $args['limit'];

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT n.*, u.display_name AS author_name
             FROM {$tables['customer_notes']} n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE {$where_clause}
             ORDER BY n.is_pinned DESC, n.created_at DESC
             LIMIT %d",
            $values
        ) );
    }

    /**
     * Get customer flags
     *
     * @param int  $customer_id Customer ID.
     * @param bool $active_only Active only.
     * @return array
     */
    public static function get_customer_flags( $customer_id, $active_only = false ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $where = 'customer_id = %d';
        if ( $active_only ) {
            $where .= ' AND is_active = 1 AND is_dismissed = 0';
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tables['customer_flags']}
             WHERE {$where}
             ORDER BY trigger_date ASC, created_at DESC",
            $customer_id
        ) );
    }

    /**
     * Get credit history
     *
     * @param int $customer_id Customer ID.
     * @return array
     */
    public static function get_credit_history( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tables['credit_history']}
             WHERE customer_id = %d
             ORDER BY created_at DESC
             LIMIT 50",
            $customer_id
        ) );
    }

    /**
     * Get timeline events
     *
     * @param object $customer Customer object.
     * @return array
     */
    public static function get_timeline_events( $customer ) {
        $events = array();

        // First contact
        if ( $customer->first_contact_date ) {
            $events[] = array(
                'date'  => date_i18n( 'M j, Y', strtotime( $customer->first_contact_date ) ),
                'label' => __( 'First Contact', 'guidepost' ),
                'type'  => 'first_contact',
            );
        }

        // First purchase
        $first_purchase = self::get_first_purchase( $customer->id );
        if ( $first_purchase ) {
            $events[] = array(
                'date'  => date_i18n( 'M j, Y', strtotime( $first_purchase->purchase_date ) ),
                'label' => __( 'First Purchase', 'guidepost' ),
                'type'  => 'first_purchase',
            );
        }

        // First appointment
        $first_appointment = self::get_first_appointment( $customer->id );
        if ( $first_appointment ) {
            $events[] = array(
                'date'  => date_i18n( 'M j, Y', strtotime( $first_appointment->booking_date ) ),
                'label' => __( 'First Appointment', 'guidepost' ),
                'type'  => 'first_appointment',
            );
        }

        // Latest service
        if ( $customer->last_booking_date ) {
            $events[] = array(
                'date'  => date_i18n( 'M j, Y', strtotime( $customer->last_booking_date ) ),
                'label' => __( 'Latest Service', 'guidepost' ),
                'type'  => 'latest_service',
            );
        }

        // Next appointment
        if ( $customer->next_booking_date && strtotime( $customer->next_booking_date ) >= strtotime( 'today' ) ) {
            $events[] = array(
                'date'  => date_i18n( 'M j, Y', strtotime( $customer->next_booking_date ) ),
                'label' => __( 'Next Appointment', 'guidepost' ),
                'type'  => 'next_appointment',
            );
        }

        return $events;
    }

    /**
     * Get first purchase
     *
     * @param int $customer_id Customer ID.
     * @return object|null
     */
    public static function get_first_purchase( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['customer_purchases']}
             WHERE customer_id = %d
             ORDER BY purchase_date ASC
             LIMIT 1",
            $customer_id
        ) );
    }

    /**
     * Get first appointment
     *
     * @param int $customer_id Customer ID.
     * @return object|null
     */
    public static function get_first_appointment( $customer_id ) {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['appointments']}
             WHERE customer_id = %d
             ORDER BY booking_date ASC
             LIMIT 1",
            $customer_id
        ) );
    }

    /**
     * Get active flags count
     *
     * @return int
     */
    public static function get_active_flags_count() {
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables['customer_flags'] ) );
        if ( ! $table_exists ) {
            return 0;
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['customer_flags']}
             WHERE is_active = 1 AND is_dismissed = 0
             AND (trigger_date IS NULL OR trigger_date <= CURDATE())"
        );
    }

    /**
     * Get status color
     *
     * @param string $status Status.
     * @return string
     */
    public static function get_status_color( $status ) {
        $colors = array(
            'active'   => '#95c93d',
            'vip'      => '#c16107',
            'paused'   => '#ffc107',
            'inactive' => '#6c757d',
            'prospect' => '#17a2b8',
        );
        return isset( $colors[ $status ] ) ? $colors[ $status ] : '#6c757d';
    }

    /**
     * Get flag icon
     *
     * @param string $flag_type Flag type.
     * @return string
     */
    public static function get_flag_icon( $flag_type ) {
        $icons = array(
            'follow_up'   => 'dashicons-phone',
            'inactive'    => 'dashicons-warning',
            'birthday'    => 'dashicons-cake',
            'vip_check'   => 'dashicons-star-filled',
            'payment_due' => 'dashicons-money-alt',
            'custom'      => 'dashicons-flag',
        );
        return isset( $icons[ $flag_type ] ) ? $icons[ $flag_type ] : 'dashicons-flag';
    }

    /**
     * Get file icon
     *
     * @param string $file_type File type.
     * @return string
     */
    public static function get_file_icon( $file_type ) {
        if ( strpos( $file_type, 'image' ) !== false ) {
            return '🖼️';
        } elseif ( strpos( $file_type, 'pdf' ) !== false ) {
            return '📄';
        } elseif ( strpos( $file_type, 'word' ) !== false || strpos( $file_type, 'document' ) !== false ) {
            return '📝';
        } elseif ( strpos( $file_type, 'sheet' ) !== false || strpos( $file_type, 'excel' ) !== false ) {
            return '📊';
        } elseif ( strpos( $file_type, 'video' ) !== false ) {
            return '🎬';
        } else {
            return '📎';
        }
    }

    /**
     * Format file size
     *
     * @param int $bytes Bytes.
     * @return string
     */
    public static function format_file_size( $bytes ) {
        if ( $bytes >= 1048576 ) {
            return round( $bytes / 1048576, 2 ) . ' MB';
        } elseif ( $bytes >= 1024 ) {
            return round( $bytes / 1024, 2 ) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
