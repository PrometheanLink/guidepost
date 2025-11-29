<?php
/**
 * Shortcodes for frontend booking
 *
 * @package GuidePost
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcodes class
 */
class GuidePost_Shortcodes {

    /**
     * Initialize shortcodes
     */
    public static function init() {
        add_shortcode( 'guidepost_booking', array( __CLASS__, 'booking_form' ) );
        add_shortcode( 'guidepost_services', array( __CLASS__, 'services_list' ) );
    }

    /**
     * Booking form shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function booking_form( $atts ) {
        $atts = shortcode_atts(
            array(
                'service'  => '',
                'provider' => '',
            ),
            $atts,
            'guidepost_booking'
        );

        ob_start();
        ?>
        <div class="guidepost-booking" data-service="<?php echo esc_attr( $atts['service'] ); ?>" data-provider="<?php echo esc_attr( $atts['provider'] ); ?>">

            <!-- Step 1: Service Selection -->
            <div class="guidepost-step guidepost-step-service active" data-step="1">
                <h3 class="guidepost-step-title"><?php esc_html_e( 'Select a Service', 'guidepost' ); ?></h3>
                <div class="guidepost-services-grid">
                    <?php echo self::get_services_html(); ?>
                </div>
            </div>

            <!-- Step 2: Provider Selection -->
            <div class="guidepost-step guidepost-step-provider" data-step="2">
                <h3 class="guidepost-step-title"><?php esc_html_e( 'Select a Provider', 'guidepost' ); ?></h3>
                <div class="guidepost-providers-grid">
                    <!-- Populated via JS -->
                </div>
                <button type="button" class="guidepost-btn guidepost-btn-back"><?php esc_html_e( 'Back', 'guidepost' ); ?></button>
            </div>

            <!-- Step 3: Date/Time Selection -->
            <div class="guidepost-step guidepost-step-datetime" data-step="3">
                <h3 class="guidepost-step-title"><?php esc_html_e( 'Select Date & Time', 'guidepost' ); ?></h3>

                <div class="guidepost-datetime-container">
                    <!-- Calendar -->
                    <div class="guidepost-calendar">
                        <div class="guidepost-calendar-header">
                            <button type="button" class="guidepost-calendar-nav guidepost-calendar-prev">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                            </button>
                            <span class="guidepost-calendar-month"></span>
                            <button type="button" class="guidepost-calendar-nav guidepost-calendar-next">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                        </div>
                        <div class="guidepost-calendar-weekdays">
                            <span><?php esc_html_e( 'Sun', 'guidepost' ); ?></span>
                            <span><?php esc_html_e( 'Mon', 'guidepost' ); ?></span>
                            <span><?php esc_html_e( 'Tue', 'guidepost' ); ?></span>
                            <span><?php esc_html_e( 'Wed', 'guidepost' ); ?></span>
                            <span><?php esc_html_e( 'Thu', 'guidepost' ); ?></span>
                            <span><?php esc_html_e( 'Fri', 'guidepost' ); ?></span>
                            <span><?php esc_html_e( 'Sat', 'guidepost' ); ?></span>
                        </div>
                        <div class="guidepost-calendar-days">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <!-- Time Slots -->
                    <div class="guidepost-time-slots">
                        <h4><?php esc_html_e( 'Available Times', 'guidepost' ); ?></h4>
                        <div class="guidepost-slots-container">
                            <!-- Populated via JS -->
                            <p class="guidepost-select-date-msg"><?php esc_html_e( 'Please select a date', 'guidepost' ); ?></p>
                        </div>
                    </div>
                </div>

                <button type="button" class="guidepost-btn guidepost-btn-back"><?php esc_html_e( 'Back', 'guidepost' ); ?></button>
            </div>

            <!-- Step 4: Customer Information -->
            <div class="guidepost-step guidepost-step-customer" data-step="4">
                <h3 class="guidepost-step-title"><?php esc_html_e( 'Your Information', 'guidepost' ); ?></h3>

                <form class="guidepost-customer-form">
                    <div class="guidepost-form-row">
                        <div class="guidepost-form-field">
                            <label for="guidepost-first-name"><?php esc_html_e( 'First Name', 'guidepost' ); ?> *</label>
                            <input type="text" id="guidepost-first-name" name="first_name" required>
                        </div>
                        <div class="guidepost-form-field">
                            <label for="guidepost-last-name"><?php esc_html_e( 'Last Name', 'guidepost' ); ?> *</label>
                            <input type="text" id="guidepost-last-name" name="last_name" required>
                        </div>
                    </div>
                    <div class="guidepost-form-row">
                        <div class="guidepost-form-field">
                            <label for="guidepost-email"><?php esc_html_e( 'Email', 'guidepost' ); ?> *</label>
                            <input type="email" id="guidepost-email" name="email" required>
                        </div>
                        <div class="guidepost-form-field">
                            <label for="guidepost-phone"><?php esc_html_e( 'Phone', 'guidepost' ); ?></label>
                            <input type="tel" id="guidepost-phone" name="phone">
                        </div>
                    </div>
                    <div class="guidepost-form-field">
                        <label for="guidepost-notes"><?php esc_html_e( 'Notes', 'guidepost' ); ?></label>
                        <textarea id="guidepost-notes" name="notes" rows="3"></textarea>
                    </div>
                </form>

                <div class="guidepost-booking-summary">
                    <h4><?php esc_html_e( 'Booking Summary', 'guidepost' ); ?></h4>
                    <div class="guidepost-summary-details">
                        <!-- Populated via JS -->
                    </div>
                </div>

                <div class="guidepost-step-actions">
                    <button type="button" class="guidepost-btn guidepost-btn-back"><?php esc_html_e( 'Back', 'guidepost' ); ?></button>
                    <button type="button" class="guidepost-btn guidepost-btn-primary guidepost-btn-book"><?php esc_html_e( 'Book Appointment', 'guidepost' ); ?></button>
                </div>
            </div>

            <!-- Step 5: Confirmation -->
            <div class="guidepost-step guidepost-step-confirmation" data-step="5">
                <div class="guidepost-confirmation-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h3 class="guidepost-step-title"><?php esc_html_e( 'Booking Confirmed!', 'guidepost' ); ?></h3>
                <p class="guidepost-confirmation-message"><?php esc_html_e( 'Your appointment has been successfully booked. A confirmation email has been sent to your email address.', 'guidepost' ); ?></p>
                <div class="guidepost-confirmation-details">
                    <!-- Populated via JS -->
                </div>
                <button type="button" class="guidepost-btn guidepost-btn-primary guidepost-btn-new"><?php esc_html_e( 'Book Another', 'guidepost' ); ?></button>
            </div>

            <!-- Loading overlay -->
            <div class="guidepost-loading">
                <div class="guidepost-spinner"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Services list shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function services_list( $atts ) {
        $atts = shortcode_atts(
            array(
                'columns' => 3,
            ),
            $atts,
            'guidepost_services'
        );

        ob_start();
        ?>
        <div class="guidepost-services-list" style="--columns: <?php echo absint( $atts['columns'] ); ?>">
            <?php echo self::get_services_html( true ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get services HTML
     *
     * @param bool $show_description Whether to show description.
     * @return string HTML output.
     */
    private static function get_services_html( $show_description = false ) {
        global $wpdb;

        $tables = GuidePost_Database::get_table_names();
        $services = $wpdb->get_results(
            "SELECT * FROM {$tables['services']} WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
        );

        if ( empty( $services ) ) {
            return '<p class="guidepost-no-services">' . esc_html__( 'No services available.', 'guidepost' ) . '</p>';
        }

        $html = '';
        foreach ( $services as $service ) {
            $html .= '<div class="guidepost-service-card" data-service-id="' . esc_attr( $service->id ) . '">';
            $html .= '<div class="guidepost-service-color" style="background-color: ' . esc_attr( $service->color ?? '#c16107' ) . '"></div>';
            $html .= '<div class="guidepost-service-info">';
            $html .= '<h4 class="guidepost-service-name">' . esc_html( $service->name ) . '</h4>';
            if ( $show_description && ! empty( $service->description ) ) {
                $html .= '<p class="guidepost-service-description">' . esc_html( $service->description ?? '' ) . '</p>';
            }
            $html .= '<div class="guidepost-service-meta">';
            $html .= '<span class="guidepost-service-duration">' . esc_html( $service->duration ) . ' ' . esc_html__( 'min', 'guidepost' ) . '</span>';
            if ( $service->price > 0 ) {
                $price_formatted = function_exists( 'wc_price' )
                    ? wc_price( $service->price )
                    : '$' . number_format( $service->price, 2 );
                $html .= '<span class="guidepost-service-price">' . $price_formatted . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }
}
