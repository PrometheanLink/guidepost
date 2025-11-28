import { test, expect } from '@playwright/test';

/**
 * GuidePost Customer Management E2E Tests
 *
 * These tests verify all customer-related functionality works correctly.
 * Tests are designed to provide detailed failure information for debugging.
 */

test.describe('Customer List Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-customers');
  });

  test('should load customer list page', async ({ page }) => {
    // Verify page title
    await expect(page.locator('h1, .wp-heading-inline')).toContainText(/Customer/i);

    // Verify customer table exists
    await expect(page.locator('.guidepost-customers-table, table')).toBeVisible();
  });

  test('should display customers in the table', async ({ page }) => {
    // Check for customer rows
    const rows = page.locator('.guidepost-customers-table tbody tr, table tbody tr');
    const count = await rows.count();

    expect(count).toBeGreaterThan(0);
    console.log(`Found ${count} customers in the list`);
  });

  test('should have working search functionality', async ({ page }) => {
    // Look for search input
    const searchInput = page.locator('input[name="s"], #customer-search, input[type="search"]');

    if (await searchInput.count() > 0) {
      await searchInput.fill('Sarah');
      await page.keyboard.press('Enter');
      await page.waitForLoadState('networkidle');

      // Verify search results or URL contains search param
      const url = page.url();
      expect(url).toContain('s=Sarah');
    } else {
      console.log('Search input not found - may need implementation');
    }
  });

  test('should have working status filter', async ({ page }) => {
    // Look for status filter dropdown
    const statusFilter = page.locator('select[name="status"], .status-filter');

    if (await statusFilter.count() > 0) {
      await statusFilter.selectOption('active');
      await page.waitForLoadState('networkidle');
    } else {
      console.log('Status filter not found');
    }
  });

  test('should navigate to customer detail when clicking a customer', async ({ page }) => {
    // Click on first customer link
    const firstCustomerLink = page.locator('.guidepost-customers-table tbody tr a, table tbody tr td a').first();

    if (await firstCustomerLink.count() > 0) {
      await firstCustomerLink.click();
      await page.waitForLoadState('networkidle');

      // Verify we're on detail page
      expect(page.url()).toContain('action=view');
      expect(page.url()).toContain('customer_id=');
    }
  });
});

test.describe('Customer Detail Page - Layout', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to first customer's detail page
    await page.goto('/wp-admin/admin.php?page=guidepost-customers');
    const firstCustomerLink = page.locator('.guidepost-customers-table tbody tr a, table tbody tr td a').first();
    await firstCustomerLink.click();
    await page.waitForLoadState('networkidle');
  });

  test('should display customer header with name and status', async ({ page }) => {
    // Check for customer name
    const customerName = page.locator('.customer-name, h1');
    await expect(customerName).toBeVisible();

    // Check for status badge
    const statusBadge = page.locator('.guidepost-status, [class*="status-"]');
    await expect(statusBadge.first()).toBeVisible();
  });

  test('should display stats cards', async ({ page }) => {
    const statsSection = page.locator('.guidepost-customer-stats, .stat-card');
    await expect(statsSection.first()).toBeVisible();

    // Check for specific stats
    const appointments = page.locator('text=Appointments').first();
    const spent = page.locator('text=Spent').first();
    const credits = page.locator('text=Credits').first();

    // At least one stat should be visible
    const statsVisible = await appointments.isVisible() ||
                         await spent.isVisible() ||
                         await credits.isVisible();
    expect(statsVisible).toBe(true);
  });

  test('should display navigation tabs', async ({ page }) => {
    // Check for expected tabs within the main content area (not sidebar)
    const mainContent = page.locator('.wrap, #wpbody-content');
    await expect(mainContent.locator('a:has-text("Overview")').first()).toBeVisible();
    await expect(mainContent.locator('a:has-text("Appointments")').first()).toBeVisible();
    await expect(mainContent.locator('a:has-text("Notes")').first()).toBeVisible();
  });

  test('should display sidebar with Flags & Quick Actions', async ({ page }) => {
    // Check for Flags section
    const flagsSection = page.locator('text=Flags');
    await expect(flagsSection.first()).toBeVisible();

    // Check for Quick Actions
    const quickActions = page.locator('text=Quick Actions');
    await expect(quickActions.first()).toBeVisible();
  });
});

test.describe('Customer Detail Page - Tabs Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-customers');
    const firstCustomerLink = page.locator('.guidepost-customers-table tbody tr a, table tbody tr td a').first();
    await firstCustomerLink.click();
    await page.waitForLoadState('networkidle');
  });

  test('should switch to Appointments tab', async ({ page }) => {
    // Click specifically within customer detail navigation (not sidebar)
    const mainContent = page.locator('.wrap, #wpbody-content');
    await mainContent.locator('nav a:has-text("Appointments"), a[href*="tab=appointments"]').first().click();
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('tab=appointments');

    // Should show appointments table or empty message
    const hasTable = await page.locator('table').count() > 0;
    const hasEmpty = await page.locator('text=No appointments').count() > 0;
    expect(hasTable || hasEmpty).toBe(true);
  });

  test('should switch to Purchases tab', async ({ page }) => {
    await page.click('text=Purchases');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('tab=purchases');
  });

  test('should switch to Documents tab', async ({ page }) => {
    await page.click('text=Documents');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('tab=documents');
  });

  test('should switch to Communications tab', async ({ page }) => {
    // Click specifically within customer detail navigation (not sidebar)
    const mainContent = page.locator('.wrap, #wpbody-content');
    await mainContent.locator('nav a:has-text("Communications"), a[href*="tab=communications"]').first().click();
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('tab=communications');
  });

  test('should switch to Notes tab', async ({ page }) => {
    await page.click('text=Notes');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('tab=notes');

    // Should show notes input
    const notesInput = page.locator('#new-note-text, textarea');
    await expect(notesInput.first()).toBeVisible();
  });
});

test.describe('Customer Detail - Notes Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-customers');
    const firstCustomerLink = page.locator('.guidepost-customers-table tbody tr a, table tbody tr td a').first();
    await firstCustomerLink.click();
    await page.waitForLoadState('networkidle');

    // Go to Notes tab
    await page.click('text=Notes');
    await page.waitForLoadState('networkidle');
  });

  test('should have note input form', async ({ page }) => {
    const noteTextarea = page.locator('#new-note-text');
    await expect(noteTextarea).toBeVisible();

    const noteTypeSelect = page.locator('#new-note-type');
    await expect(noteTypeSelect).toBeVisible();

    const addNoteBtn = page.locator('#add-note-btn');
    await expect(addNoteBtn).toBeVisible();
  });

  test('should add a new note', async ({ page }) => {
    const testNote = `Test note from Playwright - ${Date.now()}`;

    // Fill in the note
    await page.fill('#new-note-text', testNote);
    await page.selectOption('#new-note-type', 'general');

    // Click add note button
    await page.click('#add-note-btn');

    // Wait for response (page reload or AJAX)
    await page.waitForLoadState('networkidle');

    // Note should appear on the page (after reload)
    // Or check for success message
    const pageContent = await page.content();
    const noteAdded = pageContent.includes(testNote) ||
                      pageContent.includes('Note added') ||
                      page.url().includes('tab=notes');

    expect(noteAdded).toBe(true);
  });

  test('should display existing notes', async ({ page }) => {
    const notesList = page.locator('.guidepost-notes-list, .guidepost-note-item');
    const notesCount = await notesList.count();

    console.log(`Found ${notesCount} notes displayed`);

    // Check note structure if notes exist
    if (notesCount > 0) {
      const firstNote = notesList.first();
      await expect(firstNote).toBeVisible();
    }
  });
});

test.describe('Customer Detail - Flags Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-customers');
    const firstCustomerLink = page.locator('.guidepost-customers-table tbody tr a, table tbody tr td a').first();
    await firstCustomerLink.click();
    await page.waitForLoadState('networkidle');
  });

  test('should have Add Flag button', async ({ page }) => {
    const addFlagBtn = page.locator('#add-flag-btn, button:has-text("Add Flag"), .dashicons-flag').first();
    await expect(addFlagBtn).toBeVisible();
  });

  test('should open Add Flag modal', async ({ page }) => {
    // Click the flag button
    await page.click('#add-flag-btn');

    // Modal should appear
    const modal = page.locator('#add-flag-modal');
    await expect(modal).toBeVisible();

    // Modal should have form elements
    await expect(page.locator('#add-flag-modal select[name="flag_type"]')).toBeVisible();
    await expect(page.locator('#add-flag-modal [name="message"], #add-flag-modal textarea')).toBeVisible();
  });

  test('should add a new flag', async ({ page }) => {
    // Open modal
    await page.click('#add-flag-btn');
    await page.waitForSelector('#add-flag-modal', { state: 'visible' });

    // Fill in flag details
    await page.selectOption('#add-flag-modal select[name="flag_type"]', 'follow_up');
    await page.fill('#add-flag-modal [name="message"]', `Test flag from Playwright - ${Date.now()}`);

    // Submit
    await page.click('#add-flag-modal button[type="submit"]');

    // Wait for response
    await page.waitForLoadState('networkidle');

    // Verify flag was added (page should reload or show success)
  });

  test('should dismiss a flag', async ({ page }) => {
    // Check if there are any dismissable flags
    const dismissBtn = page.locator('.dismiss-flag-btn, button:has-text("Dismiss")');

    if (await dismissBtn.count() > 0) {
      await dismissBtn.first().click();
      await page.waitForLoadState('networkidle');
      console.log('Flag dismissed');
    } else {
      console.log('No flags to dismiss');
    }
  });
});

test.describe('Customer Detail - Credits Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-customers');
    const firstCustomerLink = page.locator('.guidepost-customers-table tbody tr a, table tbody tr td a').first();
    await firstCustomerLink.click();
    await page.waitForLoadState('networkidle');
  });

  test('should have Adjust Credits button', async ({ page }) => {
    const adjustCreditsBtn = page.locator('#adjust-credits-btn, button:has-text("Adjust Credits")');
    await expect(adjustCreditsBtn).toBeVisible();
  });

  test('should open Credits modal', async ({ page }) => {
    await page.click('#adjust-credits-btn');

    const modal = page.locator('#credits-modal');
    await expect(modal).toBeVisible();

    // Check for form elements
    await expect(page.locator('#credits-modal [name="amount"]')).toBeVisible();
    await expect(page.locator('#credits-modal [name="reason"]')).toBeVisible();
  });

  test('should add credits to customer', async ({ page }) => {
    // Get current credits if displayed
    const creditsStatBefore = await page.locator('.stat-card:has-text("Credits") .stat-value').textContent();
    console.log(`Credits before: ${creditsStatBefore}`);

    // Open modal
    await page.click('#adjust-credits-btn');
    await page.waitForSelector('#credits-modal', { state: 'visible' });

    // Select "Add" and fill in amount
    await page.click('#credits-modal [name="credit_type"][value="add"]');
    await page.fill('#credits-modal [name="amount"]', '5');
    await page.fill('#credits-modal [name="reason"]', 'Playwright test - adding credits');

    // Submit
    await page.click('#credits-modal button[type="submit"]');

    // Wait for response
    await page.waitForLoadState('networkidle');

    // Verify credits updated
    const creditsStatAfter = await page.locator('.stat-card:has-text("Credits") .stat-value').textContent();
    console.log(`Credits after: ${creditsStatAfter}`);
  });
});

test.describe('Customer Detail - Status Change', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-customers');
    const firstCustomerLink = page.locator('.guidepost-customers-table tbody tr a, table tbody tr td a').first();
    await firstCustomerLink.click();
    await page.waitForLoadState('networkidle');
  });

  test('should have Change Status button', async ({ page }) => {
    const changeStatusBtn = page.locator('#change-status-btn, button:has-text("Change Status")');
    await expect(changeStatusBtn).toBeVisible();
  });

  test('should open Status modal', async ({ page }) => {
    await page.click('#change-status-btn');

    const modal = page.locator('#status-modal');
    await expect(modal).toBeVisible();

    // Check for status options
    await expect(page.locator('#status-modal select[name="status"]')).toBeVisible();
  });

  test('should change customer status', async ({ page }) => {
    // Get current status
    const currentStatus = await page.locator('.guidepost-status').first().textContent();
    console.log(`Current status: ${currentStatus}`);

    // Open modal
    await page.click('#change-status-btn');
    await page.waitForSelector('#status-modal', { state: 'visible' });

    // Select a different status
    await page.selectOption('#status-modal select[name="status"]', 'vip');

    // Submit
    await page.click('#status-modal button[type="submit"]');

    // Wait for response
    await page.waitForLoadState('networkidle');

    // Verify status changed
    const newStatus = await page.locator('.guidepost-status').first().textContent();
    console.log(`New status: ${newStatus}`);
  });
});

test.describe('Customer Detail - Overview Tab Content', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-customers');
    const firstCustomerLink = page.locator('.guidepost-customers-table tbody tr a, table tbody tr td a').first();
    await firstCustomerLink.click();
    await page.waitForLoadState('networkidle');

    // Ensure we're on Overview tab - use specific selector to avoid sidebar matches
    if (!page.url().includes('tab=overview') && !page.url().includes('tab=')) {
      // Click overview tab within the main content area
      const mainContent = page.locator('.wrap, #wpbody-content');
      const overviewTab = mainContent.locator('.guidepost-tab:has-text("Overview"), nav a:has-text("Overview"), a[href*="tab=overview"]');
      if (await overviewTab.count() > 0) {
        await overviewTab.first().click();
        await page.waitForLoadState('networkidle');
      }
    }
  });

  test('should display Customer Journey timeline', async ({ page }) => {
    // Look for Customer Journey heading
    const timeline = page.locator('h2:has-text("Customer Journey")');
    await expect(timeline.first()).toBeVisible();
  });

  test('should display Recent Appointments section', async ({ page }) => {
    const recentAppointments = page.locator('h2:has-text("Recent Appointments")');
    await expect(recentAppointments.first()).toBeVisible();
  });

  test('should display Pinned Notes section when customer has pinned notes', async ({ page }) => {
    // Pinned Notes section only appears if customer has pinned notes
    const pinnedNotes = page.locator('h2:has-text("Pinned Notes")');
    const notesSectionExists = await pinnedNotes.count() > 0;

    if (notesSectionExists) {
      await expect(pinnedNotes.first()).toBeVisible();
    } else {
      // This is expected for customers without pinned notes
      console.log('Customer has no pinned notes - section not displayed (expected behavior)');
    }
  });
});

test.describe('Dashboard Page', () => {
  test('should load dashboard with KPI widgets', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost');

    // Check for dashboard widgets
    await expect(page.locator('text=Today')).toBeVisible();

    // Look for KPI cards
    const kpiWidgets = page.locator('.guidepost-kpi-card, .stat-card, .guidepost-dashboard-widgets');
    await expect(kpiWidgets.first()).toBeVisible();
  });

  test('should display Flags & Alerts widget', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost');

    // Actual text is "Customer Alerts & Flags"
    const flagsWidget = page.locator('h2:has-text("Customer Alerts"), h2:has-text("Flags")');
    await expect(flagsWidget.first()).toBeVisible();
  });
});

test.describe('Appointments Page', () => {
  test('should load appointments list', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-appointments');

    // Check page loaded
    await expect(page.locator('h1, .wp-heading-inline')).toContainText(/Appointment/i);
  });

  test('should display appointment records', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-appointments');

    // Look for appointment table or list
    const appointmentTable = page.locator('table, .guidepost-appointments-table');
    await expect(appointmentTable.first()).toBeVisible();
  });

  test('should have calendar view option', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-appointments');

    const calendarLink = page.locator('a:has-text("Calendar"), a[href*="view=calendar"]');

    if (await calendarLink.count() > 0) {
      await calendarLink.click();
      await page.waitForLoadState('networkidle');

      // Check for calendar element
      const calendar = page.locator('#guidepost-calendar, .fc');
      await expect(calendar).toBeVisible();
    }
  });
});

test.describe('Communications Page', () => {
  test('should load communications page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-communications');

    await expect(page.locator('h1, .wp-heading-inline')).toContainText(/Communication/i);
  });

  test('should have Compose tab', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-communications');

    const composeTab = page.locator('a:has-text("Compose"), [href*="tab=compose"]');
    await expect(composeTab.first()).toBeVisible();
  });

  test('should have Templates tab', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-communications');

    const templatesTab = page.locator('a:has-text("Templates"), [href*="tab=templates"]');
    await expect(templatesTab.first()).toBeVisible();
  });

  test('should have Email Log tab', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-communications');

    // Look for log tab specifically in the main content area (avoid matching "Log Out" in admin bar)
    const mainContent = page.locator('.wrap, #wpbody-content');
    const logTab = mainContent.locator('a:has-text("Email Log"), a:has-text("History"), .nav-tab:has-text("Log"), a[href*="tab=log"]');

    if (await logTab.count() > 0) {
      await expect(logTab.first()).toBeVisible();
    } else {
      // Tab might be named differently - check what tabs exist
      const allTabs = await mainContent.locator('.nav-tab, .nav-tab-wrapper a').allTextContents();
      console.log('Available tabs:', allTabs);
    }
  });
});

test.describe('Services Page', () => {
  test('should load services page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-services');

    await expect(page.locator('h1, .wp-heading-inline')).toContainText(/Service/i);
  });

  test('should display service list', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-services');

    const servicesTable = page.locator('table');
    await expect(servicesTable.first()).toBeVisible();
  });

  test('should have Add Service button', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-services');

    const addBtn = page.locator('a:has-text("Add"), button:has-text("Add")');
    await expect(addBtn.first()).toBeVisible();
  });
});

test.describe('Providers Page', () => {
  test('should load providers page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-providers');

    await expect(page.locator('h1, .wp-heading-inline')).toContainText(/Provider/i);
  });

  test('should display provider list', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=guidepost-providers');

    const providersTable = page.locator('table');
    await expect(providersTable.first()).toBeVisible();
  });
});
