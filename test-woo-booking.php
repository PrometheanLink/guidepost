<?php
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

echo "=== WooCommerce Booking Test ===\n\n";

// Check WooCommerce status
echo "WooCommerce class exists: " . ( class_exists( 'WooCommerce' ) ? 'YES' : 'NO' ) . "\n";
echo "WooCommerce enabled option: " . ( get_option( 'guidepost_woocommerce_enabled' ) ? 'YES' : 'NO' ) . "\n";

// Load our WooCommerce class
require_once GUIDEPOST_PLUGIN_DIR . 'includes/class-guidepost-woocommerce.php';

echo "GuidePost_WooCommerce::is_enabled(): " . ( GuidePost_WooCommerce::is_enabled() ? 'YES' : 'NO' ) . "\n\n";

if ( GuidePost_WooCommerce::is_enabled() ) {
    // Test getting/creating product
    $product_id = GuidePost_WooCommerce::get_product_id();
    echo "GuidePost WC Product ID: {$product_id}\n";

    if ( $product_id ) {
        $product = wc_get_product( $product_id );
        echo "Product exists: " . ( $product ? 'YES - ' . $product->get_name() : 'NO' ) . "\n";
    }

    // Test adding to cart
    $booking_data = array(
        'service_name'  => 'Test Service',
        'provider_name' => 'Test Provider',
        'date'          => '2025-12-01',
        'time'          => '10:00',
        'price'         => 150.00,
    );

    echo "\nTesting add_to_cart...\n";
    $cart_key = GuidePost_WooCommerce::add_to_cart( 999, $booking_data );
    echo "Cart key: " . ( $cart_key ? $cart_key : 'FAILED' ) . "\n";

    if ( $cart_key ) {
        echo "Checkout URL: " . wc_get_checkout_url() . "\n";
    }
}
