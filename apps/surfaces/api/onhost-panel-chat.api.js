/* onhost-panel-chat.api.js — window.OnhostPanelChat (seam #30): the panel's chat talks to the ONhost AI assistant.
 *
 * The prototype answers from a canned reply bank and stages a narrated "live engineer". Outside demo mode every
 * message goes to POST /v1/assistant/chat (domains/Support/Assistant/AssistantService.php), which answers from the
 * signed-in organization's own records (services, documents, pending transfers, domains, tickets), the knowledge base,
 * the catalogue and the API reference — through the configured LLM, or through the deterministic answer bank when no
 * provider is configured. Proposed actions come back as chips; a hand-off opens a real ticket with the transcript.
 */
(function () {
  'use strict';
  if (window.OnhostPanelChat) return;

  var S = { session: 'panel-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6), actions: [], handoff: null, busy: false };
  function A() { return window.OnhostApi; }
  function data() { return window.ONHOST_PANEL || null; }
  function isCs(cmp) { return !(cmp && cmp.state && cmp.state.lang === 'en'); }
  function now(cmp) { return cmp && cmp.clock ? cmp.clock() : ''; }
  function push(cmp, who, text) {
    cmp.setState(function (st) { return { chatTyping: false, chatMsgs: st.chatMsgs.concat([{ who: who, text: text, ago: now(cmp) }]) }; });
  }

  /* The first message of the chat: what the assistant can see, and that a human is a sentence away. */
  function welcome() {
    if (!data()) return null;
    var cs = (document.documentElement.lang || 'cs').indexOf('en') !== 0;
    return [{
      who: 'bot', ago: cs ? 'teď' : 'now',
      text: cs
        ? 'Dobrý den, jsem AI asistent ONhost. Vidím váš účet — služby, doklady, čekající platby, domény i tikety — a znám dokumentaci, znalostní bázi, ceník a API. Ptejte se konkrétně; o člověka z podpory můžete požádat kdykoliv.'
        : 'Hello, I am the ONhost AI assistant. I can see your account — services, documents, pending payments, domains and tickets — and I know the docs, the knowledge base, the price list and the API. Ask away; you can ask for a human at any time.'
    }];
  }

  /* One question → one API call; the answer and its proposed actions replace the chips. */
  function ask(cmp, text) {
    if (!data() || !A()) return false;
    var cs = isCs(cmp);
    text = String(text || '').trim();
    if (!text) return true;
    cmp.setState(function (st) { return { chatOpen: true, chatUnread: 0, chatTyping: true, chatAgent: null, chatMsgs: st.chatMsgs.concat([{ who: 'me', text: text, ago: now(cmp) }]) }; });
    S.busy = true;
    A().post('/assistant/chat', { text: text, session_id: S.session, locale: cs ? 'cs' : 'en' }).then(function (r) {
      var d = r.data || r;
      S.busy = false;
      S.actions = d.actions || [];
      if (d.handoff && d.handoff.number) S.handoff = d.handoff;
      push(cmp, 'bot', d.text || '');
      if (d.handoff && d.handoff.number && cmp.flash) {
        cmp.flash(cs ? 'Předáno podpoře' : 'Handed to support', (cs ? 'Tiket ' : 'Ticket ') + d.handoff.number + (cs ? ' obsahuje celý přepis. Odpovíme do 30 minut, u vysoké priority do 15.' : ' carries the whole transcript. We reply within 30 minutes, high priority within 15.'));
      }
    }).catch(function (e) {
      S.busy = false;
      push(cmp, 'bot', cs
        ? 'Asistent teď neodpovídá' + (e && e.message ? ' (' + e.message + ')' : '') + '. Zkuste to za chvíli, nebo napište tiket v Podpoře — dostane se k člověku.'
        : 'The assistant is not answering right now' + (e && e.message ? ' (' + e.message + ')' : '') + '. Try again in a moment or open a ticket in Support — a human will pick it up.');
    });
    return true;
  }

  /* Chips: the assistant's proposed actions after an answer, otherwise questions the account makes likely. Each chip is [label, question | handler]. */
  function chips(cmp, _) {
    var d = data();
    if (!d) return null;
    var cs = isCs(cmp), out = [];
    S.actions.forEach(function (a) {
      if (!a || !a.label) return;
      if (a.kind === 'link' && a.href) out.push([a.label, function () {
        if (a.href.indexOf('/panel#') === 0) { location.hash = a.href.slice(6); cmp.setState({ chatOpen: false }); return; } // a panel view: navigate in place
        window.open(a.href, '_blank', 'noopener');
      }]);
      else if (a.kind === 'pay') out.push([a.label, function () { cmp.setState({ tab: 'billing', chatOpen: false, selected: null }); }]);
      else if (a.kind === 'ticket') out.push([a.label, function () { escalate(cmp); }]);
      else if (a.kind === 'service_action' && a.service_id && a.action) out.push([a.label, function () { // the agent's proposal: runs only after this confirmation
        if (!window.confirm((cs ? 'Provést: ' : 'Run: ') + a.label + '?' + (a.action === 'staging.push' ? (cs ? ' Produkce se nahradí kopií ze stagingu.' : ' Production is replaced by the staging copy.') : ''))) return;
        A().post('/services/' + encodeURIComponent(a.service_id) + '/actions', { action: a.action, params: a.params || {} }, A().key())
          .then(function (r) { var d = r.data || r; push(cmp, 'bot', (cs ? '▶️ Spuštěno: ' : '▶️ Started: ') + a.label + (d.operation_id ? ' · ' + d.operation_id.slice(-6) : '') + (cs ? '. Průběh uvidíte v detailu služby (Provoz a NOC).' : '. Follow it in the service detail (Operations and NOC).')); S.actions = S.actions.filter(function (x) { return x !== a; }); })
          .catch(function (e) { push(cmp, 'bot', (cs ? '⚠️ Nepodařilo se: ' : '⚠️ Failed: ') + ((e && e.message) || '')); });
      }]);
      else if (a.kind === 'restart' && a.service_id) out.push([a.label, function () {
        if (!window.confirm((cs ? 'Restartovat ' : 'Restart ') + a.label.replace(/^(Restartovat|Restart) /, '') + '?')) return;
        A().post('/services/' + encodeURIComponent(a.service_id) + '/power', { power_action: a.power_action || 'reboot' }, A().key())
          .then(function () { if (cmp.flash) cmp.flash(cs ? 'Restart spuštěn' : 'Restart started', cs ? 'Průběh uvidíte v detailu služby.' : 'Follow it in the service detail.'); })
          .catch(function (e) { if (cmp.flash) cmp.flash(cs ? 'Nepodařilo se' : 'Failed', (e && e.message) || ''); });
      }]);
    });
    if (S.handoff && S.handoff.number) out.push([(cs ? 'Otevřít tiket ' : 'Open ticket ') + S.handoff.number, function () { cmp.setState({ tab: 'tickets', chatOpen: false, selected: null }); }]);
    if (out.length) return out;
    var pending = ((d.billing || {}).pending || []).length, services = 0, groups = d.services || {};
    Object.keys(groups).forEach(function (k) { services += (groups[k] || []).length; });
    if (pending) out.push([cs ? 'Co mám zaplatit?' : 'What do I owe?', cs ? 'Co mám zaplatit a jak?' : 'What do I owe and how do I pay?']);
    if (services) out.push([cs ? 'Stav mých služeb' : 'My services', cs ? 'Jaký je stav mých služeb?' : 'What is the state of my services?']);
    else out.push([cs ? 'Co si můžu objednat?' : 'What can I order?', cs ? 'Co si můžu objednat a kolik to stojí?' : 'What can I order and what does it cost?']);
    out.push([cs ? 'Jak nastavit DNS?' : 'How do I set up DNS?', cs ? 'Jak upravím DNS záznamy své domény?' : 'How do I edit the DNS records of my domain?']);
    out.push([cs ? 'API klíč' : 'API key', cs ? 'Jak vytvořím API klíč a kde je dokumentace API?' : 'How do I create an API key and where is the API documentation?']);
    out.push([cs ? 'Chci člověka' : 'Talk to a human', function () { escalate(cmp); }]);
    return out;
  }

  function meta(cmp, _) {
    if (!data()) return null;
    var cs = isCs(cmp);
    if (cmp.state.chatTyping) return cs ? 'píše odpověď…' : 'writing an answer…';
    if (S.handoff && S.handoff.number) return (cs ? 'předáno podpoře · tiket ' : 'handed to support · ticket ') + S.handoff.number;
    return cs ? 'odpovídá z vašeho účtu, dokumentace a znalostní báze · AI může dělat chyby' : 'answers from your account, docs and knowledge base · AI can make mistakes';
  }

  /* The footer button: asks the assistant for a human — the service opens a ticket with the transcript; a second click opens that ticket. */
  function escalate(cmp) {
    if (!data()) return false;
    if (S.handoff && S.handoff.number) { cmp.setState({ tab: 'tickets', chatOpen: false, selected: null }); return true; }
    if (!S.busy) ask(cmp, isCs(cmp) ? 'Chci mluvit s člověkem z podpory.' : 'I want to talk to a human from support.');
    return true;
  }

  window.OnhostPanelChat = { ask: ask, chips: chips, meta: meta, welcome: welcome, escalate: escalate, session: function () { return S.session; } };
})();
