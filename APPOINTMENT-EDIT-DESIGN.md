# Appointment Edit Page - Design Document

## Overview

This document outlines the design for a full appointment edit page that allows administrators to modify all aspects of an existing appointment.

---

## User Flow

```
Appointments List                    Edit Page
┌─────────────────┐                 ┌─────────────────────────────────┐
│ [List/Calendar] │                 │ Edit Appointment #123           │
│                 │   Click Edit    │                                 │
│ Apt #123  [Edit]│ ─────────────▶  │ [Form Fields]                   │
│ Apt #124  [Edit]│                 │                                 │
│                 │                 │ [Save] [Cancel] [Delete]        │
└─────────────────┘                 └─────────────────────────────────┘
                                              │
                                              │ Save
                                              ▼
                                    ┌─────────────────────────────────┐
                                    │ Redirect to Appointments List   │
                                    │ with success notice             │
                                    └─────────────────────────────────┘
```

**Entry Points:**
1. Edit button/link on Appointments List page
2. Edit button on Customer Detail → Appointments tab
3. Edit button on Calendar popup (see below)

**URL Structure:**
```
/wp-admin/admin.php?page=guidepost-appointments&action=edit&id=123
```

---

## Calendar Popup Edit Button

When clicking an event on the calendar view, a popup appears with appointment details. Add an Edit button next to the close (×) button:

```
┌─────────────────────────────────────────────┐
│ Discovery Session              [Edit] [×]   │
├─────────────────────────────────────────────┤
│                                             │
│ Customer: Jennifer Rodriguez                │
│                                             │
│ Email: jennifer.r@example.com               │
│                                             │
│ Phone: (555) 345-6789                       │
│                                             │
│ Provider: Kim Benedict                      │
│                                             │
│ Date: Thursday, December 4, 2025            │
│                                             │
│ Time: 10:00 AM - 10:30 AM (30 min)         │
│                                             │
│ Price: $0.00                                │
│                                             │
│ Status: Approved                            │
│                                             │
└─────────────────────────────────────────────┘
```

**Edit Button Behavior:**
- Clicking [Edit] navigates to the edit page: `?page=guidepost-appointments&action=edit&id={appointment_id}`
- Button styled as secondary/link style to not overshadow the close button
- Opens in same tab (not new window)

**Implementation:**
- Add edit link to the FullCalendar `eventClick` popup HTML
- Pass appointment ID through the event's `extendedProps`

---

## Page Layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ← Back to Appointments                                                  │
│                                                                         │
│ Edit Appointment #123                                    [Delete]       │
│ Created: Nov 15, 2025 at 2:30 PM                                       │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────────┐  ┌─────────────────────────────┐  │
│  │ SCHEDULING                      │  │ APPOINTMENT INFO            │  │
│  │                                 │  │                             │  │
│  │ Customer: [Search/Select ▼]    │  │ Status: [Pending ▼]        │  │
│  │  └─ Sarah Johnson              │  │                             │  │
│  │                                 │  │ Service: [Discovery Call ▼]│  │
│  │ Date: [Nov 28, 2025    📅]     │  │  Duration: 60 min          │  │
│  │                                 │  │  Price: $150.00            │  │
│  │ Time: [10:00 AM ▼]             │  │                             │  │
│  │  └─ End: 11:00 AM              │  │ Provider: [Kim Benedict ▼] │  │
│  │                                 │  │                             │  │
│  │ ⚠️ Availability Check:         │  │ Credits Used: [0]          │  │
│  │    ✓ Provider available        │  │                             │  │
│  │    ✓ No conflicts              │  └─────────────────────────────┘  │
│  │                                 │                                   │
│  └─────────────────────────────────┘                                   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ APPOINTMENT MODE                                                 │   │
│  │                                                                  │   │
│  │ Mode: (●) In Person  ( ) Virtual                                │   │
│  │                                                                  │   │
│  │ ┌─ IN PERSON ─────────────────────────────────────────────────┐ │   │
│  │ │ Location: [123 Main St, Suite 100, City, ST 12345        ]  │ │   │
│  │ └─────────────────────────────────────────────────────────────┘ │   │
│  │                                                                  │   │
│  │ ┌─ VIRTUAL (hidden when In Person) ───────────────────────────┐ │   │
│  │ │ Platform: [Zoom ▼]                                          │ │   │
│  │ │ Meeting Link: [https://zoom.us/j/123456789               ]  │ │   │
│  │ │ Password: [abc123                                        ]  │ │   │
│  │ └─────────────────────────────────────────────────────────────┘ │   │
│  │                                                                  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ NOTES                                                            │   │
│  │                                                                  │   │
│  │ Internal Notes (admin only):                                    │   │
│  │ ┌──────────────────────────────────────────────────────────┐   │   │
│  │ │ Client mentioned she may need to reschedule...           │   │   │
│  │ │                                                          │   │   │
│  │ └──────────────────────────────────────────────────────────┘   │   │
│  │                                                                  │   │
│  │ Admin Notes:                                                    │   │
│  │ ┌──────────────────────────────────────────────────────────┐   │   │
│  │ │ Prep materials: coaching workbook, goal sheet            │   │   │
│  │ │                                                          │   │   │
│  │ └──────────────────────────────────────────────────────────┘   │   │
│  │                                                                  │   │
│  │ Customer Notes (visible to customer):                           │   │
│  │ ┌──────────────────────────────────────────────────────────┐   │   │
│  │ │ Please bring your completed intake form.                 │   │   │
│  │ │                                                          │   │   │
│  │ └──────────────────────────────────────────────────────────┘   │   │
│  │                                                                  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ FOLLOW-UP                                                        │   │
│  │                                                                  │   │
│  │ Follow-up Date: [Dec 15, 2025  📅] or [ ] No follow-up needed   │   │
│  │                                                                  │   │
│  │ Follow-up Notes:                                                │   │
│  │ ┌──────────────────────────────────────────────────────────┐   │   │
│  │ │ Check in on progress with weekly goals...                │   │   │
│  │ └──────────────────────────────────────────────────────────┘   │   │
│  │                                                                  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                                                                   │  │
│  │  [Save Changes]  [Cancel]                                        │  │
│  │                                                                   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Form Sections

### 1. Header
- Back link to appointments list
- Appointment ID and creation date (read-only)
- Delete button (with confirmation)

### 2. Scheduling Section
| Field | Type | Validation | Notes |
|-------|------|------------|-------|
| Customer | Searchable Select | Required | Shows name, email. Links to customer profile |
| Date | Date Picker | Required, future or today | Triggers availability check |
| Time | Time Select | Required | Based on service duration & provider hours |
| End Time | Display Only | Auto-calculated | Date + Service Duration |

**Availability Check:**
- Triggered when Date, Time, Service, or Provider changes
- Shows warnings for:
  - Provider not available on that day
  - Time outside provider's working hours
  - Conflicts with other appointments
  - Double-booking the same customer

### 3. Appointment Info Section
| Field | Type | Validation | Notes |
|-------|------|------------|-------|
| Status | Dropdown | Required | pending, approved, canceled, completed, no_show |
| Service | Dropdown | Required | Active services only. Updates duration/price display |
| Provider | Dropdown | Required | Active providers only. Filters by service capability |
| Credits Used | Number | Min 0 | For credit-based payments |

### 4. Appointment Mode Section
| Field | Type | Validation | Notes |
|-------|------|------------|-------|
| Mode | Radio | Required | in_person or virtual |
| Location | Textarea | If in_person | Physical address |
| Platform | Dropdown | If virtual | zoom, google_meet, teams, other |
| Meeting Link | URL | If virtual | Validates URL format |
| Password | Text | Optional | Meeting password |

**Conditional Display:**
- In Person: Show Location field, hide virtual fields
- Virtual: Show Platform, Link, Password; hide Location

### 5. Notes Section
| Field | Type | Max Length | Notes |
|-------|------|------------|-------|
| Internal Notes | Textarea | Unlimited | Admin-only, never shown to customer |
| Admin Notes | Textarea | Unlimited | For prep, follow-up actions |
| Customer Notes | Textarea | Unlimited | Will be visible in customer communications |

### 6. Follow-up Section
| Field | Type | Validation | Notes |
|-------|------|------------|-------|
| Follow-up Date | Date Picker | Optional, future | Creates reminder/flag |
| Follow-up Notes | Textarea | Optional | What to follow up about |

---

## Behavior & Interactions

### On Page Load
1. Fetch appointment data by ID
2. Populate all form fields
3. Load customer, service, provider dropdowns
4. Set initial availability state

### Customer Selection
- Searchable dropdown (Select2 or similar)
- Shows: Name, Email, Phone
- Quick link to open customer profile in new tab

### Date/Time Change
1. User selects new date
2. AJAX call to check provider availability
3. Time dropdown updates with available slots
4. If current time no longer available, show warning
5. Check for customer double-booking

### Service Change
1. User selects different service
2. Update duration display
3. Update price display
4. Recalculate end time
5. Filter providers to those who offer this service
6. Re-check availability

### Provider Change
1. User selects different provider
2. Re-check availability for selected date/time
3. Show warning if provider doesn't offer selected service

### Mode Change (In Person ↔ Virtual)
1. Toggle visibility of Location vs Meeting fields
2. Clear hidden fields (optional - or preserve for switching back)

### Save
1. Validate all required fields
2. Validate availability (warn but allow override?)
3. Submit form
4. On success: Redirect to list with success message
5. On error: Show inline errors, stay on form

### Delete
1. Confirm dialog: "Are you sure you want to delete this appointment?"
2. Option to send cancellation email to customer
3. On confirm: Delete and redirect to list

---

## Validation Rules

### Required Fields
- Customer
- Date
- Time
- Status
- Service
- Provider

### Conditional Required
- Location (required if mode = in_person AND service has default location empty)
- Meeting Link (recommended if mode = virtual, but not required)

### Format Validation
- Meeting Link: Valid URL format
- Date: Valid date, not in distant past (allow recent past for corrections)
- Credits Used: Integer >= 0

### Business Rules
- Warn (not block) if:
  - Provider unavailable at selected time
  - Customer already has appointment at same time
  - Changing completed appointment
  - Changing to past date/time

---

## Data Changes on Save

**Always Updated:**
- `updated_at` = current timestamp

**Tracked for Audit (future):**
- Consider logging changes to appointment history
- "Rescheduled from Nov 25 to Nov 28 by Admin"

**Email Notifications (optional checkbox):**
- [ ] Send update notification to customer
  - If checked and date/time changed: Send reschedule email
  - If checked and status changed: Send status update email

---

## Edge Cases

### 1. Appointment Already Completed
- Allow editing but show warning banner
- "This appointment is marked as completed. Are you sure you want to modify it?"

### 2. Appointment in the Past
- Allow editing for corrections
- Show info banner: "This appointment is in the past"

### 3. Customer Deleted
- Show "Unknown Customer"
- Allow reassignment to new customer

### 4. Service/Provider Deleted
- Show warning: "Original service no longer exists"
- Require selecting a new service/provider

### 5. Concurrent Editing
- Not handling initially (low risk for single-admin system)
- Future: Add optimistic locking with `updated_at` check

---

## Technical Implementation Notes

### URL Routing
```php
// In render_appointments_page()
$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

if ( 'edit' === $action && $id > 0 ) {
    $this->render_appointment_edit_form( $id );
} else {
    $this->render_appointments_list();
}
```

### Form Handler
```php
// In handle_form_submissions()
if ( isset( $_POST['guidepost_update_appointment_nonce'] ) ) {
    $this->handle_update_appointment();
}
```

### AJAX Endpoints Needed
1. `guidepost_check_availability` - Check provider availability
2. `guidepost_get_available_times` - Get time slots for date/provider
3. `guidepost_search_customers` - Search customers for dropdown

### JavaScript Components
- Date picker (WordPress built-in or flatpickr)
- Searchable select for customer (Select2)
- Conditional field visibility
- Availability checker with debounce

---

## Design Decisions (CONFIRMED)

1. **Override availability?** ✅ YES with warning - Admins can book even if provider shows unavailable, but see a warning

2. **Email on edit?** ✅ YES - Optional checkbox to send notification email when rescheduling

3. **Change history?** Future enhancement - Not in initial implementation

4. **Delete behavior?** ✅ Hard delete with optional cancellation email to customer

5. **End time override?** ✅ NO - End time is always calculated from service duration (source of truth)

6. **Availability checking?** ✅ YES with warnings (no blocking):
   - Check provider's working hours
   - Check for conflicts with other appointments
   - Check if customer has appointment at same time
   - All show warnings but allow override

---

## Implementation Phases

### Phase 1: Core Edit Form
- Basic form with all fields
- Save functionality
- Validation
- Redirect and success messages

### Phase 2: Availability Checking
- AJAX availability check
- Provider schedule validation
- Conflict detection
- Warning messages

### Phase 3: Enhanced UX
- Searchable customer dropdown
- Dynamic time slot dropdown
- Conditional field visibility
- Delete with confirmation

### Phase 4: Notifications (Optional)
- Email on reschedule
- Email on cancellation
- Change logging

---

## File Changes Required

| File | Changes |
|------|---------|
| `class-guidepost-admin.php` | Add `render_appointment_edit_form()`, update routing in `render_appointments_page()`, add form handler |
| `assets/css/admin.css` | Add appointment edit form styles |
| `assets/js/admin.js` | Add appointment edit handlers, availability check, conditional fields |

---

## Mockup: Mobile Responsive

On smaller screens, the two-column layout collapses to single column:

```
┌─────────────────────────┐
│ ← Back                  │
│ Edit Appointment #123   │
├─────────────────────────┤
│ SCHEDULING              │
│ Customer: [▼]           │
│ Date: [📅]              │
│ Time: [▼]               │
├─────────────────────────┤
│ APPOINTMENT INFO        │
│ Status: [▼]             │
│ Service: [▼]            │
│ Provider: [▼]           │
├─────────────────────────┤
│ MODE                    │
│ (●) In Person           │
│ ( ) Virtual             │
│ Location: [          ]  │
├─────────────────────────┤
│ NOTES                   │
│ Internal: [          ]  │
│ Admin: [             ]  │
│ Customer: [          ]  │
├─────────────────────────┤
│ FOLLOW-UP               │
│ Date: [📅]              │
│ Notes: [             ]  │
├─────────────────────────┤
│ [Save]  [Cancel]        │
└─────────────────────────┘
```

---

## Approval Checklist

- [ ] Layout and sections approved
- [ ] Field list complete
- [ ] Validation rules agreed
- [ ] Availability checking scope confirmed
- [ ] Email notification requirements confirmed
- [ ] Delete behavior confirmed
- [ ] Ready for implementation
