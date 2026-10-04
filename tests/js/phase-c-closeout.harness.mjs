/* Harness for the phase C close-out (TASK-0072): the subscription cancel-at-period-end and auto-renew controls on the costs page
 * and in the billing area of the service detail, and the stop/delete-only Node projects on a closed shared aaPanel node
 * (feature node_projects_exit). It runs the real module files in a vm with a recording OnhostApi and a small fake DOM.
 *   node tests/js/phase-c-closeout.harness.mjs        (exit code 0 = all checks passed; run by tests/Feature/Http/PhaseCCloseoutScreensTest.php) */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api');
const read = (f) => fs.readFileSync(path.join(root, f), 'utf8');
/* objects made inside the vm have another Object prototype: compare them as data */
const eq = (a, b) => assert.deepStrictEqual(JSON.parse(JSON.stringify(a)), JSON.parse(JSON.stringify(b)));
const tick = () => new Promise((r) => setImmediate(r));
const settle = async () => { for (let i = 0; i < 10; i++) await tick(); };

/* ── a fake DOM that is just enough for the page's own dialog ─────────────────────────────────────────────── */
class El {
  constructor(tag) { this.tag = tag; this.children = []; this.attrs = {}; this.style = {}; this.listeners = {}; this.textContent = ''; this.parent = null; }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  appendChild(c) { c.parent = this; this.children.push(c); return c; }
  addEventListener(t, fn) { (this.listeners[t] = this.listeners[t] || []).push(fn); }
  remove() { if (this.parent) this.parent.children = this.parent.children.filter((c) => c !== this); this.parent = null; }
  focus() {}
  fire(t, ev = {}) { (this.listeners[t] || []).forEach((fn) => fn({ target: this, ...ev })); }
  all() { return [this].concat(...this.children.map((c) => c.all())); }
  text() { return this.all().map((e) => e.textContent).filter(Boolean).join('\n'); }
  byAct(a) { return this.all().find((e) => e.attrs['data-act'] === a); }
}

function makeWindow(routes) {
  const calls = [];
  const flashes = [];
  const body = new El('body');
  const docListeners = {};
  const ctx = { console, setTimeout: () => 0, clearTimeout() {}, Promise, JSON, Date, Object, Array, String, Number, parseInt, encodeURIComponent, RegExp, Math, Uint32Array, Error, isNaN };
  ctx.window = ctx;
  ctx.location = { reload() {} };
  ctx.document = {
    currentScript: { src: '/surfaces/api/onhost-panel-workbench.api.js?v=1' }, documentElement: { lang: 'cs' }, body, head: new El('head'),
    createElement: (t) => new El(t),
    addEventListener: (t, fn) => { (docListeners[t] = docListeners[t] || []).push(fn); },
    removeEventListener: (t, fn) => { docListeners[t] = (docListeners[t] || []).filter((f) => f !== fn); },
  };
  ctx.crypto = { getRandomValues: (b) => b };
  const answer = (method, p, bodyIn, key) => {
    calls.push({ method, path: p, body: bodyIn, key });
    const handler = routes[method + ' ' + p.split('?')[0]];
    if (!handler) return Promise.reject(Object.assign(new Error('no route ' + method + ' ' + p), { status: 404 }));
    const out = typeof handler === 'function' ? handler(bodyIn, p) : handler;
    return out instanceof Error ? Promise.reject(out) : Promise.resolve(out);
  };
  let n = 0;
  ctx.OnhostApi = {
    base: '/v1',
    get: (p) => answer('GET', p),
    all: (p) => answer('GET', p),
    post: (p, b, k) => answer('POST', p, b, k),
    put: (p, b, k) => answer('PUT', p, b, k),
    patch: (p, b, k) => answer('PATCH', p, b, k),
    del: (p) => answer('DELETE', p),
    key: (scope, input) => (scope ? 'k:' + scope + ':' + JSON.stringify(input === undefined ? null : input).length : 'k' + ++n),
  };
  ctx.ONHOST = { user: { name: 'Jana Nováková', email: 'jana@example.cz', member_role: 'owner', organization: { id: 'org_1' } } };
  ctx.ONHOST_PANEL = {};
  ctx.confirms = [];
  ctx.confirm = (t) => { ctx.confirms.push(t); return ctx.confirmAnswer !== false; };
  ctx.prompt = () => null;
  ctx.open = () => null;
  return { ctx, calls, flashes, body };
}

function load(win, ...files) {
  vm.createContext(win.ctx);
  for (const f of files) vm.runInContext(read(f), win.ctx, { filename: f });
}

/* a stand-in for the prototype component: the helpers WB_H gives panels and the state the modules read */
function makeCmp(win, lang = 'cs') {
  const cmp = {
    state: { lang, wbF: {}, wbTick: 0 },
    flash: (t, b) => win.flashes.push([t, b]),
    setState(p) { Object.assign(this.state, p); },
  };
  cmp.WB_H = () => {
    const s = cmp.state;
    const cell = (t, w, mono) => ({ t, w, mono });
    const act = (label, on, primary) => ({ label, on, primary });
    const F = (k, ph, w) => ({ k, ph, v: s.wbF[k], w });
    return { s, cell, act, F, TG: () => ({}), PL: (n, a, b, c) => n + ' ' + (n === 1 ? a : n < 5 ? b : c), wb: {} };
  };
  return cmp;
}

const web = (over = {}) => ({ id: 'svc_w1', type: 'web', name: 'firma.cz', apiState: 'ACTIVE', state: 'aktivní', meta: 'firma.cz · Praha', ...over });
const vps = (over = {}) => ({ id: 'svc_v1', type: 'vps', name: 'vps-1', apiState: 'ACTIVE', state: 'aktivní', ...over });
const mail = (over = {}) => ({ id: 'svc_m1', type: 'mail', name: 'firma.cz', apiState: 'ACTIVE', state: 'aktivní', ...over });
const SV = { web: [{ id: 'dbs' }, { id: 'ssh' }, { id: 'redirect' }, { id: 'noc' }], vps: [{ id: 'snap' }, { id: 'noc' }], mail: [{ id: 'boxes' }, { id: 'auth' }, { id: 'noc' }] };
const FP = 'f'.repeat(64);
const preview = (what, over = {}) => ({ data: { action: 'x', service: { id: 'svc_w1', name: 'firma.cz' }, what, depends: ['Na databázi je napojen web firma.cz.'], recovery: { kind: 'safety_copy', note: 'Než cokoli přepíšeme, uděláme zálohu a uchováme ji 30 dní.' }, fingerprint: FP, ...over } });
const feats = (features, actions = []) => ({ data: { features, actions } });

const routes = (extra = {}) => ({
  'GET /services/svc_w1/features': feats({ databases: { enabled: true, limit: 5 }, shell: { enabled: true }, redirects: { enabled: false, reason: 'permission' }, backups: { enabled: false, reason: 'state' }, snapshots: { enabled: false } }),
  'GET /services/svc_v1/features': feats({ snapshots: { enabled: true }, firewall: { enabled: true } }),
  'GET /services/svc_m1/features': feats({ mailboxes: { enabled: true }, dkim: { enabled: true }, sending: { enabled: true } }),
  'GET /services/svc_w1/resources/databases': { data: [{ name: 'shop', user: 'shop_u', charset: 'utf8mb4', remote_id: '12' }] },
  'GET /services/svc_w1/resources/shell_users': { data: [{ user: 'web1', remote_id: '5', chroot: true, has_key: true, key: null }] },
  'GET /services/svc_v1/resources/snapshots': { data: [{ name: 'pred-upgradem', description: 'ruční', created_at: '2026-10-01T10:00:00Z' }] },
  'GET /services/svc_m1/resources/mailboxes': { data: [{ address: 'jana@firma.cz', name: 'Jana', quota_mb: 1024, remote_id: '77' }] },
  'GET /services/svc_m1/resources/mail_access': { data: {} },
  'GET /services/svc_m1/resources/dkim': { data: { selector: 'onhost', record: 'v=DKIM1; k=rsa; p=AAA' } },
  'GET /services/svc_m1/access': { data: [] },
  ...extra,
});

async function setup(opts = {}) {
  const win = makeWindow(routes(opts.routes));
  win.ctx.ONHOST_PANEL = {};
  load(win, 'onhost-panel-workbench.api.js', 'onhost-panel-tools.api.js');
  const cmp = makeCmp(win, opts.lang);
  const sel = opts.sel || web();
  const W = win.ctx.window.OnhostPanelWorkbench;
  const _ = (a, b) => (cmp.state.lang === 'en' ? b : a);
  const build = async (tab, times = 3) => { for (let i = 0; i < times; i++) { W.build(cmp, sel, tab, _); await settle(); } return W.build(cmp, sel, tab, _); };
  /* the page's own dialog, if one is open */
  const dialog = () => win.body.children.filter((c) => c.attrs['data-onhost-dialog'])[0] || null;
  const press = async (role) => { const d = dialog(); assert.ok(d, 'a dialog is open'); d.byAct(role).fire('click'); await settle(); };
  return { win, cmp, sel, W, build, dialog, press, _ };
}
const actionOf = (row, label) => (row.actions || []).filter((a) => a.label === label)[0];
const extraOf = (panel, label) => (panel.extra || []).filter((a) => a.label === label)[0];
const posts = (win, p) => win.calls.filter((c) => c.method === 'POST' && c.path === p);
const gets = (win, prefix) => win.calls.filter((c) => c.method === 'GET' && c.path.startsWith(prefix));

const cases = [];
const it = (name, fn) => cases.push([name, fn]);

const sub = (over = {}) => ({ id: 'sub_1', service_id: 'svc_w1', name: 'firma.cz', period: 'month', amount: { minor: 29900, currency: 'CZK', decimal: 299 }, state: 'active', auto_renew: true, cancel_at_period_end: false,
  current_period_end: '2026-11-04T00:00:00Z', next_renewal_at: '2026-11-04T00:00:00Z', ...over });
const H = () => ({ stat: (...a) => a, pill: (k) => 'pill:' + k, bar: (v, k) => 'bar:' + k, rowStyle: '', match: () => true });
async function costsSetup(subs, extra = {}) {
  const win = makeWindow({ 'GET /wallet': { data: { forecast: {} } }, 'GET /subscriptions': { data: subs }, 'GET /organizations/org_1/projects': { data: [] }, ...extra });
  load(win, 'onhost-panel-workbench.api.js', 'onhost-panel-pages.api.js');
  const cmp = makeCmp(win);
  const Pg = win.ctx.window.OnhostPanelPages;
  const _ = (a) => a;
  const view = async () => { for (let i = 0; i < 3; i++) { Pg.costs(cmp, _, H()); await settle(); } return Pg.costs(cmp, _, H()); };
  const dialog = () => win.body.children.filter((c) => c.attrs['data-onhost-dialog'])[0] || null;
  const press = async (role) => { const d = dialog(); assert.ok(d, 'a dialog is open'); d.byAct(role).fire('click'); await settle(); };
  return { win, cmp, view, dialog, press };
}
const sideRow = (v, title) => v.side.rows.filter((r) => r.title === title)[0];

/* ── costs page: renewal and ending ─────────────────────────────────────────────────────────────────── */
it('reads every page of the subscriptions and offers Manage on each row', async () => {
  const { win, view } = await costsSetup([sub()]);
  const v = await view();
  assert.equal(v.rows.length, 1);
  assert.equal(v.rows[0].action, 'Spravovat');
  const read = win.calls.filter((c) => c.method === 'GET' && c.path.startsWith('/subscriptions'));
  assert.equal(read.length, 1);
  assert.ok(!/limit=60/.test(read[0].path), 'not capped at 60');
});

it('turns automatic renewal off only after the confirmation, with one keyed POST', async () => {
  const { win, cmp, view, dialog, press } = await costsSetup([sub()], { 'POST /subscriptions/sub_1/auto-renew': { data: sub({ auto_renew: false }) } });
  let v = await view();
  v.rows[0].onAction();
  assert.equal(cmp.state.subSel, 'sub_1');
  v = await view();
  assert.match(v.side.title, /^Předplatné · firma\.cz/);
  sideRow(v, 'Vypnout automatickou obnovu').on(); await settle();
  assert.match(dialog().text(), /Vypnout automatickou obnovu\?/);
  assert.equal(win.ctx.confirms.length, 0, 'window.confirm is not used');
  assert.equal(win.calls.filter((c) => c.method === 'POST').length, 0, 'nothing is sent before the person confirms');
  await press('confirm');
  const post = win.calls.filter((c) => c.method === 'POST' && c.path === '/subscriptions/sub_1/auto-renew');
  assert.equal(post.length, 1);
  eq(post[0].body, { enabled: false });
  assert.match(post[0].key, /^k:subscription:sub_1:auto_off:/);
  assert.equal(win.flashes.pop()[0], 'Automatická obnova vypnuta');
  assert.equal(dialog(), null);
});

it('sends nothing when the confirmation is cancelled', async () => {
  const { win, view, press } = await costsSetup([sub()]);
  let v = await view();
  v.rows[0].onAction(); v = await view();
  sideRow(v, 'Ukončit ke konci období').on(); await settle();
  await press('cancel');
  assert.equal(win.calls.filter((c) => c.method === 'POST').length, 0);
});

it('ends the subscription at the end of the period, says the date, and offers to keep it afterwards', async () => {
  const { win, view, dialog, press } = await costsSetup([sub()], { 'POST /subscriptions/sub_1/cancel': { data: sub({ cancel_at_period_end: true, auto_renew: false }) } });
  let v = await view();
  v.rows[0].onAction(); v = await view();
  sideRow(v, 'Ukončit ke konci období').on(); await settle();
  assert.match(dialog().text(), /Ukončit předplatné ke konci období\?/);
  assert.match(dialog().text(), /4\. ?11\. ?2026/);
  await press('confirm');
  eq(win.calls.filter((c) => c.path === '/subscriptions/sub_1/cancel')[0].body, { cancel: true });
  assert.equal(win.flashes.pop()[0], 'Ukončení naplánováno');
});

it('shows a scheduled ending in the row and lets the customer take it back', async () => {
  const ending = sub({ cancel_at_period_end: true, auto_renew: false });
  const { win, view, press } = await costsSetup([ending], { 'POST /subscriptions/sub_1/cancel': { data: sub() } });
  let v = await view();
  assert.equal(v.rows[0].state, 'ukončení naplánováno');
  assert.match(v.rows[0].sub, /skončí/);
  v.rows[0].onAction(); v = await view();
  assert.ok(!sideRow(v, 'Ukončit ke konci období') && !sideRow(v, 'Zapnout automatickou obnovu'), 'no second ending while one is scheduled');
  sideRow(v, 'Zachovat předplatné').on(); await settle(); await press('confirm');
  eq(win.calls.filter((c) => c.path === '/subscriptions/sub_1/cancel')[0].body, { cancel: false });
});

it('turns automatic renewal on again, and says what the server refused', async () => {
  const off = sub({ auto_renew: false });
  const refuse = Object.assign(new Error('Požádejte vlastníka o zapnutí automatického prodloužení.'), { status: 403 });
  const { win, view, press } = await costsSetup([off], { 'POST /subscriptions/sub_1/auto-renew': refuse });
  let v = await view();
  v.rows[0].onAction(); v = await view();
  sideRow(v, 'Zapnout automatickou obnovu').on(); await settle(); await press('confirm');
  eq(win.calls.filter((c) => c.path === '/subscriptions/sub_1/auto-renew')[0].body, { enabled: true });
  const f = win.flashes.pop();
  assert.equal(f[0], 'Změna předplatného neproběhla');
  assert.equal(f[1], 'Požádejte vlastníka o zapnutí automatického prodloužení.');
});

it('does not offer changes on a subscription that is not active', async () => {
  const { view } = await costsSetup([sub({ state: 'past_due' })]);
  let v = await view();
  v.rows[0].onAction(); v = await view();
  assert.ok(sideRow(v, 'Předplatné teď nejde měnit'));
  assert.ok(!sideRow(v, 'Ukončit ke konci období'));
});

/* ── service detail: the billing area ──────────────────────────────────────────────────────────────── */
it('puts renewal and ending into the billing area of the service detail', async () => {
  const { build, win, press, dialog, cmp } = await setup({ routes: { 'GET /services/svc_w1/features': feats({ quotas: { enabled: true } }), 'GET /subscriptions': { data: [sub(), sub({ id: 'sub_other', service_id: 'svc_x' })] }, 'POST /subscriptions/sub_1/auto-renew': { data: sub({ auto_renew: false }) }, 'GET /services/svc_w1/resources/quotas': { data: {} } } });
  cmp.state.wbTool = 'quotas';
  const p = await build('addons');
  const row = p.rows.filter((r) => r.cells[0].t === 'Předplatné')[0];
  assert.ok(row, 'a subscription row');
  assert.match(row.cells[1].t, /obnovuje se automaticky z kreditu/);
  eq(row.actions.map((a) => a.label), ['Vypnout automatickou obnovu', 'Ukončit ke konci období']);
  actionOf(row, 'Vypnout automatickou obnovu').on(); await settle();
  assert.ok(dialog());
  await press('confirm');
  eq(win.calls.filter((c) => c.path === '/subscriptions/sub_1/auto-renew')[0].body, { enabled: false });
});

/* ── Node projects on a closed shared node ─────────────────────────────────────────────────────────── */
const NODE = { data: [{ name: 'api', remote_id: 'api', port: 3000, version: 'v20', state: 'online', path: 'app', domains: ['firma.cz'] }] };
const nodeChip = (p) => (p.chips || []).filter((c) => c.label === 'Node.js')[0];

it('shows only stop and delete for a Node project on a closed shared node, and no create form', async () => {
  const { build, win, press, dialog } = await setup({ routes: {
    'GET /services/svc_w1/features': feats({ node_projects: { enabled: false, reason: 'shared_node' }, node_projects_exit: { enabled: true } }),
    'GET /services/svc_w1/resources/node_projects': NODE, 'POST /services/svc_w1/actions': { data: { operation_id: 'op1' } } } });
  let p = await build('addons');
  assert.ok(nodeChip(p), 'the Node.js tool stays on the hub');
  nodeChip(p).on();
  p = await build('addons');
  assert.match(p.title, /Node\.js projekty/);
  assert.equal(p.form, null, 'no create form');
  eq(p.rows[0].actions.map((a) => a.label), ['Stop', 'Smazat'], 'no start, no restart');
  assert.match(p.note, /sdíleném serveru/);
  actionOf(p.rows[0], 'Stop').on(); await settle();
  assert.match(dialog().text(), /Zastavit projekt api\?/);
  assert.equal(posts(win, '/services/svc_w1/actions').length, 0);
  await press('confirm');
  eq(posts(win, '/services/svc_w1/actions')[0].body, { action: 'node.action', params: { remote_id: 'api', op: 'stop' } });
  actionOf(p.rows[0], 'Smazat').on(); await settle();
  assert.match(dialog().text(), /Smazat projekt api\?/);
  assert.equal(win.ctx.confirms.length, 0, 'window.confirm is not used');
  await press('confirm');
  eq(posts(win, '/services/svc_w1/actions')[1].body, { action: 'node.action', params: { remote_id: 'api', op: 'delete' } });
});

it('keeps all four actions and the create form where Node projects are fully on', async () => {
  const { build } = await setup({ routes: {
    'GET /services/svc_w1/features': feats({ node_projects: { enabled: true } }),
    'GET /services/svc_w1/resources/node_projects': NODE } });
  let p = await build('addons');
  nodeChip(p).on();
  p = await build('addons');
  assert.ok(p.form, 'the create form');
  eq(p.rows[0].actions.map((a) => a.label), ['Start', 'Stop', 'Restart', 'Smazat']);
});

it('hides the Node.js tool when neither the feature nor its exit is offered', async () => {
  const { build } = await setup({ routes: { 'GET /services/svc_w1/features': feats({ node_projects: { enabled: false, reason: 'plan' }, monitoring: { enabled: true } }) } });
  const p = await build('addons');
  assert.ok(!nodeChip(p));
});

let failed = 0;
for (const [name, fn] of cases) {
  try { await fn(); console.log('ok   ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n     ' + (e && e.stack ? e.stack.split('\n').slice(0, 6).join('\n     ') : e)); }
}
console.log(cases.length - failed + '/' + cases.length + ' passed');
process.exit(failed ? 1 : 0);
