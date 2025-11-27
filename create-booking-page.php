<?php
/**
 * Create booking test page
 */
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// Check if booking page exists
$page = get_page_by_path( 'book-appointment' );

if ( $page ) {
    echo "Booking page already exists: ID {$page->ID}\n";
    echo "URL: " . get_permalink( $page->ID ) . "\n";
} else {
    // Create booking page
    $page_id = wp_insert_post( array(
        'post_title'   => 'Book an Appointment',
        'post_name'    => 'book-appointment',
        'post_content' => '[guidepost_booking]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ) );

    if ( is_wp_error( $page_id ) ) {
        echo "Error creating page: " . $page_id->get_error_message() . "\n";
    } else {
        echo "Created booking page: ID {$page_id}\n";
        echo "URL: " . get_permalink( $page_id ) . "\n";
    }
}
