<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Nastavení systému · Hromadné akce — ONhost</title>
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
  form.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
  .field { display: flex; flex-direction: column; gap: 5px; }
  .field label { font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); }
  .field input, .field select, .field textarea { font: inherit; font-size: 13px; padding: 8px 10px; border: 1px solid var(--color-text, #201e1d); background: #fff; color: inherit; }
  .field textarea { min-height: 84px; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; }
  .field .hint { font-size: 11.5px; color: var(--color-neutral-700, #605d5d); }
  .span2 { grid-column: span 2; }
  .msg { padding: 10px 14px; border-left: 4px solid var(--color-accent, #ec3013); background: color-mix(in srgb, var(--color-accent, #ec3013) 10%, transparent); font-size: 13px; }
  .msg.ok { border-color: #5a9b00; background: color-mix(in srgb, #b8ff2e 30%, transparent); }
  .muted { color: var(--color-neutral-700, #605d5d); }
  .bar { height: 6px; background: color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); position: relative; margin-top: 6px; }
  .bar i { position: absolute; left: 0; top: 0; bottom: 0; background: #5a9b00; }
  .bar b { position: absolute; top: 0; bottom: 0; background: var(--color-accent, #ec3013); }
  details summary { cursor: pointer; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Nastavení systému</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/integrace">Integrace</a>
  <a href="/sprava/nastaveni/provoz">Provoz</a>
  <a href="/sprava/nastaveni/hromadne-akce"><strong>Hromadné akce</strong></a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Hromadné akce</h1>
    <p class="lead">Jedna akce na všechny aktivní služby, které vybere filtr: rollout verze PHP na uzlu, bezpečnostní baseline u zákazníka, nové vystavení certifikátů, záloha před údržbou. Každá služba dostane vlastní operaci ve standardním workflow (audit, opakování, hlášení) a job ukazuje průběh po službách. Akce, které mažou data, hromadně nejdou.</p>
    <div id="alert" hidden></div>
  </section>

  <section class="panel">
    <h2>Nová hromadná akce</h2>
    <form class="grid" id="form">
      <div class="field"><label>Akce</label><select name="action">@foreach ($actions as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach</select></div>
      <div class="field"><label>Parametry (JSON)</label><textarea name="params" placeholder='{"version":"8.3"}'></textarea><span class="hint">např. php.set {"version":"8.3"} · security.set {"rules":{"hsts":true,"headers":true}} · index.set {"names":["index.php","index.html"]}</span></div>
      <div class="field"><label>Instance (klíč nebo id)</label><input name="provider_instance_id" placeholder="ispconfig-shared01"></div>
      <div class="field"><label>Uzel (id)</label><input name="node_id" placeholder="node_…"></div>
      <div class="field"><label>Zákazník (organization id)</label><input name="organization_id" placeholder="org_…"></div>
      <div class="field"><label>Produkt</label><input name="product_key" placeholder="web-hosting"></div>
      <div class="field"><label>Rodina</label><select name="family"><option value="">—</option><option value="web">web</option><option value="managed">managed</option><option value="cloud">cloud</option><option value="game">game</option><option value="mail">mail</option></select></div>
      <div class="field"><label>Služby (id, oddělené čárkou)</label><input name="service_ids" placeholder="srv_…, srv_…"></div>
      <div class="field span2"><label>Důvod</label><input name="reason" placeholder="rollout PHP 8.3 před koncem podpory 8.1"></div>
      <div class="field"><label>&nbsp;</label><button class="btn primary" type="submit">Spustit</button></div>
    </form>
  </section>

  <section class="panel">
    <h2>Joby</h2>
    <table><thead><tr><th>Job</th><th>Akce</th><th>Filtr</th><th>Průběh</th><th>Stav</th><th></th></tr></thead><tbody id="jobs"><tr><td colspan="6" class="muted">načítám…</td></tr></tbody></table>
  </section>
</main>
<script>
(function () {
  var API = '/v1';
  var csrf = document.querySelector('meta[name=csrf-token]').getAttribute('content');
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  function key() { return 'bulk-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8); }
  function api(method, path, body) {
    return fetch(API + path, { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', 'Idempotency-Key': key() }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.text().then(function (t) { var j = {}; try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { message: t.slice(0, 200) }; } if (!r.ok) { var e = new Error(j.message || r.statusText); e.error = j.error; e.errors = j.errors; throw e; } return j; }); });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function when(iso) { if (!iso) return '—'; var d = new Date(iso); return d.toLocaleDateString('cs-CZ') + ' ' + d.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' }); }
  function alertBox(text, ok) { var a = document.getElementById('alert'); a.hidden = false; a.className = 'msg' + (ok ? ' ok' : ''); a.textContent = text; }
  function render(jobs) {
    var tb = document.getElementById('jobs');
    if (!jobs.length) { tb.innerHTML = '<tr><td colspan="6" class="muted">zatím žádný job</td></tr>'; return; }
    tb.innerHTML = jobs.map(function (j) {
      var done = j.succeeded + j.failed + j.cancelled + j.refused, pct = j.total ? Math.round(done / j.total * 100) : 0, bad = j.total ? Math.round((j.failed + j.refused) / j.total * 100) : 0;
      var filter = Object.keys(j.filter || {}).map(function (k) { return k + '=' + (Array.isArray(j.filter[k]) ? j.filter[k].length + ' služeb' : j.filter[k]); }).join(' · ');
      var items = (j.items || []).map(function (i) { return '<tr><td>' + esc(i.label) + '<br><span class="muted mono">' + esc(i.service_id) + '</span></td><td><span class="tag ' + (i.state === 'SUCCEEDED' ? 'ok' : (i.state === 'FAILED' || i.state === 'REFUSED' ? 'bad' : 'warn')) + '">' + esc(i.state) + '</span></td><td class="muted">' + esc(i.message || i.error || '') + '</td></tr>'; }).join('');
      return '<tr><td><span class="mono">' + esc(j.id) + '</span><br><span class="muted">' + esc(when(j.created_at)) + (j.reason ? ' · ' + esc(j.reason) : '') + '</span></td><td class="mono">' + esc(j.action) + '<br><span class="muted">' + esc(JSON.stringify(j.params || {})) + '</span></td><td>' + esc(filter) + '</td>'
        + '<td>' + esc(j.succeeded) + ' ok · ' + esc(j.failed) + ' selhalo · ' + esc(j.refused) + ' odmítnuto · ' + esc(j.running) + ' běží / ' + esc(j.total) + '<div class="bar"><i style="width:' + (pct - bad) + '%"></i><b style="left:' + (pct - bad) + '%;width:' + bad + '%"></b></div></td>'
        + '<td><span class="tag ' + (j.state === 'finished' ? (j.failed || j.refused ? 'warn' : 'ok') : 'warn') + '">' + esc(j.state) + '</span></td>'
        + '<td><details><summary class="btn">Detail</summary><table><thead><tr><th>Služba</th><th>Stav</th><th>Poznámka</th></tr></thead><tbody>' + items + '</tbody></table></details></td></tr>';
    }).join('');
  }
  function load() { api('GET', '/staff/bulk-jobs?per_page=30').then(function (r) { render(r.data || []); }).catch(function (e) { alertBox('Joby se nepodařilo načíst: ' + e.message); }); }
  document.getElementById('form').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = new FormData(e.target), body = { action: f.get('action'), reason: f.get('reason') || null, filter: {}, params: {} };
    ['provider_instance_id', 'node_id', 'organization_id', 'product_key', 'family'].forEach(function (k) { if (f.get(k)) body.filter[k] = f.get(k); });
    if (f.get('service_ids')) body.filter.service_ids = String(f.get('service_ids')).split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    try { body.params = f.get('params') ? JSON.parse(f.get('params')) : {}; } catch (err) { alertBox('Parametry nejsou platný JSON.'); return; }
    if (!window.confirm('Spustit ' + body.action + ' na všech službách, které filtr vybere?')) return;
    api('POST', '/staff/bulk-jobs', body).then(function (r) { var j = r.data || r; alertBox('Job ' + j.id + ' spuštěn: ' + j.total + ' služeb, ' + j.refused + ' odmítnuto.', true); load(); }).catch(function (err) { alertBox('Spuštění neprošlo: ' + err.message + (err.errors ? ' ' + JSON.stringify(err.errors) : '')); });
  });
  load();
  setInterval(load, 20000);
})();
</script>
</body>
</html>
