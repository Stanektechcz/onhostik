// Browser smoke of the public journey and the client panel (docs/runbooks/preproduction-audit.md §5c).
// Locally it runs against the dev server (E2E_BASE_URL, default http://127.0.0.1:8001); in CI the workflow boots
// `php artisan serve` on a freshly migrated and seeded SQLite database (see .github/workflows/e2e.yml).
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8001';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 90_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list']],
  use: {
    baseURL,
    locale: 'cs-CZ',
    viewport: { width: 1280, height: 900 },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: process.env.E2E_START_SERVER
    ? {
        command: 'php artisan serve --host=127.0.0.1 --port=8001',
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      }
    : undefined,
});
