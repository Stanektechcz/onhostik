/* Harness for K7-7 (L5): the staff console reads only what the server's navigation lists for the person.
 * Loads the real onhost-session-bridge.js (OnhostApi.reads), onhost-domains.api.js, onhost-integrations.api.js and
 * onhost-admin.api.js in a vm with the boot navigation of a role (written by tests/Feature/Staff/StaffConsoleReadsTest.php),
 * records every GET the modules make while they hydrate, and checks each one against the navigation.
 *   node tests/js/staff-console-reads.harness.mjs <navs.json>   (exit code 0 = all checks passed) */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api');
const read = (f) => fs.readFileSync(path.join(root, f), 'utf8');
const tick = () => new Promise((r) => setImmediate(r));
const settle = async () => { for (let i = 0; i < 12; i++) await tick(); };

/* an independent reading of the navigation (not the bridge's code): a GET is listed when an item's api has it */
const norm = (p) => String(p || '').split('?')[0].replace(/^\/?(v1\/)?/, '').replace(/\/+$/, '');
const listed = (nav, p) => nav.some((it) => (it.api || []).some((a) => (a.method || 'GET') === 'GET'
  && new RegExp('^' + norm(a.path).split('/').map((s) => (/^\{.+\}$/.test(s) ? '[^/]+' : s.replace(/[.*+?^$()|[\]\\]/g, '\\$&'))).join('/') + '$').test(norm(p))));

function boot(nav) {
  const gets = [];
  const noop = () => {};
  const el = () => ({ style: {}, appendChild: noop, addEventListener: noop, setAttribute: noop, remove: noop, querySelector: () => null });
  const ctx = { console, Promise, JSON, Date, Object, Array, String, Number, RegExp, Math, Error, parseInt, encodeURIComponent, decodeURIComponent, Uint32Array };
  ctx.window = ctx;
  ctx.setTimeout = () => 0; ctx.clearTimeout = noop; ctx.setInterval = () => 0; ctx.clearInterval = noop;
  ctx.addEventListener = noop;
  ctx.location = { hash: '', pathname: '/sprava', search: '', href: 'http://localhost/sprava', reload: noop };
  ctx.history = { replaceState: noop, pushState: noop };
  ctx.localStorage = { getItem: () => null, setItem: noop, removeItem: noop };
  ctx.sessionStorage = ctx.localStorage;
  ctx.navigator = { userAgent: 'harness' };
  ctx.crypto = { getRandomValues: (b) => b };
  ctx.document = { documentElement: { lang: 'cs' }, cookie: '', readyState: 'complete', addEventListener: noop, removeEventListener: noop, querySelector: () => null, querySelectorAll: () => [], createElement: el, head: el(), body: el(), currentScript: { src: '/surfaces/api/x.js?v=1' } };
  ctx.fetch = (url) => { gets.push(['FETCH', String(url)]); return Promise.reject(Object.assign(new Error('offline'), { status: 0 })); };
  ctx.ONHOST = { apiBase: '/v1', user: { id: 'u1', staff: true, role: 'fakturace', nav, mfa: true } };
  vm.createContext(ctx);
  vm.runInContext(read('onhost-session-bridge.js'), ctx, { filename: 'onhost-session-bridge.js' });
  const A = ctx.OnhostApi;
  assert.equal(typeof A.reads, 'function', 'OnhostApi.reads exists');
  /* record the reads; answer every one with an empty list (the shapes the modules expect) */
  const answer = (p) => { gets.push(['GET', p]); return Promise.resolve({ data: [] }); };
  A.get = answer;
  A.all = answer;
  for (const f of ['onhost-domains.api.js', 'onhost-integrations.api.js', 'onhost-admin.api.js']) vm.runInContext(read(f), ctx, { filename: f });
  return { ctx, gets };
}

const navs = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const checks = [];
const check = (name, fn) => checks.push([name, fn]);
const CONSOLE_READS = ['/staff/integrations', '/staff/provisioning/jobs', '/staff/resource-mappings', '/staff/capacity', '/staff/domains'];

for (const [role, nav] of Object.entries(navs)) {
  check(`${role}: every read the console makes on load is listed by the navigation`, async () => {
    const { ctx, gets } = boot(nav);
    await ctx.OnhostIntegrations.ready();
    await ctx.OnhostDomains.ready();
    await settle();
    const reads = gets.filter((g) => g[0] === 'GET').map((g) => g[1]);
    const unlisted = reads.filter((p) => !listed(nav, p));
    assert.deepEqual(unlisted, [], `${role} read outside the navigation: ${unlisted.join(', ')}`);
    assert.deepEqual(gets.filter((g) => g[0] === 'FETCH'), [], 'no read bypassed OnhostApi');
    return reads;
  });
  check(`${role}: the bridge's reads() agrees with the navigation for the console reads`, async () => {
    const { ctx } = boot(nav);
    for (const p of CONSOLE_READS) assert.equal(ctx.OnhostApi.reads(p), listed(nav, p), p);
    assert.equal(ctx.OnhostApi.reads('/staff/provisioning/jobs?limit=200'), listed(nav, '/staff/provisioning/jobs'), 'query string ignored');
    assert.equal(ctx.OnhostApi.reads('/v1/staff/capacity'), listed(nav, '/staff/capacity'), '/v1 prefix ignored');
  });
}

check('owner: the console still reads what the navigation lists (positive control)', async () => {
  const nav = navs.platform_owner;
  const { ctx, gets } = boot(nav);
  await ctx.OnhostIntegrations.ready();
  await ctx.OnhostDomains.ready();
  await settle();
  const reads = gets.filter((g) => g[0] === 'GET').map((g) => norm(g[1]));
  for (const p of CONSOLE_READS) assert.ok(reads.includes(norm(p)), `owner reads ${p}`);
});

check('finance: the console makes none of the reads finance may not make (K7-7)', async () => {
  const nav = navs.billing_finance_admin;
  const { ctx, gets } = boot(nav);
  await ctx.OnhostIntegrations.ready();
  await ctx.OnhostDomains.ready();
  await settle();
  const reads = gets.filter((g) => g[0] === 'GET').map((g) => norm(g[1]));
  for (const p of CONSOLE_READS.filter((x) => !listed(nav, x))) assert.ok(!reads.includes(norm(p)), `finance does not read ${p}`);
  assert.ok(CONSOLE_READS.some((x) => !listed(nav, x)), 'the finance navigation leaves out at least one console read');
  assert.equal(ctx.OnhostDomains.domains().__denied, listed(nav, '/staff/domains') ? undefined : true, 'an unlisted domain list says "no access"');
});

check('a person outside the staff console (no navigation) reads as before', async () => {
  const { ctx } = boot(undefined);
  assert.equal(ctx.OnhostApi.reads('/staff/integrations'), true);
});

let passed = 0;
for (const [name, fn] of checks) {
  try { await fn(); passed++; console.log('ok   ' + name); } catch (e) { console.log('FAIL ' + name + '\n     ' + (e && e.message)); }
}
console.log(`${passed}/${checks.length} passed`);
process.exit(passed === checks.length ? 0 : 1);
