# Appointment Edit Page - Implementation DIFF Proposal

Based on analysis of the design document and current implementation files.

---

## Summary of Changes

| File | Changes | Lines Added |
|------|---------|-------------|
| `class-guidepost-admin.php` | Routing, form, handlers | ~450 |
| `admin.js` | Calendar popup edit, form interactions | ~90 |
| `admin.css` | Form styling | ~120 |
| **Total** | | **~660** |

---

## File 1: class-guidepost-admin.php

### CHANGE 1.1: Update handle_form_submissions()

**Location:** After existing form handlers (around line 80)

```php
// Handle appointment update
if ( isset( $_POST['guidepost_update_appointment_nonce'] ) &&
     wp_verify_nonce( $_POST['guidepost_update_appointment_nonce'], 'guidepost_update_appointment' ) ) {
    $this->handle_update_appointment();
}

// Handle appointment deletion
if ( isset( $_GET['action'] ) && 'delete_appointment' === $_GET['action'] &&
     isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_appointment_' . $_GET['id'] ) ) {
        $this->handle_delete_appointment( absint( $_GET['id'] ) );
    }
}
```

---

### CHANGE 1.2: Update render_appointments_page() Routing

**Location:** Beginning of render_appointments_page() method (around line 461)

**Add after method opening, before existing code:**

```php
public function render_appointments_page() {
    global $wpdb;
    $tables = GuidePost_Database::get_table_names();

    // Route to edit form if action=edit
    $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
    $appointment_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    if ( 'edit' === $action && $appointment_id > 0 ) {
        $this->render_appointment_edit_form( $appointment_id );
        return;
    }

    // ... rest of existing method continues ...
```

---

### CHANGE 1.3: Add Edit Button to Appointments List Table

**Location:** In the appointments list table actions column (around line 740)

**Current code structure - add Edit button before status form:**

```php
<td>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-appointments&action=edit&id=' . $apt->id ) ); ?>"
       class="button button-small" title="<?php esc_attr_e( 'Edit', 'guidepost' ); ?>">
        <span class="dashicons dashicons-edit" style="vertical-align: middle;"></span>
    </a>
    <!-- existing status change form follows -->
```

---

### CHANGE 1.4: Add render_appointment_edit_form() Method

**Location:** After render_appointments_page() method (around line 800)

```php
/**
 * Render appointment edit form
 *
 * @param int $appointment_id Appointment ID.
 */
private function render_appointment_edit_form( $appointment_id ) {
    global $wpdb;
    $tables = GuidePost_Database::get_table_names();

    // Fetch appointment with related data
    $appointment = $wpdb->get_row( $wpdb->prepare(
        "SELECT a.*,
                s.name as service_name, s.duration as service_duration, s.price as service_price,
                p.name as provider_name,
                c.first_name, c.last_name, c.email as customer_email
         FROM {$tables['appointments']} a
         LEFT JOIN {$tables['services']} s ON a.service_id = s.id
         LEFT JOIN {$tables['providers']} p ON a.provider_id = p.id
         LEFT JOIN {$tables['customers']} c ON a.customer_id = c.id
         WHERE a.id = %d",
        $appointment_id
    ) );

    if ( ! $appointment ) {
        echo '<div class="wrap"><div class="notice notice-error"><p>' .
             esc_html__( 'Appointment not found.', 'guidepost' ) . '</p></div></div>';
        return;
    }

    // Get lookup data
    $customers = $wpdb->get_results( "SELECT id, first_name, last_name, email FROM {$tables['customers']} ORDER BY last_name, first_name" );
    $services = $wpdb->get_results( "SELECT id, name, duration, price FROM {$tables['services']} WHERE status = 'active' ORDER BY name" );
    $providers = $wpdb->get_results( "SELECT id, name FROM {$tables['providers']} WHERE status = 'active' ORDER BY name" );

    // Status options
    $statuses = array(
        'pending'   => __( 'Pending', 'guidepost' ),
        'approved'  => __( 'Approved', 'guidepost' ),
        'completed' => __( 'Completed', 'guidepost' ),
        'canceled'  => __( 'Canceled', 'guidepost' ),
        'no_show'   => __( 'No Show', 'guidepost' ),
    );

    // Meeting platforms
    $platforms = array(
        ''            => __( 'Select Platform', 'guidepost' ),
        'zoom'        => __( 'Zoom', 'guidepost' ),
        'google_meet' => __( 'Google Meet', 'guidepost' ),
        'teams'       => __( 'Microsoft Teams', 'guidepost' ),
        'other'       => __( 'Other', 'guidepost' ),
    );

    ?>
    <div class="wrap guidepost-admin">
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-appointments' ) ); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Back to Appointments', 'guidepost' ); ?>
            </a>
        </p>

        <div class="guidepost-page-header">
            <h1><?php esc_html_e( 'Edit Appointment', 'guidepost' ); ?> #<?php echo esc_html( $appointment_id ); ?></h1>
            <p class="description">
                <?php
                printf(
                    esc_html__( 'Created: %s', 'guidepost' ),
                    date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $appointment->created_at ) )
                );
                ?>
            </p>
        </div>

        <?php $this->render_admin_notices(); ?>

        <form method="post" class="guidepost-appointment-form">
            <?php wp_nonce_field( 'guidepost_update_appointment', 'guidepost_update_appointment_nonce' ); ?>
            <input type="hidden" name="appointment_id" value="<?php echo esc_attr( $appointment_id ); ?>">

            <div class="guidepost-form-columns">
                <!-- Left Column -->
                <div class="guidepost-form-column">

                    <!-- Scheduling Section -->
                    <div class="guidepost-form-section">
                        <h3><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Scheduling', 'guidepost' ); ?></h3>

                        <div class="guidepost-form-group">
                            <label for="customer_id"><?php esc_html_e( 'Customer', 'guidepost' ); ?> <span class="required">*</span></label>
                            <select name="customer_id" id="customer_id" required>
                                <option value=""><?php esc_html_e( 'Select Customer', 'guidepost' ); ?></option>
                                <?php foreach ( $customers as $customer ) : ?>
                                    <option value="<?php echo esc_attr( $customer->id ); ?>" <?php selected( $appointment->customer_id, $customer->id ); ?>>
                                        <?php echo esc_html( $customer->last_name . ', ' . $customer->first_name . ' (' . $customer->email . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="guidepost-form-group">
                            <label for="booking_date"><?php esc_html_e( 'Date', 'guidepost' ); ?> <span class="required">*</span></label>
                            <input type="date" name="booking_date" id="booking_date"
                                   value="<?php echo esc_attr( $appointment->booking_date ); ?>" required>
                        </div>

                        <div class="guidepost-form-group">
                            <label for="booking_time"><?php esc_html_e( 'Time', 'guidepost' ); ?> <span class="required">*</span></label>
                            <input type="time" name="booking_time" id="booking_time"
                                   value="<?php echo esc_attr( substr( $appointment->booking_time, 0, 5 ) ); ?>" required>
                            <small>
                                <?php
                                printf(
                                    esc_html__( 'End time: %s (based on service duration)', 'guidepost' ),
                                    '<span id="end-time-display">' . esc_html( date( 'g:i A', strtotime( $appointment->end_time ) ) ) . '</span>'
                                );
                                ?>
                            </small>
                        </div>

                        <div id="availability-check"></div>
                    </div>

                    <!-- Appointment Mode Section -->
                    <div class="guidepost-form-section">
                        <h3><span class="dashicons dashicons-location"></span> <?php esc_html_e( 'Appointment Mode', 'guidepost' ); ?></h3>

                        <div class="guidepost-form-group">
                            <label>
                                <input type="radio" name="appointment_mode" value="in_person"
                                       <?php checked( $appointment->appointment_mode, 'in_person' ); ?>>
                                <?php esc_html_e( 'In Person', 'guidepost' ); ?>
                            </label>
                            <label style="margin-left: 20px;">
                                <input type="radio" name="appointment_mode" value="virtual"
                                       <?php checked( $appointment->appointment_mode, 'virtual' ); ?>>
                                <?php esc_html_e( 'Virtual', 'guidepost' ); ?>
                            </label>
                        </div>

                        <!-- In Person Fields -->
                        <div id="in-person-fields" class="guidepost-conditional-fields"
                             style="<?php echo ( 'virtual' === $appointment->appointment_mode ) ? 'display:none;' : ''; ?>">
                            <div class="guidepost-form-group">
                                <label for="location"><?php esc_html_e( 'Location', 'guidepost' ); ?></label>
                                <textarea name="location" id="location" rows="2"><?php echo esc_textarea( $appointment->location ); ?></textarea>
                            </div>
                        </div>

                        <!-- Virtual Fields -->
                        <div id="virtual-fields" class="guidepost-conditional-fields"
                             style="<?php echo ( 'in_person' === $appointment->appointment_mode || empty( $appointment->appointment_mode ) ) ? 'display:none;' : ''; ?>">
                            <div class="guidepost-form-group">
                                <label for="meeting_platform"><?php esc_html_e( 'Platform', 'guidepost' ); ?></label>
                                <select name="meeting_platform" id="meeting_platform">
                                    <?php foreach ( $platforms as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $appointment->meeting_platform, $value ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="guidepost-form-group">
                                <label for="meeting_link"><?php esc_html_e( 'Meeting Link', 'guidepost' ); ?></label>
                                <input type="url" name="meeting_link" id="meeting_link"
                                       value="<?php echo esc_url( $appointment->meeting_link ); ?>"
                                       placeholder="https://...">
                            </div>

                            <div class="guidepost-form-group">
                                <label for="meeting_password"><?php esc_html_e( 'Meeting Password', 'guidepost' ); ?></label>
                                <input type="text" name="meeting_password" id="meeting_password"
                                       value="<?php echo esc_attr( $appointment->meeting_password ); ?>">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column -->
                <div class="guidepost-form-column">

                    <!-- Appointment Info Section -->
                    <div class="guidepost-form-section">
                        <h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Appointment Info', 'guidepost' ); ?></h3>

                        <div class="guidepost-form-group">
                            <label for="status"><?php esc_html_e( 'Status', 'guidepost' ); ?> <span class="required">*</span></label>
                            <select name="status" id="status" required>
                                <?php foreach ( $statuses as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $appointment->status, $value ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="guidepost-form-group">
                            <label for="service_id"><?php esc_html_e( 'Service', 'guidepost' ); ?> <span class="required">*</span></label>
                            <select name="service_id" id="service_id" required>
                                <?php foreach ( $services as $service ) : ?>
                                    <option value="<?php echo esc_attr( $service->id ); ?>"
                                            data-duration="<?php echo esc_attr( $service->duration ); ?>"
                                            data-price="<?php echo esc_attr( $service->price ); ?>"
                                            <?php selected( $appointment->service_id, $service->id ); ?>>
                                        <?php echo esc_html( $service->name ); ?>
                                        (<?php echo esc_html( $service->duration ); ?> min - $<?php echo esc_html( number_format( $service->price, 2 ) ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="guidepost-form-group">
                            <label for="provider_id"><?php esc_html_e( 'Provider', 'guidepost' ); ?> <span class="required">*</span></label>
                            <select name="provider_id" id="provider_id" required>
                                <?php foreach ( $providers as $provider ) : ?>
                                    <option value="<?php echo esc_attr( $provider->id ); ?>" <?php selected( $appointment->provider_id, $provider->id ); ?>>
                                        <?php echo esc_html( $provider->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="guidepost-form-group">
                            <label for="credits_used"><?php esc_html_e( 'Credits Used', 'guidepost' ); ?></label>
                            <input type="number" name="credits_used" id="credits_used" min="0"
                                   value="<?php echo esc_attr( $appointment->credits_used ); ?>">
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="guidepost-form-section">
                        <h3><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Notes', 'guidepost' ); ?></h3>

                        <div class="guidepost-form-group">
                            <label for="internal_notes"><?php esc_html_e( 'Internal Notes', 'guidepost' ); ?></label>
                            <textarea name="internal_notes" id="internal_notes" rows="3"><?php echo esc_textarea( $appointment->internal_notes ); ?></textarea>
                            <small><?php esc_html_e( 'Only visible to admins', 'guidepost' ); ?></small>
                        </div>

                        <div class="guidepost-form-group">
                            <label for="admin_notes"><?php esc_html_e( 'Admin Notes', 'guidepost' ); ?></label>
                            <textarea name="admin_notes" id="admin_notes" rows="3"><?php echo esc_textarea( $appointment->admin_notes ); ?></textarea>
                        </div>

                        <div class="guidepost-form-group">
                            <label for="customer_notes"><?php esc_html_e( 'Customer Notes', 'guidepost' ); ?></label>
                            <textarea name="customer_notes" id="customer_notes" rows="3"><?php echo esc_textarea( $appointment->customer_notes ); ?></textarea>
                            <small><?php esc_html_e( 'May be visible to customer in communications', 'guidepost' ); ?></small>
                        </div>
                    </div>

                    <!-- Follow-up Section -->
                    <div class="guidepost-form-section">
                        <h3><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Follow-up', 'guidepost' ); ?></h3>

                        <div class="guidepost-form-group">
                            <label for="follow_up_date"><?php esc_html_e( 'Follow-up Date', 'guidepost' ); ?></label>
                            <input type="date" name="follow_up_date" id="follow_up_date"
                                   value="<?php echo esc_attr( $appointment->follow_up_date ); ?>">
                        </div>

                        <div class="guidepost-form-group">
                            <label for="follow_up_notes"><?php esc_html_e( 'Follow-up Notes', 'guidepost' ); ?></label>
                            <textarea name="follow_up_notes" id="follow_up_notes" rows="2"><?php echo esc_textarea( $appointment->follow_up_notes ); ?></textarea>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Form Actions -->
            <div class="guidepost-form-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="vertical-align: middle;"></span>
                    <?php esc_html_e( 'Save Changes', 'guidepost' ); ?>
                </button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=guidepost-appointments' ) ); ?>" class="button button-large">
                    <?php esc_html_e( 'Cancel', 'guidepost' ); ?>
                </a>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=guidepost-appointments&action=delete_appointment&id=' . $appointment_id ), 'delete_appointment_' . $appointment_id ) ); ?>"
                   class="button button-large" style="color: #a00; margin-left: auto;"
                   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this appointment? This cannot be undone.', 'guidepost' ); ?>');">
                    <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                    <?php esc_html_e( 'Delete', 'guidepost' ); ?>
                </a>
            </div>

        </form>
    </div>
    <?php
}
```

---

### CHANGE 1.5: Add handle_update_appointment() Method

**Location:** After handle_form_submissions() (around line 100)

```php
/**
 * Handle appointment update form submission
 */
private function handle_update_appointment() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Permission denied.', 'guidepost' ) );
    }

    global $wpdb;
    $tables = GuidePost_Database::get_table_names();

    $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;

    if ( ! $appointment_id ) {
        wp_redirect( add_query_arg( array(
            'page' => 'guidepost-appointments',
            'error' => urlencode( __( 'Invalid appointment ID.', 'guidepost' ) )
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    // Get service duration for end_time calculation
    $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
    $service = $wpdb->get_row( $wpdb->prepare(
        "SELECT duration FROM {$tables['services']} WHERE id = %d",
        $service_id
    ) );

    $booking_time = isset( $_POST['booking_time'] ) ? sanitize_text_field( $_POST['booking_time'] ) : '09:00';
    $duration = $service ? $service->duration : 60;
    $end_time = date( 'H:i:s', strtotime( $booking_time ) + ( $duration * 60 ) );

    // Prepare data
    $data = array(
        'customer_id'      => isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0,
        'service_id'       => $service_id,
        'provider_id'      => isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0,
        'booking_date'     => isset( $_POST['booking_date'] ) ? sanitize_text_field( $_POST['booking_date'] ) : '',
        'booking_time'     => $booking_time,
        'end_time'         => $end_time,
        'status'           => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'pending',
        'appointment_mode' => isset( $_POST['appointment_mode'] ) ? sanitize_text_field( $_POST['appointment_mode'] ) : 'in_person',
        'location'         => isset( $_POST['location'] ) ? sanitize_textarea_field( $_POST['location'] ) : '',
        'meeting_platform' => isset( $_POST['meeting_platform'] ) ? sanitize_text_field( $_POST['meeting_platform'] ) : '',
        'meeting_link'     => isset( $_POST['meeting_link'] ) ? esc_url_raw( $_POST['meeting_link'] ) : '',
        'meeting_password' => isset( $_POST['meeting_password'] ) ? sanitize_text_field( $_POST['meeting_password'] ) : '',
        'internal_notes'   => isset( $_POST['internal_notes'] ) ? sanitize_textarea_field( $_POST['internal_notes'] ) : '',
        'admin_notes'      => isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( $_POST['admin_notes'] ) : '',
        'customer_notes'   => isset( $_POST['customer_notes'] ) ? sanitize_textarea_field( $_POST['customer_notes'] ) : '',
        'follow_up_date'   => isset( $_POST['follow_up_date'] ) && ! empty( $_POST['follow_up_date'] ) ? sanitize_text_field( $_POST['follow_up_date'] ) : null,
        'follow_up_notes'  => isset( $_POST['follow_up_notes'] ) ? sanitize_textarea_field( $_POST['follow_up_notes'] ) : '',
        'credits_used'     => isset( $_POST['credits_used'] ) ? absint( $_POST['credits_used'] ) : 0,
        'updated_at'       => current_time( 'mysql' ),
    );

    // Validate required fields
    if ( empty( $data['customer_id'] ) || empty( $data['service_id'] ) || empty( $data['provider_id'] ) ||
         empty( $data['booking_date'] ) || empty( $data['booking_time'] ) ) {
        wp_redirect( add_query_arg( array(
            'page' => 'guidepost-appointments',
            'action' => 'edit',
            'id' => $appointment_id,
            'error' => urlencode( __( 'Please fill in all required fields.', 'guidepost' ) )
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    // Update database
    $result = $wpdb->update(
        $tables['appointments'],
        $data,
        array( 'id' => $appointment_id ),
        array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
        array( '%d' )
    );

    if ( false === $result ) {
        wp_redirect( add_query_arg( array(
            'page' => 'guidepost-appointments',
            'action' => 'edit',
            'id' => $appointment_id,
            'error' => urlencode( __( 'Failed to update appointment.', 'guidepost' ) )
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    // Fire action hook
    do_action( 'guidepost_appointment_updated', $appointment_id, $data );

    wp_redirect( add_query_arg( array(
        'page' => 'guidepost-appointments',
        'updated' => '1'
    ), admin_url( 'admin.php' ) ) );
    exit;
}
```

---

### CHANGE 1.6: Add handle_delete_appointment() Method

**Location:** After handle_update_appointment()

```php
/**
 * Handle appointment deletion
 *
 * @param int $appointment_id Appointment ID.
 */
private function handle_delete_appointment( $appointment_id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Permission denied.', 'guidepost' ) );
    }

    global $wpdb;
    $tables = GuidePost_Database::get_table_names();

    // Delete the appointment
    $result = $wpdb->delete(
        $tables['appointments'],
        array( 'id' => $appointment_id ),
        array( '%d' )
    );

    // Fire action hook
    do_action( 'guidepost_appointment_deleted', $appointment_id );

    wp_redirect( add_query_arg( array(
        'page' => 'guidepost-appointments',
        'deleted' => '1'
    ), admin_url( 'admin.php' ) ) );
    exit;
}
```

---

### CHANGE 1.7: Add Success Notices for Edit/Delete

**Location:** In render_admin_notices() method (around line 965)

**Add these notices:**

```php
if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
    echo '<div class="notice notice-success is-dismissible"><p>' .
         esc_html__( 'Appointment updated successfully.', 'guidepost' ) . '</p></div>';
}

if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) {
    echo '<div class="notice notice-success is-dismissible"><p>' .
         esc_html__( 'Appointment deleted successfully.', 'guidepost' ) . '</p></div>';
}

if ( isset( $_GET['error'] ) ) {
    echo '<div class="notice notice-error is-dismissible"><p>' .
         esc_html( urldecode( $_GET['error'] ) ) . '</p></div>';
}
```

---

## File 2: admin.js

### CHANGE 2.1: Add Edit Button to Calendar Popup

**Location:** In the showEventPopup function (around line 125-150)

**Find the popup header and update to include Edit button:**

```javascript
// Current header structure - update to:
var popupHtml = '<div class="guidepost-event-popup">' +
    '<div class="guidepost-popup-header" style="background-color: ' + (event.backgroundColor || '#c16107') + '">' +
        '<strong>' + props.service + '</strong>' +
        '<div class="guidepost-popup-actions">' +
            '<a href="' + guidepost_admin.admin_url + 'admin.php?page=guidepost-appointments&action=edit&id=' + event.id + '" class="guidepost-popup-edit-btn" title="Edit">Edit</a>' +
            '<span class="guidepost-popup-close">&times;</span>' +
        '</div>' +
    '</div>' +
    // ... rest of popup content
```

---

### CHANGE 2.2: Add Appointment Form Handlers

**Location:** In bindEvents function, add new bindings (around line 35):

```javascript
// Appointment mode toggle
$(document).on('change', 'input[name="appointment_mode"]', function() {
    var mode = $(this).val();
    if (mode === 'in_person') {
        $('#in-person-fields').show();
        $('#virtual-fields').hide();
    } else {
        $('#in-person-fields').hide();
        $('#virtual-fields').show();
    }
});

// Service change - update end time display
$(document).on('change', '#service_id', function() {
    var duration = $(this).find(':selected').data('duration');
    var bookingTime = $('#booking_time').val();
    if (duration && bookingTime) {
        var endTime = GuidePostAdmin.calculateEndTime(bookingTime, duration);
        $('#end-time-display').text(endTime);
    }
});

// Time change - update end time display
$(document).on('change', '#booking_time', function() {
    var duration = $('#service_id').find(':selected').data('duration');
    var bookingTime = $(this).val();
    if (duration && bookingTime) {
        var endTime = GuidePostAdmin.calculateEndTime(bookingTime, duration);
        $('#end-time-display').text(endTime);
    }
});
```

---

### CHANGE 2.3: Add Helper Functions

**Location:** Add to GuidePostAdmin object (before closing brace around line 200):

```javascript
/**
 * Calculate end time from start time and duration
 */
calculateEndTime: function(startTime, durationMinutes) {
    var parts = startTime.split(':');
    var date = new Date();
    date.setHours(parseInt(parts[0], 10));
    date.setMinutes(parseInt(parts[1], 10) + parseInt(durationMinutes, 10));

    var hours = date.getHours();
    var minutes = date.getMinutes();
    var ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? '0' + minutes : minutes;

    return hours + ':' + minutes + ' ' + ampm;
}
```

---

## File 3: admin.css

### CHANGE 3.1: Add Appointment Form Styles

**Location:** After existing form styles (around line 500)

```css
/* ==========================================================================
   Appointment Edit Form
   ========================================================================== */

.guidepost-appointment-form .guidepost-form-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 20px;
}

.guidepost-appointment-form .guidepost-form-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.guidepost-appointment-form .guidepost-form-section {
    background: #fff;
    border: 1px solid var(--gp-border);
    border-radius: 8px;
    padding: 20px;
}

.guidepost-appointment-form .guidepost-form-section h3 {
    margin: 0 0 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--gp-border);
    font-size: 14px;
    font-weight: 600;
    color: var(--gp-text);
    display: flex;
    align-items: center;
    gap: 8px;
}

.guidepost-appointment-form .guidepost-form-section h3 .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
    color: var(--gp-primary);
}

.guidepost-appointment-form .guidepost-form-group {
    margin-bottom: 15px;
}

.guidepost-appointment-form .guidepost-form-group:last-child {
    margin-bottom: 0;
}

.guidepost-appointment-form .guidepost-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--gp-text);
}

.guidepost-appointment-form .guidepost-form-group label .required {
    color: #dc3545;
}

.guidepost-appointment-form .guidepost-form-group input[type="text"],
.guidepost-appointment-form .guidepost-form-group input[type="date"],
.guidepost-appointment-form .guidepost-form-group input[type="time"],
.guidepost-appointment-form .guidepost-form-group input[type="url"],
.guidepost-appointment-form .guidepost-form-group input[type="number"],
.guidepost-appointment-form .guidepost-form-group select,
.guidepost-appointment-form .guidepost-form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gp-border);
    border-radius: 4px;
    font-size: 14px;
}

.guidepost-appointment-form .guidepost-form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.guidepost-appointment-form .guidepost-form-group small {
    display: block;
    margin-top: 5px;
    color: var(--gp-text-light);
    font-size: 12px;
}

.guidepost-appointment-form .guidepost-conditional-fields {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed var(--gp-border);
}

.guidepost-appointment-form .guidepost-form-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    padding-top: 20px;
    border-top: 1px solid var(--gp-border);
}

/* Calendar Popup Edit Button */
.guidepost-popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.guidepost-popup-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.guidepost-popup-edit-btn {
    background: rgba(255,255,255,0.2);
    color: #fff !important;
    padding: 4px 10px;
    border-radius: 3px;
    text-decoration: none;
    font-size: 12px;
    border: 1px solid rgba(255,255,255,0.3);
}

.guidepost-popup-edit-btn:hover {
    background: rgba(255,255,255,0.3);
    color: #fff !important;
    text-decoration: none;
}

/* Availability Check Box */
#availability-check {
    margin-top: 15px;
}

#availability-check .availability-ok {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 10px;
    border-radius: 4px;
}

#availability-check .availability-warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
    padding: 10px;
    border-radius: 4px;
}

/* Mobile Responsive */
@media (max-width: 1024px) {
    .guidepost-appointment-form .guidepost-form-columns {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 782px) {
    .guidepost-appointment-form .guidepost-form-actions {
        flex-direction: column;
    }

    .guidepost-appointment-form .guidepost-form-actions .button {
        width: 100%;
        text-align: center;
    }

    .guidepost-appointment-form .guidepost-form-actions .button:last-child {
        margin-left: 0 !important;
    }
}
```

---

## Integration Checklist

### Before Implementation
- [ ] Verify `guidepost_admin.admin_url` is passed to JavaScript (in localize_script)
- [ ] Confirm all database columns exist in appointments table
- [ ] Review existing code line numbers (may have shifted)

### After Implementation
- [ ] Test Edit button on appointments list
- [ ] Test Edit button in calendar popup
- [ ] Test all form fields save correctly
- [ ] Test mode toggle (In Person / Virtual)
- [ ] Test end time calculation
- [ ] Test delete with confirmation
- [ ] Test validation (required fields)
- [ ] Test success/error notices
- [ ] Test mobile responsive layout

---

## Potential Conflicts

1. **Line numbers may differ** - Review current file structure before applying
2. **CSS variable dependencies** - Ensure `--gp-border`, `--gp-text`, `--gp-primary`, `--gp-text-light` exist
3. **JavaScript global** - Verify `guidepost_admin` object includes `admin_url` property
4. **Database columns** - Verify all columns exist (appointment_mode, meeting_link, etc.)

---

## Files Summary

| File | Location | Estimated Lines |
|------|----------|-----------------|
| class-guidepost-admin.php | includes/admin/ | +450 lines |
| admin.js | assets/js/ | +90 lines |
| admin.css | assets/css/ | +120 lines |

**Total: ~660 lines of new code**
