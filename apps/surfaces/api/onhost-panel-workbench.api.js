/* ONhost panel — per-service workbench seams (docs/ui/data-seams.md #18).
 *
 * The prototype opens a "workbench" for every service with tabs (domain, PHP, SSL, databases, FTP, cron, backups,
 * console, snapshots, mailboxes …) that edit local state. On the API-backed panel the tabs a service shows come from
 * GET /v1/services/{id}/features — what the executor behind the service really offers (never named to the customer)
 * plus the plan's limits — and every table is a live listing (GET …/resources/{kind}) with real actions
 * (POST …/actions). Tabs whose feature the executor does not offer are simply not shown. The surface stays
 * byte-identical: SurfaceRenderer points `svTabs` and `WB_BUILD` at tabs()/build() below. */
(function () {
  if (window.OnhostPanelWorkbench) return; // the prototype runtime executes helmet scripts twice
  var API = window.OnhostApi;
  var state = { features: {}, resources: {}, ops: {}, summary: {}, pending: {}, tick: 0 };

  function cs(cmp) { return !cmp || !cmp.state || cmp.state.lang !== 'en'; }
  function T(cmp) { var c = cs(cmp); return function (a, b) { return c ? a : b; }; }
  function real(sel) { return !!(window.ONHOST_PANEL && sel && sel.apiState); }
  function family(sel) { return { web: 'web', vps: 'cloud', mc: 'game', cs2: 'game', mail: 'mail', domain: 'domain' }[sel.type] || null; }
  function rerender(cmp) { try { cmp.setState({ wbTick: ++state.tick }); } catch (e) { /* not mounted */ } }
  function flash(cmp, t, b) { if (cmp && typeof cmp.flash === 'function') cmp.flash(t, b); }
  function since(cmp, iso) { if (!iso) return ''; var d = new Date(iso); return isNaN(d) ? '' : d.toLocaleString(cs(cmp) ? 'cs-CZ' : 'en-GB', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' }); }
  function password(len) { var a = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-_.'; var out = ''; var buf = new Uint32Array(len || 20); (window.crypto || window.msCrypto).getRandomValues(buf); for (var i = 0; i < buf.length; i++) out += a[buf[i] % a.length]; return out; }

  /* ── data access ─────────────────────────────────────────────────────── */
  function load(key, path, cmp, after) {
    if (state.pending[key]) return;
    state.pending[key] = true;
    API.get(path).then(function (r) { after(r.data || r); }).catch(function (e) { after({ __error: e.message || 'error', __status: e.status }); })
      .then(function () { delete state.pending[key]; rerender(cmp); });
  }
  function features(cmp, sel) {
    var f = state.features[sel.id];
    if (f === undefined) { state.features[sel.id] = null; load('f:' + sel.id, '/services/' + sel.id + '/features', cmp, function (d) { state.features[sel.id] = d; }); }
    return f || null;
  }
  /* the service at a glance (GET /services/{id} → summary): plan and billing, project, certificate, last backup, monitor, operations */
  function summary(cmp, sel, fresh) {
    var s = state.summary[sel.id];
    if (s === undefined || fresh) { if (s === undefined) state.summary[sel.id] = null; load('s:' + sel.id, '/services/' + sel.id, cmp, function (d) { state.summary[sel.id] = d && d.summary ? d.summary : { __error: (d && d.__error) || 'error' }; }); }
    return s === undefined ? null : s;
  }
  function summaryPairs(sm, cmp, _) {
    if (!sm || sm.__error) return [];
    var day = function (iso) { return iso ? new Date(iso).toLocaleDateString('cs-CZ') : ''; };
    var money = function (m) { var n = m && typeof m === 'object' ? parseFloat(m.decimal != null ? m.decimal : (m.minor || 0) / 100) : (parseFloat(m) || 0); return typeof cmp.money === 'function' ? cmp.money(n) : (Math.round(n).toLocaleString('cs-CZ') + ' Kč'); };
    var b = sm.billing, per = b ? (b.period === 'year' ? _('ročně', 'yearly') : _('měsíčně', 'monthly')) : '';
    var renewal = !b ? _('bez předplatného', 'no subscription') : (b.cancel_at_period_end ? _('končí ', 'ends ') + day(b.current_period_end) + _(' · obnova zrušena', ' · renewal cancelled') : (b.next_renewal_at ? _('obnova ', 'renews ') + day(b.next_renewal_at) : _('období do ', 'period until ') + day(b.current_period_end)) + ' · ' + money(b.amount) + (b.period === 'year' ? _(' / rok', ' / year') : _(' / měsíc', ' / month')) + (b.auto_renew ? _(' · automaticky z kreditu', ' · automatically from credit') : _(' · ručně', ' · manually')) + (b.renewal_failures ? _(' · obnova selhala ' + b.renewal_failures + '×', ' · renewal failed ' + b.renewal_failures + '×') : ''));
    var cert = { issued: _('vystavený', 'issued'), requested: _('žádáme o vystavení', 'being issued'), pending_dns: _('čeká na DNS domény', 'waiting for the domain DNS'), failed: _('vystavení selhalo', 'issuing failed') };
    var mon = sm.monitor ? ({ up: _('běží', 'up'), down: _('nedostupný', 'down'), pending: _('první kontrola', 'first check') }[sm.monitor.state] || sm.monitor.state) + (sm.monitor.last_ms != null ? ' · ' + sm.monitor.last_ms + ' ms' : '') + (sm.monitor.last_checked_at ? ' · ' + new Date(sm.monitor.last_checked_at).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' }) : '') : _('bez monitoru', 'no monitor');
    var ops = sm.operations || {};
    var usage = sm.usage && sm.usage.metrics ? Object.keys(sm.usage.metrics).map(function (k) { var m = sm.usage.metrics[k]; return ({ disk: _('prostor', 'disk'), traffic: _('přenos', 'traffic'), memory: _('paměť', 'memory') }[k] || k) + ' ' + m.pct + ' %'; }).join(' · ') : '';
    var usageLevel = sm.usage ? sm.usage.level : null;
    return [
      [_('Tarif', 'Plan'), sm.plan && sm.plan.name ? sm.plan.name + (per ? ' · ' + per : '') + (sm.plan.sla_class && sm.plan.sla_class !== 'standard' ? ' · SLA ' + sm.plan.sla_class : '') : '—'],
      [_('Využití tarifu', 'Plan usage'), usage ? usage + (usageLevel === 'critical' ? _(' · téměř vyčerpáno', ' · nearly used up') : (usageLevel === 'warn' ? _(' · blíží se limitu', ' · near the limit') : '')) : _('zatím neměřeno', 'not measured yet')],
      [_('Automatické navýšení tarifu', 'Automatic plan upgrade'), sm.policy && sm.policy.auto_upgrade ? _('zapnuto · při 95 % objednáme vyšší tarif z kreditu', 'on · at 95 % the next plan is ordered from credit') : _('vypnuto · při 95 % jen upozorníme', 'off · at 95 % you are only told')],
      [_('Platba a obnova', 'Billing and renewal'), renewal],
      [_('Projekt', 'Project'), sm.project ? sm.project.name : _('bez projektu', 'no project')],
      [_('Certifikát', 'Certificate'), sm.certificate ? (cert[sm.certificate] || sm.certificate) : _('—', '—')],
      [_('Poslední záloha', 'Last backup'), sm.backup ? day(sm.backup.last_at) + (sm.backup.offsite ? _(' · offsite kopie', ' · offsite copy') : '') + (sm.backup.verified ? _(' · ověřená', ' · verified') : '') + (sm.backups_count ? ' · ' + sm.backups_count + _(' záloh', ' backups') : '') : _('zatím žádná', 'none yet')],
      [_('Monitoring', 'Monitoring'), mon],
      [_('Operace', 'Operations'), (ops.active ? _('probíhá ', 'in progress ') + ops.active : _('klid', 'idle')) + (ops.last_failed ? _(' · poslední selhání ', ' · last failure ') + (ops.last_failed.kind || '') + ' ' + day(ops.last_failed.finished_at) : '')]
    ];
  }
  function resource(cmp, sel, kind, fresh) {
    var key = sel.id + ':' + kind;
    var r = state.resources[key];
    if (r === undefined || fresh) {
      if (r === undefined) state.resources[key] = null;
      load('r:' + key, '/services/' + sel.id + '/resources/' + kind + (fresh ? '?fresh=1' : ''), cmp, function (d) { state.resources[key] = d; });
    }
    return r === undefined ? null : r;
  }
  /* vhost settings shared by the error-page, directive, database-admin and statistics tabs: null while loading, {__error} on failure */
  function settings(cmp, sel) { var r = resource(cmp, sel, 'site_settings'); return r === null ? null : (r && typeof r === 'object' ? r : { __error: 'invalid' }); }
  /* chargeback in credit: the open or last request, the share in force and what a cancellation now would return */
  function chargebackInfo(cmp, sel, fresh) {
    state.cb = state.cb || {};
    var r = state.cb[sel.id];
    if (r === undefined || fresh) { if (r === undefined) state.cb[sel.id] = null; load('cb:' + sel.id, '/services/' + sel.id + '/chargeback', cmp, function (d) { state.cb[sel.id] = d && d.data ? d.data : d; }); }
    return r === undefined ? null : r;
  }
  function operations(cmp, sel, fresh) {
    var r = state.ops[sel.id];
    if (r === undefined || fresh) { if (r === undefined) state.ops[sel.id] = null; load('o:' + sel.id, '/services/' + sel.id + '/operations', cmp, function (d) { state.ops[sel.id] = Array.isArray(d) ? d : (d.data || d); }); }
    return r === undefined ? null : r;
  }
  function forget(sel, kinds) { (kinds || []).forEach(function (k) { delete state.resources[sel.id + ':' + k]; }); delete state.ops[sel.id]; delete state.summary[sel.id]; }

  /* One customer action = one operation; the listing that changed is re-read after the executor had time to act. */
  function act(cmp, sel, action, params, kinds, okTitle, okBody) {
    var _ = T(cmp);
    return API.post('/services/' + sel.id + '/actions', { action: action, params: params || {} }, API.key()).then(function (r) {
      var d = r.data || r;
      flash(cmp, okTitle || _('Požadavek přijat', 'Request accepted'), (okBody || _('Provedeme ho během chvíle; průběh je v záložce Provoz a NOC.', 'It runs in a moment; progress is under Operations and NOC.')) + (d.operation_id ? ' · ' + d.operation_id.slice(-6) : ''));
      cmp.setState({ wbF: { a: '', b: '', c: '' } });
      [2500, 8000].forEach(function (ms) { setTimeout(function () { forget(sel, kinds); rerender(cmp); }, ms); });
      return d;
    }).catch(function (e) {
      flash(cmp, _('Akce neproběhla', 'Action failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.'));
      throw e;
    });
  }

  /* ── tabs ────────────────────────────────────────────────────────────── */
  var TABS = {
    web: [['cfg', 'site'], ['redirect', 'redirects'], ['errpages', 'errpages'], ['directives', 'directives'], ['run', 'php'], ['ssl', 'ssl'], ['dbs', 'databases'], ['dbusers', 'db_users'], ['dbadmin', 'db_admin'], ['ssh', 'shell'], ['ftpusers', 'ftp'], ['protected', 'protected'], ['cron', 'cron'], ['files', 'files'], ['apps', 'apps'], ['bkp', 'backups'], ['quota', 'usage'], ['stats', 'stats'], ['mon', 'logs'], ['noc', 'operations']],
    cloud: [['console', 'console'], ['tasks', 'operations'], ['snap', 'snapshots'], ['disks', 'disks'], ['net', 'firewall'], ['bkp', 'backups'], ['mon', 'usage'], ['noc', 'operations']],
    game: [['console', 'console'], ['startup', 'startup'], ['sched', 'schedules'], ['settings', 'game_settings'], ['files', 'game_files'], ['world', 'backups'], ['bkp', 'backups'], ['dbs', 'game_databases'], ['net', 'network'], ['users', 'subusers'], ['plan', 'resize'], ['mon', 'usage'], ['noc', 'operations']],
    mail: [['boxes', 'mailboxes'], ['alias', 'aliases'], ['auth', 'dkim'], ['noc', 'operations']],
    domain: [['dns', 'zone'], ['soa', 'nameservers'], ['reg', 'registration'], ['sec', 'dnssec'], ['noc', 'operations']]
  };
  function tabs(cmp, sel, SV) {
    if (!real(sel)) return null;
    var fam = family(sel), all = SV[sel.type] || SV.web;
    if (!fam) return null;
    if (fam === 'domain') { // domains are not services: DNS tabs only when the zone lives at ONhost, registration and DNSSEC always
      var wantD = ['reg', 'soa', 'sec', 'noc'];
      if (sel.dns_provider === 'onhost') wantD.push('dns');
      var outD = all.filter(function (t) { return wantD.indexOf(t.id) >= 0; });
      return outD.length ? outD : all.slice(0, 1);
    }
    var f = features(cmp, sel);
    var wanted = TABS[fam].filter(function (pair) { return pair[1] === 'operations' || (f ? !!(f.features[pair[1]] && f.features[pair[1]].enabled) : pair[0] === 'cfg' || pair[0] === 'console' || pair[0] === 'boxes'); }).map(function (p) { return p[0]; });
    var out = all.filter(function (t) { return wanted.indexOf(t.id) >= 0; });
    if (!out.length) out = all.slice(0, 1);
    return out;
  }

  /* ── panels ──────────────────────────────────────────────────────────── */
  function buildCore(cmp, sel, tab, _) {
    if (!real(sel)) return null;
    var H = cmp.WB_H(sel), fam = family(sel), f = fam === 'domain' ? { features: {} } : features(cmp, sel);
    var cell = H.cell, A = H.act, F = H.F, s = H.s;
    var loading = { title: sel.name, note: _('načítám nastavení služby…', 'loading the service…'), state: '', head: [], rows: [] };
    if (!f) return loading;
    if (f.__error) return { title: sel.name, note: _('Nastavení služby se nepodařilo načíst.', 'The service settings could not be loaded.'), state: 'error', head: [], rows: [{ cells: [cell(f.__error, '1 1 300px')], note: '' }] };
    var on = function (k) { return !!(f.features[k] && f.features[k].enabled); };
    var limit = function (k) { return f.features[k] && f.features[k].limit; };
    var listPanel = function (kind, title, note, head, mapRow, form, extra, chips) {
      var data = resource(cmp, sel, kind);
      var rows = data === null ? [{ cells: [cell(_('načítám…', 'loading…'), '1 1 300px')], note: '' }] : (data.__error ? [{ cells: [cell(_('Nelze načíst: ', 'Cannot load: ') + data.__error, '1 1 300px')], note: '' }] : (Array.isArray(data) ? data : []).map(mapRow));
      if (data !== null && !data.__error && Array.isArray(data) && !data.length) rows = [{ cells: [cell(_('zatím nic', 'nothing yet'), '1 1 300px')], note: '' }];
      return { key: 'real:' + kind, title: title, note: note, state: Array.isArray(data) ? data.length + (limit(kind) ? ' / ' + limit(kind) : '') : '', head: head, rows: rows, form: form, extra: (extra || []).concat([{ label: _('Obnovit', 'Refresh'), on: function () { resource(cmp, sel, kind, true); } }]), chips: chips };
    };
    var infoPanel = function (title, note, pairs, extra) {
      return { key: 'real:info', title: title, note: note, state: '', head: [cell(_('Parametr', 'Parameter'), '1 1 220px'), cell(_('Hodnota', 'Value'), '1 1 260px', 1)], rows: pairs.map(function (p) { return { cells: [cell(p[0], '1 1 220px'), cell(p[1] == null ? '—' : String(p[1]), '1 1 260px', 1)], note: p[2] || '' }; }), extra: extra || [] };
    };
    var unavailable = function (title) { return { key: 'real:na', title: title, note: _('Tuto funkci služba v aktuálním tarifu nenabízí. Napište podpoře, pokud ji potřebujete.', 'This feature is not offered on the current plan. Write to support if you need it.'), state: '', head: [], rows: [] }; };
    var passwordField = function (k) { return F(k, _('heslo (min. 12 znaků) · tlačítko vygeneruje', 'password (min. 12 chars) · button generates'), '1 1 220px'); };
    var genExtra = function (k) { return { label: _('Vygenerovat heslo', 'Generate password'), on: function () { var p = password(20); var next = Object.assign({}, s.wbF); next[k] = p; cmp.setState({ wbF: next }); flash(cmp, _('Heslo vygenerováno', 'Password generated'), _('Uložte si ho teď — po odeslání ho už nikde nezobrazíme: ', 'Save it now — it is never shown again after submitting: ') + p); } }; };

    if (fam === 'domain') return domainBuild(cmp, sel, tab, _, { H: H, infoPanel: infoPanel, unavailable: unavailable, loading: loading });

    /* operations (all families) */
    if (tab === 'noc' || tab === 'tasks') {
      var ops = operations(cmp, sel);
      var rows = ops === null ? [{ cells: [cell(_('načítám…', 'loading…'), '1 1 300px')], note: '' }] : (ops.__error ? [{ cells: [cell(ops.__error, '1 1 300px')], note: '' }] : (ops.length ? ops : []).map(function (o) {
        var st = { SUCCEEDED: 'ok', FAILED: 'chyba', RUNNING: 'běží', PENDING: 'čeká', WAITING: 'čeká na provider', CANCELLED: 'zrušeno' };
        var stalled = o.state === 'WAITING' && o.error && o.error.retryable; // a transient node error: the platform retries on its own
        return { cells: [cell(o.kind + (o.step_label ? ' · ' + o.step_label : ''), '1 1 220px'), cell(stalled ? _('trvá déle než obvykle · zkoušíme znovu', 'taking longer than usual · retrying') : _(st[o.state] || o.state, o.state.toLowerCase()), '0 0 110px'), cell(since(cmp, o.finished_at || o.started_at || o.queued_at), '0 0 130px', 1)], note: o.error && o.error.message ? (stalled ? _('Uzel dočasně neodpověděl: ', 'The node did not answer for a moment: ') : '') + o.error.message : '' };
      }));
      if (ops !== null && !ops.__error && !ops.length) rows = [{ cells: [cell(_('zatím žádné operace', 'no operations yet'), '1 1 300px')], note: '' }];
      var cb = chargebackInfo(cmp, sel); // chargeback in credit: request → support decides → cancel and get the share back
      if (cb && Array.isArray(rows)) {
        var req = cb.request, est = cb.estimate || {}, pct = cb.percent;
        var fmtM = function (m) { return m && m.minor != null ? (Math.round(m.minor) / 100).toLocaleString('cs-CZ') + ' ' + (m.currency || '') : '—'; };
        var closed = !req || ['refunded', 'rejected', 'withdrawn'].indexOf(req.state) >= 0;
        var cbState = closed ? (req && req.state === 'refunded' ? _('kredit vrácen ', 'credit returned ') + fmtM(req.refund) : (req && req.state === 'rejected' ? _('zamítnuto podporou', 'rejected by support') : _('bez otevřené žádosti', 'no open request'))) : ({ requested: _('čeká na technickou podporu', 'waiting for technical support'), approved: _('schváleno · službu můžete zrušit', 'approved · you may cancel the service'), cancelling: _('rušíme službu, kredit připíšeme po dokončení', 'cancelling; the credit follows') }[req.state] || req.state);
        var cbActs = [];
        if (closed) cbActs.push(A(_('Požádat o vrácení kreditu', 'Request a credit refund'), function () {
          var reason = window.prompt(_('Proč chcete službu opustit? Podpora žádost posoudí; po schválení službu zrušíte a ' + pct + ' % nevyužitého zaplaceného období se vrátí jako kredit.', 'Why do you want to leave the service? Support reviews it; after the approval you cancel and ' + pct + ' % of the unused paid period comes back as credit.'), '');
          if (!reason) return;
          API.post('/services/' + sel.id + '/chargeback', { reason: reason }, API.key()).then(function () { chargebackInfo(cmp, sel, true); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
        }));
        if (req && req.state === 'approved') cbActs.push(A(_('Zrušit službu a vrátit ' + fmtM(req.refund), 'Cancel the service and get ' + fmtM(req.refund)), function () {
          if (!window.confirm(_('Služba bude nevratně zrušena (po závěrečné záloze) a ' + fmtM(req.refund) + ' se připíše jako kredit. Pokračovat?', 'The service is cancelled for good (after a final backup) and ' + fmtM(req.refund) + ' is credited. Continue?'))) return;
          API.post('/services/' + sel.id + '/chargeback/cancel', {}, API.key()).then(function () { chargebackInfo(cmp, sel, true); operations(cmp, sel, true); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
        }));
        rows.push({ cells: [cell(_('Vrácení kreditu (chargeback)', 'Credit refund (chargeback)'), '1 1 220px'), cell(cbState, '0 0 260px'), cell(_('nyní by se vrátilo ', 'would return now ') + fmtM(est.refund_minor != null ? { minor: est.refund_minor, currency: est.currency } : null) + ' (' + pct + ' %)', '1 1 220px', 1)], note: req && req.decision_reason ? req.decision_reason : (req && req.reason && !closed ? req.reason : ''), actions: cbActs });
      }
      var mg = sel.migration && sel.migration.operation_id ? sel.migration : null; // a migration: scheduled (the customer may move it), running, finished or failed (audit §5h-3, §5i-7)
      if (mg && mg.state && mg.state !== 'scheduled') {
        var whenM = function (v) { return v ? new Date(v).toLocaleString('cs-CZ', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'; };
        var mgText = mg.state === 'finished' ? _('přestěhováno na ', 'moved to ') + (mg.to_node || '—') + (mg.address ? _(' · adresa ', ' · address ') + mg.address : '') : (mg.state === 'failed' ? _('stěhování selhalo · server zůstal, kde byl', 'migration failed · the server stayed where it was') : _('probíhá: ', 'in progress: ') + (mg.step_label || ''));
        rows.unshift({ cells: [cell(_('Stěhování serveru', 'Server migration'), '1 1 220px'), cell(mgText, '1 1 300px'), cell(whenM(mg.finished_at || mg.failed_at || mg.starts_at), '0 0 130px', 1)], note: mg.reason || '', actions: [] });
      } else if (mg) {
        var when = function (v) { return v ? new Date(v).toLocaleString('cs-CZ', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'; };
        rows.unshift({ cells: [cell(_('Plánované stěhování serveru', 'Scheduled server migration'), '1 1 220px'), cell(_('začne ', 'starts ') + when(mg.starts_at), '0 0 170px'), cell(_('okno ', 'window ') + when(mg.from) + ' – ' + when(mg.to), '1 1 240px', 1)], note: (mg.reason || '') + (mg.chosen_at ? _(' · termín jste zvolili', ' · you chose the time') : _(' · termín můžete posunout', ' · you may move the time')), actions: [A(_('Změnit termín', 'Change the time'), function () {
          var v = window.prompt(_('Kdy má stěhování začít? (RRRR-MM-DD HH:MM, v okně výše; server bude po dobu přenosu vypnutý)', 'When should the migration start? (YYYY-MM-DD HH:MM, within the window; the server is off while the data moves)'), (mg.starts_at || '').slice(0, 16).replace('T', ' '));
          if (!v) return;
          API.put('/services/' + sel.id + '/migration', { starts_at: v.trim().replace(' ', 'T') }).then(function (r) { var m = r && r.migration ? r.migration : null; if (m) { sel.migration = m; } window.alert(_('Termín uložen: ', 'Time saved: ') + when(m ? m.starts_at : v)); operations(cmp, sel, true); }).catch(function (e) { window.alert((e && e.message) || 'error'); });
        })] });
      }
      return { key: 'real:ops', title: _('Provoz · ', 'Operations · ') + sel.name, note: _('každý zásah do služby je operace s auditem; selhání zkoušíme znovu a hlásíme', 'every change is an audited operation; failures are retried and reported'), state: Array.isArray(ops) ? ops.length + ' ' + _('operací', 'operations') : '', head: [cell(_('Operace', 'Operation'), '1 1 220px'), cell(_('Stav', 'State'), '0 0 110px'), cell(_('Čas', 'Time'), '0 0 130px')], rows: rows, extra: [{ label: _('Obnovit', 'Refresh'), on: function () { operations(cmp, sel, true); } }] };
    }

    if (fam === 'web') {
      if (tab === 'cfg') {
        var subs = on('subdomains') ? resource(cmp, sel, 'subdomains') : [];
        var cert = on('ssl') ? resource(cmp, sel, 'certificate') : null;
        var pairs = [[_('Doména', 'Domain'), sel.meta ? sel.meta.split(' · ')[0] : sel.name], [_('Stav', 'State'), sel.state], [_('Lokalita', 'Location'), sel.meta ? sel.meta.split(' · ')[1] || '' : ''], [_('Verze PHP', 'PHP version'), (resource(cmp, sel, 'php') || {}).current || '—'], [_('HTTPS', 'HTTPS'), cert === null ? _('načítám…', 'loading…') : (cert && cert.issued ? (cert.letsencrypt ? "Let's Encrypt" : _('vlastní certifikát', 'own certificate')) + (cert.https_forced ? ' · ' + _('vynucené', 'forced') : '') : _('bez certifikátu', 'no certificate'))]];
        var sm = summary(cmp, sel);
        pairs = pairs.concat(sm === null ? [[_('Tarif a platba', 'Plan and billing'), _('načítám…', 'loading…')]] : summaryPairs(sm, cmp, _));
        var rowsSub = (Array.isArray(subs) ? subs : []).map(function (d) { return { cells: [cell(_('Další doména', 'Extra domain'), '1 1 220px'), cell(d.domain + (d.path ? ' → ' + d.path : ''), '1 1 260px', 1)], note: '', actions: [A(_('Odebrat', 'Remove'), function () { act(cmp, sel, 'subdomain.remove', { remote_id: d.remote_id }, ['subdomains']); })] }; });
        var panel = infoPanel(_('Doména a nastavení · ', 'Domain and settings · ') + sel.name, _('další domény a subdomény směřují na stejný web; změny platí do minuty', 'extra domains and subdomains point at the same site; changes apply within a minute'), pairs);
        // the billing row carries the one-click switch of the billing period (a period-change order line, seam #31)
        if (sm && sm.billing && sm.billing.period && window.OnhostPanelTools && window.OnhostPanelTools.switchPeriod && real(sel)) {
          var other = sm.billing.period === 'year' ? 'month' : 'year', billingLabel = _('Platba a obnova', 'Billing and renewal');
          panel.rows.forEach(function (row) {
            if (row.cells && row.cells[0] && row.cells[0].t === billingLabel) row.actions = [A(other === 'year' ? _('Platit ročně', 'Bill yearly') : _('Platit měsíčně', 'Bill monthly'), function () { window.OnhostPanelTools.switchPeriod(cmp, sel, _, helpers(), other); })];
          });
        }
        // onboarding checklist (audit §5e-4): what the site still lacks, each item opening the tab that completes it
        if (sm && !sm.__error && Array.isArray(sm.checklist) && sm.checklist.length) {
          var open = sm.checklist.filter(function (c) { return !c.done; });
          panel.rows = panel.rows.concat(sm.checklist.map(function (c) {
            var row = { cells: [cell((c.done ? '✓ ' : '○ ') + _(c.label, c.label_en), '1 1 220px'), cell(_(c.hint, c.hint_en), '1 1 260px', 1)], note: '' };
            if (!c.done && c.tab) row.actions = [A(_('Nastavit', 'Set up'), function () { cmp.setState({ svcTab: c.tab }); })];
            return row;
          }));
          if (!open.length) panel.note = panel.note + ' · ' + _('vše ze základního nastavení je hotové', 'the basic setup is complete');
          else panel.state = (panel.state ? panel.state + ' · ' : '') + open.length + ' ' + _(open.length === 1 ? 'krok k dokončení' : (open.length < 5 ? 'kroky k dokončení' : 'kroků k dokončení'), open.length === 1 ? 'step to finish' : 'steps to finish');
        }
        // usage watch (seam #31): a nudge to the plans when the plan is nearly used up, and the auto-upgrade policy switch
        if (sm && !sm.__error && real(sel)) {
          var usageLabel = _('Využití tarifu', 'Plan usage'), policyLabel = _('Automatické navýšení tarifu', 'Automatic plan upgrade');
          panel.rows.forEach(function (row) {
            if (!row.cells || !row.cells[0]) return;
            if (row.cells[0].t === usageLabel && sm.usage && sm.usage.level && sm.usage.level !== 'ok') row.actions = [A(_('Zvýšit tarif', 'Upgrade the plan'), function () { cmp.setState({ svcTab: 'quota' }); })];
            if (row.cells[0].t === policyLabel) row.actions = [A(sm.policy && sm.policy.auto_upgrade ? _('Vypnout', 'Turn off') : _('Zapnout', 'Turn on'), function () {
              var next = !(sm.policy && sm.policy.auto_upgrade);
              if (next && !window.confirm(_('Při 95 % využití objednáme vyšší tarif automaticky a uhradíme ho z kreditu (doplatek jen za zbytek období). Pokračovat?', 'At 95 % usage the next plan is ordered automatically and paid from credit (only the rest of the period). Continue?'))) return;
              window.OnhostApi.put('/services/' + encodeURIComponent(sel.id) + '/policy', { auto_upgrade: next }).then(function () { forget(sel, []); rerender(cmp); flash(cmp, _('Nastavení uloženo', 'Setting saved'), next ? _('Vyšší tarif objednáme sami, až bude potřeba.', 'The next plan is ordered for you when needed.') : _('Při plné kapacitě vás jen upozorníme.', 'You are only told when the plan is full.')); }).catch(function (e) { flash(cmp, _('Nastavení se neuložilo', 'Setting not saved'), (e && e.message) || ''); });
            })];
          });
        }
        panel.rows = panel.rows.concat(rowsSub);
        if (on('subdomains')) panel.form = { title: _('Přidat doménu nebo subdoménu', 'Add a domain or subdomain'), fields: [F('a', _('doména (např. blog.' + sel.name + ')', 'domain (e.g. blog.' + sel.name + ')'), '1 1 220px'), F('b', _('podsložka (volitelně, např. blog)', 'sub-folder (optional, e.g. blog)'), '0 0 200px')], submit: _('Přidat', 'Add'), on: function () { if (!(s.wbF.a || '').trim()) { flash(cmp, _('Chybí doména', 'Domain missing'), ''); return; } act(cmp, sel, 'subdomain.add', { domain: s.wbF.a.trim(), path: (s.wbF.b || '').trim() || undefined }, ['subdomains']); } };
        panel.state = (Array.isArray(subs) ? subs.length : 0) + (limit('subdomains') != null ? ' / ' + limit('subdomains') : '') + ' ' + _('dalších domén', 'extra domains');
        return panel;
      }
      if (tab === 'redirect') {
        if (!on('redirects')) return unavailable(_('Přesměrování', 'Redirects'));
        var rd = resource(cmp, sel, 'redirect');
        var p2 = infoPanel(_('Přesměrování · ', 'Redirects · ') + sel.name, _('celý web přesměrujeme na jinou adresu (301 trvale, 302 dočasně); prázdný cíl přesměrování zruší', 'the whole site is redirected elsewhere (301 permanent, 302 temporary); an empty target removes it'), [[_('Aktuální cíl', 'Current target'), rd === null ? _('načítám…', 'loading…') : (rd && rd.target ? rd.target + ' (' + rd.type + ')' : _('žádné přesměrování', 'no redirect'))]]);
        p2.form = { title: _('Nastavit přesměrování', 'Set a redirect'), fields: [F('a', 'https://…', '1 1 260px'), F('b', _('301 nebo 302', '301 or 302'), '0 0 120px')], submit: _('Uložit', 'Save'), on: function () { act(cmp, sel, 'redirect.set', { target: (s.wbF.a || '').trim(), type: (s.wbF.b || '301').trim() }, ['redirect']); } };
        p2.extra = [{ label: _('Zrušit přesměrování', 'Remove redirect'), on: function () { act(cmp, sel, 'redirect.set', { target: '' }, ['redirect']); } }];
        return p2;
      }
      if (tab === 'run') {
        if (!on('php')) return unavailable('PHP');
        var php = resource(cmp, sel, 'php');
        var versions = php && php.versions ? php.versions : [];
        var p3 = infoPanel(_('PHP · ', 'PHP · ') + sel.name, _('změna verze platí okamžitě pro celý web; zpět jde stejně rychle', 'a version change applies at once to the whole site and is just as quick to revert'), [[_('Aktuální verze', 'Current version'), php === null ? _('načítám…', 'loading…') : (php.current || '—')], [_('Dostupné verze', 'Available versions'), versions.join(', ') || '—']]);
        p3.chips = versions.map(function (v) { return { label: 'PHP ' + v, active: php && php.current === v, on: function () { act(cmp, sel, 'php.set', { version: v }, ['php'], _('Verze PHP se mění', 'Changing the PHP version'), _('Web poběží na PHP ' + v + ' během minuty.', 'The site runs on PHP ' + v + ' within a minute.')); } }; });
        p3.chipsLabel = _('Přepnout na', 'Switch to');
        return p3;
      }
      if (tab === 'ssl' || tab === 'le') {
        if (!on('ssl')) return unavailable('SSL');
        var c = resource(cmp, sel, 'certificate');
        var p4 = infoPanel(_('Certifikát a HTTPS · ', 'Certificate and HTTPS · ') + sel.name, _("Let's Encrypt vystavíme a obnovujeme sami; vynucení HTTPS přesměruje veškerý provoz", "we issue and renew Let's Encrypt ourselves; forcing HTTPS redirects all traffic"), c === null ? [[_('Stav', 'State'), _('načítám…', 'loading…')]] : [[_('Vystaven', 'Issued'), c.issued ? _('ano', 'yes') : _('ne', 'no')], [_('Vydavatel', 'Issuer'), c.issuer || (c.letsencrypt ? "Let's Encrypt" : '—')], [_('Platí do', 'Valid until'), c.expires_at || _('obnovujeme automaticky', 'renewed automatically')], [_('Domény', 'Domains'), (c.domains || []).join(', ') || '—'], [_('HTTPS vynucené', 'HTTPS forced'), c.https_forced ? _('ano', 'yes') : _('ne', 'no')]]);
        if (on('ssl_upload')) p4.form = { title: _('Nahrát vlastní certifikát (PEM)', 'Upload your own certificate (PEM)'), fields: [F('a', _('certifikát -----BEGIN CERTIFICATE-----', 'certificate -----BEGIN CERTIFICATE-----'), '1 1 260px'), F('b', _('privátní klíč -----BEGIN PRIVATE KEY-----', 'private key -----BEGIN PRIVATE KEY-----'), '1 1 260px'), F('c', _('řetězec CA (volitelně)', 'CA chain (optional)'), '1 1 200px')], submit: _('Nahrát', 'Upload'), on: function () { act(cmp, sel, 'ssl.upload', { cert: s.wbF.a || '', key: s.wbF.b || '', chain: s.wbF.c || '' }, ['certificate'], _('Certifikát nahrán', 'Certificate uploaded'), _('Webserver ho použije během minuty; automatické obnovení se pro vlastní certifikát vypne.', 'The web server uses it within a minute; automatic renewal is off for a custom certificate.')); } };
        p4.extra = [{ label: _("Vystavit / obnovit Let's Encrypt", "Issue / renew Let's Encrypt"), primary: true, on: function () { act(cmp, sel, 'ssl.issue', {}, ['certificate'], _('Vystavujeme certifikát', 'Issuing the certificate'), _('DNS domény musí mířit na tento web; vystavení trvá do dvou minut.', 'The domain must point at this site; issuance takes up to two minutes.')); } }];
        if (on('https')) p4.extra.push({ label: c && c.https_forced ? _('Nevynucovat HTTPS', 'Stop forcing HTTPS') : _('Vynutit HTTPS', 'Force HTTPS'), on: function () { act(cmp, sel, 'https.force', { enabled: !(c && c.https_forced) }, ['certificate']); } });
        return p4;
      }
      if (tab === 'dbs') {
        if (!on('databases')) return unavailable(_('Databáze', 'Databases'));
        return listPanel('databases', _('Databáze · ', 'Databases · ') + sel.name, _('název i uživatel dostanou předponu služby; heslo zobrazíme jen při vytvoření', 'name and user get the service prefix; the password is shown only when created'),
          [cell(_('Databáze', 'Database'), '1 1 200px'), cell(_('Uživatel', 'User'), '1 1 160px'), cell(_('Znaková sada', 'Charset'), '0 0 100px')],
          function (d) { return { cells: [cell(d.name, '1 1 200px', 1), cell(d.user || '—', '1 1 160px', 1), cell(d.charset || '—', '0 0 100px')], note: '', actions: [A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat databázi ' + d.name + ' včetně dat?', 'Delete database ' + d.name + ' with its data?'))) act(cmp, sel, 'database.delete', { remote_id: d.remote_id }, ['databases']); })] }; },
          { title: _('Nová databáze', 'New database'), fields: [F('a', _('název (písmena, číslice, _)', 'name (letters, digits, _)'), '0 0 180px'), passwordField('b')], submit: _('Vytvořit', 'Create'), on: function () { act(cmp, sel, 'database.create', { name: (s.wbF.a || '').trim(), password: s.wbF.b || '' }, ['databases'], _('Databáze se vytváří', 'Creating the database'), _('Uživatel má stejný název jako databáze; heslo jste si uložili při generování.', 'The user has the same name as the database; you saved the password when generating it.')); } },
          [genExtra('b')]);
      }
      if (tab === 'ftpusers') {
        if (!on('ftp')) return unavailable('FTP');
        return listPanel('ftp', _('FTP účty · ', 'FTP accounts · ') + sel.name, _('SFTP/FTPS na adrese webu; účet dostane předponu služby', 'SFTP/FTPS at the site address; the account gets the service prefix'),
          [cell(_('Uživatel', 'User'), '1 1 200px'), cell(_('Složka', 'Folder'), '1 1 220px'), cell(_('Stav', 'State'), '0 0 90px')],
          function (d) { return { cells: [cell(d.user, '1 1 200px', 1), cell(d.path || '/', '1 1 220px', 1), cell(d.active === false ? _('vypnutý', 'off') : _('aktivní', 'active'), '0 0 90px')], note: '', actions: [A(_('Nové heslo', 'New password'), function () { var p = password(20); if (window.confirm(_('Nastavit nové heslo účtu ' + d.user + '? Heslo: ' + p, 'Set a new password for ' + d.user + '? Password: ' + p))) act(cmp, sel, 'ftp.password', { remote_id: d.remote_id, password: p }, ['ftp'], _('Heslo se mění', 'Changing the password'), _('Nové heslo: ', 'New password: ') + p); }), A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat FTP účet ' + d.user + '?', 'Delete FTP account ' + d.user + '?'))) act(cmp, sel, 'ftp.delete', { remote_id: d.remote_id }, ['ftp']); })] }; },
          { title: _('Nový FTP účet', 'New FTP account'), fields: [F('a', _('uživatel', 'user'), '0 0 160px'), passwordField('b'), F('c', _('složka (volitelně)', 'folder (optional)'), '0 0 160px')], submit: _('Vytvořit', 'Create'), on: function () { act(cmp, sel, 'ftp.create', { user: (s.wbF.a || '').trim(), password: s.wbF.b || '', path: (s.wbF.c || '').trim() || undefined }, ['ftp']); } },
          [genExtra('b')]);
      }
      if (tab === 'errpages') {
        if (!on('errpages')) return unavailable(_('Chybové stránky', 'Error pages'));
        var ss = settings(cmp, sel);
        var p8 = infoPanel(_('Chybové stránky · ', 'Error pages · ') + sel.name, _('vlastní stránky 400/401/403/404/500 nahrajte do složky error/ v kořeni webu; zde je zapnete', 'upload your own 400/401/403/404/500 pages into the error/ folder of the site root; switch them on here'), [[_('Vlastní chybové stránky', 'Custom error pages'), ss === null ? _('načítám…', 'loading…') : (ss.__error ? _('Nelze načíst: ', 'Cannot load: ') + ss.__error : (ss.errordocs ? _('zapnuto', 'on') : _('vypnuto', 'off')))], [_('Složka', 'Folder'), (ss && ss.document_root ? ss.document_root : '') + '/error/']]);
        p8.extra = [{ label: ss && ss.errordocs ? _('Vypnout vlastní stránky', 'Turn custom pages off') : _('Zapnout vlastní stránky', 'Turn custom pages on'), primary: !(ss && ss.errordocs), on: function () { act(cmp, sel, 'errpages.set', { enabled: !(ss && ss.errordocs) }, ['site_settings']); } }];
        return p8;
      }
      if (tab === 'directives') {
        if (!on('directives')) return unavailable(_('Direktivy webserveru', 'Web server directives'));
        var ss2 = settings(cmp, sel);
        var kinds = (f.features.directives.options || []);
        var kind = kinds.indexOf(s.wbDirKind) >= 0 ? s.wbDirKind : kinds[0];
        var current = ss2 && ss2.directives ? (ss2.directives[kind] || '') : '';
        var p9 = infoPanel(_('Direktivy webserveru · ', 'Web server directives · ') + sel.name, _('vlastní pravidla pro vhost webu (přesměrování, hlavičky, cache); soubory ani moduly odsud načíst nejde. Změna platí do minuty.', 'your own rules for the site vhost (redirects, headers, caching); no includes or modules. Applies within a minute.'), [[_('Typ', 'Kind'), { apache: 'Apache', nginx: 'nginx', rewrite: _('nginx rewrite pravidla', 'nginx rewrite rules') }[kind] || kind], [_('Aktuální pravidla', 'Current rules'), ss2 === null ? _('načítám…', 'loading…') : (ss2.__error ? _('Nelze načíst: ', 'Cannot load: ') + ss2.__error : (current ? current : _('(žádná)', '(none)')))]]);
        p9.chips = kinds.map(function (k) { return { label: { apache: 'Apache', nginx: 'nginx', rewrite: 'rewrite' }[k] || k, active: k === kind, on: function () { cmp.setState({ wbDirKind: k }); } }; });
        p9.chipsLabel = _('Server', 'Server');
        p9.form = { title: _('Uložit nová pravidla', 'Save new rules'), fields: [F('a', _('pravidla (jeden řádek = jedna direktiva; oddělte středníkem pro více řádků)', 'rules (one directive per line; separate lines with a semicolon)'), '1 1 100%')], submit: _('Uložit', 'Save'), on: function () { var text = String(s.wbF.a || '').split(/\s*;\s*/).join('\n').trim(); act(cmp, sel, 'directives.set', { kind: kind, content: text }, ['site_settings'], _('Direktivy uloženy', 'Directives saved'), _('Webserver se znovu načte během minuty.', 'The web server reloads within a minute.')); } };
        p9.extra = [{ label: _('Vymazat pravidla', 'Clear rules'), on: function () { if (window.confirm(_('Odstranit všechna vlastní pravidla?', 'Remove all custom rules?'))) act(cmp, sel, 'directives.set', { kind: kind, content: '' }, ['site_settings']); } }];
        return p9;
      }
      if (tab === 'dbusers') {
        if (!on('db_users')) return unavailable(_('Uživatelé databází', 'Database users'));
        return listPanel('db_users', _('Uživatelé databází · ', 'Database users · ') + sel.name, _('účet dostane předponu služby; heslo zobrazíme jen při vytvoření', 'the account gets the service prefix; the password is shown only when created'),
          [cell(_('Uživatel', 'User'), '1 1 200px'), cell(_('Databáze', 'Databases'), '1 1 260px')],
          function (d) { return { cells: [cell(d.user, '1 1 200px', 1), cell((d.databases || []).join(', ') || '—', '1 1 260px', 1)], note: '', actions: [A(_('Nové heslo', 'New password'), function () { var pw = password(20); act(cmp, sel, 'dbuser.password', { remote_id: d.remote_id, password: pw }, ['db_users'], _('Heslo změněno', 'Password changed'), _('Nové heslo: ', 'New password: ') + pw); }), A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat uživatele ' + d.user + '?', 'Delete user ' + d.user + '?'))) act(cmp, sel, 'dbuser.delete', { remote_id: d.remote_id }, ['db_users']); })] }; },
          { title: _('Nový uživatel databáze', 'New database user'), fields: [F('a', _('uživatel', 'user'), '0 0 180px'), passwordField('b')], submit: _('Vytvořit', 'Create'), on: function () { act(cmp, sel, 'dbuser.create', { user: (s.wbF.a || '').trim(), password: s.wbF.b || '' }, ['db_users']); } },
          [genExtra('b')]);
      }
      if (tab === 'dbadmin') {
        if (!on('db_admin')) return unavailable(_('Správce databáze', 'Database admin tool'));
        var ss3 = settings(cmp, sel);
        var p10 = infoPanel(_('Správce databáze · ', 'Database admin · ') + sel.name, _('webový správce databází běží na serveru vaší služby; přihlaste se uživatelem a heslem databáze', 'the web database admin runs on your service\'s server; sign in with the database user and password'), [[_('Adresa', 'Address'), ss3 === null ? _('načítám…', 'loading…') : (ss3.__error ? _('Nelze načíst: ', 'Cannot load: ') + ss3.__error : (ss3.db_admin_url || '—'))]]);
        if (ss3 && ss3.db_admin_url) p10.extra = [{ label: _('Otevřít správce databáze', 'Open the database admin'), primary: true, on: function () { window.open(ss3.db_admin_url, '_blank', 'noopener'); } }];
        return p10;
      }
      if (tab === 'protected') {
        if (!on('protected')) return unavailable(_('Chráněné složky', 'Protected folders'));
        var siteWide = f.features.protected.options === 'site';
        return listPanel('protected_folders', _('Chráněné složky · ', 'Protected folders · ') + sel.name, siteWide ? _('heslem lze chránit celý web (staging, klientská prezentace); prohlížeč se zeptá na jméno a heslo', 'the whole site can be protected by a password (staging, client preview); the browser asks for the name and password') : _('složka chráněná jménem a heslem (HTTP Basic); prohlížeč se zeptá při první návštěvě', 'a folder protected by name and password (HTTP Basic); the browser asks on the first visit'),
          [cell(_('Složka', 'Folder'), '1 1 220px'), cell(_('Uživatelé', 'Users'), '1 1 200px')],
          function (d) { return { cells: [cell(d.path, '1 1 220px', 1), cell((d.users || []).join(', ') || '—', '1 1 200px', 1)], note: '', actions: [A(_('Zrušit ochranu', 'Remove protection'), function () { if (window.confirm(_('Zrušit ochranu složky ' + d.path + '?', 'Remove the protection of ' + d.path + '?'))) act(cmp, sel, 'folder.unprotect', { remote_id: d.remote_id }, ['protected_folders']); })] }; },
          { title: siteWide ? _('Chránit celý web heslem', 'Protect the whole site') : _('Chránit složku', 'Protect a folder'), fields: (siteWide ? [] : [F('a', _('složka (např. admin)', 'folder (e.g. admin)'), '0 0 180px')]).concat([F('b', _('uživatel', 'user'), '0 0 160px'), passwordField('c')]), submit: _('Chránit', 'Protect'), on: function () { act(cmp, sel, 'folder.protect', { path: siteWide ? '/' : (s.wbF.a || '').trim(), user: (s.wbF.b || '').trim(), password: s.wbF.c || '' }, ['protected_folders']); } },
          [genExtra('c')]);
      }
      if (tab === 'ssh') {
        if (!on('shell')) return on('ssh') ? infoPanel(_('SSH a SFTP · ', 'SSH and SFTP · ') + sel.name, _('shell přístup zapíná podpora na vyžádání; SFTP funguje s FTP účty', 'shell access is enabled by support on request; SFTP works with the FTP accounts'), [[_('SFTP', 'SFTP'), _('adresa webu, port 22, FTP účet', 'site address, port 22, FTP account')]]) : unavailable('SSH');
        return listPanel('shell_users', _('Shell a SSH klíče · ', 'Shell and SSH keys · ') + sel.name, _('shell účet v chrootu webu (WP-CLI, composer, git); přihlášení heslem nebo klíčem ed25519/RSA', 'a shell account chrooted to the site (WP-CLI, composer, git); sign in with a password or an ed25519/RSA key'),
          [cell(_('Uživatel', 'User'), '1 1 200px'), cell(_('Klíč', 'Key'), '0 0 120px'), cell(_('Chroot', 'Chroot'), '0 0 90px')],
          function (d) { return { cells: [cell(d.user, '1 1 200px', 1), cell(d.has_key ? _('nastaven', 'set') : '—', '0 0 120px'), cell(d.chroot ? _('ano', 'yes') : _('ne', 'no'), '0 0 90px')], note: '', actions: [A(_('Nastavit klíč', 'Set key'), function () { var key = window.prompt(_('Vložte veřejný SSH klíč (ssh-ed25519 …); prázdné = odebrat', 'Paste the public SSH key (ssh-ed25519 …); empty = remove'), ''); if (key !== null) act(cmp, sel, 'shell.key', { remote_id: d.remote_id, ssh_key: key.trim() }, ['shell_users']); }), A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat shell účet ' + d.user + '?', 'Delete shell account ' + d.user + '?'))) act(cmp, sel, 'shell.delete', { remote_id: d.remote_id }, ['shell_users']); })] }; },
          { title: _('Nový shell účet', 'New shell account'), fields: [F('a', _('uživatel', 'user'), '0 0 160px'), passwordField('b'), F('c', _('veřejný SSH klíč (volitelně)', 'public SSH key (optional)'), '1 1 260px')], submit: _('Vytvořit', 'Create'), on: function () { act(cmp, sel, 'shell.create', { user: (s.wbF.a || '').trim(), password: s.wbF.b || '', ssh_key: (s.wbF.c || '').trim() }, ['shell_users']); } },
          [genExtra('b')]);
      }
      if (tab === 'files') {
        if (!on('files')) return unavailable(_('Správce souborů', 'File manager'));
        return filesPanel(cmp, sel, _, H);
      }
      if (tab === 'apps') {
        if (!on('apps')) return unavailable(_('Aplikace a instalace', 'Applications'));
        var apps = resource(cmp, sel, 'apps');
        var p11 = listPanel('apps', _('Aplikace · ', 'Applications · ') + sel.name, _('instalace na jeden klik do kořene webu; existující soubory zálohujte', 'one-click install into the site root; back up existing files first'),
          [cell(_('Aplikace', 'Application'), '1 1 220px'), cell(_('Verze', 'Version'), '0 0 120px')],
          function (d) { return { cells: [cell(d.title || d.name, '1 1 220px'), cell(d.version || '—', '0 0 120px', 1)], note: '', actions: [A(_('Nainstalovat', 'Install'), function () { if (window.confirm(_('Nainstalovat ' + (d.title || d.name) + ' do kořene webu ' + sel.name + '?', 'Install ' + (d.title || d.name) + ' into the root of ' + sel.name + '?'))) act(cmp, sel, 'app.install', { name: d.name, php_version: (resource(cmp, sel, 'php') || {}).current || '' }, ['apps', 'files'], _('Instalace spuštěna', 'Install started'), _('Aplikace bude dostupná na adrese webu během několika minut.', 'The application will be reachable at the site address within minutes.')); })] }; });
        return p11;
      }
      if (tab === 'stats') {
        if (!on('stats')) return unavailable(_('Statistiky provozu', 'Traffic statistics'));
        var ss4 = settings(cmp, sel);
        var st4 = ss4 && ss4.stats ? ss4.stats : {};
        var p12 = infoPanel(_('Statistiky provozu · ', 'Traffic statistics · ') + sel.name, _('statistiky z access logu (AWStats / GoAccess) na adrese webu /stats/, chráněné heslem', 'access-log statistics (AWStats / GoAccess) at the site address /stats/, password protected'), [[_('Nástroj', 'Engine'), ss4 === null ? _('načítám…', 'loading…') : (ss4.__error ? _('Nelze načíst: ', 'Cannot load: ') + ss4.__error : (st4.type || _('vypnuto', 'off')))], [_('Adresa', 'Address'), st4.url || '—'], [_('Uživatel', 'User'), st4.user || 'admin']]);
        p12.form = { title: _('Nastavit statistiky', 'Set statistics'), fields: [F('a', _('awstats | goaccess | webalizer | none', 'awstats | goaccess | webalizer | none'), '0 0 220px'), F('b', _('heslo pro /stats/ (min. 8 znaků)', 'password for /stats/ (min. 8 chars)'), '0 0 220px')], submit: _('Uložit', 'Save'), on: function () { act(cmp, sel, 'stats.set', { type: (s.wbF.a || 'awstats').trim().toLowerCase(), password: s.wbF.b || '' }, ['site_settings']); } };
        p12.extra = [genExtra('b')];
        if (st4.url && st4.type) p12.extra.push({ label: _('Otevřít statistiky', 'Open statistics'), primary: true, on: function () { window.open(st4.url, '_blank', 'noopener'); } });
        return p12;
      }
      if (tab === 'cron') {
        if (!on('cron')) return unavailable('Cron');
        return listPanel('cron', _('Cron úlohy · ', 'Cron jobs · ') + sel.name, _('pět polí cronu (minuta hodina den měsíc den_v_týdnu) a příkaz; běží pod účtem webu', 'five cron fields (minute hour day month weekday) and a command; runs under the site user'),
          [cell(_('Plán', 'Schedule'), '0 0 130px'), cell(_('Příkaz', 'Command'), '1 1 300px'), cell(_('Stav', 'State'), '0 0 80px')],
          function (d) { return { cells: [cell(d.schedule, '0 0 130px', 1), cell(d.command, '1 1 300px', 1), cell(d.active === false ? _('vypnutá', 'off') : _('aktivní', 'active'), '0 0 80px')], note: d.label || '', actions: [A(_('Smazat', 'Delete'), function () { act(cmp, sel, 'cron.delete', { remote_id: d.remote_id }, ['cron']); })] }; },
          { title: _('Nová úloha', 'New job'), fields: [F('a', '0 3 * * *', '0 0 130px'), F('b', _('příkaz', 'command'), '1 1 300px'), F('c', _('popisek (volitelně)', 'label (optional)'), '0 0 140px')], submit: _('Přidat', 'Add'), on: function () { act(cmp, sel, 'cron.create', { schedule: (s.wbF.a || '').trim(), command: (s.wbF.b || '').trim(), label: (s.wbF.c || '').trim() || undefined }, ['cron']); } });
      }
      if (tab === 'mon') {
        if (!on('logs')) return unavailable(_('Logy', 'Logs'));
        var key = sel.id + ':logs:' + (s.wbLog || 'access');
        var lg = state.resources[key];
        if (lg === undefined) { state.resources[key] = null; load('l:' + key, '/services/' + sel.id + '/logs?log=' + (s.wbLog || 'access') + '&lines=200', cmp, function (d) { state.resources[key] = d; }); }
        var lines = lg && lg.lines ? lg.lines : [];
        return { key: 'real:logs', title: _('Logy · ', 'Logs · ') + sel.name, note: _('posledních 200 řádků přímo ze serveru', 'the last 200 lines straight from the server'), state: (s.wbLog || 'access'), console: true, conStatus: lg === null ? _('načítám', 'loading') : (lg && lg.__error ? 'error' : _('načteno', 'loaded')), conMeta: sel.name, conPrompt: '', consoleLines: (lg && lg.__error ? [{ t: '', src: '', m: lg.__error, l: 'err' }] : lines.map(function (l) { return { t: '', src: '', m: l, l: /error|fatal/i.test(l) ? 'err' : 'ok' }; })).slice().reverse(), form: null, chips: [{ label: 'access', active: (s.wbLog || 'access') === 'access', on: function () { cmp.setState({ wbLog: 'access' }); } }, { label: 'error', active: s.wbLog === 'error', on: function () { cmp.setState({ wbLog: 'error' }); } }], extra: [{ label: _('Obnovit', 'Refresh'), on: function () { delete state.resources[key]; rerender(cmp); } }] };
      }
      if (tab === 'quota') return usagePanel(cmp, sel, _);
      if (tab === 'bkp') return backupsPanel(cmp, sel, _, on('restore'));
    }

    if (fam === 'cloud') {
      if (tab === 'console') {
        var p5 = infoPanel(_('Konzole a napájení · ', 'Console and power · ') + sel.name, _('napájení řídí hypervizor; konzole je VNC přes náš relay s jednorázovým tokenem', 'power is handled by the hypervisor; the console is VNC through our relay with a one-time token'), [[_('Stav', 'State'), sel.state], [_('IP adresa', 'IP address'), (window.ONHOST_PANEL.servers.filter(function (x) { return x.id === sel.id; })[0] || {}).ip || '—']]);
        p5.extra = ['start', 'reboot', 'shutdown', 'stop'].filter(function () { return on('power'); }).map(function (pa) { return { label: { start: _('Zapnout', 'Start'), reboot: _('Restartovat', 'Reboot'), shutdown: _('Vypnout (ACPI)', 'Shut down (ACPI)'), stop: _('Vypnout tvrdě', 'Power off') }[pa], primary: pa === 'reboot', on: function () { if (pa === 'reboot' || window.confirm(_('Opravdu ' + pa + '?', 'Really ' + pa + '?'))) act(cmp, sel, 'power', { power_action: pa }, [], _('Napájení: ' + pa, 'Power: ' + pa)); } }; });
        if (on('console')) p5.extra.push({ label: _('Získat přístup ke konzoli', 'Get console access'), on: function () { API.post('/services/' + sel.id + '/console-token', {}, API.key()).then(function (r) { var d = r.data || r; flash(cmp, _('Konzole připravena', 'Console ready'), (d.url || '') + ' · token ' + (d.token || '') + ' · ' + _('platí do ', 'valid until ') + (d.expires_at || '')); }).catch(function (e) { flash(cmp, _('Konzole nedostupná', 'Console unavailable'), e.message || ''); }); } });
        return p5;
      }
      if (tab === 'snap') {
        if (!on('snapshots')) return unavailable(_('Snapshoty', 'Snapshots'));
        return listPanel('snapshots', _('Snapshoty · ', 'Snapshots · ') + sel.name, _('stav disku i paměti k danému okamžiku; návrat trvá minuty', 'disk and memory at a point in time; rolling back takes minutes'),
          [cell(_('Název', 'Name'), '1 1 200px'), cell(_('Popis', 'Description'), '1 1 220px'), cell(_('Vytvořen', 'Created'), '0 0 130px')],
          function (d) { return { cells: [cell(d.name, '1 1 200px', 1), cell(d.description || '', '1 1 220px'), cell(since(cmp, d.created_at), '0 0 130px', 1)], note: '', actions: [A(_('Vrátit se', 'Roll back'), function () { if (window.confirm(_('Vrátit server do snapshotu ' + d.name + '? Novější změny zmizí.', 'Roll the server back to ' + d.name + '? Newer changes are lost.'))) act(cmp, sel, 'rollback_snapshot', { name: d.name }, ['snapshots']); }), A(_('Smazat', 'Delete'), function () { act(cmp, sel, 'snapshot.delete', { name: d.name }, ['snapshots']); })] }; },
          { title: _('Nový snapshot', 'New snapshot'), fields: [F('a', _('název (např. pred-upgradem)', 'name (e.g. before-upgrade)'), '0 0 200px'), F('b', _('popis (volitelně)', 'description (optional)'), '1 1 220px')], submit: _('Vytvořit', 'Create'), on: function () { act(cmp, sel, 'snapshot', { name: (s.wbF.a || '').trim().replace(/[^A-Za-z0-9_-]/g, '-') || 'snap-' + Date.now().toString(36), description: (s.wbF.b || '').trim() || undefined }, ['snapshots']); } });
      }
      if (tab === 'disks') return infoPanel(_('Disky · ', 'Disks · ') + sel.name, _('velikost disku určuje tarif; navýšení proběhne změnou tarifu za provozu', 'disk size follows the plan; grow it by changing the plan while running'), [[_('Konfigurace', 'Configuration'), sel.spec], [_('Tarif', 'Plan'), sel.product || '']]);
      if (tab === 'net') {
        if (!on('firewall')) return unavailable('Firewall');
        var fw = resource(cmp, sel, 'firewall');
        var rules = fw && fw.rules ? fw.rules : [];
        var p6 = listPanel('firewall', _('Síť a firewall · ', 'Network and firewall · ') + sel.name, _('pravidla platí na hypervizoru před serverem; příchozí provoz mimo pravidla je zahozen', 'rules apply on the hypervisor in front of the server; other inbound traffic is dropped'),
          [cell(_('Akce', 'Action'), '0 0 90px'), cell(_('Směr', 'Direction'), '0 0 70px'), cell(_('Protokol / port', 'Protocol / port'), '1 1 160px'), cell(_('Zdroj', 'Source'), '1 1 160px')],
          function () { return {}; });
        p6.rows = rules.length ? rules.map(function (r, i) { return { cells: [cell(r.action, '0 0 90px'), cell(r.type, '0 0 70px'), cell((r.proto || 'any') + (r.dport ? ' ' + r.dport : ''), '1 1 160px', 1), cell(r.source || _('kdokoli', 'anyone'), '1 1 160px', 1)], note: r.comment || '', actions: [A(_('Odebrat', 'Remove'), function () { var next = rules.filter(function (x, j) { return j !== i; }); act(cmp, sel, 'firewall.apply', { rules: next, enabled: fw.enabled !== false }, ['firewall']); })] }; }) : [{ cells: [cell(_('žádná pravidla · vše povoleno', 'no rules · everything allowed'), '1 1 300px')], note: '' }];
        p6.form = { title: _('Povolit port', 'Allow a port'), fields: [F('a', _('port (např. 443 nebo 8000:8100)', 'port (e.g. 443 or 8000:8100)'), '0 0 200px'), F('b', _('zdroj (CIDR, volitelně)', 'source (CIDR, optional)'), '0 0 200px'), F('c', _('protokol tcp/udp', 'protocol tcp/udp'), '0 0 120px')], submit: _('Přidat', 'Add'), on: function () { var next = rules.concat([{ action: 'ACCEPT', type: 'in', proto: (s.wbF.c || 'tcp').trim(), dport: (s.wbF.a || '').trim(), source: (s.wbF.b || '').trim() || undefined, enable: true, comment: 'panel' }]); act(cmp, sel, 'firewall.apply', { rules: next, enabled: true }, ['firewall']); } };
        return p6;
      }
      if (tab === 'bkp') return backupsPanel(cmp, sel, _, on('restore'));
      if (tab === 'mon') return usagePanel(cmp, sel, _);
    }

    if (fam === 'game') {
      /* game servers (seam #39): status, startup variables and image, settings and the panel account, schedules, files, databases, ports, collaborators, backups — every change an audited operation */
      var gst = on('game_status') ? resource(cmp, sel, 'status') : null;
      var gstate = gst && !gst.__error ? gst : null;
      var gbytes = function (n) { n = Number(n || 0); return n >= 1073741824 ? (n / 1073741824).toFixed(1) + ' GB' : (n >= 1048576 ? Math.round(n / 1048576) + ' MB' : Math.max(0, Math.round(n / 1024)) + ' kB'); };
      var gstateLabel = { running: _('běží', 'running'), offline: _('vypnutý', 'offline'), starting: _('startuje', 'starting'), stopping: _('vypíná se', 'stopping') };
      var gstatus = gstate ? (gstateLabel[gstate.state] || gstate.state) + (gstate.installing ? _(' · instaluje se', ' · installing') : '') + (gstate.suspended ? _(' · pozastaven', ' · suspended') : '') + ' · CPU ' + gstate.cpu_pct + ' % · RAM ' + gbytes(gstate.mem_bytes) + (gstate.mem_limit_bytes ? ' / ' + gbytes(gstate.mem_limit_bytes) : '') + ' · ' + _('disk ', 'disk ') + gbytes(gstate.disk_bytes) + (gstate.disk_limit_bytes ? ' / ' + gbytes(gstate.disk_limit_bytes) : '') + (gstate.uptime_s ? ' · ' + _('běží ', 'up ') + Math.floor(gstate.uptime_s / 3600) + ' h ' + Math.floor(gstate.uptime_s % 3600 / 60) + ' min' : '') : (gst && gst.__error ? _('stav nedostupný: ', 'status unavailable: ') + gst.__error : (gst === null && on('game_status') ? _('načítám stav…', 'loading status…') : sel.state));
      var grefresh = function (kinds) { forget(sel, kinds || []); rerender(cmp); };
      if (tab === 'console') {
        var ops2 = operations(cmp, sel) || [];
        var lines2 = (Array.isArray(ops2) ? ops2 : []).filter(function (o) { return o.kind === 'service.action'; }).slice(0, 40).map(function (o) { return { t: since(cmp, o.finished_at || o.queued_at), src: o.step_label || '', m: (o.state === 'FAILED' ? (o.error && o.error.message) || 'failed' : o.state.toLowerCase()), l: o.state === 'FAILED' ? 'err' : 'ok' }; });
        var powerBtns = ['start', 'reboot', 'stop', 'kill'].filter(function () { return on('power'); }).map(function (pa) { return { label: { start: _('Start', 'Start'), reboot: _('Restart', 'Restart'), stop: _('Stop', 'Stop'), kill: _('Kill', 'Kill') }[pa], primary: pa === 'start', on: function () { act(cmp, sel, 'power', { power_action: pa }, ['status']).then(function () { setTimeout(function () { grefresh(['status']); }, 4000); }); } }; });
        if (on('game_status')) powerBtns.push({ label: _('Obnovit stav', 'Refresh status'), on: function () { grefresh(['status']); } });
        return { key: 'real:gcon', title: _('Konzole · ', 'Console · ') + sel.name, note: _('příkaz odešleme do konzole serveru; výstup je v logu serveru', 'the command goes to the server console; output is in the server log'), state: sel.state, console: true, conStatus: gstatus, conMeta: sel.spec || '', conPrompt: '>', consoleLines: lines2, form: on('command') ? { fields: [{ ph: _('příkaz (např. say Ahoj)', 'command (e.g. say Hello)') }], on: function () { var c = (s.wbF.a || '').trim(); if (!c) return; act(cmp, sel, 'command.send', { command: c }, []); } } : null, extra: powerBtns };
      }
      if (tab === 'startup') {
        if (!on('startup')) return unavailable(_('Startup a proměnné', 'Startup and variables'));
        var su = resource(cmp, sel, 'startup');
        var suRows = su === null ? [{ cells: [cell(_('načítám…', 'loading…'), '1 1 300px')], note: '' }] : (su.__error ? [{ cells: [cell(_('Nelze načíst: ', 'Cannot load: ') + su.__error, '1 1 300px')], note: '' }] : (su.variables || []).map(function (v) {
          return { cells: [cell(v.name || v.key, '1 1 200px'), cell(v.key, '0 0 190px', 1), cell(v.value === '' ? '—' : v.value, '1 1 220px', 1)], note: ((su.attention || []).indexOf(v.key) >= 0 ? _('⚠ Hodnota chybí nebo je neplatná — server bez ní nenastartuje. ', '⚠ Missing or invalid — the server will not start without it. ') : '') + (v.description || '') + (v.editable ? '' : _(' · pevně daná šablonou', ' · fixed by the template')), actions: v.editable ? [A(_('Změnit', 'Change'), function () { var next = window.prompt(_('Nová hodnota proměnné ' + v.key + ':', 'New value of ' + v.key + ':'), v.value); if (next === null || next === v.value) return; act(cmp, sel, 'variable.set', { key: v.key, value: next }, ['startup', 'server_detail']); })] : [] };
        }));
        var images = su && !su.__error ? Object.keys(su.docker_images || {}) : [];
        var extraS = [{ label: _('Obnovit', 'Refresh'), on: function () { resource(cmp, sel, 'startup', true); } }];
        if (images.length > 1) extraS.unshift({ label: _('Změnit image', 'Change image'), on: function () { var current = Object.keys(su.docker_images).filter(function (k) { return su.docker_images[k] === su.docker_image; })[0] || ''; var pick = window.prompt(_('Vyberte image (název přesně):\n', 'Pick an image (exact name):\n') + images.join('\n'), current); if (!pick || !su.docker_images[pick]) return; if (window.confirm(_('Změnit image na ' + pick + '? Server se po dalším startu spustí v novém prostředí.', 'Switch the image to ' + pick + '? The server starts in the new environment next time.'))) act(cmp, sel, 'image.set', { image: su.docker_images[pick] }, ['startup', 'server_detail']); } });
        return { key: 'real:startup', title: _('Startup a proměnné · ', 'Startup and variables · ') + sel.name, note: su && !su.__error ? _('image ', 'image ') + (su.docker_image || '—') + ' · ' + _('startovací příkaz: ', 'startup command: ') + (su.startup || '—') : _('proměnné šablony, které smíte měnit; změna platí od dalšího startu', 'template variables you may change; applied at the next start'), state: su && !su.__error ? (su.variables || []).length + _(' proměnných', ' variables') : '', head: [cell(_('Proměnná', 'Variable'), '1 1 200px'), cell(_('Klíč', 'Key'), '0 0 190px'), cell(_('Hodnota', 'Value'), '1 1 220px')], rows: suRows, extra: extraS };
      }
      if (tab === 'settings') {
        if (!on('game_settings')) return unavailable(_('Nastavení služby', 'Service settings'));
        var det = resource(cmp, sel, 'server_detail');
        var acc = on('panel_access') ? resource(cmp, sel, 'panel_access') : null;
        var d = det && !det.__error ? det : null, ac = acc && !acc.__error ? acc : null;
        var pairsG = [
          [_('Název serveru', 'Server name'), d ? d.name : (det === null ? _('načítám…', 'loading…') : (det && det.__error ? _('nelze načíst', 'cannot load') : '—')), '', d ? [A(_('Přejmenovat', 'Rename'), function () { var n = window.prompt(_('Nový název serveru:', 'New server name:'), d.name); if (!n || n === d.name) return; act(cmp, sel, 'rename', { name: n }, ['server_detail']); })] : []],
          [_('Adresa', 'Address'), d && d.allocation ? (d.allocation.alias || d.allocation.ip) + ':' + d.allocation.port : ((window.ONHOST_PANEL.servers.filter(function (x) { return x.id === sel.id; })[0] || {}).ip || '—')],
          [_('SFTP', 'SFTP'), d ? d.sftp.host + ':' + d.sftp.port + ' · ' + _('uživatel ', 'user ') + d.sftp.username : '—', _('heslo je heslo do herního panelu', 'the password is the game panel password')],
          [_('Image', 'Image'), d ? d.docker_image : '—'],
          [_('Herní panel', 'Game panel'), ac ? ac.url + ' · ' + _('uživatel ', 'user ') + (ac.username || ac.email || '—') : (acc === null && on('panel_access') ? _('načítám…', 'loading…') : '—'), _('konzole, soubory a zálohy i přímo v herním panelu', 'console, files and backups also directly in the game panel'), ac && on('panel_access') ? [A(_('Nové heslo', 'New password'), function () { var p = password(20); if (!window.confirm(_('Nastavit nové heslo do herního panelu pro ' + (ac.username || ac.email) + '?\nHeslo: ' + p + '\nUložte si ho — po potvrzení ho už nezobrazíme.', 'Set a new game panel password for ' + (ac.username || ac.email) + '?\nPassword: ' + p + '\nSave it — it is not shown again.'))) return; act(cmp, sel, 'panel.password', { password: p }, [], _('Heslo změněno', 'Password changed'), _('Přihlaste se v herním panelu novým heslem.', 'Sign in to the game panel with the new password.')); })] : []],
          [_('Reinstalace', 'Reinstall'), _('znovu spustí instalační skript šablony; soubory serveru se přepíší', 'runs the template install script again; the server files are rewritten'), '', d ? [A(_('Reinstalovat', 'Reinstall'), function () { if (window.confirm(_('Reinstalovat server ' + sel.name + '? Svět a soubory budou přepsány — udělejte si nejdřív zálohu.', 'Reinstall ' + sel.name + '? The world and files are overwritten — take a backup first.'))) act(cmp, sel, 'reinstall', { confirm: true }, ['status', 'server_detail'], _('Reinstalace spuštěna', 'Reinstall started'), _('Průběh sledujte v Konzoli a v Provozu.', 'Follow it in Console and Operations.')); })] : []]
        ];
        var pG = infoPanel(_('Nastavení služby · ', 'Service settings · ') + sel.name, _('název, adresa, SFTP a účet do herního panelu; reinstalace vyžaduje ověření', 'name, address, SFTP and the game panel account; a reinstall needs a step-up'), pairsG.map(function (p) { return [p[0], p[1], p[2] || '']; }));
        pG.rows.forEach(function (r, i) { r.actions = pairsG[i][3] || []; });
        pG.extra = [{ label: _('Obnovit', 'Refresh'), on: function () { grefresh(['server_detail', 'panel_access']); } }];
        return pG;
      }
      if (tab === 'sched') {
        if (!on('schedules')) return unavailable(_('Plánované úlohy', 'Schedules'));
        var actionLabel = function (t) { return t.action === 'power' ? _('napájení: ', 'power: ') + t.payload : (t.action === 'backup' ? _('záloha', 'backup') : _('příkaz: ', 'command: ') + t.payload); };
        var mapSched = function (x) { return { cells: [cell(x.name, '1 1 180px'), cell(x.cron, '0 0 120px', 1), cell((x.tasks || []).map(actionLabel).join(' → ') || '—', '1 1 220px'), cell(x.active ? (x.next_run_at ? since(cmp, x.next_run_at) : _('aktivní', 'active')) : _('vypnuto', 'off'), '0 0 130px', 1)], note: x.last_run_at ? _('naposledy ', 'last run ') + since(cmp, x.last_run_at) : '', actions: on('schedule_tools') ? [A(_('Spustit', 'Run now'), function () { act(cmp, sel, 'schedule.run', { remote_id: x.remote_id }, ['schedules']); }), A(x.active ? _('Vypnout', 'Disable') : _('Zapnout', 'Enable'), function () { act(cmp, sel, 'schedule.toggle', { remote_id: x.remote_id, active: !x.active }, ['schedules']); }), A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat úlohu ' + x.name + '?', 'Delete schedule ' + x.name + '?'))) act(cmp, sel, 'schedule.delete', { remote_id: x.remote_id }, ['schedules']); })] : [] }; };
        var schedForm = { title: _('Nová úloha', 'New schedule'), fields: [F('a', _('název', 'name'), '0 0 160px'), F('b', '0 4 * * *', '0 0 130px'), F('c', _('akce: restart | backup | příkaz…', 'action: restart | backup | command…'), '1 1 220px')], submit: _('Přidat', 'Add'), on: function () { var c = (s.wbF.c || '').trim(); var action = c === 'restart' ? { action: 'power', payload: 'restart' } : (c === 'backup' ? { action: 'backup', payload: '' } : { action: 'command', payload: c }); act(cmp, sel, 'schedule.create', { name: (s.wbF.a || '').trim(), cron: (s.wbF.b || '').trim(), actions: [action] }, ['schedules']); } };
        if (!on('schedule_tools')) { var p7 = infoPanel(_('Plánované úlohy · ', 'Schedules · ') + sel.name, _('cron (5 polí) + akce: restart, záloha nebo příkaz konzole', 'cron (5 fields) + action: restart, backup or a console command'), [[_('Poznámka', 'Note'), _('existující úlohy spravujete v konzoli serveru; zde přidáte novou', 'existing schedules are managed in the server console; add a new one here')]]); p7.form = schedForm; return p7; }
        return listPanel('schedules', _('Plánované úlohy · ', 'Schedules · ') + sel.name, _('cron (5 polí) + akce: restart, záloha nebo příkaz konzole; čas serveru', 'cron (5 fields) + action: restart, backup or a console command; server time'), [cell(_('Název', 'Name'), '1 1 180px'), cell(_('Cron', 'Cron'), '0 0 120px'), cell(_('Akce', 'Actions'), '1 1 220px'), cell(_('Další běh', 'Next run'), '0 0 130px')], mapSched, schedForm);
      }
      if (tab === 'files') {
        if (!on('game_files')) return unavailable(_('Správce souborů', 'File manager'));
        return gameFilesPanel(cmp, sel, _, H);
      }
      if (tab === 'dbs') {
        if (!on('game_databases')) return unavailable(_('Databáze', 'Databases'));
        var reveal = !!s.wbReveal;
        var dbKind = reveal ? 'game_databases?reveal=1' : 'game_databases';
        var dbs = resource(cmp, sel, dbKind);
        var dbRows = dbs === null ? [{ cells: [cell(_('načítám…', 'loading…'), '1 1 300px')], note: '' }] : (dbs.__error ? [{ cells: [cell(_('Nelze načíst: ', 'Cannot load: ') + dbs.__error, '1 1 300px')], note: '' }] : (Array.isArray(dbs) ? dbs : []).map(function (x) {
          return { cells: [cell(x.name, '1 1 180px', 1), cell(x.username, '0 0 150px', 1), cell(x.host + ':' + x.port, '1 1 170px', 1), cell(reveal ? (x.password || '—') : '••••••••', '0 0 150px', 1)], note: _('připojení z ', 'connections from ') + (x.connections_from || '%'), actions: [A(_('Nové heslo', 'New password'), function () { if (window.confirm(_('Vygenerovat nové heslo databáze ' + x.name + '? Aplikace se starým heslem se odpojí.', 'Generate a new password for ' + x.name + '? Apps using the old one disconnect.'))) act(cmp, sel, 'gamedb.rotate', { remote_id: x.remote_id }, ['game_databases']); }), A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat databázi ' + x.name + ' včetně dat?', 'Delete database ' + x.name + ' with its data?'))) act(cmp, sel, 'gamedb.delete', { remote_id: x.remote_id }, ['game_databases']); })] };
        }));
        if (Array.isArray(dbs) && !dbs.length) dbRows = [{ cells: [cell(_('zatím žádná databáze', 'no database yet'), '1 1 300px')], note: '' }];
        var dbLimit = f.features.game_databases && f.features.game_databases.limit;
        return { key: 'real:gdbs', title: _('Databáze · ', 'Databases · ') + sel.name, note: _('MySQL/MariaDB databáze pro pluginy a statistiky; heslo zobrazíte na vyžádání', 'MySQL/MariaDB databases for plugins and stats; the password is shown on request'), state: Array.isArray(dbs) ? dbs.length + (dbLimit ? ' / ' + dbLimit : '') : '', head: [cell(_('Databáze', 'Database'), '1 1 180px'), cell(_('Uživatel', 'User'), '0 0 150px'), cell(_('Server', 'Host'), '1 1 170px'), cell(_('Heslo', 'Password'), '0 0 150px')], rows: dbRows,
          form: { title: _('Nová databáze', 'New database'), fields: [F('a', _('název (např. stats)', 'name (e.g. stats)'), '0 0 200px'), F('b', _('povolit připojení z (% = odkudkoli)', 'allow connections from (% = anywhere)'), '0 0 220px')], submit: _('Vytvořit', 'Create'), on: function () { act(cmp, sel, 'gamedb.create', { name: (s.wbF.a || '').trim(), remote: (s.wbF.b || '').trim() || '%' }, ['game_databases']); } },
          extra: [{ label: reveal ? _('Skrýt hesla', 'Hide passwords') : _('Zobrazit hesla', 'Show passwords'), on: function () { cmp.setState({ wbReveal: !reveal }); } }, { label: _('Obnovit', 'Refresh'), on: function () { grefresh(['game_databases', 'game_databases?reveal=1']); } }] };
      }
      if (tab === 'world' || tab === 'bkp') return backupsPanel(cmp, sel, _, on('restore'), on('backup_tools'));
      if (tab === 'net') {
        if (!on('allocations')) return infoPanel(_('Síť a porty · ', 'Network and ports · ') + sel.name, _('adresa a port serveru; doménu nasměrujte CNAME/SRV záznamem v sekci Domény', 'server address and port; point a domain with a CNAME/SRV record in Domains'), [[_('Adresa', 'Address'), (window.ONHOST_PANEL.servers.filter(function (x) { return x.id === sel.id; })[0] || {}).ip || '—'], [_('Konfigurace', 'Configuration'), sel.spec]]);
        var allocLimit = f.features.allocations && f.features.allocations.limit;
        var pNet = listPanel('allocations', _('Síť, porty a doména · ', 'Network, ports and domain · ') + sel.name, _('hlavní port dostávají hráči; další porty pro hlasový chat, dynamickou mapu nebo pluginy; doménu nasměrujte SRV záznamem v sekci Domény', 'the main port is what players use; extra ports for voice chat, a live map or plugins; point a domain with an SRV record in Domains'),
          [cell(_('Adresa', 'Address'), '1 1 220px'), cell(_('Port', 'Port'), '0 0 90px'), cell(_('Role', 'Role'), '0 0 120px')],
          function (x) { return { cells: [cell((x.alias || x.ip), '1 1 220px', 1), cell(String(x.port), '0 0 90px', 1), cell(x.primary ? _('hlavní', 'primary') : _('další', 'extra'), '0 0 120px')], note: x.notes || '', actions: x.primary ? [] : [A(_('Nastavit jako hlavní', 'Make primary'), function () { if (window.confirm(_('Přepnout hlavní port na ' + x.port + '? Hráči se budou připojovat na nový port (po restartu).', 'Switch the main port to ' + x.port + '? Players connect to the new port (after a restart).'))) act(cmp, sel, 'allocation.primary', { remote_id: x.remote_id }, ['allocations', 'server_detail']); }), A(_('Odebrat', 'Remove'), function () { if (window.confirm(_('Odebrat port ' + x.port + '?', 'Remove port ' + x.port + '?'))) act(cmp, sel, 'allocation.remove', { remote_id: x.remote_id }, ['allocations']); })] }; },
          null, [{ label: _('Přidat port', 'Add a port'), primary: true, on: function () { act(cmp, sel, 'allocation.add', {}, ['allocations'], _('Přidáváme port', 'Adding a port'), _('Panel vybere volný port na uzlu; objeví se v seznamu.', 'The panel picks a free port on the node; it appears in the list.')); } }]);
        if (allocLimit) pNet.state = (pNet.state || '') + (pNet.state && pNet.state.indexOf('/') < 0 ? ' / ' + allocLimit : '');
        return pNet;
      }
      if (tab === 'users') {
        if (!on('subusers')) return unavailable(_('Spolupracovníci', 'Collaborators'));
        var presetLabel = function (perms) { var n = (perms || []).length; return n >= 20 ? _('plný přístup', 'full access') : (n >= 8 ? _('konzole a soubory', 'console and files') : _('konzole', 'console')); };
        return listPanel('subusers', _('Spolupracovníci · ', 'Collaborators · ') + sel.name, _('účet v herním panelu s omezenými právy; pozvánka přijde e-mailem', 'a game panel account with limited rights; the invitation goes by e-mail'),
          [cell(_('E-mail', 'E-mail'), '1 1 240px'), cell(_('Práva', 'Rights'), '1 1 160px'), cell(_('Od', 'Since'), '0 0 130px')],
          function (x) { return { cells: [cell(x.email, '1 1 240px', 1), cell(presetLabel(x.permissions), '1 1 160px'), cell(since(cmp, x.created_at) || '—', '0 0 130px', 1)], note: (x.permissions || []).length + _(' oprávnění', ' permissions'), actions: [A(_('Odebrat', 'Remove'), function () { if (window.confirm(_('Odebrat spolupracovníka ' + x.email + '?', 'Remove collaborator ' + x.email + '?'))) act(cmp, sel, 'subuser.delete', { remote_id: x.remote_id }, ['subusers']); })] }; },
          { title: _('Nový spolupracovník', 'New collaborator'), fields: [F('a', 'admin@priklad.cz', '0 0 240px'), F('b', _('práva: console | files | full', 'rights: console | files | full'), '0 0 200px')], submit: _('Pozvat', 'Invite'), on: function () { act(cmp, sel, 'subuser.create', { email: (s.wbF.a || '').trim(), preset: (s.wbF.b || 'console').trim().toLowerCase() || 'console' }, ['subusers']); } });
      }
      if (tab === 'mon') return usagePanel(cmp, sel, _);
      if (tab === 'plan') return infoPanel(_('Výkon a tarif · ', 'Resources and plan · ') + sel.name, _('změna tarifu proběhne za provozu; napište podpoře nebo objednejte vyšší tarif', 'a plan change happens live; write to support or order a bigger plan'), [[_('Tarif', 'Plan'), sel.product || ''], [_('Konfigurace', 'Configuration'), sel.spec]]);
    }

    if (fam === 'mail') {
      if (tab === 'boxes') {
        if (!on('mailboxes')) return unavailable(_('Schránky', 'Mailboxes'));
        return listPanel('mailboxes', _('Schránky · ', 'Mailboxes · ') + sel.name, _('IMAP/SMTP s TLS; heslo zobrazíme jen při vytvoření', 'IMAP/SMTP with TLS; the password is shown only when created'),
          [cell(_('Adresa', 'Address'), '1 1 220px'), cell(_('Jméno', 'Name'), '1 1 160px'), cell(_('Kvóta', 'Quota'), '0 0 90px')],
          function (d) { return { cells: [cell(d.address, '1 1 220px', 1), cell(d.name || '', '1 1 160px'), cell(d.quota_mb ? d.quota_mb + ' MB' : '—', '0 0 90px', 1)], note: d.active === false ? _('vypnutá', 'disabled') : '', actions: [A(_('Nové heslo', 'New password'), function () { var p = password(20); if (window.confirm(_('Nové heslo pro ' + d.address + ': ' + p, 'New password for ' + d.address + ': ' + p))) act(cmp, sel, 'mailbox.update', { remote_id: d.remote_id, password: p }, ['mailboxes']); }), A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat schránku ' + d.address + ' včetně pošty?', 'Delete mailbox ' + d.address + ' with its mail?'))) act(cmp, sel, 'mailbox.delete', { remote_id: d.remote_id }, ['mailboxes']); })] }; },
          { title: _('Nová schránka', 'New mailbox'), fields: [F('a', 'jmeno@' + sel.name, '0 0 200px'), passwordField('b'), F('c', _('jméno (volitelně)', 'name (optional)'), '0 0 140px')], submit: _('Vytvořit', 'Create'), on: function () { act(cmp, sel, 'mailbox.create', { address: (s.wbF.a || '').trim(), password: s.wbF.b || '', name: (s.wbF.c || '').trim() }, ['mailboxes']); } },
          [genExtra('b')]);
      }
      if (tab === 'alias') {
        if (!on('aliases')) return unavailable(_('Aliasy', 'Aliases'));
        return listPanel('aliases', _('Aliasy a přesměrování · ', 'Aliases and forwards · ') + sel.name, _('adresa, která doručuje do jiné schránky', 'an address that delivers into another mailbox'),
          [cell(_('Alias', 'Alias'), '1 1 200px'), cell(_('Doručit na', 'Deliver to'), '1 1 220px')],
          function (d) { return { cells: [cell(d.source, '1 1 200px', 1), cell(d.destination, '1 1 220px', 1)], note: '', actions: [A(_('Smazat', 'Delete'), function () { act(cmp, sel, 'alias.delete', { remote_id: d.remote_id }, ['aliases']); })] }; },
          { title: _('Nový alias', 'New alias'), fields: [F('a', 'info@' + sel.name, '0 0 200px'), F('b', 'jmeno@' + sel.name, '0 0 200px')], submit: _('Přidat', 'Add'), on: function () { act(cmp, sel, 'alias.create', { source: (s.wbF.a || '').trim(), destination: (s.wbF.b || '').trim() }, ['aliases']); } });
      }
      if (tab === 'auth') {
        if (!on('dkim')) return unavailable('DKIM');
        var dk = resource(cmp, sel, 'dkim');
        return infoPanel(_('SPF, DKIM a DMARC · ', 'SPF, DKIM and DMARC · ') + sel.name, _('tři DNS záznamy rozhodují, zda vaše pošta dojde; u domén v naší DNS je nastavíme za vás', 'three DNS records decide whether your mail arrives; for domains on our DNS we set them for you'), dk === null ? [[_('DKIM', 'DKIM'), _('načítám…', 'loading…')]] : [[_('DKIM selektor', 'DKIM selector'), dk.selector || '—'], [_('DKIM záznam (TXT)', 'DKIM record (TXT)'), dk.dns_record || '—'], ['SPF', 'v=spf1 mx -all'], ['DMARC', 'v=DMARC1; p=none; rua=mailto:dmarc@' + sel.name]]);
      }
    }

    return unavailable(sel.name);
  }

  /* File manager (aaPanel-backed sites): browse, create folders, edit small text files, delete; downloads stream through the API. */
  function filesPanel(cmp, sel, _, H) {
    var cell = H.cell, A = H.act, F = H.F, s = H.s;
    var dir = String(s.wbDir || '').replace(/^\/+|\/+$/g, '');
    var key = sel.id + ':files:' + dir, r = state.resources[key];
    if (r === undefined) { state.resources[key] = null; load('fl:' + key, '/services/' + sel.id + '/resources/files?path=' + encodeURIComponent(dir), cmp, function (d) { state.resources[key] = d; }); }
    var listing = r && !r.__error ? r : null;
    var refresh = function () { delete state.resources[key]; rerender(cmp); [2500, 8000].forEach(function (ms) { setTimeout(function () { delete state.resources[key]; rerender(cmp); }, ms); }); };
    var go = function (path) { cmp.setState({ wbDir: path }); };
    var rows = [];
    if (dir) rows.push({ cells: [cell('..', '1 1 260px', 1), cell(_('nadřazená složka', 'parent folder'), '0 0 120px'), cell('', '0 0 100px'), cell('', '0 0 150px')], note: '', actions: [A(_('Otevřít', 'Open'), function () { go(dir.split('/').slice(0, -1).join('/')); })] });
    (listing ? listing.entries : []).forEach(function (e) {
      var path = (dir ? dir + '/' : '') + e.name;
      var actions = e.type === 'dir'
        ? [A(_('Otevřít', 'Open'), function () { go(path); }), A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat složku ' + e.name + ' včetně obsahu?', 'Delete folder ' + e.name + ' with its contents?'))) act(cmp, sel, 'file.delete', { path: path, directory: true }, [], _('Složka smazána', 'Folder deleted'), '').then(refresh); })]
        : [A(_('Stáhnout', 'Download'), function () { window.open(API.base + '/services/' + sel.id + '/files/download?path=' + encodeURIComponent(path), '_blank', 'noopener'); }),
           A(_('Upravit', 'Edit'), function () { if ((e.size || 0) > 512 * 1024) { flash(cmp, _('Soubor je příliš velký', 'File too large'), _('Soubory nad 512 kB upravte přes SFTP.', 'Edit files over 512 kB over SFTP.')); return; } API.get('/services/' + sel.id + '/files/download?json=1&path=' + encodeURIComponent(path)).then(function (r2) { cmp.setState({ wbF: { a: path, b: String((r2.data || r2).content || ''), c: '' } }); flash(cmp, _('Soubor načten do editoru', 'File loaded into the editor'), path); }).catch(function (err) { flash(cmp, _('Nelze načíst', 'Cannot load'), err.message || ''); }); }),
           A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat soubor ' + e.name + '?', 'Delete file ' + e.name + '?'))) act(cmp, sel, 'file.delete', { path: path }, [], _('Soubor smazán', 'File deleted'), '').then(refresh); })];
      rows.push({ cells: [cell(e.name, '1 1 260px', 1), cell(e.type === 'dir' ? _('složka', 'folder') : _('soubor', 'file'), '0 0 120px'), cell(e.size != null ? (e.size >= 1048576 ? (e.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(e.size / 1024)) + ' kB') : '—', '0 0 100px', 1), cell(since(cmp, e.modified), '0 0 150px', 1)], note: '', actions: actions });
    });
    if (listing && !listing.entries.length && !dir) rows.push({ cells: [cell(_('kořen webu je prázdný', 'the site root is empty'), '1 1 300px')], note: '' });
    return { key: 'real:files', title: _('Správce souborů · ', 'File manager · ') + sel.name, note: _('kořen webu /' + (dir ? dir : '') + ' · textové soubory do 512 kB upravíte zde, větší přes SFTP', 'site root /' + (dir ? dir : '') + ' · edit text files up to 512 kB here, larger ones over SFTP'),
      state: r === null ? _('načítám', 'loading') : (r && r.__error ? _('chyba: ', 'error: ') + r.__error : (listing ? listing.entries.length + ' ' + _('položek', 'items') : '')),
      head: [cell(_('Název', 'Name'), '1 1 260px'), cell(_('Typ', 'Type'), '0 0 120px'), cell(_('Velikost', 'Size'), '0 0 100px'), cell(_('Změněno', 'Modified'), '0 0 150px')], rows: rows,
      form: { title: _('Uložit textový soubor / nová složka', 'Save a text file / new folder'), fields: [F('a', _('cesta (např. ' + (dir ? dir + '/' : '') + 'robots.txt)', 'path (e.g. ' + (dir ? dir + '/' : '') + 'robots.txt)'), '0 0 260px'), F('b', _('obsah souboru (prázdné = vytvořit složku)', 'file content (empty = create a folder)'), '1 1 300px')], submit: _('Uložit', 'Save'), on: function () {
        var path = String(s.wbF.a || '').trim().replace(/^\/+/, ''); var content = String(s.wbF.b || '');
        if (!path) { flash(cmp, _('Zadejte cestu', 'Enter a path'), ''); return; }
        (content === '' ? act(cmp, sel, 'file.mkdir', { path: path }, [], _('Složka vytvořena', 'Folder created'), '') : act(cmp, sel, 'file.save', { path: path, content: content }, [], _('Soubor uložen', 'File saved'), '')).then(refresh);
      } },
      extra: [{ label: _('Obnovit', 'Refresh'), on: refresh }, { label: _('Kořen webu', 'Site root'), on: function () { go(''); } }] };
  }

  /* Game server files (seam #39): the panel's file API — browse, edit small text files, create folders, rename, delete; downloads stream through the control plane. */
  function gameFilesPanel(cmp, sel, _, H) {
    var cell = H.cell, A = H.act, F = H.F, s = H.s;
    var dir = String(s.wbDir || '').replace(/^\/+|\/+$/g, '');
    var key = sel.id + ':game_files:' + dir, r = state.resources[key];
    if (r === undefined) { state.resources[key] = null; load('gf:' + key, '/services/' + sel.id + '/resources/game_files?path=' + encodeURIComponent(dir), cmp, function (d) { state.resources[key] = d; }); }
    var listing = r && !r.__error ? r : null;
    var refresh = function () { delete state.resources[key]; rerender(cmp); [2500, 8000].forEach(function (ms) { setTimeout(function () { delete state.resources[key]; rerender(cmp); }, ms); }); };
    var go = function (path) { cmp.setState({ wbDir: path }); };
    var root = '/' + dir;
    var rows = [];
    if (dir) rows.push({ cells: [cell('..', '1 1 260px', 1), cell(_('nadřazená složka', 'parent folder'), '0 0 120px'), cell('', '0 0 100px'), cell('', '0 0 150px')], note: '', actions: [A(_('Otevřít', 'Open'), function () { go(dir.split('/').slice(0, -1).join('/')); })] });
    (listing ? listing.entries : []).forEach(function (e) {
      var path = (dir ? dir + '/' : '') + e.name;
      var rename = A(_('Přejmenovat', 'Rename'), function () { var to = window.prompt(_('Nový název:', 'New name:'), e.name); if (!to || to === e.name) return; act(cmp, sel, 'gfile.rename', { root: root, from: e.name, to: to }, [], _('Přejmenováno', 'Renamed'), '').then(refresh); });
      var remove = A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat ' + e.name + (e.type === 'dir' ? ' včetně obsahu' : '') + '?', 'Delete ' + e.name + (e.type === 'dir' ? ' with its contents' : '') + '?'))) act(cmp, sel, 'gfile.delete', { root: root, name: e.name }, [], _('Smazáno', 'Deleted'), '').then(refresh); });
      var actions = e.type === 'dir'
        ? [A(_('Otevřít', 'Open'), function () { go(path); }), rename, remove]
        : [A(_('Stáhnout', 'Download'), function () { window.open(API.base + '/services/' + sel.id + '/files/download?path=' + encodeURIComponent(path), '_blank', 'noopener'); }),
           A(_('Upravit', 'Edit'), function () { if ((e.size || 0) > 512 * 1024) { flash(cmp, _('Soubor je příliš velký', 'File too large'), _('Soubory nad 512 kB upravte přes SFTP.', 'Edit files over 512 kB over SFTP.')); return; } API.get('/services/' + sel.id + '/files/download?json=1&path=' + encodeURIComponent(path)).then(function (r2) { cmp.setState({ wbF: { a: path, b: String((r2.data || r2).content || ''), c: '' } }); flash(cmp, _('Soubor načten do editoru', 'File loaded into the editor'), path); }).catch(function (err) { flash(cmp, _('Nelze načíst', 'Cannot load'), err.message || ''); }); }),
           rename, remove];
      rows.push({ cells: [cell(e.name, '1 1 260px', 1), cell(e.type === 'dir' ? _('složka', 'folder') : _('soubor', 'file'), '0 0 120px'), cell(e.size != null ? (e.size >= 1048576 ? (e.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(e.size / 1024)) + ' kB') : '—', '0 0 100px', 1), cell(since(cmp, e.modified), '0 0 150px', 1)], note: e.mode || '', actions: actions });
    });
    if (listing && !listing.entries.length && !dir) rows.push({ cells: [cell(_('složka serveru je prázdná', 'the server folder is empty'), '1 1 300px')], note: '' });
    return { key: 'real:gfiles', title: _('Správce souborů · ', 'File manager · ') + sel.name, note: _('složka serveru /' + dir + ' · textové soubory do 512 kB upravíte zde, větší přes SFTP (údaje v Nastavení služby)', 'server folder /' + dir + ' · edit text files up to 512 kB here, larger ones over SFTP (details under Service settings)'),
      state: r === null ? _('načítám', 'loading') : (r && r.__error ? _('chyba: ', 'error: ') + r.__error : (listing ? listing.entries.length + ' ' + _('položek', 'items') : '')),
      head: [cell(_('Název', 'Name'), '1 1 260px'), cell(_('Typ', 'Type'), '0 0 120px'), cell(_('Velikost', 'Size'), '0 0 100px'), cell(_('Změněno', 'Modified'), '0 0 150px')], rows: rows,
      form: { title: _('Uložit textový soubor / nová složka', 'Save a text file / new folder'), fields: [F('a', _('cesta (např. ' + (dir ? dir + '/' : '') + 'server.properties)', 'path (e.g. ' + (dir ? dir + '/' : '') + 'server.properties)'), '0 0 260px'), F('b', _('obsah souboru (prázdné = vytvořit složku)', 'file content (empty = create a folder)'), '1 1 300px')], submit: _('Uložit', 'Save'), on: function () {
        var path = String(s.wbF.a || '').trim().replace(/^\/+/, ''); var content = String(s.wbF.b || '');
        if (!path) { flash(cmp, _('Zadejte cestu', 'Enter a path'), ''); return; }
        var parts = path.split('/'); var name = parts.pop(); var parent = '/' + parts.join('/');
        (content === '' ? act(cmp, sel, 'gfile.mkdir', { root: parent, name: name }, [], _('Složka vytvořena', 'Folder created'), '') : act(cmp, sel, 'gfile.save', { path: path, content: content }, [], _('Soubor uložen', 'File saved'), '')).then(refresh);
      } },
      extra: [{ label: _('Obnovit', 'Refresh'), on: refresh }, { label: _('Kořen serveru', 'Server root'), on: function () { go(''); } }] };
  }

  function usagePanel(cmp, sel, _) {
    var key = sel.id + ':usage', u = state.resources[key];
    if (u === undefined) { state.resources[key] = null; load('u:' + key, '/services/' + sel.id + '/usage', cmp, function (d) { state.resources[key] = d; }); }
    var metrics = u && !u.__error ? (u.metrics || u) : {};
    var pairs = Object.keys(metrics).filter(function (k) { return typeof metrics[k] !== 'object'; }).map(function (k) { return [k.replace(/_/g, ' '), metrics[k]]; });
    return { key: 'real:usage', title: _('Využití · ', 'Usage · ') + sel.name, note: _('poslední měření ze serveru', 'the last measurement from the server'), state: u === null ? _('načítám', 'loading') : '', head: [cell2(_('Metrika', 'Metric')), cell2(_('Hodnota', 'Value'), 1)], rows: pairs.length ? pairs.map(function (p) { return { cells: [cell2(p[0]), cell2(String(p[1]), 1)], note: '' }; }) : [{ cells: [cell2(u === null ? _('načítám…', 'loading…') : (u && u.__error ? u.__error : _('zatím bez měření', 'no measurement yet')))], note: '' }], extra: [{ label: _('Obnovit', 'Refresh'), on: function () { delete state.resources[key]; rerender(cmp); } }] };
    function cell2(t, mono) { return { t: t, style: 'flex:1 1 200px;min-width:0;font-size:13px;' + (mono ? 'font-family:ui-monospace,Menlo,monospace;font-size:12px' : '') }; }
  }

  function backupsPanel(cmp, sel, _, canRestore, gameTools) {
    var key = sel.id + ':backups', b = state.resources[key];
    if (b === undefined) { state.resources[key] = null; load('b:' + key, '/services/' + sel.id + '/backups', cmp, function (d) { state.resources[key] = Array.isArray(d) ? d : (d.data || d); }); }
    var list = Array.isArray(b) ? b : [];
    var cell = function (t, w, mono) { return { t: t, style: 'flex:' + w + ';min-width:0;overflow-wrap:anywhere;font-size:13px;' + (mono ? 'font-family:ui-monospace,Menlo,monospace;font-size:12px' : '') }; };
    var A = function (label, on) { return { label: label, on: on, style: 'border:1px solid color-mix(in srgb,var(--fg,#201e1d) 34%,transparent);background:transparent;color:var(--fg,#201e1d);cursor:pointer;padding:5px 9px;font-size:10.5px;letter-spacing:.05em;text-transform:uppercase' }; };
    var later = function () { setTimeout(function () { delete state.resources[key]; rerender(cmp); }, 3000); };
    /* game panels (seam #39): lock a backup against automatic pruning, delete it, download the archive */
    var gameActions = function (x) {
      if (!gameTools || !(x.remote_id || x.id)) return [];
      var locked = !!(x.protected || (x.meta && x.meta.protected));
      return [A(_('Stáhnout', 'Download'), function () { window.open(API.base + '/services/' + sel.id + '/backups/' + encodeURIComponent(x.id || x.remote_id) + '/download', '_blank', 'noopener'); }),
        A(locked ? _('Odemknout', 'Unlock') : _('Zamknout', 'Lock'), function () { act(cmp, sel, 'gbackup.lock', { remote_id: x.remote_id || x.id, locked: !locked }, []).then(later); }),
        A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat tuto zálohu?', 'Delete this backup?'))) act(cmp, sel, 'gbackup.delete', { remote_id: x.remote_id || x.id }, []).then(later); })];
    };
    return { key: 'real:backups', title: _('Zálohy · ', 'Backups · ') + sel.name, note: _('automatické zálohy podle tarifu; ruční zálohu vytvoříte kdykoli', 'automatic backups per plan; a manual backup any time'), state: b === null ? _('načítám', 'loading') : list.length + ' ' + _('záloh', 'backups'),
      head: [cell(_('Vytvořena', 'Created'), '0 0 150px'), cell(_('Velikost', 'Size'), '0 0 110px'), cell(_('Stav', 'State'), '1 1 160px')],
      rows: list.length ? list.map(function (x) { return { cells: [cell(since(cmp, x.created_at || x.started_at), '0 0 150px', 1), cell(x.size_bytes ? Math.round(x.size_bytes / 1048576) + ' MB' : '—', '0 0 110px', 1), cell((x.state || (x.verified ? _('ověřená', 'verified') : '')) + (x.protected ? _(' · zamčená', ' · locked') : ''), '1 1 160px')], note: x.note || '', actions: (canRestore && (x.remote_id || x.id) ? [A(_('Obnovit', 'Restore'), function () { if (window.confirm(_('Obnovit službu z této zálohy? Přepíše aktuální data.', 'Restore the service from this backup? Current data is overwritten.'))) act(cmp, sel, 'restore', { backup_id: x.id || x.remote_id }, []); })] : []).concat(gameActions(x)) }; }) : [{ cells: [cell(b === null ? _('načítám…', 'loading…') : (b && b.__error ? b.__error : _('zatím žádné zálohy', 'no backups yet')), '1 1 300px')], note: '' }],
      extra: [{ label: _('Zálohovat teď', 'Back up now'), primary: true, on: function () { act(cmp, sel, 'backup', {}, [], _('Záloha se vytváří', 'Creating the backup')).then(function () { setTimeout(function () { delete state.resources[key]; rerender(cmp); }, 6000); }); } }, { label: _('Obnovit seznam', 'Refresh'), on: function () { delete state.resources[key]; rerender(cmp); } }] };
  }

  /* ── domains (seam #21): registration, nameservers, the ONhost zone with staged changes, DNSSEC ───────── */
  function dload(cmp, sel, kind, path, fresh) {
    var key = sel.id + ':' + kind, r = state.resources[key];
    if (r === undefined || fresh) { if (r === undefined) state.resources[key] = null; load('d:' + key, path, cmp, function (d) { state.resources[key] = d; }); }
    return r === undefined ? null : r;
  }
  function dinfo(cmp, sel) { return dload(cmp, sel, 'domain', '/domains/' + encodeURIComponent(sel.id)); }
  function dzone(cmp, sel) { return sel.dns_provider === 'onhost' ? dload(cmp, sel, 'zone', '/domains/' + encodeURIComponent(sel.fqdn || sel.name) + '/zone') : null; }
  /* Sensitive domain actions need a fresh second factor: ask for the authenticator code and retry once. */
  function stepUp(cmp, err) {
    var _ = T(cmp), b = err && err.body;
    if (!err || err.status !== 403 || !b || b.error !== 'step_up_required') return Promise.reject(err);
    var code = window.prompt(_('Potvrďte akci kódem z autentikátoru (TOTP) nebo záložním kódem:', 'Confirm with the code from your authenticator (TOTP) or a recovery code:')) || '';
    code = code.replace(/\s+/g, '');
    if (!code) return Promise.reject(new Error(_('Akce vyžaduje druhé ověření.', 'The action needs a second verification.')));
    return API.post('/auth/step-up', { method: code.length > 6 ? 'recovery' : 'totp', code: code }).catch(function (e) { throw new Error(_('Ověření se nezdařilo: ', 'Verification failed: ') + ((e && e.message) || '')); });
  }
  function dpost(cmp, sel, path, body, kinds, okTitle, okBody) {
    var _ = T(cmp);
    var run = function () { return API.post('/domains/' + encodeURIComponent(sel.id) + path, body || {}, API.key()); };
    return run().catch(function (e) { return stepUp(cmp, e).then(run); }).then(function (r) {
      var d = r.data || r;
      flash(cmp, okTitle || _('Požadavek přijat', 'Request accepted'), okBody || _('Změnu provedeme během chvíle; průběh je v záložce Provoz a NOC.', 'The change runs in a moment; progress is under Operations and NOC.'));
      cmp.setState({ wbF: { a: '', b: '', c: '' } });
      [1500, 6000].forEach(function (ms) { setTimeout(function () { forget(sel, kinds || ['domain', 'zone']); rerender(cmp); }, ms); });
      return d;
    }).catch(function (e) { flash(cmp, _('Akce neproběhla', 'Action failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.')); throw e; });
  }
  function zpost(cmp, sel, zone, path, body, okTitle, okBody) {
    var _ = T(cmp);
    var run = function () { return API.post('/dns/zones/' + encodeURIComponent(zone.id) + path, body || {}, API.key()); };
    return run().catch(function (e) { return stepUp(cmp, e).then(run); }).then(function (r) {
      flash(cmp, okTitle || _('Změna připravena', 'Change staged'), okBody || _('Připravené změny publikujte tlačítkem; do té doby zóna běží beze změny.', 'Publish the staged changes with the button; until then the zone runs unchanged.'));
      cmp.setState({ wbF: { a: '', b: '', c: '' } });
      [800, 4000].forEach(function (ms) { setTimeout(function () { forget(sel, ['zone', 'domain']); rerender(cmp); }, ms); });
      return r.data || r;
    }).catch(function (e) { flash(cmp, _('Akce neproběhla', 'Action failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.')); throw e; });
  }
  var DOMAIN_OPS = { 'domain-create': ['Registrace', 'Registration'], 'domain-renew': ['Prodloužení', 'Renewal'], 'domain-update-ns': ['Změna jmenných serverů', 'Nameserver change'], 'domain-transfer': ['Transfer k nám', 'Transfer in'], 'domain-send-auth-info': ['AUTH-ID', 'AUTH-ID'], 'domain-update-keyset': ['DNSSEC u registru', 'DNSSEC at the registry'], 'contact-create': ['Kontakt držitele', 'Registrant contact'], 'nsset-create': ['Sada jmenných serverů', 'Nameserver set'] };
  function domainBuild(cmp, sel, tab, _, X) {
    var H = X.H, cell = H.cell, A = H.act, F = H.F, s = H.s, infoPanel = X.infoPanel;
    var info = dinfo(cmp, sel), d = info && !info.__error ? info : null;
    var z = dzone(cmp, sel), zone = z && !z.__error ? z : null;
    var yes = _('zapnuto', 'on'), no = _('vypnuto', 'off');
    var dateOf = function (iso) { return iso ? String(iso).slice(0, 10) : '—'; };
    var errorPanel = function (title, msg) { return { key: 'real:err', title: title, note: _('Nepodařilo se načíst: ', 'Could not load: ') + msg, state: 'error', head: [], rows: [] }; };
    var onhostDns = sel.dns_provider === 'onhost';

    if (tab === 'reg') {
      if (!d) return info && info.__error ? errorPanel(sel.name, info.__error) : X.loading;
      var auth = state.resources[sel.id + ':authinfo'];
      var reg = d.registrant || null;
      var pairs = [
        [_('Stav', 'State'), sel.state], [_('Expirace', 'Expiry'), dateOf(d.expires_at) + (d.days_to_expiry != null ? ' · ' + _('za ', 'in ') + d.days_to_expiry + _(' dní', ' days') : '')],
        [_('Registrováno', 'Registered'), dateOf(d.registered_at)], [_('Automatická obnova', 'Auto-renew'), d.auto_renew ? yes + ' · ' + _('vždy na ', 'always for ') + (d.renewal_period || 1) + _(' rok(y)', ' year(s)') : no],
        [_('Zámek proti převodu', 'Transfer lock'), d.transfer_lock ? yes : no], [_('DNS', 'DNS'), onhostDns ? _('DNS u ONhost', 'ONhost DNS') : _('externí DNS', 'external DNS')],
        [_('Držitel', 'Registrant'), reg ? [reg.name, reg.organization_name, reg.email].filter(Boolean).join(' · ') : '—'], [_('Poslední kontrola registru', 'Last registry check'), since(cmp, d.last_reconciled_at) || '—']
      ];
      if (auth) pairs.push([_('AUTH-ID (zobrazeno jednorázově)', 'AUTH-ID (shown once)'), auth.code + (auth.expires_at ? ' · ' + _('platí do ', 'valid until ') + dateOf(auth.expires_at) : '')]);
      var p1 = infoPanel(_('Registrace a expirace · ', 'Registration and expiry · ') + sel.name, _('obnovujeme 14 dní před expirací z kreditu; držitelem jste vy a AUTH-ID pro převod vydáme na požádání po druhém ověření', 'we renew 14 days before expiry from credit; you are the registrant and the AUTH-ID for a transfer is issued on request after a second verification'), pairs);
      p1.extra = [
        { label: _('Prodloužit o 1 rok', 'Renew by 1 year'), primary: true, on: function () { if (window.confirm(_('Prodloužit ' + sel.name + ' o 1 rok? Cena se strhne z kreditu.', 'Renew ' + sel.name + ' by 1 year? The price is taken from credit.'))) dpost(cmp, sel, '/renew', { years: 1 }, ['domain'], _('Prodloužení zadáno', 'Renewal submitted'), _('Po potvrzení registru uvidíte nové datum expirace; vyúčtování najdete ve Fakturaci.', 'Once the registry confirms you will see the new expiry; the statement is under Billing.')); } },
        { label: d.auto_renew ? _('Vypnout automatickou obnovu', 'Turn auto-renew off') : _('Zapnout automatickou obnovu', 'Turn auto-renew on'), on: function () { dpost(cmp, sel, '/auto-renew', { enabled: !d.auto_renew }, ['domain'], _('Uloženo', 'Saved'), d.auto_renew ? _('Doménu už neobnovíme automaticky; napíšeme 30, 14 a 3 dny před expirací.', 'We will not renew automatically any more; we write 30, 14 and 3 days before expiry.') : _('Doménu obnovíme 14 dní před expirací z kreditu.', 'We renew 14 days before expiry from credit.')); } },
        { label: d.transfer_lock ? _('Odemknout převod', 'Unlock transfer') : _('Zamknout převod', 'Lock transfer'), on: function () { dpost(cmp, sel, '/transfer-lock', { locked: !d.transfer_lock }, ['domain'], _('Uloženo', 'Saved'), d.transfer_lock ? _('Doménu lze převést k jinému poskytovateli; nezapomeňte zámek znovu zapnout.', 'The domain can be transferred away; remember to lock it again.') : _('Převod pryč je zablokovaný.', 'Transfers away are blocked.')); } },
        { label: _('Vyžádat AUTH-ID', 'Request AUTH-ID'), on: function () {
          dpost(cmp, sel, '/auth-info', {}, ['domain'], _('AUTH-ID vyžádáno', 'AUTH-ID requested'), '').then(function (r) {
            if (r && r.delivery === 'inline' && r.auth_info && r.auth_info !== 'sent_to_registrant') { state.resources[sel.id + ':authinfo'] = { code: r.auth_info, expires_at: r.expires_at }; flash(cmp, _('AUTH-ID vydáno', 'AUTH-ID issued'), _('Kód je v tabulce; platí 7 dní a zobrazí se jen teď.', 'The code is in the table; it is valid for 7 days and shown only now.')); rerender(cmp); }
            else flash(cmp, _('AUTH-ID odesláno', 'AUTH-ID sent'), _('Registr ho posílá e-mailem držiteli domény.', 'The registry e-mails it to the domain holder.'));
          }).catch(function () {});
        } }
      ];
      return p1;
    }

    if (tab === 'soa') {
      var ns = (d && d.nameservers) || sel.nameservers || [];
      var p2 = infoPanel(_('Jmenné servery · ', 'Nameservers · ') + sel.name, _('DNS u ONhost = zónu spravujete v záložce DNS záznamy; vlastní jmenné servery = zónu vedete jinde. Změna u registru trvá minuty, rozšíření po internetu až den.', 'ONhost DNS = you manage the zone under DNS records; custom nameservers = the zone lives elsewhere. The registry change takes minutes, propagation up to a day.'),
        [[_('DNS', 'DNS'), onhostDns ? _('DNS u ONhost', 'ONhost DNS') : _('externí DNS', 'external DNS')], [_('Jmenné servery', 'Nameservers'), ns.length ? ns.join(', ') : '—'], [_('DNSSEC u registru', 'DNSSEC at the registry'), sel.dnssec ? yes : no]]);
      p2.form = { title: _('Vlastní jmenné servery', 'Custom nameservers'), fields: [F('a', 'ns1.example.net, ns2.example.net', '1 1 320px')], submit: _('Nastavit', 'Set'), on: function () {
        var list = String(s.wbF.a || '').toLowerCase().split(/[\s,;]+/).filter(Boolean);
        if (list.length < 2) { flash(cmp, _('Zadejte alespoň dva jmenné servery', 'Enter at least two nameservers'), ''); return; }
        dpost(cmp, sel, '/nameservers', { nameservers: list, dns_provider: 'external' }, ['domain'], _('Změna jmenných serverů zadána', 'Nameserver change submitted'), _('Registr změnu potvrdí během minut; průběh je v záložce Provoz a NOC.', 'The registry confirms within minutes; progress is under Operations and NOC.'));
      } };
      if (!onhostDns) p2.extra = [{ label: _('Použít DNS ONhost', 'Use ONhost DNS'), primary: true, on: function () { if (window.confirm(_('Založit zónu u ONhost a přesměrovat doménu na naše jmenné servery?', 'Create the zone at ONhost and point the domain at our nameservers?'))) dpost(cmp, sel, '/use-onhost-dns', { template: 'parking' }, ['domain', 'zone'], _('Zóna se zakládá', 'Creating the zone'), _('Po potvrzení registru se objeví záložka DNS záznamy.', 'Once the registry confirms, the DNS records tab appears.')); } }];
      return p2;
    }

    if (tab === 'dns') {
      if (!onhostDns) { var na = X.unavailable(_('DNS záznamy', 'DNS records')); na.note = _('Zóna této domény běží mimo ONhost. Přepněte ji na DNS ONhost v záložce SOA a jmenné servery.', 'This domain\'s zone runs outside ONhost. Switch it to ONhost DNS under SOA and nameservers.'); return na; }
      if (!zone) return z && z.__error ? errorPanel(_('DNS záznamy · ', 'DNS records · ') + sel.name, z.__error) : X.loading;
      var recs = zone.records || [], changes = zone.changes || [];
      var rows = recs.map(function (r) {
        return { cells: [cell(r.name, '0 0 150px', 1), cell(r.type + (r.prio != null && r.prio !== '' ? ' ' + r.prio : ''), '0 0 90px', 1), cell(r.content, '1 1 300px', 1), cell(String(r.ttl || ''), '0 0 70px', 1)], note: (r.protected ? _('spravovaný záznam', 'managed record') + (r.comment ? ' · ' : '') : '') + (r.comment || ''),
          actions: [A(_('Smazat', 'Delete'), function () { if (window.confirm(_('Smazat záznam ' + r.name + ' ' + r.type + '?', 'Delete record ' + r.name + ' ' + r.type + '?'))) zpost(cmp, sel, zone, '/changes', { change: 'delete', record_id: r.id, confirm_protected: !!r.protected }); })] };
      });
      changes.forEach(function (c) { var rec = c.record || c.previous || {}; rows.push({ cells: [cell(rec.name || '', '0 0 150px', 1), cell(rec.type || '', '0 0 90px', 1), cell(rec.content || '', '1 1 300px', 1), cell(String(rec.ttl || ''), '0 0 70px', 1)], note: _('čeká na publikování · ', 'pending publish · ') + c.op, actions: [] }); });
      return { key: 'real:zone', title: _('DNS záznamy · ', 'DNS records · ') + sel.name, note: _('změny se nejdřív připraví a pak publikují najednou; serial ', 'changes are staged first and published together; serial ') + (zone.serial || '—'),
        state: recs.length + ' ' + _('záznamů', 'records') + (changes.length ? ' · ' + changes.length + _(' čeká', ' pending') : ''),
        head: [cell(_('Název', 'Name'), '0 0 150px'), cell(_('Typ', 'Type'), '0 0 90px'), cell(_('Hodnota', 'Value'), '1 1 300px'), cell('TTL', '0 0 70px')],
        rows: rows.length ? rows : [{ cells: [cell(_('zóna je prázdná', 'the zone is empty'), '1 1 300px')], note: '' }],
        form: { title: _('Přidat záznam', 'Add a record'), fields: [F('a', _('název (@ nebo www)', 'name (@ or www)'), '0 0 150px'), F('b', _('typ; u MX/SRV s prioritou (MX 10)', 'type; priority for MX/SRV (MX 10)'), '0 0 190px'), F('c', _('hodnota', 'value'), '1 1 260px')], submit: _('Připravit', 'Stage'), on: function () {
          var parts = String(s.wbF.b || '').trim().toUpperCase().split(/\s+/);
          var record = { name: String(s.wbF.a || '@').trim() || '@', type: parts[0] || 'A', content: String(s.wbF.c || '').trim(), ttl: 3600 };
          if (parts[1]) record.prio = parseInt(parts[1], 10);
          if (!record.content) { flash(cmp, _('Zadejte hodnotu záznamu', 'Enter the record value'), ''); return; }
          zpost(cmp, sel, zone, '/changes', { change: 'add', record: record });
        } },
        extra: [
          { label: _('Publikovat změny', 'Publish changes') + (changes.length ? ' (' + changes.length + ')' : ''), primary: true, on: function () { if (!changes.length) { flash(cmp, _('Nic k publikování', 'Nothing to publish'), ''); return; } zpost(cmp, sel, zone, '/commit', {}, _('Zóna publikována', 'Zone published'), _('Nová verze zóny je na jmenných serverech; rozšíření po internetu trvá až TTL.', 'The new zone version is on the nameservers; propagation takes up to the TTL.')); } },
          { label: _('Zahodit změny', 'Discard changes'), on: function () { if (changes.length) zpost(cmp, sel, zone, '/discard', {}, _('Změny zahozeny', 'Changes discarded'), ''); } },
          { label: _('Obnovit', 'Refresh'), on: function () { forget(sel, ['zone']); rerender(cmp); } }
        ] };
    }

    if (tab === 'sec') {
      var p4 = infoPanel(_('DNSSEC · ', 'DNSSEC · ') + sel.name, _('zónu podepíšeme my a DS záznam publikujeme u registru; před přechodem na jiné DNS DNSSEC nejdřív vypněte', 'we sign the zone and publish the DS record at the registry; turn DNSSEC off before moving to other DNS'),
        [[_('DNSSEC u registru', 'DNSSEC at the registry'), sel.dnssec ? yes : no], [_('DNSSEC v zóně', 'DNSSEC in the zone'), !onhostDns ? _('zóna je mimo ONhost', 'the zone is outside ONhost') : (zone ? (zone.dnssec ? yes : no) : _('načítám…', 'loading…'))], [_('DS záznamy', 'DS records'), zone && zone.ds && zone.ds.length ? zone.ds.join(' | ') : '—']]);
      p4.extra = [];
      if (onhostDns && zone) {
        p4.extra.push({ label: zone.dnssec ? _('Vypnout DNSSEC v zóně', 'Turn DNSSEC off in the zone') : _('Zapnout DNSSEC v zóně', 'Turn DNSSEC on in the zone'), primary: !zone.dnssec, on: function () { zpost(cmp, sel, zone, '/dnssec', { enabled: !zone.dnssec }, _('DNSSEC', 'DNSSEC'), zone.dnssec ? _('Podpisy zóny jsou vypnuté; DS záznam u registru odstraňte přes podporu.', 'Zone signing is off; have the DS record at the registry removed through support.') : _('Zóna je podepsaná; teď publikujte DS záznam u registru.', 'The zone is signed; now publish the DS record at the registry.')); } });
        if (zone.dnssec && zone.ds && zone.ds.length) p4.extra.push({ label: _('Publikovat DS u registru', 'Publish DS at the registry'), primary: true, on: function () { dpost(cmp, sel, '/dnssec/publish', {}, ['domain'], _('DS záznam publikován', 'DS record published'), _('Validující resolvery začnou podpisy ověřovat během hodin.', 'Validating resolvers start checking signatures within hours.')); } });
      }
      return p4;
    }

    if (tab === 'noc') {
      var ops = d ? (d.registrar_operations || []) : null;
      var st = { SUCCEEDED: ['hotovo', 'done'], FAILED: ['selhalo', 'failed'], PENDING_REGISTRY: ['čeká na registr', 'waiting for the registry'], SENT: ['odesláno', 'sent'], UNKNOWN: ['ověřujeme', 'verifying'] };
      var rowsOps = ops === null ? [{ cells: [cell(_('načítám…', 'loading…'), '1 1 300px')], note: '' }] : (ops.length ? ops.map(function (o) {
        var label = DOMAIN_OPS[o.command] ? _(DOMAIN_OPS[o.command][0], DOMAIN_OPS[o.command][1]) : o.command;
        return { cells: [cell(label, '1 1 220px'), cell(st[o.state] ? _(st[o.state][0], st[o.state][1]) : String(o.state || '').toLowerCase(), '0 0 150px'), cell(since(cmp, o.completed_at || o.sent_at), '0 0 130px', 1)], note: o.normalized_error ? String(o.normalized_error).toLowerCase().replace(/_/g, ' ') : '', actions: [] };
      }) : [{ cells: [cell(_('zatím žádné operace', 'no operations yet'), '1 1 300px')], note: '' }]);
      return { key: 'real:dops', title: _('Provoz · ', 'Operations · ') + sel.name, note: _('každý zásah u registru je zaznamenaný; selhání zkoušíme znovu a hlásíme', 'every registry change is recorded; failures are retried and reported'), state: ops ? ops.length + ' ' + _('operací', 'operations') : '', head: [cell(_('Operace', 'Operation'), '1 1 220px'), cell(_('Stav', 'State'), '0 0 150px'), cell(_('Kdy', 'When'), '0 0 130px')], rows: rowsOps, extra: [{ label: _('Obnovit', 'Refresh'), on: function () { forget(sel, ['domain']); rerender(cmp); } }] };
    }

    return X.unavailable(sel.name);
  }

  /* The web/mail toolkit module (onhost-panel-tools.api.js, seam #31) adds tabs to TABS and enhances or replaces
   * panels through build(): it receives the core panel plus the helpers it needs and returns the final one. */
  function helpers() { return { act: act, resource: resource, features: features, forget: forget, rerender: rerender, flash: flash, since: since, load: load, state: state, password: password, T: T, family: family, backupsPanel: backupsPanel }; }
  function build(cmp, sel, tab, _) {
    var core = buildCore(cmp, sel, tab, _);
    var tools = window.OnhostPanelTools;
    if (!tools || !real(sel)) return core;
    try { return tools.enhance(cmp, sel, tab, _, core, helpers()) || core; }
    catch (e) { return core; }
  }

  window.OnhostPanelWorkbench = { tabs: tabs, build: build, features: features, state: state, TABS: TABS };
})();
