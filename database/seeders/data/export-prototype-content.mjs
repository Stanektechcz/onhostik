// Exports the prototype's marketing/content data (onhost-content.js, onhost-data.js) to JSON for ContentSeeder.
// Run from the repository root:  node database/seeders/data/export-prototype-content.mjs
// The prototype files are the source of truth for public copy; the seeder never invents content.
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const proto = path.resolve(here, '../../../..');
const out = {};

// onhost-content.js is an ES module exporting contentPages(cs)
const content = await import(pathToFileURL(path.join(proto, 'onhost-content.js')).href);
for (const cs of [true, false]) {
    const { blog, kb } = content.contentPages(cs);
    out[cs ? 'cs' : 'en'] = { blog, kb };
}

// onhost-data.js is a classic script assigning window.ONHOST_DATA (or a const); evaluate it in a sandbox
const dataSrc = readFileSync(path.join(proto, 'onhost-data.js'), 'utf8');
const sandbox = { window: {}, console, module: { exports: {} }, exports: {} };
vm.createContext(sandbox);
vm.runInContext(dataSrc + '\n;(typeof ONHOST_DATA !== "undefined" ? (window.ONHOST_DATA = ONHOST_DATA) : 0);', sandbox);
const data = sandbox.window.ONHOST_DATA || sandbox.module.exports.default || sandbox.module.exports;
if (!data || typeof data.locations !== 'function') {
    throw new Error('onhost-data.js did not expose ONHOST_DATA with locations()/changelog()');
}
for (const cs of [true, false]) {
    const key = cs ? 'cs' : 'en';
    out[key].locations = data.locations(cs);
    out[key].changelog = data.changelog(cs);
    out[key].status = data.status(cs);
}

const target = path.join(here, 'prototype-content.json');
writeFileSync(target, JSON.stringify(out, null, 1));
console.log('wrote', target, 'blog', out.cs.blog.length, 'kb', out.cs.kb.length, 'locations', out.cs.locations.length, 'changelog', out.cs.changelog.length);
