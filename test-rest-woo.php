<?php
/**
 * Test WooCommerce in REST-like context
 */
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

echo "=== REST-like WooCommerce Test ===\n\n";

// Simulate REST API environment
echo "1. WooCommerce class exists: " . ( class_exists( 'WooCommerce' ) ? 'YES' : 'NO' ) . "\n";
echo "2. WC() available: " . ( function_exists( 'WC' ) ? 'YES' : 'NO' ) . "\n";

// Check if GuidePost_WooCommerce class exists
echo "3. GuidePost_WooCommerce loaded: " . ( class_exists( 'GuidePost_WooCommerce' ) ? 'YES' : 'NO' ) . "\n";

// Try to load it
if ( ! class_exists( 'GuidePost_WooCommerce' ) ) {
    echo "   Loading GuidePost_WooCommerce...\n";
    require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-woocommerce.php';
    echo "   Now loaded: " . ( class_exists( 'GuidePost_WooCommerce' ) ? 'YES' : 'NO' ) . "\n";
}

echo "4. GuidePost_WooCommerce::is_enabled(): " . ( GuidePost_WooCommerce::is_enabled() ? 'YES' : 'NO' ) . "\n";

// Check if the main plugin loaded WC integration
global $wpdb;
$tables = GuidePost_Database::get_table_names();

// Get a service with price
$service = $wpdb->get_row( "SELECT * FROM {$tables['services']} WHERE price > 0 LIMIT 1" );
echo "\n5. Test service: {$service->name} - \${$service->price}\n";

if ( GuidePost_WooCommerce::is_enabled() && $service->price > 0 ) {
    echo "\n6. WooCommerce checkout flow should trigger!\n";

    // Get checkout URL
    $booking_data = array(
        'service_name'  => $service->name,
        'provider_name' => 'Test Provider',
        'date'          => '2025-12-05',
        'time'          => '14:00',
        'price'         => $service->price,
    );

    $checkout_url = GuidePost_WooCommerce::get_checkout_url( 999, $booking_data );
    echo "   Checkout URL: " . ( $checkout_url ?: 'FAILED' ) . "\n";
} else {
    echo "\nWooCommerce checkout NOT enabled or service is free.\n";
}
