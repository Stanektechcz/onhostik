// The public API documentation tells the truth (audit 2026-10, package D3): /dokumentace/api renders the OpenAPI contract with the
// vendored Redoc under a policy without eval, every error slug has its anchor, and /api and /dokumentace show what the API does
// (no invented endpoints, the real limits). Needs no sign-in.
import { expect, test } from '@playwright/test';

test('the API reference renders under a policy without eval and links every error code', async ({ page }) => {
  const problems = [];
  page.on('console', (message) => {
    // the one tolerated line: Redoc's footer asks its vendor's CDN for a logo, and the policy refuses (nothing is fetched)
    if (message.type() === 'error' && !message.text().includes('cdn.redoc.ly')) problems.push(message.text());
  });
  page.on('pageerror', (error) => problems.push(error.message));

  await page.addInitScript(() => document.addEventListener('securitypolicyviolation', (e) => { window.__csp = (window.__csp || []).concat(`${e.violatedDirective} ${e.blockedURI}`); }));
  const response = await page.goto('/dokumentace/api#idempotency-key-reused');
  const policy = response.headers()['content-security-policy'] || '';
  expect(policy).toContain("script-src 'self'");
  expect(policy).not.toContain('unsafe-eval');
  expect(policy).not.toMatch(/https?:/);

  await expect(page.getByRole('heading', { name: 'První volání za 5 minut' })).toBeVisible();
  // Redoc: its title block and a few of the 1000+ operations are in the page once it has rendered the contract
  await page.waitForFunction(() => document.querySelectorAll('#redoc [data-section-id]').length > 100, null, { timeout: 60_000 });
  expect(await page.evaluate(() => (document.querySelector('#redoc h1') || {}).textContent)).toContain('ONhost Cloud Platform API');

  // a code the API answers with has its row, in the form `help` links to (hyphens) and as the raw slug
  await expect(page.locator('#idempotency-key-reused')).toBeAttached();
  await expect(page.locator('a#idempotency_key_reused')).toBeAttached();
  await expect(page.locator('#rate-limited')).toBeAttached();
  await expect(page).toHaveURL(/#idempotency-key-reused$/);

  // the filter narrows the index
  const rows = page.locator('#err-table tbody tr:visible');
  const before = await rows.count();
  await page.locator('#err-filter').fill('idempotency');
  expect(await rows.count()).toBeGreaterThan(0);
  expect(await rows.count()).toBeLessThan(before);

  // every violation the browser reported: only that one logo (script-src would have named an eval)
  const violations = await page.evaluate(() => window.__csp || []);
  expect(violations.filter((v) => !v.includes('cdn.redoc.ly'))).toEqual([]);
  expect(problems).toEqual([]);
});

test('/api shows the real endpoints and limits, not the prototype story', async ({ page }) => {
  await page.goto('/api');
  const body = page.locator('body');
  await expect(body).toContainText('Autentizace a limity', { timeout: 30_000 });
  const text = await body.innerText();
  for (const invented of ['/v1/servers', '/v1/backups', '/v1/audit', '/v1/export', 'X-Confirm-Delete', 'X-Onhost-Confirm', 'api.onhost.cz']) {
    expect(text, `${invented} must not be on the page`).not.toContain(invented);
  }
  expect(text).toContain('/v1/services');
  expect(text).toContain('120 / min');
  expect(text).toMatch(/\d+ pokusů během \d+ h/);
  expect(text).not.toContain('24 hodin');
  expect(text).not.toContain('`');
});

test('/dokumentace carries the first-call guide', async ({ page }) => {
  await page.goto('/dokumentace');
  await page.getByRole('button', { name: 'První volání za 5 minut' }).click();
  await expect(page.getByText('Napište něco s Idempotency-Key').first()).toBeVisible({ timeout: 30_000 });
  const text = await page.locator('body').innerText();
  expect(text).not.toContain('X-Confirm-Delete');
  expect(text).not.toContain('`');
});
