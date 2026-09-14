/*
 * onhost-live-adapter.js
 *
 * One row format for everything the panel shows as a stream: game console,
 * VPS serial console, web terminal, logs and the task queue.
 *
 *   { t: '08:14:02', src: 'console', m: 'TPS 19,4 · 118 hráčů', k: 'sys', l: 'ok' }
 *
 *   t    string  time as the user should read it (HH:MM:SS, or "26. 8. 18:02" for
 *                anything older than today). Never a raw timestamp — the panel
 *                does not reformat it.
 *   src  string  short source tag rendered in brackets: console, systemd, rcon,
 *                deploy, postgres, onhost… Keep it under 12 characters.
 *   m    string  the line itself, already in the user's language where it is our
 *                text. Server output stays verbatim — we do not translate logs.
 *   k    string  who/what produced it. Drives colour and the note under a row:
 *                  'sys'  the service itself (default)
 *                  'cmd'  a command the customer sent (game/RCON)
 *                  'you'  a command the customer sent (shell/serial)
 *                  'join' a player or session connecting
 *                  'us'   an action by Onhost staff — REQUIRES a name and a
 *                         reason in m, e.g. 'vstup inženýra (M. Král) · důvod: …'
 *   l    string  severity: 'ok' | 'warn' | 'err'.
 *
 * The panel keeps the newest line first in state and renders reversed, so a
 * console reads like a console (newest at the bottom). Cap the buffer: 40 lines
 * for a console pane, 60 for the shell. Anything longer belongs in the log
 * download, not in the DOM.
 *
 * Nothing here fetches on its own. Call createLiveSource() from the host app
 * once you have a real endpoint; until then the panel runs on seeded data.
 */

const LEVELS = { ok: 'ok', warn: 'warn', warning: 'warn', err: 'err', error: 'err', critical: 'err' };

export function timeLabel(date, now) {
  const d = date instanceof Date ? date : new Date(date);
  const ref = now || new Date();
  const sameDay = d.toDateString() === ref.toDateString();
  const p = (n) => String(n).padStart(2, '0');
  if (sameDay) return p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + p(d.getHours()) + ':' + p(d.getMinutes());
}

/** Wings (Pterodactyl) console event → panel row. */
export function fromWings(event) {
  const raw = String(event && event.line != null ? event.line : event);
  const stripped = raw.replace(/\u001b\[[0-9;]*m/g, '').replace(/\s+$/, '');
  const warn = /\bWARN|WARNING\b/i.test(stripped);
  const err = /\bERROR|FATAL|Exception|OutOfMemory\b/i.test(stripped);
  const join = /joined the game|connected|has entered/i.test(stripped);
  return {
    t: timeLabel(event && event.at ? event.at : Date.now()),
    src: (event && event.source) || 'console',
    m: stripped,
    k: join ? 'join' : 'sys',
    l: err ? 'err' : warn ? 'warn' : 'ok'
  };
}

/** journald JSON (journalctl -o json-seq) → panel row. */
export function fromJournald(entry) {
  const pri = Number(entry.PRIORITY);
  return {
    t: timeLabel(Number(entry.__REALTIME_TIMESTAMP) / 1000),
    src: (entry.SYSLOG_IDENTIFIER || entry._SYSTEMD_UNIT || 'systemd').replace(/\.service$/, ''),
    m: entry.MESSAGE,
    k: 'sys',
    l: pri <= 3 ? 'err' : pri === 4 ? 'warn' : 'ok'
  };
}

/** A command the customer just submitted in the panel. */
export function fromCommand(text, opts) {
  const o = opts || {};
  return {
    t: timeLabel(Date.now()),
    src: o.src || 'console',
    m: (o.prompt || '>') + ' ' + text,
    k: o.shell ? 'you' : 'cmd',
    l: 'ok'
  };
}

/** An action by our own staff. Refuses to build a row without a reason. */
export function fromStaffAction({ engineer, reason, ticket, at }) {
  if (!engineer || !reason) throw new Error('staff action needs an engineer and a reason');
  return {
    t: timeLabel(at || Date.now()),
    src: 'onhost',
    m: 'vstup inženýra (' + engineer + ') · důvod: ' + reason + (ticket ? ' · tiket ' + ticket : ''),
    k: 'us',
    l: 'ok'
  };
}

export function normalize(input, shape) {
  if (shape === 'wings') return fromWings(input);
  if (shape === 'journald') return fromJournald(input);
  if (shape === 'aapanel') return fromAaPanel(input);
  if (shape === 'ispconfig') return fromIspConfig(input);
  if (shape === 'proxmox') return fromProxmox(input);
  if (input && input.m && input.t) return Object.assign({ k: 'sys', l: 'ok', src: 'console' }, input, { l: LEVELS[input.l] || 'ok' });
  return fromWings(input);
}

/* ==========================================================================
 * Panely: aaPanel, ISPConfig, Proxmox VE, Pterodactyl
 *
 * Čtyři cizí panely, jeden řádek na výstupu. Žádný z nich nemá WebSocket na
 * všechno: aaPanel a ISPConfig se dotazují (poller), Proxmox má task log a
 * noVNC ticket, Wings má WS na konzoli. Endpointy níž jsou reálné cesty jejich
 * API — host je doplní o base URL a token.
 * ========================================================================== */

export const PANEL_API = {
  aapanel: {
    auth: 'request_token = md5(api_key + md5(request_time)) v těle POSTu',
    poll: 2000,
    endpoints: {
      sites: 'POST /data?action=getData&table=sites',
      phpVersion: 'POST /site?action=GetPHPVersion',
      setPhp: 'POST /site?action=SetPHPVersion',
      ssl: 'POST /site?action=GetSSL',
      issueSsl: 'POST /acme?action=apply_cert_api',
      files: 'POST /files?action=GetDir',
      writeFile: 'POST /files?action=SaveFileBody',
      databases: 'POST /data?action=getData&table=databases',
      firewall: 'POST /safe?action=GetFirewallList',
      backupNow: 'POST /site?action=ToBackup',
      quota: 'POST /system?action=GetDiskInfo',
      logs: 'POST /files?action=GetFileBody (path: /www/wwwlogs/<domain>.log)'
    }
  },
  ispconfig: {
    auth: 'login(user, pass) → session_id, pak každý call se session_id (REST i SOAP)',
    poll: 3000,
    endpoints: {
      clients: 'POST /remote/json.php?client_get_all',
      clientLimits: 'POST /remote/json.php?client_get',
      mailboxes: 'POST /remote/json.php?mail_user_get',
      setQuota: 'POST /remote/json.php?mail_user_update',
      spamPolicy: 'POST /remote/json.php?mail_policy_get',
      dnsZone: 'POST /remote/json.php?dns_zone_get',
      dnsRecord: 'POST /remote/json.php?dns_txt_add',
      dkim: 'POST /remote/json.php?mail_domain_dkim_get',
      shellUsers: 'POST /remote/json.php?sites_shell_user_get',
      jobQueue: 'SELECT * FROM sys_datalog WHERE datalog_id > ? (jobqueue není v REST API)'
    }
  },
  proxmox: {
    auth: 'PVEAPIToken=user@realm!tokenid=secret v Authorization; ticket jen pro noVNC',
    poll: 2000,
    endpoints: {
      nodes: 'GET /api2/json/cluster/status',
      resources: 'GET /api2/json/cluster/resources?type=vm',
      nodeStatus: 'GET /api2/json/nodes/{node}/status',
      rrd: 'GET /api2/json/nodes/{node}/rrddata?timeframe=hour',
      tasks: 'GET /api2/json/nodes/{node}/tasks?limit=50',
      taskLog: 'GET /api2/json/nodes/{node}/tasks/{upid}/log',
      snapshot: 'POST /api2/json/nodes/{node}/qemu/{vmid}/snapshot',
      rollback: 'POST /api2/json/nodes/{node}/qemu/{vmid}/snapshot/{name}/rollback',
      migrate: 'POST /api2/json/nodes/{node}/qemu/{vmid}/migrate',
      vncproxy: 'POST /api2/json/nodes/{node}/qemu/{vmid}/vncproxy',
      vncwebsocket: 'GET /api2/json/nodes/{node}/qemu/{vmid}/vncwebsocket?port=&vncticket=',
      storage: 'GET /api2/json/nodes/{node}/storage',
      ceph: 'GET /api2/json/cluster/ceph/status',
      pbsSnapshots: 'GET /api2/json/nodes/{node}/storage/{store}/content',
      firewall: 'GET /api2/json/cluster/firewall/rules',
      sdn: 'GET /api2/json/cluster/sdn/zones'
    }
  },
  pterodactyl: {
    auth: 'Bearer ptlc_… (client API) nebo ptla_… (application API)',
    poll: 0,
    endpoints: {
      servers: 'GET /api/client',
      resources: 'GET /api/client/servers/{id}/resources',
      websocket: 'GET /api/client/servers/{id}/websocket → { token, socket }',
      power: 'POST /api/client/servers/{id}/power  { signal: start|stop|restart|kill }',
      command: 'POST /api/client/servers/{id}/command',
      startup: 'GET /api/client/servers/{id}/startup',
      schedules: 'GET /api/client/servers/{id}/schedules',
      backups: 'GET /api/client/servers/{id}/backups',
      restore: 'POST /api/client/servers/{id}/backups/{backup}/restore',
      allocations: 'GET /api/client/servers/{id}/network/allocations',
      subusers: 'GET /api/client/servers/{id}/users',
      nodes: 'GET /api/application/nodes (application API, jen NOC)',
      eggs: 'GET /api/application/nests/{nest}/eggs'
    }
  }
};

/** aaPanel: řádek z GetFileBody logu nebo záznam z fronty úloh. */
export function fromAaPanel(entry) {
  if (entry && entry.name && entry.status !== undefined) {
    return {
      t: timeLabel(entry.start || Date.now()),
      src: 'aapanel',
      m: entry.name + (entry.status === -1 ? ' · selhalo' : entry.status === 0 ? ' · běží' : ' · hotovo'),
      k: 'sys',
      l: entry.status === -1 ? 'err' : 'ok'
    };
  }
  const line = String(entry && entry.line != null ? entry.line : entry).replace(/\s+$/, '');
  const code = (line.match(/"\s(\d{3})\s/) || [])[1];
  return {
    t: timeLabel(Date.now()),
    src: 'nginx',
    m: line,
    k: 'sys',
    l: code && Number(code) >= 500 ? 'err' : code && Number(code) >= 400 ? 'warn' : 'ok'
  };
}

/** ISPConfig: záznam ze sys_datalog (fronta zápisů na servery). */
export function fromIspConfig(row) {
  const action = { i: 'vytvoření', u: 'změna', d: 'smazání' }[row.action] || row.action;
  const waited = row.tstamp ? Math.round(Date.now() / 1000 - Number(row.tstamp)) : 0;
  return {
    t: timeLabel(Number(row.tstamp || 0) * 1000 || Date.now()),
    src: 'ispconfig',
    m: action + ' · ' + row.dbtable + ' #' + row.dbidx + ' → server ' + row.server_id
      + (row.status === 'pending' ? ' · čeká ' + waited + ' s' : ' · zapsáno'),
    k: 'sys',
    l: row.status === 'pending' && waited > 120 ? 'warn' : 'ok'
  };
}

/** Proxmox: záznam z /nodes/{node}/tasks. */
export function fromProxmox(task) {
  const failed = task.status && task.status !== 'OK';
  const running = !task.endtime;
  return {
    t: timeLabel(Number(task.starttime || 0) * 1000 || Date.now()),
    src: 'pve/' + (task.node || 'cluster'),
    m: (task.type || 'task') + (task.id ? ' ' + task.id : '') + ' · ' + (task.user || 'root@pam')
      + ' · ' + (running ? 'běží' : failed ? String(task.status) : 'OK'),
    k: /^root@pam$/.test(task.user || '') ? 'sys' : 'you',
    l: failed ? 'err' : running ? 'warn' : 'ok'
  };
}

/**
 * createPanelPoller({ url, token, shape, interval, cap, fetchRows, onLine, onState })
 *
 * Pro panely bez WebSocketu (aaPanel, ISPConfig, Proxmox task log). fetchRows je
 * async funkce, kterou dodá host — adaptér nefetchuje sám, aby se autentizace
 * nemíchala do vykreslování. Vrací { lines, refresh, close } se stejným tvarem
 * řádků jako createLiveSource, takže konzole a fronta úloh vypadají stejně.
 */
export function createPanelPoller(opts) {
  const { shape = 'aapanel', interval, cap = 40, fetchRows, onLine, onState } = opts || {};
  if (typeof fetchRows !== 'function') throw new Error('createPanelPoller needs fetchRows()');
  const wait = interval || (PANEL_API[shape] && PANEL_API[shape].poll) || 3000;
  let lines = [];
  let closed = false;
  let timer = null;
  let fails = 0;

  const push = (row) => {
    lines = [row].concat(lines).slice(0, cap);
    if (onLine) onLine(row, lines);
  };

  const tick = async () => {
    if (closed) return;
    try {
      const rows = (await fetchRows()) || [];
      fails = 0;
      if (onState) onState('open');
      rows.forEach((r) => push(normalize(r, shape)));
    } catch (e) {
      // Poller, který mlčí, vypadá jako klidný panel. Řekneme to nahlas.
      fails += 1;
      if (onState) onState('error', { fails: fails, message: String(e && e.message || e) });
    }
    if (!closed) timer = setTimeout(tick, Math.min(30000, wait * Math.pow(2, Math.max(0, fails - 1))));
  };
  tick();

  return {
    get lines() { return lines; },
    refresh() { clearTimeout(timer); tick(); },
    close() { closed = true; clearTimeout(timer); if (onState) onState('closed'); }
  };
}

/**
 * Audit zásahu provedeného přes panel. Bez jména a důvodu řádek nevznikne —
 * stejné pravidlo jako u fromStaffAction, jen tady platí i pro zákazníka.
 */
export function panelAudit({ who, role, what, panel, at }) {
  if (!who || !what) throw new Error('panel audit needs who and what');
  return {
    at: timeLabel(at || Date.now()),
    who: who,
    role: role || 'Klient',
    what: (panel ? panel + ' · ' : '') + what
  };
}

/**
 * createLiveSource({ url, token, shape, cap, onLine, onState })
 *
 * WebSocket with backoff, one buffer, no global state. Returns
 * { lines, send, close }. onLine receives (row, allLines) after every append,
 * so the host can drop it straight into setState.
 */
export function createLiveSource(opts) {
  const { url, token, shape = 'wings', cap = 40, onLine, onState } = opts || {};
  if (!url) throw new Error('createLiveSource needs a url');
  let lines = [];
  let socket = null;
  let attempt = 0;
  let closed = false;
  let timer = null;

  const push = (row) => {
    lines = [row].concat(lines).slice(0, cap);
    if (onLine) onLine(row, lines);
  };
  const state = (s, detail) => { if (onState) onState(s, detail); };

  const connect = () => {
    if (closed) return;
    socket = new WebSocket(url + (token ? (url.indexOf('?') >= 0 ? '&' : '?') + 'token=' + encodeURIComponent(token) : ''));
    socket.onopen = () => { attempt = 0; state('open'); };
    socket.onmessage = (ev) => {
      let payload = ev.data;
      try { payload = JSON.parse(ev.data); } catch (e) { /* plain text line */ }
      push(normalize(payload, shape));
    };
    socket.onclose = () => {
      if (closed) return;
      // 1s, 2s, 4s… capped at 15s. A console that reconnects silently forever
      // is worse than one that says it lost the connection.
      const wait = Math.min(15000, 1000 * Math.pow(2, attempt++));
      state('reconnecting', { in: wait });
      timer = setTimeout(connect, wait);
    };
    socket.onerror = () => state('error');
  };

  connect();

  return {
    get lines() { return lines; },
    send(text, sendOpts) {
      push(fromCommand(text, sendOpts));
      if (socket && socket.readyState === 1) socket.send(JSON.stringify({ command: text }));
      else state('queued', { text });
    },
    close() { closed = true; clearTimeout(timer); if (socket) socket.close(); state('closed'); }
  };
}
