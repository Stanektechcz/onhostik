# Connecting a provider (Proxmox, ISPConfig, aaPanel, Pterodactyl, PowerDNS, WEDOS, Subreg, RKE2)

Instances are registered in **Nastavení systému → Integrace providerů** (`/sprava/nastaveni/integrace`, linked
from the console's user menu) or through the staff API below; credentials never go into git or `.env` files on
workstations — the console stores them encrypted under `db://provider_instances/<key>` and only ever shows which
keys are present. Registration needs `provider.instance.manage` and a fresh step-up (TOTP or recovery code; a
staff password is accepted only while TOTP is not enrolled and `ONHOST_STAFF_MFA_REQUIRED` is off).

TLS: vendor APIs are verified against the system CA bundle. Self-signed panels (Proxmox, PBS, aaPanel without a
public certificate) are pinned by pasting the certificate PEM into "Připnutý certifikát / CA" (option `tls_ca`);
`verify_tls: false` is refused in production. The control plane's egress IP must be on the vendor's allow-list
(aaPanel API, ISPConfig remote user, WEDOS WAPI).

Verified live on 2026-09-07: ISPConfig 3 at `https://s2.onhost.cz:8080` (remote user with sites + server + monitor
functions, `server_id` auto-resolved to 1, job queue read) and aaPanel 8.0.6 at `https://45.67.217.22:28133`
(system totals, site list). Both are registered as `ispconfig-s2` and `aapanel-cz1` on the development database.

## 1. Prepare the provider side

| Provider | What to create | Notes |
| --- | --- | --- |
| Proxmox VE | API token `onhost@pve!cp` (Datacenter → Permissions → API tokens, privilege separation on) with role: VM.Allocate, VM.Clone, VM.Config.*, VM.PowerMgmt, VM.Console, VM.Snapshot, VM.Backup, Datastore.AllocateSpace, Datastore.Audit, Sys.Audit on `/` | keep `verify_tls: false` only in labs; production uses a CA-signed API certificate |
| Proxmox Backup Server | API token with Datastore.Backup + Datastore.Verify on the datastore | option `pbs_datastore` on the Proxmox instance |
| ISPConfig | System → Remote users: user with client, sites, mail, dns and server functions | option `server_id` (web) and `mail_server_id` |
| aaPanel | Settings → API interface: enable, copy the key, allow-list the control plane's egress IP | signature = md5(time + md5(key)) is computed by the adapter |
| Pterodactyl | Admin → Application API key (read/write on users, servers, nodes, allocations) + a client API key of the ONhost service user | option `eggs` maps catalog game keys to `{nest, egg}` |
| PowerDNS | `api=yes`, `api-key=…`, `webserver=yes` in pdns.conf; secondaries allow NOTIFY from the primary | option `nameservers` = NS set published in new zones |
| Subreg | Account → API user with API access, allow the control plane IP; optionally a demoreg.net sandbox account (instance option `demo: true`) | one instance (`subreg-main`); only domain functions are used (`docs/provider-adapters/subreg.md`) |
| WEDOS WAPI | Customer admin → WAPI: enable JSON, set WAPI password, allow the control plane IP | one instance for the registrar (`wedos`), optionally one for WEDOS Zone (`wedos_zone`) |
| RKE2 | Service account with the RBAC in `infra/rke2/policies`, its token and the cluster CA | options `ingress_class`, `cluster_issuer`, `registry`, `apps_domain` |

## 2. Register the instance

`POST /v1/staff/integrations` (or `PUT /v1/staff/integrations/{key}`):

```json
{
  "key": "proxmox-cz1", "provider": "proxmox", "name": "PVE Praha 1", "region_code": "cz1",
  "base_url": "https://pve.onhost.internal:8006",
  "options": { "verify_tls": true, "storage": "nvme", "bridge": "vmbr0", "pbs_datastore": "pbs-cz1" },
  "credentials": { "token_id": "onhost@pve!cp", "token_secret": "…" }
}
```

* Credentials are stored under `db://provider_instances/<key>` encrypted with the application key
  (`secrets` table; key names are visible, values never). To use OpenBao instead, pass
  `"secret_ref": "bao://onhost/providers/proxmox-cz1"` and write the secret there.
* `GET /v1/staff/integrations/schema` lists the credential keys and default capabilities per provider.
* Blank credential fields on an update keep the stored values (rotate by sending new ones).
* **Rotation is verified before it takes effect (Brain card H314).** A value that replaces a stored one is tried against
  the panel first — a trial adapter in memory, the health read in the diagnostic lane, and for the game panel the
  client key separately from the application key. The panel accepts it → it is stored. The panel refuses it →
  `instance_credentials_unverified` (422), nothing is stored and the working access stays. Sending the same value again
  is not a rotation and asks the panel nothing. When the stored access is compromised and the panel cannot confirm the
  new one, store it anyway with `force_credentials: true` (console: the confirmation dialog; terminal:
  `onhost:integrations:secret <instance> <key> --force`); the audit record carries `credentials_forced`. Values never
  appear in an audit row, a log or a response.
* **An access belongs to one instance.** `secret_ref: db://provider_instances/<key>` is accepted only for the instance
  with that key (`instance_secret_ref_foreign` otherwise): a look-alike instance can never borrow another panel's
  credentials and send them to its own host.

## 3. Test the connection

`POST /v1/staff/integrations/{key}/probe` runs the adapter health check (Proxmox `/version`, ISPConfig login,
aaPanel `/system?action=GetSystemTotal`, Pterodactyl `/api/application/nodes`, PowerDNS `/api/v1/servers`, WAPI
`ping`, Kubernetes `/version`). The result is stored on the instance and in `integration_health`; failures show a
redacted error. The scheduled probe (`onhost:integrations:health`) repeats it every few minutes and raises
`integration.down` / `integration.recovered`.

## 4. Nodes and capacity

* Proxmox: `POST /v1/staff/integrations/{key}/discover` imports cluster nodes with their CPU/RAM/disk; re-run
  any time (manual capacity overrides are kept).
* Other executors: `POST /v1/staff/integrations/{key}/nodes` with name, role (web | managed | game | mail | dns |
  apps | backup), capacity and public addresses in `tags`.
* IP pools for VPS come from `POST /v1/staff/ipam/pools` (IPAM) — without a pool in the region, VPS orders wait
  with `ipam.exhausted`.

## 5. Go live

1. `GET /v1/staff/capacity` shows the new node in the sellable capacity of its role.
2. Place a test order in staging and watch `GET /v1/staff/provisioning/jobs`.
3. Set `state: draining` before maintenance (no new placements), `maintenance` with `maintenance_until`, or
   `disabled` to remove the instance from scheduling and health checks.
