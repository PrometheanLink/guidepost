<?php
/**
 * GuidePost Sample Data Generator
 *
 * Creates sample data for testing and demonstration purposes.
 * Uses Kim Benedict / Sojourn Coaching as the example business.
 *
 * Run via Docker:
 * docker exec guidepost-wordpress bash -c "php /var/www/html/wp-content/plugins/guidepost/sample-data.php"
 *
 * To clear data:
 * docker exec guidepost-wordpress bash -c "php /var/www/html/wp-content/plugins/guidepost/sample-data.php --clear"
 *
 * @package GuidePost
 */

// Bootstrap WordPress
$wp_load_path = dirname( __FILE__ ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
    die( "WordPress not found at: {$wp_load_path}\n" );
}
require_once $wp_load_path;

/**
 * GuidePost Sample Data Generator Class
 */
class GuidePost_Sample_Data {

    /**
     * Database instance
     */
    private $db;

    /**
     * Table names
     */
    private $tables;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        $this->db = $wpdb;

        // Get table names from database class if available
        if ( class_exists( 'GuidePost_Database' ) ) {
            $this->tables = GuidePost_Database::get_table_names();
        } else {
            // Fallback table names
            $this->tables = array(
                'services'           => $wpdb->prefix . 'guidepost_services',
                'providers'          => $wpdb->prefix . 'guidepost_providers',
                'provider_services'  => $wpdb->prefix . 'guidepost_provider_services',
                'working_hours'      => $wpdb->prefix . 'guidepost_working_hours',
                'customers'          => $wpdb->prefix . 'guidepost_customers',
                'appointments'       => $wpdb->prefix . 'guidepost_appointments',
                'customer_notes'     => $wpdb->prefix . 'guidepost_customer_notes',
                'customer_purchases' => $wpdb->prefix . 'guidepost_customer_purchases',
                'customer_documents' => $wpdb->prefix . 'guidepost_customer_documents',
                'customer_flags'     => $wpdb->prefix . 'guidepost_customer_flags',
                'credit_history'     => $wpdb->prefix . 'guidepost_credit_history',
            );
        }
    }

    /**
     * Generate all sample data
     */
    public function generate() {
        echo "=== GuidePost Sample Data Generator ===\n";
        echo "Business: Sojourn Coaching (Kim Benedict)\n\n";

        // Ensure tables exist first
        if ( class_exists( 'GuidePost_Database' ) ) {
            echo "Ensuring database tables are up to date...\n";
            GuidePost_Database::create_tables();
        }

        // Create provider (Kim Benedict)
        $provider_id = $this->create_provider();
        echo "Created provider: Kim Benedict (ID: {$provider_id})\n";

        // Create services
        $services = $this->create_services( $provider_id );
        echo "Created " . count( $services ) . " coaching services\n";

        // Create customers
        $customers = $this->create_customers();
        echo "Created " . count( $customers ) . " customers\n";

        // Create appointments
        $appointments = $this->create_appointments( $services, $customers, $provider_id );
        echo "Created " . count( $appointments ) . " appointments\n";

        // Create notes
        $notes = $this->create_notes( $customers );
        echo "Created " . count( $notes ) . " customer notes\n";

        // Create purchases
        $purchases = $this->create_purchases( $customers );
        echo "Created " . count( $purchases ) . " purchases\n";

        // Create flags
        $flags = $this->create_flags( $customers );
        echo "Created " . count( $flags ) . " customer flags\n";

        // Create credit history
        $credits = $this->create_credit_history( $customers );
        echo "Created " . count( $credits ) . " credit history entries\n";

        echo "\n=== Sample Data Generation Complete ===\n";
        echo "Visit GuidePost > Customers to see the sample data.\n";

        return true;
    }

    /**
     * Create Kim Benedict as provider
     */
    private function create_provider() {
        // First, create or get WordPress user
        $user = get_user_by( 'email', 'kim@sojourncoaching.com' );

        if ( ! $user ) {
            $user_id = wp_create_user(
                'kim.benedict',
                wp_generate_password(),
                'kim@sojourncoaching.com'
            );

            if ( ! is_wp_error( $user_id ) ) {
                wp_update_user( array(
                    'ID'           => $user_id,
                    'first_name'   => 'Kim',
                    'last_name'    => 'Benedict',
                    'display_name' => 'Kim Benedict',
                    'role'         => 'administrator',
                ) );
            } else {
                $user_id = 1;
            }
        } else {
            $user_id = $user->ID;
        }

        // Check if provider already exists
        $existing = $this->db->get_var( $this->db->prepare(
            "SELECT id FROM {$this->tables['providers']} WHERE email = %s",
            'kim@sojourncoaching.com'
        ) );

        if ( $existing ) {
            return $existing;
        }

        // Create provider record (matching actual schema)
        $this->db->insert(
            $this->tables['providers'],
            array(
                'user_id'    => $user_id,
                'name'       => 'Kim Benedict',
                'email'      => 'kim@sojourncoaching.com',
                'phone'      => '(555) 100-0001',
                'bio'        => 'Kim Benedict is a certified life and business coach specializing in helping professionals navigate career transitions and personal growth. With over 15 years of experience, Kim brings a unique blend of corporate expertise and mindfulness practices to every coaching session.',
                'timezone'   => 'America/New_York',
                'status'     => 'active',
                'created_at' => current_time( 'mysql' ),
            )
        );

        $provider_id = $this->db->insert_id;

        // Add working hours (Mon-Fri, 9am-5pm)
        // day_of_week: 0=Sunday, 1=Monday, ... 6=Saturday
        for ( $day = 1; $day <= 5; $day++ ) {
            $this->db->insert(
                $this->tables['working_hours'],
                array(
                    'provider_id' => $provider_id,
                    'day_of_week' => $day,
                    'start_time'  => '09:00:00',
                    'end_time'    => '17:00:00',
                    'is_active'   => 1,
                )
            );
        }

        return $provider_id;
    }

    /**
     * Create coaching services
     */
    private function create_services( $provider_id ) {
        // Clear existing services
        $this->db->query( "DELETE FROM {$this->tables['provider_services']}" );
        $this->db->query( "DELETE FROM {$this->tables['services']}" );

        // appointment_mode enum: 'in_person', 'virtual', 'hybrid'
        $services_data = array(
            array(
                'name'                     => 'Discovery Session',
                'description'              => 'A complimentary 30-minute session to explore your goals and see if we are a good fit for working together.',
                'duration'                 => 30,
                'price'                    => 0.00,
                'color'                    => '#95c93d',
                'appointment_mode'         => 'hybrid',
                'default_meeting_platform' => 'google_meet',
                'requires_credit'          => 0,
            ),
            array(
                'name'                     => 'Coaching Session',
                'description'              => 'Standard 60-minute one-on-one coaching session. Deep dive into your current challenges and create actionable strategies.',
                'duration'                 => 60,
                'price'                    => 175.00,
                'color'                    => '#c16107',
                'appointment_mode'         => 'hybrid',
                'default_meeting_platform' => 'google_meet',
                'requires_credit'          => 1,
            ),
            array(
                'name'                     => 'Extended Coaching Session',
                'description'              => '90-minute intensive session for complex issues or quarterly planning.',
                'duration'                 => 90,
                'price'                    => 250.00,
                'color'                    => '#c18f5f',
                'appointment_mode'         => 'hybrid',
                'default_meeting_platform' => 'zoom',
                'requires_credit'          => 1,
            ),
            array(
                'name'                     => 'VIP Day',
                'description'              => 'Full-day intensive coaching experience (6 hours). Perfect for major life decisions or breakthrough work.',
                'duration'                 => 360,
                'price'                    => 1500.00,
                'color'                    => '#8e44ad',
                'appointment_mode'         => 'in_person',
                'default_meeting_platform' => null,
                'requires_credit'          => 0,
            ),
            array(
                'name'                     => 'Quick Check-In',
                'description'              => '15-minute accountability check-in between regular sessions.',
                'duration'                 => 15,
                'price'                    => 50.00,
                'color'                    => '#3498db',
                'appointment_mode'         => 'virtual',
                'default_meeting_platform' => 'google_meet',
                'requires_credit'          => 0,
            ),
        );

        $service_ids = array();

        foreach ( $services_data as $service ) {
            $this->db->insert(
                $this->tables['services'],
                array(
                    'name'                     => $service['name'],
                    'description'              => $service['description'],
                    'duration'                 => $service['duration'],
                    'price'                    => $service['price'],
                    'color'                    => $service['color'],
                    'status'                   => 'active',
                    'appointment_mode'         => $service['appointment_mode'],
                    'default_meeting_platform' => $service['default_meeting_platform'],
                    'requires_credit'          => $service['requires_credit'],
                    'created_at'               => current_time( 'mysql' ),
                )
            );

            $service_id = $this->db->insert_id;
            if ( $service_id ) {
                $service_ids[] = $service_id;

                // Associate with provider
                $this->db->insert(
                    $this->tables['provider_services'],
                    array(
                        'provider_id' => $provider_id,
                        'service_id'  => $service_id,
                    )
                );
            }
        }

        return $service_ids;
    }

    /**
     * Create sample customers
     */
    private function create_customers() {
        // Clear existing customer data
        $this->db->query( "DELETE FROM {$this->tables['credit_history']}" );
        $this->db->query( "DELETE FROM {$this->tables['customer_flags']}" );
        $this->db->query( "DELETE FROM {$this->tables['customer_purchases']}" );
        $this->db->query( "DELETE FROM {$this->tables['customer_documents']}" );
        $this->db->query( "DELETE FROM {$this->tables['customer_notes']}" );
        $this->db->query( "DELETE FROM {$this->tables['appointments']}" );
        $this->db->query( "DELETE FROM {$this->tables['customers']}" );

        // status enum: 'active', 'paused', 'vip', 'inactive', 'prospect'
        $customers_data = array(
            array(
                'first_name'       => 'Sarah',
                'last_name'        => 'Mitchell',
                'email'            => 'sarah.mitchell@example.com',
                'phone'            => '(555) 123-4567',
                'status'           => 'active',
                'total_credits'    => 8,
                'google_drive_url' => 'https://drive.google.com/drive/folders/example-sarah',
                'tags'             => 'career-transition,executive',
                'notes'            => 'VP of Marketing transitioning to entrepreneurship.',
            ),
            array(
                'first_name'       => 'Michael',
                'last_name'        => 'Chen',
                'email'            => 'michael.chen@example.com',
                'phone'            => '(555) 234-5678',
                'status'           => 'vip',
                'total_credits'    => 12,
                'google_drive_url' => 'https://drive.google.com/drive/folders/example-michael',
                'tags'             => 'vip,startup-founder,quarterly-planning',
                'notes'            => 'Tech startup founder, quarterly planning client.',
            ),
            array(
                'first_name'       => 'Jennifer',
                'last_name'        => 'Rodriguez',
                'email'            => 'jennifer.r@example.com',
                'phone'            => '(555) 345-6789',
                'status'           => 'active',
                'total_credits'    => 4,
                'google_drive_url' => null,
                'tags'             => 'new-client,work-life-balance',
                'notes'            => 'Working mother seeking better work-life integration.',
            ),
            array(
                'first_name'       => 'David',
                'last_name'        => 'Thompson',
                'email'            => 'david.thompson@example.com',
                'phone'            => '(555) 456-7890',
                'status'           => 'paused',
                'total_credits'    => 2,
                'google_drive_url' => null,
                'tags'             => 'career-change,paused',
                'notes'            => 'On hold while relocating to new city.',
            ),
            array(
                'first_name'       => 'Amanda',
                'last_name'        => 'Foster',
                'email'            => 'amanda.foster@example.com',
                'phone'            => '(555) 567-8901',
                'status'           => 'prospect',
                'total_credits'    => 0,
                'google_drive_url' => null,
                'tags'             => 'prospect,referral',
                'notes'            => 'Referred by Michael Chen, interested in executive coaching.',
            ),
            array(
                'first_name'       => 'Robert',
                'last_name'        => 'Williams',
                'email'            => 'robert.w@example.com',
                'phone'            => '(555) 678-9012',
                'status'           => 'active',
                'total_credits'    => 6,
                'google_drive_url' => 'https://drive.google.com/drive/folders/example-robert',
                'tags'             => 'leadership,management',
                'notes'            => 'New manager developing leadership skills.',
            ),
        );

        $customer_ids = array();

        foreach ( $customers_data as $index => $customer ) {
            $days_ago = 300 - ( $index * 45 );

            $this->db->insert(
                $this->tables['customers'],
                array(
                    'first_name'       => $customer['first_name'],
                    'last_name'        => $customer['last_name'],
                    'email'            => $customer['email'],
                    'phone'            => $customer['phone'],
                    'status'           => $customer['status'],
                    'total_credits'    => $customer['total_credits'],
                    'google_drive_url' => $customer['google_drive_url'],
                    'tags'             => $customer['tags'],
                    'notes'            => $customer['notes'],
                    'created_at'       => date( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ),
                )
            );

            if ( $this->db->insert_id ) {
                $customer_ids[] = $this->db->insert_id;
            }
        }

        return $customer_ids;
    }

    /**
     * Create sample appointments
     */
    private function create_appointments( $services, $customers, $provider_id ) {
        if ( empty( $services ) || empty( $customers ) ) {
            return array();
        }

        $appointment_ids = array();

        $meeting_links = array(
            'google_meet' => 'https://meet.google.com/abc-defg-hij',
            'zoom'        => 'https://zoom.us/j/1234567890',
        );

        // Past appointments (completed)
        for ( $i = 0; $i < 15; $i++ ) {
            $customer_id = $customers[ array_rand( $customers ) ];
            $service_id = $services[ array_rand( $services ) ];

            // Get service details
            $service = $this->db->get_row( $this->db->prepare(
                "SELECT * FROM {$this->tables['services']} WHERE id = %d",
                $service_id
            ) );

            if ( ! $service ) {
                continue;
            }

            $days_ago = rand( 7, 90 );
            $hour = rand( 9, 15 );
            $booking_date = date( 'Y-m-d', strtotime( "-{$days_ago} days" ) );
            $booking_time = sprintf( '%02d:00:00', $hour );
            $end_time = date( 'H:i:s', strtotime( $booking_time ) + ( $service->duration * 60 ) );

            $mode = $service->appointment_mode === 'hybrid' ? ( rand( 0, 1 ) ? 'virtual' : 'in_person' ) : $service->appointment_mode;
            $platform = ( $mode === 'virtual' ) ? 'google_meet' : null;
            $link = ( $mode === 'virtual' && $platform ) ? $meeting_links[ $platform ] : null;

            $this->db->insert(
                $this->tables['appointments'],
                array(
                    'customer_id'      => $customer_id,
                    'service_id'       => $service_id,
                    'provider_id'      => $provider_id,
                    'booking_date'     => $booking_date,
                    'booking_time'     => $booking_time,
                    'end_time'         => $end_time,
                    'status'           => 'completed',
                    'appointment_mode' => $mode,
                    'meeting_platform' => $platform,
                    'meeting_link'     => $link,
                    'internal_notes'   => 'Great session! Made progress on key goals.',
                    'created_at'       => date( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days -3 days" ) ),
                )
            );

            if ( $this->db->insert_id ) {
                $appointment_ids[] = $this->db->insert_id;
            }
        }

        // Upcoming appointments
        for ( $i = 0; $i < 8; $i++ ) {
            $customer_id = $customers[ array_rand( array_slice( $customers, 0, 4 ) ) ];
            $service_id = $services[ array_rand( array_slice( $services, 0, 3 ) ) ];

            $service = $this->db->get_row( $this->db->prepare(
                "SELECT * FROM {$this->tables['services']} WHERE id = %d",
                $service_id
            ) );

            if ( ! $service ) {
                continue;
            }

            $days_ahead = rand( 1, 21 );
            $hour = rand( 9, 15 );
            $booking_date = date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );
            $booking_time = sprintf( '%02d:00:00', $hour );
            $end_time = date( 'H:i:s', strtotime( $booking_time ) + ( $service->duration * 60 ) );

            $status = $i < 6 ? 'approved' : 'pending';

            $this->db->insert(
                $this->tables['appointments'],
                array(
                    'customer_id'      => $customer_id,
                    'service_id'       => $service_id,
                    'provider_id'      => $provider_id,
                    'booking_date'     => $booking_date,
                    'booking_time'     => $booking_time,
                    'end_time'         => $end_time,
                    'status'           => $status,
                    'appointment_mode' => 'virtual',
                    'meeting_platform' => 'google_meet',
                    'meeting_link'     => $meeting_links['google_meet'],
                    'created_at'       => current_time( 'mysql' ),
                )
            );

            if ( $this->db->insert_id ) {
                $appointment_ids[] = $this->db->insert_id;
            }
        }

        return $appointment_ids;
    }

    /**
     * Create sample customer notes
     */
    private function create_notes( $customers ) {
        if ( empty( $customers ) ) {
            return array();
        }

        // note_type enum: 'general', 'session', 'follow_up', 'alert', 'private'
        $note_templates = array(
            array(
                'note_text' => 'Initial consultation completed. Client is motivated and clear on their goals. Recommended starting with weekly sessions.',
                'note_type' => 'session',
                'is_pinned' => 1,
            ),
            array(
                'note_text' => 'Follow-up on career transition plan. Client has updated resume and started networking. Great progress!',
                'note_type' => 'follow_up',
                'is_pinned' => 0,
            ),
            array(
                'note_text' => 'Discussed work-life balance strategies. Introduced time-blocking technique. Client will implement this week.',
                'note_type' => 'session',
                'is_pinned' => 0,
            ),
            array(
                'note_text' => 'Quarterly review session. Achieved 3 of 4 goals. Setting new targets for next quarter.',
                'note_type' => 'session',
                'is_pinned' => 1,
            ),
            array(
                'note_text' => 'Client requested to move to bi-weekly sessions starting next month.',
                'note_type' => 'general',
                'is_pinned' => 0,
            ),
        );

        $note_ids = array();
        $admin_id = get_current_user_id() ?: 1;

        foreach ( $customers as $customer_id ) {
            $num_notes = rand( 1, 3 );

            for ( $i = 0; $i < $num_notes; $i++ ) {
                $template = $note_templates[ array_rand( $note_templates ) ];
                $days_ago = rand( 1, 60 );

                $this->db->insert(
                    $this->tables['customer_notes'],
                    array(
                        'customer_id' => $customer_id,
                        'user_id'     => $admin_id,
                        'note_text'   => $template['note_text'],
                        'note_type'   => $template['note_type'],
                        'is_pinned'   => $template['is_pinned'],
                        'created_at'  => date( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ),
                    )
                );

                if ( $this->db->insert_id ) {
                    $note_ids[] = $this->db->insert_id;
                }
            }
        }

        return $note_ids;
    }

    /**
     * Create sample purchases
     */
    private function create_purchases( $customers ) {
        if ( empty( $customers ) ) {
            return array();
        }

        // purchase_type enum: 'service', 'package', 'credit', 'product', 'other'
        $purchase_types = array(
            array(
                'purchase_type'   => 'package',
                'description'     => '10-Session Coaching Package',
                'amount'          => 1500.00,
                'credits_granted' => 10,
            ),
            array(
                'purchase_type'   => 'package',
                'description'     => '5-Session Coaching Package',
                'amount'          => 800.00,
                'credits_granted' => 5,
            ),
            array(
                'purchase_type'   => 'service',
                'description'     => 'Single Coaching Session',
                'amount'          => 175.00,
                'credits_granted' => 0,
            ),
            array(
                'purchase_type'   => 'product',
                'description'     => 'Goal Setting Workbook (Digital)',
                'amount'          => 47.00,
                'credits_granted' => 0,
            ),
        );

        $purchase_ids = array();

        foreach ( $customers as $customer_id ) {
            $num_purchases = rand( 0, 2 );

            for ( $i = 0; $i < $num_purchases; $i++ ) {
                $purchase = $purchase_types[ array_rand( $purchase_types ) ];
                $days_ago = rand( 30, 180 );

                $this->db->insert(
                    $this->tables['customer_purchases'],
                    array(
                        'customer_id'    => $customer_id,
                        'purchase_type'  => $purchase['purchase_type'],
                        'description'    => $purchase['description'],
                        'amount'         => $purchase['amount'],
                        'credits_granted'=> $purchase['credits_granted'],
                        'purchase_date'  => date( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ),
                    )
                );

                if ( $this->db->insert_id ) {
                    $purchase_ids[] = $this->db->insert_id;
                }
            }
        }

        return $purchase_ids;
    }

    /**
     * Create sample customer flags
     */
    private function create_flags( $customers ) {
        if ( empty( $customers ) ) {
            return array();
        }

        // flag_type enum: 'follow_up', 'inactive', 'birthday', 'vip_check', 'payment_due', 'custom'
        $flag_templates = array(
            array(
                'flag_type' => 'follow_up',
                'message'   => 'Schedule follow-up call - Client requested a check-in next week',
            ),
            array(
                'flag_type' => 'payment_due',
                'message'   => 'Package renewal due - Coaching package expires in 2 weeks',
            ),
            array(
                'flag_type' => 'birthday',
                'message'   => 'Client anniversary coming up next month - consider sending gift',
            ),
            array(
                'flag_type' => 'custom',
                'message'   => 'VIP Day candidate - Client mentioned major career decision coming up',
            ),
        );

        $flag_ids = array();
        $admin_id = get_current_user_id() ?: 1;

        // Add flags to first few customers
        $flagged_customers = array_slice( $customers, 0, min( 4, count( $customers ) ) );

        foreach ( $flagged_customers as $index => $customer_id ) {
            $template = $flag_templates[ $index % count( $flag_templates ) ];

            $this->db->insert(
                $this->tables['customer_flags'],
                array(
                    'customer_id' => $customer_id,
                    'flag_type'   => $template['flag_type'],
                    'message'     => $template['message'],
                    'is_active'   => 1,
                    'created_at'  => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 1, 14 ) . ' days' ) ),
                )
            );

            if ( $this->db->insert_id ) {
                $flag_ids[] = $this->db->insert_id;
            }
        }

        return $flag_ids;
    }

    /**
     * Create credit history
     */
    private function create_credit_history( $customers ) {
        if ( empty( $customers ) ) {
            return array();
        }

        $history_ids = array();
        $admin_id = get_current_user_id() ?: 1;

        foreach ( $customers as $customer_id ) {
            $customer = $this->db->get_row( $this->db->prepare(
                "SELECT * FROM {$this->tables['customers']} WHERE id = %d",
                $customer_id
            ) );

            if ( ! $customer || $customer->total_credits <= 0 ) {
                continue;
            }

            // Initial package purchase
            $initial_credits = $customer->total_credits + rand( 2, 5 );

            $this->db->insert(
                $this->tables['credit_history'],
                array(
                    'customer_id' => $customer_id,
                    'delta'       => $initial_credits,
                    'reason'      => 'Coaching Package Purchase - 10 Sessions',
                    'old_balance' => 0,
                    'new_balance' => $initial_credits,
                    'reference_type' => 'purchase',
                    'created_by'  => $admin_id,
                    'created_at'  => date( 'Y-m-d H:i:s', strtotime( '-90 days' ) ),
                )
            );

            if ( $this->db->insert_id ) {
                $history_ids[] = $this->db->insert_id;
            }

            // Add usage entries
            $credits_used = $initial_credits - $customer->total_credits;
            $current_balance = $initial_credits;

            for ( $i = 0; $i < $credits_used; $i++ ) {
                $days_ago = 80 - ( $i * 10 );
                if ( $days_ago < 1 ) {
                    $days_ago = 1;
                }

                $old_balance = $current_balance;
                $current_balance--;

                $this->db->insert(
                    $this->tables['credit_history'],
                    array(
                        'customer_id' => $customer_id,
                        'delta'       => -1,
                        'reason'      => 'Coaching Session',
                        'old_balance' => $old_balance,
                        'new_balance' => $current_balance,
                        'reference_type' => 'appointment',
                        'created_by'  => $admin_id,
                        'created_at'  => date( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ),
                    )
                );

                if ( $this->db->insert_id ) {
                    $history_ids[] = $this->db->insert_id;
                }
            }
        }

        return $history_ids;
    }

    /**
     * Clear all sample data
     */
    public function clear() {
        echo "=== Clearing GuidePost Sample Data ===\n\n";

        $this->db->query( "DELETE FROM {$this->tables['credit_history']}" );
        echo "Cleared credit history\n";

        $this->db->query( "DELETE FROM {$this->tables['customer_flags']}" );
        echo "Cleared customer flags\n";

        $this->db->query( "DELETE FROM {$this->tables['customer_purchases']}" );
        echo "Cleared customer purchases\n";

        $this->db->query( "DELETE FROM {$this->tables['customer_documents']}" );
        echo "Cleared customer documents\n";

        $this->db->query( "DELETE FROM {$this->tables['customer_notes']}" );
        echo "Cleared customer notes\n";

        $this->db->query( "DELETE FROM {$this->tables['appointments']}" );
        echo "Cleared appointments\n";

        $this->db->query( "DELETE FROM {$this->tables['customers']}" );
        echo "Cleared customers\n";

        $this->db->query( "DELETE FROM {$this->tables['provider_services']}" );
        echo "Cleared provider-service links\n";

        $this->db->query( "DELETE FROM {$this->tables['services']}" );
        echo "Cleared services\n";

        $this->db->query( "DELETE FROM {$this->tables['working_hours']}" );
        echo "Cleared working hours\n";

        $this->db->query( "DELETE FROM {$this->tables['providers']}" );
        echo "Cleared providers\n";

        echo "\n=== Sample Data Cleared ===\n";

        return true;
    }
}

// Run the generator
$generator = new GuidePost_Sample_Data();

// Check for --clear flag
$clear = false;
if ( isset( $argv ) && is_array( $argv ) ) {
    foreach ( $argv as $arg ) {
        if ( $arg === '--clear' ) {
            $clear = true;
            break;
        }
    }
}

if ( $clear ) {
    $generator->clear();
} else {
    $generator->generate();
}
