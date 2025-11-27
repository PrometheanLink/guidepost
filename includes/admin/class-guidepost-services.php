<?php
/**
 * Services Admin Management
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Services Admin class
 */
class GuidePost_Services {

    /**
     * Get all services
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_services( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'  => '',
            'orderby' => 'sort_order',
            'order'   => 'ASC',
            'limit'   => 0,
            'offset'  => 0,
        );

        $args   = wp_parse_args( $args, $defaults );
        $tables = GuidePost_Database::get_table_names();

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );
        $order_clause = sprintf( 'ORDER BY %s %s', esc_sql( $args['orderby'] ), esc_sql( $args['order'] ) );

        $limit_clause = '';
        if ( $args['limit'] > 0 ) {
            $limit_clause = sprintf( 'LIMIT %d OFFSET %d', absint( $args['limit'] ), absint( $args['offset'] ) );
        }

        $query = "SELECT * FROM {$tables['services']} WHERE {$where_clause} {$order_clause} {$limit_clause}";

        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( $query, $values );
        }

        return $wpdb->get_results( $query );
    }

    /**
     * Get single service
     *
     * @param int $id Service ID.
     * @return object|null
     */
    public static function get_service( $id ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tables['services']} WHERE id = %d", $id )
        );
    }

    /**
     * Create service
     *
     * @param array $data Service data.
     * @return int|WP_Error Service ID or error.
     */
    public static function create_service( $data ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        $defaults = array(
            'name'           => '',
            'description'    => '',
            'duration'       => 60,
            'price'          => 0.00,
            'deposit_amount' => 0.00,
            'deposit_type'   => 'fixed',
            'color'          => '#c16107',
            'status'         => 'active',
            'min_capacity'   => 1,
            'max_capacity'   => 1,
            'buffer_before'  => 0,
            'buffer_after'   => 0,
            'sort_order'     => 0,
        );

        $data = wp_parse_args( $data, $defaults );

        // Validate required fields
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'missing_name', __( 'Service name is required.', 'guidepost' ) );
        }

        $result = $wpdb->insert(
            $tables['services'],
            array(
                'name'           => sanitize_text_field( $data['name'] ),
                'description'    => sanitize_textarea_field( $data['description'] ),
                'duration'       => absint( $data['duration'] ),
                'price'          => floatval( $data['price'] ),
                'deposit_amount' => floatval( $data['deposit_amount'] ),
                'deposit_type'   => in_array( $data['deposit_type'], array( 'fixed', 'percentage' ), true ) ? $data['deposit_type'] : 'fixed',
                'color'          => sanitize_hex_color( $data['color'] ) ?: '#c16107',
                'status'         => in_array( $data['status'], array( 'active', 'inactive', 'hidden' ), true ) ? $data['status'] : 'active',
                'min_capacity'   => absint( $data['min_capacity'] ),
                'max_capacity'   => absint( $data['max_capacity'] ),
                'buffer_before'  => absint( $data['buffer_before'] ),
                'buffer_after'   => absint( $data['buffer_after'] ),
                'sort_order'     => absint( $data['sort_order'] ),
            ),
            array( '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
        );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to create service.', 'guidepost' ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update service
     *
     * @param int   $id   Service ID.
     * @param array $data Service data.
     * @return bool|WP_Error
     */
    public static function update_service( $id, $data ) {
        global $wpdb;

        $tables  = GuidePost_Database::get_table_names();
        $service = self::get_service( $id );

        if ( ! $service ) {
            return new WP_Error( 'not_found', __( 'Service not found.', 'guidepost' ) );
        }

        $update_data   = array();
        $update_format = array();

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            $update_format[]     = '%s';
        }

        if ( isset( $data['description'] ) ) {
            $update_data['description'] = sanitize_textarea_field( $data['description'] );
            $update_format[]            = '%s';
        }

        if ( isset( $data['duration'] ) ) {
            $update_data['duration'] = absint( $data['duration'] );
            $update_format[]         = '%d';
        }

        if ( isset( $data['price'] ) ) {
            $update_data['price'] = floatval( $data['price'] );
            $update_format[]      = '%f';
        }

        if ( isset( $data['deposit_amount'] ) ) {
            $update_data['deposit_amount'] = floatval( $data['deposit_amount'] );
            $update_format[]               = '%f';
        }

        if ( isset( $data['deposit_type'] ) && in_array( $data['deposit_type'], array( 'fixed', 'percentage' ), true ) ) {
            $update_data['deposit_type'] = $data['deposit_type'];
            $update_format[]             = '%s';
        }

        if ( isset( $data['color'] ) ) {
            $update_data['color'] = sanitize_hex_color( $data['color'] ) ?: '#c16107';
            $update_format[]      = '%s';
        }

        if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'inactive', 'hidden' ), true ) ) {
            $update_data['status'] = $data['status'];
            $update_format[]       = '%s';
        }

        if ( isset( $data['min_capacity'] ) ) {
            $update_data['min_capacity'] = absint( $data['min_capacity'] );
            $update_format[]             = '%d';
        }

        if ( isset( $data['max_capacity'] ) ) {
            $update_data['max_capacity'] = absint( $data['max_capacity'] );
            $update_format[]             = '%d';
        }

        if ( isset( $data['buffer_before'] ) ) {
            $update_data['buffer_before'] = absint( $data['buffer_before'] );
            $update_format[]              = '%d';
        }

        if ( isset( $data['buffer_after'] ) ) {
            $update_data['buffer_after'] = absint( $data['buffer_after'] );
            $update_format[]             = '%d';
        }

        if ( isset( $data['sort_order'] ) ) {
            $update_data['sort_order'] = absint( $data['sort_order'] );
            $update_format[]           = '%d';
        }

        if ( empty( $update_data ) ) {
            return true; // Nothing to update
        }

        $result = $wpdb->update(
            $tables['services'],
            $update_data,
            array( 'id' => $id ),
            $update_format,
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Failed to update service.', 'guidepost' ) );
        }

        return true;
    }

    /**
     * Delete service
     *
     * @param int $id Service ID.
     * @return bool|WP_Error
     */
    public static function delete_service( $id ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        // Check for existing appointments
        $appointments = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['appointments']} WHERE service_id = %d AND status NOT IN ('canceled', 'completed')",
                $id
            )
        );

        if ( $appointments > 0 ) {
            return new WP_Error( 'has_appointments', __( 'Cannot delete service with active appointments.', 'guidepost' ) );
        }

        // Delete provider associations
        $wpdb->delete( $tables['provider_services'], array( 'service_id' => $id ), array( '%d' ) );

        // Delete service
        $result = $wpdb->delete( $tables['services'], array( 'id' => $id ), array( '%d' ) );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to delete service.', 'guidepost' ) );
        }

        return true;
    }

    /**
     * Count services
     *
     * @param string $status Optional status filter.
     * @return int
     */
    public static function count_services( $status = '' ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        if ( $status ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['services']} WHERE status = %s", $status )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']}" );
    }
}
