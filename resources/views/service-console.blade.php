<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="{{ $csrf }}">
<title>Konzole · {{ $service->label ?: ($service->hostname ?: $service->name) }}</title>
<style nonce="{{ $nonce }}">
  html, body { height: 100%; }
  body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #1c1b1a; color: #e8e4de; display: flex; flex-direction: column; }
  header { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; padding: 10px 14px; background: #262422; border-bottom: 1px solid #3a3734; }
  h1 { font-size: 15px; margin: 0 12px 0 0; font-weight: 700; }
  button { padding: 6px 12px; border: 1px solid #5a5550; border-radius: 7px; background: #2f2c29; color: #e8e4de; font-weight: 600; font-size: 13px; cursor: pointer; }
  button.primary { background: #ec3013; border-color: #ec3013; color: #fff; }
  button:disabled { opacity: .45; cursor: default; }
  .state { font-size: 12px; padding: 3px 8px; border-radius: 6px; background: #3a3734; }
  .state.on { background: #1f5130; color: #d9f2e1; }
  .state.err { background: #5c1d18; color: #ffd9d4; }
  #screen { flex: 1; min-height: 0; overflow: hidden; background: #000; }
  #msg { padding: 6px 14px; font-size: 13px; min-height: 18px; color: #c9c2ba; }
</style>
</head>
<body>
<header>
  <h1>Konzole · {{ $service->label ?: ($service->hostname ?: $service->name) }}</h1>
  <button class="primary" id="connect" type="button">Připojit</button>
  <button id="disconnect" type="button" disabled>Odpojit</button>
  <button id="cad" type="button" disabled>Ctrl+Alt+Del</button>
  <button id="paste" type="button" disabled>Schránka serveru</button>
  <button id="full" type="button">Celá obrazovka</button>
  <span class="state" id="state">odpojeno</span>
</header>
<div id="msg">Konzole běží přes náš relay s jednorázovým tokenem; přístup je v auditu služby. Token platí krátce — po odpojení se připojte znovu.</div>
<div id="screen"></div>
<script type="application/json" id="console-config">@json($config)</script>
<script type="module" nonce="{{ $nonce }}">
import RFB from @json($novnc);

const cfg = JSON.parse(document.getElementById('console-config').textContent);
const csrf = document.querySelector('meta[name=csrf-token]').content;
const $ = (id) => document.getElementById(id);
let rfb = null;

function say(text, kind) { $('msg').textContent = text; $('state').className = 'state' + (kind ? ' ' + kind : ''); }
function setState(text, kind) { $('state').textContent = text; $('state').className = 'state' + (kind ? ' ' + kind : ''); }
function connected(on) { $('connect').disabled = on; ['disconnect', 'cad', 'paste'].forEach((id) => { $(id).disabled = !on; }); }

async function consoleToken() {
  const key = 'console-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
  const r = await fetch('/v1/services/' + encodeURIComponent(cfg.service) + '/console-token', {
    method: 'POST', credentials: 'same-origin', body: '{}',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'X-Organization': cfg.organization, 'Idempotency-Key': key },
  });
  const j = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(j.message || j.error || ('HTTP ' + r.status));
  return j.data !== undefined ? j.data : j;
}

async function connect() {
  if (!cfg.relay) { say('Živá konzole není na této platformě zapnutá (relay není nastavený).', 'err'); return; }
  $('connect').disabled = true;
  setState('připojuji…');
  try {
    const d = await consoleToken();
    if (d.kind !== 'novnc' || !d.socket) throw new Error('Tato služba grafickou konzoli nenabízí.');
    rfb = new RFB($('screen'), d.socket, { credentials: { password: d.password || '' } });
    rfb.scaleViewport = true;
    rfb.resizeSession = false;
    rfb.addEventListener('connect', () => { connected(true); setState('připojeno', 'on'); rfb.focus(); });
    rfb.addEventListener('disconnect', (e) => { connected(false); rfb = null; setState(e.detail && e.detail.clean ? 'odpojeno' : 'spojení přerušeno', e.detail && e.detail.clean ? '' : 'err'); });
    rfb.addEventListener('securityfailure', (e) => { say('Konzole odmítla přihlášení: ' + ((e.detail && e.detail.reason) || 'neznámý důvod') + '. Připojte se znovu.', 'err'); });
    rfb.addEventListener('credentialsrequired', () => { say('Token konzole vypršel; připojte se znovu.', 'err'); rfb.disconnect(); });
  } catch (e) {
    connected(false);
    setState('nepřipojeno', 'err');
    say(e.message, 'err');
  }
}

$('connect').addEventListener('click', connect);
$('disconnect').addEventListener('click', () => { if (rfb) rfb.disconnect(); });
$('cad').addEventListener('click', () => { if (rfb) rfb.sendCtrlAltDel(); });
$('paste').addEventListener('click', () => {
  if (!rfb) return;
  const text = window.prompt('Text pro schránku serveru (systém ho vloží tam, kde schránku podporuje):', '');
  if (text) rfb.clipboardPasteFrom(text);
});
$('full').addEventListener('click', () => { const el = document.documentElement; if (document.fullscreenElement) document.exitFullscreen(); else if (el.requestFullscreen) el.requestFullscreen(); });
connect();
</script>
</body>
</html>
