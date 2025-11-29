# Null-Safe Escaping Fixes

## Issue

PHP 8.1+ deprecation warnings when passing `null` to string functions like `ltrim()`, `htmlspecialchars()`, etc.

**Error Message:**
```
Deprecated: ltrim(): Passing null to parameter #1 ($string) of type string is deprecated in /var/www/html/wp-includes/formatting.php on line 4486
```

## Root Cause

WordPress escaping functions (`esc_attr()`, `esc_html()`, `esc_textarea()`, `esc_url()`) internally call PHP string functions. When database fields are `NULL`, these functions receive `null` instead of a string.

## Fix Pattern

```php
// Before (causes deprecation warning when value is NULL):
esc_attr( $object->property )

// After (converts NULL to empty string):
esc_attr( $object->property ?? '' )
```

---

## Instances to Fix

### File: `includes/admin/class-guidepost-admin.php`

#### Appointment Edit Form (lines 1021-1184)

| Line | Current Code | Fix |
|------|--------------|-----|
| 1021 | `esc_attr( $appointment->booking_date )` | `esc_attr( $appointment->booking_date ?? '' )` |
| 1064 | `esc_textarea( $appointment->location )` | `esc_textarea( $appointment->location ?? '' )` |
| 1085 | `esc_url( $appointment->meeting_link )` | `esc_url( $appointment->meeting_link ?? '' )` |
| 1092 | `esc_attr( $appointment->meeting_password )` | `esc_attr( $appointment->meeting_password ?? '' )` |
| 1146 | `esc_attr( $appointment->credits_used )` | `esc_attr( $appointment->credits_used ?? 0 )` |
| 1156 | `esc_textarea( $appointment->internal_notes )` | `esc_textarea( $appointment->internal_notes ?? '' )` |
| 1162 | `esc_textarea( $appointment->admin_notes )` | `esc_textarea( $appointment->admin_notes ?? '' )` |
| 1167 | `esc_textarea( $appointment->customer_notes )` | `esc_textarea( $appointment->customer_notes ?? '' )` |
| 1179 | `esc_attr( $appointment->follow_up_date )` | `esc_attr( $appointment->follow_up_date ?? '' )` |
| 1184 | `esc_textarea( $appointment->follow_up_notes )` | `esc_textarea( $appointment->follow_up_notes ?? '' )` |

#### Service Form (lines 1264-1327)

| Line | Current Code | Fix |
|------|--------------|-----|
| 1264 | `esc_attr( $service->color )` | `esc_attr( $service->color ?? '#c16107' )` |
| 1327 | `esc_textarea( $is_edit ? $service->description : '' )` | Already safe (ternary) |

#### Provider Form/List (lines 1483-1597)

| Line | Current Code | Fix |
|------|--------------|-----|
| 1484 | `esc_html( $provider->email )` | `esc_html( $provider->email ?? '' )` |
| 1485 | `esc_html( $provider->phone )` | `esc_html( $provider->phone ?? '' )` |
| 1561 | `esc_textarea( $is_edit ? $provider->bio : '' )` | Already safe (ternary) |
| 1595 | `esc_attr( $service->color )` | `esc_attr( $service->color ?? '#c16107' )` |

---

### File: `includes/admin/class-guidepost-customers.php`

#### Customer List (lines 381-413)

| Line | Current Code | Fix |
|------|--------------|-----|
| 384 | `esc_html( $customer->company )` | `esc_html( $customer->company ?? '' )` |
| 397 | `esc_html( $customer->phone )` | `esc_html( $customer->phone ?? '' )` |

#### Customer Detail Header (lines 478-500)

| Line | Current Code | Fix |
|------|--------------|-----|
| 487 | `esc_html( $customer->company )` | `esc_html( $customer->company ?? '' )` |
| 489 | `esc_html( $customer->job_title )` | `esc_html( $customer->job_title ?? '' )` |
| 500 | `esc_html( $customer->phone )` | `esc_html( $customer->phone ?? '' )` |

#### Customer Sidebar (lines 1396-1406)

| Line | Current Code | Fix |
|------|--------------|-----|
| 1396 | `esc_html( $customer->source )` | `esc_html( $customer->source ?? '' )` |
| 1406 | `esc_html( $customer->timezone )` | `esc_html( $customer->timezone ?? '' )` |

#### Customer Form (line 1584)

| Line | Current Code | Fix |
|------|--------------|-----|
| 1584 | `esc_textarea( $is_edit ? $customer->notes : '' )` | Already safe (ternary) |

---

### File: `includes/admin/class-guidepost-communications.php`

#### Template Form (line 800)

| Line | Current Code | Fix |
|------|--------------|-----|
| 800 | `esc_textarea( $is_edit ? $template->body : '' )` | Already safe (ternary) |

---

### File: `includes/frontend/class-guidepost-shortcodes.php`

#### Service Cards (lines 216-220)

| Line | Current Code | Fix |
|------|--------------|-----|
| 216 | `esc_attr( $service->color )` | `esc_attr( $service->color ?? '#c16107' )` |
| 220 | `esc_html( $service->description )` | `esc_html( $service->description ?? '' )` |

---

## Summary

| File | Fixes Needed |
|------|-------------|
| `class-guidepost-admin.php` | 14 instances |
| `class-guidepost-customers.php` | 7 instances |
| `class-guidepost-communications.php` | 0 (already safe) |
| `class-guidepost-shortcodes.php` | 2 instances |
| **Total** | **23 instances** |

---

## Testing

After fixes, verify:
1. No deprecation warnings in `wp-content/debug.log`
2. All forms render correctly with NULL database values
3. All forms submit and save correctly
4. Playwright E2E tests pass

---

## Completed

- [x] class-guidepost-admin.php (14 fixes)
- [x] class-guidepost-customers.php (7 fixes)
- [x] class-guidepost-shortcodes.php (2 fixes)
- [x] Deployed to Docker
- [x] Playwright tests passed (45/45)
- [x] Debug log verified - no deprecation warnings

**Completed:** November 29, 2025
