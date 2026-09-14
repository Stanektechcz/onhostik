# aaPanel

**Files:** `providers/AaPanel/*` · **Contract test:** `tests/Contract/AaPanelContractTest.php`

Signed requests: every call carries `request_time` and `request_token = md5(request_time + md5(api_key))`;
the panel IP allow-list must contain the control plane's egress address. Key from `env://AAPANEL_<KEY>`,
options `public_ipv4`, `verify_tls`, `default_php`.

| Contract | Method | aaPanel endpoint |
| --- | --- | --- |
| InfrastructureProvider (web) | `create` | `/site?action=AddSite` (webname JSON, path, php version, ftp, sql) |
| | `resize` | `/site?action=SetPHPVersion`, `/config?action=…` quotas |
| | `suspend` / `resume` | `/site?action=SiteStop` / `SiteStart` |
| | `destroy` | `/site?action=DeleteSite` (ftp, database, path) |
| SSL | `enableSsl` | `/acme?action=apply_cert_api` (Let's Encrypt) |
| Backups | `backup` | `/site?action=ToBackup` |
| Usage | `usage()` | `/system?action=GetNetWork` + site disk sizes |

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
