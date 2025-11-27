<?php
/**
 * Availability calculation service
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Availability class
 */
class GuidePost_Availability {

    /**
     * Get available dates for a month
     *
     * @param int    $service_id  Service ID.
     * @param int    $provider_id Provider ID.
     * @param string $month       Month (YYYY-MM format).
     * @return array Available dates.
     */
    public static function get_available_dates( $service_id, $provider_id, $month ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        // Parse month
        $date_parts = explode( '-', $month );
        if ( count( $date_parts ) !== 2 ) {
            return array();
        }

        $year  = absint( $date_parts[0] );
        $month_num = absint( $date_parts[1] );
        // Get days in month using native date function (no calendar extension needed)
        $days_in_month = (int) date( 't', mktime( 0, 0, 0, $month_num, 1, $year ) );

        // Get service details
        $service = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tables['services']} WHERE id = %d", $service_id )
        );

        if ( ! $service ) {
            return array();
        }

        // Get provider working hours
        $working_hours = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT day_of_week, start_time, end_time FROM {$tables['working_hours']}
                WHERE provider_id = %d AND is_active = 1",
                $provider_id
            ),
            OBJECT_K
        );

        // Index by day_of_week
        $hours_by_day = array();
        foreach ( $working_hours as $row ) {
            $hours_by_day[ $row->day_of_week ] = $row;
        }

        // Get days off for this month
        $start_date = sprintf( '%04d-%02d-01', $year, $month_num );
        $end_date   = sprintf( '%04d-%02d-%02d', $year, $month_num, $days_in_month );

        $days_off = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT DATE(d.date_val) FROM (
                    SELECT date_start + INTERVAL seq DAY AS date_val
                    FROM {$tables['days_off']},
                    (SELECT 0 AS seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30) AS nums
                    WHERE provider_id = %d
                    AND date_start + INTERVAL seq DAY <= date_end
                ) d
                WHERE d.date_val BETWEEN %s AND %s",
                $provider_id,
                $start_date,
                $end_date
            )
        );

        $days_off_map = array_flip( $days_off );

        // Check each day in the month
        $available_dates = array();
        $today = new DateTime( 'today', wp_timezone() );

        for ( $day = 1; $day <= $days_in_month; $day++ ) {
            $date = new DateTime( sprintf( '%04d-%02d-%02d', $year, $month_num, $day ), wp_timezone() );
            $date_str = $date->format( 'Y-m-d' );

            // Skip past dates
            if ( $date < $today ) {
                continue;
            }

            // Skip days off
            if ( isset( $days_off_map[ $date_str ] ) ) {
                continue;
            }

            // Check if provider works this day
            $day_of_week = (int) $date->format( 'w' ); // 0=Sunday, 6=Saturday
            if ( ! isset( $hours_by_day[ $day_of_week ] ) ) {
                continue;
            }

            // Check if there are any available slots
            $slots = self::get_available_slots( $service_id, $provider_id, $date_str );
            if ( ! empty( $slots ) ) {
                $available_dates[] = $date_str;
            }
        }

        return $available_dates;
    }

    /**
     * Get available time slots for a date
     *
     * @param int    $service_id  Service ID.
     * @param int    $provider_id Provider ID.
     * @param string $date        Date (YYYY-MM-DD format).
     * @return array Available time slots.
     */
    public static function get_available_slots( $service_id, $provider_id, $date ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();

        // Parse and validate date
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date, wp_timezone() );
        if ( ! $date_obj ) {
            return array();
        }

        $today = new DateTime( 'today', wp_timezone() );
        if ( $date_obj < $today ) {
            return array();
        }

        // Get service details
        $service = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tables['services']} WHERE id = %d", $service_id )
        );

        if ( ! $service ) {
            return array();
        }

        // Get provider working hours for this day
        $day_of_week = (int) $date_obj->format( 'w' );
        $working_hours = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$tables['working_hours']}
                WHERE provider_id = %d AND day_of_week = %d AND is_active = 1",
                $provider_id,
                $day_of_week
            )
        );

        if ( ! $working_hours ) {
            return array();
        }

        // Check if day off
        $is_day_off = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$tables['days_off']}
                WHERE provider_id = %d AND %s BETWEEN date_start AND date_end",
                $provider_id,
                $date
            )
        );

        if ( $is_day_off ) {
            return array();
        }

        // Get existing appointments for this date
        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT booking_time, end_time FROM {$tables['appointments']}
                WHERE provider_id = %d AND booking_date = %s AND status NOT IN ('canceled')",
                $provider_id,
                $date
            )
        );

        // Convert appointments to blocked time ranges
        $blocked_ranges = array();
        foreach ( $appointments as $apt ) {
            $blocked_ranges[] = array(
                'start' => strtotime( $date . ' ' . $apt->booking_time ),
                'end'   => strtotime( $date . ' ' . $apt->end_time ),
            );
        }

        // Generate available slots
        $slot_duration = (int) get_option( 'guidepost_time_slot_duration', 30 );
        $total_duration = $service->buffer_before + $service->duration + $service->buffer_after;

        $work_start = strtotime( $date . ' ' . $working_hours->start_time );
        $work_end   = strtotime( $date . ' ' . $working_hours->end_time );

        // If today, don't show past slots
        $now = new DateTime( 'now', wp_timezone() );
        if ( $date_obj->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) ) {
            // Add buffer - don't allow bookings within the next hour
            $min_booking_time = strtotime( '+1 hour', $now->getTimestamp() );
            // Round up to next slot
            $min_booking_time = ceil( $min_booking_time / ( $slot_duration * 60 ) ) * ( $slot_duration * 60 );
            if ( $min_booking_time > $work_start ) {
                $work_start = $min_booking_time;
            }
        }

        $slots = array();
        $current = $work_start;

        while ( $current + ( $total_duration * 60 ) <= $work_end ) {
            $slot_end = $current + ( $total_duration * 60 );

            // Check if slot conflicts with any appointment
            $is_available = true;
            foreach ( $blocked_ranges as $blocked ) {
                // Check for overlap
                if ( $current < $blocked['end'] && $slot_end > $blocked['start'] ) {
                    $is_available = false;
                    break;
                }
            }

            if ( $is_available ) {
                $time = date( 'H:i', $current );
                $slots[] = array(
                    'time'    => $time,
                    'display' => date( get_option( 'time_format', 'g:i A' ), $current ),
                );
            }

            $current += $slot_duration * 60;
        }

        return $slots;
    }

    /**
     * Check if a specific slot is available
     *
     * @param int    $service_id  Service ID.
     * @param int    $provider_id Provider ID.
     * @param string $date        Date (YYYY-MM-DD).
     * @param string $time        Time (HH:MM).
     * @return bool True if available.
     */
    public static function is_slot_available( $service_id, $provider_id, $date, $time ) {
        $slots = self::get_available_slots( $service_id, $provider_id, $date );

        foreach ( $slots as $slot ) {
            if ( $slot['time'] === $time ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get next available date
     *
     * @param int $service_id  Service ID.
     * @param int $provider_id Provider ID.
     * @param int $days_ahead  How many days ahead to check.
     * @return string|null Next available date or null.
     */
    public static function get_next_available_date( $service_id, $provider_id, $days_ahead = 30 ) {
        $today = new DateTime( 'today', wp_timezone() );

        for ( $i = 0; $i < $days_ahead; $i++ ) {
            $date = clone $today;
            $date->modify( "+{$i} days" );
            $date_str = $date->format( 'Y-m-d' );

            $slots = self::get_available_slots( $service_id, $provider_id, $date_str );
            if ( ! empty( $slots ) ) {
                return $date_str;
            }
        }

        return null;
    }
}
