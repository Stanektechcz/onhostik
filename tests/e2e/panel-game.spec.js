// The game server workbench of a signed-in customer (audit §5g-1): the seeded demo customer (DevAccountSeeder) owns
// a game server bound to a lab panel that is not reachable from here, so every tab must render and say so instead
// of breaking — the feature catalogue, the declarative spec and the usage endpoints answer the shapes the workbench
// expects. Signs in with the local dev login link (php artisan onhost:dev:login-link, local environment only).
import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const php = process.env.E2E_PHP || 'php';
const email = process.env.E2E_CUSTOMER_EMAIL || 'demo@onhost.cz';
const loginLink = (next) => execSync(`${php} artisan onhost:dev:login-link ${email} --next=${next}`, { encoding: 'utf8' }).trim().split('\n').pop().trim();
const api = (page, method, path, body) => page.evaluate(async ([m, p, b]) => {
  try { return await window.OnhostApi[m](p, b); } catch (e) { return { error: e.message, status: e.status, body: e.body }; }
}, [method, path, body]);

test.describe.configure({ mode: 'serial' });

test('the game workbench opens with its tools, the API answers features, spec and usage for a game server', async ({ page }) => {
  page.on('dialog', (dialog) => dialog.dismiss());
  await page.goto(loginLink('/panel'));
  await expect(page).toHaveURL(/\/panel/);
  await expect(page.locator('h1').first()).toBeVisible();

  // the demo customer's game server through the API the workbench uses
  const services = await api(page, 'get', '/services?family=game');
  expect(services.error, JSON.stringify(services)).toBeUndefined();
  const game = (services.data || []).find((s) => s.family === 'game');
  expect(game, JSON.stringify(services)).toBeTruthy();

  const features = await api(page, 'get', `/services/${game.id}/features`);
  expect(features.error, JSON.stringify(features)).toBeUndefined();
  const keys = Object.keys(features.data.features || features.data);
  for (const key of ['power', 'console', 'backups', 'startup', 'game_settings', 'game_files', 'game_databases', 'subusers', 'allocations', 'panel_access']) {
    expect(keys, `feature ${key}`).toContain(key);
  }

  const spec = await api(page, 'get', `/services/${game.id}/spec`);
  expect(spec.error, JSON.stringify(spec)).toBeUndefined();
  expect(Object.keys(spec.data)).toEqual(expect.arrayContaining(['name', 'image', 'variables', 'schedules', 'features']));

  const usage = await api(page, 'get', `/services/${game.id}/usage`);
  expect(usage.status === undefined || usage.status < 500, JSON.stringify(usage)).toBeTruthy();

  // the workbench: the game row opens, the tabs of a game server are there, a tab whose panel is unreachable says so instead of breaking
  await page.goto('/panel#/sluzba/game');
  const row = page.getByText(new RegExp((game.label || game.name).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))).first();
  await expect(row).toBeVisible();
  await row.click();
  await expect(page.getByRole('heading', { name: new RegExp('Konzole · ') }).first()).toBeVisible();
  for (const tab of ['Konzole', 'Startup a proměnné', 'Správce souborů']) {
    await expect(page.getByRole('button', { name: tab, exact: true }).first()).toBeVisible();
  }
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.getByRole('button', { name: 'Startup a proměnné', exact: true }).first().click();
  await expect(page.getByText(/Startup a proměnné|nedostupn|Načítám|proměnn/).first()).toBeVisible();
  await page.getByRole('button', { name: 'Správce souborů', exact: true }).first().click();
  await expect(page.getByText(/Správce souborů|nedostupn|Načítám|soubor/).first()).toBeVisible();
  await page.waitForTimeout(500);
  expect(errors).toEqual([]);
});
