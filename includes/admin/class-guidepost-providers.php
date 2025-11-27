<?php
/**
 * Providers Admin Management
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Providers Admin class
 */
class GuidePost_Providers {

    /**
     * Get all providers
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_providers( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'  => '',
            'orderby' => 'name',
            'order'   => 'ASC',
        );

        $args   = wp_parse_args( $args, $defaults );
        $tables = GuidePost_Database::get_table_names();

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );
        $order_clause = sprintf( 'ORDER BY %s %s', esc_sql( $args['orderby'] ), esc_sql( $args['order'] ) );

        $query = "SELECT * FROM {$tables['providers']} WHERE {$where_clause} {$order_clause}";

        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( $query, $values );
        }

        return $wpdb->get_results( $query );
    }

    /**
     * Get single provider
     *
     * @param int $id Provider ID.
     * @return object|null
     */
    public static function get_provider( $id ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tables['providers']} WHERE id = %d", $id )
        );
    }

    /**
     * Create provider
     *
     * @param array $data Provider data.
     * @return int|WP_Error Provider ID or error.
     */
    public static function create_provider( $data ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        $defaults = array(
            'name'     => '',
            'email'    => '',
            'phone'    => '',
            'bio'      => '',
            'timezone' => 'America/New_York',
            'status'   => 'active',
            'services' => array(),
            'working_hours' => array(),
        );

        $data = wp_parse_args( $data, $defaults );

        // Validate required fields
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'missing_name', __( 'Provider name is required.', 'guidepost' ) );
        }

        if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
            return new WP_Error( 'invalid_email', __( 'Valid email is required.', 'guidepost' ) );
        }

        // Check for duplicate email
        $existing = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$tables['providers']} WHERE email = %s", $data['email'] )
        );

        if ( $existing ) {
            return new WP_Error( 'duplicate_email', __( 'A provider with this email already exists.', 'guidepost' ) );
        }

        $result = $wpdb->insert(
            $tables['providers'],
            array(
                'name'     => sanitize_text_field( $data['name'] ),
                'email'    => sanitize_email( $data['email'] ),
                'phone'    => sanitize_text_field( $data['phone'] ),
                'bio'      => sanitize_textarea_field( $data['bio'] ),
                'timezone' => sanitize_text_field( $data['timezone'] ),
                'status'   => in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to create provider.', 'guidepost' ) );
        }

        $provider_id = $wpdb->insert_id;

        // Save service assignments
        if ( ! empty( $data['services'] ) ) {
            self::save_provider_services( $provider_id, $data['services'] );
        }

        // Save working hours
        if ( ! empty( $data['working_hours'] ) ) {
            self::save_working_hours( $provider_id, $data['working_hours'] );
        } else {
            // Set default working hours (Mon-Fri 9-5)
            self::set_default_working_hours( $provider_id );
        }

        return $provider_id;
    }

    /**
     * Update provider
     *
     * @param int   $id   Provider ID.
     * @param array $data Provider data.
     * @return bool|WP_Error
     */
    public static function update_provider( $id, $data ) {
        global $wpdb;

        $tables   = GuidePost_Database::get_table_names();
        $provider = self::get_provider( $id );

        if ( ! $provider ) {
            return new WP_Error( 'not_found', __( 'Provider not found.', 'guidepost' ) );
        }

        $update_data   = array();
        $update_format = array();

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            $update_format[]     = '%s';
        }

        if ( isset( $data['email'] ) ) {
            // Check for duplicate email
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tables['providers']} WHERE email = %s AND id != %d",
                    $data['email'],
                    $id
                )
            );

            if ( $existing ) {
                return new WP_Error( 'duplicate_email', __( 'A provider with this email already exists.', 'guidepost' ) );
            }

            $update_data['email'] = sanitize_email( $data['email'] );
            $update_format[]      = '%s';
        }

        if ( isset( $data['phone'] ) ) {
            $update_data['phone'] = sanitize_text_field( $data['phone'] );
            $update_format[]      = '%s';
        }

        if ( isset( $data['bio'] ) ) {
            $update_data['bio'] = sanitize_textarea_field( $data['bio'] );
            $update_format[]    = '%s';
        }

        if ( isset( $data['timezone'] ) ) {
            $update_data['timezone'] = sanitize_text_field( $data['timezone'] );
            $update_format[]         = '%s';
        }

        if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'inactive' ), true ) ) {
            $update_data['status'] = $data['status'];
            $update_format[]       = '%s';
        }

        if ( ! empty( $update_data ) ) {
            $result = $wpdb->update(
                $tables['providers'],
                $update_data,
                array( 'id' => $id ),
                $update_format,
                array( '%d' )
            );

            if ( false === $result ) {
                return new WP_Error( 'db_error', __( 'Failed to update provider.', 'guidepost' ) );
            }
        }

        // Update service assignments
        if ( isset( $data['services'] ) ) {
            self::save_provider_services( $id, $data['services'] );
        }

        // Update working hours
        if ( isset( $data['working_hours'] ) ) {
            self::save_working_hours( $id, $data['working_hours'] );
        }

        return true;
    }

    /**
     * Delete provider
     *
     * @param int $id Provider ID.
     * @return bool|WP_Error
     */
    public static function delete_provider( $id ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        // Check for existing appointments
        $appointments = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['appointments']} WHERE provider_id = %d AND status NOT IN ('canceled', 'completed')",
                $id
            )
        );

        if ( $appointments > 0 ) {
            return new WP_Error( 'has_appointments', __( 'Cannot delete provider with active appointments.', 'guidepost' ) );
        }

        // Delete working hours
        $wpdb->delete( $tables['working_hours'], array( 'provider_id' => $id ), array( '%d' ) );

        // Delete days off
        $wpdb->delete( $tables['days_off'], array( 'provider_id' => $id ), array( '%d' ) );

        // Delete service associations
        $wpdb->delete( $tables['provider_services'], array( 'provider_id' => $id ), array( '%d' ) );

        // Delete provider
        $result = $wpdb->delete( $tables['providers'], array( 'id' => $id ), array( '%d' ) );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to delete provider.', 'guidepost' ) );
        }

        return true;
    }

    /**
     * Save provider services
     *
     * @param int   $provider_id Provider ID.
     * @param array $service_ids Service IDs.
     */
    public static function save_provider_services( $provider_id, $service_ids ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        // Delete existing associations
        $wpdb->delete( $tables['provider_services'], array( 'provider_id' => $provider_id ), array( '%d' ) );

        // Insert new associations
        foreach ( $service_ids as $service_id ) {
            $wpdb->insert(
                $tables['provider_services'],
                array(
                    'provider_id' => $provider_id,
                    'service_id'  => absint( $service_id ),
                ),
                array( '%d', '%d' )
            );
        }
    }

    /**
     * Get provider services
     *
     * @param int $provider_id Provider ID.
     * @return array Service IDs.
     */
    public static function get_provider_services( $provider_id ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT service_id FROM {$tables['provider_services']} WHERE provider_id = %d",
                $provider_id
            )
        );
    }

    /**
     * Save working hours
     *
     * @param int   $provider_id   Provider ID.
     * @param array $working_hours Working hours data.
     */
    public static function save_working_hours( $provider_id, $working_hours ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        // Delete existing hours
        $wpdb->delete( $tables['working_hours'], array( 'provider_id' => $provider_id ), array( '%d' ) );

        // Insert new hours
        foreach ( $working_hours as $day => $hours ) {
            if ( empty( $hours['enabled'] ) ) {
                continue;
            }

            $wpdb->insert(
                $tables['working_hours'],
                array(
                    'provider_id' => $provider_id,
                    'day_of_week' => absint( $day ),
                    'start_time'  => sanitize_text_field( $hours['start'] ),
                    'end_time'    => sanitize_text_field( $hours['end'] ),
                    'is_active'   => 1,
                ),
                array( '%d', '%d', '%s', '%s', '%d' )
            );
        }
    }

    /**
     * Get working hours
     *
     * @param int $provider_id Provider ID.
     * @return array Working hours by day.
     */
    public static function get_working_hours( $provider_id ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['working_hours']} WHERE provider_id = %d ORDER BY day_of_week",
                $provider_id
            )
        );

        $hours = array();
        foreach ( $rows as $row ) {
            $hours[ $row->day_of_week ] = array(
                'enabled' => (bool) $row->is_active,
                'start'   => $row->start_time,
                'end'     => $row->end_time,
            );
        }

        return $hours;
    }

    /**
     * Set default working hours
     *
     * @param int $provider_id Provider ID.
     */
    public static function set_default_working_hours( $provider_id ) {
        $default_hours = array(
            1 => array( 'enabled' => true, 'start' => '09:00:00', 'end' => '17:00:00' ), // Monday
            2 => array( 'enabled' => true, 'start' => '09:00:00', 'end' => '17:00:00' ), // Tuesday
            3 => array( 'enabled' => true, 'start' => '09:00:00', 'end' => '17:00:00' ), // Wednesday
            4 => array( 'enabled' => true, 'start' => '09:00:00', 'end' => '17:00:00' ), // Thursday
            5 => array( 'enabled' => true, 'start' => '09:00:00', 'end' => '17:00:00' ), // Friday
        );

        self::save_working_hours( $provider_id, $default_hours );
    }
}
