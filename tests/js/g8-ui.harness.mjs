/* Harness for TASK-0118 (phase G, package G8: UI follow-ups). The real browser modules run in a vm against a recording OnhostApi and a
 * small fake DOM:
 *   · the shared input dialog (OnhostDialog, session bridge) that replaced every window.prompt;
 *   · service accounts in the customer portal (list / create / issue a key / revoke a key / remove) and the owner-only refusal;
 *   · the "resend the verification e-mail" control of an unverified address;
 *   · the staff single sign-on button of the customer drawer;
 *   · no module is left that calls window.prompt.
 *   node tests/js/g8-ui.harness.mjs        (exit code 0 = all checks passed; run by tests/Feature/Http/G8UiFollowUpsTest.php) */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api');
const read = (f) => fs.readFileSync(path.join(root, f), 'utf8');
const tick = () => new Promise((r) => setImmediate(r));
const settle = async () => { for (let i = 0; i < 12; i++) await tick(); };
const eq = (a, b) => assert.deepStrictEqual(JSON.parse(JSON.stringify(a)), JSON.parse(JSON.stringify(b)));

class El {
  constructor(tag) { this.tag = tag; this.children = []; this.attrs = {}; this.style = {}; this.listeners = {}; this.textContent = ''; this.parent = null; this.value = ''; this.innerHTML = ''; this.className = ''; }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  appendChild(c) { c.parent = this; this.children.push(c); return c; }
  addEventListener(t, fn) { (this.listeners[t] = this.listeners[t] || []).push(fn); }
  remove() { if (this.parent) this.parent.children = this.parent.children.filter((c) => c !== this); this.parent = null; }
  focus() {}
  fire(t, ev = {}) { (this.listeners[t] || []).forEach((fn) => fn({ target: this, preventDefault() {}, stopPropagation() {}, ...ev })); }
  all() { return [this].concat(...this.children.map((c) => c.all())); }
  text() { return this.all().map((e) => e.textContent).filter(Boolean).join('\n'); }
}

function makeWindow(routes = {}) {
  const calls = [], flashes = [], opened = [], prompts = [];
  const body = new El('body'), docListeners = {};
  const ctx = { console, setTimeout: (fn) => { try { fn(); } catch (e) { /* focus only */ } return 0; }, clearTimeout() {}, Promise, JSON, Date, Object, Array, String, Number, Math, RegExp, parseInt, encodeURIComponent, isNaN, Error, Uint32Array };
  ctx.window = ctx;
  ctx.ONHOST = { demo: false, user: null, surface: 'panel' };
  ctx.location = { hash: '', pathname: '/', search: '', href: '/', reload() {} };
  ctx.history = { replaceState() {} };
  ctx.localStorage = { getItem() { return null; }, setItem() {}, removeItem() {} };
  ctx.crypto = { getRandomValues: (b) => b };
  ctx.document = {
    documentElement: { lang: 'cs' }, readyState: 'complete', cookie: '', body, head: new El('head'),
    addEventListener: (t, fn) => { (docListeners[t] = docListeners[t] || []).push(fn); },
    removeEventListener: (t, fn) => { docListeners[t] = (docListeners[t] || []).filter((f) => f !== fn); },
    querySelector() { return null; }, querySelectorAll() { return []; }, getElementById() { return null; },
    createElement: (t) => new El(t),
  };
  ctx.addEventListener = () => {};
  ctx.confirm = () => true;
  ctx.prompt = (m) => { prompts.push(m); return null; };
  ctx.open = (u) => { opened.push(u); return {}; };
  vm.createContext(ctx);
  vm.runInContext(read('onhost-session-bridge.js'), ctx, { filename: 'onhost-session-bridge.js' });
  /* the recording API replaces the bridge's real one */
  let n = 0;
  const answer = (method, p, bodyIn, key) => {
    calls.push({ method, path: p, body: bodyIn, key });
    const handler = routes[method + ' ' + p.split('?')[0]];
    if (handler === undefined) return Promise.reject(Object.assign(new Error('no route ' + method + ' ' + p), { status: 404 }));
    const out = typeof handler === 'function' ? handler(bodyIn, p) : handler;
    return out instanceof Error ? Promise.reject(out) : Promise.resolve(out);
  };
  ctx.OnhostApi = {
    base: '/v1', get: (p) => answer('GET', p), all: (p) => answer('GET', p), post: (p, b, k) => answer('POST', p, b, k), put: (p, b, k) => answer('PUT', p, b, k),
    patch: (p, b, k) => answer('PATCH', p, b, k), del: (p) => answer('DELETE', p), key: () => 'k' + ++n,
  };
  return { ctx, calls, flashes, opened, prompts, body, doc: { fire: (t, ev) => (docListeners[t] || []).forEach((fn) => fn(ev)), count: (t) => (docListeners[t] || []).length } };
}

const openDialog = (w) => w.body.children.filter((c) => c.attrs['data-onhost-dialog'])[0] || null;
/* fills the fields of the open dialog in order and presses the confirm button (a form submit) */
const answerDialog = (w, values) => {
  const dlg = openDialog(w);
  assert.ok(dlg, 'a dialog is open');
  const fields = dlg.all().filter((e) => ['input', 'textarea', 'select'].includes(e.tag));
  values.forEach((v, i) => { if (v !== undefined) fields[i].value = v; });
  dlg.all().filter((e) => e.tag === 'form')[0].fire('submit');
};
const press = (w, act) => openDialog(w).all().filter((e) => e.attrs['data-act'] === act)[0].fire('click');

const cmpOf = (w, lang = 'cs') => ({ state: { lang }, flash: (t, b) => w.flashes.push([t, b]), setState(p) { Object.assign(this.state, p); } });
const H = { stat: (a, b, c) => [a, b, c], pill: () => '', bar: () => '', dot: () => '', rowStyle: '', match: () => true };
const _ = (cs) => cs;

const results = [];
const check = async (name, fn) => {
  try { await fn(); results.push([name, true]); } catch (e) { results.push([name, false, e && e.stack ? e.stack.split('\n').slice(0, 4).join(' | ') : String(e)]); }
};

/* ── the dialog ─────────────────────────────────────────────────────────────────────────────────────────────────── */
await check('the dialog is a labelled modal and hands back the typed values', async () => {
  const w = makeWindow();
  const listeners = w.doc.count('keydown');
  const p = w.ctx.OnhostDialog.form({ title: 'Nový účet', lead: 'Popis', fields: [{ key: 'a', label: 'Název', value: 'x', required: true }, { key: 'b', label: 'Role', type: 'select', options: [['v', 'viewer'], ['o', 'owner']], value: 'v' }] });
  const dlg = openDialog(w);
  assert.equal(dlg.attrs.role, 'dialog');
  assert.equal(dlg.attrs['aria-modal'], 'true');
  assert.match(dlg.text(), /Nový účet/);
  assert.match(dlg.text(), /Název \*/);
  answerDialog(w, ['firma', 'o']);
  eq(await p, { a: 'firma', b: 'o' });
  assert.equal(openDialog(w), null, 'the dialog closes');
  assert.equal(w.doc.count('keydown'), listeners, 'the key listener is gone');
});
await check('cancel, Escape and the backdrop all answer null', async () => {
  const w = makeWindow();
  let p = w.ctx.OnhostDialog.form({ title: 't', fields: [{ key: 'a', label: 'A' }] });
  press(w, 'cancel');
  assert.equal(await p, null);
  p = w.ctx.OnhostDialog.form({ title: 't', fields: [{ key: 'a', label: 'A' }] });
  w.doc.fire('keydown', { key: 'Escape', stopPropagation() {} });
  assert.equal(await p, null);
  p = w.ctx.OnhostDialog.form({ title: 't', fields: [{ key: 'a', label: 'A' }] });
  const dlg = openDialog(w);
  dlg.fire('click', { target: dlg });
  assert.equal(await p, null);
});
await check('a required field blocks the submit until it is filled', async () => {
  const w = makeWindow();
  let done = false;
  const p = w.ctx.OnhostDialog.form({ title: 't', fields: [{ key: 'a', label: 'Název', required: true }] }).then((v) => { done = true; return v; });
  answerDialog(w, ['  ']);
  await settle();
  assert.equal(done, false);
  assert.match(openDialog(w).text(), /Vyplňte: Název/);
  answerDialog(w, ['ok']);
  eq(await p, { a: 'ok' });
});
await check('prompt() and confirm() keep the shape of the browser calls, asynchronously', async () => {
  const w = makeWindow();
  let p = w.ctx.OnhostDialog.prompt('Jméno:', 'Jana');
  assert.equal(openDialog(w).all().filter((e) => e.tag === 'input')[0].value, 'Jana');
  answerDialog(w, ['Petra']);
  assert.equal(await p, 'Petra');
  p = w.ctx.OnhostDialog.prompt('Jméno:', '');
  press(w, 'cancel');
  assert.equal(await p, null);
  p = w.ctx.OnhostDialog.confirm('Odstranit?', 'Nevratné.', { danger: true });
  answerDialog(w, []);
  assert.equal(await p, true);
  p = w.ctx.OnhostDialog.confirm('Odstranit?', 'Nevratné.');
  press(w, 'cancel');
  assert.equal(await p, false);
  assert.deepEqual(w.prompts, [], 'the browser prompt was never opened');
});
await check('a long question is the lead and the title stays short', async () => {
  const w = makeWindow();
  const p = w.ctx.OnhostDialog.prompt('Zadejte IP adresy oddělené čárkou (prázdné = odkudkoli, "off" = vypnout):', '');
  const text = openDialog(w).text();
  assert.match(text, /Zadejte hodnotu/);
  assert.match(text, /prázdné = odkudkoli/);
  press(w, 'cancel');
  await p;
});

/* ── service accounts (item 1) ─────────────────────────────────────────────────────────────────────────────────── */
const SA_LIST = { data: [{ id: 'sa_1', name: 'ci-deploy', state: 'active', role: 'developer', created_at: '2026-10-01T10:00:00Z', tokens: [{ id: 'tok_1', name: 'ci', scopes: ['services:read'], revoked_at: null, expires_at: '2027-01-01T00:00:00Z' }, { id: 'tok_0', name: 'old', scopes: [], revoked_at: '2026-10-02T00:00:00Z' }] }], roles: ['viewer', 'developer', 'billing_admin'], scopes: [] };
function accountWindow(extra = {}) {
  const w = makeWindow({ 'GET /tokens': { data: [] }, 'GET /webhooks': { data: [] }, 'GET /integrations/discord': { data: { configured: false } }, 'GET /service-accounts': SA_LIST, ...extra });
  w.ctx.ONHOST.user = { id: 'u1', name: 'Jana', email: 'jana@example.cz', member_role: 'owner', organization: { id: 'org_1' }, email_verified: true };
  vm.runInContext(read('onhost-panel-account.api.js'), w.ctx, { filename: 'onhost-panel-account.api.js' });
  return w;
}
const sideRows = (w, cmp) => w.ctx.OnhostPanelAccount.apiView(cmp, _, H).side.rows;

await check('the owner sees the service accounts with their role and active keys, and a way to add one', async () => {
  const w = accountWindow();
  const cmp = cmpOf(w);
  sideRows(w, cmp); // first render starts the read
  await settle();
  const rows = sideRows(w, cmp);
  const account = rows.find((r) => /ci-deploy/.test(r.title));
  assert.ok(account, 'the account is listed');
  assert.match(account.meta, /vývojář/);
  assert.match(account.meta, /1 aktivní klíč/, 'one active key, the revoked one is not counted');
  assert.ok(rows.find((r) => r.title === 'Nový servisní účet'));
  assert.ok(w.calls.some((c) => c.method === 'GET' && c.path === '/service-accounts'));
});
await check('creating a service account posts name, role and the chosen scopes under an idempotency key and shows the key once', async () => {
  const w = accountWindow({ 'POST /service-accounts': { data: { id: 'sa_2', name: 'bot', tokens: [] }, token: 'ohk_secret', id: 'tok_9', name: 'bot', scopes: ['services:read'], expires_at: '2027-02-01T00:00:00Z' } });
  const cmp = cmpOf(w);
  sideRows(w, cmp); await settle();
  sideRows(w, cmp).find((r) => r.title === 'Nový servisní účet').on();
  answerDialog(w, ['bot', 'billing_admin', 'jen čtení', '30']); // the fake select takes the typed value as is
  await settle();
  const post = w.calls.find((c) => c.method === 'POST' && c.path === '/service-accounts');
  assert.ok(post, 'POST /service-accounts');
  eq(post.body, { name: 'bot', role: 'billing_admin', scopes: ['services:read', 'invoices:read', 'domains:read', 'wallet:read'], expires_in_days: 30 });
  assert.ok(post.key, 'an Idempotency-Key travels with the write');
  assert.equal(w.flashes.length, 1);
  const view = w.ctx.OnhostPanelAccount.apiView(cmp, _, H);
  assert.match(JSON.stringify(view.wide), /ohk_secret/, 'the plain key is shown once, in the copy box');
});
await check('a key can be issued, revoked and the whole account removed (the removal asks first)', async () => {
  const w = accountWindow({
    'POST /service-accounts/sa_1/tokens': { token: 'ohk_new', name: 'ci-2', expires_at: '2027-01-01T00:00:00Z' },
    'DELETE /service-accounts/sa_1/tokens/tok_1': { data: { revoked: true } },
    'DELETE /service-accounts/sa_1': { data: { deleted: true } },
  });
  const cmp = cmpOf(w);
  const manage = async () => { sideRows(w, cmp); await settle(); sideRows(w, cmp).find((r) => /ci-deploy/.test(r.title)).on(); };
  await manage();
  answerDialog(w, ['issue', 'provoz služeb', 'ci-2', undefined]);
  await settle();
  const issued = w.calls.find((c) => c.method === 'POST' && c.path === '/service-accounts/sa_1/tokens');
  eq(issued.body.name, 'ci-2');
  eq(issued.body.scopes, ['services:read', 'services:power', 'domains:read', 'dns:write', 'tickets:write']);
  await manage();
  answerDialog(w, ['revoke', undefined, undefined, 'tok_1']);
  await settle();
  assert.ok(w.calls.some((c) => c.method === 'DELETE' && c.path === '/service-accounts/sa_1/tokens/tok_1'));
  await manage();
  answerDialog(w, ['delete']);
  await settle();
  assert.equal(w.calls.some((c) => c.method === 'DELETE' && c.path === '/service-accounts/sa_1'), false, 'nothing is removed before the confirmation');
  assert.match(openDialog(w).text(), /Odstranit servisní účet ci-deploy/);
  answerDialog(w, []);
  await settle();
  assert.ok(w.calls.some((c) => c.method === 'DELETE' && c.path === '/service-accounts/sa_1'));
});
await check('anybody but the owner is told so instead of being shown a list', async () => {
  const w = accountWindow({ 'GET /service-accounts': Object.assign(new Error('owner only'), { status: 403 }) });
  const cmp = cmpOf(w);
  sideRows(w, cmp); await settle();
  const row = sideRows(w, cmp).find((r) => r.title === 'Servisní účty');
  assert.match(row.meta, /jen vlastník/);
  assert.equal(sideRows(w, cmp).some((r) => r.title === 'Nový servisní účet'), false);
});

/* ── verification e-mail (item 2) ───────────────────────────────────────────────────────────────────────────────── */
await check('an unverified address offers to send the verification e-mail again', async () => {
  const w = accountWindow({ 'POST /me/email/verification': { data: { sent: true } }, 'GET /organizations/org_1': { data: { id: 'org_1', name: 'Firma', members: [], billing: {} } } });
  w.ctx.ONHOST.user.email_verified = false;
  const cmp = cmpOf(w);
  const row = () => w.ctx.OnhostPanelAccount.accountView(cmp, _, H).rows.find((r) => r.name === 'Přihlašovací e-mail');
  assert.equal(row().action, 'Poslat ověření znovu');
  assert.match(row().sub, /neověřený/);
  row().onAction();
  await settle();
  const sent = w.calls.find((c) => c.method === 'POST' && c.path === '/me/email/verification');
  assert.ok(sent && sent.key, 'POST /v1/me/email/verification with a key');
  assert.match(w.flashes[0][0], /Ověřovací e-mail odeslán/);
  w.ctx.ONHOST.user.email_verified = true;
  assert.equal(row().action, 'Změnit přes podporu', 'a verified address keeps the old row');
});
await check('a refused resend (too many) is said, not swallowed', async () => {
  const w = accountWindow({ 'POST /me/email/verification': Object.assign(new Error('Počkejte minutu.'), { status: 429 }), 'GET /organizations/org_1': { data: { id: 'org_1', name: 'Firma', members: [], billing: {} } } });
  w.ctx.ONHOST.user.email_verified = false;
  const cmp = cmpOf(w);
  w.ctx.OnhostPanelAccount.accountView(cmp, _, H).rows.find((r) => r.name === 'Přihlašovací e-mail').onAction();
  await settle();
  eq(w.flashes[0], ['Nepodařilo se', 'Počkejte minutu.']);
});

/* ── staff single sign-on (item 3) ─────────────────────────────────────────────────────────────────────────────── */
await check('the customer drawer opens the customer panel through the staff sign-on, with a ticket and a reason', async () => {
  const org = { id: 'org_1', name: 'Firma', currency: 'CZK', services: [{ id: 'svc_1', label: 'firma.cz', state: 'ACTIVE', product_key: 'web-hosting' }, { id: 'svc_2', label: 'stará', state: 'CANCELLED' }], orders: [], invoices: [], wallet: {}, can: {} };
  const w = makeWindow({ 'GET /staff/customers/org_1': { data: org }, 'POST /staff/services/svc_1/panel-login': { data: { url: 'https://panel.example/login?t=1', expires_in_seconds: 60, consented: true } } });
  vm.runInContext(read('onhost-admin-customer.api.js'), w.ctx, { filename: 'onhost-admin-customer.api.js' });
  w.ctx.OnhostAdminCustomer.open('org_1');
  await settle();
  const drawer = w.body.children[0];
  assert.equal((drawer.innerHTML.match(/data-a="sso"/g) || []).length, 1, 'only the running service gets the button');
  const btn = { disabled: false, getAttribute: (k) => ({ 'data-a': 'sso', 'data-v': 'svc_1' })[k] };
  drawer.fire('click', { target: { closest: () => btn } });
  assert.match(openDialog(w).text(), /otevřenému tiketu/);
  answerDialog(w, ['T-100', 'zákazník žádá o pomoc s webem']);
  await settle();
  const login = w.calls.find((c) => c.method === 'POST' && c.path === '/staff/services/svc_1/panel-login');
  eq(login.body, { ticket_id: 'T-100', reason: 'zákazník žádá o pomoc s webem' });
  eq(w.opened, ['https://panel.example/login?t=1']);
});
await check('a short reason never reaches the server', async () => {
  const org = { id: 'org_1', name: 'Firma', currency: 'CZK', services: [{ id: 'svc_1', label: 'firma.cz', state: 'ACTIVE' }], orders: [], invoices: [], wallet: {}, can: {} };
  const w = makeWindow({ 'GET /staff/customers/org_1': { data: org } });
  vm.runInContext(read('onhost-admin-customer.api.js'), w.ctx, { filename: 'onhost-admin-customer.api.js' });
  w.ctx.OnhostAdminCustomer.open('org_1');
  await settle();
  const drawer = w.body.children[0];
  drawer.fire('click', { target: { closest: () => ({ disabled: false, getAttribute: (k) => ({ 'data-a': 'sso', 'data-v': 'svc_1' })[k] }) } });
  answerDialog(w, ['T-100', 'kratce']);
  await settle();
  assert.equal(w.calls.some((c) => c.method === 'POST'), false);
  assert.match(drawer.innerHTML, /nejméně 10 znaků/);
});

/* ── Czech counts (item 7) ───────────────────────────────────────────────────────────────────────────────────── */
await check('a count takes the Czech form of its noun: 1 den, 2 dny, 5 dní, 1,5 dne; English one/other', async () => {
  const w = makeWindow();
  const cn = w.ctx.OnhostI18n.cn;
  const days = [['den', 'dny', 'dní'], ['day', 'days']];
  for (const [n, want] of [[0, '0 dní'], [1, '1 den'], [2, '2 dny'], [4, '4 dny'], [5, '5 dní'], [11, '11 dní'], [22, '22 dní'], [100, '100 dní']]) assert.equal(cn(n, ...days, 'cs'), want);
  for (const [n, want] of [[1, '1 day'], [0, '0 days'], [3, '3 days']]) assert.equal(cn(n, ...days, 'en'), want);
  w.ctx.document.documentElement.lang = 'en';
  assert.equal(cn(2, ...days), '2 days', 'without a language the page language decides');
});
await check('a module renders its counts with the right noun (the dunning table, in both languages)', async () => {
  const w = makeWindow();
  w.ctx.ONHOST_PANEL = { billing: { terms: { due_days: 1 }, dunning: { overdue_notice_days: [3], suspend_after_days: 2, terminate_after_days: 21, retention_after_termination_days: 1 } } };
  vm.runInContext(read('onhost-panel-billing.api.js'), w.ctx, { filename: 'onhost-panel-billing.api.js' });
  const cells = (lang) => JSON.stringify(w.ctx.OnhostPanelBilling.policyLedger(cmpOf(w, lang)).rows);
  const cs = cells('cs'), en = cells('en');
  for (const want of ['1 den od vystavení', '2 dny po splatnosti', '21 dní po splatnosti', '1 den po ukončení']) assert.ok(cs.includes(want), want);
  for (const want of ['1 day from issue', '2 days after due', '21 days after due', '1 day after termination']) assert.ok(en.includes(want), want);
  assert.ok(!/(^|[^0-9])1 dní/.test(cs), 'no "1 dní"');
});

/* ── no module still calls window.prompt (item 6) ──────────────────────────────────────────────────────────────── */
await check('no API module calls window.prompt any more', async () => {
  const offenders = fs.readdirSync(root).filter((f) => f.endsWith('.js') && f !== 'onhost-session-bridge.js')
    .filter((f) => /\bwindow\.prompt\(/.test(read(f).split('\n').filter((l) => !/^\s*(\/\/|\*|\/\*)/.test(l) && !/\/\*.*window\.prompt.*\*\//.test(l)).join('\n').replace(/\/\*[\s\S]*?\*\//g, '')));
  assert.deepEqual(offenders, []);
});

const failed = results.filter((r) => !r[1]);
for (const r of results) console.log((r[1] ? 'ok   ' : 'FAIL ') + r[0] + (r[1] ? '' : '\n     ' + r[2]));
console.log(`${results.length - failed.length}/${results.length} passed`);
process.exit(failed.length ? 1 : 0);
