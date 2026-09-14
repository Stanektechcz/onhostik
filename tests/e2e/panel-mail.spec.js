// The mail workbench of a signed-in customer (audit §5h-6): the seeded demo customer owns a mail domain on a lab
// node that is not reachable from here, so the workbench must render and say so; the feature catalogue and the
// declarative spec (forwards, catch-all, aliases, mailboxes, autoresponders, spam) answer the shapes the panel expects.
import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const php = process.env.E2E_PHP || 'php';
const email = process.env.E2E_CUSTOMER_EMAIL || 'demo@onhost.cz';
const loginLink = (next) => execSync(`${php} artisan onhost:dev:login-link ${email} --next=${next}`, { encoding: 'utf8' }).trim().split('\n').pop().trim();
const api = (page, method, path, body) => page.evaluate(async ([m, p, b]) => {
  try { return await window.OnhostApi[m](p, b); } catch (e) { return { error: e.message, status: e.status, body: e.body }; }
}, [method, path, body]);

test('the mail workbench opens and the API answers the mail features and the declarative spec', async ({ page }) => {
  page.on('dialog', (dialog) => dialog.dismiss());
  await page.goto(loginLink('/panel'));
  await expect(page.locator('h1').first()).toBeVisible();

  const services = await api(page, 'get', '/services?family=mail');
  expect(services.error, JSON.stringify(services)).toBeUndefined();
  const mail = (services.data || []).find((s) => s.family === 'mail');
  test.skip(!mail, 'the demo account has no mail service');

  const features = await api(page, 'get', `/services/${mail.id}/features`);
  expect(features.error, JSON.stringify(features)).toBeUndefined();
  const keys = Object.keys(features.data.features || features.data);
  for (const key of ['mailboxes', 'aliases', 'dkim', 'sending', 'forwards', 'catchall', 'autoresponder', 'spam']) {
    expect(keys, `feature ${key}`).toContain(key);
  }
  const spec = await api(page, 'get', `/services/${mail.id}/spec`);
  expect(spec.error, JSON.stringify(spec)).toBeUndefined();
  expect(Object.keys(spec.data)).toEqual(expect.arrayContaining(['forwards', 'catchall', 'aliases', 'mailboxes', 'autoresponders', 'spam', 'features']));
  const refused = await api(page, 'put', `/services/${mail.id}/spec`, { spec: { php: '8.3' } });
  expect(refused.status).toBe(422);

  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto('/panel#/sluzba/mail');
  const row = page.getByText(new RegExp((mail.label || mail.name).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))).first();
  await expect(row).toBeVisible();
  await row.click();
  await expect(page.locator('h1', { hasText: mail.label || mail.name }).first()).toBeVisible();
  await page.waitForTimeout(500);
  expect(errors).toEqual([]);
});
