# ISPConfig 3.3

**Files:** `providers/IspConfig/*` · **Contract test:** `tests/Contract/IspConfigContractTest.php`

Remote API `remote/json.php` with a session per call (`login` → `session_id` → `logout`). Remote user and
password come from `env://ISPCONFIG_<KEY>`; options: `server_id`, `mail_server_id`, `public_ipv4`,
`public_ipv6`, `verify_tls`, `php_versions`.

| Contract | Method | ISPConfig functions |
| --- | --- | --- |
| InfrastructureProvider (web) | `create` | `client_add` (one client per organization, deterministic username `onhost-<org>`), `sites_web_domain_add` (domain, php, quota, ssl/letsencrypt), `sites_database_user_add` + `sites_database_add`, `sites_ftp_user_add` |
| | `resize` | `sites_web_domain_update` (quota, traffic, php version) |
| | `suspend` / `resume` | `sites_web_domain_update` `active = n|y` |
| | `destroy` | `sites_web_domain_delete` + database/ftp cleanup |
| Mail | `createMailDomain` / `createMailbox` | `mail_domain_add`, `mail_user_add` (with `backup_interval=daily`/`backup_copies` only when the platform passes `backup_copies`), `mail_user_update` quotas |
| Mailbox backup retention | `mailboxBackupRetention` / `setMailboxBackupRetention` (`MailboxBackupRetention`, trait `IspConfigMailBackups`) | proof: `client_get` (`onh_…`), `client_get_groupid`, `mail_domain_get` (`sys_groupid`, domain); `mail_user_get` (`%@domain`, per mailbox `sys_groupid` + suffix); write: merged `mail_user_update` with `backup_interval=daily`, `backup_copies` 1–365 — contract `tests/Contract/IspConfigMailBackupRetentionContractTest.php` |
| Usage | `usage()` | `sites_web_domain_get` + `client_get_sites_by_user` traffic |

Async: ISPConfig applies changes through the server job queue; the adapter returns an `AsyncHandle` with the
datalog/job id and `awaitStatus` polls `server_get_serverid_by_ip` / `monitor_jobqueue_count` until the queue
for that server is empty. Spec keys: `domain`, `php_version`, `entitlements` (quota_mb, traffic_gb, mailboxes),
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
`sites_web_domain_backup` (`primary_id` = the **backup's** id, `backup_download` / `backup_restore`, only ids from the site's own list — docs/runbooks/backups.md; the panel has no "back up now", `siteFeatures()['backup_on_demand']` is false) downloads, `client_login_get` for the staff panel link, and the mail functions
`mail_forward_*`, `mail_catchall_*`, `mail_user_get/update` (autoresponder — merged into the stored record, so the backup fields survive), `mail_policy_get`,
`mail_spamfilter_user_*`, `mail_spamfilter_whitelist/blacklist_*`, `mail_user_filter_*`, `mail_mailinglist_*`,
`mail_fetchmail_*`, `mail_user_backup*`, `mailquota_get_by_user`. Instance options: `agent_chroot`, `webmail_url`,
`deploy_strategy` (`symlink` | `rsync`), `redis_host`/`redis_port`. The remote user needs the corresponding function
groups (313 functions granted on the live instance). Contract: `tests/Contract/IspConfigToolsContractTest.php`.
