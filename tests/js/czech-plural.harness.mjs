/* Harness for TASK-0070 (audit 2026-10 P3): the session bridge's shared plural helper (window.OnhostI18n) — 1 služba, 2–4 služby,
 * 5 služeb, a decimal takes the second form; English one/other — and the page language it falls back to (<html lang>).
 *   node tests/js/czech-plural.harness.mjs
 * Prints JSON {failures: [...]}; exit code 0 = all checks passed (run by tests/Feature/Http/SurfaceLocaleA11yTest.php). */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const file = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api', 'onhost-session-bridge.js');
const html = { lang: 'cs' };
const ctx = { console, JSON, Object, Array, String, Number, Math, RegExp, Date, isNaN, Promise, setTimeout, clearTimeout };
ctx.window = ctx;
ctx.ONHOST = { demo: false, user: null, surface: 'public' };
ctx.location = { hash: '', pathname: '/', search: '', href: '/' };
ctx.history = { replaceState() {} };
ctx.localStorage = { getItem() { return null; }, setItem() {}, removeItem() {} };
ctx.document = { documentElement: html, readyState: 'complete', cookie: '', addEventListener() {}, querySelector() { return null; }, querySelectorAll() { return []; }, createElement() { return { style: {}, setAttribute() {}, appendChild() {} }; }, head: { appendChild() {} }, body: { appendChild() {} } };
ctx.addEventListener = () => {};
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(file, 'utf8'), ctx, { filename: 'onhost-session-bridge.js' });

const failures = [];
const I = ctx.OnhostI18n;
if (!I || typeof I.plural !== 'function') {
  failures.push('window.OnhostI18n.plural is missing');
} else {
  const cs = ['služba', 'služby', 'služeb'];
  const cases = [[0, 'služeb'], [1, 'služba'], [2, 'služby'], [4, 'služby'], [5, 'služeb'], [11, 'služeb'], [22, 'služeb'], [1.5, 'služby'], [-1, 'služba']];
  for (const [n, want] of cases) { const got = I.plural(n, cs, 'cs'); if (got !== want) failures.push(`cs ${n}: ${got} !== ${want}`); }
  for (const [n, want] of [[1, 'service'], [0, 'services'], [3, 'services']]) { const got = I.plural(n, ['service', 'services'], 'en'); if (got !== want) failures.push(`en ${n}: ${got} !== ${want}`); }
  if (I.count(3, cs, 'cs') !== '3 služby') failures.push(`count: ${I.count(3, cs, 'cs')}`);
  html.lang = 'en';
  if (I.plural(2, ['day', 'days']) !== 'days') failures.push('the page language (en) is not the default');
  html.lang = 'cs';
  if (I.plural(2, ['den', 'dny', 'dní']) !== 'dny') failures.push('the page language (cs) is not the default');
}
console.log(JSON.stringify({ failures }));
process.exit(failures.length ? 1 : 0);
