/* Harness for TASK-0063 (phase C, C1 + C4): the REAL session bridge (window.OnhostApi) runs in a vm against a recording fetch,
 * with the real modules on top of it. It proves that one user intent travels under one Idempotency-Key (double click, retry
 * after a 5xx or a network error), that a new intent gets a new key, that PUT / PATCH / DELETE carry a key, that a GET can carry
 * headers, that the fixed calls go where the API answers, and that a failed read shows an error instead of an empty list.
 *   node tests/js/frontend-keys.harness.mjs        (exit code 0 = all checks passed; run by tests/Feature/Http/FrontendRouteContractTest.php) */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api');
const read = (f) => fs.readFileSync(path.join(root, f), 'utf8');
const eq = (a, b) => assert.deepStrictEqual(JSON.parse(JSON.stringify(a)), JSON.parse(JSON.stringify(b)));
const tick = () => new Promise((r) => setImmediate(r));
const settle = async () => { for (let i = 0; i < 12; i++) await tick(); };
function deferred() { let resolve; const promise = new Promise((r) => { resolve = r; }); return { promise, resolve }; }

/* a browser window just big enough for the bridge and the modules; `routes` answers fetch: 'METHOD /v1/path' → handler(req) */
function makeWorld({ user, routes = {}, panel = null } = {}) {
  const fetches = [];
  const flashes = [];
  const store = () => { const m = new Map(); return { getItem: (k) => (m.has(k) ? m.get(k) : null), setItem: (k, v) => m.set(k, String(v)), removeItem: (k) => m.delete(k) }; };
  const element = () => ({ style: {}, handlers: {}, innerHTML: '', className: '', setAttribute() {}, appendChild() {}, remove() {}, addEventListener(t, f) { this.handlers[t] = f; }, querySelector: () => null });
  const ctx = {
    console, Promise, JSON, Date, Object, Array, String, Number, Math, RegExp, Error, Intl, URLSearchParams, parseInt, parseFloat, isNaN, encodeURIComponent, decodeURIComponent,
    setTimeout: () => 0, clearTimeout() {}, addEventListener() {}, open() {},
  };
  ctx.window = ctx;
  ctx.ONHOST = { apiBase: '/v1', surface: 'panel', user: user === undefined ? { id: 'u1', name: 'Jana', email: 'jana@example.cz', organization: { id: 'org1' }, staff: false } : user };
  ctx.ONHOST_PANEL = panel;
  ctx.document = {
    cookie: '', readyState: 'complete', documentElement: { lang: 'cs' }, head: { appendChild() {} }, body: { appendChild() {} },
    createElement: element, getElementById: () => null, querySelector: () => null, querySelectorAll: () => [], addEventListener() {}, removeEventListener() {},
  };
  ctx.localStorage = store();
  ctx.sessionStorage = store();
  ctx.history = { replaceState() {} };
  ctx.location = { hash: '', pathname: '/panel', search: '', origin: 'https://onhost.test', href: '', reload() {} };
  ctx.confirm = () => true;
  ctx.alert = () => {};
  ctx.prompt = () => null;
  ctx.fetch = (url, opts) => {
    const req = { method: opts.method, url, path: url.split('?')[0], query: url.split('?')[1] || '', headers: opts.headers || {}, body: opts.body ? JSON.parse(opts.body) : undefined };
    fetches.push(req);
    const h = routes[req.method + ' ' + req.path] || routes[req.method + ' *'] || routes['*'];
    return Promise.resolve(h ? h(req) : { status: 200, body: { data: {} } }).then((res) => {
      if (res instanceof Error) throw res; // a network error: fetch itself rejects
      const status = res.status || 200;
      return { ok: status >= 200 && status < 300, status, headers: { get: (n) => (res.headers && res.headers[n] !== undefined ? String(res.headers[n]) : null) }, text: () => Promise.resolve(res.body === undefined ? '' : JSON.stringify(res.body)) };
    });
  };
  vm.createContext(ctx);
  vm.runInContext(read('onhost-session-bridge.js'), ctx, { filename: 'onhost-session-bridge.js' });
  const cmp = { state: { lang: 'cs', credit: 5000, chatMsgs: [] }, money: (n) => n + ' Kč', flash: (t, b) => flashes.push([t, b]), forceUpdate() {}, setState(p) { Object.assign(this.state, typeof p === 'function' ? p(this.state) : p); } };
  return { ctx, fetches, flashes, cmp, api: ctx.OnhostApi, load: (...files) => files.forEach((f) => vm.runInContext(read(f), ctx, { filename: f })) };
}
const keyOf = (req) => req.headers['Idempotency-Key'];
const sent = (w, method, p) => w.fetches.filter((f) => f.method === method && f.path === '/v1' + p);
const fail500 = () => ({ status: 500, body: { error: 'server_error', message: 'Server Error' } });

const cases = [];
const it = (name, fn) => cases.push([name, fn]);

it('key(scope, input) is one key per intent: the same input again is the same key, another input a new one', () => {
  const w = makeWorld();
  const a = w.api.key('wallet.topup', { amount: 500 });
  assert.equal(w.api.key('wallet.topup', { amount: 500 }), a);
  assert.notEqual(w.api.key('wallet.topup', { amount: 700 }), a);
  assert.notEqual(w.api.key('invoice.pay:i1', 'wallet'), w.api.key('invoice.pay:i1', 'bank'));
  assert.match(a, /^ui-[a-z0-9]+$/);
});

it('a wallet top-up clicked twice before the answer goes out once, under one key; the next top-up after success has a new key', async () => {
  const gate = deferred();
  const w = makeWorld({ routes: { 'POST /v1/payments/init': () => gate.promise } });
  w.load('onhost-panel-billing.api.js');
  w.ctx.OnhostPanelBilling.topUp(w.cmp, 500, 'bank');
  w.ctx.OnhostPanelBilling.topUp(w.cmp, 500, 'bank');
  await settle();
  assert.equal(sent(w, 'POST', '/payments/init').length, 1, 'the double click must not send a second top-up');
  const first = keyOf(sent(w, 'POST', '/payments/init')[0]);
  assert.ok(first);
  gate.resolve({ status: 201, body: { provider: 'bank', instructions: { variable_symbol: '900000001' } } });
  await settle();
  w.ctx.OnhostPanelBilling.topUp(w.cmp, 500, 'bank');
  await settle();
  const all = sent(w, 'POST', '/payments/init');
  assert.equal(all.length, 2);
  assert.notEqual(keyOf(all[1]), first, 'a top-up after the first one succeeded is a new intent');
});

it('a wallet top-up retried after a 5xx or a network error goes out under the same key; a changed amount is a new key', async () => {
  let answer = fail500;
  const w = makeWorld({ routes: { 'POST /v1/payments/init': () => answer() } });
  w.load('onhost-panel-billing.api.js');
  w.ctx.OnhostPanelBilling.topUp(w.cmp, 500, 'bank');
  await settle();
  answer = () => new Error('Failed to fetch');
  w.ctx.OnhostPanelBilling.topUp(w.cmp, 500, 'bank');
  await settle();
  answer = () => ({ status: 201, body: { provider: 'bank', instructions: {} } });
  w.ctx.OnhostPanelBilling.topUp(w.cmp, 500, 'bank');
  await settle();
  const keys = sent(w, 'POST', '/payments/init').map(keyOf);
  assert.equal(keys.length, 3);
  assert.equal(new Set(keys).size, 1, 'the retries are the same top-up: ' + keys.join(', '));
  assert.ok(w.flashes.some((f) => /nepodařilo/.test(f[0])), 'the failure was shown');
  w.ctx.OnhostPanelBilling.topUp(w.cmp, 700, 'bank');
  await settle();
  assert.notEqual(keyOf(sent(w, 'POST', '/payments/init')[3]), keys[0]);
});

it('paying an invoice from credit twice or again after a 5xx is one payment key; paying it another way is another key', async () => {
  let answer = fail500;
  const gate = deferred();
  const w = makeWorld({ routes: { 'POST /v1/invoices/inv1/pay': (req) => (req.body.method === 'bank' ? { status: 200, body: { instructions: {} } } : answer()) } });
  w.load('onhost-panel-billing.api.js');
  const row = { apiId: 'inv1', id: 'FV-2026-0001', total: 1210 };
  w.ctx.OnhostPanelBilling.rowAction(w.cmp, row, true);
  await settle();
  answer = () => gate.promise;
  w.ctx.OnhostPanelBilling.rowAction(w.cmp, row, true); // the retry after the 5xx …
  w.ctx.OnhostPanelBilling.rowAction(w.cmp, row, true); // … clicked twice
  await settle();
  const wallet = sent(w, 'POST', '/invoices/inv1/pay');
  assert.equal(wallet.length, 2, 'the 5xx attempt and one retry; the double click is not a third request');
  assert.equal(keyOf(wallet[0]), keyOf(wallet[1]));
  gate.resolve({ status: 200, body: { state: 'PAID' } });
  await settle();
  w.ctx.OnhostPanelBilling.payByBank(w.cmp, { id: 'inv1', number: 'FV-2026-0001' });
  await settle();
  const bank = sent(w, 'POST', '/invoices/inv1/pay').filter((r) => r.body.method === 'bank');
  assert.equal(bank.length, 1);
  assert.notEqual(keyOf(bank[0]), keyOf(wallet[0]));
});

it('a partner payout pressed twice, then again after a 5xx, is one payout key', async () => {
  let answer = fail500; // the second press comes before this answer: it finds the first request on the wire
  const w = makeWorld({ routes: { 'POST /v1/partner/payouts': () => answer() } });
  w.load('onhost-partner.api.js');
  w.ctx.OnhostPartner.requestPayout(w.cmp, 1500, 'CZ6508000000192000145399');
  w.ctx.OnhostPartner.requestPayout(w.cmp, 1500, 'CZ6508000000192000145399');
  await settle();
  assert.equal(sent(w, 'POST', '/partner/payouts').length, 1);
  answer = () => ({ status: 201, body: { number: 'PO-1' } });
  w.ctx.OnhostPartner.requestPayout(w.cmp, 1500, 'CZ6508000000192000145399');
  await settle();
  const keys = sent(w, 'POST', '/partner/payouts').map(keyOf);
  eq(keys.length, 2);
  assert.equal(keys[0], keys[1], 'the retry after the 5xx is the same payout');
  eq(sent(w, 'POST', '/partner/payouts')[0].body, { amount: 1500, iban: 'CZ6508000000192000145399', method: 'bank_transfer' });
});

it('PUT, PATCH and DELETE always send an Idempotency-Key, the caller\'s when given, and keep it for a retry after a 5xx', async () => {
  let n = 0;
  const w = makeWorld({ routes: { 'DELETE /v1/api-tokens/t1': () => (++n === 1 ? fail500() : { status: 200, body: {} }), '*': () => ({ status: 200, body: {} }) } });
  await w.api.put('/wallet/auto-topup', { enabled: true });
  await w.api.patch('/organizations/org1', { name: 'Nová' });
  await w.api.put('/partner/whitelabel', { domain: 'x.cz' }, 'own-key-1');
  await w.api.put('/cart', { items: [] }, { 'X-Cart-Token': 'ct1' });
  await w.api.del('/api-tokens/t1').catch(() => {});
  await w.api.del('/api-tokens/t1');
  const f = w.fetches;
  assert.ok(keyOf(f[0]) && keyOf(f[1]), 'PUT and PATCH carry a key');
  assert.equal(keyOf(f[2]), 'own-key-1');
  assert.equal(f[3].headers['X-Cart-Token'], 'ct1');
  assert.ok(keyOf(f[3]), 'a header map without a key still gets one');
  assert.ok(keyOf(f[4]));
  assert.equal(keyOf(f[5]), keyOf(f[4]), 'the DELETE retried after the 5xx is the same request');
});

it('a GET can carry headers: the staff game console reads a customer\'s log with X-Organization (C1)', async () => {
  const w = makeWorld({ user: { id: 's1', name: 'Pavla', staff: true, nav: [] } });
  await w.api.get('/services/svc1/logs', { 'X-Organization': 'org-customer' });
  assert.equal(w.fetches[0].headers['X-Organization'], 'org-customer');
  assert.equal(w.fetches[0].method, 'GET');
  assert.equal(keyOf(w.fetches[0]), undefined, 'a read carries no key');
  assert.match(read('onhost-admin.api.js'), /method === 'get' \? a\.get\(path, headers\)/);
});

it('the chat\'s restart chip runs the power action on the action route the API has, with the bus\'s value (C1)', async () => {
  const w = makeWorld({ panel: { services: {} }, routes: { 'POST /v1/assistant/chat': () => ({ status: 200, body: { text: 'Mohu restartovat.', actions: [{ kind: 'restart', service_id: 'svc9', label: 'Restartovat web1' }] } }), 'POST /v1/services/svc9/actions': () => ({ status: 202, body: { operation_id: 'op1' } }) } });
  w.load('onhost-panel-chat.api.js');
  w.ctx.OnhostPanelChat.ask(w.cmp, 'restartuj web1');
  await settle();
  const chip = w.ctx.OnhostPanelChat.chips(w.cmp, null).filter((c) => c[0] === 'Restartovat web1')[0];
  chip[1]();
  await settle();
  const run = sent(w, 'POST', '/services/svc9/actions');
  assert.equal(run.length, 1);
  eq(run[0].body, { action: 'power', params: { power_action: 'reboot' } });
  assert.ok(keyOf(run[0]));
  assert.equal(sent(w, 'POST', '/services/svc9/power').length, 0);
});

it('the staff drawer sends the Idempotency-Key with the assistant\'s action and one stable bank line per confirmation (C1, C4)', async () => {
  const org = { id: 'org1', currency: 'CZK', wallet: {}, orders: [{ id: 'o1', number: 'OH-1', state: 'PENDING_PAYMENT', payment_mode: 'bank', total: { decimal: '1210.00' }, currency: 'CZK', bank_instructions: { variable_symbol: '20260001' } }] };
  let lineAnswer = fail500;
  const w = makeWorld({
    user: { id: 's1', name: 'Pavla', staff: true, nav: [] },
    routes: {
      'GET /v1/staff/customers/org1': () => ({ status: 200, body: { data: org } }),
      'POST /v1/staff/assistant/chat': () => ({ status: 200, body: { text: 'Navrhuji restart.', actions: [{ kind: 'service_action', service_id: 'svc1', action: 'power', params: { power_action: 'reboot' }, label: 'Restart' }] } }),
      'POST /v1/staff/services/svc1/actions': () => ({ status: 202, body: {} }),
      'POST /v1/staff/payments/bank/lines': () => lineAnswer(),
    },
  });
  const made = [];
  const create = w.ctx.document.createElement;
  w.ctx.document.createElement = () => { const el = create(); made.push(el); return el; };
  w.load('onhost-admin-customer.api.js');
  w.ctx.OnhostDialog.prompt = () => Promise.resolve('1210'); // the amount is typed in the shared dialog now (G8 item 6), not window.prompt
  assert.equal(w.ctx.OnhostAdminCustomer.open('org1'), true);
  await settle();
  const drawer = made.filter((el) => el.className === 'ohac')[0];
  const click = (a, v) => drawer.handlers.click({ target: { closest: () => ({ disabled: false, getAttribute: (n) => (n === 'data-a' ? a : v) }) }, preventDefault() {} });
  // the assistant: a question, its proposed action, the operator confirms it
  drawer.handlers.input({ type: 'input', target: { getAttribute: () => 'ai_text', value: 'proč neběží web?' } });
  click('ai-ask');
  await settle();
  click('ai-run', '0');
  await settle();
  const run = sent(w, 'POST', '/staff/services/svc1/actions')[0];
  assert.ok(run, 'the confirmed action was sent');
  assert.match(String(keyOf(run)), /^ui-/, 'the Idempotency-Key is sent (it used to be spread from a string and lost)');
  assert.equal(run.headers['X-Organization'], 'org1');
  assert.equal(run.headers['0'], undefined, 'no characters of a spread key string');
  // a bank payment confirmed, refused with a 5xx, confirmed again: the same line, by id and by key
  click('paid', 'o1');
  await settle();
  lineAnswer = () => ({ status: 201, body: {} });
  click('paid', 'o1');
  await settle();
  const lines = sent(w, 'POST', '/staff/payments/bank/lines');
  assert.equal(lines.length, 2);
  assert.equal(lines[0].body.external_id, lines[1].body.external_id, 'one confirmation is one bank line, not one per click');
  assert.equal(keyOf(lines[0]), keyOf(lines[1]));
  assert.doesNotMatch(lines[0].body.external_id, /-\d{13}$/, 'no Date.now() in the line id');
});

it('a mocked 500 on a list shows an error, not an empty list; a 403 stays "denied" (store, domains, staff console)', async () => {
  const w = makeWorld({ routes: { 'GET /v1/orders': fail500, 'GET /v1/tickets': () => ({ status: 403, body: { error: 'forbidden' } }), 'GET /v1/domains': fail500, '*': () => ({ status: 200, body: { data: [] } }) } });
  w.load('onhost-store.api.js', 'onhost-domains.api.js');
  await settle();
  const S = w.ctx.OnhostStore, D = w.ctx.OnhostDomains;
  assert.equal(S.orders().length, 0);
  assert.equal(S.orders().__error, 'Server Error');
  assert.equal(S.error('orders'), 'Server Error');
  assert.equal(S.denied('orders'), false);
  assert.equal(S.denied('tickets'), true);
  assert.equal(S.error('tickets'), null);
  assert.equal(S.error('invoices'), null, 'a read that worked carries no error');
  assert.equal(D.domains().__error, 'Server Error');
  assert.equal(D.error(), 'Server Error');

  const nav = [{ screen: { type: 'view', target: 'customers' }, api: [{ method: 'GET', path: '/v1/staff/customers' }] }];
  const s = makeWorld({ user: { id: 's1', name: 'Pavla', staff: true, nav }, routes: { 'GET /v1/staff/customers': fail500, '*': () => ({ status: 200, body: { data: [] } }) } });
  s.load('onhost-store.api.js', 'onhost-admin.api.js');
  s.ctx.OnhostAdmin.customers(s.cmp);
  await settle();
  const rows = s.ctx.OnhostAdmin.customers(s.cmp);
  assert.equal(rows.length, 1);
  assert.equal(rows[0][0], 'Nelze načíst');
  assert.equal(rows[0][2], 'Server Error');
  assert.equal(s.ctx.OnhostAdmin.failed('customers'), 'Server Error');
});

it('a list that keeps its rows when a later read fails, and says so', async () => {
  let fail = false;
  const w = makeWorld({ routes: { 'GET /v1/orders': () => (fail ? fail500() : { status: 200, headers: { 'X-Total-Count': 1 }, body: { data: [{ id: 'o1', number: 'OH-1', state: 'PAID', total: { decimal: '10' }, items: [] }] } }), '*': () => ({ status: 200, body: { data: [] } }) } });
  w.load('onhost-store.api.js');
  await settle();
  assert.equal(w.ctx.OnhostStore.orders().length, 1);
  fail = true;
  await w.ctx.OnhostStore.refresh();
  await settle();
  const after = w.ctx.OnhostStore.orders();
  assert.equal(after.length, 1, 'the failed refresh did not wipe the list');
  assert.equal(after.__error, 'Server Error');
});

it('list loaders follow X-Total-Count page by page instead of a fixed limit', async () => {
  const rows = Array.from({ length: 450 }, (_, i) => ({ id: 'o' + i, number: 'OH-' + i, state: 'PAID', total: { decimal: '1' }, items: [] }));
  const page = (req) => {
    const q = new URLSearchParams(req.query);
    const limit = Math.min(200, Number(q.get('limit') || 40)), offset = Number(q.get('offset') || 0);
    return { status: 200, headers: { 'X-Total-Count': rows.length }, body: { data: rows.slice(offset, offset + limit) } };
  };
  const w = makeWorld({ routes: { 'GET /v1/orders': page, 'GET /v1/domains': (req) => ({ status: 200, headers: { 'X-Total-Count': 2 }, body: { data: [{ fqdn: 'a.cz', state: 'ACTIVE' }, { fqdn: 'b.cz', state: 'ACTIVE' }].slice(Number(new URLSearchParams(req.query).get('offset') || 0)) } }), '*': () => ({ status: 200, body: { data: [] } }) } });
  w.load('onhost-store.api.js', 'onhost-domains.api.js');
  await settle();
  assert.equal(w.ctx.OnhostStore.orders().length, 450);
  eq(sent(w, 'GET', '/orders').map((r) => r.query), ['limit=200&offset=0', 'limit=200&offset=200', 'limit=200&offset=400']);
  assert.equal(w.ctx.OnhostDomains.domains().length, 2);
  assert.doesNotMatch(sent(w, 'GET', '/domains').map((r) => r.query).join(' '), /limit=500/);
  // an endpoint that ignores offset is read once, not 50 times over
  const same = makeWorld({ routes: { 'GET /v1/orders': () => ({ status: 200, headers: { 'X-Total-Count': 999 }, body: { data: rows.slice(0, 200) } }), '*': () => ({ status: 200, body: { data: [] } }) } });
  same.load('onhost-store.api.js');
  await settle();
  assert.equal(same.ctx.OnhostStore.orders().length, 200);
  assert.equal(sent(same, 'GET', '/orders').length, 2);
  // an overview that is not a list comes back as it was, not as an empty list
  const ov = makeWorld({ routes: { 'GET /v1/staff/provisioning/board': () => ({ status: 200, body: { data: { nodes: [{ name: 'n1' }] } } }) } });
  eq((await ov.api.all('/staff/provisioning/board')).data, { nodes: [{ name: 'n1' }] });
});

let failed = 0;
for (const [name, fn] of cases) {
  try { await fn(); console.log('ok   ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n     ' + (e && e.stack ? e.stack.split('\n').slice(0, 4).join('\n     ') : e)); }
}
console.log(`${cases.length - failed}/${cases.length} passed`);
process.exit(failed ? 1 : 0);
