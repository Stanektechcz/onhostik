/* onhost-integrations.api.js — window.OnhostIntegrations backed by /v1/staff (integrations, provisioning jobs, drift).
 * Same interface as the prototype module: providers, provider, actions, workflows, workflow, jobs, job,
 * retryJob, cancelJob, mappings, calls, health, budgets, env, kpis, on. Reads are synchronous over a cache
 * hydrated from the API; the admin surface calls ready() (or listens with on()) before the first render.
 */
(function () {
  'use strict';
  if (window.OnhostIntegrations) return;
  var A = window.OnhostApi;
  var subs = [];
  function emit() { subs.forEach(function (f) { try { f(); } catch (e) {} }); }
  function ago(iso) {
    if (!iso) return '—';
    var mins = Math.round((Date.now() - Date.parse(iso)) / 60000);
    if (mins < 1) return 'teď';
    if (mins < 60) return mins + ' min';
    if (mins < 1440) return Math.round(mins / 60) + ' h';
    return Math.round(mins / 1440) + ' d';
  }
  var PROVIDER_LABELS = { proxmox: 'Proxmox VE', pbs: 'Proxmox Backup', ispconfig: 'ISPConfig', aapanel: 'aaPanel', pterodactyl: 'Pterodactyl', powerdns: 'PowerDNS', wedos: 'WEDOS WAPI', wedos_zone: 'WEDOS Zone', kubernetes: 'RKE2 / Kubernetes' };
  var JOB_STATE = { QUEUED: 'queued', RUNNING: 'running', WAITING: 'waiting', SUCCEEDED: 'done', FAILED: 'failed', CANCELLED: 'cancelled', COMPENSATING: 'rollback', DEAD: 'dead' };

  var providers = [], jobs = [], mappings = [], health = [], capacity = null;

  function mapInstance(i) {
    var h = i.health || {};
    return { id: i.key || i.id, apiId: i.id, name: i.name || PROVIDER_LABELS[i.provider] || i.provider, kind: i.provider, region: i.region_code, base: i.base_url,
      state: (h.state || i.state || 'unknown'), latency: h.latency_ms || null, checkedAt: h.checked_at || null, checked: ago(h.checked_at), capabilities: i.capabilities || {},
      actions: [], budgets: i.budgets || null };
  }
  function mapJob(j) {
    var st = JOB_STATE[j.state] || String(j.state || '').toLowerCase();
    return { id: j.id, provider: j.provider || (j.provider_instance && j.provider_instance.provider) || '', wf: j.kind, wfLabel: j.kind, state: st, attempts: j.attempt || j.attempts || 0, maxAttempts: j.max_attempts || 5,
      error: (j.error_detail && j.error_detail.message) || j.error || '', when: ago(j.queued_at), at: j.queued_at, service: j.service_id, org: j.organization_id, step: j.step, stepsTotal: j.steps_total, correlation: j.correlation_id, nextRun: j.next_run_at };
  }
  function mapDrift(d) {
    return { id: d.id, service: d.service_id, field: d.field, ownership: d.ownership, classification: d.classification, expected: d.expected, actual: d.actual, state: d.state, detected: ago(d.detected_at), resolution: d.resolution };
  }

  var hydrated = null;
  function hydrate() {
    hydrated = Promise.all([
      A.get('/staff/integrations').then(function (r) { providers = (r.data || []).map(mapInstance); }).catch(function () { providers = []; }),
      A.get('/staff/provisioning/jobs?limit=200').then(function (r) { jobs = (r.data || []).map(mapJob); }).catch(function () { jobs = []; }),
      A.get('/staff/resource-mappings?limit=200').then(function (r) { mappings = (r.data || []).map(mapDrift); }).catch(function () { mappings = []; }),
      A.get('/staff/capacity').then(function (r) { capacity = r.data || null; }).catch(function () { capacity = null; })
    ]).then(function () { emit(); return API; });
    return hydrated;
  }

  var API = {
    providers: function () { return providers.slice(); },
    provider: function (id) { return providers.filter(function (p) { return p.id === id; })[0] || providers[0] || null; },
    actions: function (id) { var p = this.provider(id); return p ? Object.keys(p.capabilities || {}).filter(function (k) { return p.capabilities[k]; }) : []; },
    workflows: function () {
      var seen = {};
      jobs.forEach(function (j) { seen[j.wf] = seen[j.wf] || { id: j.wf, label: j.wf, count: 0 }; seen[j.wf].count++; });
      return Object.keys(seen).map(function (k) { return seen[k]; });
    },
    workflow: function (id) { return this.workflows().filter(function (w) { return w.id === id; })[0] || null; },
    jobs: function (f) {
      f = f || {};
      return jobs.filter(function (j) {
        if (f.state && j.state !== f.state) return false;
        if (f.provider && j.provider !== f.provider) return false;
        if (f.wf && j.wf !== f.wf) return false;
        return true;
      });
    },
    job: function (id) { return jobs.filter(function (j) { return j.id === id; })[0] || null; },
    retryJob: function (id, who) {
      var j = this.job(id); if (!j) return null;
      j.state = 'retrying';
      A.post('/staff/provisioning/jobs/' + id + '/retry', { reason: who ? 'ruční opakování (' + who + ')' : 'ruční opakování' }, A.key()).then(hydrate).catch(function (e) { j.error = e.message; emit(); });
      emit(); return j;
    },
    cancelJob: function (id, who, reason) {
      var j = this.job(id); if (!j) return null;
      A.post('/staff/provisioning/jobs/' + id + '/cancel', { reason: reason || ('zrušeno operátorem ' + (who || '')) }, A.key()).then(hydrate).catch(function (e) { j.error = e.message; emit(); });
      j.state = 'cancelling'; emit(); return j;
    },
    resolveDrift: function (id, resolution, note) {
      A.post('/staff/resource-mappings/' + id + '/resolve', { resolution: resolution, note: note || '' }, A.key()).then(hydrate).catch(function () {});
    },
    mappings: function () { return mappings.slice(); },
    calls: function () { return []; }, /* provider_calls are exposed per job: /staff/provisioning/jobs/{id} */
    health: function () { return providers.map(function (p) { return { id: p.id, state: p.state, latency: p.latency, checked: p.checked }; }); },
    budgets: function () { return providers.filter(function (p) { return p.budgets; }).map(function (p) { return { id: p.id, budgets: p.budgets }; }); },
    env: function () { return { apiBase: A.base, capacity: capacity }; },
    kpis: function () {
      var failed = jobs.filter(function (j) { return j.state === 'failed' || j.state === 'dead'; }).length;
      var running = jobs.filter(function (j) { return j.state === 'running' || j.state === 'waiting' || j.state === 'queued'; }).length;
      var down = providers.filter(function (p) { return p.state === 'down' || p.state === 'degraded'; }).length;
      return { jobs: jobs.length, failed: failed, running: running, providersDown: down, drift: mappings.filter(function (m) { return m.state === 'open'; }).length };
    },
    freeze: function (reason) { return A.post('/staff/provisioning/freeze', { reason: reason || 'incident' }, A.key()).then(hydrate); },
    thaw: function () { return A.post('/staff/provisioning/thaw', {}, A.key()).then(hydrate); },
    refresh: hydrate,
    ready: function (fn) { var p = hydrated || hydrate(); return fn ? p.then(function () { try { fn(API); } catch (e) {} }) : p; },
    on: function (fn) { subs.push(fn); return function () { subs = subs.filter(function (f) { return f !== fn; }); }; }
  };
  window.OnhostIntegrations = API;
  if (A.staff()) hydrate();
})();
