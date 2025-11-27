# GuidePost Expansion Vision Document

**Version:** 1.0
**Date:** November 26, 2025
**Status:** Planning & Architecture

---

## Executive Summary

GuidePost is evolving from a booking/scheduling plugin into a comprehensive **Client Relationship & Service Management Platform**. This document outlines the expansion vision, integrating proven patterns from the sister plugins (triaddexa credit system, 30-60-90 project journey) while adding new capabilities for video appointments, rich customer records, and business intelligence.

---

## Three-Pillar Architecture

### Pillar 1: Calendar (Operational Speed)
*"What's happening now? Let me act fast."*

### Pillar 2: Customer Manager (Relationship Depth)
*"Tell me everything about this person."*

### Pillar 3: Reporting & KPIs (Business Intelligence)
*"How is my business doing? Where should I focus?"*

---

## Pillar 1: Calendar

### Current Features (Implemented)
- [x] Month/Week/Day views with FullCalendar
- [x] Color-coding by service
- [x] Event popup with customer details
- [x] Status-based styling (pending, approved, canceled, etc.)

### Expansion Features

#### 1.1 Appointment Mode Indicators
```
Visual indicators on calendar events:
├── 📍 In-person (location icon)
├── 🎥 Virtual (video icon) - click to copy meeting link
└── 🔄 Hybrid (both icons)
```

#### 1.2 Quick Actions (Right-click or hover menu)
- Reschedule (opens date/time picker)
- Cancel (with optional customer notification)
- Duplicate (create similar appointment)
- Mark Complete / No-Show
- Copy Meeting Link (for virtual)
- Send Reminder Email
- Open Customer Profile

#### 1.3 Quick Customer Peek
Hover on any event to see:
- Customer name, email, phone
- Total appointments (past/upcoming)
- Credit balance (if credits enabled)
- Status badge (Active/VIP/etc.)
- Last communication sent

#### 1.4 Export Options
- **Single Appointment ICS** - Download one event
- **Date Range ICS** - Export all appointments in range
- **Google Calendar Sync** - Push to admin's Google Calendar
- **Filtered Export** - By provider, service, or status

#### 1.5 Calendar Filters
- By Provider
- By Service
- By Appointment Mode (In-person/Virtual)
- By Status
- By Customer Tag (VIP, New, etc.)

---

## Pillar 2: Customer Manager

### 2.1 Database Schema Expansion

#### Enhanced `guidepost_customers` table:
```sql
ALTER TABLE guidepost_customers ADD COLUMN (
    status ENUM('active', 'paused', 'vip', 'inactive', 'prospect') DEFAULT 'active',
    google_drive_url VARCHAR(500) DEFAULT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    first_contact_date DATE DEFAULT NULL,
    tags TEXT DEFAULT NULL,  -- JSON array: ["vip", "corporate", "referral"]
    source VARCHAR(100) DEFAULT NULL,  -- How they found you

    -- Denormalized for fast queries (updated via triggers/hooks)
    last_booking_date DATE DEFAULT NULL,
    next_booking_date DATE DEFAULT NULL,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    total_appointments INT DEFAULT 0,
    total_credits INT DEFAULT 0,

    -- Metadata
    birthday DATE DEFAULT NULL,
    company VARCHAR(255) DEFAULT NULL,
    job_title VARCHAR(255) DEFAULT NULL,
    preferred_contact ENUM('email', 'phone', 'sms') DEFAULT 'email',
    timezone VARCHAR(50) DEFAULT NULL
);
```

#### New `guidepost_customer_notes` table:
```sql
CREATE TABLE guidepost_customer_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,  -- Staff who wrote it
    note_text TEXT NOT NULL,
    note_type ENUM('general', 'session', 'follow_up', 'alert', 'private') DEFAULT 'general',
    is_pinned TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY customer_id (customer_id),
    KEY user_id (user_id),
    KEY note_type (note_type),
    FOREIGN KEY (customer_id) REFERENCES guidepost_customers(id) ON DELETE CASCADE
);
```

#### New `guidepost_customer_purchases` table:
```sql
-- Beyond WooCommerce - track packages, credits, custom purchases
CREATE TABLE guidepost_customer_purchases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    wc_order_id BIGINT UNSIGNED DEFAULT NULL,  -- Link to WC if applicable
    purchase_type ENUM('service', 'package', 'credit', 'product', 'other') NOT NULL,
    description VARCHAR(500) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    credits_granted INT DEFAULT 0,
    quantity INT DEFAULT 1,
    metadata JSON DEFAULT NULL,  -- Flexible storage for package details, etc.
    purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,

    KEY customer_id (customer_id),
    KEY wc_order_id (wc_order_id),
    KEY purchase_type (purchase_type),
    FOREIGN KEY (customer_id) REFERENCES guidepost_customers(id) ON DELETE CASCADE
);
```

#### New `guidepost_customer_documents` table:
```sql
CREATE TABLE guidepost_customer_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED DEFAULT NULL,  -- Optional link to appointment
    filename VARCHAR(255) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size BIGINT DEFAULT NULL,
    uploaded_by ENUM('customer', 'admin') DEFAULT 'customer',
    uploaded_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    KEY customer_id (customer_id),
    KEY appointment_id (appointment_id),
    FOREIGN KEY (customer_id) REFERENCES guidepost_customers(id) ON DELETE CASCADE
);
```

#### New `guidepost_customer_flags` table:
```sql
-- Automated and manual alerts/flags
CREATE TABLE guidepost_customer_flags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    flag_type ENUM('follow_up', 'inactive', 'birthday', 'vip_check', 'payment_due', 'custom') NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_dismissed TINYINT(1) DEFAULT 0,
    dismissed_by BIGINT UNSIGNED DEFAULT NULL,
    trigger_date DATE DEFAULT NULL,  -- When flag becomes relevant
    auto_generated TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    KEY customer_id (customer_id),
    KEY flag_type (flag_type),
    KEY is_active (is_active),
    KEY trigger_date (trigger_date),
    FOREIGN KEY (customer_id) REFERENCES guidepost_customers(id) ON DELETE CASCADE
);
```

#### New `guidepost_credit_history` table:
```sql
-- Audit trail for credits (Pattern from triaddexa)
CREATE TABLE guidepost_credit_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,  -- WP user if linked
    delta INT NOT NULL,  -- +5 for purchase, -1 for consumption
    reason VARCHAR(500) NOT NULL,
    old_balance INT NOT NULL,
    new_balance INT NOT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,  -- 'order', 'appointment', 'manual', 'refund'
    reference_id BIGINT UNSIGNED DEFAULT NULL,  -- order_id, appointment_id, etc.
    created_by BIGINT UNSIGNED DEFAULT NULL,  -- Admin who made manual adjustment
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    KEY customer_id (customer_id),
    KEY reference_type (reference_type, reference_id),
    KEY created_at (created_at),
    FOREIGN KEY (customer_id) REFERENCES guidepost_customers(id) ON DELETE CASCADE
);
```

### 2.2 Appointment Mode & Video Support

#### Enhanced `guidepost_services` table:
```sql
ALTER TABLE guidepost_services ADD COLUMN (
    appointment_mode ENUM('in_person', 'virtual', 'hybrid') DEFAULT 'in_person',
    default_location TEXT DEFAULT NULL,  -- Address for in-person
    default_meeting_platform ENUM('google_meet', 'zoom', 'teams', 'other') DEFAULT NULL,
    default_meeting_link VARCHAR(500) DEFAULT NULL,  -- Recurring link
    meeting_instructions TEXT DEFAULT NULL  -- "Click link 5 min before..."
);
```

#### Enhanced `guidepost_appointments` table:
```sql
ALTER TABLE guidepost_appointments ADD COLUMN (
    appointment_mode ENUM('in_person', 'virtual') DEFAULT 'in_person',
    location TEXT DEFAULT NULL,
    meeting_platform VARCHAR(50) DEFAULT NULL,
    meeting_link VARCHAR(500) DEFAULT NULL,
    meeting_password VARCHAR(100) DEFAULT NULL,
    customer_notes TEXT DEFAULT NULL,  -- Notes customer added at booking
    admin_notes TEXT DEFAULT NULL,  -- Internal notes
    follow_up_date DATE DEFAULT NULL,
    follow_up_notes TEXT DEFAULT NULL
);
```

### 2.3 Customer Manager Page Structure

```
Customer Manager
├── Customer List View
│   ├── Search & Filter bar
│   ├── Sortable columns (Name, Last Visit, Total Spent, Status)
│   ├── Quick actions (Email, View, Edit)
│   └── Bulk actions (Export, Tag, Email Campaign)
│
└── Customer Detail View (Single Customer)
    ├── Header Section
    │   ├── Avatar, Name, Status Badge
    │   ├── Contact info (email, phone)
    │   ├── Quick actions (Send Email, Book Appointment, Add Note)
    │   └── Tags (editable chips)
    │
    ├── Relationship Timeline (Visual)
    │   └── First Contact → First Purchase → Milestones → Latest Service
    │
    ├── Stats Cards
    │   ├── Total Appointments
    │   ├── Total Spent
    │   ├── Credit Balance
    │   └── Member Since
    │
    ├── Tabs Section
    │   ├── Appointments Tab
    │   │   ├── Upcoming appointments
    │   │   ├── Past appointments (with meeting links preserved)
    │   │   └── Quick reschedule/cancel
    │   │
    │   ├── Purchases Tab
    │   │   ├── WooCommerce orders (synced)
    │   │   ├── Manual purchases/packages
    │   │   └── Credit purchases with history
    │   │
    │   ├── Documents Tab
    │   │   ├── Files uploaded by customer
    │   │   ├── Files uploaded by admin
    │   │   └── Google Drive link (URL field)
    │   │
    │   ├── Communications Tab
    │   │   ├── All emails sent (from Communications system)
    │   │   └── Quick "Send Email" with template picker
    │   │
    │   └── Notes Tab
    │       ├── Pinned notes at top
    │       ├── Chronological note list
    │       ├── Note type filter (General, Session, Follow-up)
    │       └── Add new note form
    │
    └── Sidebar
        ├── Flags/Alerts section
        │   ├── Active flags with dismiss button
        │   └── "Add Flag" button
        │
        ├── Upcoming Reminders
        │   └── Follow-up dates from appointments
        │
        └── Google Drive Link
            └── Quick link to external folder
```

### 2.4 ICS Export (Rich Data)

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//GuidePost//Booking System//EN
METHOD:PUBLISH

BEGIN:VEVENT
UID:appointment-{{appointment_id}}@{{site_domain}}
DTSTAMP:{{current_timestamp}}
DTSTART:{{appointment_start_iso}}
DTEND:{{appointment_end_iso}}
SUMMARY:{{service_name}} - {{customer_name}}
DESCRIPTION:Customer: {{customer_name}}\n
 Email: {{customer_email}}\n
 Phone: {{customer_phone}}\n
 \n
 Service: {{service_name}}\n
 Duration: {{duration}} minutes\n
 Provider: {{provider_name}}\n
 Type: {{appointment_mode}}\n
 {{#if meeting_link}}
 \n
 Join Meeting: {{meeting_link}}\n
 {{#if meeting_password}}Password: {{meeting_password}}{{/if}}\n
 {{/if}}
 {{#if customer_notes}}
 \n
 Customer Notes: {{customer_notes}}\n
 {{/if}}
LOCATION:{{#if meeting_link}}{{meeting_link}}{{else}}{{location}}{{/if}}
URL:{{meeting_link}}
STATUS:{{#if status == 'approved'}}CONFIRMED{{else}}TENTATIVE{{/if}}
ORGANIZER;CN={{company_name}}:mailto:{{admin_email}}
ATTENDEE;CN={{customer_name}}:mailto:{{customer_email}}
BEGIN:VALARM
TRIGGER:-PT30M
ACTION:DISPLAY
DESCRIPTION:Reminder: {{service_name}} with {{provider_name}}
END:VALARM
END:VEVENT

END:VCALENDAR
```

---

## Pillar 3: Reporting & KPIs

### 3.1 Dashboard Widgets

```
Reporting Dashboard
├── Period Selector (This Week / Month / Quarter / Year / Custom)
│
├── Revenue Section
│   ├── Total Revenue (with comparison to previous period)
│   ├── Revenue by Service (bar chart)
│   ├── Revenue by Provider (bar chart)
│   └── Revenue Trend (line chart)
│
├── Appointments Section
│   ├── Total Appointments
│   ├── Completion Rate (completed vs booked)
│   ├── No-Show Rate
│   ├── Cancellation Rate
│   ├── Popular Times Heatmap (day/hour grid)
│   └── Appointments by Mode (in-person vs virtual pie chart)
│
├── Customer Section
│   ├── New Customers (this period)
│   ├── Returning Customers
│   ├── Customer Retention Rate
│   ├── Average Customer Value
│   └── At-Risk Customers (no booking in X days)
│
├── Service Performance
│   ├── Most Booked Services
│   ├── Highest Revenue Services
│   ├── Service Utilization Rate
│   └── Average Service Price
│
├── Provider Performance
│   ├── Appointments per Provider
│   ├── Revenue per Provider
│   ├── No-Show Rate per Provider
│   └── Utilization Rate (booked hours / available hours)
│
└── Alerts & Actions
    ├── Customers needing follow-up
    ├── Upcoming appointment reminders not sent
    ├── Expiring credits
    └── Appointments without payment
```

### 3.2 Exportable Reports

- **Revenue Report** (PDF/CSV) - By period, service, provider
- **Customer Report** (CSV) - Full customer list with stats
- **Appointment Report** (CSV) - All appointments with details
- **At-Risk Customer Report** (CSV) - Customers needing attention
- **Credit Usage Report** (CSV) - Credit purchases and consumption

---

## Credits System Integration

### Pattern: Triaddexa-style with History Table

```php
// Core credit functions (simple, fast)
function guidepost_get_credits($customer_id) {
    global $wpdb;
    $tables = GuidePost_Database::get_table_names();
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT total_credits FROM {$tables['customers']} WHERE id = %d",
        $customer_id
    ));
}

function guidepost_add_credits($customer_id, $delta, $reason, $reference_type = null, $reference_id = null) {
    global $wpdb;
    $tables = GuidePost_Database::get_table_names();

    $old_balance = guidepost_get_credits($customer_id);
    $new_balance = max(0, $old_balance + $delta);

    // Update customer balance
    $wpdb->update(
        $tables['customers'],
        array('total_credits' => $new_balance),
        array('id' => $customer_id),
        array('%d'),
        array('%d')
    );

    // Log transaction
    $wpdb->insert(
        $tables['credit_history'],
        array(
            'customer_id'    => $customer_id,
            'delta'          => $delta,
            'reason'         => $reason,
            'old_balance'    => $old_balance,
            'new_balance'    => $new_balance,
            'reference_type' => $reference_type,
            'reference_id'   => $reference_id,
            'created_by'     => get_current_user_id(),
        ),
        array('%d', '%d', '%s', '%d', '%d', '%s', '%d', '%d')
    );

    return $new_balance;
}

// WooCommerce integration hook
add_action('woocommerce_order_status_completed', function($order_id) {
    $credit_map = get_option('guidepost_product_credits', array());
    // ... grant credits based on products purchased
});

// Appointment booking hook - consume credit
add_action('guidepost_appointment_confirmed', function($appointment_id) {
    $appointment = guidepost_get_appointment($appointment_id);
    $service = guidepost_get_service($appointment->service_id);

    if ($service->requires_credit) {
        guidepost_add_credits(
            $appointment->customer_id,
            -1,  // Consume 1 credit
            sprintf('Appointment #%d - %s', $appointment_id, $service->name),
            'appointment',
            $appointment_id
        );
    }
});
```

---

## 30-60-90 Integration Points

### Option A: Lightweight Integration (Recommended First)

GuidePost can reference 30-60-90 projects without deep coupling:

```sql
-- Add to guidepost_customers
ALTER TABLE guidepost_customers ADD COLUMN (
    project_journey_id INT DEFAULT NULL,  -- Link to 30-60-90 project if exists
    project_journey_user_id BIGINT DEFAULT NULL  -- WP user for progress tracking
);
```

**Benefits:**
- Customer profile shows link to their project journey
- Can display progress summary on customer page
- Appointments can be linked to project milestones
- No code changes needed in 30-60-90

### Option B: Deeper Integration (Future)

Create a service type "Project Session" that links to 30-60-90 tasks:

```sql
-- New table for linking appointments to project tasks
CREATE TABLE guidepost_appointment_project_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NOT NULL,
    project_id INT NOT NULL,
    task_id VARCHAR(100) NOT NULL,
    auto_complete_task TINYINT(1) DEFAULT 1,  -- Mark task done when appointment completes
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (appointment_id) REFERENCES guidepost_appointments(id) ON DELETE CASCADE
);
```

**Workflow:**
1. Admin creates appointment and selects linked project task
2. When appointment marked complete, optionally marks task complete
3. Customer's project journey stays in sync with their sessions

---

## Sample Data Structure

### Sample Customer Journey

```json
{
  "customer": {
    "id": 1,
    "first_name": "Kim",
    "last_name": "Benedict",
    "email": "kim@sojourn-coaching.com",
    "status": "vip",
    "tags": ["coaching-client", "referral-source"],
    "first_contact_date": "2025-09-15",
    "google_drive_url": "https://drive.google.com/drive/folders/abc123",
    "total_appointments": 12,
    "total_spent": 2400.00,
    "total_credits": 3
  },

  "timeline": [
    {"date": "2025-09-15", "event": "first_contact", "label": "Discovery Call"},
    {"date": "2025-09-22", "event": "first_purchase", "label": "8-Week Package"},
    {"date": "2025-10-01", "event": "milestone", "label": "Started Coaching"},
    {"date": "2025-11-26", "event": "latest_service", "label": "Session #8"}
  ],

  "appointments": [
    {
      "id": 101,
      "service_name": "Coaching Session",
      "date": "2025-11-26",
      "time": "10:00",
      "mode": "virtual",
      "meeting_link": "https://meet.google.com/abc-defg-hij",
      "status": "completed",
      "provider": "Walter"
    }
  ],

  "purchases": [
    {
      "id": 1,
      "type": "package",
      "description": "8-Week Coaching Package",
      "amount": 2400.00,
      "credits_granted": 8,
      "date": "2025-09-22",
      "wc_order_id": 1234
    }
  ],

  "credit_history": [
    {"delta": 8, "reason": "8-Week Package Purchase", "balance_after": 8},
    {"delta": -1, "reason": "Session #1", "balance_after": 7},
    {"delta": -1, "reason": "Session #2", "balance_after": 6}
  ],

  "notes": [
    {
      "type": "session",
      "text": "Great progress on defining ideal client. Homework: Write 3 client avatars.",
      "created_by": "Walter",
      "date": "2025-11-19"
    },
    {
      "type": "general",
      "text": "Prefers morning appointments. Has young kids.",
      "is_pinned": true
    }
  ],

  "flags": [
    {
      "type": "follow_up",
      "message": "Schedule 90-day check-in call",
      "trigger_date": "2025-12-15",
      "is_active": true
    }
  ],

  "communications": [
    {"type": "confirmation", "subject": "Session Confirmed", "date": "2025-11-25", "status": "sent"},
    {"type": "reminder", "subject": "Session Tomorrow", "date": "2025-11-25", "status": "sent"}
  ]
}
```

---

## Build Phases

### Phase 1: Foundation (Current Sprint)
- [x] Communications system with email templates
- [ ] Customer Manager page structure (list + detail view)
- [ ] Basic customer profile with tabs
- [ ] Notes system

### Phase 2: Appointments Enhancement
- [ ] Appointment mode (in-person/virtual) support
- [ ] Meeting links storage and display
- [ ] ICS export with rich data
- [ ] Calendar quick actions

### Phase 3: Credits & Purchases
- [ ] Credit system with history tracking
- [ ] WooCommerce integration for credit products
- [ ] Manual purchase recording
- [ ] Package support

### Phase 4: Documents & External Links
- [ ] Document upload and management
- [ ] Google Drive URL storage
- [ ] Document preview/download

### Phase 5: Timeline & Flags
- [ ] Relationship timeline visualization
- [ ] Automated flags (inactive customer, etc.)
- [ ] Manual flag creation
- [ ] Dashboard flag notifications

### Phase 6: Reporting
- [ ] KPI dashboard
- [ ] Revenue reports
- [ ] Customer analytics
- [ ] Export functionality

### Phase 7: PDF Generation (Composer/mPDF)
- [ ] Set up Composer with `composer.json`
- [ ] Install mPDF library for PDF generation
- [ ] PDF receipt/invoice generation
- [ ] PDF appointment confirmation
- [ ] PDF customer summary reports
- [ ] PDF session notes export

### Phase 8: Integration
- [ ] 30-60-90 project linking (optional)
- [ ] Google Calendar sync
- [ ] Advanced automation triggers

---

## Questions for Discussion

1. **Credit Packages** - Should we support package types like "Buy 5 get 1 free" or just simple credit bundles?

2. **Document Storage** - WordPress Media Library, or should we plan for S3/external storage for larger deployments?

3. **Meeting Link Generation** - Manual entry only, or add Google Meet API integration (requires OAuth)?

4. **Customer Status Workflow** - What triggers status changes?
   - Active → Inactive: No booking in X days (configurable)?
   - VIP: Manual designation only, or earned by spending?
   - Paused: Manual only?

5. **Flags/Alerts Distribution** - Where should flags appear?
   - Customer Manager only?
   - Dashboard widget?
   - Email digest to admin?
   - Badge count on menu item?

6. **Multi-Provider Access** - Should providers see:
   - All customers?
   - Only customers they've served?
   - Configurable per provider?

7. **30-60-90 Depth** - Start with simple link, or build appointment-to-task connection now?

---

## Technical Notes

### CSS Design System (Consistent with existing)
```css
:root {
    --gp-primary: #c16107;
    --gp-primary-hover: #a85206;
    --gp-secondary: #c18f5f;
    --gp-success: #95c93d;
    --gp-text: #333333;
    --gp-text-light: #666666;
    --gp-border: #dddddd;
    --gp-bg: #ffffff;
    --gp-bg-light: #f8f8f8;
    --font-heading: 'Crimson Text', Georgia, serif;
    --font-body: 'Nunito Sans', -apple-system, sans-serif;
}
```

### API Endpoints Needed
```
GET    /guidepost/v1/customers
GET    /guidepost/v1/customers/{id}
POST   /guidepost/v1/customers
PUT    /guidepost/v1/customers/{id}
DELETE /guidepost/v1/customers/{id}

GET    /guidepost/v1/customers/{id}/appointments
GET    /guidepost/v1/customers/{id}/purchases
GET    /guidepost/v1/customers/{id}/documents
GET    /guidepost/v1/customers/{id}/notes
POST   /guidepost/v1/customers/{id}/notes
GET    /guidepost/v1/customers/{id}/communications
GET    /guidepost/v1/customers/{id}/credits
POST   /guidepost/v1/customers/{id}/credits

GET    /guidepost/v1/customers/{id}/export/ics
GET    /guidepost/v1/reports/revenue
GET    /guidepost/v1/reports/appointments
GET    /guidepost/v1/reports/customers
```

---

*This document is a living specification. Update as decisions are made and features are built.*
