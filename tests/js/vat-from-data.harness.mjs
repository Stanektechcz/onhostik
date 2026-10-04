/* Harness for TASK-0070 (audit 2026-10 P1-6 leftovers): the cart's domain line and the panel order wizard's "credit covers the order"
 * use the VAT rate the server publishes (ONHOST_DATA.vat() on the public site, ONHOST_PANEL.vat in the panel), not a hard-coded 21 %.
 * Each module runs in its own sandbox with a made-up rate; no browser, no network.
 *   node tests/js/vat-from-data.harness.mjs
 * Prints JSON {failures: [...], seen: {...}}; exit code 0 = all checks passed (run by tests/Feature/Http/VatFromDataTest.php). */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const api = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api');
const failures = [];
const seen = {};

function sandbox(file, extra) {
  const ctx = { console, JSON, Object, Array, String, Number, Math, RegExp, Date, isNaN, Infinity, Promise, setTimeout, clearTimeout };
  ctx.window = ctx;
  ctx.document = { documentElement: { lang: 'cs' }, readyState: 'complete', addEventListener() {}, querySelector() { return null; }, querySelectorAll() { return []; }, createElement() { return { style: {}, setAttribute() {}, appendChild() {} }; }, head: { appendChild() {} }, body: { appendChild() {} } };
  ctx.localStorage = { getItem() { return null; }, setItem() {}, removeItem() {} };
  ctx.addEventListener = () => {};
  Object.assign(ctx, extra);
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(path.join(api, file), 'utf8'), ctx, { filename: file });
  return ctx;
}

for (const rate of [0.1, 0.21]) {
  // the public cart: a domain line shows its price with VAT
  const cart = sandbox('onhost-cart.api.js', { ONHOST_DATA: { vat: () => rate, pricing: () => ({}), addons: () => ({}), tlds: () => [] } });
  const row = cart.OnhostCart.cartRow({ id: 'd1', name: 'priklad.cz', price: 1000, qty: 1, meta: { product_key: 'domain', config: { tld: 'cz', period_years: 1 } } }, null, (a) => a);
  const expected = 's DPH ' + Math.round(1000 * (1 + rate)).toLocaleString('cs-CZ') + ' Kč';
  seen[`cart@${rate}`] = row && row.lineVat;
  if (!row || row.lineVat !== expected) failures.push(`cart ${rate}: lineVat ${row && row.lineVat} !== ${expected}`);

  // the panel order wizard: credit of 1 150 covers 1 000 + 10 % VAT, but not 1 000 + 21 %
  const order = sandbox('onhost-panel-order.api.js', { ONHOST_PANEL: { vat: rate, catalog: [] } });
  const cmp = { state: { credit: 1150, lang: 'cs' }, money: (n) => String(n) };
  const wallet = order.OnhostPanelOrder.payOptions(cmp, {}, [null, null, 1000])[0][2];
  const covers = /ihned/.test(wallet);
  seen[`order@${rate}`] = wallet;
  if (covers !== (1000 * (1 + rate) <= 1150)) failures.push(`order ${rate}: credit 1150 for 1000 net ${covers ? 'covers' : 'does not cover'} the order`);
}

// without a published rate (no active tax rules) the statutory 21 % stays the fallback
const bare = sandbox('onhost-panel-order.api.js', { ONHOST_PANEL: { vat: null, catalog: [] } });
const bareWallet = bare.OnhostPanelOrder.payOptions({ state: { credit: 1150, lang: 'cs' }, money: (n) => String(n) }, {}, [null, null, 1000])[0][2];
if (/ihned/.test(bareWallet)) failures.push('order without a rate: 1150 must not cover 1000 + 21 %');

// no module of this set multiplies by a literal 21 % any more
for (const file of ['onhost-cart.api.js', 'onhost-game-config.api.js', 'onhost-panel-order.api.js', 'onhost-panel-shop.api.js', 'onhost-admin.api.js']) {
  const src = fs.readFileSync(path.join(api, file), 'utf8');
  const hits = src.split('\n').filter((l) => /[*/]\s*1\.21\b|\b1\.21\s*\*|[*]\s*0\.21\b/.test(l));
  if (hits.length) failures.push(`${file}: hard-coded VAT ${hits.map((l) => l.trim().slice(0, 80)).join(' | ')}`);
}

console.log(JSON.stringify({ failures, seen }));
process.exit(failures.length ? 1 : 0);
