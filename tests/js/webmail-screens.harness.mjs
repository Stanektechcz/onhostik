/* Harness for the web and mail screens (TASK-0069, phase C5/C6): runs the real module files in a vm with a recording
 * OnhostApi and a small fake DOM, builds the panels the way the prototype asks for them and presses their buttons.
 * No browser, no network.
 *   node tests/js/webmail-screens.harness.mjs        (exit code 0 = all checks passed; run by tests/Feature/Http/WebMailScreensTest.php) */
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

/* ── destructive actions: the server's preview, the page's own dialog, the fingerprint ─────────────────── */
it('asks the server what a database delete would do, shows it in the page and sends the fingerprint', async () => {
  const { build, win, dialog, press } = await setup({ routes: { 'GET /services/svc_w1/actions/database.delete/preview': preview(['Databáze se smaže i s obsahem.']), 'POST /services/svc_w1/actions': { data: { operation_id: 'op_abcdef123456' } } } });
  const p = await build('dbs');
  assert.equal(p.rows.length, 1);
  actionOf(p.rows[0], 'Smazat').on(); await settle();
  assert.equal(gets(win, '/services/svc_w1/actions/database.delete/preview')[0].path, '/services/svc_w1/actions/database.delete/preview?params%5Bremote_id%5D=12&locale=cs'); // the preview is asked in the page's language (G8 item 4)
  const d = dialog();
  assert.ok(d, 'the preview is shown in the page');
  const text = d.text();
  assert.match(text, /Smazat databázi shop\?/); assert.match(text, /Databáze se smaže i s obsahem\./); assert.match(text, /Na databázi je napojen web/); assert.match(text, /uchováme ji 30 dní/);
  assert.equal(win.ctx.confirms.length, 0, 'window.confirm is not used');
  assert.equal(posts(win, '/services/svc_w1/actions').length, 0, 'nothing is sent before the person confirms');
  await press('confirm');
  const post = posts(win, '/services/svc_w1/actions')[0];
  eq(post.body, { action: 'database.delete', params: { remote_id: '12' }, confirm: FP });
  assert.match(post.key, /^k:svc:svc_w1:database\.delete:/, 'one Idempotency-Key per intent, scoped to the service and the action');
  assert.equal(dialog(), null, 'the dialog closes');
  assert.match(win.flashes.pop()[1], /abcdef123456|123456/);
});

it('cancelling the dialog sends nothing, and so does Escape', async () => {
  const { build, win, press, dialog } = await setup({ routes: { 'GET /services/svc_w1/actions/database.delete/preview': preview(['Databáze se smaže i s obsahem.']) } });
  const p = await build('dbs');
  actionOf(p.rows[0], 'Smazat').on(); await settle();
  await press('cancel');
  assert.equal(posts(win, '/services/svc_w1/actions').length, 0);
  assert.equal(dialog(), null);
});

it('does not run a destructive action when its preview cannot be read', async () => {
  const err = Object.assign(new Error('Služba není vaše'), { status: 403 });
  const { build, win, dialog } = await setup({ routes: { 'GET /services/svc_w1/actions/database.delete/preview': err } });
  const p = await build('dbs');
  actionOf(p.rows[0], 'Smazat').on(); await settle();
  assert.equal(dialog(), null);
  assert.equal(posts(win, '/services/svc_w1/actions').length, 0);
  const [t, b] = win.flashes.pop();
  assert.equal(t, 'Náhled se nenačetl, nic jsme neprovedli'); assert.equal(b, 'Služba není vaše');
});

it('shows the server refusal of a stale confirmation (target changed) and does not claim success', async () => {
  const err = Object.assign(new Error('Od zobrazení náhledu se změnilo, čeho se akce týká.'), { status: 409 });
  const { build, win, press } = await setup({ routes: { 'GET /services/svc_w1/actions/database.delete/preview': preview(['Databáze se smaže i s obsahem.']), 'POST /services/svc_w1/actions': err } });
  const p = await build('dbs');
  actionOf(p.rows[0], 'Smazat').on(); await settle();
  await press('confirm');
  const [t, b] = win.flashes.pop();
  assert.equal(t, 'Akce neproběhla'); assert.match(b, /změnilo/);
});

it('rolls a server back to a snapshot and deletes one through the preview, snapshot name in the query', async () => {
  const { build, win, press } = await setup({ sel: vps(), routes: { 'GET /services/svc_v1/actions/rollback_snapshot/preview': preview(['Server se vrátí do stavu ze snapshotu.']), 'GET /services/svc_v1/actions/snapshot.delete/preview': preview(['Snapshot se smaže.']), 'POST /services/svc_v1/actions': { data: {} } } });
  const p = await build('snap');
  actionOf(p.rows[0], 'Vrátit se').on(); await settle(); await press('confirm');
  eq(posts(win, '/services/svc_v1/actions')[0].body, { action: 'rollback_snapshot', params: { name: 'pred-upgradem' }, confirm: FP });
  actionOf(p.rows[0], 'Smazat').on(); await settle(); await press('confirm');
  eq(posts(win, '/services/svc_v1/actions')[1].body, { action: 'snapshot.delete', params: { name: 'pred-upgradem' }, confirm: FP });
  assert.equal(win.ctx.confirms.length, 0);
});

/* ── service check, spec, ssh keys, sending, password link ───────────────────────────────────────────── */
const HEALTH = { data: { service_id: 'svc_w1', name: 'firma.cz', family: 'web', state: 'active', verdict: 'warn', checked_at: '2026-10-04T10:00:00Z', findings: [
  { key: 'state', level: 'ok', cs: 'Služba je aktivní.', en: 'The service is active.' },
  { key: 'backup', level: 'warn', cs: 'Služba zatím nemá žádnou dokončenou zálohu.', en: 'The service has no finished backup yet.' },
  { key: 'cert', level: 'bad', cs: 'Certifikát vypršel.', en: 'The certificate has expired.' }] } };

it('runs the service check on the operations tab: worst finding first, in the person\'s language', async () => {
  const { build, win, cmp } = await setup({ routes: { 'GET /services/svc_w1/health': HEALTH, 'GET /services/svc_w1/access': { data: [] }, 'GET /services/svc_w1/operations': { data: [] }, 'GET /services/svc_w1/chargeback': { data: { request: null, estimate: {}, percent: 50 } }, 'GET /services/svc_w1/withdrawal': { data: {} } } });
  let p = await build('noc');
  eq(p.chips.map((c) => c.label), ['Provoz', 'Kontrola služby', 'Nastavení jako dokument', 'Přístupy']);
  p.chips[1].on();
  p = await build('noc');
  assert.equal(p.title, 'Kontrola služby · firma.cz');
  assert.equal(p.state, 'pár věcí k vyřešení');
  eq(p.rows.slice(0, 3).map((r) => r.cells[1].t), ['Certifikát vypršel.', 'Služba zatím nemá žádnou dokončenou zálohu.', 'Služba je aktivní.']);
  assert.equal(gets(win, '/services/svc_w1/health').length, 1, 'one read, cached');
  cmp.state.lang = 'en';
  p = await build('noc');
  assert.equal(p.rows[0].cells[1].t, 'The certificate has expired.');
});

it('says why a feature is off: the service is not running, or this person may not', async () => {
  const { build, cmp } = await setup({ routes: { 'GET /services/svc_w1/health': HEALTH, 'GET /services/svc_w1/access': { data: [] }, 'GET /services/svc_w1/operations': { data: [] }, 'GET /services/svc_w1/chargeback': { data: { request: null, estimate: {}, percent: 50 } }, 'GET /services/svc_w1/withdrawal': { data: {} } } });
  cmp.state.wbTool = 'health';
  const p = await build('noc');
  const blocked = p.rows.filter((r) => r.cells[0].t === 'nedostupné');
  assert.equal(blocked.length, 2);
  assert.match(blocked[0].cells[1].t, /1 funkce teď nejde: backups/);
  assert.match(blocked[0].note, /Služba teď neběží/);
  assert.match(blocked[1].cells[1].t, /1 funkce teď nejde: redirects/);
  assert.match(blocked[1].note, /nemáte oprávnění/);
  const redirect = await build('redirect');
  assert.match(redirect.note, /nemáte oprávnění/, 'a tab that is reached says why it is empty');
});

it('lists a failed service check instead of an empty verdict', async () => {
  const err = Object.assign(new Error('Služba nenalezena'), { status: 404 });
  const { build, cmp } = await setup({ routes: { 'GET /services/svc_w1/health': err, 'GET /services/svc_w1/access': { data: [] }, 'GET /services/svc_w1/operations': { data: [] }, 'GET /services/svc_w1/chargeback': { data: { request: null, estimate: {}, percent: 50 } }, 'GET /services/svc_w1/withdrawal': { data: {} } } });
  cmp.state.wbTool = 'health';
  const p = await build('noc');
  assert.match(p.rows[0].cells[0].t, /Nelze načíst: Služba nenalezena/);
});

const SPEC = { data: { php: '8.2', redirect: null, security: { waf: true }, features: ['php', 'security'] } };
const nocRoutes = { 'GET /services/svc_w1/access': { data: [] }, 'GET /services/svc_w1/operations': { data: [] }, 'GET /services/svc_w1/chargeback': { data: { request: null, estimate: {}, percent: 50 } }, 'GET /services/svc_w1/withdrawal': { data: {} } };

it('shows the settings document and sends only the section that was edited, after a confirmation', async () => {
  const { build, win, cmp, press, dialog } = await setup({ routes: { ...nocRoutes, 'GET /services/svc_w1/spec': SPEC, 'PUT /services/svc_w1/spec': { data: { operation_id: 'op_1' } } } });
  cmp.state.wbTool = 'spec';
  let p = await build('noc');
  eq(p.rows.map((r) => r.cells[0].t), ['php', 'redirect', 'security']);
  assert.match(p.rows[1].note, /panel tuto sekci nevrátil/);
  assert.equal((p.rows[1].actions || []).length, 0, 'a section the panel did not report cannot be edited');
  actionOf(p.rows[0], 'Upravit').on();
  assert.equal(cmp.state.wbF.a, 'php'); assert.equal(cmp.state.wbF.b, '"8.2"');
  cmp.state.wbF = { a: 'php', b: '"8.3"' };
  p = await build('noc');
  p.form.on(); await settle();
  assert.match(dialog().text(), /Použít sekci php\?/);
  assert.equal(win.calls.filter((c) => c.method === 'PUT').length, 0);
  await press('confirm');
  const put = win.calls.filter((c) => c.method === 'PUT')[0];
  eq(put.body, { spec: { php: '8.3' } });
  assert.match(put.key, /^k:svc:svc_w1:spec:/);
});

it('refuses an unknown section and invalid JSON before anything is sent', async () => {
  const { build, win, cmp } = await setup({ routes: { ...nocRoutes, 'GET /services/svc_w1/spec': SPEC } });
  cmp.state.wbTool = 'spec';
  let p = await build('noc');
  cmp.state.wbF = { a: 'redirect', b: '"x"' }; p = await build('noc'); p.form.on();
  assert.equal(win.flashes.pop()[0], 'Neznámá sekce');
  cmp.state.wbF = { a: 'php', b: '{nejde' }; p = await build('noc'); p.form.on();
  assert.equal(win.flashes.pop()[0], 'Hodnota není platný JSON');
  assert.equal(win.calls.filter((c) => c.method === 'PUT').length, 0);
});

const KEYS = { data: { keys: [
  { id: 'k1', account: { remote_id: '5', user: 'web1' }, key_type: 'ssh-ed25519', fingerprint: 'SHA256:abc', owner: { name: 'Jana', email: 'jana@example.cz', member: true }, state: 'active', installed_at: '2026-09-01T10:00:00Z' },
  { id: 'k2', account: { remote_id: '5', user: 'web1' }, key_type: 'ssh-rsa', fingerprint: 'SHA256:def', owner: { name: 'Petr', email: 'petr@example.cz', member: false }, state: 'revoking', revocation: { attempts: 2 } },
  { id: 'k3', account: { remote_id: '5', user: 'web1' }, key_type: 'ssh-rsa', fingerprint: 'SHA256:old', owner: null, state: 'revoked' }], pending_revocations: 1 } };

it('manages SSH keys: who owns which key, revocations the server has not confirmed, taking a key off', async () => {
  const { build, win, cmp, press, dialog } = await setup({ routes: { 'GET /services/svc_w1/ssh-keys': KEYS, 'POST /services/svc_w1/actions': { data: { operation_id: 'op_k' } } } });
  let p = await build('ssh');
  assert.equal(p.chips[1].label, 'Klíče a odvolání · 1');
  p.chips[1].on();
  p = await build('ssh');
  assert.equal(p.title, 'SSH klíče · firma.cz');
  assert.equal(p.state, '2 klíče platí');
  assert.match(p.rows[0].cells[1].t, /odebraný klíč server nepotvrdil/, 'the unconfirmed revocation is the first thing said');
  assert.match(p.rows[0].note, /ještě se jím lze přihlásit|ještě lze přihlásit|se tím klíčem ještě přihlásit/);
  const revoking = p.rows.filter((r) => r.cells[3] && r.cells[3].t === 'ruší se')[0];
  assert.match(revoking.cells[2].t, /Petr \(už není členem\)/);
  assert.match(revoking.note, /nepotvrdil, do té doby se jím lze přihlásit\. Pokusů: 2/);
  const active = p.rows.filter((r) => r.cells[3] && r.cells[3].t === 'platí')[0];
  actionOf(active, 'Odebrat klíč').on(); await settle();
  assert.match(dialog().text(), /Odebrat klíč z účtu web1\?/);
  await press('confirm');
  eq(posts(win, '/services/svc_w1/actions')[0].body, { action: 'shell.key', params: { remote_id: '5', ssh_key: '' } });
  assert.equal(win.ctx.confirms.length, 0);
});

it('sets a key on a shell account only for an existing account and a public key', async () => {
  const { build, win, cmp, press } = await setup({ routes: { 'GET /services/svc_w1/ssh-keys': KEYS, 'POST /services/svc_w1/actions': { data: {} } } });
  cmp.state.wbSsh = 'keys';
  let p = await build('ssh');
  cmp.state.wbF = { a: 'nikdo', b: 'ssh-ed25519 AAAA' }; p = await build('ssh'); p.form.on();
  assert.equal(win.flashes.pop()[0], 'Takový shell účet není');
  cmp.state.wbF = { a: 'web1', b: '-----BEGIN OPENSSH PRIVATE KEY-----' }; p = await build('ssh'); p.form.on();
  assert.equal(win.flashes.pop()[0], 'To nevypadá jako veřejný SSH klíč');
  assert.equal(posts(win, '/services/svc_w1/actions').length, 0);
  cmp.state.wbF = { a: 'web1', b: 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMq jana@pc' }; p = await build('ssh'); p.form.on(); await settle(); await press('confirm');
  eq(posts(win, '/services/svc_w1/actions')[0].body, { action: 'shell.key', params: { remote_id: '5', ssh_key: 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMq jana@pc' } });
});

it('switches sending of a mail domain on and off, each direction confirmed', async () => {
  const { build, win, press, dialog } = await setup({ sel: mail(), routes: { 'POST /services/svc_m1/actions': { data: {} } } });
  const p = await build('auth');
  const row = p.rows.filter((r) => r.cells[0].t === 'Odesílání pošty')[0];
  assert.ok(row, 'the sending row is on the DKIM tab');
  eq((row.actions || []).map((a) => a.label), ['Zapnout', 'Vypnout']);
  actionOf(row, 'Vypnout').on(); await settle();
  assert.match(dialog().text(), /Vypnout odesílání pošty z firma\.cz\?/); assert.match(dialog().text(), /Příchozí pošta chodí dál/);
  await press('confirm');
  eq(posts(win, '/services/svc_m1/actions')[0].body, { action: 'sending.set', params: { enabled: false } });
  actionOf(row, 'Zapnout').on(); await settle(); await press('confirm');
  eq(posts(win, '/services/svc_m1/actions')[1].body, { action: 'sending.set', params: { enabled: true } });
});

it('says why sending cannot be switched when the person may not', async () => {
  const { build } = await setup({ sel: mail(), routes: { 'GET /services/svc_m1/features': feats({ mailboxes: { enabled: true }, dkim: { enabled: true }, sending: { enabled: false, reason: 'permission' } }) } });
  const p = await build('auth');
  const row = p.rows.filter((r) => r.cells[0].t === 'Odesílání pošty')[0];
  assert.match(row.cells[1].t, /nemáte oprávnění/);
  assert.equal((row.actions || []).length, 0);
});

it('hands over a one-time password link for a mailbox user, in a dialog they can copy from', async () => {
  const url = 'https://panel.example.cz/mailbox/password/abc?signature=1';
  const { build, win, press, dialog } = await setup({ sel: mail(), routes: { 'POST /services/svc_m1/mailbox-password-link': { data: { url, expires_at: '2026-10-05T10:00:00Z', mailbox: 'jana@firma.cz' } } } });
  const p = await build('boxes');
  const row = p.rows.filter((r) => r.cells[0].t === 'jana@firma.cz')[0];
  eq((row.actions || []).map((a) => a.label), ['Odkaz na heslo', 'Nové heslo', 'Smazat']);
  actionOf(row, 'Odkaz na heslo').on(); await settle();
  const post = posts(win, '/services/svc_m1/mailbox-password-link')[0];
  eq(post.body, { remote_id: '77' });
  assert.match(post.key, /^k:svc:svc_m1:mbpw:/);
  const d = dialog();
  assert.match(d.text(), /Odkaz na nastavení hesla/); assert.match(d.text(), /Platí jednou/);
  assert.equal(d.all().filter((e) => e.attrs['data-copy'])[0].attrs.value, url);
  assert.equal(d.byAct('cancel'), undefined, 'a plain notice has one button');
  await press('confirm');
  assert.equal(dialog(), null);
});

it('keeps the confirmation when there is no document: window.confirm with the same words', async () => {
  const { W, cmp, win } = await setup();
  win.ctx.document.body = null;
  win.ctx.confirmAnswer = false;
  const yes = await W.dialog(cmp, { title: 'Smazat?', lines: ['Zmizí to.'], note: 'Záloha zůstane.' });
  assert.equal(yes, false);
  assert.match(win.ctx.confirms[0], /Smazat\?[\s\S]*Zmizí to\.[\s\S]*Záloha zůstane\./);
});

it('has a Czech plural helper: 1 klíč, 2-4 klíče, 0 and 5+ klíčů', async () => {
  const { W, cmp } = await setup();
  const w = (n) => W.plural(cmp, n, ['klíč', 'klíče', 'klíčů'], ['key', 'keys']);
  eq([0, 1, 2, 4, 5, 11, 21].map(w), ['0 klíčů', '1 klíč', '2 klíče', '4 klíče', '5 klíčů', '11 klíčů', '21 klíčů']);
  cmp.state.lang = 'en';
  eq([1, 2].map(w), ['1 key', '2 keys']);
});

/* ── projects and pages: what is read in full and what fails loudly ───────────────────────────────────── */
const H = () => ({ stat: (...a) => a, pill: (k) => 'pill:' + k, bar: (v, k) => 'bar:' + k, rowStyle: '', match: () => true });
function setupPage(files, routesIn, state = {}) {
  const win = makeWindow(routesIn);
  load(win, ...files);
  const cmp = makeCmp(win);
  Object.assign(cmp.state, state);
  return { win, cmp, _: (a) => a };
}

it('reads the projects page with every page of services and shows a failed read with a retry, not an empty list', async () => {
  const { win, cmp, _ } = setupPage(['onhost-panel-projects.api.js'], {
    'GET /organizations/org_1/projects': Object.assign(new Error('Server neodpovídá'), { status: 503 }),
  });
  const P = win.ctx.window.OnhostPanelProjects;
  let v = P.view(cmp, _, H()); await settle();
  v = P.view(cmp, _, H());
  assert.equal(v.rows[0].name, 'Projekty se nepodařilo načíst');
  assert.equal(v.rows[0].sub, 'Server neodpovídá');
  assert.equal(v.rows[0].action, 'Zkusit znovu');
  assert.equal(win.flashes.filter((f) => f[0] === 'Načtení se nepodařilo').length, 1, 'said once');
  P.view(cmp, _, H()); P.view(cmp, _, H()); await settle();
  assert.equal(gets(win, '/organizations/org_1/projects').length, 1, 'a failed read is not retried by every render');
  win.ctx.OnhostApi.get = () => Promise.resolve({ data: [{ id: 'prj_1', name: 'E-shop', state: 'active', services: 1, members: 2, monthly: { minor: 10000, currency: 'CZK' }, share: 100 }], spend: { monthly_total: { minor: 10000, currency: 'CZK' } } });
  v.rows[0].onAction(); await settle();
  v = P.view(cmp, _, H()); await settle(); v = P.view(cmp, _, H());
  assert.equal(v.rows[0].name, 'E-shop');
  assert.equal(v.rows[0].c2, '1 služba · 2 členové');
});

it('lists all services for "assign" through OnhostApi.all, and a failure is a row, not "every service is already in"', async () => {
  const detail = { data: { id: 'prj_1', name: 'E-shop', state: 'active', members: [], services: [], spend: {}, created_at: '2026-10-01T10:00:00Z' } };
  const ok = setupPage(['onhost-panel-projects.api.js'], { 'GET /organizations/org_1/projects': { data: [], spend: {} }, 'GET /organizations/org_1/projects/prj_1': detail, 'GET /services': { data: [{ id: 's1', label: 'Web 1', project_id: null, state: 'ACTIVE' }] } }, { projSel: 'prj_1' });
  const Pok = ok.win.ctx.window.OnhostPanelProjects;
  Pok.view(ok.cmp, ok._, H()); await settle();
  let v = Pok.view(ok.cmp, ok._, H());
  assert.ok(ok.win.calls.some((c) => c.path === '/services'), 'the services come through OnhostApi.all, not a hand-written limit');
  assert.ok(!ok.win.calls.some((c) => /limit=200/.test(c.path)));
  assert.equal(v.side.rows[1].title, 'Web 1');

  const bad = setupPage(['onhost-panel-projects.api.js'], { 'GET /organizations/org_1/projects': { data: [], spend: {} }, 'GET /organizations/org_1/projects/prj_1': detail, 'GET /services': Object.assign(new Error('Časový limit'), { status: 504 }) }, { projSel: 'prj_1' });
  const Pbad = bad.win.ctx.window.OnhostPanelProjects;
  Pbad.view(bad.cmp, bad._, H()); await settle();
  v = Pbad.view(bad.cmp, bad._, H());
  const titles = v.side.rows.map((r) => r.title);
  assert.ok(titles.includes('Služby se nepodařilo načíst'));
  assert.ok(!titles.includes('Všechny služby už jsou v tomto projektu'));
});

it('shows a project that did not load, not an endless "loading"', async () => {
  const { win, cmp, _ } = setupPage(['onhost-panel-projects.api.js'], { 'GET /organizations/org_1/projects': { data: [], spend: {} }, 'GET /organizations/org_1/projects/prj_1': Object.assign(new Error('Projekt neexistuje'), { status: 404 }), 'GET /services': { data: [] } }, { projSel: 'prj_1' });
  const P = win.ctx.window.OnhostPanelProjects;
  P.view(cmp, _, H()); await settle();
  const v = P.view(cmp, _, H());
  assert.equal(v.title, 'Projekt se nepodařilo načíst');
  assert.equal(v.rows[0].sub, 'Projekt neexistuje');
});

it('puts a failed read of the backups page first, with a retry, and counts days in Czech', async () => {
  const archive = { id: 'a1', family: 'web', label: 'stary-web.cz', paid: true, size_bytes: 1048576, parts: ['files'], days_left: 2, retention_until: '2026-11-01T00:00:00Z' };
  const { win, cmp, _ } = setupPage(['onhost-panel-pages.api.js'], { 'GET /backups': Object.assign(new Error('Zálohy nejsou dostupné'), { status: 500 }), 'GET /services/archives': { archives: [archive], policy: { retention_days: 60 } } });
  const Pg = win.ctx.window.OnhostPanelPages;
  Pg.backups(cmp, _, H()); await settle();
  const v = Pg.backups(cmp, _, H());
  assert.equal(v.rows[0].name, 'Zálohy se nepodařilo načíst');
  assert.equal(v.rows[0].sub, 'Zálohy nejsou dostupné');
  assert.equal(v.rows[0].action, 'Zkusit znovu');
  assert.equal(v.rows[1].state, '2 dny do smazání');
  assert.match(v.side.rows[0].meta, /2 dny$/);
  assert.equal(gets(win, '/backups').length, 1);
});

it('reads every service when an archive is restored, so the first click does not say "nothing to restore into"', async () => {
  const archive = { id: 'a1', family: 'web', label: 'stary-web.cz', paid: true, parts: [], days_left: 20 };
  const { win, cmp, _ } = setupPage(['onhost-panel-pages.api.js'], {
    'GET /backups': { data: [] }, 'GET /services/archives': { archives: [archive], policy: {} },
    'GET /services': { data: [{ id: 's9', family: 'web', state: 'ACTIVE', label: 'novy-web.cz' }, { id: 's8', family: 'mail', state: 'ACTIVE', label: 'posta' }] },
    'POST /services/archives/a1/restore': { data: {} },
  });
  const Pg = win.ctx.window.OnhostPanelPages;
  Pg.backups(cmp, _, H()); await settle();
  const v = Pg.backups(cmp, _, H());
  v.side.rows[0].on(); await settle();
  assert.ok(win.calls.some((c) => c.path === '/services'), 'read on the click');
  assert.equal(win.ctx.confirms.length, 1);
  assert.match(win.ctx.confirms[0], /novy-web\.cz/);
  const post = posts(win, '/services/archives/a1/restore')[0];
  eq(post.body, { service_id: 's9' });
  assert.match(post.key, /^k:archive\.restore:a1:/);
});

it('says when the services could not be read for an archive restore', async () => {
  const archive = { id: 'a1', family: 'web', label: 'stary-web.cz', paid: true, parts: [], days_left: 20 };
  const { win, cmp, _ } = setupPage(['onhost-panel-pages.api.js'], { 'GET /backups': { data: [] }, 'GET /services/archives': { archives: [archive], policy: {} }, 'GET /services': Object.assign(new Error('Služby nejsou dostupné'), { status: 500 }) });
  const Pg = win.ctx.window.OnhostPanelPages;
  Pg.backups(cmp, _, H()); await settle();
  Pg.backups(cmp, _, H()).side.rows[0].on(); await settle();
  assert.equal(win.flashes.pop()[1], 'Služby nejsou dostupné');
  assert.equal(posts(win, '/services/archives/a1/restore').length, 0);
});

/* ── the prototypes stay as they are ──────────────────────────────────────────────────────────────────── */
it('has no destructive action left behind a plain act() or a window.confirm', async () => {
  const src = read('onhost-panel-workbench.api.js') + read('onhost-panel-tools.api.js');
  for (const action of ['database.delete', 'site.delete', 'rollback_snapshot', 'snapshot.delete', 'gbackup.delete', 'backup.delete', 'staging.push', 'staging.delete', 'restore']) {
    const quoted = "'" + action + "'";
    assert.ok(src.includes(quoted), action + ' is wired');
    assert.ok(!src.includes('act(cmp, sel, ' + quoted) && !src.includes('act(ctx, ' + quoted), action + ' is sent without its preview');
    assert.ok(src.includes('destructive(cmp, sel, ' + quoted) || src.includes('destructive(ctx.cmp, sel, ' + quoted), action + ' goes through destructive()');
  }
});

let failed = 0;
for (const [name, fn] of cases) {
  try { await fn(); console.log('ok   ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n     ' + (e && e.stack ? e.stack.split('\n').slice(0, 6).join('\n     ') : e)); }
}
console.log(cases.length - failed + '/' + cases.length + ' passed');
process.exit(failed ? 1 : 0);
