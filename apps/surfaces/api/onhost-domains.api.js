/* onhost-domains.api.js — window.OnhostDomains backed by /v1/domains and the two-phase DNS API.
 * Same interface as the prototype: domains, domain, zone, zoneMeta, pending, addRow, updateRow, deleteRow,
 * commit, discard, credit, kpis, nssets, on. Zone changes are staged locally exactly like WAPI/PowerDNS
 * staging in the control plane and published with commit() → POST /domains/{zone}/zone/commit.
 */
(function () {
  'use strict';
  if (window.OnhostDomains) return;
  var A = window.OnhostApi;
  var subs = [];
  function emit() { subs.forEach(function (f) { try { f(); } catch (e) {} }); }
  function day(iso) { if (!iso) return '—'; var d = new Date(iso); return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear(); }
  var STATUS = { ACTIVE: 'active', PENDING_REGISTRATION: 'pending', PENDING_TRANSFER: 'transfer', EXPIRING: 'expiring', EXPIRED: 'expired', REDEMPTION: 'expired', TRANSFERRED_OUT: 'gone', CANCELLED: 'gone' };

  var DOMAINS = [], zones = {}, credit = null, nssets = [];
  function mapDomain(d) {
    return { name: d.fqdn, unicode: d.unicode, tld: d.tld, owner: d.organization_name || d.organization_id || '', status: STATUS[d.state] || String(d.state || '').toLowerCase(), state: d.state,
      expires: day(d.expires_at), expDays: typeof d.days_to_expiry === 'number' ? d.days_to_expiry : 9999, autorenew: !!d.auto_renew, lock: !!d.transfer_lock, dnssec: !!d.dnssec, dns: d.dns_provider, ns: d.nameservers || [], critical: !!d.critical, id: d.id };
  }
  function z(name) {
    if (!zones[name]) zones[name] = { rows: [], pending: [], serial: null, committedAt: null, loaded: false };
    return zones[name];
  }
  function loadZone(name) {
    return A.get('/domains/' + encodeURIComponent(name) + '/zone').then(function (r) {
      var d = r.data || r; var Z = z(name);
      Z.rows = (d.records || d.rows || []).map(function (x) { return { id: x.id, type: x.type, host: x.name === '@' ? '' : x.name, data: x.content, ttl: x.ttl, prio: x.priority }; });
      Z.serial = d.serial || (d.version && d.version.serial) || null; Z.committedAt = d.committed_at || null; Z.loaded = true; Z.dnssec = d.dnssec || null;
      emit(); return Z;
    }).catch(function () { return z(name); });
  }
  var hydrated = null;
  function hydrate() {
    hydrated = Promise.all([
      A.get(A.staff() ? '/staff/domains?limit=500' : '/domains?limit=500').then(function (r) { DOMAINS = (r.data || []).map(mapDomain); }).catch(function () { DOMAINS = []; }),
      A.staff() ? A.get('/staff/integrations').then(function (r) { var w = (r.data || []).filter(function (i) { return i.provider === 'wedos'; })[0]; credit = w && w.health ? w.health.credit || null : null; }).catch(function () {}) : Promise.resolve()
    ]).then(function () { emit(); return API; });
    return hydrated;
  }

  var API = {
    domains: function (f) {
      f = f || {};
      return DOMAINS.filter(function (d) {
        if (f.q) { var q = String(f.q).toLowerCase(); if (d.name.indexOf(q) < 0 && d.owner.toLowerCase().indexOf(q) < 0) return false; }
        if (f.status && d.status !== f.status) return false;
        if (f.expiring && d.expDays > 30) return false;
        if (f.pending && !z(d.name).pending.length) return false;
        return true;
      });
    },
    domain: function (name) { return DOMAINS.filter(function (d) { return d.name === name; })[0] || DOMAINS[0] || null; },
    zone: function (name) { var Z = z(name); if (!Z.loaded) loadZone(name); return Z.rows.slice(); },
    zoneMeta: function (name) { var Z = z(name); return { serial: Z.serial, committedAt: Z.committedAt, pending: Z.pending.length, dnssec: Z.dnssec }; },
    pending: function (name) { return z(name).pending.slice(); },
    addRow: function (name, row, who, why) { z(name).pending.push({ op: 'add', row: Object.assign({ id: 'Rnew' + Date.now() }, row), who: who || 'panel', why: why || '' }); emit(); return true; },
    updateRow: function (name, id, row, who, why) { z(name).pending.push({ op: 'update', id: id, row: row, who: who || 'panel', why: why || '' }); emit(); return true; },
    deleteRow: function (name, id, who, why) { z(name).pending.push({ op: 'delete', id: id, who: who || 'panel', why: why || '' }); emit(); return true; },
    commit: function (name, who, why) {
      var Z = z(name);
      var changes = Z.pending.map(function (p) {
        var r = p.row || {};
        return { op: p.op, id: p.id || null, name: r.host === undefined ? undefined : (r.host || '@'), type: r.type, content: r.data, ttl: r.ttl ? parseInt(r.ttl, 10) : undefined, priority: r.prio };
      });
      return A.post('/domains/' + encodeURIComponent(name) + '/zone/changes', { changes: changes, reason: why || '' }, A.key())
        .then(function () { return A.post('/domains/' + encodeURIComponent(name) + '/zone/commit', { reason: why || '' }, A.key()); })
        .then(function () { Z.pending = []; return loadZone(name); });
    },
    discard: function (name) { z(name).pending = []; emit(); return true; },
    rollback: function (name, version) { return A.post('/domains/' + encodeURIComponent(name) + '/zone/rollback', { version: version }, A.key()).then(function () { return loadZone(name); }); },
    credit: function () { return credit; },
    kpis: function () {
      return { total: DOMAINS.length, expiring: DOMAINS.filter(function (d) { return d.expDays <= 30; }).length, transfers: DOMAINS.filter(function (d) { return d.status === 'transfer'; }).length,
        pending: Object.keys(zones).filter(function (k) { return zones[k].pending.length; }).length, credit: credit };
    },
    nssets: function () { return nssets.slice(); },
    refresh: hydrate,
    ready: function (fn) { var p = hydrated || hydrate(); return fn ? p.then(function () { try { fn(API); } catch (e) {} }) : p; },
    on: function (fn) { subs.push(fn); return function () { subs = subs.filter(function (f) { return f !== fn; }); }; }
  };
  window.OnhostDomains = API;
  if (A.user()) hydrate();
})();
