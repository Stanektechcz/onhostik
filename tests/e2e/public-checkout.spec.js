// Public journey: catalogue → cart drawer → guest checkout → confirmation → client panel (Fakturace, Služby).
// The one thing this smoke exists for: the amounts the customer sees in the drawer, the checkout summary and the
// confirmation are the server's quote (POST /v1/cart/quote), never a client-side estimate — the September 2026 audit
// found a 336 Kč / 3 364 Kč mismatch exactly there. The order is placed by bank transfer (nothing is charged) and
// cancelled at the end, so the run leaves only a cancelled order behind.
import { expect, test } from '@playwright/test';

const czk = (minor) => new Intl.NumberFormat('cs-CZ', { minimumFractionDigits: minor % 100 ? 2 : 0, maximumFractionDigits: minor % 100 ? 2 : 0 }).format(minor / 100) + ' Kč';
const stamp = Date.now().toString(36);

test.describe.configure({ mode: 'serial' });

test('catalogue → cart → guest checkout → confirmation shows the server quote; the panel lists the proforma', async ({ page, request }) => {
  await page.goto('/#/webhosting');
  await expect(page.getByText('Tarify webhostingu')).toBeVisible();

  // the Standard plan goes into the cart monthly (no implicit yearly term); the drawer opens with the server quote
  const quote = page.waitForResponse((r) => r.url().includes('/v1/cart/quote') && r.request().method() === 'POST');
  await page.getByText('Vyzkoušet zdarma →').nth(1).click();
  const quoted = (await (await quote).json()).data;
  const drawer = page.locator('text=měsíčně, bez závazku').first();
  await expect(drawer).toBeVisible();
  expect(quoted.lines[0].period).toBe('month');
  await expect(page.getByText('Celkem měsíčně', { exact: false })).toBeVisible();
  await expect(page.getByText(czk(quoted.total), { exact: true }).first()).toBeVisible();

  // a yearly term re-quotes at the yearly list price and the drawer follows the quote
  const yearly = page.waitForResponse((r) => r.url().includes('/v1/cart/quote') && r.request().method() === 'POST');
  await page.getByText('12 měs.', { exact: true }).click();
  const quotedYear = (await (await yearly).json()).data;
  expect(quotedYear.lines[0].period).toBe('year');
  expect(quotedYear.total).toBeGreaterThan(quoted.total);
  await expect(page.getByText('Celkem za rok', { exact: false })).toBeVisible();
  await expect(page.getByText(czk(quotedYear.total), { exact: true }).first()).toBeVisible();
  await expect(page.getByText('platba na rok předem', { exact: false })).toBeVisible();

  // checkout: step 01 configuration → 02 guest details → 03 bank transfer
  await page.getByText('K pokladně').click();
  await expect(page).toHaveURL(/#\/kosik/);
  await expect(page.getByText('Souhrn objednávky', { exact: false })).toBeVisible();
  await expect(page.getByText('Celkem k platbě', { exact: false })).toBeVisible();
  await expect(page.getByText(czk(quotedYear.total), { exact: true }).first()).toBeVisible();
  await page.getByRole('button', { name: 'Pokračovat' }).first().click();

  const email = `e2e-${stamp}@onhost-test.cz`;
  const guestToggle = page.locator('label', { hasText: 'Nakupuji bez registrace' }).locator('input[type=checkbox]');
  if (await guestToggle.count()) {
    if (!(await guestToggle.isChecked())) await guestToggle.check();
  }
  await page.getByPlaceholder('jan@firma.cz').fill(email);
  await page.getByPlaceholder('Jan Novák').fill('E2E Tester');
  await page.getByPlaceholder('Dlouhá 12').fill('Krátká 5');
  await page.getByPlaceholder('Praha').fill('Brno');
  await page.getByPlaceholder('110 00').fill('602 00');
  await page.getByRole('button', { name: 'Pokračovat' }).first().click();

  await page.getByText('Bankovní převod', { exact: false }).first().click();
  const consent = page.locator('input[type=checkbox]').last();
  if (!(await consent.isChecked())) await consent.check();
  const placed = page.waitForResponse((r) => r.url().includes('/v1/checkout/guest') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Zaplatit a spustit server' }).click();
  const order = await (await placed).json();
  const totalMinor = order.total && typeof order.total === 'object' ? order.total.minor : Number(order.total);
  expect(order.number).toMatch(/^OH-\d{4}-\d+$/);
  expect(order.state).toBe('PENDING_PAYMENT');
  expect(totalMinor).toBe(quotedYear.total); // the invoice carries exactly what the customer saw

  // confirmation: the order number, the amount to transfer and the variable symbol
  await expect(page.getByText('Objednávka přijata', { exact: false })).toBeVisible();
  await expect(page.getByText(order.number, { exact: false }).first()).toBeVisible();
  await expect(page.getByText(czk(totalMinor), { exact: true }).first()).toBeVisible();
  const vs = order.bank_instructions && (order.bank_instructions.variable_symbol || order.bank_instructions.vs);
  if (vs) await expect(page.getByText(String(vs), { exact: false }).first()).toBeVisible();

  // the guest checkout signed the browser in: the overview shows the amount due, Fakturace the proforma, Služby the pending service
  // (the panel boots on the overview whatever the hash; sections are reached by changing the hash once the shell runs)
  await page.goto('/panel');
  await expect(page.getByText('Přehled účtu', { exact: false }).first()).toBeVisible();
  await expect(page.getByText(czk(totalMinor) + ' k úhradě', { exact: false }).first()).toBeVisible();
  await page.evaluate(() => { location.hash = '#/fakturace'; });
  await expect(page.getByText('Zálohová faktura', { exact: false }).first()).toBeVisible();
  await expect(page.getByText(czk(totalMinor), { exact: true }).first()).toBeVisible();
  await page.evaluate(() => { location.hash = '#/sluzby'; });
  await expect(page.getByText('Webhosting Standard', { exact: false }).first()).toBeVisible();

  // leave nothing to pay behind: cancel the unpaid order through the API with the browser's session
  // (through the panel's own API client, so the request carries the session, the CSRF token and the organization like every panel call)
  const cancelled = await page.evaluate(async (id) => {
    try { return await window.OnhostApi.post(`/orders/${id}/transition`, { to: 'cancelled', reason: 'e2e smoke' }); } catch (e) { return { error: e.message, status: e.status }; }
  }, order.order_id);
  expect(cancelled.error, JSON.stringify(cancelled)).toBeUndefined();
  expect(cancelled.data.state).toBe('CANCELLED');
});
