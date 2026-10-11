/* Harness for G3 (owner decision G-R2): the cart module shows redeemed loyalty points as a line of their own and names them in the
 * discount row. The module runs in a sandbox against a fake API that answers PUT /cart and POST /cart/quote with a quote carrying
 * the points line; no browser, no network.
 *   node tests/js/loyalty-cart.harness.mjs
 * Prints JSON {failures: [...], seen: {...}}; exit code 0 = all checks passed (run by tests/Feature/Loyalty/G3RedeemTest.php). */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const api = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api');
const failures = [];
const seen = { calls: [] };

const quote = {
  quote_id: 'qt_1', currency: 'CZK', subtotal: 189000, discount: 30000, tax: 33390, total: 192390, renewal_total: 189000,
  lines: [
    { line_id: 'l1', sku: 'web-hosting-standard', product_key: 'web-hosting', name: 'Webhosting Standard', qty: 1, period: 'year', unit_net: 189000, discount: 0, net: 189000, config: { line_id: 'l1', periods_billed: 1 } },
    { line_id: 'loyalty', sku: 'loyalty-redeem', product_key: 'loyalty', name: 'Sleva za věrnostní body (300 bodů)', qty: 1, period: 'once', unit_net: -30000, discount: 0, net: -30000, config: { line_id: 'loyalty' } },
  ],
  loyalty: { requested: 300, applied: 300, value: 30000, max_points: 378, min_points: 100, cap_pct: 20, reason: null },
};
const fakeApi = {
  put(url, body) { seen.calls.push('PUT ' + url); return Promise.resolve({ data: { token: 't' } }); },
  post(url, body) { seen.calls.push('POST ' + url + (body && body.points != null ? ' ' + body.points : '')); return Promise.resolve(url === '/cart/quote' ? { data: quote } : { points: body.points }); },
  get(url) { seen.calls.push('GET ' + url); return Promise.resolve({ data: { redeem: { available: 500, min_points: 100, cap_pct: 20, point_value: { minor: 100, currency: 'CZK' } } } }); },
};

const ctx = { console, JSON, Object, Array, String, Number, Math, RegExp, Date, isNaN, Infinity, Promise, setTimeout, clearTimeout };
ctx.window = ctx;
ctx.document = { documentElement: { lang: 'cs' }, readyState: 'complete', addEventListener() {}, querySelector() { return null; }, querySelectorAll() { return []; }, createElement() { return { style: {}, setAttribute() {}, appendChild() {}, addEventListener() {} }; }, head: { appendChild() {} }, body: { appendChild() {} } };
ctx.sessionStorage = { getItem() { return null; }, setItem() {} };
ctx.location = { hash: '#/kosik' };
ctx.OnhostApi = fakeApi;
ctx.ONHOST = { user: { email: 'jana@example.cz' } };
ctx.ONHOST_DATA = { vat: () => 0.21, pricing: () => ({}), addons: () => ({}), tlds: () => [] };
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(path.join(api, 'onhost-cart.api.js'), 'utf8'), ctx, { filename: 'onhost-cart.api.js' });

const cart = ctx.OnhostCart;
const cmp = {
  state: { cartOpen: true, commit: 12, promo: '', promoOk: false, cartItems: [{ id: 'a', name: 'Webhosting Standard', price: 189, qty: 1, commit: 12, meta: { product_key: 'web-hosting', plan_key: 'standard', config: {} } }] },
  setState(patch) { Object.assign(this.state, typeof patch === 'function' ? patch(this.state) : patch); },
  mny(v) { return Math.round(v) + ' Kč'; },
};

if (cart.discountLabel(cmp.state, true) !== 'Sleva') failures.push('a cart without a code or points: ' + cart.discountLabel(cmp.state, true));
cart.totals(cmp); // asks the API for a quote after a pause in the clicking
await new Promise((resolve) => setTimeout(resolve, 900));
const totals = cart.totals(cmp);
seen.totals = totals;
seen.rows = cart.summaryRows(cmp, true);
seen.label = cart.discountLabel(cmp.state, true);
seen.labelEn = cart.discountLabel(Object.assign({}, cmp.state, { promo: 'onhost10', promoOk: true }), false);

if (!totals || totals.promoOff !== 300 || totals.total !== 1923.9) failures.push('totals do not come from the quote: ' + JSON.stringify(totals));
const row = (seen.rows || []).filter((r) => /věrnostní body/.test(r.k))[0];
if (!row || row.v !== '− 300 Kč') failures.push('the points are not a row of their own: ' + JSON.stringify(seen.rows));
if (seen.label !== 'Sleva věrnostní body') failures.push('the discount row does not name the points: ' + seen.label);
if (seen.labelEn !== 'Discount ONHOST10') failures.push('a quote of another cart (here: with a code) must not lend its points to the label: ' + seen.labelEn);
if (seen.calls.indexOf('PUT /cart') < 0 || seen.calls.indexOf('POST /cart/quote') < 0) failures.push('the quote was not asked for: ' + seen.calls.join(', '));
if (seen.calls.some((c) => /\/cart\/loyalty/.test(c))) failures.push('points were redeemed without the customer asking: ' + seen.calls.join(', '));

console.log(JSON.stringify({ failures, seen }));
process.exit(failures.length ? 1 : 0);
