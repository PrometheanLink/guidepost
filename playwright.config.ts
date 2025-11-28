import { defineConfig, devices } from '@playwright/test';

/**
 * GuidePost E2E Test Configuration
 *
 * Tests run against the Docker WordPress instance at localhost:8690
 */
export default defineConfig({
  testDir: './tests/e2e',

  /* Run tests in files in parallel */
  fullyParallel: false, // Sequential for WordPress to avoid session conflicts

  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,

  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,

  /* Single worker for WordPress admin testing */
  workers: 1,

  /* Reporter to use */
  reporter: [
    ['html', { open: 'never' }],
    ['list']
  ],

  /* Shared settings for all the projects below */
  use: {
    /* Base URL for WordPress */
    baseURL: 'http://localhost:8690',

    /* Collect trace when retrying the failed test */
    trace: 'on-first-retry',

    /* Capture screenshot on failure */
    screenshot: 'only-on-failure',

    /* Video on failure */
    video: 'retain-on-failure',

    /* Longer timeout for WordPress admin pages */
    actionTimeout: 15000,
    navigationTimeout: 30000,
  },

  /* Global timeout */
  timeout: 60000,

  /* Configure projects for major browsers */
  projects: [
    /* Setup project - handles login */
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },

    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        /* Use saved auth state */
        storageState: './tests/e2e/.auth/user.json',
      },
      dependencies: ['setup'],
    },
  ],

  /* Run your local dev server before starting the tests */
  // webServer: {
  //   command: 'docker-compose up',
  //   url: 'http://localhost:8690',
  //   reuseExistingServer: true,
  // },
});
