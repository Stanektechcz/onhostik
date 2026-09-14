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
