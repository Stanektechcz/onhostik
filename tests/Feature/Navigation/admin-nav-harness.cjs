/* Runs the staff console modules (onhost-store.api.js, onhost-admin.api.js) in a bare window with a scripted API, for
 * StaffNavigationScriptTest. Prints one JSON object with what the modules did. Usage: node admin-nav-harness.cjs <repo root> */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = process.argv[2];
const read = (f) => fs.readFileSync(path.join(root, 'apps/surfaces/api', f), 'utf8');
const nav = JSON.parse(process.argv[3] || '[]');
const calls = [];
const flashes = [];

function reject403(p) { const e = new Error('Missing permission'); e.status = 403; return Promise.reject(e); }
function body(p) {
  if (p.indexOf('/staff/tickets') === 0) return { data: [{ id: 't1', number: 'TK-1', subject: 'Web nejde', ui: 'otevreny', created_at: '2026-10-01T10:00:00Z', messages: [{ from: 'zakaznik', text: 'Pomoc', visibility: 'public' }, { from: 'podpora', text: 'Kolega se dívá', visibility: 'internal' }] }] };
  return { data: [] };
}
const api = {
  staff: () => true,
  user: () => ({ id: 'u1', name: 'Pavla', staff: true, nav: nav }),
  key: () => 'k',
  get: (p) => { calls.push(['GET', p]); return /\/staff\/customers|\/staff\/dunning|\/staff\/withdrawals/.test(p) ? reject403(p) : Promise.resolve(body(p)); },
  post: (p, b) => { calls.push(['POST', p, b]); return Promise.resolve({ data: {} }); },
  put: (p, b) => { calls.push(['PUT', p, b]); return Promise.resolve({ data: {} }); },
};
const window = { OnhostApi: api, ONHOST: { user: { id: 'u1', name: 'Pavla', staff: true, nav: nav } }, location: { href: '' }, confirm: () => true, alert: () => {}, prompt: () => null };
const ctx = vm.createContext({ window, console, Promise, Date, JSON, Math, setTimeout, Intl, RegExp, String, Object, Array, Number, isNaN, parseFloat, encodeURIComponent });
vm.runInContext(read('onhost-store.api.js'), ctx);
vm.runInContext(read('onhost-admin.api.js'), ctx);

const cmp = { state: { lang: 'cs', view: 'customers' }, forceUpdate() {}, setState(s) { Object.assign(this.state, s); }, flash(a, b) { flashes.push([a, b]); } };
const A = window.OnhostAdmin;
const S = window.OnhostStore;

setTimeout(() => {
  A.customers(cmp); // starts the read
  A.table(cmp, null, 'invoices');
  setTimeout(() => {
    const t = S.tickets()[0];
    if (t) {
      A.ticketActions(cmp, { id: t.id }).filter((a) => /Interní poznámka/.test(a[0]))[0][2](); // switch the internal note on
      A.reply(cmp, { id: t.id }, 'Jen pro nás');
      S.setTicketState(t.id, 'vyreseny', 'podpora');
    }
    A.documents(cmp);
    const out = {
      role: A.role(),
      allows: { dash: A.allows('dash'), queue: A.allows('queue'), customers: A.allows('customers'), invoices: A.allows('invoices'), fleet: A.allows('fleet') },
      label: A.label(cmp, 'invoices'),
      customers: A.customers(cmp),
      invoices: A.table(cmp, null, 'invoices'),
      thread: t ? A.thread({ id: t.id }) : null,
      ticketsDenied: S.denied('tickets'),
      ordersDenied: S.denied('orders'),
      view: cmp.state.view,
      calls: calls,
      flashes: flashes,
    };
    process.stdout.write(JSON.stringify(out));
  }, 30);
}, 30);
