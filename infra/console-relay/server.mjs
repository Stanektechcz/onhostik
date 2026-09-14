// ONhost console relay — websocket proxy between the customer's browser and the provider console session.
// Flow (docs/runbooks/console-relay.md): the browser opens ws://relay/ws/<con_token>; the relay resolves the
// single-use descriptor at GET {ONHOST_API}/console/ws/<token> with the shared X-Relay-Key, then pipes frames:
//   pve_vnc  → wss://<pve>/api2/json/nodes/{node}/qemu/{vmid}/vncwebsocket?port=…&vncticket=…  (noVNC binary)
//   wings_ws → wss://<wings>/api/servers/{uuid}/ws  (Pterodactyl console: {event:"auth", args:[token]} first)
// No state, no logging of tickets; one upstream per client socket; closes when either side closes.
import http from 'node:http';
import { WebSocketServer, WebSocket } from 'ws';

const PORT = parseInt(process.env.PORT || '8090', 10);
const API = (process.env.ONHOST_API || 'http://localhost:8000').replace(/\/$/, '');
const KEY = process.env.ONHOST_CONSOLE_RELAY_KEY || '';
const ALLOW_INSECURE_UPSTREAM = process.env.RELAY_ALLOW_INSECURE_UPSTREAM === '1';

if (!KEY) { console.error('ONHOST_CONSOLE_RELAY_KEY is required'); process.exit(1); }

const server = http.createServer((req, res) => {
  if (req.url === '/healthz') { res.writeHead(200, { 'content-type': 'application/json' }); res.end('{"ok":true}'); return; }
  res.writeHead(404); res.end();
});
const wss = new WebSocketServer({ server, path: undefined, maxPayload: 4 * 1024 * 1024 });

async function resolve(token) {
  const r = await fetch(`${API}/console/ws/${encodeURIComponent(token)}`, { headers: { 'X-Relay-Key': KEY, Accept: 'application/json' } });
  if (!r.ok) throw new Error(`descriptor ${r.status}`);
  return (await r.json()).data;
}

wss.on('connection', async (client, req) => {
  const m = /^\/ws\/(con_[0-9a-z]{26})$/.exec(req.url || '');
  if (!m) { client.close(1008, 'bad token'); return; }
  let d;
  try { d = await resolve(m[1]); } catch (e) { client.close(1008, 'token rejected'); return; }

  let upstream;
  const opts = { rejectUnauthorized: !ALLOW_INSECURE_UPSTREAM, perMessageDeflate: false };
  if (d.kind === 'pve_vnc') {
    const url = `${d.upstream.replace(/^http/, 'ws')}?port=${encodeURIComponent(d.port)}&vncticket=${encodeURIComponent(d.vncticket)}`;
    upstream = new WebSocket(url, ['binary'], { ...opts, headers: { Cookie: `PVEAuthCookie=${encodeURIComponent(d.vncticket)}` } });
  } else if (d.kind === 'wings_ws') {
    upstream = new WebSocket(d.socket, opts);
    upstream.once('open', () => upstream.send(JSON.stringify({ event: 'auth', args: [d.token] })));
  } else {
    client.close(1011, 'unsupported console kind'); return;
  }

  const closeBoth = (code, reason) => { try { client.close(code, reason); } catch {} try { upstream.close(); } catch {} };
  upstream.on('open', () => { console.log(JSON.stringify({ at: new Date().toISOString(), msg: 'relay.open', kind: d.kind, service: d.service_id })); });
  upstream.on('message', (data, isBinary) => { if (client.readyState === WebSocket.OPEN) client.send(data, { binary: isBinary }); });
  client.on('message', (data, isBinary) => { if (upstream.readyState === WebSocket.OPEN) upstream.send(data, { binary: isBinary }); });
  upstream.on('close', () => closeBoth(1000, 'upstream closed'));
  upstream.on('error', (e) => { console.log(JSON.stringify({ at: new Date().toISOString(), msg: 'relay.upstream_error', error: e.message })); closeBoth(1011, 'upstream error'); });
  client.on('close', () => closeBoth(1000, 'client closed'));
  client.on('error', () => closeBoth(1011, 'client error'));
  // provider sessions are short-lived; hard cap a relay session at 2 hours
  setTimeout(() => closeBoth(1000, 'session cap'), 2 * 60 * 60 * 1000).unref();
});

server.listen(PORT, () => console.log(JSON.stringify({ at: new Date().toISOString(), msg: 'relay.listening', port: PORT, api: API })));
