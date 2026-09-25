/* onhost-store.api.js — window.OnhostStore backed by the ONhost control plane (/v1).
 *
 * Same public API as the prototype's onhost-store.js (orders, tickets, incidents, invoices,
 * notifications, mail outbox, audit log, counts, flows, ready/on) so no surface changes.
 * Reads are synchronous over an in-memory cache hydrated from the API; mutations call the
 * API and re-hydrate. Row shapes are the prototype's (ids are human numbers: OH-…, TK-…, INC-…).
 * Demo-only features (seedVolume, reset) are no-ops here — production data is not seeded from the UI.
 */
(function () {
  'use strict';
  if (window.OnhostStore) return;
  var A = window.OnhostApi;
  var staff = A.staff();
  var me = A.user();

  /* The state of an order follows its payment and its services: "zaplaceno" comes from a payment (the bank matching, the
   * gateway, the credit), "aktivní" from delivered services. The one thing a person does to an order is cancel it — the
   * customer while it is unpaid, staff also once it is paid and nothing of it runs. So the only button the API offers is
   * the cancellation; the prototype's other arrows would be refused (422 order_transition_not_offered). */
  var ORDER_FLOW = {
    nova: { label: 'Nová', next: ['zruseno'], tone: '#8a6d00', internal: true },
    zaplaceno: { label: 'Zaplaceno', next: staff ? ['zruseno'] : [], tone: '#0f5f3a' },
    provisioning: { label: 'Provisioning', next: [], tone: '#1a4c8f' },
    aktivni: { label: 'Aktivní', next: [], tone: '#0f5f3a' },
    pozastaveno: { label: 'Pozastaveno', next: [], tone: '#8f1c0a' },
    zruseno: { label: 'Zrušeno', next: [], tone: '#6b6663' }
  };
  var TICKET_FLOW = {
    otevreny: { label: 'Otevřený', next: ['ceka', 'vyreseny'], tone: '#8f1c0a' },
    ceka: { label: 'Čeká na zákazníka', next: ['otevreny', 'vyreseny'], tone: '#8a6d00' },
    vyreseny: { label: 'Vyřešený', next: ['otevreny'], tone: '#0f5f3a' }
  };
  var INC_FLOW = {
    vysetrovani: { label: 'Vyšetřujeme', next: ['identifikovano', 'vyreseno'], tone: '#8f1c0a' },
    identifikovano: { label: 'Příčina nalezena', next: ['monitoring', 'vyreseno'], tone: '#8a6d00' },
    monitoring: { label: 'Sledujeme', next: ['vyreseno', 'vysetrovani'], tone: '#1a4c8f' },
    vyreseno: { label: 'Vyřešeno', next: [], tone: '#0f5f3a' }
  };
  var INV_FLOW = {
    vystavena: { label: 'Vystavena', next: ['zaplacena', 'stornovana'], tone: '#8a6d00' },
    po_splatnosti: { label: 'Po splatnosti', next: ['zaplacena', 'stornovana'], tone: '#8f1c0a' },
    zaplacena: { label: 'Zaplacena', next: [], tone: '#0f5f3a' },
    stornovana: { label: 'Stornována', next: [], tone: '#6b6663' }
  };

  var ORDER_UI = { DRAFT: 'nova', PENDING_PAYMENT: 'nova', PAID: 'zaplaceno', PROVISIONING: 'provisioning', PARTIALLY_ACTIVE: 'provisioning', ACTIVE: 'aktivni', SUSPENDED: 'pozastaveno', CANCELLED: 'zruseno', FAILED: 'zruseno' };
  var ORDER_API = { zaplaceno: 'PAID', provisioning: 'PROVISIONING', aktivni: 'ACTIVE', pozastaveno: 'SUSPENDED', zruseno: 'CANCELLED' };
  var TICKET_API = { otevreny: 'OPEN', ceka: 'WAITING_CUSTOMER', vyreseny: 'RESOLVED' };
  var INC_API = { vysetrovani: 'INVESTIGATING', identifikovano: 'IDENTIFIED', monitoring: 'MONITORING', vyreseno: 'RESOLVED' };
  var INV_UI = { DRAFT: 'vystavena', ISSUED: 'vystavena', PAID: 'zaplacena', CREDITED: 'stornovana', CANCELLED: 'stornovana', VOID: 'stornovana' };

  var db = { orders: [], tickets: [], incidents: [], invoices: [], notifs: [], mails: [], log: [], seq: 0 };
  var subs = [];
  function emit() { subs.forEach(function (f) { try { f(db); } catch (e) {} }); }
  function ts(iso) { var t = iso ? Date.parse(iso) : NaN; return isNaN(t) ? Date.now() : t; }
  function num(m) { return m && typeof m === 'object' ? parseFloat(m.decimal || 0) : (parseFloat(m) || 0); }
  function money(n) { return new Intl.NumberFormat('cs-CZ').format(Math.round(n)) + ' Kč'; }
  function log(who, text) { db.log.unshift({ at: Date.now(), who: who, text: text }); db.log = db.log.slice(0, 200); }
  function all(path, limit) {
    return A.get(path + (path.indexOf('?') >= 0 ? '&' : '?') + 'limit=' + (limit || 100)).then(function (r) { return r.data || []; }).catch(function () { return []; });
  }

  /* ---------- mapping API → prototype rows ---------- */
  function mapOrder(o) {
    var st = ORDER_UI[o.state] || 'nova';
    return { id: o.number || o.id, apiId: o.id, email: o.email || (o.organization && o.organization.billing_email) || (me && me.email) || '', name: o.organization_name || (o.organization && o.organization.name) || (me && me.name) || '',
      state: st, items: (o.items || []).map(function (i) { return { sku: i.sku, name: i.name, qty: i.qty, unitNet: num(i.unit_net) }; }),
      commit: o.commit_months || 1, total: num(o.total), at: ts(o.placed_at || o.created_at), src: o.source || 'web',
      stalled: !!(o.provisioning && o.provisioning.stalled), // fulfilment waiting on a transient node error (retrying)
      review: !!(o.review && o.review.state === 'pending'), // held by the intake pre-check (audit §5f-8): paid, provisioned after a staff decision
      approval: !!(o.approval && o.approval.state === 'pending'), // TASK-0021: a credit order waiting for the owner or a billing admin
      hist: [['nova', ts(o.placed_at || o.created_at)]].concat(o.paid_at ? [['zaplaceno', ts(o.paid_at)]] : []).concat(o.activated_at ? [['aktivni', ts(o.activated_at)]] : []) };
  }
  function mapTicket(t) {
    return { id: t.number || t.id, apiId: t.id, email: t.email || '', name: t.name || '', subject: t.subject, prio: t.prio || 'stredni', state: t.ui || 'otevreny', at: ts(t.created_at), svc: (t.service_id && window.OnhostPanelSupport && window.OnhostPanelSupport.serviceName(t.service_id)) || t.service_id || null,
      csat: t.csat_score || null,
      msgs: (t.messages || []).map(function (m) { return { from: m.from || (m.author_type === 'customer' ? 'zakaznik' : 'podpora'), at: ts(m.created_at), text: m.text || '', who: m.author_name || '' }; }) };
  }
  function mapIncident(i) {
    return { id: i.number || i.id, apiId: i.id, title: i.title, svc: (i.components || []).join(', ') || 'Platforma', sev: i.sev || i.severity || 'p3', state: i.ui_state || 'vysetrovani',
      at: ts(i.started_at), impact: i.impact || '', hist: (i.hist || []).map(function (h) { return [h.ui_state || 'vysetrovani', ts(h.at), h.note || '']; }) };
  }
  function mapInvoice(v) {
    var credit = v.type === 'credit_note';
    return { id: v.number || v.id, apiId: v.id, order: v.order_id || null, email: (v.buyer && v.buyer.email) || (me && me.email) || '', name: (v.buyer && v.buyer.name) || '', credit: credit,
      rows: (v.lines || []).map(function (l) { return { name: l.description, qty: parseFloat(l.qty) || 1, unitNet: num(l.unit_net) }; }),
      net: num(v.subtotal), vat: num(v.tax), total: num(v.total), state: INV_UI[v.state] || 'vystavena', issued: ts(v.issued_at || v.created_at), due: ts(v.due_at), paid: v.paid_at ? ts(v.paid_at) : null,
      method: v.payment_method || null, period: v.period || null, src: v.corrects_invoice_id || null, type: v.type };
  }
  function mapNotif(n) {
    return { id: n.id, aud: n.aud, at: ts(n.created_at), kind: n.kind, ref: n.ref, title: n.title, body: n.body, read: !!n.read_at, surface: n.surface || null };
  }
  function mapMail(m) {
    return { id: m.id, tpl: m.template_key, to: m.to, subject: m.subject, state: m.state === 'sent' ? 'sent' : (m.state === 'failed' ? 'failed' : 'queued'), at: ts(m.created_at), vars: m.vars || {}, ref: m.ref_id || null };
  }
  function overdue() {
    db.invoices.forEach(function (i) { if (i.state === 'vystavena' && i.due && i.due < Date.now()) i.state = 'po_splatnosti'; });
  }

  /* ---------- hydration ---------- */
  var hydrated = null;
  function hydrate() {
    var jobs = [
      all(staff ? '/staff/orders' : '/orders').then(function (l) { db.orders = l.map(mapOrder); }),
      all(staff ? '/staff/tickets' : '/tickets').then(function (l) { db.tickets = l.map(mapTicket); }),
      all(staff ? '/staff/incidents' : '/incidents').then(function (l) { db.incidents = l.map(mapIncident); }),
      A.get(staff ? '/notifications?audience=internal&limit=60' : '/notifications?limit=60').then(function (r) { db.notifs = (r.data || []).map(mapNotif); }).catch(function () { db.notifs = []; })
    ];
    if (!staff) jobs.push(all('/invoices').then(function (l) { db.invoices = l.map(mapInvoice); overdue(); }));
    if (staff) jobs.push(all('/staff/outbox').then(function (l) { db.mails = l.map(mapMail); }));
    hydrated = Promise.all(jobs).then(function () { emit(); return API; });
    return hydrated;
  }
  function refresh() { return hydrate(); }

  function idx() {
    var o = {}, t = {}, i = {}, v = {};
    db.orders.forEach(function (x) { o[x.id] = x; o[x.apiId] = x; });
    db.tickets.forEach(function (x) { t[x.id] = x; t[x.apiId] = x; });
    db.incidents.forEach(function (x) { i[x.id] = x; i[x.apiId] = x; });
    db.invoices.forEach(function (x) { v[x.id] = x; v[x.apiId] = x; });
    return { o: o, t: t, i: i, v: v };
  }

  var API = {
    KEY: 'onhost.ops',
    flows: { order: ORDER_FLOW, ticket: TICKET_FLOW, incident: INC_FLOW, invoice: INV_FLOW },
    on: function (fn) { subs.push(fn); return function () { subs = subs.filter(function (f) { return f !== fn; }); }; },
    all: function () { return db; },
    money: money,
    pl: function (n, one, few, many) { return n + ' ' + (n === 1 ? one : (n >= 2 && n <= 4) ? few : many); },
    refresh: refresh,

    /* --- orders --- */
    orders: function (filter) {
      var l = db.orders.slice().sort(function (a, b) { return b.at - a.at; });
      if (filter && filter.email) l = l.filter(function (o) { return o.email === filter.email; });
      if (filter && filter.state) l = l.filter(function (o) { return o.state === filter.state; });
      return l;
    },
    order: function (id) { return idx().o[id] || null; },
    createOrder: function (p) {
      // The public checkout already placed the order through the API (onhost-session-bridge.js sets window.__onhostOrder);
      // here we only mirror it into the local cache and refresh from the server. Orders are never created from the panel store directly.
      var placed = window.__onhostOrder && (window.__onhostOrder.number === p.id || !p.id) ? window.__onhostOrder : null;
      var row = { id: placed ? placed.number : (p.id || 'OH-…'), apiId: placed ? placed.id : null, email: p.email || '', name: p.name || '', state: placed && placed.state ? (ORDER_UI[placed.state] || 'nova') : 'nova',
        items: p.items || [], commit: p.commit || 1, total: p.total || 0, at: Date.now(), hist: [['nova', Date.now()]], src: p.src || 'web', pending: !placed };
      db.orders.unshift(row);
      log(p.src || 'web', 'Objednávka ' + row.id + (placed ? ' přijata' : ' odeslána'));
      refresh();
      emit();
      return row;
    },
    setOrderState: function (id, to, who) {
      var o = this.order(id); if (!o) return null;
      var f = ORDER_FLOW[o.state];
      if (!f || f.next.indexOf(to) < 0) return null;
      var path = staff ? '/staff/orders/' + o.apiId + '/transition' : '/orders/' + o.apiId + '/transition';
      var reason = who || 'panel';
      if (staff) { // a cancellation by staff says why: the reason goes to the audit and, for a paid order, onto the credit note
        reason = window.prompt('Důvod zrušení objednávky ' + id + ' (zapíše se do auditu a na opravný doklad):', '');
        if (!reason || !String(reason).trim()) return null;
      }
      var before = o.state;
      A.post(path, { state: ORDER_API[to] || to, reason: String(reason).trim() }, A.key()).then(refresh)
        .catch(function (e) { o.state = before; o.hist.pop(); log('system', 'Změna stavu ' + id + ' selhala: ' + e.message); emit(); refresh(); });
      o.state = to; o.hist.push([to, Date.now()]);
      log(who || 'admin', id + ' → ' + ORDER_FLOW[to].label);
      emit();
      return o;
    },

    /* TASK-0021: the owner or a billing admin decides a credit order another member placed (approve | reject, a reason for a rejection) */
    orderApproval: function (id, decision, reason) {
      var o = this.order(id); if (!o || !o.approval) return null;
      return A.post('/orders/' + o.apiId + '/approval', { decision: decision, reason: reason || undefined }, A.key()).then(refresh)
        .catch(function (e) { log('system', id + ': ' + e.message); emit(); throw e; });
    },

    /* --- tickets --- */
    tickets: function (filter) {
      var l = db.tickets.slice().sort(function (a, b) { return b.at - a.at; });
      if (filter && filter.email) l = l.filter(function (t) { return t.email === filter.email; });
      if (filter && filter.state) l = l.filter(function (t) { return t.state === filter.state; });
      return l;
    },
    ticket: function (id) { return idx().t[id] || null; },
    createTicket: function (p) {
      var pending = { id: 'TK-…', email: p.email || '', name: p.name || '', subject: p.subject || 'Bez předmětu', prio: p.prio || 'stredni', state: 'otevreny', at: Date.now(), svc: p.svc || null, msgs: [{ from: 'zakaznik', at: Date.now(), text: p.text || '' }], pending: true };
      db.tickets.unshift(pending);
      A.post('/tickets', { subject: pending.subject, body: p.text || '', priority: p.prio || null, service_id: (window.OnhostPanelSupport ? window.OnhostPanelSupport.serviceId(p.svc) : p.svc) || null, email: p.email || undefined, name: p.name || undefined }, A.key())
        .then(function (r) { var t = mapTicket(r.data || r); t.msgs = pending.msgs; db.tickets = db.tickets.filter(function (x) { return x !== pending; }); db.tickets.unshift(t); log('panel', 'Ticket ' + t.id + ' — ' + t.subject); emit(); })
        .catch(function (e) { pending.error = e.message; emit(); });
      emit();
      return pending;
    },
    replyTicket: function (id, from, text) {
      var t = this.ticket(id); if (!t || !String(text || '').trim()) return null;
      t.msgs.push({ from: from, at: Date.now(), text: String(text).trim() });
      t.state = from === 'podpora' ? 'ceka' : 'otevreny';
      var path = staff ? '/staff/tickets/' + t.apiId + '/messages' : '/tickets/' + t.apiId + '/messages';
      A.post(path, { body: String(text).trim(), visibility: 'public' }, A.key()).then(refresh).catch(function (e) { log('system', id + ': ' + e.message); emit(); });
      log(from === 'podpora' ? 'podpora' : 'panel', id + ' — nová zpráva');
      emit();
      return t;
    },
    rateTicket: function (id, score, comment) {
      var t = this.ticket(id); if (!t) return null;
      t.csat = score;
      A.post('/tickets/' + t.apiId + '/csat', { score: score, comment: comment || undefined }, A.key()).then(refresh).catch(function (e) { log('system', id + ': ' + e.message); emit(); });
      log('panel', id + ' — hodnocení ' + score + '/5');
      emit();
      return t;
    },
    setTicketState: function (id, to, who) {
      var t = this.ticket(id); if (!t) return null;
      var f = TICKET_FLOW[t.state];
      if (!f || f.next.indexOf(to) < 0) return null;
      var req = staff ? A.post('/staff/tickets/' + t.apiId + '/transition', { state: TICKET_API[to] || to }, A.key())
        : (to === 'vyreseny' ? A.post('/tickets/' + t.apiId + '/close', {}, A.key()) : A.post('/tickets/' + t.apiId + '/messages', { body: 'Znovu otevírám požadavek.' }, A.key()));
      req.then(refresh).catch(function (e) { log('system', id + ': ' + e.message); emit(); });
      t.state = to;
      log(who || 'podpora', id + ' → ' + TICKET_FLOW[to].label);
      emit();
      return t;
    },

    /* --- incidents --- */
    incidents: function (filter) {
      var l = db.incidents.slice().sort(function (a, b) { return b.at - a.at; });
      if (filter && filter.open) l = l.filter(function (i) { return i.state !== 'vyreseno'; });
      return l;
    },
    incident: function (id) { return idx().i[id] || null; },
    createIncident: function (p) {
      var pending = { id: 'INC-…', title: p.title || 'Incident', svc: p.svc || 'Platforma', sev: p.sev || 'p3', state: 'vysetrovani', at: Date.now(), impact: p.impact || '', hist: [['vysetrovani', Date.now(), p.note || 'Incident otevřen.']], pending: true };
      db.incidents.unshift(pending);
      A.post('/staff/incidents', { title: pending.title, severity: pending.sev, components: p.components || [p.component || 'portal'], impact: p.impact || null, note: p.note || null }, A.key())
        .then(function (r) { var i = mapIncident(r); db.incidents = db.incidents.filter(function (x) { return x !== pending; }); db.incidents.unshift(i); log('noc', 'Incident ' + i.id + ' otevřen — ' + i.title); emit(); })
        .catch(function (e) { pending.error = e.message; emit(); });
      emit();
      return pending;
    },
    setIncidentState: function (id, to, note) {
      var i = this.incident(id); if (!i) return null;
      var f = INC_FLOW[i.state];
      if (!f || f.next.indexOf(to) < 0) return null;
      var req = to === 'vyreseno' ? A.post('/staff/incidents/' + i.apiId + '/resolve', { note: note || '' }, A.key()) : A.post('/staff/incidents/' + i.apiId + '/updates', { note: note || INC_FLOW[to].label + '.', state: INC_API[to] }, A.key());
      req.then(refresh).catch(function (e) { log('system', id + ': ' + e.message); emit(); });
      i.state = to; i.hist.push([to, Date.now(), note || INC_FLOW[to].label + '.']);
      log('noc', id + ' → ' + INC_FLOW[to].label);
      emit();
      return i;
    },

    /* --- invoices --- */
    invoices: function (filter) {
      var l = db.invoices.slice().sort(function (a, b) { return b.issued - a.issued; });
      if (filter && filter.email) l = l.filter(function (i) { return i.email === filter.email; });
      if (filter && filter.state) l = l.filter(function (i) { return i.state === filter.state; });
      if (filter && filter.order) l = l.filter(function (i) { return i.order === filter.order; });
      if (filter && filter.unpaid) l = l.filter(function (i) { return i.state === 'vystavena' || i.state === 'po_splatnosti'; });
      return l;
    },
    invoice: function (id) { return idx().v[id] || null; },
    issueInvoice: function () { return null; }, /* documents are issued by the control plane on payment/renewal */
    payInvoice: function (id, method) {
      var i = this.invoice(id); if (!i || i.state === 'zaplacena' || i.state === 'stornovana') return null;
      var req = staff ? A.post('/invoices/' + i.apiId + '/mark-paid', { method: method || 'bank', reference: 'panel' }, A.key()) : A.post('/invoices/' + i.apiId + '/pay', { method: method || 'wallet' }, A.key());
      req.then(function (r) { var d = r.data || r; if (d && d.redirect_url) location.href = d.redirect_url; return refresh(); }).catch(function (e) { log('fakturace', id + ': ' + e.message); emit(); });
      log('fakturace', i.id + ' — platba odeslána (' + (method || 'wallet') + ')');
      emit();
      return i;
    },
    remindInvoice: function (id) {
      var i = this.invoice(id); if (!i) return null;
      A.post('/staff/dunning/run', {}, A.key()).then(refresh).catch(function () {});
      log('fakturace', 'Upomínkový běh vyžádán pro ' + i.id);
      emit();
      return i;
    },
    creditNote: function (id, reason) {
      var i = this.invoice(id); if (!i || i.state === 'stornovana') return null;
      A.post('/invoices/' + i.apiId + '/credit-note', { reason: reason || 'Opravný doklad' }, A.key()).then(refresh).catch(function (e) { log('fakturace', id + ': ' + e.message); emit(); });
      log('fakturace', 'Opravný doklad k ' + i.id + ' · ' + (reason || ''));
      emit();
      return i;
    },
    slaCredit: function (incidentId, pct) {
      var i = this.incident(incidentId); if (!i) return null;
      A.post('/staff/incidents/' + i.apiId + '/sla-credits', {}, A.key()).then(refresh).catch(function (e) { log('fakturace', incidentId + ': ' + e.message); emit(); });
      log('fakturace', 'SLA kredit vyžádán pro ' + incidentId + (pct ? ' (' + pct + ' %)' : ''));
      emit();
      return i;
    },
    dunningRun: function () {
      A.post('/staff/dunning/run', {}, A.key()).then(refresh).catch(function (e) { log('fakturace', 'Upomínky: ' + e.message); emit(); });
      log('fakturace', 'Spuštěn upomínkový běh');
      emit();
      return [];
    },
    revenue: function () {
      var m = {};
      db.invoices.forEach(function (i) { if (i.state !== 'zaplacena') return; var d = new Date(i.paid || i.issued); var k = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'); m[k] = (m[k] || 0) + i.total; });
      return Object.keys(m).sort().map(function (k) { return { month: k, total: m[k] }; });
    },

    /* --- notifications --- */
    notifs: function (aud) { return db.notifs.filter(function (n) { return !aud || n.aud === aud; }).sort(function (a, b) { return b.at - a.at; }); },
    unread: function (aud) { return this.notifs(aud).filter(function (n) { return !n.read; }).length; },
    markRead: function (aud, id) {
      var ids = [];
      db.notifs.forEach(function (n) { if ((!aud || n.aud === aud) && (!id || n.id === id) && !n.read) { n.read = true; ids.push(n.id); } });
      if (ids.length) A.post('/notifications/read', { ids: ids }).catch(function () {});
      emit();
    },

    /* --- mail outbox (staff) --- */
    mails: function () { return db.mails.slice().sort(function (a, b) { return b.at - a.at; }); },
    queueMail: function () { /* transactional mail is queued by the control plane from domain events */ },
    sendMail: function (id) {
      var m = db.mails.filter(function (x) { return x.id === id; })[0]; if (!m) return null;
      A.post('/staff/outbox/' + id + '/send', {}, A.key()).then(refresh).catch(function (e) { log('system', 'Mail ' + id + ': ' + e.message); emit(); });
      m.state = 'sent'; emit(); return m;
    },
    sendAllQueued: function () { var self = this; this.mails().filter(function (m) { return m.state === 'queued'; }).forEach(function (m) { self.sendMail(m.id); }); },

    /* --- audit log / counts --- */
    log: function () { return db.log.slice(); },
    audit: function (who, text) { log(who, text); emit(); },
    counts: function () {
      var self = this;
      return {
        orders: db.orders.filter(function (o) { return o.state === 'nova' || o.state === 'zaplaceno'; }).length,
        tickets: db.tickets.filter(function (t) { return t.state !== 'vyreseny'; }).length,
        incidents: db.incidents.filter(function (i) { return i.state !== 'vyreseno'; }).length,
        unpaid: db.invoices.filter(function (i) { return i.state === 'vystavena' || i.state === 'po_splatnosti'; }).length,
        mails: db.mails.filter(function (m) { return m.state === 'queued'; }).length,
        unread: { internal: self.unread('internal'), customer: self.unread('customer') }
      };
    },
    reset: function () { return refresh(); },
    seedVolume: function () { return null; },
    size: function () { return { orders: db.orders.length, tickets: db.tickets.length, incidents: db.incidents.length, invoices: db.invoices.length }; },

    /* --- support helpers --- */
    topics: function () { return (window.__onhostTopics || []).slice(); },
    topicOf: function (text) { var t = this.topics(); return t.length ? t[0] : { id: 'ostatni', label: 'Ostatní' }; },
    clusters: function () { return window.__onhostClusters || []; },
    ticketsByTopic: function (topic) { return db.tickets.filter(function (t) { return t.topic === topic; }); },
    bot: function (text, email) {
      return A.post('/assistant/chat', { message: text, session_id: window.__onhostAssistantSession || null }).then(function (r) {
        var d = r.data || r; window.__onhostAssistantSession = d.session_id || window.__onhostAssistantSession;
        return { text: d.reply || d.text || '', handoff: !!d.handoff, actions: d.actions || [] };
      });
    },
    ready: function (fn) { var p = hydrated || hydrate(); return fn ? p.then(function () { try { fn(API); } catch (e) {} }) : p; },
    /* the web-order banner asks whether fulfilment of an order waits on a node (GET /v1/orders → provisioning.stalled) */
    orderStalled: function (number) { var o = (db.orders || []).filter(function (x) { return x.id === number || x.apiId === number; })[0]; return !!(o && o.stalled); },
    orderReview: function (number) { var o = (db.orders || []).filter(function (x) { return x.id === number || x.apiId === number; })[0]; return !!(o && o.review); },
    flush: function () { return Promise.resolve(); }
  };

  if (staff) {
    A.get('/staff/tickets/clusters').then(function (r) { window.__onhostClusters = (r.data && r.data.clusters) || r.data || []; window.__onhostTopics = (r.data && r.data.topics) || []; }).catch(function () {});
  }
  window.OnhostStore = API;
  if (me) hydrate(); else hydrated = Promise.resolve(API);
})();
