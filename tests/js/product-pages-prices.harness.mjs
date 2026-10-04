/* Harness for the public product pages (TASK-0064, owner decision R14, audit 2026-10 P1-6): runs the six prototype page modules
 * (apps/surfaces/onhost-svc-*.js) through the real api/onhost-svc-pages.api.js against the ONHOST_DATA the server generated, and checks
 * every slug in both languages: a price appears only where a catalogue SKU stands behind it; a page without one says
 * "Připravujeme" and has no order button, no hourly rates, no configurator and no amounts in its copy. No browser, no network.
 *   node tests/js/product-pages-prices.harness.mjs <path to the generated onhost-data.js>
 * Exit code 0 = all checks passed; the summary is printed as JSON (run by tests/Feature/Http/PriceSourceSeamTest.php). */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath, pathToFileURL } from 'node:url';

const surfaces = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces');
const dataFile = process.argv[2];
if (!dataFile || !fs.existsSync(dataFile)) { console.error('usage: node product-pages-prices.harness.mjs <onhost-data.js>'); process.exit(2); }

const ctx = { console, JSON, Object, Array, String, Number, Math, RegExp, Date, isNaN, Infinity };
ctx.window = ctx;
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(dataFile, 'utf8'), ctx, { filename: 'onhost-data.js' });
vm.runInContext(fs.readFileSync(path.join(surfaces, 'api', 'onhost-svc-pages.api.js'), 'utf8'), ctx, { filename: 'onhost-svc-pages.api.js' });
const D = ctx.ONHOST_DATA, S = ctx.OnhostSvcPages;

const modules = [['web', 'webPages'], ['compute', 'computePages'], ['other', 'otherPages'], ['more', 'morePages'], ['dev', 'devPages'], ['corp', 'corpPages']];
const fns = [];
for (const [file, name] of modules) fns.push((await import(pathToFileURL(path.join(surfaces, `onhost-svc-${file}.js`)).href))[name]);

// pages the audit named (P1-6) and the decision (R14) covers: no catalogue product behind them in the seeded catalogue
const MUST_BE_COMING = ['gpu', 'inference', 'vectordb', 'colocation', 'git', 'ci', 'migration', 'managed', 'storage', 'sol-dev', 'sol-ai', 'sol-energy', 'sol-agency', 'sol-eshop', 'sol-gaming', 'sol-startup', 'enterprise'];
const AMOUNT = /\d[\d\s .,]*\s?(Kč|CZK|EUR|€|zł|PLN|USD|GBP|£)|(€|\$|£)\s?\d/;
const failures = [];
const fail = (slug, cs, msg) => failures.push(`${cs ? 'cs' : 'en'} ${slug}: ${msg}`);
const amounts = (v, strict, where, out) => {
  if (typeof v === 'string') { const m = v.match(AMOUNT); if (m && (strict || /[1-9]/.test(m[0]))) out.push(`${where} = ${v.slice(0, 80)}`); }
  else if (Array.isArray(v)) v.forEach((x, i) => amounts(x, strict, `${where}[${i}]`, out));
  else if (v && typeof v === 'object') Object.keys(v).forEach((k) => amounts(v[k], strict, `${where}.${k}`, out));
  return out;
};
const summary = { slugs: 0, priced: 0, coming: [], catalogue: [] };

for (const cs of [true, false]) {
  const ov = D.pages(cs) || {};
  const prices = new Map(); // product_key|plan_key → price the catalogue sells the plan at
  Object.keys(ov).forEach((slug) => (ov[slug].plans || []).forEach((p) => { if (p.sku) prices.set(`${p.sku.product_key}|${p.sku.plan_key}`, Number(p.price)); }));
  for (const fn of fns) {
    const raw = fn(cs);
    const pages = S.wrap(fn)(cs);
    for (const slug of Object.keys(pages)) {
      const page = pages[slug], o = ov[slug] || null;
      if (cs) summary.slugs++;
      if (o && o.withdrawn) continue;
      const plans = page.plans || [];
      const wasPriced = (raw[slug].plans || []).some((p) => Number(p.price) > 0);
      // 1. a price only with a catalogue SKU, at the catalogue's price
      for (const p of plans) {
        if (!(Number(p.price) > 0)) continue;
        if (cs) summary.priced++;
        if (!p.sku) { fail(slug, cs, `plan "${p.name}" shows ${p.price} without a catalogue SKU`); continue; }
        const listed = prices.get(`${p.sku.product_key}|${p.sku.plan_key}`);
        if (listed === undefined || Math.abs(listed - Number(p.price)) > 0.001) fail(slug, cs, `plan "${p.name}" shows ${p.price}, the catalogue sells ${p.sku.product_key}/${p.sku.plan_key} at ${listed}`);
      }
      const product = !!o || wasPriced;
      if (!product) continue;
      const sellsFromCatalogue = plans.some((p) => p.sku);
      // 2. nothing prices a page outside the catalogue: hourly rates, the prototype configurator, the ROI calculator without a base
      if (page.config) fail(slug, cs, 'the prototype configurator (its own unit prices, add to cart) is still on');
      if (page.hourly && !(o && o.hourly)) fail(slug, cs, 'hourly rates that are not the catalogue\'s');
      if (!sellsFromCatalogue && page.roi) fail(slug, cs, 'the ROI calculator runs on a made-up base price');
      // 3. no amount in the page's copy (a page not on offer: not even "0 Kč")
      const strict = !sellsFromCatalogue && !(o && o.plans && o.plans.length);
      const copy = { kicker: page.kicker, title: page.title, lead: page.lead, kpis: page.kpis, chips: page.chips, faq: page.faq, sla: page.sla, bench: page.bench, cases: page.cases, feats: page.feats, panels: page.panels, cmp: (o && o.cmp && o.cmp.rows && o.cmp.rows.length) ? null : page.cmp };
      amounts(copy, strict, 'page', []).forEach((hit) => fail(slug, cs, `amount outside the catalogue: ${hit}`));
      // 4. a page with no catalogue product says "Připravujeme" and offers no order button
      if (MUST_BE_COMING.includes(slug)) {
        const label = cs ? 'Připravujeme' : 'Coming soon';
        if (sellsFromCatalogue) fail(slug, cs, 'expected no catalogue plan');
        if (!plans.length || !plans.some((p) => p.priceLabel === label)) fail(slug, cs, `no plan says "${label}"`);
        plans.forEach((p) => {
          if (Number(p.price) > 0 || p.sku || p.goto) fail(slug, cs, `plan "${p.name}" still orders (price ${p.price}, sku ${!!p.sku}, goto ${p.goto})`);
          if (/^(Objednat|Order)/i.test(String(p.ctaLabel || ''))) fail(slug, cs, `plan "${p.name}" is labelled as an order: ${p.ctaLabel}`);
        });
        if (cs) summary.coming.push(slug);
      } else if (cs && sellsFromCatalogue) summary.catalogue.push(slug);
    }
  }
}

console.log(JSON.stringify({ ok: failures.length === 0, failures, summary }, null, 1));
process.exit(failures.length ? 1 : 0);
