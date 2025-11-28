import { test as setup, expect } from '@playwright/test';

const authFile = './tests/e2e/.auth/user.json';

/**
 * WordPress Admin Login Setup
 *
 * This runs once before all tests to authenticate and save the session.
 * All subsequent tests reuse this authenticated state.
 */
setup('authenticate as admin', async ({ page }) => {
  // Go to WordPress login
  await page.goto('/wp-login.php');

  // Fill in credentials
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin123');

  // Click login button
  await page.click('#wp-submit');

  // Wait for redirect to admin dashboard
  await page.waitForURL(/wp-admin/);

  // Verify we're logged in by checking for admin bar or dashboard
  await expect(page.locator('#wpadminbar')).toBeVisible();

  // Save authentication state
  await page.context().storageState({ path: authFile });
});
