// The partner portal shows only real partner data (audit A3, P0-2) and the concepts are staff-only (owner decision R11).
// Signs in with the local dev login link (php artisan onhost:dev:login-link, local environment only) as the seeded partner
// (agentura@onhost.cz), the seeded customer (demo@onhost.cz) and the seeded SRE (noc@onhost.cz) from DevAccountSeeder.
// Read-only: nothing is written.
import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const php = process.env.E2E_PHP || 'php';
const loginLink = (email, next) => execSync(`${php} artisan onhost:dev:login-link ${email} --next=${next}`, { encoding: 'utf8' }).trim().split('\n').pop().trim();
const NARRATED = ['Šindelář', 'Bezvazásilky', 'SINDELAR4821', 'Kavárna Zrno'];

test.describe.configure({ mode: 'serial' });

test('the partner sees the own partnership, never the prototype partner', async ({ page }) => {
  await page.goto(loginLink('agentura@onhost.cz', '/partner'));
  await expect(page).toHaveURL(/\/partner/);
  await expect(page.getByText(/Agentura Pixel s\.r\.o\. · \S+/).first()).toBeVisible();
  for (const tab of ['', '#/klienti', '#/vyplaty', '#/materialy']) {
    if (tab) await page.goto('/partner' + tab);
    await expect(page.getByText(/Agentura Pixel s\.r\.o\. · \S+/).first()).toBeVisible();
    const text = await page.locator('body').innerText();
    for (const literal of NARRATED) expect(text, `${tab || 'overview'}: ${literal}`).not.toContain(literal);
  }
  await page.goto('/partner#/materialy');
  await expect(page.getByText(/\/\?ref=/).first()).toBeVisible();
  expect(await page.locator('body').innerText()).not.toContain('ref=SINDELAR4821');
});

test('a failed partner read shows an error row instead of the prototype', async ({ page }) => {
  await page.goto(loginLink('agentura@onhost.cz', '/partner/vyplaty'));
  await page.route('**/v1/partner/**', (route) => route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ error: 'server_error', message: 'Simulated outage' }) }));
  await page.goto('/partner#/prehled');
  await expect(page.getByText(/Data partnera se nepodařilo načíst/).first()).toBeVisible();
  const text = await page.locator('body').innerText();
  for (const literal of NARRATED) expect(text).not.toContain(literal);
});

test('a customer without a partnership lands on the programme, not in the portal', async ({ page }) => {
  await page.goto(loginLink('demo@onhost.cz', '/partner'));
  await expect(page).toHaveURL(/\/reseller/);
  expect(await page.locator('body').innerText()).not.toContain('Bezvazásilky');
});

test('the concepts are for staff only, marked and unindexed', async ({ page }) => {
  const guest = await page.request.get('/widgets', { maxRedirects: 0 });
  expect(guest.status()).toBe(302);
  expect(guest.headers().location).toContain('/prihlaseni');

  await page.goto(loginLink('noc@onhost.cz', '/m'));
  await expect(page).toHaveURL(/\/m/);
  await expect(page.locator('#onhost-concept-banner')).toContainText('KONCEPT');
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow');
});
