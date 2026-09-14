<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="{{ $csrf }}">
<title>Konzole · {{ $service->label ?: $service->name }}</title>
<style>
  body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f2ef; color: #201e1d; }
  main { max-width: 1100px; margin: 3vh auto; background: #fff; border: 1px solid #e3ded8; border-radius: 12px; padding: 22px 26px; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .muted { color: #6f6862; font-size: 13px; }
  .bar { display: flex; gap: 8px; flex-wrap: wrap; margin: 14px 0; align-items: center; }
  button { padding: 8px 14px; border: 2px solid #201e1d; border-radius: 8px; background: #fff; color: #201e1d; font-weight: 700; font-size: 13px; cursor: pointer; }
  button.primary { background: #ec3013; border-color: #ec3013; color: #fff; }
  button:disabled { opacity: .5; cursor: default; }
  pre { background: #1c1b1a; color: #e8e4de; border-radius: 10px; padding: 14px; height: 52vh; overflow: auto; font: 13px/1.45 ui-monospace, Menlo, Consolas, monospace; white-space: pre-wrap; margin: 0; }
  form { display: flex; gap: 8px; margin-top: 10px; }
  input { flex: 1; padding: 10px; border: 1px solid #c9c2ba; border-radius: 8px; font: 14px ui-monospace, Menlo, Consolas, monospace; }
  input[type=file] { flex: none; font: 13px system-ui, sans-serif; }
  .state { display: inline-block; padding: 2px 8px; border-radius: 6px; background: #eee8e0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  .live { display: inline-block; padding: 2px 8px; border-radius: 6px; background: #eee8e0; font-size: 12px; font-weight: 700; }
  .live.on { background: #d9f2e1; color: #0f5d2c; }
  .msg { font-size: 13px; margin-top: 8px; min-height: 18px; }
  .msg.err { color: #b3261e; font-weight: 600; }
  .tabs { display: flex; gap: 6px; margin: 10px 0 6px; }
  .tabs button { padding: 5px 10px; border-width: 1px; font-weight: 600; }
  .tabs button.active { background: #201e1d; color: #fff; }
</style>
</head>
<body>
<main>
  <h1>Konzole · {{ $service->label ?: $service->name }} <span class="state" id="state">{{ strtolower($service->state) }}</span> <span class="live" id="livestate">log každých 5 s</span></h1>
  <div class="muted">{{ $organization?->name ?? '—' }} · {{ $service->product_key }} · {{ $service->region_code }}@if($node) · uzel {{ $node->name }}@endif · {{ $service->hostname ?? '' }}</div>
  <div class="bar">
    @if ($canPower)
      <button class="primary" data-power="start">Start</button>
      <button data-power="reboot">Restart</button>
      <button data-power="stop">Stop</button>
      <button data-power="kill">Kill</button>
    @endif
    <button id="refresh">Obnovit log</button>
    @if ($relayUrl !== '')
      <button id="connect">Připojit živou konzoli</button>
    @endif
    <button id="live">Token živé konzole</button>
    <label class="muted"><input type="checkbox" id="auto" checked style="width:auto;flex:none"> obnovovat každých 5 s</label>
  </div>
  <div class="tabs"><button class="active" data-tab="log">Log serveru</button><button data-tab="term">Živá konzole</button></div>
  <pre id="log">načítám…</pre>
  <pre id="term" hidden>živá konzole není připojená — tlačítko „Připojit živou konzoli“ otevře websocket přes relay a přehraje poslední řádky</pre>
  @if ($canCommand)
    <form id="cmd"><input id="line" placeholder="příkaz konzole, např. list · say Ahoj · op Hrac" autocomplete="off"><button class="primary" type="submit">Odeslat</button></form>
  @endif
  @if ($canFiles)
    <form id="upload"><input id="path" placeholder="cesta na serveru, např. plugins/config.yml" autocomplete="off"><input type="file" id="file"><button type="submit">Nahrát soubor (text, do 512 kB)</button></form>
  @endif
  <div class="msg" id="msg"></div>
  <p class="muted">Vše jde přes zákaznické API služby s hlavičkou organizace; každá akce je v auditu. Živá konzole běží přes websocket relay (token je jednorázový, spojení se po vypršení obnoví samo); příkaz odeslaný při připojené konzoli jde přímo do ní, jinak přes API.</p>
</main>
<script>
(function () {
  var sid = @json($service->id), org = @json($service->organization_id), relay = @json($relayUrl), csrf = document.querySelector('meta[name=csrf-token]').content;
  var logEl = document.getElementById('log'), termEl = document.getElementById('term'), msg = document.getElementById('msg'), stateEl = document.getElementById('state'), liveEl = document.getElementById('livestate');
  var socket = null, reconnects = 0, termLines = [];
  function key() { return 'staff-console-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8); }
  function api(method, path, body) {
    return fetch('/v1' + path, { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'X-Organization': org, 'Idempotency-Key': key() }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || j.error || ('HTTP ' + r.status)); return j.data !== undefined ? j.data : j; }); });
  }
  function say(text, err) { msg.textContent = text; msg.className = 'msg' + (err ? ' err' : ''); }
  function stickBottom(el, fn) { var stick = el.scrollTop + el.clientHeight >= el.scrollHeight - 30; fn(); if (stick) el.scrollTop = el.scrollHeight; }
  function loadLog() {
    api('GET', '/services/' + encodeURIComponent(sid) + '/logs?lines=300').then(function (d) {
      var lines = d.lines || [];
      stickBottom(logEl, function () { logEl.textContent = Array.isArray(lines) ? lines.join('\n') : String(lines); });
    }).catch(function (e) { logEl.textContent = 'Log nelze načíst: ' + e.message; });
    api('GET', '/services/' + encodeURIComponent(sid)).then(function (d) { if (d && d.state) stateEl.textContent = String(d.ui || d.state).toLowerCase(); }).catch(function () {});
  }
  document.querySelectorAll('.tabs button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.tabs button').forEach(function (x) { x.classList.toggle('active', x === b); });
      var tab = b.getAttribute('data-tab');
      logEl.hidden = tab !== 'log'; termEl.hidden = tab !== 'term';
    });
  });
  document.querySelectorAll('button[data-power]').forEach(function (b) {
    b.addEventListener('click', function () {
      var pa = b.getAttribute('data-power');
      if (pa === 'kill' && !window.confirm('Kill zabije proces bez uložení. Pokračovat?')) return;
      b.disabled = true;
      api('POST', '/services/' + encodeURIComponent(sid) + '/actions', { action: 'power', params: { power_action: pa } }).then(function (d) { say('Napájení: ' + pa + ' · operace ' + (d.operation_id || '')); setTimeout(loadLog, 2500); }).catch(function (e) { say(e.message, true); }).then(function () { b.disabled = false; });
    });
  });

  /* live console (audit §5q-3): the relay pipes the provider's websocket; Pterodactyl frames are JSON {event, args} */
  function termWrite(line) {
    termLines.push(String(line).replace(/\[[0-9;]*m/g, ''));
    if (termLines.length > 2000) termLines = termLines.slice(-1500);
    stickBottom(termEl, function () { termEl.textContent = termLines.join('\n'); });
  }
  function setLive(on, text) { liveEl.textContent = text; liveEl.className = 'live' + (on ? ' on' : ''); }
  function connect() {
    if (!relay) { say('Relay živé konzole není nastavený (ONHOST_CONSOLE_RELAY_URL).', true); return; }
    if (socket && socket.readyState <= 1) { socket.close(1000, 'reconnect'); }
    api('POST', '/services/' + encodeURIComponent(sid) + '/console-token', {}).then(function (d) {
      if (d.kind !== 'wings_ws') { say('Tato konzole je ' + (d.kind || 'grafická') + ' (VNC) — otevřete ji v zákaznickém panelu; token: ' + (d.token || '')); return; }
      var ws = new WebSocket(relay.replace(/^http/, 'ws') + '/ws/' + encodeURIComponent(d.token));
      socket = ws;
      setLive(false, 'připojuji…');
      ws.onopen = function () {
        reconnects = 0;
        setLive(true, 'živá konzole připojená');
        termWrite('— připojeno přes relay, přehrávám poslední řádky —');
        ws.send(JSON.stringify({ event: 'send logs', args: [null] }));
        ws.send(JSON.stringify({ event: 'send stats', args: [null] }));
        document.querySelector('.tabs button[data-tab=term]').click();
      };
      ws.onmessage = function (ev) {
        var f; try { f = JSON.parse(ev.data); } catch (e) { termWrite(ev.data); return; }
        var args = f.args || [];
        if (f.event === 'console output' || f.event === 'install output' || f.event === 'daemon message') { args.forEach(termWrite); }
        else if (f.event === 'status') { stateEl.textContent = String(args[0] || '').toLowerCase(); }
        else if (f.event === 'stats') { try { var s = JSON.parse(args[0]); setLive(true, 'živá konzole · CPU ' + Math.round(s.cpu_absolute || 0) + ' % · RAM ' + Math.round((s.memory_bytes || 0) / 1048576) + ' MB'); } catch (e) {} }
        else if (f.event === 'token expiring' || f.event === 'token expired') { termWrite('— token vypršel, obnovuji spojení —'); ws.close(1000, 'token'); }
        else if (f.event === 'jwt error' || f.event === 'daemon error') { termWrite('— chyba konzole: ' + args.join(' ') + ' —'); }
      };
      ws.onclose = function (ev) {
        socket = null;
        setLive(false, 'živá konzole odpojená');
        if (ev.reason === 'token' && reconnects < 20) { reconnects++; setTimeout(connect, 500); }
        else if (ev.reason !== 'reconnect') { termWrite('— spojení ukončeno (' + (ev.reason || ev.code) + ') —'); }
      };
      ws.onerror = function () { setLive(false, 'relay nedostupný'); };
    }).catch(function (e) { say(e.message, true); });
  }
  var connectBtn = document.getElementById('connect');
  if (connectBtn) connectBtn.addEventListener('click', connect);

  var form = document.getElementById('cmd');
  if (form) form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var line = document.getElementById('line').value.trim(); if (!line) return;
    if (socket && socket.readyState === 1) { socket.send(JSON.stringify({ event: 'send command', args: [line] })); termWrite('> ' + line); document.getElementById('line').value = ''; return; }
    api('POST', '/services/' + encodeURIComponent(sid) + '/actions', { action: 'command.send', params: { command: line } }).then(function (d) { say('Odesláno: ' + line + ' · operace ' + (d.operation_id || '')); document.getElementById('line').value = ''; setTimeout(loadLog, 2500); }).catch(function (e) { say(e.message, true); });
  });

  /* file transfer (audit §5q-3): text files up to 512 kB through the audited `gfile.save` action; binaries stay with SFTP */
  var upload = document.getElementById('upload');
  if (upload) upload.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var path = document.getElementById('path').value.trim(), file = document.getElementById('file').files[0];
    if (!path || !file) { say('Zadejte cestu na serveru a vyberte soubor.', true); return; }
    if (file.size > 512 * 1024) { say('Soubor je větší než 512 kB — použijte SFTP nebo panel.', true); return; }
    var reader = new FileReader();
    reader.onload = function () {
      api('POST', '/services/' + encodeURIComponent(sid) + '/actions', { action: 'gfile.save', params: { path: path, content: String(reader.result) } }).then(function (d) { say('Soubor ' + path + ' odeslán · operace ' + (d.operation_id || '')); }).catch(function (e) { say(e.message, true); });
    };
    reader.readAsText(file);
  });

  document.getElementById('refresh').addEventListener('click', loadLog);
  document.getElementById('live').addEventListener('click', function () {
    api('POST', '/services/' + encodeURIComponent(sid) + '/console-token', {}).then(function (d) { say('Živá konzole (' + (d.kind || '') + '): token ' + (d.token || '') + (d.url ? ' · ' + d.url : '') + ' · platí do ' + (d.expires_at || '')); }).catch(function (e) { say(e.message, true); });
  });
  loadLog();
  setInterval(function () { if (document.getElementById('auto').checked) loadLog(); }, 5000);
})();
</script>
</body>
</html>
