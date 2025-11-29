# Backup & Restore Feature - Integration DIFF

This document outlines all changes needed to integrate the Backup & Restore feature into GuidePost.

---

## Summary of Changes

### Files Created (New)
1. `includes/admin/class-guidepost-backup.php` - Main backup class (972 lines)

### Files Modified
1. `guidepost.php` - Add require and initialization
2. `assets/css/admin.css` - Add backup page styles (~270 lines added)
3. `assets/js/admin.js` - Add backup module (~180 lines added)

---

## Integration Steps

### Step 1: Modify `guidepost.php`

**Location:** Around line 76-77, in `load_dependencies()` method

```diff
// Admin classes - always load so menu can register
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-admin.php';
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-communications.php';
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-customers.php';
+require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-backup.php';

// Frontend classes
```

**Location:** Around line 125, in `admin_init()` method

```diff
public function admin_init() {
    GuidePost_Admin::get_instance();
    GuidePost_Communications::get_instance();
    GuidePost_Customers::get_instance();
+   GuidePost_Backup::get_instance();

    // Initialize WooCommerce integration (WooCommerce is loaded by now)
    if ( class_exists( 'WooCommerce' ) ) {
```

---

## File Details

### 1. `includes/admin/class-guidepost-backup.php` (NEW FILE)

**Purpose:** Handles all backup and restore functionality

**Key Features:**
- Create backups of all GuidePost database tables
- Export to JSON format in ZIP archive
- Download backup files
- Restore from existing backups or uploaded files
- Merge OR Overwrite restore modes
- Selectable tables for granular backup/restore
- Pre-restore backup option
- Backup directory security (.htaccess, index.php)

**Public Methods:**
- `get_instance()` - Singleton accessor
- `create_backup($tables, $include_settings, $prefix)` - Create backup
- `restore_from_file($filepath, $mode, $tables)` - Restore backup
- `get_existing_backups()` - List backups
- `get_backup_info($filename)` - Get backup manifest

**AJAX Handlers:**
- `guidepost_create_backup` - Create backup via AJAX
- `guidepost_restore_backup` - Restore via AJAX
- `guidepost_delete_backup` - Delete backup via AJAX
- `guidepost_get_backup_info` - Get backup info for modal

**Menu:**
- Adds "Backup & Restore" submenu under GuidePost main menu
- URL: `admin.php?page=guidepost-backup`

---

### 2. `assets/css/admin.css` (MODIFIED)

**Lines Added:** ~270 lines (at end of file, lines 3608-3879)

**New Selectors:**
```css
.guidepost-backup-page
.guidepost-backup-container
.guidepost-backup-section
.guidepost-backup-options
.guidepost-backup-option
#guidepost-create-backup-btn
#guidepost-restore-modal
#restore-backup-info
#restore-tables-list
.guidepost-modal-warning
.guidepost-delete-backup-btn
.guidepost-backup-progress
```

**Responsive:** Mobile styles included for `@media (max-width: 782px)`

---

### 3. `assets/js/admin.js` (MODIFIED)

**Lines Added:** ~180 lines (before closing, lines 976-1164)

**New Module:** `GuidePostBackup`

**Methods:**
- `init()` - Initialize on backup page
- `bindEvents()` - Bind click handlers
- `openRestoreModal()` - Open restore modal and load backup info
- `displayBackupInfo()` - Display backup manifest in modal
- `selectAllTables()` / `deselectAllTables()` - Toggle table checkboxes
- `confirmDelete()` - Confirm delete action
- `closeModal()` - Close restore modal

**Events Bound:**
- `.guidepost-restore-btn` click - Opens restore modal
- `.guidepost-delete-backup-btn` click - Confirm delete
- `#select-all-tables` / `#deselect-all-tables` click - Toggle selections
- Modal close handlers

---

## Database Tables Backed Up

The backup system handles all 15 GuidePost tables:

| Table | Priority | Dependencies |
|-------|----------|--------------|
| `services` | Core (Required) | None |
| `providers` | Core (Required) | None |
| `customers` | Core (Required) | None |
| `appointments` | Core (Required) | customers, providers, services |
| `payments` | Core (Required) | appointments |
| `customer_notes` | Optional | customers |
| `customer_flags` | Optional | customers |
| `customer_documents` | Optional | customers, appointments |
| `customer_purchases` | Optional | customers |
| `credit_history` | Optional | customers |
| `provider_services` | Optional | providers, services |
| `working_hours` | Optional | providers |
| `days_off` | Optional | providers |
| `email_templates` | Optional | None |
| `notifications` | Optional | appointments, customers, email_templates |

**Import Order:** Tables are restored in dependency order to maintain foreign key integrity.

---

## Backup File Structure

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
└── settings.json          # wp_options guidepost_*
```

---

## Security Considerations

1. **Capability Check:** Requires `manage_options` capability
2. **Nonce Verification:** All actions verified with nonces
3. **File Sanitization:** All filenames sanitized with `sanitize_file_name()`
4. **Backup Directory Protection:**
   - `.htaccess` with `deny from all`
   - `index.php` with `// Silence is golden`
5. **Location:** Backups stored in `wp-content/guidepost-backups/`

---

## Testing Checklist

After integration, verify:

- [ ] "Backup & Restore" submenu appears under GuidePost
- [ ] Create Backup form displays with table checkboxes
- [ ] Core tables (required) are checked and disabled
- [ ] Optional tables can be selected/deselected
- [ ] Create Backup button creates a .zip file
- [ ] Backup appears in "Existing Backups" list
- [ ] Download button downloads the .zip file
- [ ] Delete button removes the backup (with confirmation)
- [ ] Restore button opens modal with backup info
- [ ] Restore mode (Overwrite/Merge) selection works
- [ ] Table selection in restore modal works
- [ ] Pre-restore backup checkbox creates backup before restore
- [ ] Restore from file upload works
- [ ] Success/error notices display correctly
- [ ] Mobile responsive layout works

---

## Rollback

If issues arise, remove these changes:

1. Remove `require_once` for backup class from `guidepost.php`
2. Remove `GuidePost_Backup::get_instance()` call from `guidepost.php`
3. Delete `includes/admin/class-guidepost-backup.php`
4. Remove CSS block starting with `/* Backup & Restore Page */` from `admin.css`
5. Remove `GuidePostBackup` module from `admin.js`
6. Remove `GuidePostBackup.init()` call from `admin.js`

---

## Notes

- No database schema changes required
- No new dependencies required
- ZipArchive PHP extension required (standard on most hosts)
- Compatible with existing admin styles and patterns
