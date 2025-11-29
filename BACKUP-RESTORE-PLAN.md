# GuidePost Backup & Restore Feature Plan

## Overview

This plan outlines a comprehensive backup and restore system for GuidePost that allows administrators to:
1. Create full backups of all GuidePost data
2. Download backups as portable files
3. Restore from backup files
4. Schedule automatic backups

---

## Data to Backup

### Database Tables (15 total)

| Table | Priority | Records |
|-------|----------|---------|
| `guidepost_customers` | Critical | Customer profiles |
| `guidepost_appointments` | Critical | All bookings |
| `guidepost_services` | Critical | Service catalog |
| `guidepost_providers` | Critical | Staff members |
| `guidepost_payments` | Critical | Payment records |
| `guidepost_customer_notes` | High | Internal notes |
| `guidepost_customer_flags` | High | Alerts/follow-ups |
| `guidepost_customer_documents` | High | Document metadata |
| `guidepost_customer_purchases` | High | Purchase history |
| `guidepost_credit_history` | High | Credit audit trail |
| `guidepost_provider_services` | Medium | Provider-service mapping |
| `guidepost_working_hours` | Medium | Provider schedules |
| `guidepost_days_off` | Medium | Time-off records |
| `guidepost_email_templates` | Medium | Custom email templates |
| `guidepost_notifications` | Low | Email send history |

### WordPress Options
- All `guidepost_*` options from `wp_options` table
- Approximately 15 settings entries

### Files (Optional)
- Customer documents from `wp-content/uploads/`
- Provider photos (if stored locally)
- Customer avatars (if stored locally)

---

## Feature Specifications

### 1. Backup Page UI

**Location:** GuidePost > Settings > Backup/Restore tab (or new submenu)

**Layout:**
```
+-----------------------------------------------+
|  BACKUP & RESTORE                             |
+-----------------------------------------------+
|                                               |
|  [CREATE BACKUP]                              |
|                                               |
|  Include in backup:                           |
|  [x] All database tables (required)           |
|  [x] Plugin settings                          |
|  [ ] Uploaded documents (increases file size) |
|                                               |
|  ─────────────────────────────────────────    |
|                                               |
|  EXISTING BACKUPS                             |
|  ┌─────────────────────────────────────────┐  |
|  │ Backup Name          Date       Size    │  |
|  │ backup-2025-11-28   Today      2.4 MB   │  |
|  │   [Download] [Restore] [Delete]         │  |
|  │ backup-2025-11-25   3 days     2.1 MB   │  |
|  │   [Download] [Restore] [Delete]         │  |
|  └─────────────────────────────────────────┘  |
|                                               |
|  ─────────────────────────────────────────    |
|                                               |
|  RESTORE FROM FILE                            |
|  [Choose File] [Upload & Restore]             |
|                                               |
|  ─────────────────────────────────────────    |
|                                               |
|  AUTOMATIC BACKUPS                            |
|  [x] Enable scheduled backups                 |
|  Frequency: [Daily ▼]                         |
|  Keep last: [7 ▼] backups                     |
|  [Save Schedule]                              |
|                                               |
+-----------------------------------------------+
```

### 2. Backup File Format

**Structure:** ZIP archive containing:
```
guidepost-backup-2025-11-28-143052.zip
├── manifest.json          # Backup metadata
├── database/
│   ├── customers.json
│   ├── appointments.json
│   ├── services.json
│   ├── providers.json
│   ├── payments.json
│   ├── customer_notes.json
│   ├── customer_flags.json
│   ├── customer_documents.json
│   ├── customer_purchases.json
│   ├── credit_history.json
│   ├── provider_services.json
│   ├── working_hours.json
│   ├── days_off.json
│   ├── email_templates.json
│   └── notifications.json
├── settings.json          # wp_options guidepost_*
└── uploads/               # (optional) document files
    └── guidepost/
        └── [document files]
```

**manifest.json:**
```json
{
  "version": "1.0",
  "plugin_version": "1.0.0",
  "wordpress_version": "6.8.3",
  "created_at": "2025-11-28T14:30:52Z",
  "site_url": "https://example.com",
  "table_prefix": "wp_guidepost_",
  "includes_uploads": true,
  "record_counts": {
    "customers": 6,
    "appointments": 23,
    "services": 5,
    "providers": 4,
    ...
  },
  "checksum": "sha256:abc123..."
}
```

### 3. Backup Process

1. **Initiate Backup**
   - User clicks "Create Backup"
   - Show progress indicator

2. **Export Tables**
   - Query each table sequentially
   - Convert to JSON format
   - Handle special characters and serialized data

3. **Export Settings**
   - Query wp_options WHERE option_name LIKE 'guidepost_%'
   - Save to settings.json

4. **Export Files (if selected)**
   - Scan guidepost_customer_documents for file paths
   - Copy files to temp directory
   - Maintain folder structure

5. **Create Archive**
   - Generate manifest.json with checksums
   - Create ZIP file
   - Store in `wp-content/guidepost-backups/`

6. **Cleanup**
   - Remove temp files
   - Return download link to user

### 4. Restore Process

1. **Upload/Select Backup**
   - User uploads ZIP or selects existing backup
   - Validate file structure

2. **Pre-Restore Checks**
   - Verify manifest.json exists
   - Check plugin version compatibility
   - Validate checksum integrity
   - Count records to restore

3. **Confirmation Dialog**
   ```
   ┌────────────────────────────────────────┐
   │  RESTORE CONFIRMATION                  │
   ├────────────────────────────────────────┤
   │  This will restore:                    │
   │  • 6 customers                         │
   │  • 23 appointments                     │
   │  • 5 services                          │
   │  • 4 providers                         │
   │  • ... and more                        │
   │                                        │
   │  ⚠️ WARNING: This will replace ALL    │
   │  existing GuidePost data!              │
   │                                        │
   │  [ ] Create backup before restoring    │
   │                                        │
   │  [Cancel]  [Restore Now]               │
   └────────────────────────────────────────┘
   ```

4. **Restore Execution**
   - Optionally create pre-restore backup
   - Disable foreign key checks
   - Truncate existing tables
   - Import data table by table (in dependency order)
   - Restore settings
   - Re-enable foreign key checks
   - Copy uploaded files (if included)

5. **Post-Restore**
   - Verify record counts match manifest
   - Clear any caches
   - Show success/failure report

### 5. Import Order (for foreign key integrity)

1. `services` (no dependencies)
2. `providers` (no dependencies)
3. `email_templates` (no dependencies)
4. `customers` (no dependencies)
5. `provider_services` (depends on providers, services)
6. `working_hours` (depends on providers)
7. `days_off` (depends on providers)
8. `appointments` (depends on customers, providers, services)
9. `customer_notes` (depends on customers)
10. `customer_flags` (depends on customers)
11. `customer_documents` (depends on customers, appointments)
12. `customer_purchases` (depends on customers)
13. `credit_history` (depends on customers)
14. `payments` (depends on appointments)
15. `notifications` (depends on appointments, customers, email_templates)

---

## Implementation Plan

### Phase 1: Core Backup System
**Files to create/modify:**
- `includes/admin/class-guidepost-backup.php` - Main backup class
- `includes/admin/views/backup-page.php` - Admin UI template
- `class-guidepost-admin.php` - Add menu item and page registration

**Features:**
- Create full database backup
- Export to JSON format in ZIP
- Download backup files
- List existing backups
- Delete old backups

### Phase 2: Restore System
**Features:**
- Upload backup file
- Validate backup integrity
- Preview restore contents
- Execute restore with progress
- Pre-restore backup option

### Phase 3: Scheduled Backups
**Features:**
- WordPress cron integration
- Configurable frequency (daily/weekly)
- Retention policy (keep last N backups)
- Email notification on backup completion/failure

### Phase 4: Advanced Features (Future)
- Selective restore (choose which tables)
- Partial backups (date range)
- Cloud storage integration (S3, Google Drive)
- Migration tool (different site/prefix)

---

## Technical Considerations

### Security
- Backup files stored outside web root OR protected with .htaccess
- Require admin capabilities for backup/restore
- Validate nonces on all actions
- Sanitize all file operations
- Log backup/restore activities

### Performance
- Use batched queries for large tables
- Stream ZIP creation for memory efficiency
- Background processing for large backups (via cron)
- Show progress for long operations

### Compatibility
- Handle different table prefixes
- Support serialized data (WordPress options)
- Preserve primary keys where needed
- Handle character encoding (UTF-8)

### Error Handling
- Transaction-based restore (rollback on failure)
- Detailed error logging
- User-friendly error messages
- Recovery options if restore fails

---

## File Structure After Implementation

```
guidepost/
├── includes/
│   └── admin/
│       ├── class-guidepost-backup.php      # NEW
│       └── views/
│           └── backup-page.php             # NEW
├── assets/
│   ├── css/
│   │   └── admin.css                       # Add backup page styles
│   └── js/
│       └── admin.js                        # Add backup page handlers
└── guidepost.php                           # Register backup class
```

---

## Estimated Effort

| Phase | Description | Complexity |
|-------|-------------|------------|
| Phase 1 | Core Backup | Medium |
| Phase 2 | Restore System | High |
| Phase 3 | Scheduled Backups | Medium |
| Phase 4 | Advanced Features | High |

---

## Questions to Consider

1. **Restore behavior:** Should restore merge with existing data or completely replace it?
   - Recommendation: **Replace** (with pre-restore backup option)

2. **Backup storage:** Store backups locally or offer cloud options?
   - Start with: **Local** (wp-content/guidepost-backups/)
   - Future: Add cloud storage options

3. **File uploads:** Include customer documents in backups?
   - Recommendation: **Optional** (checkbox, disabled by default for smaller backups)

4. **Notifications:** Should scheduled backups send email notifications?
   - Recommendation: **Yes** (on failure always, on success optionally)

5. **Backup retention:** How many automatic backups to keep?
   - Recommendation: **Configurable** (default 7)

---

## Next Steps

1. Review and approve this plan
2. Implement Phase 1 (Core Backup)
3. Test with sample data
4. Implement Phase 2 (Restore)
5. Test restore scenarios
6. Implement Phase 3 (Scheduled)
7. Deploy and document
