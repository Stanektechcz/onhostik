// The staff console table views (audit §5g-1): the seeded platform owner (DevAccountSeeder, admin@onhost.cz) opens
// the automation rules, the scheduler with its liveness line, the game panel views and the fleet — every view is
// served by the staff API and rendered through the prototype table seam. An automation switch is flipped and put
// back through the console so the account and the settings are left as found.
import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

const php = process.env.E2E_PHP || 'php';
const email = process.env.E2E_STAFF_EMAIL || 'admin@onhost.cz';
const loginLink = (next) => execSync(`${php} artisan onhost:dev:login-link ${email} --next=${next}`, { encoding: 'utf8' }).trim().split('\n').pop().trim();
const api = (page, method, path, body) => page.evaluate(async ([m, p, b]) => {
  try { return await window.OnhostApi[m](p, b); } catch (e) { return { error: e.message, status: e.status, body: e.body }; }
}, [method, path, body]);

test.describe.configure({ mode: 'serial' });

test('automation, jobs, game and fleet views render from the staff API; a rule switch round-trips', async ({ page }) => {
  await page.goto(loginLink('/sprava'));
  await expect(page).toHaveURL(/\/sprava/);

  // the staff API behind the views
  const automation = await api(page, 'get', '/staff/automation');
  expect(automation.error, JSON.stringify(automation)).toBeUndefined();
  expect(automation.data.length).toBeGreaterThanOrEqual(12);
  expect(automation.liveness).toHaveProperty('scheduler');
  expect(automation.liveness).toHaveProperty('worker');
  const jobs = await api(page, 'get', '/staff/jobs');
  expect(jobs.error, JSON.stringify(jobs)).toBeUndefined();
  expect(jobs.data.scheduler.length).toBeGreaterThan(20);
  expect(jobs.data.bulk_actions).toContain('backup');
  const game = await api(page, 'get', '/staff/game');
  expect(game.error, JSON.stringify(game)).toBeUndefined();
  expect(Array.isArray(game.data.instances)).toBe(true);

  // the views: automation rules with their switches
  await page.goto('/sprava#/automation');
  await expect(page.getByText('Automatizace a pravidla', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Hlídání využití tarifu').first()).toBeVisible();
  await expect(page.getByText('Provisioning', { exact: true }).first()).toBeVisible();

  // a switch round-trip through the API the buttons call: off, recorded as off, on again
  const off = await api(page, 'put', '/staff/automation/commerce.prune', { enabled: false, reason: 'e2e smoke' });
  expect(off.error, JSON.stringify(off)).toBeUndefined();
  expect(off.enabled).toBe(false);
  const listed = await api(page, 'get', '/staff/automation');
  expect(listed.data.find((r) => r.key === 'commerce.prune').enabled).toBe(false);
  const on = await api(page, 'put', '/staff/automation/commerce.prune', { enabled: true, reason: 'e2e smoke' });
  expect(on.enabled).toBe(true);

  // scheduler with the liveness line, the game nodes, the fleet
  await page.goto('/sprava#/jobsadm');
  await expect(page.getByText('Běhové úlohy a fronty', { exact: true }).first()).toBeVisible();
  await expect(page.getByText(/plánovač (běží|NEBĚŽÍ) · worker fronty (běží|NEBĚŽÍ)/).first()).toBeVisible();
  await page.goto('/sprava#/gnodes');
  await expect(page.getByText('Herní uzly', { exact: true }).first()).toBeVisible();
  await page.goto('/sprava#/geggs');
  await expect(page.getByText('Šablony her', { exact: true }).first()).toBeVisible();
  await page.goto('/sprava#/fleet');
  await expect(page.getByText('Uzly a operace', { exact: true }).first()).toBeVisible();

  // every backed console view answers its deep link on a fresh load (§5h-8), the ones with VIEW_SLUG names included
  for (const [hash, text] of [['#/renewals', 'Obnovy a expirace'], ['#/galloc', 'Alokace a porty'], ['#/gprov', 'Provisioning fronta'], ['#/customers', 'Zákazníci'], ['#/incidents', 'Incidenty'], ['#/maintenance', 'Kalendář odstávek'], ['#/prehled', 'Přehled']]) {
    await page.goto('/sprava' + hash);
    await expect(page.getByText(text).first()).toBeVisible();
  }

  // a deep link with a selection opens the customer detail; the risk review answers the "Přehled rozhodnutí" action
  const customers = await api(page, 'get', '/staff/customers?limit=1');
  const first = (customers.data || [])[0];
  if (first && first.id) {
    await page.goto('/sprava#/customers/' + first.id);
    await expect(page.getByText(first.name).first()).toBeVisible();
  }
  const review = await api(page, 'get', '/staff/orders/risk-review?days=30');
  expect(review.error, JSON.stringify(review)).toBeUndefined();
  expect(review.data).toHaveProperty('signals');
  const chargebacks = await api(page, 'get', '/staff/chargebacks');
  expect(chargebacks.error, JSON.stringify(chargebacks)).toBeUndefined();
  expect(typeof chargebacks.data.percent).toBe('number');
  await page.goto('/sprava#/money');
  await expect(page.getByText('Vrácení kreditu (chargebacky)', { exact: true }).first()).toBeVisible();
  await expect(page.getByText(/vratný podíl nevyužitého období: \d+ %/).first()).toBeVisible(); // the table finished loading (not the "Načítám…" head)

  // the risk tuning endpoint behind the "Upravit váhy" action round-trips and resets
  const tuned = await api(page, 'put', '/staff/automation/order.risk/tuning', { weights: { rapid_orders: 35 }, hold_score: 65, reason: 'e2e smoke' });
  expect(tuned.error, JSON.stringify(tuned)).toBeUndefined();
  expect(tuned.weights.rapid_orders).toBe(35);
  const reset = await api(page, 'put', '/staff/automation/order.risk/tuning', { reset: true });
  expect(reset.hold_score).toBe(60);
});
