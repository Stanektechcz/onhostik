<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Provoz — přehled směny — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 14px; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  .topbar .spacer { flex: 1; }
  main { max-width: 1320px; margin: 0 auto; padding: 24px; display: grid; gap: 24px; }
  h1 { font-size: 26px; margin: 0 0 6px; }
  h2 { font-size: 16px; margin: 0 0 12px; letter-spacing: .04em; text-transform: uppercase; }
  .lead { color: var(--color-neutral-700, #605d5d); max-width: 900px; }
  .panel { border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); padding: 18px; }
  .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
  .kpi { border: 2px solid var(--color-text, #201e1d); padding: 12px 14px; }
  .kpi b { display: block; font-size: 26px; font-family: var(--font-heading, inherit); }
  .kpi span { font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); padding: 6px 8px; border-bottom: 1px solid var(--color-divider, #9a9797); }
  td { padding: 9px 8px; border-bottom: 1px solid color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); vertical-align: top; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
  .tag { display: inline-block; padding: 2px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; border: 1px solid var(--color-text, #201e1d); }
  .tag.ok { background: #b8ff2e; border-color: transparent; color: #1a1918; }
  .tag.warn { background: #e0a100; border-color: transparent; color: #1a1918; }
  .tag.bad { background: var(--color-accent, #ec3013); border-color: transparent; color: #fff; }
  .tag.off { background: color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); border-color: transparent; }
  .btn { font: inherit; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; padding: 7px 12px; border: 2px solid var(--color-text, #201e1d); background: transparent; color: inherit; cursor: pointer; }
  .btn.primary { background: var(--color-accent, #ec3013); border-color: var(--color-accent, #ec3013); color: #fff; }
  .btn:disabled { opacity: .5; cursor: default; }
  .actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .msg { padding: 10px 14px; border-left: 4px solid var(--color-accent, #ec3013); background: color-mix(in srgb, var(--color-accent, #ec3013) 10%, transparent); font-size: 13px; }
  .msg.ok { border-color: #5a9b00; background: color-mix(in srgb, #b8ff2e 30%, transparent); }
  .muted { color: var(--color-neutral-700, #605d5d); }
  .err { font-size: 12px; color: var(--color-neutral-700, #605d5d); overflow-wrap: anywhere; }
  .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Provoz</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/provozni-prehled"><strong>Přehled směny</strong></a>
  <a href="/sprava/nastaveni/provoz">Operace a uzly</a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Provoz: přehled směny</h1>
    <p class="lead">Jen ke čtení. Totéž, co hlídá <span class="mono">onhost:doctor</span> a metriky: běží plánovač a fronta, nic nečeká déle, než má, zálohy platformy jsou ověřené, bankovní výpis se páruje, certifikáty neexpirují a žádný panel ani uzel nestojí. Každou část vidí jen ten, kdo má její oprávnění. Obnovuje se každou minutu.</p>
    <div id="alert" hidden></div>
    <p class="muted" id="hidden" hidden></p>
  </section>
  <section class="kpis" id="kpis"></section>
  <div class="grid2">
    <section class="panel" id="automation" hidden>
      <h2>Automatizace</h2>
      <table><tbody id="automation-rows"></tbody></table>
      <h2 style="margin-top:16px">Plánované úlohy po rozpočtu</h2>
      <table><thead><tr><th>Úloha</th><th>Naposledy</th><th>Rozpočet</th><th>Selhání po sobě</th></tr></thead><tbody id="overdue"></tbody></table>
    </section>
    <section class="panel" id="backups" hidden>
      <h2>Zálohy platformy</h2>
      <table><tbody id="backup-rows"></tbody></table>
    </section>
    <section class="panel" id="payments" hidden>
      <h2>Platby a upomínky</h2>
      <table><tbody id="payment-rows"></tbody></table>
    </section>
    <section class="panel" id="infrastructure" hidden>
      <h2>Infrastruktura</h2>
      <table><tbody id="infra-rows"></tbody></table>
      <h2 style="margin-top:16px">Uzly čekající na kvalifikaci</h2>
      <table><thead><tr><th>Uzel</th><th>Role</th><th>Region</th><th>Od</th></tr></thead><tbody id="qualifying"></tbody></table>
    </section>
  </div>
</main>
<script>
(function () {
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function when(iso) { if (!iso) return '—'; var d = new Date(iso); return d.toLocaleDateString('cs-CZ') + ' ' + d.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' }); }
  function age(s) { if (s == null) return 'nikdy'; if (s >= 86400) return (s / 86400).toFixed(1) + ' d'; if (s >= 3600) return (s / 3600).toFixed(1) + ' h'; if (s >= 60) return Math.round(s / 60) + ' min'; return s + ' s'; }
  function tag(ok, yes, no) { return '<span class="tag ' + (ok ? 'ok' : 'bad') + '">' + esc(ok ? yes : no) + '</span>'; }
  function row(label, value) { return '<tr><th>' + esc(label) + '</th><td>' + value + '</td></tr>'; }
  function show(id, on) { document.getElementById(id).hidden = !on; }
  var NAMES = { automation: 'automatizace', backups: 'zálohy', payments: 'platby', infrastructure: 'infrastruktura' };
  function automation(a, kpis) {
    kpis.push(['Po rozpočtu', a.scheduled_jobs.overdue_count], ['Backlog operací', a.backlog.stale], ['Dead letters', a.dead_letters.count]);
    document.getElementById('automation-rows').innerHTML = row('Plánovač', tag(a.scheduler.alive, 'běží', 'neběží') + ' <span class="muted">' + esc(when(a.scheduler.last_seen_at)) + '</span>')
      + row('Fronta (' + a.worker.driver + ')', tag(a.worker.alive, 'běží', 'neběží') + ' <span class="muted">' + esc(when(a.worker.last_seen_at)) + '</span>')
      + row('Backlog operací', esc(a.backlog.stale) + ' <span class="muted">(starší než ' + esc(a.backlog.age_minutes) + ' min, práh ' + esc(a.backlog.threshold) + ')</span>' + (a.backlog.alert ? ' ' + tag(false, '', 'nad prahem') : ''))
      + row('Outbox: dead letters', esc(a.dead_letters.count) + (a.dead_letters.count ? ' <span class="muted">nejstarší ' + esc(age(a.dead_letters.oldest_seconds)) + '</span>' : ''))
      + row('Selhané úlohy fronty', esc(a.failed_jobs))
      + row('Plánované úlohy', esc(a.scheduled_jobs.count) + ', po rozpočtu ' + esc(a.scheduled_jobs.overdue_count));
    document.getElementById('overdue').innerHTML = a.scheduled_jobs.overdue.length ? a.scheduled_jobs.overdue.map(function (j) { return '<tr><td class="mono">' + esc(j.command) + '</td><td>' + esc(age(j.age_seconds)) + '</td><td>' + esc(age(j.budget_seconds)) + '</td><td>' + esc(j.failures_in_a_row) + '</td></tr>'; }).join('') : '<tr><td colspan="4" class="muted">každá úloha v rozpočtu</td></tr>';
  }
  function backups(b, kpis) {
    var pl = b.platform, dr = b.drill;
    kpis.push(['Záloha ověřená', pl.verified_within_26h ? 'ano' : 'ne']);
    document.getElementById('backup-rows').innerHTML = row('Poslední sada', esc(pl.last_set || '—') + ' <span class="muted">' + esc(when(pl.last_at)) + '</span>')
      + row('Ověřená sada', esc(pl.verified_set || '—') + ' ' + tag(pl.verified_within_26h, 'do 26 h', 'starší než 26 h') + ' <span class="muted">' + esc(when(pl.verified_at)) + '</span>')
      + row('Disk', esc(pl.disk) + ' <span class="muted">· retence ' + esc(pl.retention_days) + ' dní</span>')
      + (dr ? row('Cvičná obnova', dr.at ? tag(dr.ok && dr.within_35_days, 'v pořádku', dr.ok ? 'starší než 35 dní' : 'selhala') + ' <span class="muted">' + esc(when(dr.at)) + ' · ' + esc(dr.set || '') + '</span>' : '<span class="muted">zatím neproběhla</span>') : '');
  }
  function payments(p, kpis) {
    kpis.push(['Nespárované řádky', p.bank.unmatched_lines], ['Otevřené upomínky', p.dunning.open]);
    document.getElementById('payment-rows').innerHTML = row('Poslední řádek výpisu', esc(when(p.bank.last_line_at)))
      + row('Nespárované řádky', esc(p.bank.unmatched_lines))
      + row('Upomínky', esc(p.dunning.open) + (Object.keys(p.dunning.by_state).length ? ' <span class="muted">(' + Object.keys(p.dunning.by_state).map(function (k) { return esc(k) + ' ' + esc(p.dunning.by_state[k]); }).join(', ') + ')</span>' : ''));
  }
  function infrastructure(i, kpis) {
    kpis.push(['Certifikáty do 7 dní', i.certificates.expiring_7d], ['Otevřené breakery', i.breakers_open.count]);
    document.getElementById('infra-rows').innerHTML = row('Certifikáty', 'expirují do 7 dní: ' + esc(i.certificates.expiring_7d) + ', prošlé: ' + esc(i.certificates.expired))
      + row('Panely držené na neověřené verzi', i.panels_held.count ? i.panels_held.panels.map(function (x) { return '<span class="mono">' + esc(x.instance) + '</span> ' + esc(x.version || ''); }).join(', ') : '<span class="muted">žádný</span>')
      + row('Otevřené breakery', i.breakers_open.count ? i.breakers_open.instances.map(function (x) { return '<span class="mono">' + esc(x.instance) + '</span> ' + esc(x.state); }).join(', ') : '<span class="muted">žádný</span>');
    document.getElementById('qualifying').innerHTML = i.nodes_qualifying.nodes.length ? i.nodes_qualifying.nodes.map(function (n) { return '<tr><td>' + esc(n.name) + '</td><td>' + esc(n.role) + '</td><td>' + esc(n.region) + '</td><td>' + esc(when(n.since)) + '</td></tr>'; }).join('') : '<tr><td colspan="4" class="muted">žádný</td></tr>';
  }
  function render(d) {
    var s = d.sections || {}, kpis = [];
    show('automation', !!s.automation); show('backups', !!s.backups); show('payments', !!s.payments); show('infrastructure', !!s.infrastructure);
    var hidden = document.getElementById('hidden');
    hidden.hidden = !(d.hidden || []).length;
    hidden.textContent = (d.hidden || []).length ? 'Bez oprávnění nevidíte: ' + d.hidden.map(function (h) { return NAMES[h] || h; }).join(', ') + '.' : '';
    if (s.automation) { automation(s.automation, kpis); }
    if (s.backups) { backups(s.backups, kpis); }
    if (s.payments) { payments(s.payments, kpis); }
    if (s.infrastructure) { infrastructure(s.infrastructure, kpis); }
    document.getElementById('kpis').innerHTML = kpis.map(function (k) { return '<div class="kpi"><b>' + esc(k[1]) + '</b><span>' + esc(k[0]) + '</span></div>'; }).join('');
  }
  function load() {
    fetch('/v1/staff/operations/overview', { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) { throw new Error(j.message || r.statusText); } return j; }); })
      .then(function (j) { document.getElementById('alert').hidden = true; render(j.data || {}); })
      .catch(function (e) { var a = document.getElementById('alert'); a.hidden = false; a.className = 'msg'; a.textContent = 'Přehled se nepodařilo načíst: ' + e.message; });
  }
  load();
  setInterval(load, 60000);
})();
</script>
</body>
</html>
