<?php
/**
 * Flush permalinks
 */
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Flush rewrite rules
flush_rewrite_rules( true );
echo "Permalinks flushed!\n";

// Update permalink structure if not set
$structure = get_option( 'permalink_structure' );
if ( empty( $structure ) ) {
    update_option( 'permalink_structure', '/%postname%/' );
    flush_rewrite_rules( true );
    echo "Updated to pretty permalinks.\n";
}

// Get booking page URL
$page = get_page_by_path( 'book-appointment' );
if ( $page ) {
    echo "Booking page URL: " . get_permalink( $page->ID ) . "\n";
} else {
    echo "Booking page not found.\n";
}
