/* onhost-panel-support.api.js — the customer's ticket conversation in the panel (data seam #26).
 *
 * The prototype narrates one demo thread ("Tiket 4821"). In production the support view's thread block shows the
 * selected real ticket from window.OnhostStore (messages come from GET /v1/tickets/{id}), sends replies through
 * POST /v1/tickets/{id}/messages, closes with POST /v1/tickets/{id}/close and rates with POST /v1/tickets/{id}/csat.
 * The list row's "Odpovědět" opens the thread here (the prototype sent customers to the staff queue). */
(function () {
  'use strict';
  if (window.OnhostPanelSupport) return;
  var PRIO = { vysoka: 'P1', stredni: 'P2', nizka: 'P3' };
  var STATE = { otevreny: ['otevřený · čeká na podporu', 'open · waiting for support'], ceka: ['čeká na vaši odpověď', 'waiting for your reply'], vyreseny: ['vyřešený', 'resolved'] };
  function store() { return window.OnhostStore || null; }
  function tr(cs, a, b) { return cs ? a : b; }
  function initials(name) { return (String(name || '').split(/\s+/).map(function (p) { return p.charAt(0); }).join('').slice(0, 2).toUpperCase()) || 'ON'; }
  function when(ts, cs) {
    var d = new Date(ts);
    return d.toLocaleDateString(cs ? 'cs-CZ' : 'en-GB', { day: 'numeric', month: 'numeric' }) + ' · ' + d.toLocaleTimeString(cs ? 'cs-CZ' : 'en-GB', { hour: '2-digit', minute: '2-digit' });
  }
  /* the ticket the customer opened from the list, otherwise the newest one that still waits for somebody */
  function current(cmp) {
    var S = store();
    if (!S) return null;
    var id = cmp.state && cmp.state.ticketOpen;
    var t = id ? S.ticket(id) : null;
    if (!t) {
      var u = window.OnhostSession && window.OnhostSession.user();
      var list = u && u.email ? S.tickets({ email: u.email }) : S.tickets();
      t = list.filter(function (x) { return x.state !== 'vyreseny'; })[0] || list[0] || null;
    }
    return t;
  }
  /* the list endpoint carries no messages: the conversation is fetched once per ticket object (a store refresh
   * after a reply replaces the object, so the thread reloads and shows the new message) */
  var inflight = {};
  function hydrate(cmp, t) {
    if (t.detailLoaded || inflight[t.apiId] || !window.OnhostApi || !t.apiId) return;
    inflight[t.apiId] = true;
    window.OnhostApi.get('/tickets/' + encodeURIComponent(t.apiId)).then(function (r) {
      var d = (r && r.data) || r || {};
      t.msgs = (d.messages || []).map(function (m) { return { from: m.from || (m.author_type === 'customer' ? 'zakaznik' : 'podpora'), at: Date.parse(m.created_at) || Date.now(), text: m.text || '', who: m.author_name || '' }; });
      t.csat = d.csat_score || t.csat || null;
    }).catch(function () {}).then(function () {
      t.detailLoaded = true;
      delete inflight[t.apiId];
      try { cmp.setState({ ticketDetailAt: Date.now() }); } catch (e) {}
    });
  }
  function thread(cmp, cs) {
    var t = current(cmp);
    if (!t) return null;
    hydrate(cmp, t);
    var S = store();
    var closed = t.state === 'vyreseny';
    var short = String(t.id).replace(/^TK-\d{4}-/, '');
    var me = (window.ONHOST && window.ONHOST.user && window.ONHOST.user.name) || tr(cs, 'vy', 'you');
    var msgs = (t.msgs || []).map(function (m) {
      var staff = m.from === 'podpora';
      var who = m.who || (staff ? tr(cs, 'podpora ONhost', 'ONhost support') : me);
      return [who, staff ? tr(cs, 'inženýr · ONhost', 'engineer · ONhost') : tr(cs, 'vy', 'you'), when(m.at, cs), m.text, initials(who), '', staff];
    });
    var rate = function (score) { return function () { S.rateTicket(t.id, score); }; };
    var actions = closed
      ? (t.csat ? [] : [[tr(cs, 'Hodnotit 5 ★', 'Rate 5 ★'), '', true, rate(5)], [tr(cs, 'Hodnotit 3 ★', 'Rate 3 ★'), '', false, rate(3)], [tr(cs, 'Hodnotit 1 ★', 'Rate 1 ★'), '', false, rate(1)]])
      : [[tr(cs, 'Označit za vyřešený', 'Mark as resolved'), '', false, function () {
          if (window.confirm(tr(cs, 'Uzavřít tiket ' + t.id + '? Odpovědí do 30 dní ho znovu otevřete.', 'Close ticket ' + t.id + '? Replying within 30 days reopens it.'))) S.setTicketState(t.id, 'vyreseny', 'zakaznik');
        }]];
    var st = STATE[t.state] || STATE.otevreny;
    return {
      ticketId: t.id,
      title: '#' + short + ' · ' + t.subject,
      note: (PRIO[t.prio] || 'P2') + ' · ' + (t.svc || tr(cs, 'účet a fakturace', 'account and billing')) + ' · ' + tr(cs, st[0], st[1]),
      actions: actions, msgs: msgs, macros: [],
      ph: closed ? tr(cs, 'Odpovědí tiket znovu otevřete…', 'Replying reopens the ticket…') : tr(cs, 'Napište odpověď…', 'Write a reply…'),
      cta: tr(cs, 'Odeslat odpověď', 'Send the reply'),
      hint: tr(cs, 'Odpovídá inženýr, který službu provozuje. O odpovědi vás informujeme e-mailem.', 'Answered by the engineer who runs the service. Replies are also sent by e-mail.'),
      metaTitle: tr(cs, 'Detail tiketu', 'Ticket detail'),
      meta: [
        [tr(cs, 'Číslo', 'Number'), t.id], [tr(cs, 'Priorita', 'Priority'), PRIO[t.prio] || 'P2'], [tr(cs, 'Stav', 'State'), tr(cs, st[0], st[1])],
        [tr(cs, 'Zpráv', 'Messages'), String(msgs.length)], [tr(cs, 'Otevřeno', 'Opened'), new Date(t.at).toLocaleDateString(cs ? 'cs-CZ' : 'en-GB')], [tr(cs, 'Hodnocení', 'Rating'), t.csat ? t.csat + ' / 5' : '—']
      ],
      metaNote: tr(cs, 'Vlákno vidí celý váš tým. Uzavřený tiket znovu otevře odpověď do 30 dní.', 'The whole team sees this thread. A reply within 30 days reopens a closed ticket.'),
      onSend: function (text) { S.replyTicket(t.id, 'zakaznik', text); }
    };
  }
  /* the customer's services as the ticket form knows them: display names, resolved back to ids for the API */
  function services() {
    var groups = (window.ONHOST_PANEL && window.ONHOST_PANEL.services) || {};
    var out = [];
    Object.keys(groups).forEach(function (k) { (groups[k] || []).forEach(function (s) { if (s && s.id && s.name) out.push({ id: s.id, name: s.name }); }); });
    return out;
  }
  window.OnhostPanelSupport = {
    open: function (cmp, storeId) { cmp.setState({ ticketOpen: storeId, threadDraft: '' }); },
    serviceNames: function () { return services().map(function (s) { return s.name; }); },
    serviceId: function (name) { var hit = services().filter(function (s) { return s.name === name || s.id === name; })[0]; return hit ? hit.id : null; },
    serviceName: function (id) { var hit = services().filter(function (s) { return s.id === id; })[0]; return hit ? hit.name : null; },
    current: current,
    thread: thread
  };
})();
