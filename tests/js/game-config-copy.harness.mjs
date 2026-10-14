/* Harness for N1 (phase N): the game configurator's summary says "/ měsíc s DPH" and "bez DPH …" only to the customers of a VAT
 * payer (a non-payer's price is final: ONHOST_DATA.vatPayer() === false, vat() 0), and its perks name no location ("v Praze") and
 * no support hours ("24/7") that no document in force states, and (O1) no "Anti-DDoS included" nothing documents. The module runs in a sandbox with a one-game catalogue; no browser.
 *   node tests/js/game-config-copy.harness.mjs
 * Prints JSON {failures: [...], seen: {...}}; exit code 0 = all checks passed (run by tests/Feature/Content/ServicePageCopyClaimsTest.php). */
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const api = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'apps', 'surfaces', 'api');
const failures = [];
const seen = {};

const catalogue = {
  product_key: 'gamehosting', plan_key: 'custom', base_price: 100,
  categories: [{ key: 'sandbox', label: 'Sandbox' }],
  groups: [{ key: 'minecraft-java', label: 'Minecraft', note: '', category: 'sandbox', eggs: ['paper'], from: 100 }],
  games: [{ key: 'paper', label: 'Paper', min: { ram_gb: 2 } }],
  options: [{ key: 'ram_gb', label: 'RAM', unit: 'GB', min: 1, max: 32, step: 1, default: 2, price: 50 }]
};

function sandbox(data) {
  const ctx = { console, JSON, Object, Array, String, Number, Math, RegExp, Date, isNaN, Infinity, Promise, setTimeout, clearTimeout };
  ctx.window = ctx;
  ctx.document = { documentElement: { lang: 'cs' }, getElementById() { return null; } };
  ctx.ONHOST_DATA = data;
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(path.join(api, 'onhost-game-config.api.js'), 'utf8'), ctx, { filename: 'onhost-game-config.api.js' });
  return ctx;
}

function view(data, cs) {
  const ctx = sandbox(data);
  const cmp = { state: { gameCfg: { group: 'minecraft-java', egg: 'paper' } }, setState() {}, czk: (n) => Math.round(n) + ' Kč', mny: (n) => Math.round(n) + ' Kč' };
  return ctx.OnhostGameConfig.view(cmp, cs);
}

const cases = {
  nonPayer: { vat: () => 0, vatPayer: () => false, gameConfig: () => catalogue },
  payer: { vat: () => 0.21, vatPayer: () => true, gameConfig: () => catalogue }
};

for (const [name, data] of Object.entries(cases)) {
  const cs = view(data, true);
  const en = view(data, false);
  seen[`${name}.unit`] = cs.unit;
  seen[`${name}.net`] = cs.net;
  if (!cs.has || !cs.hasGame) failures.push(`${name}: the configurator did not open the game`);
  for (const out of [cs, en]) {
    const perks = (out.perks || []).join(' | ');
    if (/Praze|Prague|24\/7/.test(perks)) failures.push(`${name}: perks still claim ${perks}`);
    // O1: no node configuration, runbook or upstream contract documents a DDoS mitigation, so it is no perk "in the price"
    if (/DDoS/i.test(perks)) failures.push(`${name}: perks still claim ${perks}`);
  }
  if (name === 'nonPayer') {
    if (cs.unit !== '/ měsíc' || en.unit !== '/ month') failures.push(`non-payer: unit ${cs.unit} / ${en.unit}`);
    if (cs.net !== '' || en.net !== '') failures.push(`non-payer: a price "bez DPH" is shown: ${cs.net} / ${en.net}`);
  } else {
    if (cs.unit !== '/ měsíc s DPH' || en.unit !== '/ month incl. VAT') failures.push(`payer: unit ${cs.unit} / ${en.unit}`);
    if (!/^bez DPH /.test(cs.net) || !/^excl\. VAT /.test(en.net)) failures.push(`payer: net ${cs.net} / ${en.net}`);
  }
}

console.log(JSON.stringify({ failures, seen }));
process.exit(failures.length ? 1 : 0);
