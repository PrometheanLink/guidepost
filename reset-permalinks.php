<?php
/**
 * Reset permalinks to default
 */
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Set to plain permalinks (default)
update_option( 'permalink_structure', '' );
flush_rewrite_rules( true );
echo "Permalinks reset to default.\n";

// Get booking page URL
$page = get_page_by_path( 'book-appointment' );
if ( $page ) {
    echo "Booking page URL: " . get_permalink( $page->ID ) . "\n";
}
