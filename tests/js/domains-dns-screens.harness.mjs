/* Harness for the panel's domain and DNS screens (TASK-0056): runs the real module files in a vm with a recording OnhostApi,
 * builds the panels the way the prototype asks for them and presses their buttons. No browser, no network.
 *   node tests/js/domains-dns-screens.harness.mjs        (exit code 0 = all checks passed; run by tests/Feature/Http/PanelDomainDnsScreensTest.php) */
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
const settle = async () => { for (let i = 0; i < 8; i++) await tick(); };

function makeWindow(routes) {
  const calls = [];
  const flashes = [];
  const ctx = { console, setTimeout: (fn) => 0 && fn, clearTimeout() {}, Promise, JSON, Date, Object, Array, String, Number, parseInt, encodeURIComponent, RegExp, Math, Uint32Array };
  ctx.window = ctx;
  ctx.location = { reload() { calls.push(['RELOAD']); } };
  const appended = [];
  ctx.document = { currentScript: { src: '/surfaces/api/onhost-panel-workbench.api.js?v=1' }, documentElement: { lang: 'cs' }, head: { appendChild(el) { appended.push(el); } }, createElement: () => ({}) };
  ctx.crypto = { getRandomValues: (b) => b };
  const answer = (method, p, body) => {
    calls.push([method, p, body]);
    const handler = routes[method + ' ' + p.split('?')[0]] || routes[method + ' *'];
    if (!handler) return Promise.reject(Object.assign(new Error('no route ' + method + ' ' + p), { status: 404 }));
    const out = typeof handler === 'function' ? handler(body, p) : handler;
    return out instanceof Error ? Promise.reject(out) : Promise.resolve(out);
  };
  let key = 0;
  ctx.OnhostApi = {
    base: '/v1',
    get: (p) => answer('GET', p),
    post: (p, b, k) => { calls.push(['KEY', k]); return answer('POST', p, b); },
    del: (p) => answer('DELETE', p),
    key: () => 'k' + ++key,
  };
  ctx.ONHOST = { user: { name: 'Jana Nováková', email: 'jana@example.cz' } };
  ctx.ONHOST_PANEL = { tlds: [{ tld: 'cz', registry_terms_url: 'https://nic.cz/terms', registrar_terms_url: 'https://onhost.cz/terms' }] };
  ctx.confirms = [];
  ctx.confirm = (t) => { ctx.confirms.push(t); return ctx.confirmAnswer !== false; };
  return { ctx, calls, flashes, appended };
}

function load(win, ...files) {
  vm.createContext(win.ctx);
  for (const f of files) vm.runInContext(read(f), win.ctx, { filename: f });
}

/* a stand-in for the prototype component: the helpers WB_H gives panels and the state the modules read */
function makeCmp(win) {
  const cmp = {
    state: { lang: 'cs', wbF: {}, wbTick: 0 },
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

const domain = (over = {}) => ({ id: 'dom_1', type: 'domain', name: 'firma.cz', fqdn: 'firma.cz', apiState: 'ACTIVE', dns_provider: 'external', ...over });
const SV = { domain: [{ id: 'dns' }, { id: 'soa' }, { id: 'reg' }, { id: 'sec' }, { id: 'noc' }] };

const routes = (extra = {}) => ({
  'GET /domains/dom_1': { data: { id: 'dom_1', fqdn: 'firma.cz', state: 'ACTIVE', auto_renew: true, transfer_lock: true, renewal_period: 1, registrar_operations: [], registrant: { name: 'Jana Nováková', email: 'jana@example.cz', phone: '+420.777000111', street: 'Dlouhá 1', city: 'Praha', postal_code: '11000', country: 'CZ' } } },
  'GET /domains/firma.cz/zone': { data: { id: 'zon_1', name: 'firma.cz', version: 3, serial: 2026100403, records: [{ id: 'rec_1', name: '@', type: 'A', content: '203.0.113.5', ttl: 3600 }], changes: [], dnssec: false, ds: [] } },
  'GET /dns/zones': { data: [{ id: 'zon_1', name: 'firma.cz', state: 'active', version: 3, domain_id: 'dom_1' }, { id: 'zon_2', name: 'samostatna.cz', state: 'active', version: 1, domain_id: null }] },
  'GET /dns/zones/zon_1/versions': { data: [{ version: 1, serial: 1, records: 5, reason: 'zone created', committed_at: '2026-10-01T10:00:00Z' }, { version: 2, serial: 2, records: 1, reason: 'add api', committed_at: '2026-10-02T10:00:00Z' }, { version: 3, serial: 3, records: 2, reason: 'commit', committed_at: '2026-10-03T10:00:00Z' }] },
  'GET /dns/zones/samostatna.cz': { data: { id: 'zon_2', name: 'samostatna.cz', version: 1, serial: 1, records: [], changes: [], dnssec: false, ds: [] } },
  ...extra,
});

const cases = [];
const it = (name, fn) => cases.push([name, fn]);

async function setup(opts = {}) {
  const win = makeWindow(routes(opts.routes));
  load(win, 'onhost-panel-workbench.api.js', ...(opts.noModule ? [] : ['onhost-panel-domains.api.js']));
  const cmp = makeCmp(win);
  const sel = domain(opts.sel);
  const W = win.ctx.window.OnhostPanelWorkbench;
  const build = async (tab, times = 1) => { let p; for (let i = 0; i < times; i++) { p = W.build(cmp, sel, tab, (a, b) => (cmp.state.lang === 'en' ? b : a)); await settle(); } return W.build(cmp, sel, tab, (a, b) => (cmp.state.lang === 'en' ? b : a)); };
  return { win, cmp, sel, W, build, M: win.ctx.window.OnhostPanelDomains };
}
const lastPost = (calls, p) => calls.filter((c) => c[0] === 'POST' && c[1] === p).pop();
const labels = (xs) => (xs || []).map((x) => x.label);

it('shows the DNS tab for every domain, with or without an ONhost zone', async () => {
  const { W, cmp } = await setup();
  const tabs = W.tabs(cmp, domain({ dns_provider: 'external' }), SV).map((t) => t.id).sort();
  eq(tabs, ['dns', 'noc', 'reg', 'sec', 'soa']);
});

it('adds the holder-contact and transfer chips to the registration tab and switches between them', async () => {
  const { build, cmp, M, sel } = await setup();
  let p = await build('reg');
  eq(labels(p.chips), ['Registrace', 'Kontakt držitele']);
  assert.ok(p.rows.length >= 6, 'the registration facts stay');
  p.chips[1].on();
  p = await build('reg');
  assert.equal(p.title.startsWith('Kontakt držitele'), true);
  eq(p.form.fields.map((f) => f.k), ['a', 'b', 'c', 'd', 'e', 'f']);
  assert.equal(M.state.mode[sel.id + ':reg'], 'holder');
});

it('sends only the filled holder fields, asks for confirmation, tells how many domains share the contact', async () => {
  const { build, cmp, win } = await setup({ routes: { 'POST /domains/dom_1/holder': { data: { changed: ['email'], domains_affected: 3, registrar_updated: true } } } });
  let p = await build('reg'); p.chips[1].on(); p = await build('reg');
  cmp.state.wbF = { a: 'nova@example.cz', f: 'cz' };
  p.form.on(); await settle();
  const post = lastPost(win.calls, '/domains/dom_1/holder');
  eq(post[2], { email: 'nova@example.cz', country: 'cz' });
  assert.match(win.calls.find((c) => c[0] === 'KEY')[1], /^k\d+$/);
  assert.equal(win.ctx.confirms.length, 1);
  const last = win.flashes.pop();
  assert.equal(last[0], 'Kontakt držitele změněn');
  assert.match(last[1], /3 domény/);
});

it('validates the holder form in Czech before anything is sent', async () => {
  const { build, cmp, win } = await setup();
  let p = await build('reg'); p.chips[1].on(); p = await build('reg');
  p.form.on(); assert.equal(win.flashes.pop()[0], 'Není co měnit');
  cmp.state.wbF = { a: 'neni-email' }; p.form.on(); assert.equal(win.flashes.pop()[0], 'E-mail nemá platný tvar');
  cmp.state.wbF = { f: 'CZE' }; p.form.on(); assert.equal(win.flashes.pop()[0], 'Země musí mít dvě písmena');
  assert.equal(win.calls.filter((c) => c[0] === 'POST').length, 0);
});

it('shows the refusal to change the holder himself in Czech, never the English server text', async () => {
  const err = Object.assign(new Error('Changing the holder (name, company, IČO) is a transfer'), { status: 422, body: { error: 'domain_holder_identity_change', message: 'Changing the holder (name, company, IČO) is a transfer' } });
  const { build, cmp, win } = await setup({ routes: { 'POST /domains/dom_1/holder': err } });
  let p = await build('reg'); p.chips[1].on(); p = await build('reg');
  cmp.state.wbF = { a: 'nova@example.cz' }; p.form.on(); await settle();
  const [title, body] = win.flashes.pop();
  assert.equal(title, 'Akce neproběhla');
  assert.match(body, /převod domény na jinou osobu/);
  assert.doesNotMatch(body, /Changing the holder/);
});

it('starts a transfer in with consent, validates the form first and reloads the list afterwards (flag on)', async () => {
  const { build, cmp, win, M } = await setup({ routes: { 'POST /domains/transfer-in': { data: { operation_id: 'op_1', state: 'PENDING' } } } });
  M.flags.transferIn = true;
  let p = await build('reg'); p.chips[2].on(); p = await build('reg');
  eq(p.form.fields.map((f) => f.k), ['a', 'b']);
  cmp.state.wbF = { a: 'neplatne', b: 'AUTH-1234' }; p.form.on(); assert.equal(win.flashes.pop()[0], 'Zadejte celý název domény');
  cmp.state.wbF = { a: 'firma.xyz', b: 'AUTH-1234' }; p.form.on(); assert.equal(win.flashes.pop()[0], 'Tuto koncovku zatím nepřevádíme');
  cmp.state.wbF = { a: 'firma.cz', b: 'ab c' }; p.form.on(); assert.equal(win.flashes.pop()[0], 'AUTH-ID nemá platný tvar');
  assert.equal(win.calls.filter((c) => c[0] === 'POST').length, 0);
  cmp.state.wbF = { a: 'https://Prevod.CZ/', b: 'AUTH-1234' }; p.form.on(); await settle();
  const post = lastPost(win.calls, '/domains/transfer-in');
  assert.equal(post[2].fqdn, 'prevod.cz');
  assert.equal(post[2].auth_info, 'AUTH-1234');
  assert.equal(post[2].consent.person, 'Jana Nováková');
  assert.equal(post[2].consent.registry_terms_url, 'https://nic.cz/terms');
  assert.match(win.ctx.confirms.pop(), /prevod\.cz/);
  assert.equal(win.flashes.pop()[0], 'Převod zahájen');
  assert.equal(cmp.state.wbF.b, '', 'the transfer code is cleared from the form');
});

it('does not offer the transfer-in screen while transfer is unbilled: no chip, and a stored mode falls back', async () => {
  const { build, M, sel } = await setup();
  assert.equal(M.flags.transferIn, false);
  M.state.mode[sel.id + ':reg'] = 'transfer';
  const p = await build('reg');
  assert.equal(labels(p.chips).some((l) => /Převod|Transfer/.test(l)), false);
  assert.equal(p.form, undefined, 'no transfer form');
  assert.ok(p.rows.length >= 6, 'the registration facts are shown instead');
});
it('does not send a transfer the customer did not confirm (flag on)', async () => {
  const { build, cmp, win, M } = await setup();
  M.flags.transferIn = true;
  win.ctx.confirmAnswer = false;
  let p = await build('reg'); p.chips[2].on(); p = await build('reg');
  cmp.state.wbF = { a: 'firma.cz', b: 'AUTH-1234' }; p.form.on(); await settle();
  assert.equal(win.calls.filter((c) => c[0] === 'POST').length, 0);
});

it('lists the zone versions, marks the current one and rolls back through the API', async () => {
  const { build, win, cmp } = await setup({ sel: { dns_provider: 'onhost' }, routes: { 'POST /dns/zones/zon_1/rollback': { data: { version: 4 } } } });
  await build('dns', 2);
  let p = await build('dns');
  eq(labels(p.chips), ['Záznamy', 'Verze a návrat', 'Moje zóny']);
  p.chips[1].on();
  p = await build('dns', 2);
  assert.equal(p.title, 'Verze zóny · firma.cz');
  assert.equal(p.state, '3 verze');
  eq(p.rows.map((r) => r.cells[0].t), ['v3', 'v2', 'v1']);
  assert.equal(p.rows[0].note, 'aktuální verze');
  assert.equal(p.rows[0].actions.length, 0);
  p.rows[1].actions[0].on(); await settle();
  eq(lastPost(win.calls, '/dns/zones/zon_1/rollback')[2], { version: 2 });
  assert.match(win.ctx.confirms.pop(), /verzi 2/);
  assert.equal(win.flashes.pop()[0], 'Zóna vrácena na verzi 2');
});

it('refuses a rollback while changes wait for publication', async () => {
  const pending = { data: { id: 'zon_1', name: 'firma.cz', version: 3, serial: 3, records: [], changes: [{ id: 'chg_1', op: 'add', record: { name: 'x', type: 'A', content: '1.1.1.1' } }], dnssec: false, ds: [] } };
  const { build, win } = await setup({ sel: { dns_provider: 'onhost' }, routes: { 'GET /domains/firma.cz/zone': pending } });
  await build('dns', 2);
  let p = await build('dns'); p.chips[1].on(); p = await build('dns', 2);
  p.rows[1].actions[0].on(); await settle();
  assert.equal(win.flashes.pop()[0], 'Zóna má nepublikované změny');
  assert.equal(win.calls.filter((c) => c[0] === 'POST').length, 0);
});

it('offers the zone export (a download, not a call) on the records and versions panels', async () => {
  const { build, win } = await setup({ sel: { dns_provider: 'onhost' } });
  const opened = [];
  win.ctx.open = (u, t, f) => opened.push([u, t, f]);
  await build('dns', 2);
  const p = await build('dns');
  const exp = p.extra.find((x) => x.label.startsWith('Stáhnout zónu'));
  exp.on();
  eq(opened[0], ['/v1/dns/zones/zon_1/export', '_blank', 'noopener']);
});

it('lists the organization zones for a domain with external DNS and creates a standalone zone', async () => {
  const { build, cmp, win } = await setup({ routes: { 'POST /dns/zones': { data: { zone_id: 'zon_3', name: 'nova-zona.cz', nameservers: ['ns1.onhost.cz', 'ns2.onhost.cz'] } } } });
  await build('dns', 2);
  let p = await build('dns');
  assert.equal(p.title, 'Moje DNS zóny');
  assert.equal(p.state, '2 zóny');
  eq(p.rows.map((r) => r.note), ['zóna domény', 'samostatná zóna']);
  cmp.state.wbF = { a: 'špatný název' }; p.form.on(); assert.equal(win.flashes.pop()[0], 'Zadejte název zóny');
  cmp.state.wbF = { a: 'Nova-Zona.CZ.' }; p.form.on(); await settle();
  eq(lastPost(win.calls, '/dns/zones')[2], { name: 'nova-zona.cz' });
  const [t, b] = win.flashes.pop();
  assert.equal(t, 'Zóna založena');
  assert.match(b, /ns1\.onhost\.cz, ns2\.onhost\.cz/);
});

it('deletes a standalone zone after confirmation, with a fixed reason, and shows the in-use refusal in Czech', async () => {
  const inUse = Object.assign(new Error('Zone firma.cz serves the domain'), { status: 409, body: { error: 'dns_zone_in_use', domain: 'firma.cz' } });
  const { build, win } = await setup({ routes: { 'DELETE /dns/zones/zon_2': { data: { deleted: true } }, 'DELETE /dns/zones/zon_1': inUse } });
  await build('dns', 2);
  let p = await build('dns');
  p.rows[1].actions[2].on(); await settle();
  const del = win.calls.filter((c) => c[0] === 'DELETE').pop();
  assert.equal(del[1], '/dns/zones/zon_2?reason=zru%C5%A1eno%20z%C3%A1kazn%C3%ADkem%20v%20panelu');
  assert.equal(win.flashes.pop()[0], 'Zóna smazána');
  p.rows[0].actions[2].on(); await settle();
  const [title, body] = win.flashes.pop();
  assert.equal(title, 'Akce neproběhla');
  assert.match(body, /slouží doméně firma\.cz/);
});

it('does not delete a zone the customer did not confirm', async () => {
  const { build, win } = await setup();
  win.ctx.confirmAnswer = false;
  await build('dns', 2);
  const p = await build('dns');
  p.rows[1].actions[2].on(); await settle();
  assert.equal(win.calls.filter((c) => c[0] === 'DELETE').length, 0);
});

it('opens a standalone zone for editing and returns to the domain', async () => {
  const { build, win } = await setup({ sel: { dns_provider: 'onhost' } });
  await build('dns', 2);
  let p = await build('dns'); p.chips[2].on();
  p = await build('dns', 2);
  p.rows[1].actions[0].on();                       // Otevřít samostatnou zónu
  p = await build('dns', 3);
  assert.match(p.title, /DNS záznamy · samostatna\.cz/);
  assert.match(p.note, /samostatnou zónu, která nepatří k doméně firma\.cz/);
  assert.equal(p.chips[0].label, '← zpět na firma.cz');
  assert.ok(win.calls.some((c) => c[0] === 'GET' && c[1] === '/dns/zones/samostatna.cz'));
  p.chips[0].on();
  p = await build('dns', 2);
  assert.match(p.title, /DNS záznamy · firma\.cz/);
});

it('writes to the opened zone, not to the domain\'s own', async () => {
  const { build, win, cmp } = await setup({ sel: { dns_provider: 'onhost' }, routes: { 'POST /dns/zones/zon_2/changes': { data: { id: 'chg_9' } } } });
  await build('dns', 2);
  let p = await build('dns'); p.chips[2].on();
  p = await build('dns', 2); p.rows[1].actions[0].on();
  p = await build('dns', 3);
  cmp.state.wbF = { a: 'www', b: 'A', c: '203.0.113.9' };
  p.form.on(); await settle();
  assert.ok(lastPost(win.calls, '/dns/zones/zon_2/changes'), 'staged in zon_2');
  assert.equal(win.calls.filter((c) => c[0] === 'POST' && c[1].startsWith('/dns/zones/zon_1')).length, 0);
});

it('speaks English when the panel does, with English plurals', async () => {
  const { build, cmp } = await setup({ sel: { dns_provider: 'onhost' } });
  cmp.state.lang = 'en';
  await build('dns', 2);
  let p = await build('dns');
  eq(labels(p.chips), ['Records', 'Versions and rollback', 'My zones']);
  p.chips[2].on();
  p = await build('dns', 2);
  assert.equal(p.state, '2 zones');
});

it('maps API error codes to Czech and falls back to a generic Czech sentence', async () => {
  const { M, cmp } = await setup();
  assert.match(M.errorText(cmp, { status: 409, body: { error: 'dns_rollback_noop' } }), /odpovídá této verzi/);
  assert.match(M.errorText(cmp, { status: 403, body: { error: 'step_up_required' } }), /druhé ověření/);
  assert.equal(M.errorText(cmp, { cancelled: true, message: 'Ověření zrušeno.' }), 'Ověření zrušeno.');
  assert.match(M.errorText(cmp, { status: 500, message: 'SQLSTATE[HY000] boom' }), /Na naší straně/);
  assert.doesNotMatch(M.errorText(cmp, { status: 418, message: 'I am a teapot' }), /teapot/);
  assert.match(M.errorText(cmp, { status: 422, body: { errors: { email: ['E-mail není platný.'] } } }), /E-mail není platný/);
});

it('Czech plurals follow the 1 / 2–4 / 5+ rule', async () => {
  const { M, cmp } = await setup();
  const c = (n, k) => M.count(cmp, n, k);
  eq([0, 1, 2, 4, 5, 11].map((n) => c(n, 'record')), ['0 záznamů', '1 záznam', '2 záznamy', '4 záznamy', '5 záznamů', '11 záznamů']);
  eq([1, 3, 6].map((n) => c(n, 'version')), ['1 verze', '3 verze', '6 verzí']);
  eq([1, 2, 5].map((n) => c(n, 'zone')), ['1 zóna', '2 zóny', '5 zón']);
  eq([1, 2, 5].map((n) => c(n, 'domain')), ['1 doména', '2 domény', '5 domén']);
});

it('loads the domains module beside the workbench when the renderer did not inject it, then rebuilds', async () => {
  const { build, win, cmp } = await setup({ noModule: true });
  const p0 = await build('reg');
  assert.equal(p0.chips, undefined, 'the core panel is shown until the module arrives');
  assert.equal(win.appended.length, 1);
  assert.equal(win.appended[0].src, '/surfaces/api/onhost-panel-domains.api.js?v=1');
  await build('reg');
  assert.equal(win.appended.length, 1, 'requested once');
  vm.runInContext(read('onhost-panel-domains.api.js'), win.ctx);
  win.appended[0].onload();
  const p1 = await build('reg');
  assert.equal(p1.chips.length, 2);
});

it('onhost-domains.api.js stages every change with the field names the API takes, then commits; a failure discards what was staged', async () => {
  const win = makeWindow({
    'POST /dns/zones/firma.cz/changes': (b) => ({ data: { id: 'chg_' + b.change } }), 'POST /dns/zones/firma.cz/commit': { data: { version: 4 } }, 'POST /dns/zones/firma.cz/discard': { data: { discarded: 2 } },
    'GET /domains/firma.cz/zone': { data: { records: [], version: 4 } }, 'GET *': { data: [] },
  });
  win.ctx.OnhostApi.user = () => ({ name: 'x' }); win.ctx.OnhostApi.staff = () => false;
  load(win, 'onhost-domains.api.js');
  const D = win.ctx.window.OnhostDomains;
  D.addRow('firma.cz', { host: 'www', type: 'A', data: '203.0.113.9', ttl: '300' });
  D.updateRow('firma.cz', 'rec_7', { host: '', type: 'MX', data: 'mail.example.cz', ttl: '3600', prio: 10 });
  D.deleteRow('firma.cz', 'rec_8');
  await D.commit('firma.cz', 'me', 'quarterly');
  const changes = win.calls.filter((c) => c[0] === 'POST' && c[1].endsWith('/changes')).map((c) => c[2]);
  eq(changes[0], { change: 'add', reason: 'quarterly', record: { name: 'www', type: 'A', content: '203.0.113.9', ttl: 300 } });
  eq(changes[1], { change: 'update', reason: 'quarterly', record_id: 'rec_7', record: { name: '@', type: 'MX', content: 'mail.example.cz', ttl: 3600, prio: 10 } });
  eq(changes[2], { change: 'delete', reason: 'quarterly', record_id: 'rec_8' });
  eq(lastPost(win.calls, '/dns/zones/firma.cz/commit')[2], { reason: 'quarterly' });
  assert.equal(D.pending('firma.cz').length, 0);

  // a refused commit keeps the local list and clears what this call staged at the server
  const win2 = makeWindow({
    'POST /dns/zones/firma.cz/changes': { data: { id: 'c' } }, 'POST /dns/zones/firma.cz/commit': Object.assign(new Error('boom'), { status: 502 }), 'POST /dns/zones/firma.cz/discard': { data: { discarded: 1 } }, 'GET *': { data: [] },
  });
  win2.ctx.OnhostApi.user = () => null; win2.ctx.OnhostApi.staff = () => false;
  load(win2, 'onhost-domains.api.js');
  const D2 = win2.ctx.window.OnhostDomains;
  D2.addRow('firma.cz', { host: 'a', type: 'A', data: '203.0.113.1', ttl: '300' });
  await assert.rejects(D2.commit('firma.cz', 'me', 'x'));
  assert.equal(D2.pending('firma.cz').length, 1);
  assert.ok(win2.calls.some((c) => c[0] === 'POST' && c[1] === '/dns/zones/firma.cz/discard'));
});

it('onhost-domains.api.js rolls back with the integer version the API takes, on the route that exists', async () => {
  const win = makeWindow({ 'POST /dns/zones/firma.cz/rollback': { data: { version: 5 } }, 'GET /domains/firma.cz/zone': { data: { records: [] } }, 'GET *': { data: [] } });
  win.ctx.OnhostApi.user = () => null; win.ctx.OnhostApi.staff = () => false;
  load(win, 'onhost-domains.api.js');
  await win.ctx.window.OnhostDomains.rollback('firma.cz', '2');
  eq(lastPost(win.calls, '/dns/zones/firma.cz/rollback')[2], { version: 2 });
  assert.equal(win.calls.some((c) => String(c[1]).includes('/zone/rollback')), false, 'the route that never existed is gone');
});

let failed = 0;
for (const [name, fn] of cases) {
  try { await fn(); console.log('ok   ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n     ' + String(e && e.stack || e).split('\n').slice(0, 6).join('\n     ')); }
}
console.log(`${cases.length - failed}/${cases.length} passed`);
process.exit(failed ? 1 : 0);
