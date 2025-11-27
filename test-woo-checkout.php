<?php
/**
 * Test WooCommerce checkout URL generation
 */
require_once dirname( __FILE__ ) . '/../../../wp-load.php';
require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-woocommerce.php';

echo "=== WooCommerce Checkout URL Test ===\n\n";

// Check prerequisites
echo "1. WooCommerce active: " . ( class_exists( 'WooCommerce' ) ? 'YES' : 'NO' ) . "\n";
echo "2. WC enabled option: " . ( get_option( 'guidepost_woocommerce_enabled' ) ? 'YES' : 'NO' ) . "\n";
echo "3. GuidePost_WooCommerce::is_enabled(): " . ( GuidePost_WooCommerce::is_enabled() ? 'YES' : 'NO' ) . "\n\n";

if ( ! GuidePost_WooCommerce::is_enabled() ) {
    echo "WooCommerce integration is not enabled. Exiting.\n";
    exit;
}

// Get a service with price
global $wpdb;
$tables = GuidePost_Database::get_table_names();
$service = $wpdb->get_row( "SELECT * FROM {$tables['services']} WHERE price > 0 LIMIT 1" );

if ( ! $service ) {
    echo "No paid services found. Exiting.\n";
    exit;
}

echo "4. Test service: {$service->name} - \${$service->price}\n\n";

// Create a test appointment
$customer_id = $wpdb->get_var( "SELECT id FROM {$tables['customers']} LIMIT 1" );
$provider_id = $wpdb->get_var( "SELECT id FROM {$tables['providers']} WHERE status = 'active' LIMIT 1" );

$wpdb->insert(
    $tables['appointments'],
    array(
        'service_id'   => $service->id,
        'provider_id'  => $provider_id,
        'customer_id'  => $customer_id,
        'booking_date' => date( 'Y-m-d', strtotime( '+3 days' ) ),
        'booking_time' => '14:00:00',
        'end_time'     => '15:00:00',
        'status'       => 'pending',
    ),
    array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
);

$appointment_id = $wpdb->insert_id;
echo "5. Created test appointment ID: {$appointment_id}\n\n";

// Get checkout URL
$provider = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['providers']} WHERE id = %d", $provider_id ) );

$booking_data = array(
    'service_name'  => $service->name,
    'provider_name' => $provider->name,
    'date'          => date( 'Y-m-d', strtotime( '+3 days' ) ),
    'time'          => '14:00',
    'price'         => $service->price,
);

$checkout_url = GuidePost_WooCommerce::get_checkout_url( $appointment_id, $booking_data );

echo "6. Generated checkout URL:\n";
echo "   {$checkout_url}\n\n";

// Parse and display URL components
$parsed = parse_url( $checkout_url );
parse_str( $parsed['query'], $query_params );

echo "7. URL breakdown:\n";
echo "   - Base: {$parsed['scheme']}://{$parsed['host']}{$parsed['path']}\n";
echo "   - appointment_id: {$query_params['appointment_id']}\n";
echo "   - nonce: " . substr( $query_params['nonce'], 0, 10 ) . "...\n\n";

echo "=== SUCCESS ===\n";
echo "Visit the checkout URL in your browser to test the full WooCommerce checkout flow.\n";
echo "The URL will:\n";
echo "  1. Verify the nonce\n";
echo "  2. Load the appointment data\n";
echo "  3. Add the booking to WooCommerce cart\n";
echo "  4. Redirect to WooCommerce checkout page\n";
