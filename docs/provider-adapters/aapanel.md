# aaPanel

**Files:** `providers/AaPanel/*` · **Contract test:** `tests/Contract/AaPanelContractTest.php`

Signed requests: every call carries `request_time` and `request_token = md5(request_time + md5(api_key))`;
the panel IP allow-list must contain the control plane's egress address. Key from `env://AAPANEL_<KEY>`,
options `public_ipv4`, `verify_tls`, `default_php`.

| Contract | Method | aaPanel endpoint |
| --- | --- | --- |
| InfrastructureProvider (web) | `create` | `/site?action=AddSite` (webname JSON, path, php version, ftp, sql, remark `ps = onhost:<service>`). A site of that name without **this** service's remark is historical or another service's and is refused with `CONFLICT` before any write (TASK-0014, owner rule of 2026-09-24; taking one over only via `docs/runbooks/historical-site-import.md`) |
| | `resize` | **nothing on the node** (`applied: false`, `AaPanelWebProvider::resize()`): aaPanel runs one PHP-FPM pool per PHP version for the whole node, and `SetPHPMaxChildren` would resize it for every customer on that version. The pool is sized by the operator with the node; the plan's numbers live in the service's entitlements and are checked by the platform before each action |
| | `suspend` / `resume` | `/site?action=SiteStop` / `SiteStart`; the suspension depth (cron jobs, FTP accounts, Node apps, game schedules) is the platform's `SuspensionDepth` — `docs/runbooks/web-tools.md` (*A suspended site is more than a stopped vhost*) |
| | `destroy` (`terminate`) | the site's cron jobs and Node.js projects first (`dropCron`, `dropNodeProjects`), then `/site?action=DeleteSite` (ftp, database, path) |
| SSL | `enableSsl` | `/acme?action=apply_cert_api` (Let's Encrypt) |
| Backups | `backup` | `/site?action=ToBackup` — files only, and only the fallback: a backup of a web service is the platform's own set (files by `files?action=Zip` of the root entries + every database dump), see `docs/runbooks/backups.md` |
| Usage | `usage()` | `/system?action=GetSystemTotal` + `GetDiskInfo` — figures of the **whole node** (`node_cpu_pct`, `node_mem_pct`, `node_disk_pct`). For a web or managed service they are not the customer's: `Metering\CustomerUsage::of()` keeps the keys and shows them as `null` in `GET /v1/services/{id}/usage` (audit H286, TASK-0023). The customer's own disk and traffic come from the toolkit's `quotas` (`du`), database sizes are not measured on aaPanel (`panel_reports_no_database_size`, the plan total is then `partial`) |

Spec keys: `domain`, `php_version`, `entitlements`. Paths in aaPanel move between major versions
(docs/development/docs-provider-apis.md); the adapter pins the paths in one map and the contract test locks them.

Errors: `status:false` with "already exists" → `CONFLICT`; IP not allowed / bad token → `AUTH`; 5xx →
`TRANSIENT`. Sensitive fields (`password`, `api_key`, `request_token`) are redacted from `provider_calls`.

## Web toolkit (`AaPanelTools`, `AaPanelShell`, `AaPanelTransport`)

The panel API has no tenant-scoped shell, so the toolkit wraps `files?action=ExecShell` (root, asynchronous): every
command runs as `timeout N bash -c '…' > /tmp/<id>.out 2>&1; echo $? > /tmp/<id>.exit` switched to the site user with
`su -s /bin/bash www -c`, the exit file is polled through `GetFileBody`, the output read the same way and both files
removed. `shell: ssh` on the instance (with `ssh_host`, `ssh_port`, `ssh_user` and the `ssh_private_key` credential)
replaces the wrapper with SSH. Files go through the panel file API (`GetDir`, `GetFileBody`, `SaveFileBody`,
`CreateFile`, chunked `upload`, `MvFile`, `SetFileAccess`, `Zip`, `UnZip`, `DeleteFile`/`DeleteDir`); downloads
stream through `GetFileBody` in base64 chunks. On top: `.user.ini` PHP settings, the managed security include
(`vhost/nginx/onhost/<site>.security.conf`, `nginx -t` with rollback), HTTP/3 (`listen 443 quic`), cron
`modify_crond`/`StartTask`/`GetLogs`, `GetDatabaseAccess`/`SetDatabaseAccess`, `ToBackup`/`InputSql` exports and
imports, `DelBackup`, run path switching (`SetSiteRunPath`, used by git deploy releases), Node projects
(`/project/nodejs/*`) and `du`/`find` quotas. Tenancy rules: cron jobs are matched by the `onhost:<service>:` label
prefix, databases by the service prefix, Node projects by the site path. Contract: `tests/Contract/AaPanelToolsContractTest.php`.

## What a plan sells on aaPanel (owner decision 7, TASK-0023, TASK-0027)

`php_workers` on aaPanel is a share of the node's pool, and the price list says so (*Sdílené PHP workery*). A plan that
sells **dedicated** workers (`php_workers_dedicated`) is placed on ISPConfig only (`PlacementRules`: placement, staff pin,
scheduler, cart, plan change — `placement_requires_dedicated_php`, `plan_change_does_not_fit`); the one managed plan that
sold it on aaPanel (`eshop/shop-peak`) gets a new plan version without it through the catalogue revision
`2026-09-shared-php-workers` (`php artisan onhost:catalog:revise`, `docs/runbooks/pricing.md`). Services already on the
old version keep it and are listed by `onhost:capacity:basis` and the doctor; nothing is moved.
