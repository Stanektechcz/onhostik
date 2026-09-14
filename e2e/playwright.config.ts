import { defineConfig, devices } from '@playwright/test';

// Surface smoke + visual regression against a running control plane (php artisan serve or docker compose).
// Baselines live in e2e/__screenshots__ and are compared with a 0.2 % pixel tolerance (design tokens must not drift).
export default defineConfig({
  testDir: './tests',
  timeout: 60_000,
  expect: { toHaveScreenshot: { maxDiffPixelRatio: 0.002, animations: 'disabled' } },
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.ONHOST_BASE_URL || 'http://localhost:8000',
    locale: 'cs-CZ',
    timezoneId: 'Europe/Prague',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1100 } } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
});
