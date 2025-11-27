<?php
/**
 * WooCommerce Integration
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WooCommerce class
 */
class GuidePost_WooCommerce {

    /**
     * Initialize WooCommerce integration
     */
    public static function init() {
        if ( ! self::is_enabled() ) {
            return;
        }

        // Cart and checkout hooks
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );

        // Order status hooks
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'order_completed' ) );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'order_processing' ) );
        add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'order_cancelled' ) );
        add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'order_refunded' ) );

        // Create GuidePost product on activation
        add_action( 'admin_init', array( __CLASS__, 'ensure_product_exists' ) );

        // Handle checkout redirect from REST API bookings
        add_action( 'template_redirect', array( __CLASS__, 'handle_checkout_redirect' ) );
    }

    /**
     * Check if WooCommerce integration is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        return class_exists( 'WooCommerce' ) && get_option( 'guidepost_woocommerce_enabled', false );
    }

    /**
     * Get or create the GuidePost product
     *
     * @return int Product ID.
     */
    public static function get_product_id() {
        $product_id = get_option( 'guidepost_wc_product_id' );

        if ( $product_id && get_post( $product_id ) ) {
            return $product_id;
        }

        return self::create_product();
    }

    /**
     * Ensure product exists
     */
    public static function ensure_product_exists() {
        if ( ! self::is_enabled() ) {
            return;
        }

        self::get_product_id();
    }

    /**
     * Create the GuidePost booking product
     *
     * @return int Product ID.
     */
    private static function create_product() {
        $product = new WC_Product_Simple();

        $product->set_name( __( 'Appointment Booking', 'guidepost' ) );
        $product->set_status( 'private' ); // Hidden from shop
        $product->set_catalog_visibility( 'hidden' );
        $product->set_price( 0 );
        $product->set_regular_price( 0 );
        $product->set_sold_individually( true );
        $product->set_virtual( true );
        $product->set_description( __( 'Appointment booking through GuidePost', 'guidepost' ) );

        $product_id = $product->save();

        update_option( 'guidepost_wc_product_id', $product_id );

        return $product_id;
    }

    /**
     * Add booking to cart
     *
     * @param int   $appointment_id Appointment ID.
     * @param array $booking_data   Booking data.
     * @return bool|string Cart item key or false.
     */
    public static function add_to_cart( $appointment_id, $booking_data ) {
        if ( ! self::is_enabled() ) {
            return false;
        }

        $product_id = self::get_product_id();

        if ( ! $product_id ) {
            return false;
        }

        // Initialize WC session and cart if not already done (needed for REST API)
        if ( ! WC()->session ) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
        if ( ! WC()->cart ) {
            WC()->cart = new WC_Cart();
        }

        // Clear cart first (one booking at a time)
        WC()->cart->empty_cart();

        // Add custom cart item data
        $cart_item_data = array(
            'guidepost_booking' => true,
            'appointment_id'    => $appointment_id,
            'service_name'      => $booking_data['service_name'],
            'provider_name'     => $booking_data['provider_name'],
            'booking_date'      => $booking_data['date'],
            'booking_time'      => $booking_data['time'],
            'price'             => $booking_data['price'],
        );

        // Set the price
        add_filter( 'woocommerce_product_get_price', function( $price, $product ) use ( $booking_data ) {
            if ( $product->get_id() === self::get_product_id() ) {
                return $booking_data['price'];
            }
            return $price;
        }, 10, 2 );

        return WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
    }

    /**
     * Display booking data in cart
     *
     * @param array $item_data Cart item data.
     * @param array $cart_item Cart item.
     * @return array
     */
    public static function display_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['guidepost_booking'] ) ) {
            return $item_data;
        }

        $item_data[] = array(
            'key'   => __( 'Service', 'guidepost' ),
            'value' => $cart_item['service_name'],
        );

        $item_data[] = array(
            'key'   => __( 'Provider', 'guidepost' ),
            'value' => $cart_item['provider_name'],
        );

        $item_data[] = array(
            'key'   => __( 'Date', 'guidepost' ),
            'value' => date_i18n( get_option( 'date_format' ), strtotime( $cart_item['booking_date'] ) ),
        );

        $item_data[] = array(
            'key'   => __( 'Time', 'guidepost' ),
            'value' => date_i18n( get_option( 'time_format' ), strtotime( $cart_item['booking_time'] ) ),
        );

        return $item_data;
    }

    /**
     * Add booking meta to order item
     *
     * @param WC_Order_Item_Product $item       Order item.
     * @param string                $cart_item_key Cart item key.
     * @param array                 $values     Cart item values.
     * @param WC_Order              $order      Order.
     */
    public static function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['guidepost_booking'] ) ) {
            return;
        }

        $item->add_meta_data( '_guidepost_appointment_id', $values['appointment_id'] );
        $item->add_meta_data( __( 'Service', 'guidepost' ), $values['service_name'] );
        $item->add_meta_data( __( 'Provider', 'guidepost' ), $values['provider_name'] );
        $item->add_meta_data( __( 'Date', 'guidepost' ), $values['booking_date'] );
        $item->add_meta_data( __( 'Time', 'guidepost' ), $values['booking_time'] );
    }

    /**
     * Handle order completed
     *
     * @param int $order_id Order ID.
     */
    public static function order_completed( $order_id ) {
        self::update_appointment_from_order( $order_id, 'approved', 'paid' );
    }

    /**
     * Handle order processing (for payment methods that process immediately)
     *
     * @param int $order_id Order ID.
     */
    public static function order_processing( $order_id ) {
        self::update_appointment_from_order( $order_id, 'approved', 'paid' );
    }

    /**
     * Handle order cancelled
     *
     * @param int $order_id Order ID.
     */
    public static function order_cancelled( $order_id ) {
        self::update_appointment_from_order( $order_id, 'canceled', 'failed' );
    }

    /**
     * Handle order refunded
     *
     * @param int $order_id Order ID.
     */
    public static function order_refunded( $order_id ) {
        self::update_appointment_from_order( $order_id, 'canceled', 'refunded' );
    }

    /**
     * Update appointment status from order
     *
     * @param int    $order_id          Order ID.
     * @param string $appointment_status Appointment status.
     * @param string $payment_status    Payment status.
     */
    private static function update_appointment_from_order( $order_id, $appointment_status, $payment_status ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();
        $order  = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $appointment_id = $item->get_meta( '_guidepost_appointment_id' );

            if ( ! $appointment_id ) {
                continue;
            }

            // Update appointment status
            $wpdb->update(
                $tables['appointments'],
                array( 'status' => $appointment_status ),
                array( 'id' => $appointment_id ),
                array( '%s' ),
                array( '%d' )
            );

            // Update payment status
            $wpdb->update(
                $tables['payments'],
                array(
                    'status'       => $payment_status,
                    'wc_order_id'  => $order_id,
                    'paid_at'      => $payment_status === 'paid' ? current_time( 'mysql' ) : null,
                ),
                array( 'appointment_id' => $appointment_id ),
                array( '%s', '%d', '%s' ),
                array( '%d' )
            );

            // Trigger action for notifications
            do_action( 'guidepost_appointment_status_changed', $appointment_id, $appointment_status );
        }
    }

    /**
     * Create checkout URL for booking
     *
     * @param int   $appointment_id Appointment ID.
     * @param array $booking_data   Booking data.
     * @return string|false Checkout URL or false.
     */
    public static function get_checkout_url( $appointment_id, $booking_data ) {
        // For REST API context, return a redirect URL that will handle cart addition in browser
        $checkout_page_url = add_query_arg(
            array(
                'guidepost_checkout' => 1,
                'appointment_id'     => $appointment_id,
                'nonce'              => wp_create_nonce( 'guidepost_checkout_' . $appointment_id ),
            ),
            home_url( '/' )
        );

        return $checkout_page_url;
    }

    /**
     * Handle checkout redirect
     * This runs on template_redirect to catch our checkout URL
     */
    public static function handle_checkout_redirect() {
        if ( empty( $_GET['guidepost_checkout'] ) || empty( $_GET['appointment_id'] ) ) {
            return;
        }

        $appointment_id = absint( $_GET['appointment_id'] );
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';

        // Verify nonce
        if ( ! wp_verify_nonce( $nonce, 'guidepost_checkout_' . $appointment_id ) ) {
            wp_die( __( 'Invalid checkout request.', 'guidepost' ) );
        }

        // Get appointment data
        global $wpdb;
        $tables = GuidePost_Database::get_table_names();

        $appointment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.*, s.name as service_name, s.price, p.name as provider_name
                 FROM {$tables['appointments']} a
                 LEFT JOIN {$tables['services']} s ON a.service_id = s.id
                 LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
                 WHERE a.id = %d",
                $appointment_id
            )
        );

        if ( ! $appointment ) {
            wp_die( __( 'Appointment not found.', 'guidepost' ) );
        }

        // Add to cart
        $booking_data = array(
            'service_name'  => $appointment->service_name,
            'provider_name' => $appointment->provider_name,
            'date'          => $appointment->booking_date,
            'time'          => $appointment->booking_time,
            'price'         => $appointment->price,
        );

        $cart_item_key = self::add_to_cart( $appointment_id, $booking_data );

        if ( $cart_item_key ) {
            // Redirect to WooCommerce checkout
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        } else {
            wp_die( __( 'Failed to add booking to cart. Please try again.', 'guidepost' ) );
        }
    }
}
