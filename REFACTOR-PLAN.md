# GuidePost Customer Module Refactoring Plan

## Overview

**Current State:** `class-guidepost-customers.php` - 2,482 lines, 47 methods
**Target State:** 6 focused files, each under 700 lines

## Current Method Map

| Method | Lines | Category |
|--------|-------|----------|
| `__construct` | 39-44 | Core |
| `init_hooks` | 46-64 | Core |
| `add_customers_menu` | 66-81 | Core |
| `handle_form_submissions` | 83-98 | Core |
| `handle_save_customer` | 100-159 | Core |
| `handle_delete_customer` | 161-183 | Core |
| `render_customers_page` | 185-206 | Core (router) |
| `render_customer_list` | 208-446 | Core |
| `render_admin_notices` | 1606-1626 | Core |
| `render_customer_detail` | 448-748 | Detail View |
| `render_overview_tab` | 750-863 | Detail View |
| `render_appointments_tab` | 865-993 | Detail View |
| `render_purchases_tab` | 995-1104 | Detail View |
| `render_documents_tab` | 1106-1164 | Detail View |
| `render_communications_tab` | 1166-1263 | Detail View |
| `render_notes_tab` | 1265-1326 | Detail View |
| `render_sidebar` | 1328-1422 | Detail View |
| `render_timeline` | 1424-1442 | Detail View |
| `render_customer_form` | 1444-1604 | Form |
| `get_customer` | 1628-1644 | Helper |
| `get_customer_appointments` | 1646-1668 | Helper |
| `get_customer_appointments_count` | 1670-1684 | Helper |
| `get_customer_purchases` | 1686-1702 | Helper |
| `get_customer_documents` | 1704-1721 | Helper |
| `get_customer_notes` | 1723-1760 | Helper |
| `get_customer_flags` | 1762-1783 | Helper |
| `get_credit_history` | 1785-1802 | Helper |
| `get_timeline_events` | 1804-1861 | Helper |
| `get_first_purchase` | 1863-1880 | Helper |
| `get_first_appointment` | 1882-1898 | Helper |
| `get_active_flags_count` | 1900-1925 | Helper |
| `get_status_color` | 1927-1942 | Helper |
| `get_flag_icon` | 1944-1960 | Helper |
| `get_file_icon` | 1962-1982 | Helper |
| `format_file_size` | 1984-1998 | Helper |
| `ajax_add_note` | 2000-2041 | AJAX |
| `ajax_delete_note` | 2043-2060 | AJAX |
| `ajax_toggle_note_pin` | 2062-2088 | AJAX |
| `ajax_add_flag` | 2090-2123 | AJAX |
| `ajax_dismiss_flag` | 2125-2151 | AJAX |
| `ajax_adjust_credits` | 2153-2204 | AJAX |
| `ajax_update_status` | 2206-2229 | AJAX |
| `ajax_save_field` | 2231-2261 | AJAX |
| `ajax_get_flags_count` | 2263-2270 | AJAX |
| `ajax_export_ics` | 2272-2329 | AJAX |
| `generate_ics` | 2331-2473 | ICS |
| `ics_escape` | 2475-2482 | ICS |

---

## New File Structure

```
includes/admin/
├── class-guidepost-customers.php          (~450 lines) - Main controller
├── class-guidepost-customer-detail.php    (~650 lines) - Detail page + tabs
├── class-guidepost-customer-form.php      (~180 lines) - Add/Edit form
├── class-guidepost-customer-ajax.php      (~350 lines) - AJAX handlers
├── class-guidepost-customer-helpers.php   (~400 lines) - Data access utilities
└── class-guidepost-customer-ics.php       (~160 lines) - ICS export
```

---

## File 1: class-guidepost-customers.php (Main Controller)

**Purpose:** Entry point, menu registration, routing, list view

**Methods to KEEP:**
- `get_instance()` (singleton)
- `__construct()`
- `init_hooks()` - MODIFIED to delegate AJAX registration
- `add_customers_menu()`
- `handle_form_submissions()`
- `handle_save_customer()`
- `handle_delete_customer()`
- `render_customers_page()` - MODIFIED to delegate to other classes
- `render_customer_list()`
- `render_admin_notices()`

**New Dependencies:**
```php
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-customer-helpers.php';
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-customer-detail.php';
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-customer-form.php';
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-customer-ajax.php';
require_once GUIDEPOST_PLUGIN_DIR . 'includes/admin/class-guidepost-customer-ics.php';
```

**Changes to init_hooks():**
```php
// Before (registers AJAX directly):
add_action( 'wp_ajax_guidepost_add_customer_note', array( $this, 'ajax_add_note' ) );
// ...etc

// After (delegates to AJAX class):
GuidePost_Customer_Ajax::get_instance(); // Self-registers its hooks
```

**Changes to render_customers_page():**
```php
// Before:
switch ( $action ) {
    case 'view':
        $this->render_customer_detail();
        break;
    case 'edit':
    case 'add':
        $this->render_customer_form();
        break;
    // ...
}

// After:
switch ( $action ) {
    case 'view':
        GuidePost_Customer_Detail::render( $customer_id );
        break;
    case 'edit':
    case 'add':
        GuidePost_Customer_Form::render( $customer_id );
        break;
    // ...
}
```

---

## File 2: class-guidepost-customer-helpers.php (Data Access)

**Purpose:** All data retrieval methods - stateless utility class

**Class Structure:**
```php
class GuidePost_Customer_Helpers {
    // All methods are PUBLIC STATIC - no instance needed

    public static function get_customer( $customer_id ) { ... }
    public static function get_customer_appointments( $customer_id, $limit = 100, $offset = 0 ) { ... }
    public static function get_customer_appointments_count( $customer_id ) { ... }
    public static function get_customer_purchases( $customer_id ) { ... }
    public static function get_customer_documents( $customer_id ) { ... }
    public static function get_customer_notes( $customer_id, $args = array() ) { ... }
    public static function get_customer_flags( $customer_id, $active_only = false ) { ... }
    public static function get_credit_history( $customer_id ) { ... }
    public static function get_timeline_events( $customer ) { ... }
    public static function get_first_purchase( $customer_id ) { ... }
    public static function get_first_appointment( $customer_id ) { ... }
    public static function get_active_flags_count() { ... }
    public static function get_status_color( $status ) { ... }
    public static function get_flag_icon( $flag_type ) { ... }
    public static function get_file_icon( $file_type ) { ... }
    public static function format_file_size( $bytes ) { ... }
}
```

**Usage Change Throughout Codebase:**
```php
// Before:
$customer = $this->get_customer( $customer_id );

// After:
$customer = GuidePost_Customer_Helpers::get_customer( $customer_id );
```

---

## File 3: class-guidepost-customer-detail.php (Detail View)

**Purpose:** Customer detail page with all tabs

**Class Structure:**
```php
class GuidePost_Customer_Detail {

    public static function render( $customer_id ) { ... }  // Main entry point

    private static function render_overview_tab( $customer ) { ... }
    private static function render_appointments_tab( $customer ) { ... }
    private static function render_purchases_tab( $customer ) { ... }
    private static function render_documents_tab( $customer ) { ... }
    private static function render_communications_tab( $customer ) { ... }
    private static function render_notes_tab( $customer ) { ... }
    private static function render_sidebar( $customer ) { ... }
    private static function render_timeline( $customer ) { ... }
    private static function render_modals( $customer ) { ... }  // Extract modal HTML
}
```

**Dependencies:**
- Uses `GuidePost_Customer_Helpers::` for all data access

---

## File 4: class-guidepost-customer-form.php (Form)

**Purpose:** Add/Edit customer form

**Class Structure:**
```php
class GuidePost_Customer_Form {

    public static function render( $customer_id = 0 ) { ... }
}
```

**Dependencies:**
- Uses `GuidePost_Customer_Helpers::get_customer()` for edit mode

---

## File 5: class-guidepost-customer-ajax.php (AJAX Handlers)

**Purpose:** All AJAX request handlers

**Class Structure:**
```php
class GuidePost_Customer_Ajax {

    private static $instance = null;

    public static function get_instance() { ... }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action( 'wp_ajax_guidepost_add_customer_note', array( $this, 'ajax_add_note' ) );
        add_action( 'wp_ajax_guidepost_delete_customer_note', array( $this, 'ajax_delete_note' ) );
        add_action( 'wp_ajax_guidepost_toggle_note_pin', array( $this, 'ajax_toggle_note_pin' ) );
        add_action( 'wp_ajax_guidepost_add_customer_flag', array( $this, 'ajax_add_flag' ) );
        add_action( 'wp_ajax_guidepost_dismiss_flag', array( $this, 'ajax_dismiss_flag' ) );
        add_action( 'wp_ajax_guidepost_adjust_credits', array( $this, 'ajax_adjust_credits' ) );
        add_action( 'wp_ajax_guidepost_update_customer_status', array( $this, 'ajax_update_status' ) );
        add_action( 'wp_ajax_guidepost_save_customer_field', array( $this, 'ajax_save_field' ) );
        add_action( 'wp_ajax_guidepost_get_active_flags_count', array( $this, 'ajax_get_flags_count' ) );
        add_action( 'wp_ajax_guidepost_export_ics', array( $this, 'ajax_export_ics' ) );
    }

    public function ajax_add_note() { ... }
    public function ajax_delete_note() { ... }
    public function ajax_toggle_note_pin() { ... }
    public function ajax_add_flag() { ... }
    public function ajax_dismiss_flag() { ... }
    public function ajax_adjust_credits() { ... }
    public function ajax_update_status() { ... }
    public function ajax_save_field() { ... }
    public function ajax_get_flags_count() { ... }
    public function ajax_export_ics() { ... }
}
```

**Dependencies:**
- Uses `GuidePost_Customer_Helpers::` for data access
- Uses `GuidePost_Customer_ICS::generate()` for export

---

## File 6: class-guidepost-customer-ics.php (ICS Export)

**Purpose:** ICS calendar file generation

**Class Structure:**
```php
class GuidePost_Customer_ICS {

    public static function generate( $appointment ) { ... }

    private static function escape( $text ) { ... }
}
```

---

## Execution Order (Phases)

### Phase 1: Create Helpers (LOWEST RISK)
1. Create `class-guidepost-customer-helpers.php` with all static methods
2. Add `require_once` to main file
3. Test: Verify file loads without errors
4. **DO NOT change callers yet** - just create the new file

### Phase 2: Migrate Helpers Usage
1. Update all `$this->get_customer()` calls to `GuidePost_Customer_Helpers::get_customer()`
2. Update all other helper method calls
3. Remove old methods from main class
4. Test: Verify customer list and detail pages load

### Phase 3: Create AJAX Class
1. Create `class-guidepost-customer-ajax.php`
2. Move all `ajax_*` methods
3. Update `init_hooks()` in main class to instantiate AJAX class
4. Remove AJAX hooks and methods from main class
5. Test: Verify notes, flags, credits, status changes work

### Phase 4: Create ICS Class
1. Create `class-guidepost-customer-ics.php`
2. Move `generate_ics()` and `ics_escape()`
3. Update AJAX class to call `GuidePost_Customer_ICS::generate()`
4. Test: Verify ICS export works

### Phase 5: Create Form Class
1. Create `class-guidepost-customer-form.php`
2. Move `render_customer_form()`
3. Update router in main class
4. Test: Verify add/edit customer works

### Phase 6: Create Detail Class
1. Create `class-guidepost-customer-detail.php`
2. Move all `render_*_tab()` methods + `render_sidebar()` + `render_timeline()`
3. Move `render_customer_detail()` as main entry point
4. Update router in main class
5. Test: Verify customer detail page with all tabs works

### Phase 7: Cleanup
1. Remove any dead code from main class
2. Verify line counts are in target range
3. Final integration test
4. Commit

---

## Risk Mitigation

1. **Commit after each phase** - easy rollback points
2. **Test after each phase** - catch breaks immediately
3. **Helpers first** - they have no dependencies, safest to extract
4. **Keep backup** - we have `8fad83c` to revert to if catastrophic failure

---

## Estimated Line Counts (Post-Refactor)

| File | Est. Lines | Methods |
|------|------------|---------|
| class-guidepost-customers.php | ~450 | 10 |
| class-guidepost-customer-helpers.php | ~400 | 16 |
| class-guidepost-customer-detail.php | ~650 | 10 |
| class-guidepost-customer-form.php | ~180 | 1 |
| class-guidepost-customer-ajax.php | ~350 | 11 |
| class-guidepost-customer-ics.php | ~160 | 2 |
| **TOTAL** | ~2,190 | 50 |

Note: Total is slightly less than current 2,482 due to removed duplicate boilerplate.

---

## Approval Checklist

Before proceeding, confirm:

- [ ] File structure looks correct
- [ ] Method assignments make sense
- [ ] Execution order is acceptable
- [ ] Risk mitigation is adequate
- [ ] Ready to begin Phase 1
