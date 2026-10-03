/* ONhost panel — the domain and DNS screens the core workbench leaves out (docs/ui/data-seams.md #21, TASK-0056).
 *
 * The domain workbench (onhost-panel-workbench.api.js, domainBuild) shows registration, nameservers, the ONhost zone and DNSSEC.
 * This module adds, through the panels' own `chips`, `form` and `extra` slots (the prototype stays byte-identical):
 *   registration tab  → "Kontakt držitele" (e-mail, phone, address at the registrar; the holder's name/IČO is a transfer, not an edit)
 *                       and "Převod k nám" (a domain held elsewhere, with its AUTH-ID)
 *   DNS tab           → "Verze a návrat" (history of the zone, rollback to a version, zone file export)
 *                       and "Moje zóny" (every zone of the organization: create a standalone zone, open, export, delete)
 * Every write is a POST/DELETE with an Idempotency-Key; the second factor is asked for by OnhostApi itself on a step_up_required answer;
 * errors are shown in the visitor's language from the API's error codes, never as the raw (English) server text.
 * The workbench loads this file on demand when the renderer does not inject it. */
(function () {
  if (window.OnhostPanelDomains) return; // the prototype runtime executes helmet scripts twice
  var API = window.OnhostApi;
  var ui = { mode: {}, zone: {}, busy: {} };

  function cs(cmp) { return !cmp || !cmp.state || cmp.state.lang !== 'en'; }
  function T(cmp) { var c = cs(cmp); return function (a, b) { return c ? a : b; }; }
  /* Czech plural: 1 → one, 2–4 → few, otherwise many; English: one / many. */
  function pl(cmp, n, cz, en) {
    if (!cs(cmp)) return n + ' ' + (n === 1 ? en[0] : en[1]);
    return n + ' ' + (n === 1 ? cz[0] : (n >= 2 && n <= 4 ? cz[1] : cz[2]));
  }
  var P = {
    record: [['záznam', 'záznamy', 'záznamů'], ['record', 'records']],
    version: [['verze', 'verze', 'verzí'], ['version', 'versions']],
    zone: [['zóna', 'zóny', 'zón'], ['zone', 'zones']],
    domain: [['doména', 'domény', 'domén'], ['domain', 'domains']]
  };
  function count(cmp, n, kind) { return pl(cmp, n, P[kind][0], P[kind][1]); }

  /* API error code → [Czech, English]. `{domain}` is replaced from the answer. */
  var ERR = {
    step_up_required: ['Akce vyžaduje druhé ověření. Potvrďte ji kódem a zkuste to znovu.', 'The action needs a second verification. Confirm with a code and try again.'],
    step_up_failed: ['Ověření se nezdařilo. Zkontrolujte kód.', 'Verification failed. Check the code.'],
    dns_zone_taken: ['Tuto zónu už spravuje jiná organizace.', 'Another organization already manages this zone.'],
    dns_zone_in_use: ['Zóna slouží doméně {domain}. Nejdřív přepněte doménu na jiné jmenné servery, pak zónu smažte.', 'The zone serves the domain {domain}. Switch the domain to other nameservers first, then delete the zone.'],
    dns_version_not_found: ['Tato verze zóny neexistuje.', 'This zone version does not exist.'],
    dns_rollback_noop: ['Zóna už přesně odpovídá této verzi.', 'The zone already matches this version.'],
    dns_pending_changes: ['Zóna má nepublikované změny. Nejdřív je publikujte nebo zahoďte.', 'The zone has unpublished changes. Publish or discard them first.'],
    dns_record_limit: ['Zóna dosáhla nejvyššího počtu záznamů.', 'The zone reached its record limit.'],
    domain_holder_identity_change: ['Jméno držitele, firmu ani IČO tady změnit nejde: je to převod domény na jinou osobu. Napište podpoře.', 'The holder\'s name, company and company ID cannot be changed here: that is a transfer to another person. Write to support.'],
    domain_holder_unchanged: ['Žádný údaj se neliší od toho, co už registrátor má.', 'Nothing differs from what the registrar already has.'],
    domain_holder_not_changeable: ['Kontakt jde měnit jen u aktivní domény.', 'The contact can only be changed on an active domain.'],
    domain_external: ['Doménu spravujete u připojeného registrátora; změna se dělá tam.', 'The domain is managed at your connected registrar; change it there.'],
    domain_taken: ['Tuto doménu už spravuje jiná organizace.', 'Another organization already manages this domain.'],
    domain_invalid: ['Název domény není platný.', 'The domain name is not valid.'],
    contact_email_invalid: ['E-mail nemá platný tvar.', 'The e-mail is not valid.'],
    contact_incomplete: ['Údaj kontaktu nesmí být prázdný.', 'A contact field cannot be empty.'],
    contact_country_invalid: ['Země musí být dvoupísmenný kód, např. CZ.', 'The country must be a two-letter code, e.g. CZ.'],
    critical_domain_approval_required: ['Tuto doménu chrání schválení druhou osobou. Požádejte o něj ve svém týmu.', 'A second person must approve changes of this domain. Ask in your team.'],
    idempotency_key_reused: ['Tento požadavek už byl odeslán s jinými údaji. Zkuste to znovu.', 'This request was already sent with other data. Try again.'],
    consent_required: ['Potvrďte podmínky registru a registrátora.', 'Confirm the registry and registrar terms.']
  };
  function errorText(cmp, e) {
    var _ = T(cmp), b = e && e.body, code = b && b.error;
    if (e && e.cancelled) return e.message || _('Ověření zrušeno.', 'Verification cancelled.');
    if (code && ERR[code]) return _(ERR[code][0], ERR[code][1]).replace('{domain}', (b && b.domain) || '');
    if (e && e.status === 422 && b && b.errors) { var first = Object.keys(b.errors)[0]; if (first && b.errors[first] && b.errors[first][0]) return String(b.errors[first][0]); }
    if (e && e.status === 403) return _('K této akci nemáte oprávnění.', 'You are not allowed to do this.');
    if (e && e.status === 404) return _('Položka už neexistuje. Obnovte stránku.', 'The item no longer exists. Reload the page.');
    if (e && e.status === 429) return _('Příliš mnoho požadavků. Chvíli počkejte.', 'Too many requests. Wait a moment.');
    if (e && e.status >= 500) return _('Na naší straně se něco pokazilo. Zkuste to za chvíli; nic jste neztratili.', 'Something went wrong on our side. Try again shortly; nothing was lost.');
    return cs(cmp) ? _('Akce se nezdařila. Zkuste to prosím znovu.', '') : ((e && e.message) || 'The action failed. Please try again.');
  }

  /* ── plumbing ─────────────────────────────────────────────────────────── */
  function modeOf(sel, tab, fallback) { return ui.mode[sel.id + ':' + tab] || fallback; }
  function setMode(cmp, sel, tab, mode, X) { ui.mode[sel.id + ':' + tab] = mode; X.rerender(cmp); }
  function chips(cmp, sel, tab, current, list, X) {
    return list.map(function (c) { return { label: c[1], active: current === c[0], on: function () { setMode(cmp, sel, tab, c[0], X); } }; });
  }
  /* One write: guards against a double click, shows the outcome in the visitor's language, keeps the Idempotency-Key per attempt. */
  function send(cmp, X, method, path, body, okTitle, okBody) {
    var _ = T(cmp), key = method + ' ' + path;
    if (ui.busy[key]) return Promise.reject(new Error('busy'));
    ui.busy[key] = true;
    var call = method === 'DELETE' ? API.del(path) : API.post(path, body || {}, API.key());
    return call.then(function (r) {
      delete ui.busy[key];
      var d = r.data || r;
      X.flash(cmp, okTitle, typeof okBody === 'function' ? okBody(d) : (okBody || ''));
      return d;
    }).catch(function (e) {
      delete ui.busy[key];
      if (e && e.message !== 'busy') X.flash(cmp, e && e.cancelled ? _('Ověření zrušeno', 'Verification cancelled') : _('Akce neproběhla', 'Action failed'), errorText(cmp, e));
      throw e;
    });
  }
  function clearForm(cmp) { try { cmp.setState({ wbF: { a: '', b: '', c: '', d: '', e: '', f: '' } }); } catch (e) { /* not mounted */ } }
  function later(X, cmp, sel, kinds, delays) { (delays || [800, 4000]).forEach(function (ms) { setTimeout(function () { X.forget(sel, kinds); X.rerender(cmp); }, ms); }); }
  function exportButton(cmp, zone, _) {
    return { label: _('Stáhnout zónu (export)', 'Download the zone (export)'), on: function () { window.open(API.base + '/dns/zones/' + encodeURIComponent(zone.id) + '/export', '_blank', 'noopener'); } };
  }
  function tldPolicy(tld) { var t = (window.ONHOST_PANEL && window.ONHOST_PANEL.tlds) || []; for (var i = 0; i < t.length; i++) if (t[i].tld === tld) return t[i]; return null; }
  function personName() { var u = window.ONHOST && window.ONHOST.user; return (u && (u.name || u.email)) || ''; }
  var HOST = /^(?=.{3,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/;
  function hostOf(v) { return String(v || '').trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/.*$/, '').replace(/\.$/, ''); }

  /* ── registration tab: holder contact and transfer in ─────────────────── */
  function holderPanel(cmp, sel, _, X, d) {
    var r = d && d.registrant || {};
    var panel = X.infoPanel(_('Kontakt držitele · ', 'Holder contact · ') + sel.name, _('změníme ho u registrátora i u nás; jméno držitele, firmu a IČO tady změnit nejde — to je převod domény na jinou osobu a řeší se přes podporu', 'we change it at the registrar and here; the holder\'s name, company and company ID cannot be changed here — that is a transfer to another person and goes through support'), [
      [_('Držitel', 'Holder'), [r.name, r.organization_name].filter(Boolean).join(' · ') || '—'], [_('E-mail', 'E-mail'), r.email || '—'], [_('Telefon', 'Phone'), r.phone || '—'],
      [_('Adresa', 'Address'), [r.street, [r.postal_code, r.city].filter(Boolean).join(' '), r.country].filter(Boolean).join(', ') || '—']
    ]);
    panel.form = { title: _('Nové kontaktní údaje (vyplňte jen to, co se mění)', 'New contact details (fill in only what changes)'), fields: [
      X.F('a', _('e-mail', 'e-mail'), '1 1 200px'), X.F('b', _('telefon (+420.777123456)', 'phone (+420.777123456)'), '0 0 190px'), X.F('c', _('ulice a číslo', 'street and number'), '1 1 180px'),
      X.F('d', _('město', 'city'), '0 0 140px'), X.F('e', _('PSČ', 'postcode'), '0 0 90px'), X.F('f', _('země (CZ)', 'country (CZ)'), '0 0 90px')
    ], submit: _('Změnit kontakt', 'Change contact'), on: function () { submitHolder(cmp, sel, _, X, d); } };
    return panel;
  }
  function submitHolder(cmp, sel, _, X, d) {
    var s = X.s, v = function (k) { return String(s.wbF[k] || '').trim(); };
    var body = {}, map = { a: 'email', b: 'phone', c: 'street', d: 'city', e: 'postal_code', f: 'country' };
    Object.keys(map).forEach(function (k) { if (v(k)) body[map[k]] = v(k); });
    if (!Object.keys(body).length) { X.flash(cmp, _('Není co měnit', 'Nothing to change'), _('Vyplňte alespoň jeden údaj.', 'Fill in at least one field.')); return; }
    if (body.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(body.email)) { X.flash(cmp, _('E-mail nemá platný tvar', 'The e-mail is not valid'), ''); return; }
    if (body.country && !/^[A-Za-z]{2}$/.test(body.country)) { X.flash(cmp, _('Země musí mít dvě písmena', 'The country needs two letters'), _('Například CZ nebo SK.', 'For example CZ or SK.')); return; }
    if (!window.confirm(_('Změnit kontaktní údaje držitele u registrátora? Registr je může zveřejnit podle nastavení soukromí.', 'Change the holder\'s contact details at the registrar? The registry may publish them according to the privacy setting.'))) return;
    send(cmp, X, 'POST', '/domains/' + encodeURIComponent(d.id || sel.id) + '/holder', body, _('Kontakt držitele změněn', 'Holder contact changed'), function (r) {
      var n = r.domains_affected || 1;
      return _('Registrátor změnu přijal.', 'The registrar accepted the change.') + (n > 1 ? _(' Kontakt sdílí ', ' The contact is shared by ') + count(cmp, n, 'domain') + _('; změnil se u všech.', '; it changed for all of them.') : '');
    }).then(function () { clearForm(cmp); later(X, cmp, sel, ['domain'], [1000, 4000]); }).catch(function () {});
  }
  function transferPanel(cmp, sel, _, X) {
    var panel = X.infoPanel(_('Převod domény k nám', 'Transfer a domain to us'), _('pro doménu, kterou dnes máte u jiného registrátora; převod trvá podle koncovky hodiny až dny a doménu zároveň prodlouží', 'for a domain you hold at another registrar today; the transfer takes hours to days depending on the TLD and renews the domain at the same time'), [
      [_('1. U stávajícího registrátora', '1. At the current registrar'), _('odemkněte doménu (transfer lock) a vyžádejte AUTH-ID (převodní kód)', 'unlock the domain (transfer lock) and request the AUTH-ID (transfer code)')],
      [_('2. Tady', '2. Here'), _('zadejte celý název domény a AUTH-ID; převod potvrdíte druhým ověřením', 'enter the whole domain name and the AUTH-ID; you confirm the transfer with a second verification')],
      [_('3. Potom', '3. Then'), _('převod sledujte v sekci Domény; pokud ho registrátor vyžaduje, potvrďte ho e-mailem od něj', 'follow the transfer under Domains; if the registrar requires it, confirm it by the e-mail it sends')]
    ]);
    panel.form = { title: _('Zahájit převod', 'Start the transfer'), fields: [X.F('a', _('doména (firma.cz)', 'domain (company.cz)'), '1 1 220px'), X.F('b', _('AUTH-ID (převodní kód)', 'AUTH-ID (transfer code)'), '1 1 220px')], submit: _('Převést k nám', 'Transfer to us'), on: function () { submitTransfer(cmp, _, X); } };
    return panel;
  }
  function submitTransfer(cmp, _, X) {
    var s = X.s, fqdn = hostOf(s.wbF.a), code = String(s.wbF.b || '').trim();
    if (!HOST.test(fqdn)) { X.flash(cmp, _('Zadejte celý název domény', 'Enter the whole domain name'), _('Například firma.cz.', 'For example company.cz.')); return; }
    var tld = fqdn.split('.').slice(1).join('.'), policy = tldPolicy(tld) || tldPolicy(fqdn.split('.').pop());
    if (!policy) { X.flash(cmp, _('Tuto koncovku zatím nepřevádíme', 'We do not transfer this TLD yet'), _('Napište podpoře, poradíme.', 'Write to support and we will advise.')); return; }
    if (code.length < 4 || code.length > 64 || /\s/.test(code)) { X.flash(cmp, _('AUTH-ID nemá platný tvar', 'The AUTH-ID is not valid'), _('Opište ho přesně tak, jak ho vydal stávající registrátor (bez mezer).', 'Copy it exactly as the current registrar issued it (no spaces).')); return; }
    var person = personName();
    if (!person) { X.flash(cmp, _('Chybí jméno u účtu', 'The account has no name'), _('Doplňte ho v nastavení účtu; je součástí souhlasu.', 'Add it in the account settings; it is part of the consent.')); return; }
    var terms = [policy.registry_terms_url, policy.registrar_terms_url].filter(Boolean).join('\n');
    if (!window.confirm(_('Převést ' + fqdn + ' k ONhost a souhlasit s podmínkami registru a registrátora?', 'Transfer ' + fqdn + ' to ONhost and accept the registry and registrar terms?') + (terms ? '\n\n' + terms : ''))) return;
    var body = { fqdn: fqdn, auth_info: code, consent: { person: person, language: cs(cmp) ? 'cs' : 'en', registry_terms_url: policy.registry_terms_url || null, registrar_terms_url: policy.registrar_terms_url || null, accepted_at: new Date().toISOString() } };
    send(cmp, X, 'POST', '/domains/transfer-in', body, _('Převod zahájen', 'Transfer started'), _('Převod u registru běží; stav uvidíte v sekci Domény a oznámíme ho e-mailem.', 'The transfer is running at the registry; you will see the state under Domains and we will tell you by e-mail.'))
      .then(function () {
        clearForm(cmp);
        if (window.OnhostStore && window.OnhostStore.refresh) window.OnhostStore.refresh();
        setTimeout(function () { location.reload(); }, 3000);
      }).catch(function () {});
  }
  function regTab(cmp, sel, _, panel, X) {
    var info = X.info(), d = info && !info.__error ? info : null;
    var mode = modeOf(sel, 'reg', 'info');
    var tabs = chips(cmp, sel, 'reg', mode, [['info', _('Registrace', 'Registration')], ['holder', _('Kontakt držitele', 'Holder contact')], ['transfer', _('Převod k nám', 'Transfer to us')]], X);
    var out = mode === 'holder' ? (d ? holderPanel(cmp, sel, _, X, d) : panel) : (mode === 'transfer' ? transferPanel(cmp, sel, _, X) : panel);
    out.chips = tabs;
    return out;
  }

  /* ── DNS tab: versions and rollback, export, zones ────────────────────── */
  function versionsPanel(cmp, sel, _, X, zone) {
    var cell = X.H.cell, A = X.H.act;
    var list = X.load(sel, 'versions:' + zone.id, '/dns/zones/' + encodeURIComponent(zone.id) + '/versions');
    var rows;
    if (list === null) rows = [{ cells: [cell(_('načítám…', 'loading…'), '1 1 300px')], note: '' }];
    else if (list.__error) rows = [{ cells: [cell(_('Nelze načíst: ', 'Cannot load: ') + list.__error, '1 1 300px')], note: '' }];
    else rows = (Array.isArray(list) ? list : []).slice().sort(function (a, b) { return b.version - a.version; }).map(function (v) {
      var current = v.version === zone.version;
      return { cells: [cell('v' + v.version, '0 0 70px', 1), cell(String(v.serial || ''), '0 0 120px', 1), cell(count(cmp, v.records || 0, 'record'), '0 0 120px'), cell(v.reason || '—', '1 1 220px'), cell(X.since(cmp, v.committed_at), '0 0 130px', 1)],
        note: current ? _('aktuální verze', 'current version') : '', actions: current ? [] : [A(_('Vrátit na tuto verzi', 'Roll back to this version'), function () { rollback(cmp, sel, _, X, zone, v); })] };
    });
    var n = Array.isArray(list) ? list.length : 0;
    return { key: 'real:zversions', title: _('Verze zóny · ', 'Zone versions · ') + zone.name, note: _('každé publikování zóny je jedna verze; návrat na starší verzi vytvoří novou a hned ji publikuje', 'every publication of the zone is one version; rolling back creates a new one and publishes it at once'),
      state: Array.isArray(list) ? count(cmp, n, 'version') : '', head: [cell(_('Verze', 'Version'), '0 0 70px'), cell('Serial', '0 0 120px'), cell(_('Záznamů', 'Records'), '0 0 120px'), cell(_('Důvod', 'Reason'), '1 1 220px'), cell(_('Kdy', 'When'), '0 0 130px')], rows: rows.length ? rows : [{ cells: [cell(_('zatím žádná verze', 'no version yet'), '1 1 300px')], note: '' }],
      extra: [exportButton(cmp, zone, _), { label: _('Obnovit', 'Refresh'), on: function () { X.forget(sel, ['versions:' + zone.id]); X.rerender(cmp); } }] };
  }
  function rollback(cmp, sel, _, X, zone, v) {
    if ((zone.changes || []).length) { X.flash(cmp, _('Zóna má nepublikované změny', 'The zone has unpublished changes'), _('Nejdřív je v záložce Záznamy publikujte nebo zahoďte.', 'Publish or discard them first under Records.')); return; }
    if (!window.confirm(_('Vrátit zónu ' + zone.name + ' na verzi ' + v.version + '? Záznamy se nahradí záznamy z té verze a změna se hned publikuje. Stávající stav zůstane v historii.', 'Roll the zone ' + zone.name + ' back to version ' + v.version + '? The records are replaced by that version\'s and the change is published at once. The present state stays in the history.'))) return;
    send(cmp, X, 'POST', '/dns/zones/' + encodeURIComponent(zone.id) + '/rollback', { version: v.version }, _('Zóna vrácena na verzi ' + v.version, 'Zone rolled back to version ' + v.version), function (r) {
      return _('Vznikla nová verze ' + (r.version || '') + '; rozšíření po internetu trvá až TTL.', 'Version ' + (r.version || '') + ' was created; propagation takes up to the TTL.');
    }).then(function () { later(X, cmp, sel, ['zone', 'zone:' + zone.name, 'versions:' + zone.id, 'domain'], [500, 3000]); }).catch(function () {});
  }

  function zonesPanel(cmp, sel, _, X, onhostDns) {
    var cell = X.H.cell, A = X.H.act, F = X.H.F, s = X.H.s;
    var list = X.load(sel, 'zones', '/dns/zones');
    var rows;
    if (list === null) rows = [{ cells: [cell(_('načítám…', 'loading…'), '1 1 300px')], note: '' }];
    else if (list.__error) rows = [{ cells: [cell(_('Nelze načíst: ', 'Cannot load: ') + list.__error, '1 1 300px')], note: '' }];
    else rows = (Array.isArray(list) ? list : []).map(function (z) { return zoneRow(cmp, sel, _, X, z, cell, A); });
    var n = Array.isArray(list) ? list.length : 0;
    return { key: 'real:zones', title: _('Moje DNS zóny', 'My DNS zones'), note: _('zóny vaší organizace; zóna domény u nás vznikne sama, samostatnou zónu založíte níže (jmenné servery pak nastavíte u registrátora domény)', 'the zones of your organization; a domain\'s zone appears by itself, a standalone zone you create below (then set the nameservers at the domain\'s registrar)') + (onhostDns ? '' : _(' · tato doména má DNS jinde', ' · this domain has its DNS elsewhere')),
      state: Array.isArray(list) ? count(cmp, n, 'zone') : '', head: [cell(_('Zóna', 'Zone'), '1 1 220px'), cell(_('Stav', 'State'), '0 0 100px'), cell(_('Verze', 'Version'), '0 0 80px')], rows: rows.length ? rows : [{ cells: [cell(_('zatím žádná zóna', 'no zone yet'), '1 1 300px')], note: '' }],
      form: { title: _('Založit samostatnou zónu', 'Create a standalone zone'), fields: [F('a', _('název zóny (priklad.cz)', 'zone name (example.com)'), '1 1 260px')], submit: _('Založit', 'Create'), on: function () { createZone(cmp, sel, _, X, s); } },
      extra: [{ label: _('Obnovit', 'Refresh'), on: function () { X.forget(sel, ['zones']); X.rerender(cmp); } }] };
  }
  function zoneRow(cmp, sel, _, X, z, cell, A) {
    var states = { active: _('aktivní', 'active'), pending: _('zakládá se', 'being created'), error: _('chyba', 'error') };
    return { cells: [cell(z.name, '1 1 220px', 1), cell(states[z.state] || String(z.state || ''), '0 0 100px'), cell('v' + (z.version || 0), '0 0 80px', 1)], note: z.domain_id ? _('zóna domény', 'a domain\'s zone') : _('samostatná zóna', 'standalone zone'),
      actions: [A(_('Otevřít', 'Open'), function () { ui.zone[sel.id] = z.name; ui.mode[sel.id + ':dns'] = 'records'; X.forget(sel, ['zone:' + z.name]); X.rerender(cmp); }),
        A(_('Export', 'Export'), function () { window.open(API.base + '/dns/zones/' + encodeURIComponent(z.id) + '/export', '_blank', 'noopener'); }),
        A(_('Smazat', 'Delete'), function () { deleteZone(cmp, sel, _, X, z); })] };
  }
  function createZone(cmp, sel, _, X, s) {
    var name = hostOf(s.wbF.a);
    if (!HOST.test(name)) { X.flash(cmp, _('Zadejte název zóny', 'Enter the zone name'), _('Celý název, například priklad.cz.', 'The whole name, for example example.com.')); return; }
    send(cmp, X, 'POST', '/dns/zones', { name: name }, _('Zóna založena', 'Zone created'), function (r) {
      var ns = (r.nameservers || []).join(', ');
      return _('Zóna ' + name + ' je připravená. ', 'The zone ' + name + ' is ready. ') + (ns ? _('Jmenné servery nastavte u registrátora domény: ', 'Set the nameservers at the domain\'s registrar: ') + ns : '');
    }).then(function () { clearForm(cmp); later(X, cmp, sel, ['zones'], [300, 3000]); }).catch(function () {});
  }
  function deleteZone(cmp, sel, _, X, z) {
    if (!window.confirm(_('Smazat zónu ' + z.name + '? Zmizí všechny záznamy i historie verzí a zóna přestane odpovídat. Tuto akci nelze vrátit.', 'Delete the zone ' + z.name + '? All records and the version history are gone and the zone stops answering. This cannot be undone.'))) return;
    var reason = encodeURIComponent('zrušeno zákazníkem v panelu');
    send(cmp, X, 'DELETE', '/dns/zones/' + encodeURIComponent(z.id) + '?reason=' + reason, null, _('Zóna smazána', 'Zone deleted'), _('Zóna ' + z.name + ' je pryč i z jmenných serverů.', 'The zone ' + z.name + ' is gone from the nameservers too.'))
      .then(function () { if (ui.zone[sel.id] === z.name) delete ui.zone[sel.id]; later(X, cmp, sel, ['zones', 'zone:' + z.name], [300, 3000]); }).catch(function () {});
  }

  function dnsTab(cmp, sel, _, panel, X) {
    var over = ui.zone[sel.id] || null;
    var mode = modeOf(sel, 'dns', over || X.onhostDns ? 'records' : 'zones');
    var back = over ? [{ label: _('← zpět na ', '← back to ') + sel.name, active: false, on: function () { delete ui.zone[sel.id]; ui.mode[sel.id + ':dns'] = 'records'; X.rerender(cmp); } }] : [];
    var tabs = back.concat(chips(cmp, sel, 'dns', mode, (over || X.onhostDns ? [['records', _('Záznamy', 'Records')], ['versions', _('Verze a návrat', 'Versions and rollback')]] : []).concat([['zones', _('Moje zóny', 'My zones')]]), X));
    var out;
    if (mode === 'zones') out = zonesPanel(cmp, sel, _, X, X.onhostDns);
    else {
      var z = X.zone(), zone = z && !z.__error ? z : null;
      if (mode === 'versions' && zone) out = versionsPanel(cmp, sel, _, X, zone);
      else {
        out = panel;
        if (zone && Array.isArray(out.extra)) out.extra = out.extra.concat([exportButton(cmp, zone, _)]);
        if (over && zone) { out.title = _('DNS záznamy · ', 'DNS records · ') + zone.name; out.note = _('Upravujete samostatnou zónu, která nepatří k doméně ', 'You are editing a standalone zone that does not belong to the domain ') + sel.name + ' · ' + (out.note || ''); }
      }
    }
    out.chips = tabs;
    return out;
  }

  /* ── hook called by the workbench's domainBuild ───────────────────────── */
  function enhance(cmp, sel, tab, _, panel, X) {
    if (!panel) return panel;
    if (tab === 'reg') return regTab(cmp, sel, _, panel, X);
    if (tab === 'dns') return dnsTab(cmp, sel, _, panel, X);
    return panel;
  }

  window.OnhostPanelDomains = { enhance: enhance, errorText: errorText, activeZone: function (sel) { return ui.zone[sel.id] || null; }, state: ui, count: count };
})();
