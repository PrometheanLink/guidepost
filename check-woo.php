<?php
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

echo "=== Plugin Status ===\n\n";

// Check WooCommerce
if ( class_exists( 'WooCommerce' ) ) {
    echo "WooCommerce: ACTIVE (v" . WC()->version . ")\n";
} else {
    // Check if installed but not active
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugins = get_plugins();
    $woo_found = false;
    foreach ( $plugins as $path => $data ) {
        if ( strpos( $path, 'woocommerce' ) !== false ) {
            echo "WooCommerce: INSTALLED but NOT ACTIVE\n";
            echo "  Path: {$path}\n";
            echo "  Activating...\n";
            $result = activate_plugin( $path );
            if ( is_wp_error( $result ) ) {
                echo "  ERROR: " . $result->get_error_message() . "\n";
            } else {
                echo "  SUCCESS - WooCommerce activated!\n";
            }
            $woo_found = true;
            break;
        }
    }
    if ( ! $woo_found ) {
        echo "WooCommerce: NOT INSTALLED\n";
    }
}

echo "\n=== GuidePost Settings ===\n";
echo "WooCommerce enabled: " . ( get_option( 'guidepost_woocommerce_enabled' ) ? 'YES' : 'NO' ) . "\n";
