# GuidePost User Guide

**Version 1.0.0**

A comprehensive booking and customer relationship management plugin for WordPress, designed for coaching businesses and service providers.

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Dashboard](#dashboard)
4. [Customer Management](#customer-management)
5. [Appointments](#appointments)
6. [Services](#services)
7. [Providers](#providers)
8. [Communications](#communications)
9. [Settings](#settings)
10. [Troubleshooting](#troubleshooting)
11. [Credits](#credits)

---

## Introduction

### What is GuidePost?

GuidePost is a powerful WordPress plugin that combines appointment booking with comprehensive customer relationship management (CRM). Built specifically for coaching businesses, consultants, and service providers, GuidePost helps you:

- **Manage Appointments**: Schedule, track, and organize client sessions
- **Track Customer Relationships**: Maintain detailed customer profiles with notes, flags, and history
- **Handle Payments**: Integrate with WooCommerce for seamless payment processing
- **Communicate Effectively**: Send professional emails using customizable templates
- **Monitor Business Health**: View key metrics and alerts from your dashboard

### Key Features

| Feature | Description |
|---------|-------------|
| **Customer Manager** | Complete CRM with status tracking, notes, flags, and credits |
| **Appointment Booking** | Calendar-based scheduling with provider availability |
| **Service Catalog** | Define services with pricing, duration, and capacity |
| **Provider Management** | Manage team members with individual schedules |
| **Email Communications** | Template-based emails with personalization |
| **Credits System** | Pre-paid session credits for package deals |
| **Flags & Alerts** | Never miss a follow-up with smart reminders |
| **ICS Export** | Calendar integration for appointments |

---

## Getting Started

### Accessing GuidePost

After installation, GuidePost appears in your WordPress admin sidebar. Click **GuidePost** to access:

- **Dashboard** - Overview of your business
- **Appointments** - Manage scheduled sessions
- **Services** - Define what you offer
- **Providers** - Manage your team
- **Settings** - Configure the plugin
- **Communications** - Email templates and logs
- **Customers** - Your complete CRM

### Initial Setup Checklist

1. **Configure Settings** - Set your business name, timezone, and email preferences
2. **Add Services** - Create your service offerings with pricing and duration
3. **Add Providers** - Set up yourself and any team members
4. **Set Working Hours** - Define when each provider is available
5. **Customize Email Templates** - Brand your customer communications
6. **Add Your First Customer** - Start building your client base

---

## Dashboard

The Dashboard provides an at-a-glance view of your business health.

### KPI Widgets

| Widget | Description |
|--------|-------------|
| **Today's Appointments** | Number of sessions scheduled for today |
| **Pending Appointments** | Sessions awaiting confirmation |
| **Total Customers** | Your complete customer count |
| **Monthly Revenue** | Income for the current month |

### Flags & Alerts Widget

This widget shows customers requiring attention:

- **Follow Up** - Clients needing outreach
- **Payment Due** - Outstanding balances
- **Inactive** - Customers who haven't booked recently
- **VIP Check-in** - High-value clients to nurture
- **Birthday** - Upcoming client birthdays
- **Custom** - Your own custom alerts

Click any flag to go directly to that customer's profile.

### Upcoming Appointments

View your next scheduled sessions with:
- Client name and contact info
- Service and duration
- Appointment type (In-Person or Virtual)
- Quick status indicators

---

## Customer Management

The Customer Manager is your complete CRM system.

### Customer List

Access via **GuidePost → Customers**

#### Filtering & Search

- **Search Box**: Find customers by name, email, or company
- **Status Filter**: View by Active, VIP, Paused, Inactive, or Prospect
- **Sort Options**: Order by name, date added, last booking, or total spent

#### Customer Statuses

| Status | Description | Color |
|--------|-------------|-------|
| **Active** | Regular, engaged customers | Green |
| **VIP** | High-value clients deserving special attention | Orange |
| **Paused** | Temporarily inactive (e.g., on vacation) | Yellow |
| **Inactive** | Haven't booked in a while | Gray |
| **Prospect** | Potential customers not yet converted | Blue |

### Customer Detail Page

Click any customer to view their complete profile.

#### Header Section

- Customer avatar with initials
- Name and current status badge
- Contact information (email, phone)
- Company and job title
- Tags for categorization
- Quick action buttons: Send Email, Edit, Drive Folder

#### Stats Cards

- **Appointments** - Total sessions booked
- **Total Spent** - Lifetime revenue from this customer
- **Credits** - Available pre-paid session credits
- **Days as Customer** - Relationship duration

#### Tabs

##### Overview Tab

- **Customer Journey Timeline** - Key milestones (first contact, first purchase, first appointment, etc.)
- **Recent Appointments** - Last 5 sessions with details
- **Pinned Notes** - Important notes you've highlighted

##### Appointments Tab

Complete appointment history with:
- Date and time
- Service provided
- Provider name
- Appointment type (In-Person/Virtual)
- Status (Completed, Pending, Canceled, No Show)
- ICS export for calendar integration

##### Purchases Tab

Financial history including:
- Purchase date
- Description
- Amount paid
- Credits granted (if applicable)

##### Documents Tab

File management for:
- Contracts and agreements
- Session materials
- Customer uploads
- Any relevant documents

##### Communications Tab

Email history showing:
- All emails sent to this customer
- Template used
- Send date
- Open/click status (if tracking enabled)

##### Notes Tab

Your private notes about this customer:
- **General** - Standard notes
- **Session** - Notes from specific sessions
- **Follow-up** - Action items
- **Alert** - Important warnings
- **Private** - Sensitive information

### Sidebar Features

#### Flags & Alerts

View and manage customer-specific alerts:
- Click the **flag icon** to add a new flag
- Set flag type, message, and trigger date
- Dismiss flags when addressed

#### Quick Actions

- **Adjust Credits** - Add or subtract session credits
- **Change Status** - Update customer status

#### Details Panel

- Member since date
- Preferred contact method
- Additional customer information

### Adding a New Customer

1. Click **Add Customer** button
2. Fill in required fields:
   - First Name
   - Last Name
   - Email Address
3. Add optional information:
   - Phone number
   - Company and job title
   - Initial status
   - Tags (comma-separated)
   - First contact date
   - Notes
4. Click **Save Customer**

### Working with Notes

#### Adding a Note

1. Go to customer's **Notes** tab
2. Type your note in the text area
3. Select note type from dropdown
4. Click **Add Note**

#### Pinning Important Notes

- Click the **pin icon** on any note to pin it
- Pinned notes appear in the Overview tab
- Click again to unpin

#### Deleting Notes

- Click the **trash icon** to delete
- Confirm deletion when prompted
- Note: This action cannot be undone

### Managing Credits

Credits allow customers to pre-pay for sessions.

#### Adding Credits

1. Click **Adjust Credits** in the sidebar
2. Select **Add Credits**
3. Enter amount and reason
4. Click **Update Credits**

#### Using Credits

Credits are automatically tracked when:
- Applied to appointments
- Manually adjusted
- Granted from package purchases

#### Credit History

View complete credit transaction history in the customer's profile, showing:
- Date of transaction
- Amount added/subtracted
- Reason
- Running balance

### Managing Flags

Flags help you track important follow-ups.

#### Flag Types

| Type | Use Case |
|------|----------|
| **Follow Up** | General outreach needed |
| **Payment Due** | Outstanding balance |
| **Inactive** | Re-engagement needed |
| **VIP Check-in** | Nurture high-value relationship |
| **Birthday** | Send birthday wishes |
| **Custom** | Any other reminder |

#### Adding a Flag

1. Click the **flag button** in the sidebar
2. Select flag type
3. Enter your message
4. Set trigger date (optional)
5. Click **Add Flag**

#### Dismissing Flags

When you've addressed a flag:
1. Click **Dismiss** on the flag
2. It's marked as handled but preserved in history

---

## Appointments

### Viewing Appointments

Access via **GuidePost → Appointments**

#### List View

Default view showing appointments in a table:
- Customer name and contact
- Service and provider
- Date and time
- Duration
- Status
- Actions

#### Calendar View

Click **Calendar View** for a visual calendar showing:
- Monthly, weekly, or daily views
- Color-coded by service
- Click any event for details

### Appointment Statuses

| Status | Description |
|--------|-------------|
| **Pending** | Awaiting confirmation |
| **Approved** | Confirmed and scheduled |
| **Completed** | Session finished |
| **Canceled** | Appointment canceled |
| **No Show** | Customer didn't attend |

### Managing Appointments

#### Changing Status

1. Find the appointment in the list
2. Use the status dropdown
3. Select new status
4. Changes save automatically

#### Viewing Details

Click any appointment to see:
- Complete customer information
- Service details
- Provider notes
- Internal notes
- Payment status

### ICS Calendar Export

Export appointments to external calendars:

1. Click the **calendar icon** on any appointment
2. Download the .ics file
3. Import into Google Calendar, Outlook, Apple Calendar, etc.

---

## Services

### Managing Services

Access via **GuidePost → Services**

### Service Properties

| Property | Description |
|----------|-------------|
| **Name** | Service title shown to customers |
| **Description** | Detailed service description |
| **Duration** | Length in minutes |
| **Price** | Cost per session |
| **Color** | Calendar display color |
| **Status** | Active, Inactive, or Hidden |
| **Appointment Mode** | In-Person, Virtual, or Hybrid |

### Adding a Service

1. Click **Add Service**
2. Enter service name
3. Set duration and price
4. Choose a display color
5. Select appointment mode
6. Add description
7. Click **Save Service**

### Virtual Meeting Settings

For virtual services, configure:
- Default meeting platform (Zoom, Google Meet, Teams)
- Meeting link template
- Meeting instructions for customers

### Credit Requirements

Services can require credits instead of payment:
1. Enable **Requires Credit**
2. Set number of credits needed
3. Customers with sufficient credits can book without payment

---

## Providers

### Managing Providers

Access via **GuidePost → Providers**

### Provider Properties

| Property | Description |
|----------|-------------|
| **Name** | Provider's display name |
| **Email** | Contact email |
| **Phone** | Contact phone |
| **Bio** | Professional biography |
| **Photo** | Profile image |
| **Timezone** | Provider's timezone |
| **Status** | Active or Inactive |

### Adding a Provider

1. Click **Add Provider**
2. Enter name and email
3. Add bio and photo (optional)
4. Set timezone
5. Click **Save Provider**

### Setting Working Hours

After creating a provider:

1. Click **Edit** on the provider
2. Go to **Working Hours** section
3. For each day of the week:
   - Toggle day on/off
   - Set start and end times
4. Click **Save Changes**

### Managing Days Off

For vacations and holidays:

1. Go to provider's **Days Off** tab
2. Click **Add Days Off**
3. Select start and end dates
4. Enter reason (optional)
5. Click **Save**

Days off block all bookings for that provider during the specified period.

### Assigning Services

Control which services each provider offers:

1. Go to provider's **Services** tab
2. Check services they can provide
3. Optionally set custom pricing per provider
4. Click **Save**

---

## Communications

### Overview

Access via **GuidePost → Communications**

The Communications system helps you send professional emails to customers.

### Email Templates

#### Default Templates

GuidePost includes pre-built templates for:
- Appointment Confirmation
- Appointment Reminder
- Appointment Cancellation
- Appointment Rescheduled
- Payment Receipt
- Welcome Email
- Follow-Up Email
- Admin Notification

#### Editing Templates

1. Go to **Communications → Templates**
2. Click on any template
3. Edit subject line and body
4. Use personalization tags (see below)
5. Click **Save Template**

### Personalization Tags

Insert dynamic content using tags:

| Tag | Inserts |
|-----|---------|
| `{{customer_first_name}}` | Customer's first name |
| `{{customer_last_name}}` | Customer's last name |
| `{{customer_name}}` | Full name |
| `{{customer_email}}` | Email address |
| `{{service_name}}` | Service booked |
| `{{service_price}}` | Service price |
| `{{service_duration}}` | Duration in minutes |
| `{{appointment_date}}` | Formatted date |
| `{{appointment_time}}` | Formatted time |
| `{{provider_name}}` | Provider's name |
| `{{company_name}}` | Your business name |
| `{{booking_url}}` | Link to book again |

**Tip**: Click any tag in the sidebar to copy it to your clipboard!

### Composing Emails

#### Send to a Customer

1. Go to **Communications → Compose**
2. Select "Customer" as recipient type
3. Choose customer from dropdown
4. Select email template
5. Add custom message (optional)
6. Click **Preview** to review
7. Click **Send Email**

#### Send to Custom Email

1. Select "Manual Entry" as recipient type
2. Enter email address and name
3. Continue as above

### Email Log

View all sent emails:
- Recipient and subject
- Template used
- Send date
- Status (Sent, Failed, Pending)
- Click **View** to see customer details

### SMTP Settings

For reliable email delivery, configure SMTP:

1. Go to **Communications → Settings**
2. Enable SMTP
3. Enter your email provider's settings:
   - Host (e.g., smtp.gmail.com)
   - Port (e.g., 587)
   - Username
   - Password
   - Encryption (TLS/SSL)
4. Click **Send Test Email** to verify
5. Save settings

**Quick Presets**: Click Gmail, Outlook, or other provider buttons to auto-fill common settings.

---

## Settings

### General Settings

Access via **GuidePost → Settings**

#### Business Information

- **Business Name** - Displayed in emails and bookings
- **Admin Email** - Receives notifications
- **Timezone** - Your business timezone

#### Booking Settings

- **Booking Window** - How far ahead customers can book
- **Minimum Notice** - Required advance notice for bookings
- **Cancellation Policy** - Hours before appointment for free cancellation

#### Notification Settings

- **Send Confirmation Emails** - Automatic booking confirmations
- **Send Reminder Emails** - Automatic appointment reminders
- **Reminder Timing** - When to send reminders (24 hours, 48 hours, etc.)
- **Admin Notifications** - Get notified of new bookings

---

## Troubleshooting

### Common Issues

#### Buttons Not Working

If buttons on the customer detail page aren't responding:
1. Clear your browser cache
2. Refresh the page
3. Check browser console for JavaScript errors
4. Ensure no plugin conflicts exist

#### Emails Not Sending

1. Check SMTP settings are correct
2. Use the "Send Test Email" feature
3. Check spam/junk folders
4. Review email logs for error messages

#### Calendar Not Loading

1. Ensure JavaScript is enabled
2. Check for browser extensions blocking scripts
3. Try a different browser
4. Clear browser cache

#### Missing Data

If customers, appointments, or other data seems missing:
1. Check filters aren't hiding records
2. Clear search boxes
3. Refresh the page
4. Check database connection

### Getting Help

For technical support:
1. Check the documentation
2. Review error logs in WordPress
3. Contact your administrator
4. Visit the GitHub repository for known issues

---

## Credits

### About GuidePost

**GuidePost** is a WordPress booking and customer management plugin designed to help coaching businesses and service providers manage their client relationships effectively.

### Development

**Developed by:** PrometheanLink LLC

**Lead Developer:** Walter Hieber

### Version History

| Version | Date | Notes |
|---------|------|-------|
| 1.0.0 | November 2025 | Initial release |

### Technology

GuidePost is built with:
- PHP 7.4+
- WordPress 5.0+
- MySQL 5.7+
- JavaScript (ES6+)
- FullCalendar library
- WooCommerce integration (optional)

### License

GuidePost is proprietary software developed by PrometheanLink LLC. All rights reserved.

### Acknowledgments

Special thanks to:
- The WordPress community
- FullCalendar open-source project
- All beta testers and early adopters

---

## Quick Reference

### Keyboard Shortcuts

While on the GuidePost admin pages:
- **Escape** - Close modals
- **Enter** - Submit forms (when focused)

### Status Color Codes

| Color | Customer Status |
|-------|-----------------|
| 🟢 Green | Active |
| 🟠 Orange | VIP |
| 🟡 Yellow | Paused |
| ⚫ Gray | Inactive |
| 🔵 Blue | Prospect |

### Appointment Status Colors

| Color | Status |
|-------|--------|
| 🟡 Yellow | Pending |
| 🟢 Green | Approved |
| 🔵 Blue | Completed |
| 🔴 Red | Canceled |
| ⚫ Gray | No Show |

---

*GuidePost User Guide v1.0.0*
*© 2025 PrometheanLink LLC. All rights reserved.*
*Developed by Walter Hieber*
