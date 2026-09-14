/* onhost-panel-projects.api.js — the projects page of the customer panel (data seam #35).
 *
 * Projects partition an organization: services are assigned to a project, spend is reported per project and a member's
 * project role applies to that project's services only. Everything comes from /v1/organizations/{id}/projects and
 * /v1/services/{id}/project; the views use the prototype's generic view shape (crumb/title/stats/form/cols/rows/side/advice)
 * so the surface stays byte-identical. Helpers (stat/pill/bar/dot/rowStyle/match) come from the prototype's render scope. */
(function () {
  'use strict';
  if (window.OnhostPanelProjects) return;
  var S = { list: null, detail: null, detailId: null, services: null, busy: {} };
  var FALLBACK_ROLES = [['viewer', 'jen čtení', 'viewer'], ['developer', 'vývojář', 'developer'], ['cloud_operator', 'operátor cloudu', 'cloud operator'], ['domain_manager', 'správce domén', 'domain manager'], ['dns_manager', 'správce DNS', 'DNS manager'], ['mail_manager', 'správce pošty', 'mail manager'], ['billing_admin', 'fakturace', 'billing'], ['support_contact', 'kontakt pro podporu', 'support contact']];

  function A() { return window.OnhostApi; }
  function me() { return (window.ONHOST && window.ONHOST.user) || null; }
  function orgId() { var u = me(); return u && u.organization ? u.organization.id : null; }
  function isCs(cmp) { return !cmp || !cmp.state || cmp.state.lang !== 'en'; }
  function rerender(cmp) { try { cmp.setState({ projectsAt: Date.now() }); } catch (e) {} }
  function flash(cmp, t, b) { if (cmp && typeof cmp.flash === 'function') cmp.flash(t, b); }
  function fail(cmp, _, e) { flash(cmp, _('Nepodařilo se', 'Failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.')); }
  function base() { return '/organizations/' + encodeURIComponent(orgId()) + '/projects'; }
  function money(m, cs) { if (!m) return '—'; var n = Number(m.decimal != null ? m.decimal : (m.minor || 0) / 100); return n.toLocaleString(cs ? 'cs-CZ' : 'en-GB', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ' + (m.currency || ''); }
  function day(iso, cs) { if (!iso) return '—'; return new Date(iso).toLocaleDateString(cs ? 'cs-CZ' : 'en-GB'); }
  function roles() { var acct = window.OnhostPanelAccount; return (acct && acct.roles) || FALLBACK_ROLES; }
  function roleKey(label) { var r = roles().filter(function (x) { return x[1] === label || x[2] === label || x[0] === label; })[0]; return r ? r[0] : label; }
  function roleLabel(key, cs) { var r = roles().filter(function (x) { return x[0] === key; })[0]; return r ? (cs ? r[1] : r[2]) : key; }
  function canManage() { var u = me() || {}; return !u.member_role || u.member_role === 'owner' || u.member_role === 'org_admin'; }

  function loadList(cmp) {
    if (S.list !== null || S.busy.list || !A() || !orgId()) return;
    S.busy.list = true;
    A().get(base()).then(function (r) { S.list = r; }).catch(function () { S.list = { data: [], spend: null }; }).then(function () { delete S.busy.list; rerender(cmp); });
  }
  function loadDetail(cmp, id) {
    if (S.detailId === id && (S.detail !== null || S.busy.detail)) return;
    S.detailId = id; S.detail = null; S.busy.detail = true;
    A().get(base() + '/' + encodeURIComponent(id)).then(function (r) { S.detail = r.data || r; }).catch(function () { S.detail = null; }).then(function () { delete S.busy.detail; rerender(cmp); });
  }
  function loadServices(cmp) {
    if (S.services !== null || S.busy.services || !A()) return;
    S.busy.services = true;
    A().get('/services?limit=200').then(function (r) { S.services = r.data || []; }).catch(function () { S.services = []; }).then(function () { delete S.busy.services; rerender(cmp); });
  }
  function reload(cmp) { S.list = null; S.detail = null; S.detailId = null; S.services = null; rerender(cmp); }
  function back(cmp) { return function () { cmp.setState({ projSel: null, query: '' }); }; }

  function assign(cmp, _, service, projectId, label) {
    A().post('/services/' + encodeURIComponent(service) + '/project', { project_id: projectId }, A().key())
      .then(function () { flash(cmp, projectId ? _('Služba zařazena do projektu', 'Service assigned to the project') : _('Služba vyřazena z projektu', 'Service removed from the project'), label || ''); reload(cmp); })
      .catch(function (e) { fail(cmp, _, e); });
  }

  /* ── list ─────────────────────────────────────────────────────────────── */
  function listView(cmp, _, H, cs, s) {
    loadList(cmp);
    var list = S.list || { data: [], spend: null }, rows = list.data || [], spend = list.spend || {};
    var active = rows.filter(function (p) { return p.state !== 'archived'; });
    var services = rows.reduce(function (n, p) { return n + (p.services || 0); }, 0);
    var unassigned = (spend.unassigned && spend.unassigned.services) || 0;
    return {
      crumb: _('Nastavení', 'Settings'), title: _('Projekty', 'Projects'),
      stats: [
        H.stat(_('Projekty', 'Projects'), String(active.length), rows.length - active.length ? String(rows.length - active.length) + _(' archivovaných', ' archived') : '', 4, Math.min(100, active.length * 20), 10, 'ok'),
        H.stat(_('Zařazené služby', 'Assigned services'), String(services), '', 8, Math.min(100, services * 10), 12, 'ok'),
        H.stat(_('Měsíční útrata', 'Monthly spend'), money(spend.monthly_total, cs), _('obnovy vč. DPH', 'renewals incl. VAT'), 12, 60, 14, 'ok'),
        H.stat(_('Nezařazeno', 'Unassigned'), String(unassigned), unassigned ? _('služeb bez projektu', 'services without a project') : '', 16, unassigned ? 40 : 10, 8, unassigned ? 'warn' : 'ok')
      ],
      form: canManage() ? {
        title: _('Nový projekt', 'New project'), note: _('projekt rozděluje služby, útratu a práva · role v projektu platí jen pro jeho služby', 'a project partitions services, spend and rights · a project role applies to its services only'),
        fields: [{ key: 'projName', label: _('Název', 'Name'), ph: 'E-shop', kind: 'text' }, { key: 'projDesc', label: _('Popis', 'Description'), ph: _('web, e-shop a jejich domény', 'site, shop and their domains'), kind: 'text' }],
        cta: _('Založit projekt', 'Create project'), hint: _('Nákladové středisko a štítky nastavíte v detailu projektu.', 'Set the cost centre and tags in the project detail.'),
        submit: function () {
          var name = String(s.projName || '').trim();
          if (!name) { flash(cmp, _('Chybí název', 'Name missing'), _('Pojmenujte projekt.', 'Name the project.')); return; }
          A().post(base(), { name: name, description: String(s.projDesc || '').trim() || null }, A().key())
            .then(function () { cmp.setState({ projName: '', projDesc: '' }); flash(cmp, _('Projekt založen', 'Project created'), name); reload(cmp); })
            .catch(function (e) { fail(cmp, _, e); });
        }
      } : null,
      tableTitle: _('Projekty', 'Projects'), tableNote: _('služby, útrata a podíl na celku', 'services, spend and share of the total'),
      cols: [_('Projekt', 'Project'), _('Obsah', 'Contents'), _('Stav', 'State'), _('Měsíčně', 'Monthly'), ''],
      rows: rows.filter(function (p) { return H.match(p.name) || H.match(p.slug || ''); }).map(function (p) {
        var archived = p.state === 'archived';
        return {
          name: p.name, sub: [p.description, p.cost_center ? _('středisko ', 'cost centre ') + p.cost_center : null, (p.tags || []).join(', ')].filter(Boolean).join(' · ') || p.slug || '',
          c2: String(p.services || 0) + _(' služeb', ' services') + (p.members ? ' · ' + p.members + _(' členů', ' members') : ''),
          state: archived ? _('archivován', 'archived') : _('aktivní', 'active'), stateStyle: H.pill(archived ? 'off' : 'ok'), barStyle: H.bar(Math.max(2, Number(p.share) || 0), archived ? 'off' : 'ok'),
          metric: money(p.monthly, cs) + (p.share ? ' · ' + p.share + ' %' : ''), rowStyle: H.rowStyle,
          action: _('Otevřít', 'Open'), actionCls: 'btn btn-secondary', onAction: function () { cmp.setState({ projSel: p.id, query: '' }); }
        };
      }),
      filters: [], readOnly: true,
      side: {
        title: _('Útrata podle projektů', 'Spend by project'),
        rows: rows.map(function (p) {
          return { title: p.name, meta: String(p.services || 0) + _(' služeb · ', ' services · ') + (p.share || 0) + ' %', value: money(p.monthly, cs), kind: p.state === 'archived' ? 'off' : 'ok', on: function () { cmp.setState({ projSel: p.id, query: '' }); } };
        }).concat(spend.unassigned ? [{ title: _('Nezařazeno', 'Unassigned'), meta: String(spend.unassigned.services || 0) + _(' služeb', ' services'), value: money(spend.unassigned.monthly, cs), kind: spend.unassigned.services ? 'warn' : 'off' }] : [])
      },
      advice: {
        title: _('Role platí v rámci projektu', 'Roles apply inside a project'),
        lead: _('Člen organizace může být vývojářem jen jednoho projektu: nasazuje a spravuje jeho služby, ostatní vidí, ale nemění. Otevřete projekt a přidejte člena s rolí.', 'An organization member can be the developer of one project only: they deploy and manage its services and merely see the rest. Open a project and add a member with a role.'),
        cta: rows.length ? _('Otevřít první projekt', 'Open the first project') : '',
        on: function () { if (rows[0]) cmp.setState({ projSel: rows[0].id, query: '' }); }
      }
    };
  }

  /* ── detail ───────────────────────────────────────────────────────────── */
  function detailView(cmp, _, H, cs, s, id) {
    loadList(cmp); loadDetail(cmp, id); loadServices(cmp);
    var p = S.detail;
    if (!p) {
      return { crumb: _('Projekty', 'Projects'), title: _('Načítám projekt…', 'Loading the project…'), stats: [], cols: [], rows: [], filters: [], readOnly: true, side: { title: _('Projekty', 'Projects'), rows: [{ title: _('← Zpět na projekty', '← Back to projects'), meta: '', value: '', kind: 'off', on: back(cmp) }] } };
    }
    var members = p.members || [], services = p.services || [], spend = p.spend || {}, archived = p.state === 'archived', manage = canManage();
    var candidates = (S.services || []).filter(function (x) { return x.project_id !== p.id && x.state !== 'TERMINATED'; });
    var path = base() + '/' + encodeURIComponent(p.id);
    var settingsRow = {
      name: _('Nastavení projektu', 'Project settings'), sub: [p.description, p.cost_center ? _('středisko ', 'cost centre ') + p.cost_center : null, (p.tags || []).join(', ')].filter(Boolean).join(' · ') || _('popis, nákladové středisko, štítky', 'description, cost centre, tags'),
      c2: 'slug ' + (p.slug || ''), state: archived ? _('archivován', 'archived') : _('aktivní', 'active'), stateStyle: H.pill(archived ? 'off' : 'ok'), barStyle: H.bar(100, archived ? 'off' : 'ok'), metric: day(p.created_at, cs), rowStyle: H.rowStyle,
      action: manage ? _('Upravit', 'Edit') : '', actionCls: 'btn btn-secondary',
      onAction: function () {
        if (!manage) return;
        var name = window.prompt(_('Název projektu:', 'Project name:'), p.name); if (name === null) return;
        var cost = window.prompt(_('Nákladové středisko (prázdné = žádné):', 'Cost centre (empty = none):'), p.cost_center || ''); if (cost === null) return;
        var tags = window.prompt(_('Štítky oddělené čárkou:', 'Tags separated by commas:'), (p.tags || []).join(', ')); if (tags === null) return;
        A().patch(path, { name: name.trim() || p.name, cost_center: cost.trim() || null, tags: tags.split(',').map(function (t) { return t.trim(); }).filter(Boolean) })
          .then(function () { flash(cmp, _('Projekt upraven', 'Project updated'), name.trim() || p.name); reload(cmp); }).catch(function (e) { fail(cmp, _, e); });
      }
    };
    var serviceRows = services.filter(function (x) { return H.match(x.label || '') || H.match(x.hostname || '') || H.match(x.name || ''); }).map(function (x) {
      return {
        name: x.label || x.hostname || x.name, sub: (x.hostname || '') + (x.product_key ? ' · ' + x.product_key : ''), c2: _('služba', 'service'),
        state: x.ui || x.state, stateStyle: H.pill(x.state === 'ACTIVE' ? 'ok' : (x.state === 'TERMINATED' ? 'off' : 'warn')), barStyle: H.bar(100, x.state === 'ACTIVE' ? 'ok' : 'warn'), metric: day(x.created_at, cs), rowStyle: H.rowStyle,
        action: manage && !archived ? _('Vyřadit', 'Remove') : '', actionCls: 'btn btn-secondary',
        onAction: function () { if (manage && !archived) assign(cmp, _, x.id, null, x.label || x.hostname); }
      };
    });
    var memberRows = members.filter(function (m) { return H.match(m.name || '') || H.match(m.email || ''); }).map(function (m) {
      return {
        name: m.name || m.email || '—', sub: m.email || '', c2: roleLabel(m.role, cs), state: _('člen projektu', 'project member'), stateStyle: H.pill('ok'), barStyle: H.bar(60, 'ok'), metric: day(m.since, cs), rowStyle: H.rowStyle,
        action: manage ? _('Odebrat', 'Remove') : '', actionCls: 'btn btn-secondary',
        onAction: function () {
          if (!manage || !window.confirm(_('Odebrat ' + (m.email || m.name) + ' z projektu? Role v organizaci zůstane.', 'Remove ' + (m.email || m.name) + ' from the project? The organization role stays.'))) return;
          A().del(path + '/members/' + encodeURIComponent(m.user_id)).then(function () { flash(cmp, _('Člen odebrán', 'Member removed'), m.email || ''); reload(cmp); }).catch(function (e) { fail(cmp, _, e); });
        }
      };
    });
    return {
      crumb: _('Projekty', 'Projects'), title: p.name,
      stats: [
        H.stat(_('Služby', 'Services'), String(services.length), '', 3, Math.min(100, services.length * 15), 12, 'ok'),
        H.stat(_('Členové', 'Members'), String(members.length), '', 7, Math.min(100, members.length * 20), 10, 'ok'),
        H.stat(_('Měsíčně', 'Monthly'), money(spend.monthly, cs), _('obnovy vč. DPH', 'renewals incl. VAT'), 11, 55, 14, 'ok'),
        H.stat(_('Podíl na útratě', 'Share of spend'), String(spend.share || 0) + ' %', spend.budget ? _('rozpočet ', 'budget ') + money(spend.budget.limit, cs) : '', 15, Math.min(100, Number(spend.share) || 0), 8, 'ok')
      ],
      form: manage && !archived ? {
        title: _('Přidat člena projektu', 'Add a project member'), note: _('člen organizace dostane roli jen pro služby tohoto projektu', 'an organization member gets a role for this project\'s services only'),
        fields: [{ key: 'projMemEmail', label: 'E-mail', ph: 'kolega@firma.cz', kind: 'text' }, { key: 'projMemRole', label: _('Role v projektu', 'Project role'), kind: 'select', options: roles().filter(function (r) { return r[0] !== 'owner' && r[0] !== 'org_admin'; }).map(function (r) { return cs ? r[1] : r[2]; }) }],
        cta: _('Přidat', 'Add'), hint: _('Nejdřív musí být členem organizace (Tým a práva); jeho organizační role zůstává základem.', 'They must be an organization member first (Team and roles); the organization role stays the floor.'),
        submit: function () {
          var email = String(s.projMemEmail || '').trim(), role = roleKey(s.projMemRole || (cs ? 'vývojář' : 'developer'));
          if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { flash(cmp, _('Neplatný e-mail', 'Invalid e-mail'), ''); return; }
          A().post(path + '/members', { email: email, role: role }, A().key())
            .then(function () { cmp.setState({ projMemEmail: '' }); flash(cmp, _('Člen přidán', 'Member added'), email + ' · ' + roleLabel(role, cs)); reload(cmp); })
            .catch(function (e) { fail(cmp, _, e); });
        }
      } : null,
      tableTitle: _('Služby a členové', 'Services and members'), tableNote: _('co do projektu patří a kdo v něm má roli', 'what belongs to the project and who holds a role in it'),
      cols: [_('Položka', 'Item'), _('Typ', 'Type'), _('Stav', 'State'), _('Od', 'Since'), ''],
      rows: [settingsRow].concat(serviceRows, memberRows),
      filters: [], readOnly: true,
      side: {
        title: _('Přiřadit službu', 'Assign a service'),
        rows: [{ title: _('← Zpět na projekty', '← Back to projects'), meta: '', value: '', kind: 'off', on: back(cmp) }].concat(manage && !archived ? candidates.slice(0, 12).map(function (x) {
          return { title: x.label || x.hostname || x.name, meta: (x.project_id ? _('nyní v jiném projektu', 'now in another project') : _('nezařazeno', 'unassigned')) + (x.product_key ? ' · ' + x.product_key : ''), value: _('Přiřadit →', 'Assign →'), kind: 'ok', on: function () { assign(cmp, _, x.id, p.id, x.label || x.hostname); } };
        }) : []).concat(manage && !archived && !candidates.length ? [{ title: _('Všechny služby už jsou v tomto projektu', 'Every service is already in this project'), meta: '', value: '', kind: 'off' }] : [])
      },
      advice: archived ? {
        title: _('Projekt je archivován', 'The project is archived'), lead: _('Archivovaný projekt nepřijímá služby ani členy. Obnovením se vrátí mezi aktivní.', 'An archived project takes no services or members. Restoring it makes it active again.'),
        cta: manage ? _('Obnovit projekt', 'Restore project') : '',
        on: function () { if (!manage) return; A().post(path + '/restore', {}, A().key()).then(function () { flash(cmp, _('Projekt obnoven', 'Project restored'), p.name); reload(cmp); }).catch(function (e) { fail(cmp, _, e); }); }
      } : {
        title: _('Archivovat projekt', 'Archive the project'), lead: _('Archivace vyžaduje prázdný projekt (bez služeb); poslední aktivní projekt organizace zůstává. Členové projektu o roli přijdou až s jeho smazáním, ne archivací.', 'Archiving needs an empty project (no services); the organization\'s last active project stays.'),
        cta: manage ? _('Archivovat', 'Archive') : '',
        on: function () {
          if (!manage || !window.confirm(_('Archivovat projekt ' + p.name + '?', 'Archive project ' + p.name + '?'))) return;
          A().post(path + '/archive', {}, A().key()).then(function () { flash(cmp, _('Projekt archivován', 'Project archived'), p.name); reload(cmp); }).catch(function (e) { fail(cmp, _, e); });
        }
      }
    };
  }

  window.OnhostPanelProjects = {
    view: function (cmp, _, H) { var cs = isCs(cmp), s = cmp.state; return s.projSel ? detailView(cmp, _, H, cs, s, s.projSel) : listView(cmp, _, H, cs, s); },
    reload: reload
  };
})();
