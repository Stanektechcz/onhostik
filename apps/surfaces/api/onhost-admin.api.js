/* onhost-admin.api.js — window.OnhostAdmin (seam #33): the staff console outside demo mode.
 * The prototype narrates a whole NOC (shift roster, fake P1, invented customers). Outside demo mode the console keeps only
 * the areas the control plane backs — ticket queue and detail, customers, incidents, maintenance calendar, documents,
 * the overview — and reads them from the operational store (/v1/staff/*) and the staff API; the narrated areas stay
 * hidden until they have a data source. Loaded by SurfaceRenderer for the admin surface; every hook falls back to the
 * prototype literal when this module is absent, so the demo mode is untouched. */
(function () {
  if (window.OnhostAdmin) return;
  var B = window.ONHOST_BOOT || {};
  var cmps = [];
  var data = { customers: null, maintenance: null, game: null, board: null, automation: null, renewals: null, jobs: null, chargebacks: null, loyalty: null, capacity: null, capreq: null, forecasts: {} }; // §5o: loyalty campaigns (view `coupons`) and capacity requests (view `nodecost`)
  var loading = {};
  // the API-backed views: support and customers, plus (audit §5f-2) the game panels, the fleet, the jobs, the automation rules and the renewals ahead
  var ALLOWED = ['dash', 'queue', 'ticket', 'customers', 'incidents', 'maintenance', 'gnodes', 'geggs', 'galloc', 'gprov', 'fleet', 'jobsadm', 'automation', 'renewals', 'money', 'coupons', 'nodecost']; // `coupons` = loyalty campaigns, `nodecost` = capacity requests (§5o: two prototype table views repurposed) // documents stay per organization (customer detail), the console has no cross-tenant invoice list yet
  var PRIO = { kriticka: 'P1', vysoka: 'P2', stredni: 'P3', nizka: 'P4', critical: 'P1', urgent: 'P1', high: 'P2', normal: 'P3', medium: 'P3', low: 'P4' };
  var TARGET = { P1: 15, P2: 60, P3: 240, P4: 480 };

  function S() { return window.OnhostStore || null; }
  function A() { return window.OnhostApi || null; }
  function en(cmp) { return !!(cmp && cmp.state && cmp.state.lang === 'en'); }
  function tr(cmp, a, b) { return en(cmp) ? b : a; }
  function track(cmp) { if (cmp && cmps.indexOf(cmp) < 0) cmps.push(cmp); }
  function bump() { cmps.forEach(function (c) { try { c.forceUpdate(); } catch (e) {} }); }
  function load(key, path) {
    if (data[key] !== null || loading[key] || !A()) return;
    loading[key] = true;
    A().get(path).then(function (r) { data[key] = r.data || []; }).catch(function () { data[key] = []; }).then(function () { loading[key] = false; bump(); });
  }
  function fmt(ts) {
    if (!ts) return '—';
    var d = new Date(ts);
    return d.toLocaleDateString('cs-CZ', { day: 'numeric', month: 'numeric' }) + ' ' + d.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
  }
  function clock(ts) { return ts ? new Date(ts).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' }) : '—'; }
  function ago(cmp, ts) {
    var m = Math.max(0, Math.round((Date.now() - (ts || Date.now())) / 60000));
    if (m < 1) return tr(cmp, 'právě teď', 'just now');
    if (m < 60) return tr(cmp, 'před ' + m + ' min', m + ' min ago');
    var h = Math.round(m / 60);
    if (h < 24) return tr(cmp, 'před ' + h + ' h', h + ' h ago');
    return fmt(ts);
  }
  function openTickets(s) { return s.tickets().filter(function (t) { return t.state !== 'vyreseny'; }); }
  function openIncidentList(s) { return s.incidents().filter(function (i) { return i.state !== 'vyreseno'; }); }

  /* --- sidebar: only API-backed areas, every real staff member sees the same console (rights are checked by the API) --- */
  function allows(view) { return ALLOWED.indexOf(view) >= 0; }
  function role() { return 'lead'; }

  /* --- ticket queue rows in the prototype's shape (id, priority, subject, customer, service, sla minutes left, …) --- */
  function tickets() {
    var s = S();
    if (!s) return null;
    return openTickets(s).map(function (t) {
      var p = PRIO[String(t.prio || '').toLowerCase()] || 'P3';
      var age = Math.max(0, Math.round((Date.now() - (t.at || Date.now())) / 60000));
      var waiting = t.state === 'ceka';
      var answered = (t.msgs || []).some(function (m) { return m.from !== 'zakaznik'; });
      return { id: String(t.id), apiId: t.apiId, priority: p, subject: t.subject || '', customer: t.name || t.email || '—', service: t.svc || '—',
        sla: waiting || answered ? 999 : Math.max(0, (TARGET[p] || 240) - age), mine: false, opened: fmt(t.at), replies: (t.msgs || []).length,
        plan: waiting ? 'čeká na zákazníka' : (answered ? 'odpovězeno' : '') };
    }).sort(function (a, b) { return a.sla - b.sla; });
  }
  /* the console assumes a selected ticket exists; before the store answers (or with an empty queue) this stands in */
  function emptyTicket(cmp) { return { id: '—', apiId: null, priority: 'P4', subject: tr(cmp, 'Žádný tiket ve frontě', 'No ticket in the queue'), customer: '', service: '', sla: 999, mine: false, opened: '', replies: 0, plan: '' }; }
  function thread(sel) {
    var s = S();
    var t = s && sel ? s.ticket(sel.id) : null;
    if (!t) return null;
    var msgs = (t.msgs || []).map(function (m) { return [m.from === 'zakaznik' ? 'user' : 'op', m.text || '', fmt(m.at)]; });
    return msgs.length ? msgs : [['user', t.subject || '', fmt(t.at)]];
  }
  function reply(cmp, sel, text) {
    var s = S();
    if (!s || !sel || !text) return false;
    s.replyTicket(sel.id, 'podpora', text);
    return true;
  }
  /* ticket actions [label, primary, handler]: state changes go through the store (PATCH /v1/staff/tickets/{id}/transition) */
  function ticketActions(cmp, sel) {
    var s = S(), t = s && sel ? s.ticket(sel.id) : null;
    if (!t) return [];
    var go = function (to, label) { return function () { if (window.confirm(label + '?')) { s.setTicketState(t.id, to, 'podpora'); cmp.setState({ view: 'queue' }); } }; };
    var out = [];
    if (t.state !== 'ceka') out.push([tr(cmp, 'Čeká na zákazníka', 'Waiting for the customer'), false, go('ceka', tr(cmp, 'Označit tiket jako čekající na zákazníka', 'Mark the ticket as waiting for the customer'))]);
    if (t.state === 'ceka') out.push([tr(cmp, 'Znovu otevřít', 'Reopen'), false, go('otevreny', tr(cmp, 'Vrátit tiket do fronty', 'Return the ticket to the queue'))]);
    out.push([tr(cmp, 'Uzavřít', 'Close'), true, go('vyreseny', tr(cmp, 'Uzavřít tiket jako vyřešený', 'Close the ticket as resolved'))]);
    return out;
  }
  /* the context column of a ticket: service, customer, their other tickets, rating */
  function context(cmp, sel) {
    var s = S(), t = s && sel ? s.ticket(sel.id) : null;
    if (!t) return [];
    var others = s.tickets().filter(function (x) { return x.id !== t.id && ((t.email && x.email === t.email) || (t.name && x.name === t.name)); });
    var open = others.filter(function (x) { return x.state !== 'vyreseny'; }).length;
    var dot = function (k) { return 'width:8px;height:8px;flex:0 0 auto;background:' + (k === 'hot' ? 'var(--a-hot,#c0392b)' : k === 'warn' ? 'var(--a-warn,#c9a227)' : k === 'off' ? 'var(--a-muted,#8a8a8a)' : 'var(--a-acc,#2a9d8f)'); };
    return [
      { title: t.svc || tr(cmp, 'Bez služby', 'No service'), meta: t.svc ? tr(cmp, 'služba uvedená v tiketu', 'service named in the ticket') : tr(cmp, 'obecný dotaz', 'general question'), value: '', dot: dot('ok') },
      { title: t.name || t.email || '—', meta: t.email || '', value: '', dot: dot('ok') },
      { title: tr(cmp, 'Další tikety zákazníka', "Customer's other tickets"), meta: others.length ? open + ' ' + tr(cmp, 'otevřených', 'open') + ' · ' + others.length + ' ' + tr(cmp, 'celkem', 'total') : tr(cmp, 'první tiket', 'first ticket'), value: String(others.length), dot: dot(open ? 'warn' : 'off') },
      { title: tr(cmp, 'Stav', 'State'), meta: t.state + ' · ' + tr(cmp, 'založeno', 'opened') + ' ' + fmt(t.at) + (t.csat ? ' · ' + tr(cmp, 'hodnocení', 'rating') + ' ' + t.csat + '/5' : ''), value: '', dot: dot('ok') }
    ];
  }
  /* quick replies: neutral openers the operator completes with the concrete finding */
  function quick(cmp, sel) {
    var name = (B.user && B.user.name) || '';
    return [
      [tr(cmp, 'Beru si to', 'Taking it'), tr(cmp, 'Dobrý den, ' + name + ' z podpory ONhost. Přebírám váš požadavek a do dvaceti minut se ozvu s konkrétním zjištěním.', 'Hello, ' + name + ' from ONhost support. I am taking your request and will come back within twenty minutes with a concrete finding.')],
      [tr(cmp, 'Potřebuji upřesnění', 'Need details'), tr(cmp, 'Dobrý den, abychom to vyřešili napoprvé, potřebujeme ještě upřesnit: ', 'Hello, to solve this in one go we need one more detail: ')],
      [tr(cmp, 'Vyřešeno', 'Resolved'), tr(cmp, 'Dobrý den, požadavek je vyřešený: ', 'Hello, the request is resolved: ')]
    ];
  }

  /* --- customers from /v1/staff/customers: name, id, services · domains, billing mode, health from open tickets --- */
  function customers(cmp) {
    track(cmp);
    load('customers', '/staff/customers?limit=200');
    if (data.customers === null) return [];
    var s = S();
    return data.customers.map(function (o) {
      var open = s ? openTickets(s).filter(function (t) { return t.name === o.name || (o.billing && t.email && t.email === o.billing.email); }).length : 0;
      var tone = o.state === 'suspended' ? 'hot' : (open ? 'warn' : 'ok');
      var health = o.state === 'suspended' ? tr(cmp, 'pozastaven', 'suspended') : (open ? open + ' ' + tr(cmp, open === 1 ? 'tiket' : 'tikety', open === 1 ? 'ticket' : 'tickets') : '—');
      var goQueue = function () { cmp.setState({ view: 'queue', cmd: o.name, filter: 'all' }); };
      return [o.name || '—', 'ID ' + String(o.id).slice(-8).toUpperCase(), (o.services || 0) + ' ' + tr(cmp, 'služeb', 'services') + ' · ' + (o.domains || 0) + ' ' + tr(cmp, 'domén', 'domains') + (o.customer_class ? ' · ' + o.customer_class : ''),
        (o.billing && o.billing.mode ? o.billing.mode : '—'), tone, health, goQueue, goQueue];
    });
  }

  /* --- incident and maintenance cards (title, body, facts, age, kind, actions[label, primary, handler]) --- */
  function cards(cmp) {
    track(cmp);
    var v = cmp.state.view, s = S();
    if (v === 'incidents') {
      if (!s) return [];
      return s.incidents().slice().sort(function (a, b) { return (a.state === 'vyreseno') - (b.state === 'vyreseno') || (b.at || 0) - (a.at || 0); }).slice(0, 40).map(function (i) {
        var open = i.state !== 'vyreseno', sev = String(i.sev || 'p3').toUpperCase();
        return { title: sev + ' · ' + (i.title || i.id), body: (i.impact || '') + (i.svc ? ' · ' + i.svc : ''),
          facts: [[tr(cmp, 'Stav', 'State'), i.state], [tr(cmp, 'Od', 'Since'), fmt(i.at)], [tr(cmp, 'Číslo', 'Number'), String(i.id)]],
          age: open ? tr(cmp, 'otevřený', 'open') : tr(cmp, 'vyřešený', 'resolved'), kind: open ? (sev === 'P1' ? 'hot' : 'warn') : 'off',
          actions: open ? [
            [tr(cmp, 'Označit vyřešený', 'Mark resolved'), true, function () { var note = window.prompt(tr(cmp, 'Poznámka k vyřešení (zákazníci ji uvidí na stránce stavu)', 'Resolution note (customers see it on the status page)'), ''); if (note !== null) s.setIncidentState(i.id, 'vyreseno', note); }],
            [tr(cmp, 'Aktualizace stavu', 'Status update'), false, function () { var note = window.prompt(tr(cmp, 'Zpráva pro stavovou stránku', 'Status page update'), ''); if (note) s.setIncidentState(i.id, i.state, note); }]
          ] : [] };
      });
    }
    if (v === 'maintenance') {
      load('maintenance', '/staff/maintenance?limit=100');
      if (data.maintenance === null) return [];
      return data.maintenance.map(function (m) {
        var upcoming = !m.ends_at || new Date(m.ends_at) > new Date();
        return { title: (m.title || m.id), body: (m.description || m.note || m.impact || ''),
          facts: [[tr(cmp, 'Od', 'From'), fmt(m.starts_at)], [tr(cmp, 'Do', 'To'), fmt(m.ends_at)], [tr(cmp, 'Stav', 'State'), m.state || '—']].concat(m.components && m.components.length ? [[tr(cmp, 'Komponenty', 'Components'), m.components.join(', ')]] : []),
          age: m.starts_at ? ago(cmp, m.starts_at) : '—', kind: m.state === 'cancelled' ? 'off' : (upcoming ? 'warn' : 'off'), actions: [] };
      });
    }
    return null;
  }

  /* --- the burning strip: an open P1 incident, or a P1 ticket close to its first-reply promise --- */
  function alert(cmp) {
    var s = S();
    if (!s) return null;
    var p1 = openIncidentList(s).filter(function (i) { return String(i.sev || '').toLowerCase() === 'p1'; })[0];
    if (p1) return { text: String(p1.id) + ' · ' + p1.title + ' · P1', clock: clock(p1.at), view: 'incidents' };
    var burn = (tickets() || []).filter(function (t) { return t.priority === 'P1' && t.sla < 15; })[0];
    if (burn) return { text: '#' + burn.id + ' · ' + burn.subject + ' · P1 · ' + burn.customer, clock: tr(cmp, 'zbývá ' + burn.sla + ' min', burn.sla + ' min left'), view: 'ticket', sel: burn.id };
    return null;
  }
  function alertTake(cmp) {
    var a = alert(cmp);
    if (!a) return;
    cmp.setState(a.view === 'ticket' ? { view: 'ticket', sel: a.sel } : { view: 'incidents' });
  }

  /* --- location capacity from /v1/staff/capacity (average node occupancy per region); nothing when nodes carry no usage --- */
  function pct(n) {
    var keys = ['occupancy', 'usage', 'load', 'utilisation', 'utilization', 'used_pct', 'usage_pct', 'fill'];
    for (var i = 0; i < keys.length; i++) if (typeof n[keys[i]] === 'number') return Math.round(n[keys[i]] <= 1 ? n[keys[i]] * 100 : n[keys[i]]);
    if (n.capacity && typeof n.capacity === 'object' && typeof n.capacity.used === 'number' && n.capacity.total) return Math.round(n.capacity.used / n.capacity.total * 100);
    if (n.capacity && typeof n.capacity === 'object' && n.usage && typeof n.usage === 'object') { // {vcpu, ram_mb, disk_gb} pairs: the fullest dimension counts
      var best = null;
      Object.keys(n.capacity).forEach(function (k) { var c = Number(n.capacity[k]), u = Number(n.usage[k]); if (c > 0 && !isNaN(u)) best = Math.max(best === null ? 0 : best, Math.round(u / c * 100)); });
      return best;
    }
    return null;
  }
  function capacity() {
    var I = window.OnhostIntegrations, env = I && typeof I.env === 'function' ? I.env() : null;
    var nodes = env && env.capacity && env.capacity.nodes ? env.capacity.nodes : [];
    var by = {};
    nodes.forEach(function (n) { var p = pct(n); if (p === null) return; var r = String(n.region || '—').toUpperCase(); (by[r] = by[r] || []).push(p); });
    return Object.keys(by).sort().map(function (r) { return [r, Math.round(by[r].reduce(function (a, b) { return a + b; }, 0) / by[r].length)]; });
  }

  /* --- activity log and the notification drawer from internal notifications --- */
  function internal(s) { return s.notifs('internal').slice().sort(function (a, b) { return (b.at || 0) - (a.at || 0); }); }
  /* internal notifications carry their payload as JSON; the strip shows the step and the reason, not the object */
  function pretty(body) {
    var b = String(body || '');
    if (b.charAt(0) !== '{') return b;
    try {
      var o = JSON.parse(b);
      var reason = (o.error && (o.error.message || o.error)) || o.reason || o.message || o.note || '';
      return [o.step_label, o.service_id, typeof reason === 'string' ? reason : ''].filter(Boolean).join(' · ');
    } catch (e) {
      // the router truncates long payloads: pull the readable fields out of the fragment
      var pick = function (key) { var m = b.match(new RegExp('"' + key + '":"((?:[^"\\\\]|\\\\.)*)"')); return m ? m[1].replace(/\\\//g, '/').replace(/\\"/g, '"') : ''; };
      var parts = [pick('step_label'), pick('service_id'), pick('message') || pick('reason')].filter(Boolean);
      return parts.length ? parts.join(' · ') : b.slice(0, 160);
    }
  }
  function log(cmp) {
    var s = S();
    if (!s) return [];
    return internal(s).slice(0, 12).map(function (n) { return [clock(n.at), n.kind || 'event', (n.title || '') + (n.body ? ' · ' + pretty(n.body) : '')]; });
  }
  var VIEW_OF = { ticket: 'queue', support: 'queue', incident: 'incidents', order: 'dash', finance: 'invoices', infra: 'dash', security: 'incidents', account: 'customers' };
  function notifs(cmp) {
    var s = S();
    if (!s) return [];
    return internal(s).slice(0, 8).map(function (n) { return [n.title || '', pretty(n.body), ago(cmp, n.at), n.read ? 'off' : 'warn', VIEW_OF[n.kind] || 'dash']; });
  }
  function unread() { var s = S(); var u = s && typeof s.unread === 'function' ? s.unread('internal') : null; return Array.isArray(u) ? u.length : 0; }

  /* --- overview numbers: what the platform knows right now --- */
  function kpis(cmp) {
    var s = S();
    if (!s) return [];
    var open = openTickets(s), waiting = open.filter(function (t) { return t.state === 'ceka'; }).length;
    var unanswered = open.filter(function (t) { return t.state !== 'ceka' && !(t.msgs || []).some(function (m) { return m.from !== 'zakaznik'; }); }).length;
    var orders = s.orders().filter(function (o) { return o.state === 'nova' || o.state === 'zaplaceno' || o.state === 'provisioning'; }).length;
    var overdue = s.invoices().filter(function (v) { return v.state === 'po_splatnosti' || v.state === 'overdue'; }).length;
    var mails = s.mails().filter(function (m) { return m.state !== 'sent'; }).length;
    var hide = 'display:none';
    return [
      { label: tr(cmp, 'Otevřené tikety', 'Open tickets'), value: String(open.length), delta: unanswered ? unanswered + ' ' + tr(cmp, 'bez odpovědi', 'unanswered') : '', deltaStyle: unanswered ? 'font-size:11px;font-family:var(--font-heading);font-weight:800;color:var(--a-warn,#c9a227)' : hide },
      { label: tr(cmp, 'Čeká na zákazníka', 'Waiting for the customer'), value: String(waiting), delta: '', deltaStyle: hide },
      { label: tr(cmp, 'Otevřené incidenty', 'Open incidents'), value: String(openIncidentList(s).length), delta: '', deltaStyle: hide },
      { label: tr(cmp, 'Objednávky v běhu', 'Orders in flight'), value: String(orders), delta: '', deltaStyle: hide },
      { label: tr(cmp, 'Doklady po splatnosti', 'Overdue documents'), value: String(overdue), delta: '', deltaStyle: hide },
      { label: tr(cmp, 'Pošta ve frontě', 'Mail queued'), value: String(mails), delta: '', deltaStyle: hide }
    ];
  }
  function openIncidents() { var s = S(); return s ? openIncidentList(s).length : 0; }

  /* --- overview panels (title, value, rows [label, value, bar %], note) and the timeline [time, title, detail, tone] --- */
  function share(n, total) { return total ? Math.round(n / total * 100) : 0; }
  function dashPanels(cmp) {
    track(cmp);
    var s = S();
    if (!s) return [];
    var open = openTickets(s), rows = tickets() || [];
    var byPrio = ['P1', 'P2', 'P3', 'P4'].map(function (p) { var n = rows.filter(function (t) { return t.priority === p; }).length; return [p + ' · ' + tr(cmp, 'otevřené', 'open'), String(n), share(n, rows.length)]; });
    var unanswered = rows.filter(function (t) { return t.sla < 999; });
    var incidents = openIncidentList(s), upcoming = (data.maintenance || []).filter(function (m) { return m.state !== 'cancelled' && (!m.ends_at || new Date(m.ends_at) > new Date()); });
    var orders = s.orders(), states = [['nova', tr(cmp, 'nové', 'new')], ['zaplaceno', tr(cmp, 'zaplacené', 'paid')], ['provisioning', tr(cmp, 'zřizují se', 'provisioning')], ['aktivni', tr(cmp, 'aktivní', 'active')]];
    load('maintenance', '/staff/maintenance?limit=100');
    return [
      { title: tr(cmp, 'Fronta tiketů', 'Ticket queue'), value: open.length + ' ' + tr(cmp, open.length === 1 ? 'otevřený' : 'otevřených', 'open'),
        rows: byPrio.concat([[tr(cmp, 'Bez první odpovědi', 'Without a first reply'), String(unanswered.length), share(unanswered.length, rows.length)]]),
        note: tr(cmp, 'Řadíme podle zbývající reakční doby: P1 15 min, P2 60 min, P3 4 h, P4 8 h od založení.', 'Sorted by remaining first-reply time: P1 15 min, P2 60 min, P3 4 h, P4 8 h from opening.') },
      { title: tr(cmp, 'Incidenty a odstávky', 'Incidents and maintenance'), value: incidents.length + ' ' + tr(cmp, 'otevřených', 'open'),
        rows: incidents.slice(0, 4).map(function (i) { return [String(i.sev || '').toUpperCase() + ' · ' + (i.title || i.id), i.state, String(i.sev).toLowerCase() === 'p1' ? 100 : 60]; })
          .concat(upcoming.slice(0, 3).map(function (m) { return [tr(cmp, 'Odstávka · ', 'Maintenance · ') + (m.title || m.id), fmt(m.starts_at), 30]; })),
        note: tr(cmp, 'Otevřené incidenty a nadcházející plánované zásahy; zákazníci je vidí na stránce stavu.', 'Open incidents and upcoming planned work; customers see them on the status page.') },
      { title: tr(cmp, 'Objednávky', 'Orders'), value: String(orders.length),
        rows: states.map(function (st) { var n = orders.filter(function (o) { return o.state === st[0]; }).length; return [st[1], String(n), share(n, orders.length)]; }),
        note: tr(cmp, 'Zaplacené objednávky se zřizují automaticky; co zůstává „nové“, čeká na platbu.', 'Paid orders provision automatically; what stays "new" is waiting for payment.') }
    ];
  }
  function timeline(cmp) {
    var s = S();
    if (!s) return [];
    var items = internal(s).slice(0, 8).map(function (n) { return [clock(n.at), n.title || '', pretty(n.body), n.read ? 'off' : 'warn']; });
    openIncidentList(s).slice(0, 3).forEach(function (i) { items.unshift([clock(i.at), String(i.sev || '').toUpperCase() + ' · ' + (i.title || i.id), i.impact || i.svc || '', String(i.sev).toLowerCase() === 'p1' ? 'hot' : 'warn']); });
    return items.length ? items : [[clock(Date.now()), tr(cmp, 'Klid', 'Quiet'), tr(cmp, 'žádné interní oznámení ani otevřený incident', 'no internal notification and no open incident'), 'ok']];
  }
  function counts(cmp) {
    track(cmp);
    load('customers', '/staff/customers?limit=200');
    load('maintenance', '/staff/maintenance?limit=100');
    var s = S();
    var upcoming = (data.maintenance || []).filter(function (m) { return m.state !== 'cancelled' && (!m.ends_at || new Date(m.ends_at) > new Date()); }).length;
    var g = data.game, b = data.board, r = data.renewals, a = data.automation, j = data.jobs;
    var gameNodes = g ? g.instances.reduce(function (n, i) { return n + (i.nodes || []).length; }, 0) : null;
    var gameEggs = g ? g.instances.reduce(function (n, i) { return n + Object.keys(i.eggs_mapped || {}).length; }, 0) : null;
    var gameFree = g ? g.instances.reduce(function (n, i) { return n + (i.allocations || []).reduce(function (m, x) { return m + (x.free || 0); }, 0); }, 0) : null;
    return { incidents: String(openIncidents()), maintenance: String(upcoming), invoices: s ? String(s.invoices({ unpaid: true }).length) : '', customers: data.customers ? String(data.customers.length) : '',
      gnodes: gameNodes === null ? '' : String(gameNodes), geggs: gameEggs === null ? '' : String(gameEggs), galloc: gameFree === null ? '' : String(gameFree), gprov: g ? String((g.queue || []).length) : '',
      fleet: b ? String((b.nodes || []).length) : '', jobsadm: j ? String((j.scheduler || []).length) : '', automation: a ? String(a.length) : '', renewals: r ? String(r.length) : '',
      money: data.chargebacks ? String(data.chargebacks.open || 0) : '', moneyDot: data.chargebacks && data.chargebacks.open ? 'warn' : 'ok',
      coupons: data.loyalty ? String((data.loyalty.campaigns || []).length) : '', nodecost: data.capreq ? String((data.capreq.data || []).filter(function (r) { return ['proposed', 'approved', 'ordered', 'failed'].indexOf(r.state) >= 0; }).length) : '', nodecostDot: data.capreq && (data.capreq.data || []).some(function (r) { return r.state === 'failed' || r.state === 'proposed'; }) ? 'warn' : 'ok',
      gprovDot: g && (g.queue || []).some(function (o) { return o.state === 'FAILED'; }) ? 'hot' : 'ok', fleetDot: b && (b.counts || {}).draining ? 'warn' : 'ok', renewalsDot: r && r.some(function (x) { return x.covered === false; }) ? 'warn' : 'ok', gnodesDot: g && g.instances.some(function (i) { return i.error || (i.nodes || []).some(function (n) { return n.maintenance; }); }) ? 'warn' : 'ok' };
  }

  /* --- table views (audit §5f-2): the prototype's table shape {title, note, head, actions, rows, foot}; rows [title, sub, [c1, c2], tone, state, [[label, note, primary, fn]]] --- */
  function reloadView(key) { data[key] = null; bump(); }
  function post(path, body, ok, then) {
    var a = A();
    if (!a) return;
    a.post(path, body || {}, a.key()).then(function () { if (ok) ok(); if (then) then(); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
  }
  function put(path, body, then) {
    var a = A();
    if (!a) return;
    a.put(path, body || {}).then(function () { if (then) then(); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
  }
  function pctTone(p) { return p >= 90 ? 'hot' : (p >= 75 ? 'warn' : 'ok'); }
  function table(cmp, _, view) {
    track(cmp);
    if (['gnodes', 'geggs', 'galloc', 'gprov'].indexOf(view) >= 0) { load('game', '/staff/game'); return gameTable(cmp, _, view); }
    if (view === 'fleet') { load('board', '/staff/provisioning/board'); var ft = fleetTable(cmp, _); if (ft && ft.actions) ft.actions = ft.actions.concat(rebalanceActions(cmp)); return ft; }
    if (view === 'money') { load('chargebacks', '/staff/chargebacks'); return chargebacksTable(cmp, _); }
    if (view === 'jobsadm') { load('jobs', '/staff/jobs'); return jobsTable(cmp, _); }
    if (view === 'automation') { load('automation', '/staff/automation'); return automationTable(cmp, _); }
    if (view === 'renewals') { load('renewals', '/staff/renewals?days=30'); return renewalsTable(cmp, _); }
    if (view === 'coupons') { load('loyalty', '/staff/loyalty/campaigns'); return loyaltyTable(cmp, _); }
    if (view === 'nodecost') { load('capacity', '/staff/capacity'); load('capreq', '/staff/capacity/requests?state=all'); return capacityTable(cmp, _); }
    return null;
  }
  /* --- §5o-5: loyalty campaigns with the cost forecast before they open --- */
  function fmtMoney(m) { return m && m.minor != null ? (Math.round(m.minor) / 100).toLocaleString('cs-CZ', { maximumFractionDigits: 0 }) + ' ' + (m.currency || 'CZK') : '—'; }
  function forecastNote(cmp, f) {
    if (!f) return '';
    return tr(cmp, 'odhad: ', 'forecast: ') + f.organizations + tr(cmp, ' org · body max ', ' orgs · points max ') + f.points_max + tr(cmp, ' / očekávané ', ' / expected ') + f.points_expected + ' (' + f.expected_completion_pct + ' %) · ' + tr(cmp, 'level-upy až ', 'level-ups up to ') + f.level_ups_max + ' · ' + tr(cmp, 'kredit max ', 'credit max ') + fmtMoney(f.credit_max) + tr(cmp, ', očekávaný ', ', expected ') + fmtMoney(f.credit_expected);
  }
  function forecast(cmp, draft, then) {
    var a = A(); if (!a) return;
    a.post('/staff/loyalty/campaigns/forecast', draft, a.key()).then(function (r) { var d = r.data || r; if (then) then(d); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
  }
  function saveCampaigns(cmp, list) { put('/staff/loyalty/campaigns', { campaigns: list }, function () { reloadView('loyalty'); }); }
  function loyaltyTable(cmp, _) {
    var l = data.loyalty;
    var title = tr(cmp, 'Věrnost a kampaně', 'Loyalty and campaigns'), note = tr(cmp, 'misijní kampaně · odhad nákladů dřív, než kampaň otevřete · body a kredit z tabulky úrovní', 'mission campaigns · the cost forecast before a campaign opens · points and credit from the level table');
    if (!l) return loadingTable(cmp, title, note);
    var campaigns = l.campaigns || [], missions = l.missions || [];
    var rows = campaigns.map(function (c) {
      var f = data.forecasts[c.key];
      var acts = [
        [tr(cmp, 'Odhad nákladů', 'Cost forecast'), tr(cmp, 'Kolik organizací kampaň osloví, kolik bodů a kreditu může stát.', 'How many organizations it reaches, what it may cost in points and credit.'), 1, function () { forecast(cmp, { key: c.key, cs: c.cs, en: c.en, missions: c.missions, badge: c.badge, active_from: c.active_from, active_to: c.active_to }, function (d) { data.forecasts[c.key] = d; bump(); }); }],
        [tr(cmp, 'Smazat', 'Delete'), tr(cmp, 'Kampaň zmizí z katalogu; udělené odznaky zůstávají.', 'The campaign leaves the catalogue; granted badges stay.'), 0, function () { if (!window.confirm(tr(cmp, 'Smazat kampaň ', 'Delete campaign ') + c.key + '?')) return; saveCampaigns(cmp, campaigns.filter(function (x) { return x.key !== c.key; })); }]
      ];
      return [(c.cs || c.key) + ' · ' + c.key, (c.missions || []).join(', ') + ' · ' + (c.active_from || '') + (c.active_to ? ' → ' + c.active_to : '') + (c.badge ? ' · ' + tr(cmp, 'odznak ', 'badge ') + c.badge : '') + (f ? ' · ' + forecastNote(cmp, f) : ''), [String((c.missions || []).length), c.in_window ? tr(cmp, 'běží', 'running') : tr(cmp, 'mimo okno', 'outside window')], c.in_window ? 'ok' : 'off', c.in_window ? tr(cmp, 'aktivní', 'active') : tr(cmp, 'čeká', 'waiting'), acts];
    });
    if (!rows.length) rows.push([tr(cmp, 'Žádná kampaň', 'No campaign'), tr(cmp, 'založte první: mise z katalogu, okno, odznak — odhad nákladů uvidíte před uložením', 'create the first: catalogue missions, a window, a badge — the forecast shows before you save'), ['0', '—'], 'off', tr(cmp, 'prázdné', 'empty'), []]);
    var actions = [
      [tr(cmp, 'Nová kampaň', 'New campaign'), tr(cmp, 'Nejdřív odhad nákladů, pak uložení.', 'The forecast first, then the save.'), 1, function () {
        var key = window.prompt(tr(cmp, 'Klíč kampaně (a-z, 0-9, pomlčky):', 'Campaign key (a-z, 0-9, dashes):'), ''); if (!key) return;
        var cs = window.prompt(tr(cmp, 'Název (cs):', 'Title (cs):'), ''); if (!cs) return;
        var ms = window.prompt(tr(cmp, 'Mise (klíče oddělené čárkou) z: ', 'Missions (comma-separated keys) from: ') + missions.join(', '), missions.slice(0, 2).join(', ')); if (!ms) return;
        var from = window.prompt(tr(cmp, 'Platí od (YYYY-MM-DD):', 'Active from (YYYY-MM-DD):'), new Date().toISOString().slice(0, 10)); if (!from) return;
        var to = window.prompt(tr(cmp, 'Platí do (YYYY-MM-DD, prázdné = bez konce):', 'Active to (YYYY-MM-DD, empty = open-ended):'), ''); if (to === null) return;
        var badge = window.prompt(tr(cmp, 'Odznak (volitelné):', 'Badge (optional):'), key); if (badge === null) return;
        var draft = { key: key.trim().toLowerCase(), cs: cs.trim(), en: cs.trim(), missions: ms.split(',').map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean), active_from: from.trim(), active_to: to.trim() || null, badge: badge.trim() || null };
        forecast(cmp, draft, function (d) {
          if (!window.confirm(forecastNote(cmp, d) + '\n\n' + tr(cmp, 'Uložit kampaň?', 'Save the campaign?'))) return;
          saveCampaigns(cmp, campaigns.concat([draft]));
        });
      }],
      [tr(cmp, 'Obnovit', 'Refresh'), '', 0, function () { reloadView('loyalty'); }]
    ];
    return { title: title, note: note, head: [tr(cmp, 'Kampaň', 'Campaign'), tr(cmp, 'Mise · okno · odhad', 'Missions · window · forecast'), [tr(cmp, 'Misí', 'Missions'), tr(cmp, 'Okno', 'Window')], tr(cmp, 'Stav', 'State'), ''], actions: actions, rows: rows, foot: tr(cmp, 'Odhad: katalogové body × aktivní organizace; očekávání z 90denní historie misí (bez historie ' + '25 %); kredit = level-upy, které body spustí.', 'Forecast: catalogue points × active organizations; expectation from the missions\' 90-day history (25 % without history); credit = the level-ups the points trigger.') };
  }
  /* --- §5n-7/§5o-7: capacity requests — the forecast's proposals, the vendor orders, the readiness of ordered nodes --- */
  function capacityTable(cmp, _) {
    var c = data.capacity, q = data.capreq;
    var title = tr(cmp, 'Kapacita a nákup uzlů', 'Capacity and node purchases'), note = tr(cmp, 'fondy podle trendu · návrhy nákupu · objednávky u dodavatele · uzly, které se ohlásily', 'pools by trend · purchase proposals · vendor orders · nodes that reported back');
    if (!c || !q) return loadingTable(cmp, title, note);
    var reload = function () { reloadView('capacity'); reloadView('capreq'); };
    var decide = function (r, decision, extra) { post('/staff/capacity/requests/' + encodeURIComponent(r.id) + '/decide', Object.assign({ decision: decision }, extra || {}), null, reload); };
    var rows = [];
    (c.forecast || []).forEach(function (p) {
      rows.push([p.role + ' · ' + (p.region || '—'), tr(cmp, 'prodejné ', 'sellable ') + Math.round((p.sellable_mb || 0) / 1024) + ' GB · ' + tr(cmp, 'prodáno ', 'sold ') + Math.round((p.sold_mb || 0) / 1024) + ' GB · ' + tr(cmp, 'měřeno ', 'measured ') + Math.round((p.used_mb || 0) / 1024) + ' GB · ' + tr(cmp, 'růst ', 'growth ') + Math.round((p.growth_mb_per_day || 0) / 1024) + ' GB/den', [p.days_left === null ? '∞' : String(p.days_left), tr(cmp, 'dní', 'days')], p.low ? 'hot' : 'ok', p.low ? tr(cmp, 'dochází', 'running out') : tr(cmp, 'v pořádku', 'fine'), []]);
    });
    (q.data || []).forEach(function (r) {
      var acts = [];
      if (r.state === 'proposed' || r.state === 'approved') acts.push([tr(cmp, r.state === 'proposed' ? 'Schválit' : 'Objednat znovu', r.state === 'proposed' ? 'Approve' : 'Order again'), tr(cmp, 'S dodavatelem na instanci se uzel objedná; bez něj čeká na ruční nákup.', 'With a vendor on the instance the node is ordered; without one it waits for the purchase.'), 1, function () { var n = window.prompt(tr(cmp, 'Poznámka (volitelné):', 'Note (optional):'), ''); if (n === null) return; decide(r, 'approve', { note: n || null }); }]);
      if (r.state === 'failed') acts.push([tr(cmp, 'Zkusit znovu', 'Retry'), '', 1, function () { decide(r, 'retry'); }]);
      if (r.state === 'proposed' || r.state === 'approved' || r.state === 'ordered') acts.push([tr(cmp, 'Dodáno', 'Delivered'), tr(cmp, 'Uzel je aktivní: zapište jeho název a žádost se uzavře.', 'The node is active: name it and the request closes.'), 0, function () { var n = window.prompt(tr(cmp, 'Název uzlu:', 'Node name:'), r.node_name || ''); if (!n) return; decide(r, 'delivered', { node_name: n }); }]);
      if (r.state === 'proposed' || r.state === 'approved' || r.state === 'ordered') acts.push([tr(cmp, 'Zrušit', 'Cancel'), '', 0, function () { if (!window.confirm(tr(cmp, 'Zrušit žádost?', 'Cancel the request?'))) return; decide(r, 'cancel'); }]);
      var ready = r.ready_at ? ' · ' + tr(cmp, 'ohlásil se ', 'reported ') + (r.ready && r.ready.ip ? r.ready.ip : '') + (r.ready && r.ready.os ? ' ' + r.ready.os : '') : '';
      rows.push([tr(cmp, 'Žádost ', 'Request ') + r.role + ' · ' + (r.region || '—'), Math.round((r.wanted.ram_mb || 0) / 1024) + ' GB RAM · ' + r.wanted.cpu_cores + ' vCPU · ' + r.wanted.disk_gb + ' GB' + (r.vendor ? ' · ' + tr(cmp, 'dodavatel ', 'vendor ') + r.vendor : ' · ' + tr(cmp, 'ruční nákup', 'manual purchase')) + (r.node_name ? ' · ' + r.node_name : '') + (r.ip ? ' · ' + r.ip : '') + (r.error ? ' · ' + r.error : '') + ready, [r.days_left === null ? '∞' : String(r.days_left), tr(cmp, 'dní', 'days')], r.state === 'failed' ? 'hot' : (r.state === 'delivered' || r.state === 'cancelled' ? 'off' : 'warn'), r.state, acts]);
    });
    if (!rows.length) rows.push([tr(cmp, 'Žádné fondy', 'No pools'), tr(cmp, 'plánovač nezná žádný uzel', 'the scheduler knows no node'), ['0', '—'], 'off', '—', []]);
    var actions = [
      [tr(cmp, 'Spustit forecast', 'Run the forecast'), tr(cmp, 'Denní běh hned: varování, návrhy, objednávky podle pravidla.', 'The daily pass now: warnings, proposals, orders per the rule.'), 1, function () { post('/staff/capacity/forecast/run', {}, null, reload); }],
      [tr(cmp, 'Obnovit', 'Refresh'), '', 0, reload]
    ];
    return { title: title, note: note + (q.auto_order ? ' · ' + tr(cmp, 'automatický nákup ZAPNUT', 'automatic ordering ON') : ' · ' + tr(cmp, 'automatický nákup vypnut (Automatizace → capacity.auto_order)', 'automatic ordering off (Automation → capacity.auto_order)')), head: [tr(cmp, 'Fond / žádost', 'Pool / request'), tr(cmp, 'Detail', 'Detail'), [tr(cmp, 'Zbývá', 'Left'), ''], tr(cmp, 'Stav', 'State'), ''], actions: actions, rows: rows, foot: tr(cmp, 'Forecast: prodejná RAM (N+1) proti prodanému a měřenému p95, růst z 7denního trendu.', 'Forecast: sellable RAM (N+1) against sold and measured p95, growth from the 7-day trend.') };
  }
  function loadingTable(cmp, title, note) { return { title: title, note: note, head: [tr(cmp, 'Načítám…', 'Loading…'), '', ['', ''], '', ''], actions: [], rows: [], foot: '' }; }
  function gameTable(cmp, _, view) {
    var g = data.game;
    var titles = { gnodes: [tr(cmp, 'Herní uzly', 'Game nodes'), tr(cmp, 'uzly herního panelu · údržba = žádné nové servery · odstavení přes plánovač', 'game panel nodes · maintenance = no new servers · drain through the scheduler')], geggs: [tr(cmp, 'Šablony her', 'Game templates'), tr(cmp, 'šablony (egg) na panelu a klíče katalogu, pod kterými se prodávají', 'panel templates (eggs) and the catalogue keys they sell under')], galloc: [tr(cmp, 'Alokace a porty', 'Allocations and ports'), tr(cmp, 'volné porty na uzlech · nový server potřebuje aspoň jeden', 'free ports per node · a new server needs at least one')], gprov: [tr(cmp, 'Provisioning herních serverů', 'Game server provisioning'), tr(cmp, 'fronta a selhání zřizování · servery panelu s jejich službami', 'the provisioning queue and failures · panel servers with their services')] };
    if (!g) return loadingTable(cmp, titles[view][0], titles[view][1]);
    var reload = function () { reloadView('game'); };
    var rows = [], actions = [];
    g.instances.forEach(function (inst) {
      var base = '/staff/integrations/' + encodeURIComponent(inst.key);
      if (inst.error) rows.push([inst.name || inst.key, tr(cmp, 'panel neodpovídá: ', 'panel not answering: ') + inst.error, ['—', '—'], 'hot', tr(cmp, 'nedostupný', 'down'), [[tr(cmp, 'Zkusit znovu', 'Retry'), '', 0, reload]]]);
      if (view === 'gnodes') {
        actions.push([tr(cmp, 'Objevit uzly · ' + inst.key, 'Discover nodes · ' + inst.key), tr(cmp, 'Uzly panelu se zapíší do plánovače (paměť, disk, údržba).', 'Panel nodes are written into the scheduler (memory, disk, maintenance).'), 1, function () { post(base + '/discover', {}, null, reload); }]);
        actions.push([tr(cmp, 'Prerekvizity · ' + inst.key, 'Prerequisites · ' + inst.key), tr(cmp, 'Klientský klíč, démony uzlů, mapování šablon.', 'Client key, node daemons, template mapping.'), 0, function () { post(base + '/prerequisites', {}, null, reload); }]);
        actions.push([tr(cmp, 'Zprovoznit panel · ' + inst.key, 'Bring the panel up · ' + inst.key), tr(cmp, 'Jedním krokem: sonda, uzly, šablony podle názvu, prerekvizity, rozsah portů, umístění tarifů.', 'One step: probe, nodes, templates by name, prerequisites, port range, plan placement.'), 0, function () { post(base + '/game/bootstrap', {}, null, reload); }]);
        (inst.nodes || []).forEach(function (n) {
          var tone = n.maintenance ? 'warn' : pctTone(Math.max(n.memory_pct || 0, n.disk_pct || 0));
          var acts = [];
          if (n.scheduler_id) acts.push([tr(cmp, 'Vystěhovat servery', 'Evacuate servers'), tr(cmp, 'Uzel se odstaví a každý server dostane vlastní stěhování: zastavení → záloha → nový server na cíli → přenos dat → přepnutí adresy → úklid.', 'The node is drained and every server gets its own migration: stop → backup → new server on the target → data transfer → address switch → clean-up.'), 0, function () {
            var others = (inst.nodes || []).filter(function (o) { return o.scheduler_id && o.scheduler_id !== n.scheduler_id; }).map(function (o) { return o.name; });
            var target = window.prompt(tr(cmp, 'Cílový uzel (prázdné = vybere plánovač): ', 'Target node (empty = the scheduler picks): ') + others.join(', '), '');
            if (target === null) return;
            var reason = window.prompt(tr(cmp, 'Důvod (zapíše se do auditu a zákazníkům)', 'Reason (goes to the audit log and to customers)'), tr(cmp, 'údržba uzlu', 'node maintenance'));
            if (reason === null) return;
            post(base + '/game/nodes/' + encodeURIComponent(n.scheduler_id) + '/evacuate', { target_node_id: target.trim() || null, reason: reason || null }, null, reload);
          }]);
          if (n.scheduler_id) acts.push([n.scheduler_state === 'draining' ? tr(cmp, 'Vrátit do provozu', 'Resume') : tr(cmp, 'Odstavit z umísťování', 'Drain'), tr(cmp, 'Odstavený uzel nedostává nové servery; běžící zůstávají.', 'A drained node gets no new servers; running ones stay.'), 0, function () { var to = n.scheduler_state === 'draining' ? 'active' : 'draining'; var reason = to === 'draining' ? window.prompt(tr(cmp, 'Důvod odstavení:', 'Reason for draining:'), '') : null; if (to === 'draining' && reason === null) return; post(base + '/nodes/' + encodeURIComponent(n.scheduler_id) + '/state', { state: to, reason: reason || undefined, keep: to === 'draining' }, null, reload); }]);
          rows.push([n.name, tr(cmp, 'RAM ' + Math.round((n.allocated_memory || 0) / 1024) + ' / ' + Math.round((n.memory || 0) / 1024) + ' GB · disk ' + Math.round((n.allocated_disk || 0) / 1024) + ' / ' + Math.round((n.disk || 0) / 1024) + ' GB', 'RAM ' + Math.round((n.allocated_memory || 0) / 1024) + ' / ' + Math.round((n.memory || 0) / 1024) + ' GB · disk ' + Math.round((n.allocated_disk || 0) / 1024) + ' / ' + Math.round((n.disk || 0) / 1024) + ' GB') + (n.scheduler_state ? ' · ' + tr(cmp, 'plánovač: ', 'scheduler: ') + n.scheduler_state : ' · ' + tr(cmp, 'mimo plánovač', 'not in the scheduler')), [String(n.servers || 0), Math.max(n.memory_pct || 0, n.disk_pct || 0) + ' %'], tone, n.maintenance ? tr(cmp, 'údržba', 'maintenance') : (n.scheduler_state === 'draining' ? tr(cmp, 'odstavený', 'drained') : tr(cmp, 'v provozu', 'serving')), acts]);
        });
      }
      if (view === 'geggs') {
        actions.push([tr(cmp, 'Namapovat automaticky · ' + inst.key, 'Map automatically · ' + inst.key), tr(cmp, 'Klíče katalogu se přiřadí k šablonám panelu podle názvu; chybějící šablony hlásí, co doimportovat.', 'Catalogue keys are matched to panel templates by name; missing templates say what to import.'), 0, function () { post(base + '/game/eggs/sync', {}, null, reload); }]);
        actions.push([tr(cmp, 'Namapovat šablonu · ' + inst.key, 'Map a template · ' + inst.key), tr(cmp, 'Klíč katalogu (např. minecraft-paper) → hnízdo a egg panelu.', 'Catalogue key (e.g. minecraft-paper) → panel nest and egg.'), 1, function () { var key = window.prompt(tr(cmp, 'Klíč katalogu (minecraft-paper, cs2, rust…):', 'Catalogue key (minecraft-paper, cs2, rust…):'), ''); if (!key) return; var nest = window.prompt(tr(cmp, 'ID hnízda (nest):', 'Nest id:'), ''); var egg = window.prompt(tr(cmp, 'ID šablony (egg):', 'Egg id:'), ''); if (!nest || !egg) return; put(base + '/game/eggs', { key: key.trim(), nest: Number(nest), egg: Number(egg) }, reload); }]);
        (inst.eggs || []).forEach(function (e) {
          var mapped = (e.mapped_as || []).length > 0;
          rows.push([e.nest + ' · ' + e.name, tr(cmp, 'hnízdo ' + e.nest_id + ' · egg ' + e.id + ' · image ' + (e.docker_image || '—') + (e.privileged ? ' · privilegovaný kontejner (neprodáváme)' : ''), 'nest ' + e.nest_id + ' · egg ' + e.id + ' · image ' + (e.docker_image || '—') + (e.privileged ? ' · privileged container (not sold)' : '')), [String(e.servers || 0), mapped ? e.mapped_as.join(', ') : '—'], e.privileged ? 'hot' : (mapped ? 'ok' : 'off'), mapped ? tr(cmp, 'v katalogu', 'in catalogue') : tr(cmp, 'nenamapováno', 'unmapped'),
            mapped ? e.mapped_as.map(function (k) { return [tr(cmp, 'Odebrat ' + k, 'Remove ' + k), tr(cmp, 'Katalogový klíč přestane ukazovat na tuto šablonu.', 'The catalogue key stops pointing at this template.'), 0, function () { put(base + '/game/eggs', { key: k, remove: true }, reload); }]; }) : [[tr(cmp, 'Namapovat', 'Map'), '', 1, function () { var key = window.prompt(tr(cmp, 'Klíč katalogu pro ' + e.name + ':', 'Catalogue key for ' + e.name + ':'), ''); if (!key) return; put(base + '/game/eggs', { key: key.trim(), nest: e.nest_id, egg: e.id }, reload); }]]]);
        });
      }
      if (view === 'galloc') {
        (inst.allocations || []).forEach(function (a) {
          rows.push([a.name + ' (#' + a.node + ')', tr(cmp, 'IP ' + (a.ips || []).join(', '), 'IP ' + (a.ips || []).join(', ')), [String(a.total || 0), String(a.free || 0)], a.free === 0 ? 'hot' : (a.free < 5 ? 'warn' : 'ok'), a.free === 0 ? tr(cmp, 'bez volných portů', 'no free ports') : tr(cmp, 'volné', 'free'),
            [[tr(cmp, 'Vytvořit rozsah portů', 'Create a port range'), tr(cmp, 'Rozsah vytvoříme celý dopředu, ať se při zakládání serveru nečeká.', 'The whole range up front so nothing waits at server creation.'), 1, function () { var ip = window.prompt(tr(cmp, 'IP adresa uzlu:', 'Node IP:'), (a.ips || [])[0] || ''); if (!ip) return; var ports = window.prompt(tr(cmp, 'Porty nebo rozsahy oddělené čárkou (25565, 25570-25580):', 'Ports or ranges, comma-separated (25565, 25570-25580):'), ''); if (!ports) return; post(base + '/game/allocations', { node: a.node, ip: ip.trim(), ports: ports.split(',').map(function (p) { return p.trim(); }).filter(Boolean) }, null, reload); }]]]);
        });
      }
      if (view === 'gprov') {
        actions.push([tr(cmp, 'Založit herní server · ' + inst.key, 'Create a game server · ' + inst.key), tr(cmp, 'Server pro zákazníka bez objednávky: organizace, tarif, šablona, verze. Stejná cesta jako objednávka (audit, fronta).', 'A server for a customer without an order: organization, plan, template, version. The same path an order takes (audit, queue).'), 1, function () { // §5o: staff quick action
          var org = window.prompt(tr(cmp, 'Organizace (id nebo slug):', 'Organization (id or slug):'), ''); if (!org) return;
          var plan = window.prompt(tr(cmp, 'Tarif (game-8 / game-16 / game-32):', 'Plan (game-8 / game-16 / game-32):'), 'game-8'); if (!plan) return;
          var eggKeys = Object.keys(inst.eggs_mapped || {});
          var egg = window.prompt(tr(cmp, 'Šablona: ', 'Template: ') + (eggKeys.join(', ') || 'minecraft-spigot'), eggKeys.indexOf('minecraft-spigot') >= 0 ? 'minecraft-spigot' : (eggKeys[0] || 'minecraft-spigot')); if (!egg) return;
          var version = window.prompt(tr(cmp, 'Verze hry (Minecraft, např. 1.21.8; prázdné = výchozí šablony):', 'Game version (Minecraft, e.g. 1.21.8; empty = the template default):'), egg.indexOf('minecraft') === 0 ? '1.21.8' : ''); if (version === null) return;
          var label = window.prompt(tr(cmp, 'Název serveru pro zákazníka:', 'Server label for the customer:'), ''); if (label === null) return;
          var body = { product_key: 'game', plan_key: plan.trim(), config: { egg: egg.trim(), version: version.trim() || undefined, label: label.trim() || undefined, region: inst.region || undefined } };
          var a = A(); if (!a) return;
          a.post('/staff/customers/' + encodeURIComponent(org.trim()) + '/services', body, a.key()).then(function (r) { var d = r.data || r; window.alert(tr(cmp, 'Založeno: ', 'Created: ') + (d.service ? d.service.name + ' · ' + d.service.state : '') + (d.operation_id ? ' · ' + tr(cmp, 'operace ', 'operation ') + d.operation_id : '')); reload(); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
        }]);
        (inst.servers || []).forEach(function (sv) {
          var svc = sv.service;
          var acts = [];
          if (svc && svc.id) { // §5o: full control from the console — power, a console command, the log, the live console
            var sid = encodeURIComponent(svc.id), org = svc.organization_id;
            var call = function (method, path, body, done) { var a = A(); if (!a) return; var headers = { 'X-Organization': org }; if (method === 'post') headers['Idempotency-Key'] = a.key(); a[method](path, body || {}, headers).then(function (r) { if (done) done(r && r.data !== undefined ? r.data : r); }).catch(function (e) { window.alert((e && e.message) || 'error'); }); };
            var power = function (pa) { return function () { call('post', '/services/' + sid + '/actions', { action: 'power', params: { power_action: pa } }, function () { reload(); }); }; };
            acts.push([tr(cmp, 'Start', 'Start'), '', 1, power('start')]);
            acts.push([tr(cmp, 'Restart', 'Restart'), '', 0, power('reboot')]);
            acts.push([tr(cmp, 'Stop', 'Stop'), '', 0, power('stop')]);
            acts.push([tr(cmp, 'Příkaz', 'Command'), tr(cmp, 'Odešle se do konzole serveru (např. list, say Ahoj, op Hrac).', 'Sent to the server console (e.g. list, say Hi, op Player).'), 0, function () { var c = window.prompt(tr(cmp, 'Příkaz konzole:', 'Console command:'), 'list'); if (!c) return; call('post', '/services/' + sid + '/actions', { action: 'command.send', params: { command: c } }, function () { window.alert(tr(cmp, 'Odesláno · výstup je v logu', 'Sent · the output is in the log')); }); }]);
            acts.push([tr(cmp, 'Log', 'Log'), tr(cmp, 'Posledních 200 řádků konzole serveru.', 'The last 200 lines of the server console.'), 0, function () { call('get', '/services/' + sid + '/logs', null, function (d) { var lines = (d && (d.lines || d.log || d)) || []; window.alert(Array.isArray(lines) ? lines.slice(-60).join('\n') : String(lines).slice(-4000)); }); }]);
            acts.push([tr(cmp, 'Konzole', 'Console'), tr(cmp, 'Konzole serveru vedle konzole: log, příkazy, napájení, token živé konzole.', 'The server console next to this console: log, commands, power, the live console token.'), 0, function () { window.open('/sprava/konzole/' + sid, '_blank', 'noopener'); }]); // §5p-2
          }
          rows.push([sv.name + ' · ' + sv.identifier, (svc ? svc.organization + ' · ' + svc.label + ' · ' + svc.state : tr(cmp, 'bez služby v platformě (server založený mimo)', 'no platform service (created outside)')) + ' · ' + tr(cmp, 'uzel ', 'node ') + sv.node + (sv.status ? ' · ' + sv.status : ''), [Math.round((sv.memory || 0) / 1024) + ' GB', sv.suspended ? tr(cmp, 'pozastaven', 'suspended') : (sv.installed ? tr(cmp, 'nainstalován', 'installed') : (sv.status || tr(cmp, 'instaluje se', 'installing')))], sv.suspended ? 'warn' : (svc ? 'ok' : 'off'), svc ? tr(cmp, 'spárován', 'matched') : tr(cmp, 'cizí', 'foreign'), acts]);
        });
      }
    });
    if (view === 'gprov') {
      (g.queue || []).forEach(function (o) {
        var failed = o.state === 'FAILED', waiting = o.state === 'WAITING';
        rows.unshift([(o.organization || '—') + ' · ' + (o.service || o.service_id), (o.step_label || o.kind) + (o.error && o.error.message ? ' · ' + o.error.message : ''), [o.attempts + '×', o.state.toLowerCase()], failed ? 'hot' : (waiting ? 'warn' : 'ok'), failed ? tr(cmp, 'selhalo', 'failed') : (waiting ? tr(cmp, 'čeká na uzel', 'waiting on the node') : tr(cmp, 'běží', 'running')),
          failed || waiting ? [[tr(cmp, 'Opakovat', 'Retry'), tr(cmp, 'Operace se spustí znovu od kroku, kde skončila.', 'The operation resumes from the step where it stopped.'), 1, function () { post('/staff/provisioning/jobs/' + encodeURIComponent(o.id) + '/retry', {}, null, reload); }], [tr(cmp, 'Zrušit', 'Cancel'), tr(cmp, 'Objednávka zůstane k ručnímu dokončení.', 'The order stays for manual completion.'), 0, function () { var reason = window.prompt(tr(cmp, 'Důvod zrušení:', 'Cancel reason:'), ''); if (reason === null) return; post('/staff/provisioning/jobs/' + encodeURIComponent(o.id) + '/cancel', { reason: reason }, null, reload); }]] : []]);
      });
      if (!(g.queue || []).length && !rows.length) rows.push([tr(cmp, 'Fronta je prázdná', 'The queue is empty'), tr(cmp, 'za 24 h dokončeno: ' + (g.recent || 0), 'finished in 24 h: ' + (g.recent || 0)), ['0', '—'], 'ok', tr(cmp, 'klid', 'quiet'), []]);
    }
    if (!g.instances.length) rows.push([tr(cmp, 'Žádný herní panel', 'No game panel'), tr(cmp, 'zaregistrujte instanci herního panelu v Nastavení systému → Integrace', 'register a game panel instance under System settings → Integrations'), ['0', '—'], 'off', tr(cmp, 'nenastaveno', 'not set up'), []]);
    actions.push([tr(cmp, 'Obnovit', 'Refresh'), '', 0, reload]);
    return { title: titles[view][0], note: titles[view][1], head: view === 'gnodes' ? [tr(cmp, 'Uzel', 'Node'), tr(cmp, 'Kapacita', 'Capacity'), [tr(cmp, 'Serverů', 'Servers'), tr(cmp, 'Obsazenost', 'Occupancy')], tr(cmp, 'Stav', 'State'), ''] : (view === 'geggs' ? [tr(cmp, 'Šablona', 'Template'), tr(cmp, 'Panel', 'Panel'), [tr(cmp, 'Serverů', 'Servers'), tr(cmp, 'Klíč', 'Key')], tr(cmp, 'Stav', 'State'), ''] : (view === 'galloc' ? [tr(cmp, 'Uzel', 'Node'), tr(cmp, 'Adresy', 'Addresses'), [tr(cmp, 'Portů', 'Ports'), tr(cmp, 'Volných', 'Free')], tr(cmp, 'Stav', 'State'), ''] : [tr(cmp, 'Server / operace', 'Server / operation'), tr(cmp, 'Kde to stojí', 'Where it stands'), [tr(cmp, 'Paměť / pokusy', 'Memory / attempts'), tr(cmp, 'Stav', 'State')], tr(cmp, 'Stav', 'State'), ''])), actions: actions, rows: rows, foot: view === 'gprov' ? tr(cmp, 'Selhaná instalace je náš problém, ne zákazníkův — opakujeme, co je bezpečné opakovat, a ozveme se dřív, než napíše.', 'A failed install is our problem, not the customer’s — we retry what is safe to retry and write before they do.') : '' };
  }
  function fleetTable(cmp, _) {
    var b = data.board;
    if (!b) return loadingTable(cmp, tr(cmp, 'Uzly a operace', 'Nodes and operations'), '');
    var reload = function () { reloadView('board'); };
    var rows = (b.nodes || []).map(function (n) {
      var bmc = n.bmc || null, bmcBad = !!(bmc && ((bmc.psu_failed || 0) > 0 || (bmc.fans_failed || 0) > 0)); // §5p-6: what the management controller reported
      var tone = n.state === 'draining' ? 'warn' : (n.suggest_drain || bmcBad ? 'hot' : (n.health && n.health.up === false ? 'hot' : 'ok'));
      var acts = [[n.state === 'draining' ? tr(cmp, 'Vrátit do provozu', 'Resume') : tr(cmp, 'Odstavit', 'Drain'), tr(cmp, 'Odstavený uzel nedostává nové služby; běžící zůstávají.', 'A drained node gets no new services; running ones stay.'), 0, function () { if (!n.instance) return; var to = n.state === 'draining' ? 'active' : 'draining'; var reason = to === 'draining' ? window.prompt(tr(cmp, 'Důvod odstavení:', 'Reason:'), '') : null; if (to === 'draining' && reason === null) return; post('/staff/integrations/' + encodeURIComponent(n.instance.key) + '/nodes/' + encodeURIComponent(n.id) + '/state', { state: to, reason: reason || undefined, keep: to === 'draining' }, null, reload); }]];
      return [n.name + ' · ' + (n.role || '') + ' · ' + (n.region || ''), (n.instance ? n.instance.key + ' · ' : '') + (n.health ? (n.health.up ? tr(cmp, 'integrace odpovídá', 'integration up') : tr(cmp, 'integrace neodpovídá', 'integration down')) : tr(cmp, 'bez měření', 'no probe')) + ' · ' + tr(cmp, 'za ' + n.window_minutes + ' min: ' + n.succeeded + ' ok, ' + n.transient_failures + ' přechodných chyb', 'last ' + n.window_minutes + ' min: ' + n.succeeded + ' ok, ' + n.transient_failures + ' transient failures') + (n.auto_drained ? tr(cmp, ' · odstaveno automaticky', ' · drained automatically') : '') + (bmc ? ' · BMC ' + (bmc.temp_max_c != null ? bmc.temp_max_c + ' °C' : '—') + ((bmc.psu_failed || 0) > 0 ? ' · ' + tr(cmp, 'zdroj mimo OK', 'PSU not OK') : '') + ((bmc.fans_failed || 0) > 0 ? ' · ' + tr(cmp, 'ventilátor mimo OK', 'fan not OK') : '') : '') + (n.power_w != null ? ' · ' + n.power_w + ' W' : ''), [String(n.succeeded), String(n.transient_failures)], tone, n.state === 'draining' ? tr(cmp, 'odstavený', 'drained') : (n.state === 'maintenance' ? tr(cmp, 'údržba', 'maintenance') : (n.suggest_drain ? tr(cmp, 'k odstavení', 'drain suggested') : n.state)), acts];
    });
    var ops = (b.stalled || []).map(function (o) { return ['stalled', o]; }).concat((b.failed || []).map(function (o) { return ['failed', o]; })).concat((b.long_running || []).map(function (o) { return ['long', o]; }));
    ops.forEach(function (pair) {
      var kind = pair[0], o = pair[1];
      rows.push([(o.kind || '') + ' · ' + (o.service_id || ''), (o.step_label || '') + (o.error && o.error.message ? ' · ' + o.error.message : ''), [(o.attempts || 0) + '×', (o.state || '').toLowerCase()], kind === 'failed' ? 'hot' : 'warn', kind === 'stalled' ? tr(cmp, 'čeká na uzel', 'waiting on the node') : (kind === 'failed' ? tr(cmp, 'selhalo', 'failed') : tr(cmp, 'běží dlouho', 'long running')),
        [[tr(cmp, 'Opakovat', 'Retry'), '', 1, function () { post('/staff/provisioning/jobs/' + encodeURIComponent(o.id) + '/retry', {}, null, reload); }], [tr(cmp, 'Zrušit', 'Cancel'), '', 0, function () { var reason = window.prompt(tr(cmp, 'Důvod zrušení:', 'Cancel reason:'), ''); if (reason === null) return; post('/staff/provisioning/jobs/' + encodeURIComponent(o.id) + '/cancel', { reason: reason }, null, reload); }]]]);
    });
    var c = b.counts || {};
    return { title: tr(cmp, 'Uzly a operace', 'Nodes and operations'), note: tr(cmp, c.stalled + ' čeká na uzel · ' + c.failed_24h + ' selhalo za 24 h · ' + c.long_running + ' běží dlouho · ' + c.draining + ' odstavených uzlů', c.stalled + ' waiting on a node · ' + c.failed_24h + ' failed in 24 h · ' + c.long_running + ' long running · ' + c.draining + ' drained nodes'), head: [tr(cmp, 'Uzel / operace', 'Node / operation'), tr(cmp, 'Co víme', 'What we know'), [tr(cmp, 'OK', 'OK'), tr(cmp, 'Chyby', 'Failures')], tr(cmp, 'Stav', 'State'), ''], actions: [[tr(cmp, 'Obnovit', 'Refresh'), '', 0, reload], [tr(cmp, 'Nastavení systému', 'System settings'), tr(cmp, 'Integrace, instance a přístupy.', 'Integrations, instances, credentials.'), 0, function () { window.location.href = '/sprava/nastaveni/integrace'; }]], rows: rows, foot: tr(cmp, 'Uzel, který jen selhává, odstavíme automaticky a vrátíme ho po dvou zdravých sondách; ručně odstavený uzel necháme být, dokud ho nevrátíte.', 'A node that only fails is drained automatically and comes back after two healthy probes; a node you drained by hand stays until you resume it.') };
  }
  function jobsTable(cmp, _) {
    var j = data.jobs;
    if (!j) return loadingTable(cmp, tr(cmp, 'Běhové úlohy a fronty', 'Scheduled jobs and queues'), '');
    var rows = (j.scheduler || []).map(function (e) {
      var last = e.last, at = last && last.at ? ago(cmp, new Date(last.at).getTime()) : tr(cmp, 'bez záznamu', 'no record');
      var stats = last && last.stats ? Object.keys(last.stats).map(function (k) { return k + ' ' + last.stats[k]; }).join(' · ') : '';
      return [e.command.replace(/^onhost:/, ''), (e.description || '') + (stats ? ' · ' + stats : ''), [e.expression, at], last && last.error ? 'hot' : 'ok', tr(cmp, 'další ', 'next ') + clock(e.next_run_at), []];
    });
    (j.bulk_jobs || []).forEach(function (bj) {
      rows.unshift([tr(cmp, 'Hromadná akce ', 'Bulk action ') + bj.action, (bj.reason || '') + ' · ' + bj.succeeded + ' ok · ' + bj.failed + tr(cmp, ' selhalo · ', ' failed · ') + bj.running + tr(cmp, ' běží', ' running'), [String(bj.total), bj.state], bj.failed ? 'warn' : 'ok', bj.state,
        [[tr(cmp, 'Podrobnosti', 'Details'), '', 0, function () { window.alert((bj.items || []).map(function (i) { return (i.label || i.service_id) + ': ' + (i.state || '') + (i.message || i.error ? ' · ' + (i.message || i.error) : ''); }).join('\n') || tr(cmp, 'bez položek', 'no items')); }]]]);
    });
    var q = j.queue || {}, lv = j.liveness || {}, w = lv.worker || {}, sc = lv.scheduler || {};
    var live = tr(cmp, ' · plánovač ' + (sc.alive ? 'běží' : 'NEBĚŽÍ') + ' · worker fronty ' + (w.alive ? 'běží' : 'NEBĚŽÍ'), ' · scheduler ' + (sc.alive ? 'alive' : 'DOWN') + ' · queue worker ' + (w.alive ? 'alive' : 'DOWN'));
    return { title: tr(cmp, 'Běhové úlohy a fronty', 'Scheduled jobs and queues'), note: tr(cmp, 'fronta operací: ' + (q.pending || 0) + ' čeká · ' + (q.running || 0) + ' běží · ' + (q.waiting || 0) + ' čeká na uzel', 'operation queue: ' + (q.pending || 0) + ' pending · ' + (q.running || 0) + ' running · ' + (q.waiting || 0) + ' waiting on a node') + live, head: [tr(cmp, 'Úloha', 'Job'), tr(cmp, 'Co dělá a poslední výsledek', 'What it does and the last result'), [tr(cmp, 'Plán', 'Schedule'), tr(cmp, 'Naposledy', 'Last run')], tr(cmp, 'Další běh', 'Next run'), ''], actions: [[tr(cmp, 'Obnovit', 'Refresh'), '', 0, function () { reloadView('jobs'); }], [tr(cmp, 'Hromadná akce', 'Bulk action'), tr(cmp, 'Jedna akce napříč službami podle filtru; každá služba dostane vlastní operaci a audit.', 'One action across the services a filter selects; every service gets its own operation and audit row.'), 1, function () { startBulk(cmp); }]], rows:rows, foot: tr(cmp, 'Úloha, která tiše selže, je horší než úloha, která neexistuje — proto každá zapisuje svůj poslední výsledek.', 'A job that fails quietly is worse than one that does not exist — so every job records its last result.') };
  }
  /* chargebacks (credit refunds): the support queue, the decision, the returned share */
  function chargebacksTable(cmp, _) {
    var c = data.chargebacks;
    if (!c) return loadingTable(cmp, tr(cmp, 'Vrácení kreditu (chargebacky)', 'Credit refunds (chargebacks)'), '');
    var fmt = function (m) { return m && m.minor != null ? (Math.round(m.minor) / 100).toLocaleString('cs-CZ') + ' ' + (m.currency || '') : '—'; };
    var st = { requested: [tr(cmp, 'čeká na rozhodnutí', 'awaiting decision'), 'warn'], approved: [tr(cmp, 'schváleno · čeká na zrušení zákazníkem', 'approved · waiting for the customer'), 'ok'], cancelling: [tr(cmp, 'rušíme službu', 'cancelling the service'), 'ok'], refunded: [tr(cmp, 'kredit vrácen', 'credit returned'), 'ok'], rejected: [tr(cmp, 'zamítnuto', 'rejected'), 'off'], withdrawn: [tr(cmp, 'staženo', 'withdrawn'), 'off'] };
    var rows = (c.rows || []).map(function (r) {
      var s = st[r.state] || [r.state, 'off'];
      var acts = r.state === 'requested' ? [
        [tr(cmp, 'Schválit', 'Approve'), tr(cmp, 'Zákazník pak službu zruší v panelu a dostane ' + r.percent + ' % nevyužitého období jako kredit.', 'The customer then cancels the service in the panel and gets ' + r.percent + ' % of the unused period as credit.'), 1, function () { var reason = window.prompt(tr(cmp, 'Poznámka pro zákazníka (volitelná):', 'Note for the customer (optional):'), ''); if (reason === null) return; post('/staff/chargebacks/' + encodeURIComponent(r.id) + '/decide', { decision: 'approve', reason: reason || null }, null, function () { reloadView('chargebacks'); }); }],
        [tr(cmp, 'Zamítnout', 'Reject'), '', 0, function () { var reason = window.prompt(tr(cmp, 'Důvod zamítnutí (zákazník ho uvidí):', 'Reason (the customer sees it):'), ''); if (!reason) return; post('/staff/chargebacks/' + encodeURIComponent(r.id) + '/decide', { decision: 'reject', reason: reason }, null, function () { reloadView('chargebacks'); }); }]
      ] : [];
      return [(r.organization || '—') + ' · ' + ((r.service && r.service.label) || r.service_id), (r.reason || '') + (r.decision_reason ? ' · ' + tr(cmp, 'rozhodnutí: ', 'decision: ') + r.decision_reason : ''), [fmt(r.refund), r.percent + ' %'], s[1], s[0], acts];
    });
    if (!rows.length) rows.push([tr(cmp, 'Žádné žádosti', 'No requests'), tr(cmp, 'zákazníci žádají z panelu služby (Provoz → Vrácení kreditu)', 'customers ask from the service panel (Operations → Credit refund)'), ['—', c.percent + ' %'], 'ok', tr(cmp, 'klid', 'quiet'), []]);
    return { title: tr(cmp, 'Vrácení kreditu (chargebacky)', 'Credit refunds (chargebacks)'), note: tr(cmp, 'vratný podíl nevyužitého období: ' + c.percent + ' % · ' + (c.open || 0) + ' otevřených', 'returned share of the unused period: ' + c.percent + ' % · ' + (c.open || 0) + ' open'), head: [tr(cmp, 'Zákazník · služba', 'Customer · service'), tr(cmp, 'Důvod', 'Reason'), [tr(cmp, 'K vrácení', 'Refund'), tr(cmp, 'Podíl', 'Share')], tr(cmp, 'Stav', 'State'), ''],
      actions: [[tr(cmp, 'Obnovit', 'Refresh'), '', 0, function () { reloadView('chargebacks'); }],
        // why customers leave (audit §5j-6) and the marketplace disputes (§5j-1)
        [tr(cmp, 'Proč odcházejí', 'Why they leave'), tr(cmp, 'Důvody za 90 dní podle produktu, uzlu a tématu; shluky nad prahem otevírají interní incident.', 'Reasons of 90 days by product, node and theme; clusters above the threshold open an internal incident.'), 0, function () {
          var a = A(); if (!a) return;
          a.get('/staff/chargebacks/analytics?days=90').then(function (r) { var d = r.data || r; var t = (d.by_theme || []).map(function (x) { return x.theme + ': ' + x.count + '×'; }).join(', '); var n = (d.by_node || []).slice(0, 5).map(function (x) { return x.node + ' ' + x.count + '×'; }).join(', '); var p = (d.by_product || []).slice(0, 5).map(function (x) { return x.product_key + ' ' + x.count + '×'; }).join(', '); var c = (d.clusters || []).map(function (x) { return x.label + ' (' + x.count + '× · ' + x.top_theme + ')'; }).join('\n'); window.alert(tr(cmp, 'Žádostí: ', 'Requests: ') + (d.total || 0) + '\n' + tr(cmp, 'Témata: ', 'Themes: ') + (t || '—') + '\n' + tr(cmp, 'Uzly: ', 'Nodes: ') + (n || '—') + '\n' + tr(cmp, 'Produkty: ', 'Products: ') + (p || '—') + '\n\n' + tr(cmp, 'Shluky nad prahem ', 'Clusters above the threshold ') + (d.threshold || 0) + ':\n' + (c || tr(cmp, 'žádný', 'none'))); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
        }],
        [tr(cmp, 'Spory marketplace', 'Marketplace disputes'), tr(cmp, 'Reklamované zakázky partnerů: vrátit kredit, nebo potvrdit dodání.', 'Disputed partner jobs: refund the credit or confirm the delivery.'), 0, function () {
          var a = A(); if (!a) return;
          a.get('/staff/marketplace/orders?state=disputed').then(function (r) { var rows = r.data || []; if (!rows.length) { window.alert(tr(cmp, 'Žádný otevřený spor.', 'No open dispute.')); return; } var o = rows[0]; var pick = window.prompt(tr(cmp, 'Spor: ', 'Dispute: ') + (o.listing ? o.listing.title : '') + ' · ' + (o.organization || '') + ' ↔ ' + (o.partner || '') + '\n' + (o.dispute_reason || '') + '\n\n' + tr(cmp, 'Rozhodnutí (refund / deliver):', 'Decision (refund / deliver):'), 'refund'); if (!pick) return; var reason = window.prompt(tr(cmp, 'Odůvodnění (uvidí zákazník i partner):', 'Reason (the customer and the partner see it):'), ''); if (!reason) return; post('/staff/marketplace/orders/' + encodeURIComponent(o.id) + '/resolve', { decision: pick, reason: reason }, null, function () { reloadView('chargebacks'); }); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
        }], [tr(cmp, 'Nastavit podíl', 'Set the share'), tr(cmp, 'Kolik procent nevyužitého zaplaceného období se vrací jako kredit.', 'What share of the unused paid period comes back as credit.'), 0, function () { var v = window.prompt(tr(cmp, 'Podíl v procentech (0–100):', 'Share in percent (0–100):'), String(c.percent)); if (v === null) return; put('/staff/chargebacks/settings', { percent: Number(v) }, function () { reloadView('chargebacks'); }); }]],
      rows: rows, foot: tr(cmp, 'Vrácení jde vždy jen do kreditu zákazníka, nikdy na účet. Platí podíl nastavený v okamžiku schválení; služba se ruší až po závěrečné záloze.', 'Refunds go to the customer\'s credit only, never to a bank account. The share in force at the approval applies; the service is cancelled after a final backup.') };
  }
  /* rebalancing (audit §5i): the plan and its execution as migrations inside a window */
  function rebalanceActions(cmp) {
    return [
      [tr(cmp, 'Návrh přerozdělení', 'Rebalancing plan'), tr(cmp, 'Uzly nad mezí předají nejmenší služby nejméně vytíženému uzlu stejné role a regionu.', 'Nodes above the mark hand their smallest services to the least loaded node of the same role and region.'), 0, function () {
        var a = A(); if (!a) return;
        a.get('/staff/provisioning/rebalance').then(function (r) { var p = r.data || r; var lines = (p.moves || []).map(function (m) { return m.label + ' (' + Math.round((m.ram_mb || 0) / 1024) + ' GB): ' + m.from + ' → ' + m.to; }); var nodes = (p.nodes || []).map(function (n) { return n.name + ' ' + n.load_pct + ' %'; }); window.alert((lines.length ? tr(cmp, 'Navržené přesuny:\n', 'Proposed moves:\n') + lines.join('\n') : tr(cmp, 'Žádný uzel nepřekračuje mez; nic k přesunu.', 'No node above the mark; nothing to move.')) + '\n\n' + tr(cmp, 'Vytížení: ', 'Load: ') + nodes.join(' · ')); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
      }],
      [tr(cmp, 'Návrh podle využití', 'Plan from the measured load'), tr(cmp, 'Místo prodané paměti počítá s naměřenou zátěží uzlů (prediktivní; noční běh chodí do inboxu provozu).', 'Uses the measured load of the nodes instead of the sold RAM (predictive; the nightly run reaches the operations inbox).'), 0, function () {
        var a = A(); if (!a) return;
        a.get('/staff/provisioning/rebalance?basis=usage').then(function (r) { var p = r.data || r; var lines = (p.moves || []).map(function (m) { return m.label + ' (' + Math.round((m.ram_mb || 0) / 1024) + ' GB): ' + m.from + ' → ' + m.to; }); var nodes = (p.nodes || []).map(function (n) { return n.name + ' ' + n.load_pct + ' %'; }); window.alert(tr(cmp, 'Podle naměřené zátěže · uzly: ', 'By measured load · nodes: ') + nodes.join(', ') + '\n\n' + (lines.join('\n') || tr(cmp, 'žádný přesun', 'no move'))); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
      }],
      [tr(cmp, 'Spustit přerozdělení', 'Run the rebalancing'), tr(cmp, 'Každý přesun je stěhování v okně, které si zákazník může posunout.', 'Every move is a migration inside a window the customer may move.'), 0, function () {
        var days = window.prompt(tr(cmp, 'Okno pro zákazníky (dní od zítřka):', 'Window for customers (days from tomorrow):'), '3');
        if (days === null) return;
        var from = new Date(Date.now() + 86400000), to = new Date(from.getTime() + Math.max(1, Number(days) || 3) * 86400000);
        var reason = window.prompt(tr(cmp, 'Důvod (zákazníci ho uvidí):', 'Reason (customers see it):'), tr(cmp, 'vyrovnání zátěže uzlů', 'node load balancing'));
        if (reason === null) return;
        post('/staff/provisioning/rebalance', { window_from: from.toISOString(), window_to: to.toISOString(), reason: reason || null }, null, function () { reloadView('board'); });
      }]
    ];
  }
  function startBulk(cmp) {
    var j = data.jobs || {}, actions = j.bulk_actions || [];
    var action = window.prompt(tr(cmp, 'Akce: ', 'Action: ') + actions.join(', '), actions[0] || 'backup');
    if (!action) return;
    action = action.trim();
    if (actions.length && actions.indexOf(action) < 0) { window.alert(tr(cmp, 'Neznámá akce.', 'Unknown action.')); return; }
    var sel = window.prompt(tr(cmp, 'Výběr služeb — family:web · product:web-hosting · instance:<klíč instance> · node:<id> · org:<id> · services:<id,id>', 'Service selection — family:web · product:web-hosting · instance:<instance key> · node:<id> · org:<id> · services:<id,id>'), 'family:web');
    if (!sel) return;
    var m = /^\s*(family|product|instance|node|org|services)\s*:\s*(.+)$/.exec(sel);
    if (!m) { window.alert(tr(cmp, 'Filtr má tvar klíč:hodnota.', 'The filter has the form key:value.')); return; }
    var keys = { family: 'family', product: 'product_key', instance: 'provider_instance_id', node: 'node_id', org: 'organization_id', services: 'service_ids' }, filter = {};
    filter[keys[m[1]]] = m[1] === 'services' ? m[2].split(/[\s,]+/).filter(Boolean) : m[2].trim();
    var raw = window.prompt(tr(cmp, 'Parametry akce jako JSON, např. {"version":"8.3"}; prázdné = bez parametrů', 'Action parameters as JSON, e.g. {"version":"8.3"}; empty = none'), '{}');
    if (raw === null) return;
    var params = {};
    try { params = raw.trim() ? JSON.parse(raw) : {}; } catch (e) { window.alert(tr(cmp, 'Parametry nejsou platný JSON.', 'The parameters are not valid JSON.')); return; }
    var reason = window.prompt(tr(cmp, 'Důvod (zapíše se do auditu a k úloze)', 'Reason (goes to the audit log and the job)'), '');
    if (reason === null) return;
    post('/staff/bulk-jobs', { action: action, filter: filter, params: params, reason: reason || null }, null, function () { reloadView('jobs'); });
  }
  function automationTable(cmp, _) {
    var a = data.automation;
    if (!a) return loadingTable(cmp, tr(cmp, 'Automatizace a pravidla', 'Automation and rules'), '');
    var rows = a.map(function (r) {
      var last = r.last, now = r.now || {}, off = r.enabled === false;
      var nowText = Object.keys(now).map(function (k) { return k + ' ' + (now[k] === true ? tr(cmp, 'ano', 'yes') : (now[k] === false ? tr(cmp, 'ne', 'no') : now[k])); }).join(' · ');
      var stats = last && last.stats ? Object.keys(last.stats).map(function (k) { return k + ' ' + last.stats[k]; }).join(' · ') : '';
      var tone = off ? 'off' : (last && last.error ? 'hot' : (r.command === null ? 'ok' : (last ? 'ok' : 'off')));
      var state = off ? tr(cmp, 'vypnuto obsluhou', 'switched off') : (r.command === null ? tr(cmp, 'při události', 'on event') : (last ? tr(cmp, 'běží', 'running') : tr(cmp, 'čeká na první běh', 'awaiting first run')));
      var acts = r.switchable === false ? [] : [[off ? tr(cmp, 'Zapnout', 'Switch on') : tr(cmp, 'Vypnout', 'Switch off'), off ? '' : tr(cmp, 'Pravidlo zůstane v plánu, jen zapisuje přeskočení.', 'The rule keeps its slot and records skips.'), off ? 1 : 0, function () {
        var reason = window.prompt(tr(cmp, 'Důvod (zapíše se do auditu)', 'Reason (goes to the audit log)'), '');
        if (reason === null) return;
        put('/staff/automation/' + encodeURIComponent(r.key), { enabled: off, reason: reason || null }, function () { reloadView('automation'); });
      }]];
      if (r.key === 'order.risk') acts.push([tr(cmp, 'Upravit váhy', 'Edit weights'), tr(cmp, 'Váhy signálů (5–100) a práh zadržení (10–300); "reset" vrátí výchozí a smaže naučené.', 'Signal weights (5–100) and the hold threshold (10–300); "reset" restores the defaults and forgets the feedback.'), 0, function () {
        var cur = String(now.weights || '').split(' ').reduce(function (o, kv) { var p = kv.split('='); if (p[0] && p[1] !== undefined) o[p[0]] = Number(p[1]); return o; }, {});
        var raw = window.prompt(tr(cmp, 'Váhy jako JSON včetně "hold_score", nebo napište reset:', 'Weights as JSON including "hold_score", or type reset:'), JSON.stringify(Object.assign({ hold_score: now.hold_score }, cur)));
        if (raw === null) return;
        var body;
        if (raw.trim().toLowerCase() === 'reset') body = { reset: true };
        else { try { var j = JSON.parse(raw); body = { weights: {}, hold_score: j.hold_score }; Object.keys(j).forEach(function (k) { if (k !== 'hold_score') body.weights[k] = j[k]; }); } catch (e) { window.alert(tr(cmp, 'Neplatný JSON.', 'Invalid JSON.')); return; } }
        put('/staff/automation/order.risk/tuning', body, function () { reloadView('automation'); });
      }]);
      if (r.key === 'order.risk') acts.push([tr(cmp, 'Přehled rozhodnutí', 'Decision review'), tr(cmp, 'Zadržené objednávky za 90 dní: kolikrát byl každý signál potvrzen zamítnutím.', 'Held orders of 90 days: how often each signal was confirmed by a rejection.'), 0, function () {
        var a = A(); if (!a) return;
        a.get('/staff/orders/risk-review?days=90').then(function (res) { var d = res.data || res; var lines = Object.keys(d.signals || {}).map(function (k) { var s = d.signals[k]; return k + ': ' + s.held + '× ' + tr(cmp, 'zadrženo', 'held') + ', ' + s.released + '↓ ' + s.rejected + '↑' + (s.precision != null ? ' · ' + tr(cmp, 'přesnost ', 'precision ') + Math.round(s.precision * 100) + ' %' : ''); }); window.alert(tr(cmp, 'Zadržených objednávek: ', 'Held orders: ') + (d.held || 0) + '\n' + (lines.join('\n') || tr(cmp, 'zatím žádný signál', 'no signal yet')) + '\n\n' + tr(cmp, 'Export: /v1/staff/orders/risk-review?format=csv', 'Export: /v1/staff/orders/risk-review?format=csv')); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
      }]);
      return [r.name, r.does + (nowText ? ' · ' + tr(cmp, 'teď: ', 'now: ') + nowText : ''), [r.runs, last && last.at ? ago(cmp, new Date(last.at).getTime()) + (stats ? ' · ' + stats : '') : tr(cmp, 'zatím neběželo', 'not run yet')], tone, state, acts];
    });
    return { title: tr(cmp, 'Automatizace a pravidla', 'Automation and rules'), note: tr(cmp, 'co běží bez člověka · každé pravidlo hlásí poslední běh a co teď ovlivňuje · vypínač platí do dalšího zapnutí', 'what runs without a human · every rule reports its last run and what it touches now · a switch holds until switched back'), head: [tr(cmp, 'Pravidlo', 'Rule'), tr(cmp, 'Co dělá', 'What it does'), [tr(cmp, 'Spuštění', 'Runs'), tr(cmp, 'Poslední běh', 'Last run')], tr(cmp, 'Stav', 'State'), ''], actions: [[tr(cmp, 'Obnovit', 'Refresh'), '', 0, function () { reloadView('automation'); }]], rows: rows, foot: tr(cmp, 'Automat smí objednat vyšší tarif z kreditu, dobít kartu, odstavit uzel a zadržet podezřelou objednávku. Odpojit službu nesmí a nebude. Provisioning a zdraví integrací vypnout nelze — na incident slouží zmrazení.', 'Automation may order a bigger plan from credit, charge a stored card, drain a node and hold a suspicious order. It may never disconnect a service. Provisioning and integration health have no switch — the freeze is for incidents.') };
  }
  function renewalsTable(cmp, _) {
    var r = data.renewals;
    if (!r) return loadingTable(cmp, tr(cmp, 'Obnovy a expirace', 'Renewals and expiries'), '');
    var rows = r.map(function (x) {
      var amount = x.amount ? (Number(x.amount.decimal != null ? x.amount.decimal : (x.amount.minor || 0) / 100)).toLocaleString('cs-CZ') + ' ' + (x.amount.currency || '') : '—';
      var tone = x.covered === false ? 'warn' : (!x.auto_renew ? 'off' : 'ok');
      return [(x.organization || '—') + ' · ' + (x.service || ''), (x.kind === 'domain' ? tr(cmp, 'doména', 'domain') : tr(cmp, 'předplatné · ', 'subscription · ') + (x.period === 'year' ? tr(cmp, 'ročně', 'yearly') : tr(cmp, 'měsíčně', 'monthly'))) + (x.auto_renew ? tr(cmp, ' · automatická obnova', ' · auto-renew') : tr(cmp, ' · obnova vypnutá', ' · renewal off')), [amount, x.days === null ? '—' : tr(cmp, 'za ' + x.days + ' d', 'in ' + x.days + ' d')], tone, x.covered === false ? tr(cmp, 'chybí kredit', 'credit short') : (x.covered === true ? tr(cmp, 'pokryto', 'covered') : (x.auto_renew ? tr(cmp, 'v plánu', 'scheduled') : tr(cmp, 'končí', 'ends'))), x.organization_id ? [[tr(cmp, 'Zákazník', 'Customer'), '', 0, function () { cmp.setState({ view: 'customers', cmd: x.organization || '' }); }]] : []];
    });
    return { title: tr(cmp, 'Obnovy a expirace do 30 dní', 'Renewals and expiries within 30 days'), note: tr(cmp, 'předplatná a domény · ochrana obnov upozorní týden předem, dobije z uložené karty, kde je', 'subscriptions and domains · the renewal guard warns a week ahead and charges a stored card where there is one'), head: [tr(cmp, 'Položka', 'Item'), tr(cmp, 'Rozsah', 'Scope'), [tr(cmp, 'Částka', 'Amount'), tr(cmp, 'Kdy', 'When')], tr(cmp, 'Stav', 'State'), ''], actions: [[tr(cmp, 'Obnovit', 'Refresh'), '', 0, function () { reloadView('renewals'); }]], rows: rows, foot: tr(cmp, 'Nic nenecháme propadnout mlčky: na každou obnovu bez kreditu upozorňujeme dřív, než začne bolet.', 'Nothing lapses in silence: every renewal without credit gets a warning before it hurts.') };
  }
  function newIncident(cmp) {
    var s = S();
    if (!s) return;
    var title = window.prompt(tr(cmp, 'Název incidentu (zákazníci ho uvidí na stránce stavu)', 'Incident title (customers see it on the status page)'), '');
    if (!title) return;
    var sev = (window.prompt(tr(cmp, 'Závažnost: P1 (výpadek), P2 (degradace), P3 (omezení)', 'Severity: P1 (outage), P2 (degradation), P3 (limited)'), 'P2') || 'P2').toLowerCase();
    if (['p1', 'p2', 'p3', 'p4'].indexOf(sev) < 0) sev = 'p2';
    var component = window.prompt(tr(cmp, 'Dotčená komponenta (např. web, mail, dns, portal)', 'Affected component (e.g. web, mail, dns, portal)'), 'portal') || 'portal';
    s.createIncident({ title: title, sev: sev, components: [component], impact: '' });
    cmp.setState({ view: 'incidents' });
  }

  window.OnhostAdmin = { allows: allows, role: role, tickets: tickets, emptyTicket: emptyTicket, thread: thread, reply: reply, ticketActions: ticketActions, quick: quick, context: context, customers: customers, cards: cards, alert: alert, alertTake: alertTake,
    capacity: capacity, log: log, notifs: notifs, unread: unread, kpis: kpis, openIncidents: openIncidents, counts: counts, newIncident: newIncident, dashPanels: dashPanels, timeline: timeline, table: table, boot: B };
})();
