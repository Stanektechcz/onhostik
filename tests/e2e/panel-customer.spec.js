// A signed-in customer in the client panel (audit §5d-1): deep links from mails open the section they name (path and
// hash forms), the service rows say what the customer pays and when, and the first tab of a service switches the
// billing period through the ordinary order flow. Signs in with the local dev login link (php artisan
// onhost:dev:login-link, local environment only) for the seeded demo customer (DevAccountSeeder); whatever the switch
// did is undone at the end so the account is left as found.
import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const php = process.env.E2E_PHP || 'php';
const email = process.env.E2E_CUSTOMER_EMAIL || 'demo@onhost.cz';
const loginLink = (next) => execSync(`${php} artisan onhost:dev:login-link ${email} --next=${next}`, { encoding: 'utf8' }).trim().split('\n').pop().trim();
const api = (page, method, path, body) => page.evaluate(async ([m, p, b]) => {
  try { return await window.OnhostApi[m](p, b); } catch (e) { return { error: e.message, status: e.status, body: e.body }; }
}, [method, path, body]);

test.describe.configure({ mode: 'serial' });

test('deep links open their section, service rows show plan and renewal, the first tab switches the billing period', async ({ page }) => {
  page.on('dialog', (dialog) => dialog.accept());

  // a link from a mail names the section by path: /panel/fakturace → Fakturace
  await page.goto(loginLink('/panel/fakturace'));
  await expect(page.locator('h1', { hasText: 'Fakturace' }).first()).toBeVisible();
  await expect(page).toHaveURL(/#\/fakturace/);

  // the hash form on a fresh load: /panel#/sluzba/web → the web services with plan · period · renewal · amount
  await page.goto('/panel#/sluzba/web');
  const row = page.getByText(/demo-web\.cz · CZ1 · Profi · (měsíčně|ročně) · obnova \d{1,2}\. \d{1,2}\. \d{4} · [\d ]+ Kč \/ (měs\.|rok)/).first();
  await expect(row).toBeVisible();
  const monthlyBefore = /měsíčně/.test(await row.textContent());

  // the workbench's first tab: the summary with the one-click period switch
  await row.click();
  await expect(page.getByText('Platba a obnova', { exact: true })).toBeVisible();
  await expect(page.getByText(/^(Profi · (měsíčně|ročně))/).first()).toBeVisible();
  const switchButton = page.getByRole('button', { name: monthlyBefore ? 'Platit ročně' : 'Platit měsíčně' }).first();
  await expect(switchButton).toBeVisible();
  const placed = page.waitForResponse((r) => r.url().endsWith('/v1/orders') && r.request().method() === 'POST');
  await switchButton.click(); // the confirm dialog is accepted above
  const order = await (await placed).json();
  expect(order.number).toMatch(/^OH-\d{4}-\d+$/);
  await expect(page.getByText(/Změna období OH-\d{4}-\d+ přijata/).first()).toBeVisible();

  // leave the demo account as found: an unpaid order is cancelled; a paid one (credit) already switched the period, so switch back (free)
  const detail = await api(page, 'get', `/orders/${order.order_id}`);
  expect(detail.error, JSON.stringify(detail)).toBeUndefined();
  if (['NEW', 'PENDING_PAYMENT'].includes(detail.data.state)) {
    const cancelled = await api(page, 'post', `/orders/${order.order_id}/transition`, { to: 'cancelled', reason: 'e2e smoke' });
    expect(cancelled.error, JSON.stringify(cancelled)).toBeUndefined();
    expect(cancelled.data.state).toBe('CANCELLED');
  } else {
    const services = await api(page, 'get', '/services?product_key=web-hosting');
    const service = (services.data || []).find((s) => s.hostname === 'demo-web.cz');
    expect(service, JSON.stringify(services)).toBeTruthy();
    const back = monthlyBefore ? 'month' : 'year';
    const cart = await api(page, 'put', '/cart', { items: [{ product_key: 'web-hosting', plan_key: 'profi', qty: 1, period: back, config: { upgrade_of: service.id } }], commit_months: 1, currency: 'CZK', promo_code: null });
    expect(cart.error, JSON.stringify(cart)).toBeUndefined();
    const quote = await api(page, 'post', '/cart/quote', {});
    expect(quote.error, JSON.stringify(quote)).toBeUndefined();
    const consents = await page.evaluate(() => { const D = window.ONHOST_PANEL || {}, out = {}; Object.keys(D.consents || {}).forEach((k) => { out[k] = { version: D.consents[k] }; }); return out; });
    const undo = await api(page, 'post', '/orders', { quote_id: quote.data.quote_id, consents, payment: { mode: 'wallet' }, source: 'panel' });
    expect(undo.error, JSON.stringify(undo)).toBeUndefined();
  }
});
