<?php
/**
 * Debug Calendar - Check why events aren't showing
 */
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

echo "=== GuidePost Calendar Debug ===\n\n";

// Check if tables exist
global $wpdb;

$tables = array(
    'services'          => $wpdb->prefix . 'guidepost_services',
    'providers'         => $wpdb->prefix . 'guidepost_providers',
    'appointments'      => $wpdb->prefix . 'guidepost_appointments',
    'customers'         => $wpdb->prefix . 'guidepost_customers',
    'working_hours'     => $wpdb->prefix . 'guidepost_working_hours',
    'provider_services' => $wpdb->prefix . 'guidepost_provider_services',
);

echo "1. TABLE CHECK:\n";
foreach ( $tables as $name => $table ) {
    $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
    echo "   {$name}: " . ( $exists ? 'EXISTS' : 'MISSING!' ) . "\n";
}

// Check record counts
echo "\n2. RECORD COUNTS:\n";
foreach ( $tables as $name => $table ) {
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    echo "   {$name}: {$count} records\n";
}

// Check appointments specifically
echo "\n3. APPOINTMENTS SAMPLE:\n";
$appointments = $wpdb->get_results(
    "SELECT a.id, a.booking_date, a.booking_time, a.status,
            s.name AS service_name, s.color,
            p.name AS provider_name,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name
     FROM {$tables['appointments']} a
     LEFT JOIN {$tables['services']} s ON a.service_id = s.id
     LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
     LEFT JOIN {$tables['customers']} c ON a.customer_id = c.id
     ORDER BY a.booking_date DESC
     LIMIT 10"
);

if ( empty( $appointments ) ) {
    echo "   NO APPOINTMENTS FOUND!\n";
    echo "   Run sample-data.php to generate test data.\n";
} else {
    foreach ( $appointments as $apt ) {
        echo "   ID {$apt->id}: {$apt->booking_date} {$apt->booking_time} - {$apt->customer_name} - {$apt->service_name} ({$apt->status})\n";
    }
}

// Check date range
echo "\n4. DATE RANGE ANALYSIS:\n";
$stats = $wpdb->get_row(
    "SELECT
        MIN(booking_date) as earliest,
        MAX(booking_date) as latest,
        COUNT(*) as total,
        SUM(CASE WHEN booking_date >= CURDATE() THEN 1 ELSE 0 END) as future,
        SUM(CASE WHEN booking_date < CURDATE() THEN 1 ELSE 0 END) as past
     FROM {$tables['appointments']}"
);

if ( $stats && $stats->total > 0 ) {
    echo "   Earliest appointment: {$stats->earliest}\n";
    echo "   Latest appointment: {$stats->latest}\n";
    echo "   Total: {$stats->total}\n";
    echo "   Future appointments: {$stats->future}\n";
    echo "   Past appointments: {$stats->past}\n";
    echo "   Today: " . date('Y-m-d') . "\n";
} else {
    echo "   No appointment date data available.\n";
}

// Test JSON encoding
echo "\n5. JSON ENCODING TEST:\n";
$test_events = array();
foreach ( $appointments as $apt ) {
    $test_events[] = array(
        'id'              => $apt->id,
        'title'           => $apt->customer_name . ' - ' . $apt->service_name,
        'start'           => $apt->booking_date . 'T' . $apt->booking_time,
        'backgroundColor' => $apt->color ?: '#c16107',
    );
}
$json = wp_json_encode( $test_events );
if ( $json ) {
    echo "   JSON encoding: SUCCESS\n";
    echo "   Sample: " . substr( $json, 0, 200 ) . "...\n";
} else {
    echo "   JSON encoding: FAILED!\n";
    echo "   Error: " . json_last_error_msg() . "\n";
}

echo "\n=== RECOMMENDATIONS ===\n";
if ( empty( $appointments ) ) {
    echo "1. Run: docker exec guidepost-wordpress php /var/www/html/wp-content/plugins/guidepost/sample-data.php\n";
} elseif ( $stats->future == 0 ) {
    echo "1. All appointments are in the past. Run sample-data.php to generate new ones.\n";
} else {
    echo "1. Data looks good! Check browser console for JavaScript errors.\n";
    echo "2. Make sure you're viewing the correct month in the calendar.\n";
}
