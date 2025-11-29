# Appointment Edit - AJAX Save Enhancement

## Overview

Improve UX when saving appointment edits. Currently redirects to list view, losing context. New behavior: AJAX save with success modal, stay on edit page.

---

## Current Behavior

1. User edits appointment on `/wp-admin/admin.php?page=guidepost-appointments&action=edit&id=X`
2. Clicks "Save Changes"
3. Form POST submits to `handle_update_appointment()`
4. PHP redirects to appointments list with `?message=updated`
5. User loses context of what they were editing

## New Behavior (AJAX Save)

1. User edits appointment
2. Clicks "Save Changes"
3. JavaScript intercepts form submit
4. AJAX POST to new endpoint
5. Success: Show modal with green checkmark, stay on page
6. Error: Show error message inline, stay on page

---

## Implementation Plan

### 1. PHP: Add AJAX Handler

**File:** `includes/admin/class-guidepost-admin.php`

Add new AJAX action in `__construct()`:
```php
add_action( 'wp_ajax_guidepost_update_appointment_ajax', array( $this, 'ajax_update_appointment' ) );
```

Add new method `ajax_update_appointment()`:
```php
public function ajax_update_appointment() {
    // Verify nonce
    check_ajax_referer( 'guidepost_update_appointment', 'nonce' );

    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied.' ) );
    }

    // Get appointment ID
    $appointment_id = isset( $_POST['appointment_id'] ) ? absint( $_POST['appointment_id'] ) : 0;
    if ( ! $appointment_id ) {
        wp_send_json_error( array( 'message' => 'Invalid appointment ID.' ) );
    }

    global $wpdb;
    $tables = GuidePost_Database::get_table_names();

    // Get service duration for end_time calculation
    $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
    $service = $wpdb->get_row( $wpdb->prepare(
        "SELECT duration FROM {$tables['services']} WHERE id = %d",
        $service_id
    ) );

    $booking_time = isset( $_POST['booking_time'] ) ? sanitize_text_field( $_POST['booking_time'] ) : '09:00';
    $duration = $service ? $service->duration : 60;
    $end_time = date( 'H:i:s', strtotime( $booking_time ) + ( $duration * 60 ) );

    // Prepare data (same as handle_update_appointment)
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
        wp_send_json_error( array( 'message' => 'Please fill in all required fields.' ) );
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
        wp_send_json_error( array( 'message' => 'Failed to update appointment.' ) );
    }

    // Fire action hook
    do_action( 'guidepost_appointment_updated', $appointment_id, $data );

    wp_send_json_success( array(
        'message' => 'Changes saved successfully!',
        'appointment_id' => $appointment_id
    ) );
}
```

### 2. JavaScript: AJAX Form Handler

**File:** `assets/js/admin.js`

Add to `bindEvents()`:
```javascript
// Appointment edit form AJAX submit
$(document).on('submit', '.guidepost-appointment-form', this.handleAppointmentSave.bind(this));
```

Add new method:
```javascript
/**
 * Handle appointment form save via AJAX
 */
handleAppointmentSave: function(e) {
    e.preventDefault();

    const $form = $(e.target);
    const $submitBtn = $form.find('button[type="submit"]');
    const originalBtnHtml = $submitBtn.html();

    // Disable button, show loading
    $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');

    // Collect form data
    const formData = new FormData($form[0]);
    formData.append('action', 'guidepost_update_appointment_ajax');
    formData.append('nonce', $form.find('#guidepost_update_appointment_nonce').val());

    $.ajax({
        url: guidepost_admin.ajax_url,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                // Show success modal
                GuidePostAdmin.showSuccessModal(response.data.message);
            } else {
                // Show error
                GuidePostAdmin.showErrorMessage(response.data.message || 'Failed to save changes.');
            }
        },
        error: function() {
            GuidePostAdmin.showErrorMessage('Network error. Please try again.');
        },
        complete: function() {
            // Restore button
            $submitBtn.prop('disabled', false).html(originalBtnHtml);
        }
    });
},

/**
 * Show success modal
 */
showSuccessModal: function(message) {
    // Remove existing modal
    $('.guidepost-success-modal').remove();

    const modal = $(`
        <div class="guidepost-success-modal">
            <div class="guidepost-success-modal-content">
                <div class="guidepost-success-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <p class="guidepost-success-message">${message}</p>
                <button type="button" class="button button-primary guidepost-success-close">Continue Editing</button>
            </div>
        </div>
    `);

    $('body').append(modal);

    // Auto-dismiss after 3 seconds
    setTimeout(function() {
        modal.fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);

    // Close on button click
    modal.find('.guidepost-success-close').on('click', function() {
        modal.fadeOut(300, function() {
            $(this).remove();
        });
    });

    // Close on backdrop click
    modal.on('click', function(e) {
        if ($(e.target).is('.guidepost-success-modal')) {
            modal.fadeOut(300, function() {
                $(this).remove();
            });
        }
    });
},

/**
 * Show error message
 */
showErrorMessage: function(message) {
    // Remove existing error
    $('.guidepost-ajax-error').remove();

    const error = $(`
        <div class="guidepost-ajax-error notice notice-error">
            <p>${message}</p>
            <button type="button" class="notice-dismiss"></button>
        </div>
    `);

    $('.guidepost-appointment-form').before(error);

    // Dismiss button
    error.find('.notice-dismiss').on('click', function() {
        error.fadeOut(300, function() {
            $(this).remove();
        });
    });

    // Scroll to error
    $('html, body').animate({
        scrollTop: error.offset().top - 50
    }, 300);
}
```

### 3. CSS: Modal Styles

**File:** `assets/css/admin.css`

```css
/* Success Modal */
.guidepost-success-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100000;
}

.guidepost-success-modal-content {
    background: #fff;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    max-width: 400px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: guidepost-modal-in 0.3s ease;
}

@keyframes guidepost-modal-in {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.guidepost-success-icon {
    margin-bottom: 20px;
}

.guidepost-success-icon .dashicons {
    font-size: 64px;
    width: 64px;
    height: 64px;
    color: #46b450;
}

.guidepost-success-message {
    font-size: 18px;
    color: #333;
    margin-bottom: 20px;
}

.guidepost-success-close {
    min-width: 150px;
}

/* AJAX Error Notice */
.guidepost-ajax-error {
    margin: 15px 0;
}

/* Spin animation for loading */
.dashicons.spin {
    animation: guidepost-spin 1s linear infinite;
}

@keyframes guidepost-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
```

---

## Testing Checklist

- [ ] Save appointment → success modal appears
- [ ] Modal auto-dismisses after 3 seconds
- [ ] Click "Continue Editing" → modal closes
- [ ] Click backdrop → modal closes
- [ ] Required field missing → error message appears
- [ ] Network error → error message appears
- [ ] Button shows loading state during save
- [ ] Data actually saves to database
- [ ] Delete button still works (not AJAX, redirect is fine)
- [ ] Cancel button still works (redirect to list)

---

## Files to Modify

1. `includes/admin/class-guidepost-admin.php`
   - Add AJAX action hook in constructor
   - Add `ajax_update_appointment()` method

2. `assets/js/admin.js`
   - Add form submit handler
   - Add `handleAppointmentSave()` method
   - Add `showSuccessModal()` method
   - Add `showErrorMessage()` method

3. `assets/css/admin.css`
   - Add success modal styles
   - Add error notice styles
   - Add spin animation

---

## Session Notes

- This builds on the Appointment Edit feature added in commit `94e7cfd`
- The existing `handle_update_appointment()` method can remain for non-JS fallback
- Consider adding this same pattern to other forms later (services, providers, customers)

---

## Deploy & Test Commands

```bash
# Deploy to Docker
powershell -ExecutionPolicy Bypass -File "docker\scripts\deploy-plugin.ps1"

# Run Playwright tests
cd guidepost && npx playwright test --reporter=list

# Check debug log
docker exec guidepost-wordpress tail -50 /var/www/html/wp-content/debug.log
```

---

## Implementation Status

**Completed:** November 29, 2025

### Files Modified:
1. `includes/admin/class-guidepost-admin.php`
   - Added AJAX hook in constructor (line 53)
   - Added `ajax_update_appointment()` method (lines 1821-1899)

2. `assets/js/admin.js`
   - Added form submit handler in bindEvents() (line 72)
   - Added `handleAppointmentSave()` method (lines 273-313)
   - Added `showSuccessModal()` method (lines 318-358)
   - Added `showErrorMessage()` method (lines 363-387)

3. `assets/css/admin.css`
   - Added success modal styles (lines 4055-4126)
   - Added error notice styles
   - Added spin animation

### Testing Results:
- Deployed to Docker: Success
- Playwright tests: 45/45 passed
- Debug log: Clean (no errors or warnings)
