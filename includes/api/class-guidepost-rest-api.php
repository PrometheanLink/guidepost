<?php
/**
 * REST API endpoints
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API class
 */
class GuidePost_REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'guidepost/v1';

    /**
     * Register REST routes
     */
    public static function register_routes() {
        // Services
        register_rest_route(
            self::NAMESPACE,
            '/services',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_services' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/services/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_service' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'id' => array(
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            },
                        ),
                    ),
                ),
            )
        );

        // Providers
        register_rest_route(
            self::NAMESPACE,
            '/providers',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_providers' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'service_id' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );

        // Availability - time slots for a specific date
        register_rest_route(
            self::NAMESPACE,
            '/availability',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_availability' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'service_id'  => array(
                            'required'          => true,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'provider_id' => array(
                            'required'          => true,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'date'        => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        // Available dates for a month (for calendar)
        register_rest_route(
            self::NAMESPACE,
            '/availability/dates',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_available_dates' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'service_id'  => array(
                            'required'          => true,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'provider_id' => array(
                            'required'          => true,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'month'       => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        // Bookings (requires authentication for write)
        register_rest_route(
            self::NAMESPACE,
            '/bookings',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'create_booking' ),
                    'permission_callback' => '__return_true', // Public booking
                    'args'                => array(
                        'service_id'  => array(
                            'required'          => true,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'provider_id' => array(
                            'required'          => true,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                        'date'        => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'time'        => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'first_name'  => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'last_name'   => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'email'       => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_email',
                        ),
                        'phone'       => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'notes'       => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_textarea_field',
                        ),
                    ),
                ),
            )
        );

        // Admin endpoints (require manage_options capability)
        register_rest_route(
            self::NAMESPACE,
            '/admin/appointments',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_appointments' ),
                    'permission_callback' => array( __CLASS__, 'admin_permission_check' ),
                    'args'                => array(
                        'status' => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'date_from' => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'date_to' => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/admin/appointments/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( __CLASS__, 'update_appointment' ),
                    'permission_callback' => array( __CLASS__, 'admin_permission_check' ),
                    'args'                => array(
                        'id' => array(
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            },
                        ),
                        'status' => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Admin permission check
     *
     * @return bool
     */
    public static function admin_permission_check() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get services
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_services( $request ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        $services = $wpdb->get_results(
            "SELECT * FROM {$tables['services']} WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
        );

        return rest_ensure_response( $services );
    }

    /**
     * Get single service
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_service( $request ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();
        $id = $request->get_param( 'id' );

        $service = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$tables['services']} WHERE id = %d",
                $id
            )
        );

        if ( ! $service ) {
            return new WP_Error( 'not_found', __( 'Service not found.', 'guidepost' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response( $service );
    }

    /**
     * Get providers
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_providers( $request ) {
        global $wpdb;

        $tables     = GuidePost_Database::get_table_names();
        $service_id = $request->get_param( 'service_id' );

        if ( $service_id ) {
            // Get providers for specific service
            $providers = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.* FROM {$tables['providers']} p
                    INNER JOIN {$tables['provider_services']} ps ON p.id = ps.provider_id
                    WHERE ps.service_id = %d AND p.status = 'active'
                    ORDER BY p.name ASC",
                    $service_id
                )
            );
        } else {
            // Get all active providers
            $providers = $wpdb->get_results(
                "SELECT * FROM {$tables['providers']} WHERE status = 'active' ORDER BY name ASC"
            );
        }

        return rest_ensure_response( $providers );
    }

    /**
     * Get availability
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_availability( $request ) {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-availability.php';

        $service_id  = $request->get_param( 'service_id' );
        $provider_id = $request->get_param( 'provider_id' );
        $date        = $request->get_param( 'date' );

        // Validate date format
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date ) {
            return new WP_Error( 'invalid_date', __( 'Invalid date format.', 'guidepost' ), array( 'status' => 400 ) );
        }

        $slots = GuidePost_Availability::get_available_slots( $service_id, $provider_id, $date );

        return rest_ensure_response( array( 'slots' => $slots ) );
    }

    /**
     * Get available dates for a month
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_available_dates( $request ) {
        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-availability.php';

        $service_id  = $request->get_param( 'service_id' );
        $provider_id = $request->get_param( 'provider_id' );
        $month       = $request->get_param( 'month' );

        // Validate month format (YYYY-MM)
        if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
            return new WP_Error( 'invalid_month', __( 'Invalid month format. Use YYYY-MM.', 'guidepost' ), array( 'status' => 400 ) );
        }

        $dates = GuidePost_Availability::get_available_dates( $service_id, $provider_id, $month );

        return rest_ensure_response( array( 'dates' => $dates ) );
    }

    /**
     * Create booking
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function create_booking( $request ) {
        global $wpdb;

        require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-availability.php';

        $tables = GuidePost_Database::get_table_names();

        // Get parameters
        $service_id  = $request->get_param( 'service_id' );
        $provider_id = $request->get_param( 'provider_id' );
        $date        = $request->get_param( 'date' );
        $time        = $request->get_param( 'time' );
        $first_name  = $request->get_param( 'first_name' );
        $last_name   = $request->get_param( 'last_name' );
        $email       = $request->get_param( 'email' );
        $phone       = $request->get_param( 'phone' );
        $notes       = $request->get_param( 'notes' );

        // Validate service
        $service = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tables['services']} WHERE id = %d", $service_id )
        );

        if ( ! $service ) {
            return new WP_Error( 'invalid_service', __( 'Service not found.', 'guidepost' ), array( 'status' => 400 ) );
        }

        // Verify slot is still available (prevents double booking)
        if ( ! GuidePost_Availability::is_slot_available( $service_id, $provider_id, $date, $time ) ) {
            return new WP_Error( 'slot_unavailable', __( 'This time slot is no longer available. Please select another time.', 'guidepost' ), array( 'status' => 409 ) );
        }

        // Calculate end time
        $booking_time = new DateTime( $time, wp_timezone() );
        $end_time     = clone $booking_time;
        $end_time->modify( "+{$service->duration} minutes" );

        // Get or create customer
        $customer_id = self::get_or_create_customer( $first_name, $last_name, $email, $phone );

        if ( is_wp_error( $customer_id ) ) {
            return $customer_id;
        }

        // Create appointment
        $result = $wpdb->insert(
            $tables['appointments'],
            array(
                'service_id'     => $service_id,
                'provider_id'    => $provider_id,
                'customer_id'    => $customer_id,
                'booking_date'   => $date,
                'booking_time'   => $booking_time->format( 'H:i:s' ),
                'end_time'       => $end_time->format( 'H:i:s' ),
                'status'         => 'pending',
                'customer_notes' => $notes,
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            return new WP_Error( 'booking_failed', __( 'Failed to create booking.', 'guidepost' ), array( 'status' => 500 ) );
        }

        $appointment_id = $wpdb->insert_id;

        // Create payment record if service has a price
        if ( $service->price > 0 ) {
            $wpdb->insert(
                $tables['payments'],
                array(
                    'appointment_id' => $appointment_id,
                    'amount'         => $service->price,
                    'status'         => 'pending',
                    'gateway'        => 'on_site',
                ),
                array( '%d', '%f', '%s', '%s' )
            );
        }

        // Trigger booking created action
        do_action( 'guidepost_booking_created', $appointment_id, $service, $customer_id );

        // Check if WooCommerce payment is needed
        $response_data = array(
            'success'        => true,
            'appointment_id' => $appointment_id,
            'message'        => __( 'Booking created successfully.', 'guidepost' ),
        );

        // If WooCommerce is enabled and service has a price, redirect to checkout
        if ( class_exists( 'GuidePost_WooCommerce' ) && GuidePost_WooCommerce::is_enabled() && $service->price > 0 ) {
            // Get provider name for cart display
            $provider = $wpdb->get_row(
                $wpdb->prepare( "SELECT name FROM {$tables['providers']} WHERE id = %d", $provider_id )
            );

            $booking_data = array(
                'service_name'  => $service->name,
                'provider_name' => $provider ? $provider->name : '',
                'date'          => $date,
                'time'          => $time,
                'price'         => $service->price,
            );

            $checkout_url = GuidePost_WooCommerce::get_checkout_url( $appointment_id, $booking_data );

            if ( $checkout_url ) {
                $response_data['checkout_url'] = $checkout_url;
                $response_data['requires_payment'] = true;
                $response_data['message'] = __( 'Booking created. Redirecting to checkout...', 'guidepost' );
            }
        }

        return rest_ensure_response( $response_data );
    }

    /**
     * Get or create customer
     *
     * @param string $first_name First name.
     * @param string $last_name  Last name.
     * @param string $email      Email address.
     * @param string $phone      Phone number.
     * @return int|WP_Error Customer ID or error.
     */
    private static function get_or_create_customer( $first_name, $last_name, $email, $phone ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        // Check for existing customer by email
        $customer_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$tables['customers']} WHERE email = %s",
                $email
            )
        );

        if ( $customer_id ) {
            // Update customer info
            $wpdb->update(
                $tables['customers'],
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'phone'      => $phone,
                ),
                array( 'id' => $customer_id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );
            return (int) $customer_id;
        }

        // Create new customer
        $result = $wpdb->insert(
            $tables['customers'],
            array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $email,
                'phone'      => $phone,
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            return new WP_Error( 'customer_failed', __( 'Failed to create customer.', 'guidepost' ), array( 'status' => 500 ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Get appointments (admin)
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_appointments( $request ) {
        global $wpdb;

        $tables    = GuidePost_Database::get_table_names();
        $status    = $request->get_param( 'status' );
        $date_from = $request->get_param( 'date_from' );
        $date_to   = $request->get_param( 'date_to' );

        $where = array( '1=1' );
        $values = array();

        if ( $status ) {
            $where[]  = 'a.status = %s';
            $values[] = $status;
        }

        if ( $date_from ) {
            $where[]  = 'a.booking_date >= %s';
            $values[] = $date_from;
        }

        if ( $date_to ) {
            $where[]  = 'a.booking_date <= %s';
            $values[] = $date_to;
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT a.*,
                         s.name as service_name, s.color as service_color,
                         p.name as provider_name,
                         c.first_name, c.last_name, c.email, c.phone
                  FROM {$tables['appointments']} a
                  LEFT JOIN {$tables['services']} s ON a.service_id = s.id
                  LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
                  LEFT JOIN {$tables['customers']} c ON a.customer_id = c.id
                  WHERE {$where_clause}
                  ORDER BY a.booking_date DESC, a.booking_time DESC";

        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( $query, $values ); // phpcs:ignore
        }

        $appointments = $wpdb->get_results( $query ); // phpcs:ignore

        return rest_ensure_response( $appointments );
    }

    /**
     * Update appointment (admin)
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function update_appointment( $request ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();
        $id     = $request->get_param( 'id' );
        $status = $request->get_param( 'status' );

        $valid_statuses = array( 'pending', 'approved', 'canceled', 'completed', 'no_show' );

        if ( $status && ! in_array( $status, $valid_statuses, true ) ) {
            return new WP_Error( 'invalid_status', __( 'Invalid status.', 'guidepost' ), array( 'status' => 400 ) );
        }

        $update_data = array();
        $update_format = array();

        if ( $status ) {
            $update_data['status'] = $status;
            $update_format[] = '%s';
        }

        if ( empty( $update_data ) ) {
            return new WP_Error( 'no_update', __( 'No data to update.', 'guidepost' ), array( 'status' => 400 ) );
        }

        $result = $wpdb->update(
            $tables['appointments'],
            $update_data,
            array( 'id' => $id ),
            $update_format,
            array( '%d' )
        );

        if ( false === $result ) {
            return new WP_Error( 'update_failed', __( 'Failed to update appointment.', 'guidepost' ), array( 'status' => 500 ) );
        }

        // Trigger status change action
        if ( $status ) {
            do_action( 'guidepost_appointment_status_changed', $id, $status );
        }

        return rest_ensure_response(
            array(
                'success' => true,
                'message' => __( 'Appointment updated.', 'guidepost' ),
            )
        );
    }
}
