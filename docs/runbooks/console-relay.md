# Console relay (VNC / serial / game console)

Customers never receive provider credentials. The path is:

1. Panel calls `POST /v1/services/{id}/console-token` (audited, service must be active/suspended).
2. The adapter creates a provider-side session (Proxmox `vncproxy` ticket, Pterodactyl Wings websocket token),
   stores the descriptor in the cache under `onhost:console:con_<ulid>` for 120 s and returns to the browser
   only `{kind, token, url: /console/ws/<token>, expires_at}`.
3. The browser opens the websocket at the relay service with the token. The relay calls
   `GET /console/ws/{token}` with header `X-Relay-Key` (`ONHOST_CONSOLE_RELAY_KEY`); the descriptor is returned
   once and deleted (single use). The relay then proxies frames to the upstream (`upstream` + `vncticket` or
   `socket` + `token`) and closes when the provider session expires.
4. `GET /console/check/{token}` lets the panel show "session expired" without revealing upstream details.

Relay service: a small websocket proxy (Node or Go) deployed next to the app, no persistent state, outbound
access only to provider networks. It must reject tokens whose descriptor `organization_id` does not match
the browser's session when the relay authenticates browsers itself; otherwise rely on the single-use token
lifetime. Logs contain token ids and service ids, never tickets.

Troubleshooting:

* 401 on `/console/ws` → relay key mismatch (`config/onhost.php` → `console.relay_key`).
* 410 → token expired or already consumed; the panel must request a new one.
* Black screen after connect → provider session expired (Proxmox tickets live ~30 s after issue); request again.

## Consoles end with access (TASK-0044, permission program D20 / S1-08)

A console ticket is no bearer value any more, and an open console is asked about again:

* `GET /console/ws/{token}` (resolve) refuses with 410 `console_session_ended` when the person the ticket was issued to may no
  longer open it: account disabled, `service.console` (or `staff.console` as staff) no longer held on that service, or their
  consoles were ended after the ticket was issued (`ConsoleSessions::refusal`). The answer carries `alive_every` (seconds).
* The relay (`infra/console-relay/server.mjs`) then calls `GET /console/ws/{token}/alive` with the same `X-Relay-Key` every
  `alive_every` seconds (`ONHOST_CONSOLE_ALIVE_SECONDS`, default 15, never below 5). 200 keeps the socket; 401/403/404/410 closes
  it at once with code 4001 `access ended`; three failed checks in a row (the API unreachable) close it with 4002.
* A removed or demoted member's open console therefore closes within `alive_every` seconds; a ticket issued a minute before the
  removal opens nothing. An MFA reset and an owner taken out by an owner recovery end the person's consoles explicitly
  (`SessionKill` → `ConsoleSessions::endFor`), together with every web session, the "remember me" token and step-up grants; an
  MFA reset also revokes every personal API token. Audit: `service.console.relay` (`denied`), `service.console.closed`,
  `identity.sessions.end`.
* Deploy order: the API first (the alive route is additive), then the relay. An old relay without the alive loop still works,
  but keeps an open console until it ends by itself (at most the 2-hour cap).

Residual window per panel (what the platform cannot close):

| Panel | Console channel | Ends when access ends | Residual window |
| --- | --- | --- | --- |
| Proxmox (noVNC) | relay → `vncwebsocket` | yes — the relay closes the socket on the next alive check | ≤ `alive_every` s; the `vncticket` is single-use and lives ~30 s |
| Pterodactyl (Wings console) | relay → Wings websocket | yes — as above | ≤ `alive_every` s; a Wings token lives ~10 min but is only usable through the relay |
| Panel sign-on (aaPanel, ISPConfig, Pterodactyl panel login) | the panel's own browser session after an SSO link | no — none of the three offers an API to end one session | until the panel's own session times out; the panel identity itself is revoked by `RevokeDelegatedAccess` / the provenance work (S1-06, open) |
| SSH / SFTP sessions opened with a key | direct to the node | no — the key is removed, an established session is not killed | until the client disconnects |
