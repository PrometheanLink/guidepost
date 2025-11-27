# GuidePost Plugin - Development Handoff

## Overview

GuidePost is a WordPress booking and customer management plugin built for **Sojourn Coaching** (Kim Benedict). This is a **standalone plugin** - it does NOT need to integrate with the 30-60-90 system or any other external projects.

---

## Docker Development Environment

### Container Details
- **Container Name:** `guidepost-wordpress`
- **WordPress URL:** http://localhost:8690
- **Admin URL:** http://localhost:8690/wp-admin
- **Admin Credentials:** Check docker-compose.yml or use standard dev credentials

### Docker Commands

```bash
# Deploy plugin changes to Docker
docker cp "C:\Users\whieb\OneDrive\Documents\Level 5 - tribe\PrometheanLink LLC\Internal Projects\Project-Phase-Journey\guidepost" guidepost-wordpress:/var/www/html/wp-content/plugins/

# Run sample data generator
docker exec guidepost-wordpress bash -c "php /var/www/html/wp-content/plugins/guidepost/sample-data.php"

# Access WordPress container shell
docker exec -it guidepost-wordpress bash

# Check if container is running
docker ps | grep guidepost
```

### Project Location
```
C:\Users\whieb\OneDrive\Documents\Level 5 - tribe\PrometheanLink LLC\Internal Projects\Project-Phase-Journey\guidepost
```

---

## GitHub Repository

- **Repository:** https://github.com/PrometheanLink/guidepost
- **Branch:** master

---

## Current Status (November 26, 2025)

### Completed Features

#### Core Booking System
- [x] Services management with pricing, duration, colors
- [x] Provider management with working hours and days off
- [x] Appointment booking with calendar interface
- [x] REST API for frontend booking flow
- [x] WooCommerce payment integration
- [x] ICS calendar export

#### Customer Manager
- [x] Customer list with search and status filters
- [x] Customer detail pages with tabs (Overview, Appointments, Purchases, Documents, Communications, Notes)
- [x] Customer status badges (Active, VIP, Paused, Inactive, Prospect)
- [x] Credits system with add/subtract functionality
- [x] Pagination on Appointments and Communications tabs

#### Flags & Alerts System
- [x] Add Flag modal with types: Follow Up, Payment Due, Inactive, VIP Check-in, Birthday, Custom
- [x] Dismiss flags functionality
- [x] Dashboard widget showing active flags
- [x] Priority styling based on flag type

#### Notes System
- [x] Add notes with types: General, Session, Follow-up, Alert, Private
- [x] Notes display with author and timestamp
- [x] Pin/unpin notes (UI exists, backend connected)
- [x] Delete notes

#### Communications System
- [x] Email compose with customer selection
- [x] Email templates management
- [x] Email Log with pagination
- [x] Personalization tags with click-to-copy
- [x] View button links to customer's Communications tab
- [x] Quick Tips sidebar

#### Dashboard
- [x] KPI widgets (Today's Appointments, Pending, Customers, Revenue)
- [x] Customer Alerts & Flags widget
- [x] Upcoming appointments list

#### UI Polish
- [x] Professional CSS styling throughout
- [x] Modal dialogs for Flags, Credits, Status changes
- [x] Pagination on data tables
- [x] WordPress native styling integration

### Database Tables (15 total)
1. `wp_guidepost_services`
2. `wp_guidepost_providers`
3. `wp_guidepost_provider_services`
4. `wp_guidepost_working_hours`
5. `wp_guidepost_days_off`
6. `wp_guidepost_customers`
7. `wp_guidepost_customer_notes`
8. `wp_guidepost_customer_purchases`
9. `wp_guidepost_customer_documents`
10. `wp_guidepost_customer_flags`
11. `wp_guidepost_credit_history`
12. `wp_guidepost_appointments`
13. `wp_guidepost_payments`
14. `wp_guidepost_notifications` (email log)
15. `wp_guidepost_email_templates`

---

## What Still Needs Testing/Polish

### Known Areas to Review
1. **Dead Buttons** - Most buttons now work, but should do a full UI walkthrough
2. **ICS Export** - Button wired up, test actual export functionality
3. **Email Sending** - Templates and compose work, test actual email delivery
4. **WooCommerce Integration** - Payment flow needs live testing
5. **Frontend Booking Form** - REST API works, test customer-facing flow

### Potential Enhancements
1. Document upload functionality
2. Recurring appointments
3. SMS notifications
4. Calendar sync (Google Calendar, Outlook)
5. Reporting/analytics dashboard

---

## Key Files

### PHP Classes
- `includes/class-guidepost.php` - Main plugin class
- `includes/class-guidepost-database.php` - Database schema and operations
- `includes/class-guidepost-email.php` - Email functionality
- `includes/admin/class-guidepost-admin.php` - Admin dashboard
- `includes/admin/class-guidepost-customers.php` - Customer manager
- `includes/admin/class-guidepost-communications.php` - Communications system
- `includes/admin/class-guidepost-appointments.php` - Appointments management
- `includes/admin/class-guidepost-services.php` - Services management
- `includes/admin/class-guidepost-providers.php` - Provider management

### Assets
- `assets/css/admin.css` - Admin styling (3400+ lines)
- `assets/js/admin.js` - Admin JavaScript (1000+ lines)
- `assets/css/frontend.css` - Frontend booking form styles
- `assets/js/frontend.js` - Frontend booking functionality

### Sample Data
- `sample-data.php` - Generates test data for Sojourn Coaching
  - Creates provider: Kim Benedict
  - Creates 5 services (Discovery, Coaching, Extended, Check-In, VIP Day)
  - Creates 6 sample customers
  - Creates 23 sample appointments
  - Creates customer notes, flags, purchases, credit history
  - Creates 12 email log entries

---

## Sample Data

Run this command to regenerate sample data:
```bash
docker exec guidepost-wordpress bash -c "php /var/www/html/wp-content/plugins/guidepost/sample-data.php"
```

Sample customers created:
1. Sarah Mitchell (Active) - sarah.mitchell@example.com
2. Michael Chen (VIP) - michael.chen@example.com
3. Jennifer Rodriguez (Active) - jennifer.r@example.com
4. David Thompson (Paused) - david.thompson@example.com
5. Amanda Foster (Prospect) - amanda.foster@example.com
6. Robert Williams (Active) - robert.w@example.com

---

## Important Notes

1. **Standalone Plugin** - GuidePost is independent and does NOT integrate with:
   - 30-60-90 day planning system
   - Any other external projects
   - This is a complete booking/CRM solution on its own

2. **End User** - Kim Benedict of Sojourn Coaching
   - Non-technical user
   - Communications interface simplified for ease of use
   - Personalization tags made click-to-copy friendly

3. **Auto-Upgrade** - Database has version tracking for schema migrations

---

## Quick Test URLs

- **Dashboard:** http://localhost:8690/wp-admin/admin.php?page=guidepost
- **Customers:** http://localhost:8690/wp-admin/admin.php?page=guidepost-customers
- **Customer Detail:** http://localhost:8690/wp-admin/admin.php?page=guidepost-customers&action=view&customer_id=45
- **Appointments:** http://localhost:8690/wp-admin/admin.php?page=guidepost-appointments
- **Communications:** http://localhost:8690/wp-admin/admin.php?page=guidepost-communications
- **Services:** http://localhost:8690/wp-admin/admin.php?page=guidepost-services
- **Providers:** http://localhost:8690/wp-admin/admin.php?page=guidepost-providers
- **Settings:** http://localhost:8690/wp-admin/admin.php?page=guidepost-settings

---

## Session Summary (Nov 26, 2025)

This session accomplished:
1. Fixed CSS on Customer Manager pages
2. Added Edit/Delete buttons to CRUD rows
3. Polished Customer Detail page with professional styling
4. Simplified Communications sidebar for non-technical users
5. Added sample email log entries
6. Enhanced Email Log UI with relative dates and previews
7. Added pagination to Communications and Appointments tabs
8. Implemented click-to-copy for personalization tags
9. Fixed Email Log View button to link to customer page
10. Fixed dashboard SQL errors (priority column)
11. Added working modals for Flags, Credits, Status changes
12. Fixed Notes Add button functionality
13. Fixed dashboard flag widget warnings

All changes committed and pushed to GitHub.
