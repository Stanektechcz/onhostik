# Connecting a provider (Proxmox, ISPConfig, aaPanel, Pterodactyl, PowerDNS, WEDOS, Subreg, RKE2)

Instances are registered in **Nastavení systému → Integrace providerů** (`/sprava/nastaveni/integrace`, linked
from the console's user menu) or through the staff API below; credentials never go into git or `.env` files on
workstations — the console stores them encrypted under `db://provider_instances/<key>` and only ever shows which
keys are present. Registration needs `provider.instance.manage` and a fresh step-up (TOTP or recovery code; a
staff password is accepted only while TOTP is not enrolled and `ONHOST_STAFF_MFA_REQUIRED` is off). The same holds for
customers: the password is a second verification only for an account without an authenticator — once TOTP is enrolled,
a step-up takes the code or a recovery code. The setting is `onhost.identity.staff_mfa_required`; the step-up and the
doctor used to read `onhost.security.staff_mfa_required`, which never existed, so the doctor reported staff MFA as off
whatever the environment said.

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
| Proxmox Backup Server | API token with Datastore.Backup + Datastore.Verify on the datastore | option `backup_storage` on the Proxmox instance = the Proxmox storage ID of that datastore (the code reads `backup_storage`; `pbs_datastore` is read by nothing) |
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
  "options": { "verify_tls": true, "storage": "nvme", "bridge": "vmbr0", "backup_storage": "pbs-cz1" },
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
* **After a rotation the old key is still valid at the panel** until somebody deletes it there: the platform replaces
  what *it* uses, it cannot revoke a key the panel issued. Delete the old API key / remote user password at the panel
  as the last step of every rotation — that is the one manual step at a panel the rotation needs (Brain card H12).
* **No secret reaches a log (H12).** `provider_calls` and the audit trail are redacted by key name; an answer that is a
  credential under a neutral key is withheld whole (`ProviderRequest::secretResponse`): the ISPConfig `login` (the
  session) and `client_login_get` (a one-time link into the customer's panel). An adapter that adds such a call sets the
  flag; `tests/Feature/Platform/SecretsInLogsTest.php` plants recognisable values and looks for them in everything
  that was written.
* **An access belongs to one instance.** `secret_ref: db://provider_instances/<key>` is accepted only for the instance
  with that key (`instance_secret_ref_foreign` otherwise): a look-alike instance can never borrow another panel's
  credentials and send them to its own host.

## 3. Test the connection

`POST /v1/staff/integrations/{key}/probe` runs the adapter health check (Proxmox `/version`, ISPConfig login,
aaPanel `/system?action=GetSystemTotal`, Pterodactyl `/api/application/nodes`, PowerDNS `/api/v1/servers`, WAPI
`ping`, Kubernetes `/version`). The result is stored on the instance and in `integration_health`; failures show a
redacted error. The scheduled probe (`onhost:integrations:health`) repeats it every few minutes and raises
`integration.down` / `integration.recovered`.

## 3a. What the panel really answers (`SelfProbing`)

Adapters are written from the vendors' documentation and proven against doubles. Whether the panel in the rack answers
the same way is a fact about that panel — its version, the rights of the API user, a missing plugin. The prerequisites
pass (`onhost:nodes:check`, nightly; `POST /v1/staff/integrations/{instance}/prerequisites` on demand) asks read-only
and records the answers under `capabilities.prereqs.probes`:

| Panel | Probe | What it decides |
| --- | --- | --- |
| ISPConfig | `backup_api` (`sites_web_domain_backup_list`) | whether panel archives can be listed and restored at all — the remote user needs the *sites* backup functions |
| ISPConfig | `datalog_api`, `datalog_fields` (`sys_datalog_get_by_tstamp`) | whether a change can be followed by its own record instead of the whole server's queue; the field NAMES the panel sends are written down (never values). Informational: no warning |
| aaPanel | `backup_api`, `files_api` | the backup table behind restores and exports; the file API behind "pack the whole site" |
| Pterodactyl | `server_status_field`, `backup_api` | a restore is followed by `status` of the server in the application API; the client API lists backups |
| Proxmox | `backup_storage` | the instance option is set and its content can be listed — backups and final snapshots are found there |

A failed probe is a warning of the pass (`integration.prereqs.regressed` tells operations the night it appears).
After the first pass on staging, read `datalog_fields`: if it lists `status` and `error`, per-change tracking on
ISPConfig can be built on it (audit §7, item 2).

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

## TLS of a panel behind a private CA

Every adapter reads the instance options `tls_ca` (the CA or the self-signed certificate — a path on the control plane or
the PEM text pasted in the console; it is written once to `storage/app/tls/<instance>.pem`) and `verify_tls: false`
(development only; refused in production). The game panel has one more: `wings_tls_ca` for its daemons when they carry
another certificate than the panel itself (it falls back to `tls_ca`). Transfer links of the game panel are followed only
to the FQDNs of its own nodes — a node whose FQDN in the panel differs from the name in its signed links cannot transfer
backups until the two agree.

## ISPConfig: following our own change, not the whole server's queue (2026-09-21)

ISPConfig applies nothing in the response. The remote API writes a row into `sys_datalog`; the server's own cron reads
it, does the work and writes the outcome back into that row. The adapter used to watch `monitor_jobqueue_count` — the
length of the **whole server's** queue — which is wrong in both directions:

* an empty queue was reported as **success** even when our own job had failed (the row is taken out of the queue either
  way), so the platform told a customer their PHP version had changed when the node had refused it;
* on a busy server, other people's writes kept the count above zero and our operation waited until its timeout, long
  after it had been applied.

**Now:** a write the connector can name (`IspConfigConnector::DATALOG_TABLES`) records the row it will become —
`web_domain / domain_id:7`, `mail_user / mailuser_id:12`, … — and `jobqueueHandle` carries it. `awaitStatus` then reads
`sys_datalog_get_by_tstamp` and looks for **that** row:

| what the row says | what the platform does |
| --- | --- |
| `status = ok` | the operation finishes at once, whatever else is queued |
| `status = error` | the operation **fails**, with the server's own message |
| still pending, or no row yet | keep waiting |
| the write had no name, or no row of ours is there | the old queue count, exactly as before |

It is **opt in on evidence**: only a panel whose nightly node check really returned a `status` field
(`prereqs.probes.datalog_fields`) is followed this way. An older ISPConfig, or a remote user without that function
group, keeps the behaviour it had. A table this map guesses wrongly can only *miss* — the row is matched on the table
**and** the index — so it can never pick up somebody else's record.

**What is still needed on the node.** The lower bound of an ISPConfig change is how often `server.sh` runs; by default
that is once a minute, so "in seconds" is impossible however precisely the platform watches. Run it on a systemd timer
every 10–15 s on the web nodes, then this path makes the difference visible.

Tests: `tests/Contract/IspConfigDatalogContractTest.php`.
