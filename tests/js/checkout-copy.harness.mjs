/* Harness for M2 (K7-4, K7-5, J-R9): the cart module's checkout copy for a seller who is not a VAT payer and for one who is — the
 * order total names VAT only for a payer, the transfer amount is printed in Czech format (never "49.00 CZK"), a domain line says
 * "s DPH" only for a payer, and the pay button promises a server start only when the cart holds a server.
 *   node tests/js/checkout-copy.harness.mjs
 * Prints JSON {failures: [...]}; exit code 0 = all checks passed (run by tests/Feature/Web/CheckoutCopyTest.php). */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const api = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api');
const failures = [];
const check = (ok, what) => { if (!ok) failures.push(what); };

function load(payer, lang) {
  const ctx = { console, JSON, Object, Array, String, Number, Math, RegExp, Date, Intl, isNaN, Infinity, Promise, setTimeout, clearTimeout };
  ctx.window = ctx;
  ctx.document = { documentElement: { lang }, readyState: 'complete', addEventListener() {}, querySelector() { return null; }, querySelectorAll() { return []; }, createElement() { return { style: {}, setAttribute() {}, appendChild() {}, addEventListener() {} }; }, head: { appendChild() {} }, body: { appendChild() {} } };
  ctx.sessionStorage = { getItem() { return null; }, setItem() {} };
  ctx.location = { hash: '#/kosik' };
  ctx.OnhostApi = { get: () => Promise.resolve({}), put: () => Promise.resolve({}), post: () => Promise.resolve({}) };
  ctx.ONHOST = {};
  ctx.ONHOST_DATA = {
    vat: () => (payer ? 0.21 : 0), vatPayer: () => payer, tlds: () => [], addons: () => ({}),
    pricing: () => ({ product_families: { 'web-hosting': 'web', vps: 'cloud', game: 'game', domain: 'domain' } }),
  };
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(path.join(api, 'onhost-cart.api.js'), 'utf8'), ctx, { filename: 'onhost-cart.api.js' });
  return ctx.OnhostCart;
}

const cmp = { mny: (n) => Math.round(n).toLocaleString('cs-CZ') + ' Kč', setState() {} };
const order = { total: 4900, currency: 'CZK', bank_instructions: { account_number: '6478243359/0800', variable_symbol: '2026000123', amount: '49.00', currency: 'CZK' } };

for (const payer of [false, true]) {
  const cart = load(payer, 'cs');
  const rows = cart.doneRows(order, cmp, true);
  const total = rows.find((r) => /^Celkem k platbě/.test(r.k));
  check(total && total.k === (payer ? 'Celkem k platbě (s DPH)' : 'Celkem k platbě'), `payer=${payer}: total label ${total && total.k}`);
  const amount = rows.find((r) => r.k === 'Částka převodu');
  check(amount && amount.v === '49,00 CZK', `payer=${payer}: transfer amount ${amount && amount.v}`);
  const en = load(payer, 'en').doneRows(order, cmp, false);
  const enAmount = en.find((r) => r.k === 'Transfer amount');
  check(enAmount && enAmount.v === '49.00 CZK', `payer=${payer}: English transfer amount ${enAmount && enAmount.v}`);
  check(en.some((r) => r.k === (payer ? 'Total due (incl. VAT)' : 'Total due')), `payer=${payer}: English total label`);

  const domain = { id: 'd1', name: 'example.cz', price: 250, qty: 1, meta: { product_key: 'domain', config: { tld: 'cz', period_years: 1 }, renew: 250 } };
  const row = cart.cartRow(domain, cmp, (a) => a);
  check(payer ? /^s DPH /.test(row.lineVat) : row.lineVat === '', `payer=${payer}: domain lineVat "${row.lineVat}"`);
}

const cart = load(false, 'cs');
const web = { cartItems: [{ id: 'a', name: 'Webhosting Standard', price: 189, meta: { product_key: 'web-hosting', plan_key: 'standard' } }] };
const vps = { cartItems: [{ id: 'b', name: 'VPS Compute 4', price: 690, meta: { product_key: 'vps', plan_key: 'compute-4' } }] };
const game = { cartItems: [{ id: 'c', name: 'Minecraft', price: 290, meta: { product_key: 'game', plan_key: 's' } }] };
check(cart.payLabel(web, true) === 'Zaplatit objednávku', 'web hosting: ' + cart.payLabel(web, true));
check(cart.payLabel(vps, true) === 'Zaplatit a spustit server', 'vps: ' + cart.payLabel(vps, true));
check(cart.payLabel(game, false) === 'Pay and launch the server', 'game (en): ' + cart.payLabel(game, false));
check(cart.payLabel({ cartItems: [] }, false) === 'Pay for the order', 'empty (en): ' + cart.payLabel({ cartItems: [] }, false));

process.stdout.write(JSON.stringify({ failures }));
process.exit(failures.length ? 1 : 0);
