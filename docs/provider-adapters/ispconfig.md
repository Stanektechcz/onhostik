# ISPConfig 3.3

**Files:** `providers/IspConfig/*` · **Contract test:** `tests/Contract/IspConfigContractTest.php`

Remote API `remote/json.php` with a session per call (`login` → `session_id` → `logout`). Remote user and
password come from `env://ISPCONFIG_<KEY>`; options: `server_id`, `mail_server_id`, `public_ipv4`,
`public_ipv6`, `verify_tls`, `php_versions`.

| Contract | Method | ISPConfig functions |
| --- | --- | --- |
| InfrastructureProvider (web) | `create` | `client_add` (one client per organization, deterministic username `onh_<org>` — `onh_` + 20 characters of the organization id, `IspConfigWebProvider.php:1148`; its limits are what the **organization** holds on that panel, `ClientAllowance` → spec key `client_entitlements`, and an existing client is brought up to them through `client_get` + merged `client_update`, TASK-0008), `sites_web_domain_add` (domain, php, quota, ssl/letsencrypt), `sites_database_user_add` + `sites_database_add`, `sites_ftp_user_add`. A site or mail domain already on the server is ours only when the panel says so (the organization's `onh_…` client owns it); anything else is refused with `CONFLICT` before a single write (historical resources, TASK-0014, owner rule of 2026-09-24; taking one over only via `docs/runbooks/historical-site-import.md`) |
| | `resize` | `sites_web_domain_update` (`hd_quota`, `pm_max_children` — one PHP-FPM pool per site, so dedicated PHP workers are real here — `backup_copies`), after the client's limits (`client_update` with the organization's `client_entitlements`, so a plan change of one service no longer rewrites the limits of the customer's other sites) |
| | `suspend` / `resume` | `sites_web_domain_update` `active = n|y`; the suspension depth (cron jobs, FTP accounts) is described in `docs/runbooks/web-tools.md` (*A suspended site is more than a stopped vhost*) |
| | `destroy` (`terminate`) | the site is proven first (domain and site user, `CONFLICT` otherwise); `dropSiteChildren()` then removes what hangs off it by `parent_domain_id` — further host names, databases, FTP accounts, shell users, cron jobs, and last the database logins that no longer own a database (collected before their databases, since they are found through them) — and only then `sites_web_domain_delete`. A refusal does not stop the termination; it comes back as `leftover` and reaches the operators as `service.purge.leftover` (TASK-0011; before, only the vhost was deleted) |
| Ownership of ids | every id-taking method | an id a customer sends is resolved against the site's own listing (`ownRow()`), mail through the service's own mail domains; a stranger's id is refused (TASK-0005, TASK-0016, `docs/runbooks/security-boundaries.md` §19) |
| Mail | `createMailDomain` / `createMailbox` | `mail_domain_add`, `mail_user_add` (with `backup_interval=daily`/`backup_copies` only when the platform passes `backup_copies`), `mail_user_update` quotas |
| Mailbox backup retention (TASK-0024, rule `mail.backup_retention`, default off) | `mailboxBackupRetention` / `setMailboxBackupRetention` (`MailboxBackupRetention`, trait `IspConfigMailBackups`) | proof: `client_get` (`onh_…`), `client_get_groupid`, `mail_domain_get` (`sys_groupid`, domain); `mail_user_get` (`%@domain`, per mailbox `sys_groupid` + suffix); write: merged `mail_user_update` with `backup_interval=daily`, `backup_copies` 1–365 — contract `tests/Contract/IspConfigMailBackupRetentionContractTest.php` |
| Usage | `usage()` | `sites_web_domain_get` + `client_get_sites_by_user` traffic |

Async: ISPConfig applies changes through the server job queue (the server's cron, once a minute by default). The
adapter returns an `AsyncHandle` that carries the change-log row of its own write where the connector can name it
(`DATALOG_TABLES`), and `awaitStatus` follows **that `sys_datalog` row**: `status = error` fails the operation with the
server's message, `ok` completes it at once however many foreign jobs wait. This is on only for a panel whose nightly
probe recorded a `status` field (`prereqs.probes.datalog_fields`, `onhost:nodes:check`); otherwise, and for a write
the connector cannot name, it falls back to `monitor_jobqueue_count` until the server's queue is empty (audit §7 row 2,
`IspConfigWebProvider::awaitStatus()`/`datalogRow()`). Spec keys: `domain`, `php_version`, `entitlements` (quota_mb, traffic_gb, mailboxes),
`ssl`, `ipv4`/`ipv6`.

Errors: `remote_fault` with "already exists" → `CONFLICT` (existing); permission errors → `AUTH`;
connection/timeouts → `TRANSIENT`. Credentials for the customer (FTP/DB) are returned once in the
provisioning result and delivered via the `service-activated` mail; they are never stored in plain text.

## Web & mail toolkit (`IspConfigTools`, `IspConfigMailTools`)

Node access is a jailed **agent shell user** per site (`<prefix>ag`, `sites_shell_user_add`, chroot from the
`agent_chroot` option, default jailkit) sharing the web user's uid; the platform's Ed25519 key for the instance lives at
`db://provider_instances/<key>/node-shell` and is created on first use. Commands run over SSH (`SshShell`), files over
SFTP (`SftpTransport`, rooted at `<home>/web`); `ensureAgent()` is asynchronous (job queue) and the toolkit answers
"prepared, run again" on first use. Remote-API pieces: `custom_php_ini` per site (editable keys from
`SecurityRules::PHP_EDITABLE`), `nginx_directives`/`apache_directives` managed block, `sites_cron_update`,
`sites_database_update` (`remote_access`, `remote_ips`), `quota_get_by_user`/`trafficquota_get_by_user`,
`databasequota_get_by_user` (TASK-0023 web-disk-total, `DatabaseSizeCapable::databaseSizes()`: sizes per client from the
server monitor's `database_size` data, narrowed to the databases `sites_database_get` lists under the site's own
`parent_domain_id`, `used_raw` before `used`, a database not yet measured is null; the remote user needs the function
group that grants it — unverified on the live panels, a refusal leaves the plan total `partial`; not called at all until
`ONHOST_WEB_DISK_TOTAL_DATABASE_SIZES=true`, and a site whose remote id is not a positive domain id or a row without its
own `parent_domain_id` is never counted),
`sites_web_domain_backup` (`primary_id` = the **backup's** id, `backup_download` / `backup_restore`, only ids from the site's own list — docs/runbooks/backups.md; the panel has no "back up now", `siteFeatures()['backup_on_demand']` is false) downloads, `client_login_get` for the staff panel link, and the mail functions
`mail_forward_*`, `mail_catchall_*`, `mail_user_get/update` (autoresponder — merged into the stored record, so the backup fields survive), `mail_policy_get`,
`mail_spamfilter_user_*`, `mail_spamfilter_whitelist/blacklist_*`, `mail_user_filter_*`, `mail_mailinglist_*`,
`mail_fetchmail_*`, `mail_user_backup*`, `mailquota_get_by_user`. Instance options: `agent_chroot`, `webmail_url`,
`deploy_strategy` (`symlink` | `rsync`), `redis_host`/`redis_port`. The remote user needs the corresponding function
groups (313 functions granted on the live instance). Contract: `tests/Contract/IspConfigToolsContractTest.php`.
