<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Nastavení systému · Tarify a verze — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 14px; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); flex-wrap: wrap; }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  .topbar .spacer { flex: 1; }
  main { max-width: 1320px; margin: 0 auto; padding: 24px; display: grid; gap: 24px; }
  h1 { font-size: 26px; margin: 0 0 6px; }
  h2 { font-size: 16px; margin: 0 0 12px; letter-spacing: .04em; text-transform: uppercase; }
  .lead { color: var(--color-neutral-700, #605d5d); max-width: 900px; }
  .panel { border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); padding: 18px; overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); padding: 6px 8px; border-bottom: 1px solid var(--color-divider, #9a9797); }
  td { padding: 8px; border-bottom: 1px solid color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); vertical-align: top; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
  .tag { display: inline-block; padding: 2px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; border: 1px solid var(--color-text, #201e1d); }
  .tag.ok { background: #b8ff2e; border-color: transparent; color: #1a1918; }
  .tag.off { background: color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); border-color: transparent; }
  .btn { font: inherit; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; padding: 7px 12px; border: 2px solid var(--color-text, #201e1d); background: transparent; color: inherit; cursor: pointer; }
  .btn.primary { background: var(--color-accent, #ec3013); border-color: var(--color-accent, #ec3013); color: #fff; }
  .btn:disabled { opacity: .5; cursor: default; }
  .actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
  .msg { padding: 10px 14px; border-left: 4px solid var(--color-accent, #ec3013); background: color-mix(in srgb, var(--color-accent, #ec3013) 10%, transparent); font-size: 13px; }
  .msg.ok { border-color: #5a9b00; background: color-mix(in srgb, #b8ff2e 30%, transparent); }
  .muted { color: var(--color-neutral-700, #605d5d); }
  .hint { font-size: 12px; color: var(--color-neutral-700, #605d5d); margin-top: 4px; }
  label { display: block; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); margin-bottom: 5px; }
  input[type=number], input[type=text], select { font: inherit; padding: 7px 9px; border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); color: inherit; max-width: 100%; }
  .pick { display: flex; gap: 14px; flex-wrap: wrap; align-items: end; }
  .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px; }
  dialog { border: 2px solid var(--color-text, #201e1d); padding: 20px; max-width: 420px; font: inherit; }
  dialog::backdrop { background: rgba(0,0,0,.45); }
  .field { display: flex; flex-direction: column; gap: 5px; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Nastavení systému</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/integrace">Integrace</a>
  <a href="/sprava/nastaveni/provoz">Provoz</a>
  <a href="/sprava/nastaveni/zivotni-cyklus">Životní cyklus služeb</a>
  <a href="/sprava/nastaveni/tarify"><strong>Tarify a verze</strong></a>
  <a href="/sprava/nastaveni/schvalovani">Schvalování</a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Tarify a jejich verze</h1>
    <p class="lead">Tarif se nikdy neupravuje. Změna limitů nebo ceny je <strong>nová verze</strong>: dostanou ji nové objednávky, kdo už koupil, zůstává na své verzi i ceně. Co ve formuláři nezměníte, přenese se beze změny. Chybnou verzi stáhnete z prodeje tím, že vrátíte do prodeje tu předchozí. Vydání vyžaduje druhé ověření a důvod, který zůstane u verze.</p>
    <div id="alert" hidden></div>
  </section>

  <section class="panel">
    <div class="pick">
      <div><label for="product">Produkt</label><select id="product"></select></div>
      <div><label for="plan">Tarif</label><select id="plan"></select></div>
    </div>
  </section>

  <section class="panel">
    <h2>Verze</h2>
    <table><thead><tr><th>Verze</th><th>Ceny (bez DPH)</th><th>Limity</th><th>Kdo na ní je</th><th>Platí od</th><th></th></tr></thead><tbody id="versions"><tr><td colspan="6" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel">
    <h2>Nová verze</h2>
    <form id="publish">
      <div class="grid2">
        <div>
          <table><thead><tr><th>Limit</th><th>Nyní</th><th>Nově</th></tr></thead><tbody id="ent"></tbody></table>
        </div>
        <div>
          <table><thead><tr><th>Cena</th><th>Nyní</th><th>Nově</th></tr></thead><tbody id="prices"></tbody></table>
          <div class="hint">Obnova se řídí novou cenou. Změnu o více než 50 % je třeba výslovně potvrdit.</div>
        </div>
      </div>
      <div class="pick" style="margin-top:16px">
        <div style="flex:1;min-width:280px"><label for="reason">Důvod změny</label><input type="text" id="reason" minlength="5" maxlength="250" required style="width:100%"></div>
        <div><label><input type="checkbox" id="large"> potvrzuji změnu ceny o více než 50 %</label></div>
        <div><button class="btn primary" type="submit">Vydat novou verzi</button></div>
      </div>
    </form>
  </section>
</main>

<dialog id="stepup">
  <form method="dialog" id="stepup-form">
    <h2>Druhé ověření</h2>
    <p class="muted">Verze tarifu určuje, co dostane a zaplatí každý nový zákazník. Zadejte kód z autentikátoru nebo záložní kód.</p>
    <div class="field"><label for="s-method">Metoda</label><select id="s-method"><option value="totp">TOTP (autentikátor)</option><option value="recovery">záložní kód</option><option value="password">heslo (jen dokud není zapnuté TOTP a mimo produkci)</option></select></div>
    <div class="field" style="margin-top:10px"><label for="s-code">Kód</label><input type="text" id="s-code" inputmode="numeric" autocomplete="one-time-code" required></div>
    <div class="actions" style="margin-top:14px"><button class="btn primary" value="ok">Ověřit a pokračovat</button><button class="btn" value="cancel" type="button" id="s-cancel">Zrušit</button></div>
    <p class="hint" id="s-error" style="color:#ae1800"></p>
  </form>
</dialog>

<script>
(function () {
  var API = '/v1';
  var csrf = document.querySelector('meta[name=csrf-token]').getAttribute('content');
  var state = { products: [], history: null, retry: null, cancel: null };
  var $ = function (id) { return document.getElementById(id); };
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  function key() { return 'plans-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function day(iso) { return iso ? new Date(iso).toLocaleDateString('cs-CZ') : '—'; }
  function say(text, ok) { var a = $('alert'); a.hidden = false; a.className = 'msg' + (ok ? ' ok' : ''); a.textContent = text; window.scrollTo({ top: 0, behavior: 'smooth' }); }
  function api(method, path, body) {
    return fetch(API + path, {
      method: method, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', 'Idempotency-Key': key() },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.text().then(function (t) {
        var j = {}; try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { message: t.slice(0, 200) }; }
        if (!r.ok) { var e = new Error(j.message || r.statusText); e.error = j.error; e.status = r.status; e.approvalId = j.approval_id; throw e; }
        return j;
      });
    });
  }
  /* Price and plan changes take a second person (owner decision 13): the refusal carries the request it opened. The form stays as it is: once
     somebody else approves, the same change is sent again unchanged and goes through. */
  function pendingApproval(e) {
    var p = new Error('Změna čeká na schválení druhou osobou (žádost ' + (e.approvalId || '?') + '). Po schválení ji odešlete znovu beze změny — /sprava/nastaveni/schvalovani');
    p.error = 'approval_required'; p.approvalId = e.approvalId; p.status = e.status; p.payload = e.payload;
    return p;
  }
  /* HIGH-risk commands answer step_up_required until the session holds a fresh grant: ask, verify, retry. */
  function guarded(action) {
    return action().catch(function (e) {
      if (e.error === 'approval_required') throw pendingApproval(e);
      if (e.error !== 'step_up_required') throw e;
      return new Promise(function (resolve, reject) {
        state.retry = function () { return action().then(resolve, reject); };
        state.cancel = function () { reject(new Error('Druhé ověření zrušeno — nic se nezměnilo.')); };
        $('s-error').textContent = ''; $('s-code').value = '';
        $('stepup').showModal();
      });
    });
  }
  $('stepup-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    api('POST', '/auth/step-up', { method: $('s-method').value, code: $('s-code').value.trim() }).then(function () {
      $('stepup').close(); var r = state.retry; state.retry = null; state.cancel = null; if (r) r();
    }).catch(function (e) { $('s-error').textContent = e.message || 'Ověření se nezdařilo.'; });
  });
  $('s-cancel').addEventListener('click', function () { $('stepup').close(); state.retry = null; var c = state.cancel; state.cancel = null; if (c) c(); });

  function base() { return '/staff/pricing/plans/' + encodeURIComponent($('product').value) + '/' + encodeURIComponent($('plan').value) + '/versions'; }
  function money(minor, currency) { return (Number(minor || 0) / 100).toLocaleString('cs-CZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ' + currency; }

  function render(h) {
    state.history = h;
    var current = (h.versions || []).filter(function (v) { return v.on_sale; })[0] || {};
    $('versions').innerHTML = (h.versions || []).map(function (v) {
      var prices = (v.prices || []).map(function (p) { return esc(p.currency + '/' + p.period) + ' <strong>' + esc(money(p.amount_minor, p.currency)) + '</strong>'; }).join('<br>');
      var ent = Object.keys(v.entitlements || {}).map(function (k) { return '<span class="mono">' + esc(k) + '</span> ' + esc(String(v.entitlements[k])); }).join(' · ');
      return '<tr><td><strong>v' + esc(v.version) + '</strong><br>' + (v.on_sale ? '<span class="tag ok">v prodeji</span>' : '<span class="tag off">mimo prodej</span>') + '</td><td>' + prices + '</td><td>' + ent + '</td>'
        + '<td>' + esc(v.services) + ' služeb<br><span class="muted">' + esc(v.subscriptions) + ' předplatných</span></td><td>' + esc(day(v.effective_from)) + (v.effective_to ? '<br><span class="muted">do ' + esc(day(v.effective_to)) + '</span>' : '') + '</td>'
        + '<td>' + (v.on_sale ? '' : '<button class="btn" type="button" data-activate="' + esc(v.version) + '">Vrátit do prodeje</button>') + '</td></tr>';
    }).join('') || '<tr><td colspan="6" class="muted">tarif nemá žádnou verzi</td></tr>';

    $('ent').innerHTML = Object.keys(current.entitlements || {}).map(function (k) {
      var was = current.entitlements[k];
      var input = typeof was === 'boolean' ? '<input type="checkbox" data-ent="' + esc(k) + '" data-kind="bool"' + (was ? ' checked' : '') + '>'
        : typeof was === 'number' ? '<input type="number" min="0" step="any" data-ent="' + esc(k) + '" data-kind="num" placeholder="' + esc(was) + '">'
        : typeof was === 'string' ? '<input type="text" maxlength="120" data-ent="' + esc(k) + '" data-kind="str" placeholder="' + esc(was) + '">' : '<span class="muted">nelze měnit zde</span>';
      return '<tr><td class="mono">' + esc(k) + '</td><td>' + esc(typeof was === 'object' ? JSON.stringify(was) : String(was)) + '</td><td>' + input + '</td></tr>';
    }).join('');
    $('prices').innerHTML = (current.prices || []).filter(function (p) { return p.state === 'active'; }).map(function (p) {
      return '<tr><td class="mono">' + esc(p.currency + '/' + p.period) + '</td><td>' + esc(money(p.amount_minor, p.currency)) + '</td><td><input type="number" min="0" step="0.01" data-price="' + esc(p.currency + '/' + p.period) + '" placeholder="' + esc(Number(p.amount_minor) / 100) + '"></td></tr>';
    }).join('');
  }

  function load() {
    if (!$('product').value || !$('plan').value) return;
    api('GET', base()).then(function (r) { render(r.data || r); }).catch(function (e) { say('Verze se nepodařilo načíst: ' + e.message); });
  }
  function fillPlans() {
    var product = state.products.filter(function (p) { return p.key === $('product').value; })[0] || { plans: [] };
    $('plan').innerHTML = product.plans.map(function (p) { return '<option value="' + esc(p.key) + '">' + esc(p.name) + ' (' + esc(p.key) + ')</option>'; }).join('');
    load();
  }
  $('product').addEventListener('change', fillPlans);
  $('plan').addEventListener('change', load);

  $('publish').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var current = ((state.history || {}).versions || []).filter(function (v) { return v.on_sale; })[0] || { entitlements: {} };
    var body = { reason: $('reason').value.trim(), entitlements: {}, prices: [], confirm_large_change: $('large').checked };
    document.querySelectorAll('[data-ent]').forEach(function (el) {
      var k = el.dataset.ent, was = current.entitlements[k];
      if (el.dataset.kind === 'bool') { if (el.checked !== was) body.entitlements[k] = el.checked; }
      else if (el.value !== '') { body.entitlements[k] = el.dataset.kind === 'num' ? Number(el.value) : el.value; }
    });
    document.querySelectorAll('[data-price]').forEach(function (el) {
      if (el.value === '') return;
      var parts = el.dataset.price.split('/');
      body.prices.push({ currency: parts[0], period: parts[1], amount: el.value });
    });
    if (!Object.keys(body.entitlements).length) delete body.entitlements;
    if (!body.prices.length) delete body.prices;
    guarded(function () { return api('POST', base(), body); })
      .then(function (r) { say('Verze ' + r.version + ' je v prodeji. Stávající zákazníci zůstávají na své verzi.', true); $('publish').reset(); render(r.plan); })
      .catch(function (e) { say('Vydání neprošlo: ' + e.message); });
  });

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('button[data-activate]') : null;
    if (!b) return;
    var reason = window.prompt('Verze ' + b.dataset.activate + ' se vrátí do prodeje pro nové objednávky. Důvod (min. 5 znaků):', '');
    if (!reason || reason.trim().length < 5) return;
    guarded(function () { return api('POST', base() + '/' + encodeURIComponent(b.dataset.activate) + '/activate', { reason: reason.trim() }); })
      .then(function (r) { say('V prodeji je teď verze ' + r.plan.current_version + '.', true); render(r.plan); })
      .catch(function (e) { say('Změna neprošla: ' + e.message); });
  });

  api('GET', '/staff/pricing').then(function (r) {
    var d = r.data || r;
    state.products = (d.products || []).filter(function (p) { return (p.plans || []).length; });
    $('product').innerHTML = state.products.map(function (p) { return '<option value="' + esc(p.key) + '">' + esc(p.name) + ' (' + esc(p.key) + ')</option>'; }).join('');
    fillPlans();
  }).catch(function (e) { say('Katalog se nepodařilo načíst: ' + e.message); });
})();
</script>
</body>
</html>
