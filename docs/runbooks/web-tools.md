# Web toolkit — terminal, deploy, staging, WordPress, certificates, CDN, import, monitoring, backups

The hosting panels behind a web service (aaPanel, ISPConfig — never named to the customer) provide sites,
databases, FTP, cron, SSL and backups. The web toolkit adds what a modern hosting panel offers on top of
them, implemented once on the platform so both executors behave the same. Everything is feature-gated
(`GET /v1/services/{id}/features`), audited (operations) and available in the client panel under the
service workbench (`apps/surfaces/api/onhost-panel-tools.api.js`, seam #31).

## How the platform reaches the node

| Executor | Shell | Files | Notes |
| --- | --- | --- | --- |
| aaPanel | panel API `files?action=ExecShell` wrapped in a script that records exit code and output (`AaPanelShell`), commands switched to the site user `www`; optional SSH (`shell: ssh` instance option with `ssh_host`, `ssh_port`, `ssh_user` and the `ssh_private_key` credential) | panel file API (`AaPanelTransport`), downloads chunked through `GetFileBody` | root API; no per-site agent needed |
| ISPConfig | SSH as a jailed **agent shell user** per site (`<prefix>ag`, jailkit chroot, Ed25519 key of the instance stored at `db://provider_instances/<key>/node-shell`) created by `ensureAgent()` on first use | SFTP with the same identity (`SftpTransport`) rooted at `<home>/web` | jail must contain php, mysql, mysqldump, tar, rsync, git, composer (`jailkit_chroot_app_programs`); WP-CLI is downloaded into `private/` |

`WebToolsProvider` (both adapters) exposes shell, transport, PHP settings, security rules, HTTP/3, cron
edit/run/logs, database export/import/remote access, backup download/delete, quotas, Node projects and the
staff panel login link. `MailToolsProvider` (ISPConfig) exposes forwards, catch-all, autoresponder, spam
policies, white/blacklists, filters, mailing lists, fetchmail, mailbox backups, usage and webmail.

## Instance options (Správa → Integrace → instance)

| Option | Executor | Meaning |
| --- | --- | --- |
| `shell` | aaPanel | `api` (default) or `ssh` |
| `ssh_host`, `ssh_port`, `ssh_user` | aaPanel | SSH endpoint when `shell: ssh`; credential `ssh_private_key` |
| `agent_chroot` | ISPConfig | chroot type for agent users (`jailkit` default, `none`) |
| `webmail_url` | ISPConfig | webmail link shown to customers |
| `phpmyadmin_url` | both | database admin link (site settings) |
| `deploy_strategy` | ISPConfig | `symlink` (default: `web` → release) or `rsync` (copy into `web/`) |
| `redis_host`, `redis_port` | both | Redis for the WordPress object cache (default 127.0.0.1:6379) |

## Features and where they live

| Feature | Actions / endpoints | Implementation |
| --- | --- | --- |
| Terminal | `command.run` (command, cwd, timeout) — result `output`, `exit_code` in the operation | `CommandRunner::guard` allow-list, no sudo/daemons; output through `Presenters::customerResult` |
| PHP settings | `php.settings` (settings{}) ; resource `php_settings` | aaPanel `.user.ini`, ISPConfig `custom_php_ini`; keys `SecurityRules::PHP_EDITABLE`; `memory_limit` capped by `php_memory_mb` |
| Security / WAF-lite | `security.set` (rules) ; resource `security` | managed block in the vhost (`SecurityRules`): IP deny/allow, bad bots, hotlink, headers, HSTS, nginx rate limits |
| HTTP/3 | `http3.set` ; resource `http_versions` | aaPanel nginx with `http_v3_module`; ISPConfig reports only |
| Cron | `cron.update`, `cron.run` ; resource `cron_logs` | aaPanel `modify_crond` / `StartTask` / `GetLogs`; ISPConfig `sites_cron_update`, run through the agent |
| Databases | `database.export` (→ `download_token`), `database.import` (upload_id), `database.access` | aaPanel `ToBackup`/`InputSql`/`SetDatabaseAccess`; ISPConfig mysqldump/mysql in the jail with remembered credentials (`DatabaseCredentials`, secret store) |
| Files | `file.rename`, `file.copy`, `file.chmod`, `file.archive`, `file.extract`, `POST …/files/upload` | transports |
| Backups | `GET …/backups/{id}/download`, `backup.delete`, `PUT …/backups/schedule` | `BackupScheduler` (`onhost:backups:run` every 15 min): frequency/days/generations from the plan (`backup_frequency`, `backup_days`, `backup_generations`), off-site copy to `ONHOST_BACKUP_OFFSITE_DISK` |
| Staging | `staging.create/refresh/push/delete` ; resource `staging` | `StagingService` + `StagingWorkflow`: second service on the same server (`<site>-staging.<ONHOST_STAGING_SUFFIX>`, tag `billing: included`, never invoiced), rsync on one aaPanel node or tar relay through the control plane, database copy, WordPress search-replace; push takes a backup first |
| Git deploy | `GET/PUT/DELETE …/deploy`, `POST …/deploy/rotate-secret`, `deploy.run`, `deploy.rollback`, public `POST /v1/hooks/deploy/{source}` | `DeployService` + `DeployWorkflow`: Ed25519 deploy key per site, HMAC webhook (GitHub `X-Hub-Signature-256`, GitLab token), releases under `.onhost/releases`, run-path switch (aaPanel) or `web` symlink (ISPConfig), build command + hooks guarded, keep N releases |
| WordPress | `wp.update` (what, staged), `wp.cache` (enabled), `wp.plugin` (slug, op) ; resource `wordpress` | WP-CLI through the shell (`WordPressService`); staged updates refresh staging, update there, health-check, then production; Redis object cache via the `redis-cache` plugin |
| Monitoring | `GET/PUT …/monitoring`, `DELETE …/monitoring/{id}` | `UptimeMonitor` (`onhost:monitoring:check` every minute): HTTP checks, keyword, N failures → `monitoring.down` (mail `site-down`), recovery → `monitoring.up` |
| Wildcard SSL | `ssl.wildcard` (domain) ; resource `certificates` | `AcmeClient` + `CertificateWorkflow`: DNS-01 through the platform DNS (`_acme-challenge` TXT), key material in the secret store, installed with `uploadCertificate`, renewed by `onhost:certificates:renew` |
| CDN | `cdn.enable/disable/purge` (settings) ; resource `cdn` | `CdnService` + `CloudflareCdnProvider` (`ONHOST_CDN_CLOUDFLARE_SECRET_REF` → secret with `token`, `account_id`): zone per apex, records mirrored from the platform DNS (web hosts proxied), settings, purge, nameserver switch when the domain is ours; `onhost:cdn:refresh` hourly |
| Import | `import.run` (kind cpanel/plesk/url/upload, source, files, databases, subdir) ; resource `imports` | `ImportService` + `ImportWorkflow`: unpack on the control plane, detect document root and SQL dumps, upload through the transport, create databases, re-point WordPress |
| Node.js projects | `node.create`, `node.action` ; resource `node_projects` | aaPanel Node project API |
| Staff panel login | staff `GET /v1/staff/services/{id}/panel-login` | ISPConfig `client_login_get` (permission `staff.console`, audited) |

## Executors on the nodes (what a live check taught us)

* **aaPanel**: the node hardening (`/etc/ld.so.preload` → `libusranalyse`) stops the `www` user from executing
  any binary (exit 126). Every site therefore gets its own agent user `<prefix>ag` (`AaPanelTools::ensureAgent`:
  `useradd -M -d <site root> -s /bin/bash -G www`, `setfacl` rwx + default ACLs for the agent and `www` on the
  site root). The terminal, WP-CLI, git deploys, imports and staging copies run as that user with the site's PHP
  first on `PATH`; the panel API (files, zip, cron, PHP settings, backups) is used where it exists. `tools.agent`
  is `ready` / `preparing` / `unavailable`; the panel shows a "preparing" state instead of an error.
* **ISPConfig**: the agent is a jailed shell user (`sites_shell_user_add`, strong password, our key in
  `authorized_keys`); SFTP root is `web/`, the document root inside the jail differs from the panel path
  (`IspConfigTools::agentDocroot`). The jail template ships tar, rsync, git, mysql, mysqldump, curl, wget, unzip
  but **no php/composer** — add them to the jailkit `php` section on the node before WP-CLI or Composer can run.
  `sites_cron_get` returns an empty list on the tested server and `sites_cron_delete` fails with an SQL error
  (server-side fault in the remote API): cron rows created through the platform are listed from the platform's own
  records and deletion is retried by the reconciler.
* Both: `php.settings`, `security.set` and `cron.create` on ISPConfig wait for the job queue (40–70 s); aaPanel
  answers in seconds.
* **Staging** (verified live on both nodes): the copy is a service of its own (`billing: included`) provisioned like
  any site. On aaPanel the copy's site creation can take the panel more than 30 s (writes now wait up to 120 s) and the
  HTTP-01 certificate fails until the staging hostname resolves — the site is created anyway with
  `certificate: pending_dns` and the certificate is retried by `ssl.issue`; the copy of files runs as root over
  rsync and skips the immutable `.user.ini`. On ISPConfig the copy's jailed shell user arrives through the job
  queue: `StagingService::sync` prepares the agent and the operation retries every minute until SSH accepts it
  (`SshShell::$preparingUntil`); `tar` warnings ("file changed as we read it") do not fail the pack. A push is
  refused until the copy has been refreshed at least once, so an empty copy cannot overwrite production;
  `staging.delete` terminates the copy and marks an unprovisioned one terminated without touching the node.

## DNS for hosting subdomains (platform zones)

`<label>.web.onhost.cz` lives in a zone the operator already runs (`onhost.cz` at WEDOS). The platform adopts such a
zone once — `php artisan onhost:dns:adopt onhost.cz --provider=wedos_zone --instance=wedos-zone` — into its own
organization (`ONHOST_PLATFORM_ORGANIZATION_SLUG`), imports the existing rows as customer-managed (the wildcard, MX, …
are never touched) and from then on site provisioning writes the hostname's A/AAAA rows there (owner `service:<id>`,
`DnsService::syncHostname`), termination removes them (`ServiceActionWorkflow::platformDnsStep`). `ONHOST_PLATFORM_ZONES`
lists the zones (default `onhost.cz`); `php artisan onhost:dns:platform-sync [--dry-run]` backfills the rows of every
active site (run it once after adopting). Node addresses come from `nodes.tags.public_ipv4/6` (or the instance option).
Prerequisite at WEDOS: the WAPI must allow the control plane's IPv4 (the 2026-09-13 run from 193.179.119.167 was
refused with `2051 Access not allowed from this IP address`); the `wedos-zone` instance shares the `wedos-main` WAPI
credentials. Until the rows exist, the WEDOS wildcard `*.web.onhost.cz` sends every hosting subdomain to s2
(161.97.119.173) — sites on other nodes (cz1) answer that node's default page.

## Discord control, action hooks and the service agent

* **Discord** (`domains/Integrations/DiscordService`): one application-level slash command `/onhost` with
  subcommands `link`, `unlink`, `services`, `status`, `backup`, `restart`, `deploy`, `ask`. Interactions arrive
  on `POST /v1/integrations/discord/interactions`, signed with Ed25519 (`ONHOST_DISCORD_PUBLIC_KEY`); replies
  are ephemeral. A customer links the Discord account in the panel (Účet a správa → API klíče a webhooky →
  Discord → *Propojit Discord účet*): the panel issues a 15-minute code, `/onhost link KÓD` binds the Discord
  user to the signed-in user (`discord_links`, one active link per Discord user). Actions never run from the
  command alone: the bot answers with a confirmation button (`custom_id act:<proposal>`, 15 minutes in cache);
  the click dispatches `ServiceService::requestAction` as the linked user with an idempotency key, the usual
  entitlement checks and the audit trail. Terminate, suspend, resume, resize and restore are never offered from
  Discord; 40 interactions per 10 minutes per Discord user.
  Setup: create the application in the Discord developer portal, copy *Application ID* and *Public Key* into
  `ONHOST_DISCORD_APPLICATION_ID` / `ONHOST_DISCORD_PUBLIC_KEY`, put the bot token into the secret named by
  `ONHOST_DISCORD_BOT_SECRET_REF` (`env://DISCORD_BOT` → `DISCORD_BOT_TOKEN`), set the *Interactions Endpoint
  URL* to `https://<portal>/v1/integrations/discord/interactions`, run `php artisan onhost:discord:register-commands`
  and invite the bot with the URL the panel shows (`applications.commands` scope).
* **Discord channel notifications**: a webhook whose URL is a Discord channel webhook receives the platform's
  events as embeds (`DiscordMessage`) instead of the signed JSON body.
* **Action hooks** (`POST /v1/hooks/run/{token}`): a customer creates a hook for one predefined action on one
  service (backup, restart, deploy, cache purge, …) from the workbench hub (*Akční webhooky*); the token is shown
  once, its hash is stored (`action_hooks`). A call runs the action as the creating user with a 10-second
  idempotency window, so a bursting caller triggers one operation. Throttled like authentication.
* **Service agent in the chat** (`domains/Support/Assistant/ServiceIntent`): messages such as „zálohuj
  688mr1zw" or „přepni web na PHP 8.3" are matched by deterministic rules against the organization's services,
  their features and allowed actions; the assistant answers with `service_action` proposals the customer confirms
  in the chat (chip → confirmation → `POST /v1/services/{id}/actions`). With an LLM configured the model gets the
  tools `list_services`, `get_service_status` and `propose_service_action`; it can only propose, never execute.
  Topic `sprava` in triage.

## Speed of the customer journey

Events published in a request (order paid, payment matched, ticket reply, top-up) are relayed by a worker within
seconds: `OutboxPublisher::publish` dispatches one debounced `RelayOutboxJob` per burst after the transaction commits
(`ONHOST_OUTBOX_EAGER`, default on; the test suite relays explicitly). A queued transactional mail is sent by
`SendMailOutboxJob` on the `mails` queue as soon as it is queued. `onhost:outbox:relay` and `onhost:mail:send` keep
running every minute as the safety net (retries, mails scheduled for later, a worker that was down). Payments and
top-ups mail the customer (`payment-received`, `wallet-topup`) besides the in-app notification.

What one bank payment of an order mails (verified 2026-09-13 with a guest order): *Objednávka přijata*, *Doklad PF*
(the proforma), *Vítejte* (the new account), then on matching *Platba přijata* (from `payment.succeeded` with the order
number) and *Služba je aktivní* once the site exists — five mails, sent within two seconds of each event. The receipt
(PP) and the statement (VY) are not mailed as "Doklad" (they are in the panel); a paid postpaid invoice still mails
*Platba přijata*. Money that settles an order at once (`wallet.topup.completed` with `purpose: order`) is not announced
as a top-up — a standalone top-up mails *Kredit dobit* only.

## Credit forecast (prediction)

`WalletForecast` walks the active subscriptions of an organization against the available credit — gross amounts
with the customer's VAT (`TaxEngine`), yearly plans at their full renewal — and answers the day the credit stops
covering a renewal (`depletes_at`, `days`), the monthly burn, the renewals and the shortfall of the next 30 days and
the next renewal. `GET /v1/wallet` returns it as `data.forecast`; `onhost:billing:runway` (daily 07:30) publishes
`wallet.runway.low` once per organization and day when the horizon is 14 days or less and no automatic top-up is
armed — the customer gets a notification and the `wallet-runway` mail.

## Certificates for sites that were provisioned before their DNS

A site whose name did not resolve to the node at provisioning keeps `tags.access.certificate = pending_dns` (the
ACME challenge cannot pass). `onhost:certificates:issue-pending` (every 15 min, `CertificateAutoIssuer`) resolves the
names of such sites; once one answers with the node's public address (`Node.tags.public_ipv4` or the instance option)
the certificate is requested through the ordinary `ssl.issue` action under the system context, the tag moves to
`requested` and then `issued` when the workflow returns. Nothing is retried while the name still points elsewhere.

## Projects (partitioning and management)

Every organization has projects (`Default` is created with it). `GET/PATCH /v1/organizations/{org}/projects[/{id}]`,
`…/archive`, `…/restore`, `…/members` (`POST` with `user_id` or `email` + `role`, `DELETE …/members/{user}`) and
`POST /v1/services/{service}/project` (`project_id` or `null`) manage them; all writes are `OrganizationCommand`
ops handled by `ProjectService`, permission `project.manage`. A project role is a customer role (developer, cloud
operator, …) bound at project scope on top of the member's organization role: `CommandScope::resource()` carries the
service's project, so the role covers the services of that project and nothing else (`Authorizer::bindingCovers`,
`ServiceActionCommand::scope()`). The project list reports spend per project — the gross monthly renewal cost of the
active subscriptions behind its services, yearly plans spread over twelve months, the share of the total and the
project budget when one exists — plus the "Nezařazeno" bucket. Archiving needs the project empty; the last active
project stays.

## Connected registrar accounts (bring your own WEDOS API)

A customer connects their own WEDOS account in *Nastavení → Připojené registrátory* (`POST /v1/registrar-connections`,
HIGH risk → fresh step-up): the WAPI password goes into the secret store as `db://registrar-connections/<id>` and never
returns to a browser or a log; the connection owns two customer-scoped provider instances (`wedos-c-…` registrar,
`wedos-zone-c-…` DNS, column `provider_instances.organization_id`). `ProviderInstance::scopePlatform()` keeps them out
of every platform lookup (`ProviderRegistry::findInstance`, `RegistrarClient::instances`, pricing, placement, doctor,
the staff integrations list). The connect call probes the account (domain list + credit); wrong credentials leave
nothing behind. `RegistrarConnectionService::sync` — on connect, hourly (`onhost:registrars:sync-connections`, 40 min
past, only when `auto_sync` is on) or on demand — mirrors the domains into `domains` (`meta.source = connection`,
`registrar_provider = wedos`, `auto_renew` false: the platform never renews at the customer's registrar), adopts the
zones the account hosts (`dns_zones.provider = wedos_zone` on the customer instance, existing rows imported as
customer-managed, 25 zone checks and 10 refreshes per run), flags domains that vanished (`meta.missing_since`,
`domain.connection.missing`), publishes `domain.external_expiry_notice` 30/14/7/3/1 days ahead (mail
`domain-external-expiry`, once per threshold, the most urgent implies the coarser ones) and `domain.expired`, and
watches the credit (`registrar.connection.credit_low` under `settings.credit_threshold_minor`, default 200 CZK, once
a day, re-armed above the limit, mail `registrar-credit-low`). Every run lands in `runs` (last 20, shown in the
panel) and in the audit trail (`GET …/history`). Disconnecting deletes the secret, the instances and the mirrored
domains and zones (soft-deleted; a reconnect restores them) — the registrar account itself is never touched: nothing
in the platform deletes a zone or a domain at WEDOS. Staff see every connection under `GET /v1/staff/registrar-connections`
and can pause one (`…/disable` with a reason, `…/enable`, permission `domain.registrar.manage`); a paused connection
refuses syncs and pairing.

**Pairing a domain with a hosting plan** (`POST /v1/domains/{domain}/pair {service_id}`, `DomainPairingService`): the
site learns the domain and its `www` (WebHostingProvider::addSubdomain — aaPanel AddDomain, ISPConfig alias domain),
the zone gets `A @` and `A www` (and AAAA when the node has one) pointing at `Node.tags.public_ipv4` — written straight
into the mirrored WEDOS zone (the customer's old A/AAAA/CNAME rows for `@`/`www` are dropped first, everything else
stays) or, when the DNS runs elsewhere, returned as instructions (`dns: manual`) — the service remembers the domain in
`desired_spec.extra_domains` and its certificate goes back to `pending_dns`, so `CertificateAutoIssuer` requests
`ssl.issue` for every name (site name + paired domains, with `www` when it resolves too) as soon as it answers with the
node. `…/unpair` removes the aliases and the rows the pairing added. WAPI note: zone rows use the label relative to the
zone (`''` for the apex, `www`), validated separately from domain names in `WapiGateway`.

## Projects page (seam #35) and registrars page (seam #36)

Both are settings pages the prototype does not have, rendered through its generic view (`sets.projects`,
`sets.registrars`, deep links `/panel/projekty`, `/panel/registratori`, sidebar links `projects`/`registrars` in
`PanelNavigation::LINKS`, quick-select actions). The WAPI password field uses the generic form's `password` kind
(account seam #27: `type="password"`, `autocomplete="new-password"`). Modules: `apps/surfaces/api/onhost-panel-projects.api.js`, `onhost-panel-registrars.api.js`.

## Plan changes (upgrade / downgrade)

`GET /v1/services/{id}/plans` lists the plans of the service's product priced per its billing period with the
pro-rated cost of switching now (`PlanChangeService::options`); the panel shows them in the site's *Kvóty* tool (web)
and the *Výkon a tarif* tab (game). Switching is an ordinary order: the cart line carries `config.upgrade_of` (the
service id), `QuoteService` prices the difference between the new and the current renewal amount for the rest of the
period (`prorationFraction`), an upgrade is paid from credit, by transfer or by card like any order, a downgrade is a
zero-total order settled at once. `FulfillPaidOrder` hands such lines to `PlanChangeService::apply`: the `resize`
action moves the node (aaPanel PHP workers, ISPConfig quota + workers, Proxmox/Pterodactyl resources), the service
gets the new plan version, `SubscriptionService::changePlan` renews at the new price from the next period, and
`service.plan_changed` tells the customer (`service-plan-changed` mail). The same plan, a service of another
organization, a guest quote and a non-active service are refused (`plan_change_*`).

**Billing period change (2026-09-13).** The same endpoint lists `periods` — the current plan sold monthly and yearly
(`price`, `saving_per_year`, `change_now`, `unused_credit`, `period_end_after`). A cart line with `config.upgrade_of`
and an explicit `period` other than the subscription's is a *period change*, allowed on the plan the service already
runs: it starts a **new period today** and is priced as the new period's list price minus the unused rest of the
current period (`old amount × remaining fraction`, `plan_change.unused_credit_minor`); the line is named *Změna
období: … (ročně)*. `PlanChangeService::apply` skips the node `resize` when the plan stays the same, and
`SubscriptionService::changePlan` restarts `current_period_start/end`, `next_renewal_at` (minus the renewal lead) and
`last_renewed_at`. The customer gets *Období platby služby … změněno na roční* (mail `service-period-changed`). A plan
change without an explicit period keeps the subscription's period as before. The panel's *Kvóty* / *Výkon a tarif*
tab shows both periods with *Platit ročně* / *Platit měsíčně* (`onhost-panel-tools.api.js`, `changePlan` with a
period row). Switching back from yearly to monthly usually costs nothing now (a whole unused year outweighs one month);
the unused credit is not refunded, it is consumed by the new period. Product changes (web → managed) remain a new
order plus a termination. `tests/Feature/Orders/PlanChangeTest.php`.

## Service summary (what the customer sees first)

`GET /v1/services/{id}` carries `summary` (`ServiceSummary`): the plan (key, name, SLA class), the billing (period,
amount, current period end, next renewal or the end when cancelled, auto-renew, renewal failures), the project, the
location (region + zone — never the node or the panel), the certificate state (`tags.access.certificate`), the last
available backup and the count, the uptime monitor (state, last status, latency) and the operations in flight plus
the last failure. `GET /v1/services` rows carry the `plan` + `billing` part, and the panel's service rows say
*hostname · CZ1 · Standard · ročně · obnova 13. 9. 2027* (`SurfaceDataController` services block, fields `plan`,
`period`, `renews_at`). The workbench's first tab (*Doména a nastavení*) shows the summary under the domain and PHP
rows (`summaryPairs` in `onhost-panel-workbench.api.js`; `forget()` drops the cached summary after an action).
`tests/Feature/Http/ServiceSummaryTest.php`.

## Reverse proxy and default documents

aaPanel sites forward a path to an upstream (`proxy.create {name, target, path, cache, host}` → `CreateProxy`,
`proxy.delete`, listing `resources/proxies`) and choose their index order (`index.set {names}` → `SetIndex`,
`resources/default_docs`). ISPConfig has no API for either, so since 2026-09-13 the platform keeps both as a **managed
block inside the site's web server directives** (`providers/Shell/ManagedDirectives.php`, markers
`# ONHOST-TOOLS-BEGIN {json}` … `# ONHOST-TOOLS-END`, the state travels as JSON on the marker line): Apache gets
`DirectoryIndex …`, `ProxyPreserveHost On`, `ProxyPass /path/ http://upstream/` + `ProxyPassReverse`; nginx gets
`index …;` and a `location /path/ { proxy_pass …; proxy_set_header … }` block. The block lives next to the customer's
own directives and the security block (`SecurityRules`); `siteSettings().directives` shows the customer's part only and
`directives.set` writes it back around both managed blocks. `webServerType()` (cached `server_get`) picks the field.
Apache nodes need `mod_proxy`/`mod_proxy_http` enabled (`a2enmod proxy proxy_http`) — without it the vhost fails to
load, so check `apache2ctl -M | grep proxy` on a node before offering proxies there; ISPConfig applies the change on
its next job run (`jobqueue` handle). The `cache` flag is stored but has no effect on ISPConfig (no proxy_cache zone
in the vhost template). **Verified live on s2 (2026-09-13)**: `DirectoryIndex` and `ProxyPass` both took effect within
one ISPConfig job run (a non-existent index → 403, a proxy to a closed port → 503, so `mod_proxy` is on). Two rules
came out of it: the managed `DirectoryIndex`/`index` line always ends with `standard_index.html` (ISPConfig's own
template puts its welcome page there; without it an empty docroot answers 403), and `index.set` with an empty list
means "the web server's default order" — the block is removed on ISPConfig and aaPanel gets its default list — while a
list made only of bad names is refused. `tests/Contract/IspConfigToolsContractTest.php`,
`tests/Feature/Provisioning/WebProxyFeatureTest.php`; live check script pattern in the audit runbook §5c.

**Many proxies at once (audit §5d-4).** `proxies.set {items: [{name, target, path?, cache?, host?}]}` replaces the
whole list (`WebToolsProvider::setProxies`, at most 20, unique names, the `proxy.create` rules per item): ISPConfig
renders the block once and writes the vhost once whatever the count; aaPanel has no bulk call, so the adapter applies
the difference (removes what is gone, recreates what changed, adds what is new, leaves the rest untouched). An empty
list clears every proxy. Automation (API keys, action hooks) should use it instead of a `proxy.create` loop.

## The remaining panel areas (seam #37)

Notifications and audit, maintenance windows, costs and forecast, personal data and leaving, monitoring and backups
were narrated by the prototype; outside demo mode `api/onhost-panel-pages.api.js` renders them from the API
(`/v1/organizations/{id}/audit`, `/v1/notifications`, `/v1/calendar` + `/v1/status`, `/v1/wallet` + `/v1/subscriptions`
+ project spend, `/v1/data-requests`, and the organization-wide `GET /v1/monitors` and `GET /v1/backups` of
`InsightsController`). Staff switch each page off per link in *Navigace klientského panelu*. Mirrored domains (a
connected registrar account) refuse renew, auto-renew, nameserver and DNS-delegation actions with `domain_external`
— those happen at the registrar; pairing with a hosting plan is the platform's job. Staff see every connected account
on *Nastavení systému → Integrace → Zákaznická připojení registrátorů* (sync, pause with a reason, resume) and in the
customer detail (`registrar_connections`); Discord/Slack/Teams get their own wording for `registrar.connection.*`,
`domain.paired`/`unpaired` and `domain.external_expiry_notice`; the ICS calendar marks such expiries as "at the
connected account".

## Calendar (ICS feed)

`GET /v1/calendar` lists the organization's dates for the next 180 days (`days`, `project_id`): renewals with the
gross price, domain expiries (with the auto-renew hint), invoice and proforma due dates, planned maintenance touching
the organization's services and the credit-depletion day from the forecast. `GET /v1/calendar/feed` returns a signed
link (`/calendar/{org}.ics?v=…&signature=…`, `CalendarFeedController`, middleware `signed`) any calendar client
subscribes to without a session; `POST /v1/calendar/feed/rotate` bumps the version stored in the organization
settings and every earlier link answers 404. Lines are folded at 75 octets (RFC 5545), times are UTC, all-day events
use `VALUE=DATE`.

## Chat webhooks (Slack, Teams, Discord)

A webhook endpoint whose URL is a Slack incoming webhook (`hooks.slack.com/services|workflows/…`) receives Block Kit,
a Microsoft Teams incoming webhook (`*.webhook.office.com`, `*.logic.azure.com`) a MessageCard, a Discord channel
webhook an embed; every other endpoint keeps the signed platform envelope (`ChatMessage::build`, wording shared through
`DiscordMessage::describe`). The `X-ONhost-Signature` header is sent in every case.

## Scheduler

`onhost:monitoring:check` (every minute), `onhost:backups:run` (every 15 min), `onhost:certificates:renew`
(daily 03:10), `onhost:certificates:issue-pending` (every 15 min), `onhost:cdn:refresh` (hourly),
`onhost:dns:platform-sync` (hourly at :25), `onhost:billing:runway` (daily 07:30), `onhost:web-tools:prune`
(hourly, removes staged uploads and downloads older than six hours), `onhost:monitoring:prune` (daily),
`onhost:commerce:prune` (daily 04:20, `CommerceHousekeeping`: open quotes whose validity ended more than 24 h ago and
that no order references, and open carts expired more than 7 days ago that were never converted — browsing with the
cart drawer open creates a quote per change and a server cart per visitor; `--quote-hours` / `--cart-days` tune it),
`onhost:services:usage-watch` (hourly at :50), `onhost:billing:renewal-guard` (daily 07:35),
`onhost:provisioning:board` (every 5 min, automatic drain/resume), `onhost:digest:weekly` (Mondays 07:00),
`onhost:digest:staff-daily` (daily 07:15), `onhost:nodes:check` (daily 05:20).

## Automation block (audit §5e, 2026-09-13)

Eight pieces that remove manual work across the client panel, the staff console and service management. Every
write goes through the command bus, every notification through the router (`docs/architecture/events-catalog.md`).

**Usage watch → one-click upgrade** (`UsageWatch`, `onhost:services:usage-watch` hourly at :50). Web/managed
services are measured through the `quotas` resource (disk, traffic; limits from the node or the plan's `nvme_gb` /
`quota_mb` / `traffic_gb`), VPS through `usage()` (disk, memory). `tags.usage` keeps the verdict (`level` ok / warn ≥ 85 % /
critical ≥ 95 %, per-metric `pct`). `service.usage.high` (once per level and day) tells the customer the next plan and
today's pro-rated price (`PlanChangeService::options`); mail `service-usage-high`. The panel rows say *kapacita 92 %
(prostor)* and turn amber; the workbench's first tab has *Využití tarifu* (→ *Zvýšit tarif* opens the plans) and
*Automatické navýšení tarifu* (policy switch, `PUT /v1/services/{id}/policy {auto_upgrade}`, op `policy.set`,
`tags.policy`). With the policy on, at critical the watch places the upgrade order itself
(`PlanChangeService::orderUpgrade`, credit only, source `auto`, at most once a day) and the notice names the order;
an empty wallet or a refusal is audited (`service.auto_upgrade refused`) and the customer only hears the warning.

**Renewal guard and automatic top-ups** (`WalletForecast::renewalGuard`, `onhost:billing:renewal-guard` daily 07:35).
Renewals due within 7 days are summed gross per organization and compared with the available credit; when short,
`AutoTopup::attempt` runs first (opt-in `PUT /v1/wallet/auto-topup {enabled, amount, threshold, max_per_day,
monthly_limit, payment_method_id}`, `AutoTopupCommand`, table `auto_topup_settings`; `GET /v1/wallet` → `auto_topup`),
then `billing.renewal.underfunded` (once a day) says how much is missing by when — or that the automatic top-up
happened. Charging needs a payment provider implementing `providers/Contracts/StoredMethodCharging` (it charges the
stored method inside `createPaymentIntent` when `method: stored` and `stored_method_id` arrive — `PaymentService`
forwards it); none is live yet (Comgate token later), so the setting reports `supported: false` and the customer is
asked to top up. The Fakturace ledger *Co se stane, když nezaplatíte* carries the policy row and its switch.

**Operations board** (`OperationsBoard`, `GET /v1/staff/provisioning/board`, Blade `/sprava/nastaveni/provoz`).
Stalled (WAITING with a retryable error or a second attempt), failed in 24 h and running > 10 min across tenants,
with retry/cancel (the existing job commands), plus every node with its 15-minute record (successes, transient
failures, instance health). `onhost:provisioning:board` (every 5 min) drains a node with ≥ 3 transient failures and
no success (`node.drained`, tag `auto_drain`, scheduler skips non-active nodes) and resumes it once operations succeed
again (`node.resumed`); staff drain/resume by hand with `POST /v1/staff/integrations/{instance}/nodes/{node}/state`
(op `node.state`, `provider.instance.manage`) — hand-set states are never resumed automatically.

**Onboarding checklist** (`ServiceSummary::checklist`, in `GET /v1/services/{id}` → `summary.checklist`). Own domain
(not a platform zone), HTTPS certificate issued, a verified backup, monitoring on — each with the tab and action that
completes it. The workbench's first tab lists them (✓/○, *Nastavit* opens the tab), the assistant's facts name what
an active site still lacks.

**Digests** (`DigestService`). `onhost:digest:weekly` (Mondays 07:00): per organization a notification (kind
`digest`, switchable) and mail `digest-weekly` — services and usage warnings, the next 14 days from the calendar
(renewals, expiries, due dates, credit depletion, maintenance), credit and monthly burn, backups of the week,
monitors, ticket activity. `onhost:digest:staff-daily` (07:15): internal feed + mail `digest-staff` to
`ONHOST_STAFF_DIGEST_TO` (comma-separated) — board counts, integrations down, draining nodes, backups, orders and
renewals, open dunning, plan capacity, new tickets.

**Declarative spec** (`ServiceSpecService`, `GET/PUT /v1/services/{id}/spec`, op `spec.apply`). One document per web
service: `php`, `proxies`, `index`, `redirect`, `security`, `cron`, `monitoring` (+ `features` = the sections the plan
offers). PUT takes any subset, compares with the node and dispatches only what differs (`php.set`, `proxies.set`,
`index.set`, `redirect.set`, `security.set`, `cron.create`/`cron.delete` by schedule+command, monitor configure);
unchanged sections are listed, sections the plan lacks are skipped, unknown ones refused (`spec_section_unknown`).
Idempotent: the same document twice does nothing — the Terraform-style counterpart of the workbench.

**Bulk staff actions** (`BulkActionService`, table `bulk_jobs`, `POST/GET /v1/staff/bulk-jobs[/{id}]`, Blade
`/sprava/nastaveni/hromadne-akce`, op `bulk.start`, `staff.service.manage`). A filter (instance, node, customer,
product, family or explicit service ids; active services only, ≤ 500) and one of the repeatable actions (`php.set`,
`security.set`, `ssl.issue`, `https.force`, `index.set`, `proxies.set`, `backup`, `http3.set`, `php.settings`,
`wp.update`) → one ordinary operation per service; the job reports per service (operation state or the refusal), the
page shows progress bars and details. Data-destroying actions stay per service.

**Node prerequisites** (`NodePrerequisites`, `onhost:nodes:check` daily 05:20, `POST
/v1/staff/integrations/{instance}/prerequisites`, button *Prerekvizity* on the integrations page). Records on the
instance (`capabilities.prereqs`): API up/version, PHP versions, cron API ok/broken (probed through the first bound
site), job-queue depth, `mod_proxy` from the instance option (`yes`/`no`/unknown), warnings. ISPConfig
`siteFeatures()` reads it: a broken cron API hides cron and cron editing, `mod_proxy=no` hides reverse proxies. Live
check of `ispconfig-s2` (2026-09-13): API up, cron API answers the listing (the earlier fault concerned `sites_cron_delete`
on probe rows), job queue 0, PHP 5.6–8.5; `mod_proxy` confirmed by the live proxy round trip and recorded as the
instance option `mod_proxy=yes`.

## Game servers (game panel · seam #39, 2026-09-13)

The game panel behind `game` services (Pterodactyl 1.11, adapter `providers/Pterodactyl/PterodactylGameProvider.php`)
now implements `GameToolsProvider` next to `GameProvider`: everything the panel offers around one server is a
customer feature, an audited action and a live listing — the customer never learns the vendor. Feature keys
(`ServiceFeatures`, family `game`): `game_status` (live state, CPU, RAM, disk, uptime), `startup` (egg variables the
customer may edit, the container image from the egg's image list), `game_settings` (rename, reinstall, the panel
account, SFTP details), `schedule_tools` (list, run now, enable/disable, delete on top of `schedules`),
`game_databases` (plan limit `databases`; passwords only with `?reveal=1`), `subusers` (plan limit `subusers`, presets
`console`/`files`/`full` from `GameToolsProvider::SUBUSER_PRESETS` — raw permission keys never come from the customer),
`game_files` (browse, edit ≤ 512 kB, mkdir, rename, delete, download through `GET …/files/download`), `allocations`
(plan limit `allocations`; add = the panel picks a free port, primary, remove), `backup_tools` (lock, delete, signed
download URL through `GET …/backups/{id}/download`), `panel_access` (`panel.password` — HIGH, step-up; refused for
panel administrators). Actions: `variable.set`, `image.set`, `rename` (also updates the service label), `reinstall`
(HIGH, step-up, `confirm=true`, async until the panel reports the install done), `schedule.delete/toggle/run`,
`gamedb.create/rotate/delete`, `subuser.create/delete`, `gfile.save/delete/mkdir/rename`, `allocation.add/primary/remove`,
`gbackup.delete/lock`, `panel.password`. Resources: `status`, `server_detail`, `startup`, `schedules`,
`game_databases`, `subusers`, `game_files?path=`, `allocations`, `panel_access`.

Workbench tabs (`onhost-panel-workbench.api.js`, prototype tab ids of the `mc`/`cs2` types): *Konzole* (status line +
power + command), *Startup a proměnné*, *Plánované úlohy*, *Nastavení služby* (name, address, SFTP, panel account
with *Nové heslo*, reinstall), *Správce souborů*, *Zálohy* (restore + lock/delete/download), *Databáze* (show/hide
passwords), *Síť, porty a doména*, *Spolupracovníci*, *Výkon a tarif*, *Monitoring* (usage), *Provoz a NOC*.

Control plane: `ProviderInstanceService::discoverNodes` imports panel nodes (`role=game`, `remote_id` = panel node id,
memory/disk as capacity, allocated resources as usage, maintenance mode as state); `NodePrerequisites::check` records
`client_api` (ok/missing/rejected — without it every server tool is hidden through `capabilities.prereqs.client_api`),
the nodes with their daemon status (one server per node is asked for its resources) and the template mapping
(`options.eggs` key → nest/egg validated against the panel; privileged eggs are refused). Staff console views
(seam #40): *Herní uzly*, *Šablony her* (map a catalogue key to a nest/egg, `PUT /v1/staff/integrations/{key}/game/eggs`),
*Alokace a porty* (create port ranges, `POST …/game/allocations`), *Provisioning fronta* (retry/cancel), all from
`GET /v1/staff/game`. Usage watch measures game servers (disk, memory against the plan); the declarative spec
(`GET/PUT /v1/services/{id}/spec`) carries `name`, `image`, `variables`, `schedules` for game servers and `firewall`
for VPS (`ServiceSpecService::SECTIONS` per family).

Credentials: the application key (`application_key`, `ptla_…`) for users/servers/nodes/allocations and the client key
(`client_key`, `ptlc_…`, a panel user with admin rights) for power, console, files, backups, schedules, databases,
subusers, startup. Store them from the terminal without leaving traces:

```bash
php artisan onhost:integrations:secret pterodactyl-gamepanel application_key --check
php artisan onhost:integrations:secret pterodactyl-gamepanel client_key --check
```

(hidden prompt; `--stdin` pipes a value; `--check` runs the connection test and the prerequisites and prints the
nodes, daemons and template mapping). The live instance `pterodactyl-gamepanel` (`https://gamepanel.onhost.cz`,
region cz1) is registered without credentials; after the keys are stored run *Objevit uzly* and map the templates in
*Šablony her*, then `onhost:doctor` reports `game: …` rows (client key, templates, daemons).

Tests: `tests/Contract/PterodactylToolsContractTest.php`, `tests/Feature/Provisioning/GameToolsFeatureTest.php`,
`tests/Feature/Provisioning/NodePrerequisitesTest.php` (game panel), `tests/Feature/Http/StaffConsoleTest.php`.

## Hands-off block (audit §5f, 2026-09-13)

**Stored payment methods** (`StoredMethodCharging::storedMethodsAvailable/chargeStoredMethod`, Comgate recurring):
a card top-up with `save_method=true` (`POST /v1/payments/init`; the panel asks once when automatic top-up is
supported and no card is stored) is created with `initRecurring`; when it settles, `PaymentService::rememberMethod`
stores the `transId` as the token (`payment_methods`, encrypted), makes the first card the default, attaches it to the
automatic top-up (`AutoTopupSetting.payment_method_id`) and publishes `payment.method.saved`. The renewal guard then
charges through `createIntent(method: stored)` → `initRecurringId`. `GET /v1/payment-methods`,
`DELETE /v1/payment-methods/{id}` (`RemovePaymentMethodCommand`); the billing policy table shows the stored card and
removes it. `COMGATE_RECURRING=false` switches the whole thing off; `onhost:doctor` reports the state.

**Staff console table views** (`onhost-admin.api.js` `table()`, renderer seam `const t = … OnhostAdmin.table(this, _,
s.view) || T[s.view]`): the prototype's `gnodes`, `geggs`, `galloc`, `gprov`, `fleet`, `jobsadm`, `automation`,
`renewals` views are allowed outside demo mode and filled from `/v1/staff/game`, `/v1/staff/provisioning/board`,
`/v1/staff/jobs` (scheduler expressions + next runs + bulk jobs), `/v1/staff/automation` (`AutomationLedger` — every
scheduled rule records its last run and counts; `AutomationLedger::RULES` is the catalogue) and `/v1/staff/renewals`
(subscriptions and domain expiries within 30 days with credit coverage). Sidebar counts come from `counts()`.

**Usage watch for game and mail**: game servers through `usage()` (disk, memory against `nvme_gb`/`ram_mb`), mail
domains through `mail_usage` (mailboxes' used bytes against `mail_quota_gb`/`quota_mb` or the sum of mailbox quotas),
metric `mail` (*poštovní schránky*).

**Spec for VPS and game servers**: see the game section; `spec_unsupported` for families without a document.

**Digest tuning**: organization setting `digest.frequency` (`weekly` default, `monthly` = the first weekly run of the
month, `off`) through `PATCH /v1/organizations/{id} {digest_frequency}`; preview `GET /v1/organizations/{id}/digest`;
EN organizations get English headings and wording. Account settings rows *Přehled e-mailem* and *Náhled přehledu*.

**Shell probes**: `NodePrerequisites::shellProbe` runs one command as the first site's agent user (php CLI version,
git/rsync/composer/wp/unzip/tar via `command -v`, the Apache proxy module file) and records `prereqs.shell`; a probe
that sees the module sets `mod_proxy=yes` when the option was unknown. Missing tools become warnings.

**Auto-drain feedback loop**: `node.drained` carries `failed[]` (the transient failures: operation id, kind, step,
message) so the internal notification says what failed; a drained node with no operations in the window is probed
(`IntegrationHealthProbe`) and resumed after two healthy probes in a row (`auto_drain.probe_ok`); staff can drain with
`keep=true` (`POST …/nodes/{node}/state`) and the loop leaves the node alone.

**Order intake pre-check** (`OrderRiskService`, config `onhost.orders.risk`): signals — new account without a paid
order (25), disposable mailbox (50), free mailbox behind a company (10), ≥ 3 orders in 15 minutes (30), first order
above the limit (25; `ONHOST_ORDER_RISK_FIRST_CZK`, default 20 000 Kč), ≥ 2 failed payments in 24 h (30), VAT id
from another country (15) — add up; at `hold_score` (60) the order is placed and paid but `markPaid` publishes
`order.review.required` instead of `order.paid`. Staff see the queue (`GET /v1/staff/orders?review=pending`, score and
reasons) and decide (`POST /v1/staff/orders/{id}/review {decision: release|reject, reason}` → `order.paid` or a
cancellation with the hold released; events `order.review.released/rejected`). The customer's order banner says
*objednávku ještě kontrolujeme*. Automatic upgrades (`source=auto`) are never scored.

## Game panel go-live, migrations, switches (audit §5g, 2026-09-13)

**Bootstrap.** `php artisan onhost:game:bootstrap pterodactyl-gamepanel` (or *Herní uzly → Zprovoznit panel*)
after the operator stored the keys with `onhost:integrations:secret pterodactyl-gamepanel application_key --check`
and `… client_key --check`. The report lists the probe, the nodes, the templates mapped by name
(`config/onhost.php` → `game.eggs`; unmapped keys name the community egg to import in the panel's admin UI), the
prerequisites, the port ranges created and the plan placement. `--eggs-only` re-maps templates; `--force` re-maps
the ones already mapped. The public `gamehosting` page sells the per-game configurator (`game-custom` + sliders, floors per egg in config onhost.game.eggs); the panel wizard also keeps the fixed `game-8/16/32` plans
with the template as the wizard's "image" choice (`config.egg` on the order line).

**Migrations.** `POST /v1/staff/services/{service}/migrate {target_node_id?, reason}` (a node id or name of the same
panel; empty = the scheduler picks) or *Herní uzly → Vystěhovat servery* (`POST …/game/nodes/{node}/evacuate`,
drains the node, keeps it drained, one saga per server). Steps: target → stop source (offline or kill after four
minutes) → backup → allocation → new server → transfer (queued `TransferGameArchive`; the saga polls
`onhost:gmig:{operation}`) → switch (binding, `game_servers`, node, `tags.access.address`, `service.migrated` mail)
→ delete source. Failures before the switch delete the target and start the source again (`service.migration.failed`
internal alert). The migrations show in *Provisioning fronta* (`kind` `game.migrate`).

**Automation switches and liveness.** *Automatizace* rows carry *Vypnout/Zapnout* (`PUT /v1/staff/automation/{key}`);
a switched-off rule records `skipped 1` on schedule. *Běhové úlohy* shows *plánovač běží/NEBĚŽÍ · worker fronty
běží/NEBĚŽÍ* (`AutomationLedger::liveness()`: the last `provisioning.tick` and the `QueueHeartbeat` stamp, stale
after five minutes); `onhost:doctor` fails on either in production; `platform.queue.stalled` alerts once per half
hour. *Hromadná akce* starts a bulk job from the console (filter `family:web`, `product:web-hosting`,
`instance:<key>`, `node:<id>`, `org:<id>`, `services:<id,id>`).

**Mail spec.** `GET/PUT /v1/services/{id}/spec` for a mail domain: `forwards` and `aliases` as `{source,
destination}` lists (one action per row to add or remove), `catchall` as a destination or `''`; `mailboxes` as
`{address, name?, quota_mb?, password?}` rows (a new address needs `password`; a password on an existing row
rotates it; rows the document omits are reported as `mailbox_extra:<address>` and never deleted); `autoresponders`
as `{<address>: {enabled, subject, text, start?, end?}}`; `spam` as `{<address>: <policy id or name>}` (the current
document lists the policies). Per-mailbox details are read for the first 50 mailboxes.

**Migration windows (§5h-3).** `POST /v1/staff/services/{id}/migrate` with `window_from`/`window_to` (ISO
datetimes, at most 30 days apart) schedules the saga instead of starting it: the customer gets the window and the
default start, moves it with `PUT /v1/services/{id}/migration {starts_at}` (workbench *Provoz* → *Změnit termín*),
and `onhost:provisioning:tick` starts the saga on time. Cross-panel targets work the same way (the node name or id
of any active game panel).

**Risk tuning (§5h-4).** *Automatizace → Kontrola objednávek → Upravit váhy* (`PUT
/v1/staff/automation/order.risk/tuning {weights, hold_score}` or `{reset: true}`); feedback counts per signal show
next to the weights.

**Backlog (§5h-5).** *Běhové úlohy* carries the operations due for longer than `ONHOST_QUEUE_BACKLOG_AGE_MINUTES`
per queue; above `ONHOST_QUEUE_BACKLOG_THRESHOLD` the health rule raises `platform.queue.backlog` and the doctor
warns; `/metrics` exposes `onhost_operations_backlog{queue}` for a second worker.

**Order risk.** `ONHOST_ORDER_RISK_GEO_ENDPOINT` (a JSON endpoint with `{ip}`) adds the `ip_country_mismatch`
signal; staff releases/rejects move the signal weights (`system_settings` `orders.risk.weights`, step
`ONHOST_ORDER_RISK_FEEDBACK_STEP`, bounds 5–100); the console shows them under *Kontrola objednávek*.

**Stored cards.** Stripe and GoPay keep cards like Comgate (`STRIPE_RECURRING`, `GOPAY_RECURRING`); the automatic
top-up charges through the gateway that holds the card.

## Service management, chargebacks, loyalty (audit §5i, 2026-09-13)

**Migrations for every supported family.** `ServiceMigrationService` (`domains/Provisioning`) is the one entry:
game services run `GameMigrationWorkflow` (same panel or cross-panel through the archive path), cloud servers run
`VpsMigrationWorkflow` (Proxmox live migration between nodes of the same cluster and role; the VM keeps its
address). Staff: `POST /v1/staff/services/{id}/migrate {target_node_id|target_node, reason, window_from, window_to}`
(op `service.migrate`), `POST /v1/staff/nodes/{id}/evacuate` (op `service.evacuate`, every migratable family).
Customers pick or move their window in the workbench (`PUT /v1/services/{id}/migration`). Other families answer
`migration_unsupported` (409). The workbench operations row shows the schedule, the step label while running, the
new address when finished and the failure when it failed (`tags.migration.state`).

**Rebalancing.** `GET /v1/staff/provisioning/rebalance?role=compute|game` plans: load = sold RAM / capacity per
node (`nodes[].load_pct`), hot nodes above `ONHOST_REBALANCE_HIGH` (0.85) hand their smallest services to the
coldest node of the same role and region (same instance for compute; a matching template on the target panel for
game) until they sit at `ONHOST_REBALANCE_TARGET` (0.75); nodes below `ONHOST_REBALANCE_LOW` (0.6) are the first
candidates. `POST …/rebalance {service_ids?, reason, window_from?, window_to?}` (op `rebalance.apply`) turns the
moves into migrations; the fleet view has *Plán přerozdělení* (shows the plan) and *Spustit přerozdělení*.

**Chargeback (refund in credits).**

1. Customer: workbench → operations tab → *Vrácení kreditu* → `POST /v1/services/{id}/chargeback {reason}` (the
   service must be active/degraded/suspended, one open request per service; the row shows the estimate: unused
   part of the paid period × percentage).
2. Support: *Finance* console view (`#/money`) or `GET /v1/staff/chargebacks?state=open` → *Schválit* / *Zamítnout*
   (`POST /v1/staff/chargebacks/{id}/decide {decision, reason}`, permission `staff.service.manage`). Approval
   snapshots the percentage; the customer is notified (mails `chargeback-approved|rejected`).
3. Customer: *Zrušit službu s vrácením* (step-up, `POST /v1/services/{id}/chargeback/cancel`) → `terminate`
   action with a final backup; when the termination succeeded (`service.terminated`) `ChargebackSettlement`
   credits the wallet (source `chargeback`, purpose `chargeback`) and sends `chargeback-refunded`.

The percentage: *Finance* → *Procento vrácení* (`PUT /v1/staff/chargebacks/settings {percent}`, 0–100, stored in
`system_settings` key `chargeback.percent`; default `ONHOST_CHARGEBACK_PERCENT=70`). Money never leaves the
platform: a chargeback is wallet credit for the requesting organization only.

**Loyalty (gamification).** `LoyaltyRouter` listens to the outbox: paid orders earn 1 point per 100 Kč
(`loyalty.points.order.paid_per_100`), on-time payments 10, enabling MFA 50 (+ badge *guardian*), backups 30
(*archivist*), monitoring 20 (*watchman*), the first service 100 (*first-service*), an approved partner referral
200 (*ambassador*). Levels bronze/silver/gold/platinum (`loyalty.levels`, editable through
`GET|PUT /v1/staff/loyalty/levels`) — a level-up posts the promo-credit reward of the level (source `promo`, key
`loyalty:level:<key>:<org>`, never twice) and mails `loyalty-level-up`. Customers see level, points, badges and the
history on the account page (`GET /v1/account/rewards`); manual awards `POST /v1/staff/loyalty/award {organization_id,
points, note}` (permission `staff.customer.manage`). Points are idempotent per rule and reference (unique index).

**Queue helpers on the backlog.** `php artisan onhost:queue:scale` prints stale/desired/running; `--apply` starts
the missing helpers (`queue:work --stop-when-empty --max-time=ONHOST_QUEUE_MAX_TIME`), at most
`ONHOST_QUEUE_MAX_HELPERS` (3), one round per `ONHOST_QUEUE_COOLDOWN_MINUTES` (15). `ONHOST_QUEUE_AUTOSCALE=true`
schedules it every five minutes. Production may replace the launcher with a systemd template unit driven by the
same numbers (`QueueScaler::advise()`).

**Risk review.** `GET /v1/staff/orders/risk-review?days=90[&format=csv]` lists held orders with their signals and
the outcome (pending/released/rejected) and the per-signal precision (rejected ÷ decided); the automation row
`order.risk` has *Přehled rozhodnutí*.

**Mailbox password link.** `POST /v1/services/{id}/mailbox-password-link {remote_id}` → a signed one-time URL
(`/mailbox/password/{token}`, 24 h) the account owner hands to the mailbox user; the page validates 12–72
characters + confirmation, runs the ordinary `mailbox.update` action and burns the link. No password is stored,
logged or shown anywhere.

**Fixture recorder.** `php artisan onhost:fixtures:record comgate|stripe|gopay [--amount=100] [--currency=CZK]
[--out=]` creates one sandbox payment (left unpaid, expires on the gateway) and stores the create/status payloads
redacted under `tests/Contract/fixtures/<gateway>/recorded_*.json`. Refused in production.

## Ecosystem block (audit §5j, 2026-09-13)

**Marketplace of partner services.** Approved partners list what they deliver (`POST /v1/partner/marketplace/listings
{key, title, description, category, price_minor, billing, delivery_days}` with `X-Organization` = the partner's
organization; categories `care, seo, security, backup, migration, development, design, content`); the listing is a
draft until staff publish it (`POST /v1/staff/marketplace/listings/{id}/state {state: published|paused|retired}`,
permission `partner.manage`); partners pause and resume their published listings, a price or title change sends the
listing back to draft. Customers browse `GET /v1/marketplace[?category=]` and order `POST /v1/marketplace/{key}/order
{brief, service_id?}` (step-up; the credit is charged net + VAT, a paid tax document is issued, the platform's share is
`ONHOST_MARKETPLACE_COMMISSION` %). The partner starts and delivers (`POST /v1/partner/marketplace/orders/{id}/start`,
`…/deliver {note}`), the customer accepts or disputes (`POST /v1/account/marketplace/orders/{id}/accept|dispute
{reason}|cancel`); acceptance books the partner's share as a payable commission (kind `marketplace`, paid out with the
ordinary partner payouts), a dispute lands in *Finance → Spory marketplace* (`POST /v1/staff/marketplace/orders/{id}/
resolve {decision: refund|deliver, reason}` — a refund returns the credit and issues a credit note). Deliveries nobody
answered count as accepted after `ONHOST_MARKETPLACE_AUTO_ACCEPT_DAYS` (`php artisan onhost:marketplace:auto-accept`,
daily 05:05). A partner cannot order their own listing; listings are priced in the partner's currency and only sell to
accounts in the same currency.

**Referrals.** Every organization gets an invite code on demand (`POST /v1/account/referral/code`; account row
*Doporučte nás* copies the link `/registrace?ref=<code>`); `POST /v1/auth/register {ref}` binds the new organization
for good. The first paid tax document of the invited organization rewards both sides once: the referrer
`loyalty.referral.referrer_points` + `ONHOST_REFERRAL_CREDIT` promo credit, the invited one
`referred_points` + `ONHOST_REFERRAL_WELCOME_CREDIT`. Refused (with a recorded reason, finance is told): the same
non-public e-mail domain on both owners, an order of the invited organization held or rejected by the risk check, the
same registration address as an earlier invite of the same referrer, more than `ONHOST_REFERRAL_MONTHLY_CAP` rewards
in 30 days, sandbox tenants. `GET /v1/account/referral` lists invites with masked names.

**Missions and streaks.** `GET /v1/account/missions` shows the five monthly missions with progress; `POST
/v1/account/missions/evaluate` (or the daily `onhost:loyalty:missions`) awards the completed ones once per month
(`loyalty.missions.*` points) and the badge `mission:month` when everything is done. The streak counts consecutive
months (ending last month) in which every document due was paid by its due date (a month without documents counts
when services were running); at `ONHOST_STREAK_MONTHS` the badge `streak:target` and `loyalty.streak.reached` fire
once and finance decides: `POST /v1/staff/loyalty/streak/{organization} {percent (0–30), note}` stores
`settings.loyalty_discount`; `QuoteService` takes it off every line after the commitment and promo discounts (never
on a plan change); `percent: 0` removes it.

**Predictive rebalancing.** `GET /v1/staff/provisioning/rebalance?basis=usage` plans from the node's last measured
RAM (`usage.ram_used_mb`) instead of the RAM sold; nodes without a measurement keep the sold figure. `php artisan
onhost:rebalance:plan [--role=] [--basis=usage] [--mail]` prints the plan; the nightly run (03:35) sends it to the
operations inbox (`rebalance.plan`) with the loads before and after the proposed moves. Applying still goes through
`POST …/rebalance` (migrations in a window).

**Status page per organization.** Off by default. `PATCH /v1/organizations/{id} {status_page: {enabled, title,
show_monitors, show_incidents}}` (account row *Vlastní stránka stavu*); `GET /v1/account/status-page` returns the
settings, the URLs and a preview. Public: `/stav/<slug>` (HTML, refreshes every two minutes), `/stav/<slug>/badge.svg`,
`GET /v1/status/org/<slug>` — the customer's enabled monitors by host name (never the full URL), the public platform
components behind their service families and region, public incidents touching their services, organization or
components, and maintenance windows touching them. `status.<their-domain>` = a CNAME to the portal host plus a vhost
alias serving `/stav/<slug>` (operator task; the page carries `noindex`-free plain HTML).

**Chargeback analytics.** `GET /v1/staff/chargebacks/analytics?days=90` returns requests per product, per node and
per theme (`performance`, `reliability`, `price`, `support`, `features`, `moving`, `other` — keyword clusters over the
reasons, Czech and English) and the clusters above `ONHOST_CHARGEBACK_CLUSTER_THRESHOLD` inside
`ONHOST_CHARGEBACK_CLUSTER_DAYS`; `POST /v1/staff/chargebacks/analyse` (daily 05:25 as `onhost:chargebacks:analyse`)
opens one internal p3 incident per cluster on the matching status component (`games-cz1`, `cloud-cz1`, `web-cz1`,
…, fallback `portal`) — one per cluster while it is open (`meta.chargeback_cluster`). *Finance → Proč odcházejí*.

**Signed data export.** The export archive now carries `audit` (the last 5 000 audit rows of the organization). `POST
/v1/data-requests/{id}/link` (permission `organization.manage`, export `ready`) returns a signed URL
`/export/{id}/{token}` valid seven days at most and never past the export's expiry; issuing a new link revokes the
previous one; the token is stored hashed; a stale or superseded link answers 410, a tampered signature 403.

**Regional pricing and currency.** `config onhost.pricing.regions` (default: SK → EUR, 0 %; EU → EUR,
`ONHOST_PRICING_EU_ADJUST` %) is overridden by the staff table `PUT /v1/staff/pricing/regions {regions: [{key,
label, countries[], currency, adjust_pct (−50…+100)}]}` (an empty list restores the defaults; `GET /v1/staff/pricing`
lists it). The cart applies the group's percentage of the customer's country to the list and renewal price and records
`price_region`/`price_region_pct` on the line and the quote (`region` on a line is the placement region the
provisioning reads — a different thing); `GET /v1/catalog/regions?country=` tells the storefront the suggested
currency. Customers switch the account currency with `PATCH /v1/organizations/{id} {currency: CZK|EUR}`; wallets are
per currency, the checkout defaults to the account currency.

**Sandbox tenants.** `POST /v1/staff/customers/{organization}/sandbox {enabled}` (permission
`staff.customer.manage`) sets `feature_flags.sandbox`, books `ONHOST_SANDBOX_CREDIT` promo credit once (`sandbox:<org>`)
and notifies the customer. Provider instances flagged `options.sandbox: true` are lab instances: the scheduler places
sandbox tenants there only and never places anyone else there — provisioning, game and VPS migrations alike (a
sandbox order with no lab node fails with `capacity_unavailable`, it never falls back to production). Sandbox tenants
earn no loyalty points and no referral rewards.

**Green hosting.** `config onhost.green.regions` declares the energy profile per region (`source`, `gco2_per_kwh`,
`pue`, `renewable_pct`; CZ1 from `ONHOST_GREEN_CZ1_*`); a node overrides it with `tags.energy`. The footprint of an
organization = Σ RAM sold (GB) × `ONHOST_GREEN_WATTS_PER_GB` × 730 h × PUE / 1000 kWh × gCO₂/kWh — an estimate, stated
as such everywhere. `GET /v1/account/green` (account row *Uhlíková stopa*), every issued invoice and receipt carries
`meta.green` (JSON `green` on the document, a footnote on the PDF), `GET /v1/green` is the public profile,
`/green/badge.svg?pct=&lang=` the badge for the customer's own site.

## Ecosystem block, part two (audit §5k, 2026-09-13)

**Partner portal · Marketplace tab (seam #46).** Outside demo mode the partner portal has a *Marketplace* tab
(`/partner#/marketplace`): the partner's listings with *Nová nabídka* (key, title, category, net price, one-off or
monthly, delivery days, description → a draft staff publish), *Pozastavit / Obnovit*, *Upravit cenu* (sends a
published listing back to draft), and the jobs customers ordered with *Převzít* and *Označit dodáno* (the note reaches
the customer). The tab's badge counts new jobs. Module `apps/surfaces/api/onhost-partner.api.js`, seams in
`SurfaceRenderer::partnerSeams` (a mismatch is logged as `surface seam #46 anchor mismatch`).

**Monthly listings.** A listing with `billing: monthly` opens a subscription with the order (`subscription` on the
order: `period_end`, `cancel_at_period_end`, `state`); `php artisan onhost:marketplace:renew` (daily 05:00) charges the
credit for the next period, issues a paid statement and books the partner's share as a payable commission of the
period. The customer ends a running monthly listing with `POST /v1/account/marketplace/orders/{id}/cancel` (it stops
with the paid period, state `ended`); without credit the renewal fails (`marketplace.renewal_failed`, the customer is
told the grace deadline), is retried daily and the listing ends after `billing.dunning.grace_days` (`marketplace.ended`,
reason `unpaid`). A refund of a disputed monthly order cancels its subscription.

**Status page on the customer's domain.** Account settings → *Stavová stránka na vlastní doméně*: the customer sets
`status.<their-domain>` (`PATCH /v1/organizations/{id} {status_page: {domain}}`), creates a CNAME to
`ONHOST_STATUS_CNAME` (default: the portal host) and clicks *Ověřit* (`POST /v1/account/status-page/verify`; the
hourly `onhost:status:verify-domains` re-checks pending hosts). A verified host serves the page at `/` and the badge at
`/badge.svg` through the `StatusHost` middleware; the portal host itself is never affected. The edge issues the
certificate on demand and asks the platform first — Caddy:

```
https:// {
    tls {
        on_demand
    }
}
# global options
{
    on_demand_tls {
        ask https://portal.onhost.cz/v1/status/host-check
    }
}
```

`GET /v1/status/host-check?host=<host>` answers 200 only for a verified, enabled status host (404 otherwise), so no
certificate is ever issued for a host nobody verified. A host verified by one organization cannot be verified by
another (`status_domain_taken`); changing the host starts the verification over.

**Referral landing.** Any public page visited with `?ref=<code>` stores the code in the `onhost_ref` cookie (30 days,
HTTP-only, unencrypted so the API reads it); the registration endpoint uses the form's code first and the cookie
second. `/registrace?ref=FIRMA-AB12` therefore attributes a sign-up that happens days later from the plain form.

**Missions catalogue.** `GET /v1/staff/loyalty/missions` shows the table in force (seasonal rows included, `in_season`),
the checks a mission may use and the defaults; `PUT /v1/staff/loyalty/missions {missions: [{key, cs, en, points,
hint_cs, hint_en, check, params?, active_from?, active_to?, badge?}]}` replaces it (an empty list restores the five
built-in missions). Checks: `mfa_all`, `monitor`, `restore_test`, `on_time`, `profile`, `backup_done` (a completed
backup this month), `services_min {min}` (running services), `ticket_free` (no ticket this month). A mission with
`badge` grants `mission:<badge>` on its first completion; a mission outside its season is neither shown nor awarded.

**Measured power.** PDU/IPMI probes post `POST /v1/probes/power {readings: [{node, watts, at?}]}` with a probe token
(the same tokens as the SLA probes, `throttle:probes`); the reading lands on the node (`usage.power_w`,
`usage.power_sampled_at`) and in `node_usage_samples`. While fresh (`ONHOST_GREEN_MEASURED_MAX_AGE_HOURS`, 24) the
footprint of a service on that node = watts × 730 h × the service's RAM share of the node (`basis: measured`), older
readings fall back to the model; `GET /v1/green` reports `measured.nodes` of `of`.

**Trend rebalancing.** `onhost:provisioning:sample-nodes` (hourly) copies every recently seen node's `usage` into
`node_usage_samples` (retention `ONHOST_NODE_SAMPLES_RETENTION_DAYS`, 30). `GET /v1/staff/provisioning/rebalance?
basis=trend` plans from `NodeRebalancer::trend()` — the 95th percentile of the last 7 days of RAM plus the slope of the
daily averages projected a week ahead (capped at the node's capacity); nodes with fewer than twelve samples use the last
measurement. The nightly `onhost:rebalance:plan --basis=trend --mail` (03:35) reaches operations with the loads before
and after the proposed moves.

## Ecosystem block, part three (audit §5l, 2026-09-13)

**Partner portal on the API (seam #47).** Outside demo mode every tab of `/partner` reads the partner API: the
overview shows the real volume, commission, active clients and notices, the tier table (`partners.tiers`) and the
feed of commissions and payouts; *Klienti* lists the organizations attributed to the partner with their state, volume,
services and commission; *Provize* the monthly rows of the commission engine; *Výplaty* the payable balance, the held
part and the payout history — *Požádat o výplatu* posts `POST /v1/partner/payouts {amount, iban}` (the API enforces
the minimum and the balance); *Whitelabel* loads the saved settings and *Ověřit* saves them with
`PUT /v1/partner/whitelabel` (the CNAME target comes from `ONHOST_WHITELABEL_CNAME`, verification is hourly);
*Materiály* uses the partner's own `?ref=<code>` link and the files from `partners.assets`. A tab whose data has not
arrived shows the prototype literal until it does; a seam that does not match logs `surface seam #46 anchor mismatch`.

**Marketplace delivery SLA.** `php artisan onhost:marketplace:sla` (daily 05:10): a job past its due date warns the
partner (*Zakázka po termínu*) and the customer (*Dodání se zpozdilo*) once; after `ONHOST_MARKETPLACE_OVERDUE_GRACE_DAYS`
(7) the customer gets *Nedodáno v termínu: můžete si vzít kredit zpět* and `POST /v1/account/marketplace/orders/{id}/cancel`
refunds a started job without a dispute (credit note included). Orders carry `sla` (`overdue`, `days_overdue`,
`refund_available`, `grace_days`); the partner's job rows show *PO TERMÍNU*.

**Edge configuration.** `php artisan onhost:edge:config --format=caddy --upstream=127.0.0.1:8000` prints
`infra/edge/Caddyfile.status-hosts.global` (the one global options block Caddy allows: on-demand TLS ask =
`/v1/status/host-check`) and `infra/edge/Caddyfile.status-hosts` (the site block for conf.d) with the portal host filled
in; `--out=` writes the snippet and `<out>.global`; `--format=nginx` prints the nginx default server + certbot flavour;
`--write` stores `infra/edge/<format>.generated` for the deploy.

**Referral fraud scoring.** Every referral settlement is scored: `same_email_domain` (100), `risk_hold` (100),
`chargeback` (100), `same_address` (60), `refused_history` (30), `rapid_signup` (25, an earlier invite of the same
referrer inside the hour), `many_pending` (20, five or more waiting). A score ≥ 100 refuses with the strongest signal as
the reason, ≥ 60 holds the referral for finance (`referral.held` in the finance inbox; the customer sees it as pending).
`GET /v1/staff/referrals?state=held|all` lists them with scores, signals and the current weights;
`POST /v1/staff/referrals/{id}/review {decision: release|reject, note}` rewards or refuses and teaches the weights
(release −5 on the referral's signals, reject +5; bounds 5–100, stored in `loyalty.referral.weights`).
`chargeback.approved` of a referred organization within `ONHOST_REFERRAL_CLAWBACK_DAYS` (90) after the reward marks
the referral `clawback` (`referral.clawback` to finance) and raises its signals by 10; the promo credit already
booked is never taken back from anyone.

**Mission campaigns.** `PUT /v1/staff/loyalty/campaigns {campaigns: [{key, cs, en, missions[], badge?, active_from,
active_to?, mail?}]}` (missions must exist in the catalogue, seasonal rows included); `GET` lists them with
`in_window`. `php artisan onhost:loyalty:campaigns` (daily 05:20) announces a campaign whose window opened once to every
active organization (`loyalty.campaign.started`, mail `loyalty-campaign` when `mail` is true). The daily
`onhost:loyalty:missions` (and the customer's *Vyhodnotit*) grants `campaign:<badge>` once when every mission of the
campaign was awarded inside the window (`loyalty.campaign.completed`); `GET /v1/account/missions` lists
`campaigns[] {done, total, earned, until}`.

**Power per service.** On a node with measured watts the footprint splits them by `tags.usage.memory.used` of every
service on the node (the usage watch's hypervisor telemetry); a service without telemetry weighs its sold RAM. The
footprint row says `split: telemetry|ram`; the share is measured against the node's capacity, so the idle rest of a
half-empty host is charged to nobody.

**Trend on CPU and disk.** `NodeRebalancer::trend()` returns `cpu_p95` and `disk_p95_gb` next to the RAM projection;
`?basis=trend` marks a node hot when its RAM projection is above the high mark, its CPU p95 is at or above the high
mark (85 %) or its disk p95 is at or above 85 % of capacity (`reasons` on the node row), and moves its smallest
services until RAM, the CPU estimate and the disk estimate sit under the target — the estimates shrink with the RAM
share that leaves with each service.

## Ecosystem block, part four (audit §5m, 2026-09-13)

**Commission model as a contract term (seam #48).** Outside demo mode the *Podíl z objemu* / *Jednorázově + bonus*
buttons of the partner portal no longer switch anything: clicking the other model asks finance
(`POST /v1/partner/model {model, note}`), one open request at a time (`partner_request_pending`), the model in force
is refused (`partner_model_same`). Finance sees *Partner žádá o změnu modelu provize* in the inbox and decides with
`POST /v1/staff/partners/requests/{id}/decide {decision: approve|reject, note}` (`GET /v1/staff/partners/requests
?state=requested|approved|rejected|all` lists them). An approval stores `pending_model` and `model_effective_from` (the
first of next month) on the partner; `php artisan onhost:partners:apply-models` (daily 02:35) flips the model on that
day (`partner.model.changed`). `GET /v1/partner/model` returns the model in force, the pending one and the last
request; the portal's note under the buttons reads it (*Žádost o změnu … čeká na finance*, *Schválená změna … platí
od …*, *Poslední žádost finance zamítly: …*).

**Marketplace late credits.** A delivery that came after its due date and was accepted anyway credits the customer
automatically when the acceptance settles: `ONHOST_MARKETPLACE_LATE_CREDIT_PCT` (5) percent of the net price per day
late, capped at `ONHOST_MARKETPLACE_LATE_CREDIT_CAP` (50) percent — wallet credit *Kredit za pozdní dodání*,
notification *Kredit za pozdní dodání: <title>*. The partner's share carries the credit (`late_credit_minor` on the
order, the marketplace commission row is booked net of it). Order `sla` carries `days_late` and `late_credit`; a
delivery inside the due date pays nothing, a zero rate switches the credit off.

**Edge as code.** `infra/ansible/edge.yml` runs the role `onhost_edge` on the `edge` group: it renders the flavour
(`onhost_edge_flavour: caddy|nginx`) on the app host with `php artisan onhost:edge:config --format= --upstream=
--out=`, copies the file to `onhost_edge_conf_dir` (`/etc/caddy/conf.d/status-hosts.caddy` or the nginx conf), merges the
global `on_demand_tls` block into the top of `onhost_edge_caddy_main` (`/etc/caddy/Caddyfile`, which must only import
conf.d and carry no global block of its own — an imported file may not have one), validates
it (`caddy validate` / `nginx -t`) and reloads the service through a handler. Defaults live in
`roles/onhost_edge/defaults/main.yml` (`onhost_app_dir`, `onhost_php`, `onhost_edge_upstream`, `onhost_app_host`).

**Shared risk signals.** The order risk check reads the referral loop: `referral_flagged` (40) when the ordering
organization was referred and that referral is held, refused or clawed back. The referral check reads the order loop:
`referrer_risk` (40) when the referrer had an order rejected by staff inside 180 days. A staff reject in one loop
teaches the other's cross signal (+5, bounds as before): an order reject of a referrer raises `referrer_risk`, a
referral reject that carried `risk_hold` or `referrer_risk` raises `referral_flagged`; the two do not cascade back.

**Campaign analytics.** `GET /v1/staff/loyalty/campaigns/{key}/analytics` returns `announced` (organizations that
got the start notice), `completed` (campaign badges granted), `missions[] {key, organizations, points}` inside the
window, `points`, `level_ups {count, credit}` (level badges reached in the window and their promo credit from the
level table) and `completion_rate` (%).

**Per-VM power.** A probe that reads per-VM watts (IPMI/Redfish per-slot sensors, hypervisor power telemetry) adds
`vms: [{id, watts}]` to a node reading of `POST /v1/probes/power`; `id` is the VM's remote id on the node's provider
instance (the Pterodactyl server id, the Proxmox VMID), matched through the provider binding. The service is charged its
own watts while the reading is fresh (`ONHOST_GREEN_MEASURED_MAX_AGE_HOURS`; footprint `split: vm`); the other
services on the node split only what is left of the node's watts. Unknown ids come back as `node/id` in `unknown`.

**Capacity forecast.** `php artisan onhost:provisioning:capacity-forecast` (daily 03:45) prints per role and region the
sellable RAM (the N+1 view of the scheduler), the demand (the larger of sold RAM and the measured p95 of the 7-day
trend), the growth (the summed daily slope of the nodes' trends) and the days left before the pool is sold out; a pool
under `ONHOST_CAPACITY_WARN_DAYS` (30) days or without headroom raises `capacity.forecast.low` to operations once a
day (*Kapacita dochází: game cz1 · zbývá 8 dní*). `GET /v1/staff/capacity` carries the same rows as `forecast`.
## Ecosystem block, part five (audit §5n, 2026-09-14)

**Contract terms in the portal (seam #48, extended).** Under the model buttons the *Provize* tab shows *Smluvní
podmínky*: the model, the rate lock, the payout terms and the white-label scope, each with *Požádat o změnu* — a prompt
lists the allowed values (`rate_lock` 3/6/12 months, `payout_terms` on_request/monthly/quarterly, `whitelabel_scope`
basic/full), a note for finance is optional, and the row then reads *žádost o … čeká na finance* or *schváleno: … od
<date>*. The API is `GET /v1/partner/changes` (terms, `pending`, `open`, `kinds`, `min_payout`, `requests`) and
`POST /v1/partner/changes {kind, value, note}` (one open request per term: `partner_request_pending`; the value in
force: `partner_change_same`). Finance sees *Partner žádá o změnu podmínek: … → …* and decides as before; money terms
apply on the first of next month (`onhost:partners:apply-models`, daily 02:35, now every kind), the white-label scope at
once. On the basic scope `PUT /v1/partner/whitelabel` keeps `own_mail`, `own_prices`, `own_support` off and lists them
in `whitelabel.limited`.

**Payout terms.** `php artisan onhost:partners:auto-payouts` (the 1st at 06:00) requests the payable balance for every
active partner on monthly terms (quarterly: January, April, July, October) when an IBAN is on file, the balance is at
or above `min_payout_minor` and no payout is open — *Výplata provize požádána automaticky* for the partner, the usual
finance flow afterwards.

**Monthly deliverables.** A running monthly listing owes a deliverable per period. The partner's *deliver* on an
accepted subscription order reports it (`period_delivered_at`, customer notice *Měsíční plnění dodáno*); 80 % into an
unserved period (`ONHOST_MARKETPLACE_PERIOD_WARN_PCT`) the daily `onhost:marketplace:sla` reminds the partner once
(*Měsíční plnění ještě není odevzdané*). A period that ends unserved is credited at the renewal:
`ONHOST_MARKETPLACE_MISSED_PERIOD_CREDIT` (50) percent of the period's net price to the customer's wallet (*Kredit za
chybějící měsíční plnění*), the partner's share of the new period reduced by it (*Období bez plnění* for the partner);
`missed_periods` and `late_credit_minor` accumulate on the order. `sla.period {start, end, served, delivered_at,
missed_periods, warn_at}` and `sla.late_credit_preview` (what a late delivery would credit before the customer accepts)
are on every order row.

**Edge role in CI.** `.github/workflows/edge-role.yml` runs `ansible-lint` (`infra/ansible/.ansible-lint`, profile
moderate) and `molecule test` in `infra/ansible/roles/onhost_edge` on changes under `infra/ansible` or `infra/edge`:
the scenario prepares a Debian container with Caddy and a stub `php` that renders the packaged template to `--out`,
converges the role and verifies the installed file (`on_demand`, the ask endpoint, the upstream) with `caddy validate`.

**One risk model.** `risk.weights` (system settings) is the only weight table: the order check reads its signals from
it, the referral check its own, and every staff decision — order release/reject, referral release/reject — moves every
signal that fired, so a signal both loops know (`disposable_email` now scores referrals too, the cross signals
`referral_flagged` / `referrer_risk`) learns from both. Installations that tuned the old `orders.risk.weights` /
`loyalty.referral.weights` keep their values (merged on first read). `PUT /v1/staff/automation/order.risk/tuning
{weights}` accepts any signal of either loop; the answer carries `shared` (the whole table) and `referral` (hold 60,
refuse 100); `{reset: true}` restores every default.

**Campaign cost forecast.** `POST /v1/staff/loyalty/campaigns/forecast {missions[], cs?, en?, badge?, active_from?,
active_to?}` prices a draft: `organizations` (active, no sandbox), per mission the catalogue points, the 90-day
completion share (or `ONHOST_LOYALTY_FORECAST_DEFAULT_PCT` 25 when nobody completed it), `points_max` /
`points_expected`; `level_ups_max` and `credit_max` (organizations the campaign's points would push over a level, with
the level's promo credit), `credit_expected` scaled by the expected completion, `expected_completion_pct`.

**Host power from the BMC.** A node with `tags.bmc = {driver: redfish, url, chassis?, secret_ref?, insecure?}` is read by
the hourly `onhost:provisioning:sample-nodes` before the snapshot: `EnvironmentMetrics` (`PowerWatts.Reading`) first,
`Power` (`PowerConsumedWatts`, or the PSU outputs) as fallback; basic auth or `X-Auth-Token` from the secret store
entry; the reading lands in `usage.power_w` / `power_source` and the hourly sample, so the footprint measures without a
probe. A hypervisor that reports a VM's own draw (`tags.usage.power_w` + `sampled_at`) has that VM charged directly
(`split: vm`); the node's remaining watts split among the others.

**Capacity requests.** `onhost:provisioning:capacity-forecast` (daily 03:45) now also runs the planner: a pool marked
low gets one open `capacity_requests` row sized like its largest node (*Návrh nákupu uzlu* to operations, with the
vendor when the pool's instance has `options.node_order`). `GET /v1/staff/capacity/requests?state=open|approved|…|all`
(also `capacity.requests` on `GET /v1/staff/capacity`); `POST /v1/staff/capacity/requests/{id}/decide {decision:
approve|cancel|delivered|retry, note?, node_name?}` (permission `capacity.manage`; approve and retry need a fresh
step-up). Approve with a vendor orders the node (`NodeOrders` → Hetzner Cloud `POST /v1/servers` with the instance's
`server_type|image|location|ssh_keys|user_data`, token from `options.node_order.secret_ref` or the instance entry's
`node_order_token`), creates the node `pending` — never sellable — and reports *Uzel objednán*; when operations put the
node active, the next pass delivers the request. Without a vendor the request waits for the purchase and `delivered
{node_name}` closes it. The rule `capacity.auto_order` (console, off by default) approves and orders without a human;
a refused order leaves the request `failed` (*Objednávka uzlu selhala*) for `retry`.
## Ecosystem block, part six (audit §5o, 2026-09-14)

**Finance rules for simple terms.** The rule *Automatické schválení smluvních změn* (`partners.auto_approve`,
Automatizace, on by default) approves two kinds of partner requests the moment they arrive: a rate lock of at most
`ONHOST_PARTNER_AUTO_RATE_LOCK_MONTHS` (6) months, and a payout-terms change for a partner approved at least
`ONHOST_PARTNER_AUTO_CLEAN_MONTHS` (12) months ago with no rejected payout in that time. The decision note reads
*automaticky: …*, finance sees *Smluvní změna schválena automaticky* (info), the partner the usual approval; the term
applies on the first of next month as always. The model and the white-label scope never go through the rule.

**Checklist behind a monthly deliverable.** A monthly listing names its checklist when it is created (portal prompt
*Checklist měsíčního plnění*: comma-separated items, a trailing `%` makes a text item) or through the API
(`checklist: [{key, cs, en?, kind: check|text}]`, up to ten). *Odevzdat měsíční plnění* in the partner's job list then
asks for every item (a confirmation per tick, a value per text item) before the note; the API refuses an incomplete
report with `marketplace_checklist_incomplete` and the missing keys. The customer's order row carries `checklist` and
the last three periods' `period_evidence {period_end, at, note, items}`.

**Ansible roles and molecule.** `infra/ansible/roles`: `onhost_proxmox_api` (role, user, ACL and token through
`pveum`, idempotent, the token secret shown once for `onhost:integrations:secret`), `onhost_ispconfig_remote` (the
remote API user in `remote_user` through the MariaDB client, sha512 password hash, function groups kept in sync),
`onhost_powerdns` (packages, `pdns.d/onhost-api.conf` with the API key, loopback webserver, NOTIFY set),
`onhost_probe` (`onhost-probe.sh` + systemd timer posting `results[]` to `/v1/probes/results` with the probe token).
Every role has `molecule/default` — PowerDNS and the probe converge for real, Proxmox and ISPConfig against stub
binaries that log the calls and prove a second run adds nothing. `.github/workflows/edge-role.yml` runs
`ansible-lint` and then every scenario in a matrix.

**Risk review across loops.** `GET /v1/staff/orders/risk-review?days=` now returns `referrals[] {loop, referral, code,
organization, referrer, score, reasons, outcome, state, decided_at, decision_reason}` next to `orders[]`, one
`signals` table counting both (held / released / rejected / pending, precision) and the shared `weights`; `held` sums
both loops; `format=csv` adds a `loop` column and the referral rows.

**Loyalty campaigns in the console.** *Věrnost a kampaně* (the prototype's `coupons` table, `#/coupons`) lists the
campaigns with their missions, window and badge; *Odhad nákladů* writes the forecast into the row (organizations,
points max / expected, level-ups, credit max / expected); *Nová kampaň* asks for key, title, missions, window and
badge, shows the forecast and saves only after a confirmation; *Smazat* removes a campaign.

**BMC inventory.** The hourly host power sweep also reads `Chassis/{id}/Thermal` (the hottest sensor, fans not OK)
and `Chassis/{id}/Power` (power supplies' health) into the node's `usage.bmc {temp_max_c, fans_failed, psus[], psu_failed,
at}`; a host at or above `ONHOST_BMC_TEMP_WARN_C` (75) °C, a failed PSU or a failed fan raises *Hardware hlásí problém*
(hot) to operations once a day per node.

**Vendor node bootstrap.** A node ordered from the vendor boots with cloud-init: base packages, the operator's key
(`ONHOST_NODE_BOOTSTRAP_SSH_KEY`), a call to `POST {ONHOST_NODE_BOOTSTRAP_CALLBACK|APP_URL}/v1/probes/capacity/{id}/ready`
with a one-time token (kept hashed on the request) and the host's facts (hostname, IP, OS, cores, RAM, disk);
`ONHOST_NODE_BOOTSTRAP_USER_DATA` replaces the template (`{callback}`, `{token}`, `{request}`, `{role}`, `{region}`,
`{ssh_key}`); an instance option `node_order.user_data` wins over both. The call-back marks the request ready,
stores the facts on the node's `tags.bootstrap`, spends the token and tells operations *Uzel je připraven na
instalaci* with the playbook line. *Kapacita a nákup uzlů* (the prototype's `nodecost` table, `#/nodecost`) shows the
forecast pools and every request with *Schválit / Dodáno / Zrušit / Zkusit znovu* and *Spustit forecast*
(`POST /v1/staff/capacity/forecast/run`).

**Spigot and the staff quick action.** The template `minecraft-spigot` maps onto a Spigot egg when the panel has one
and onto the Paper egg otherwise (`via_fallback`), with `DL_PATH=https://download.getbukkit.org/spigot/spigot-{version}.jar`
and `SERVER_JARFILE=spigot-{version}.jar`; `{version}` follows the order's `version` (→ `MINECRAFT_VERSION`, default
1.21.8). *Založit herní server* in the console's *Provisioning fronta* (organization, plan, template, version, label)
and `php artisan onhost:game:create <org|slug|owner e-mail> --plan=game-8 --egg=minecraft-spigot --game-version=1.21.8`
create a service for a customer without an order (`POST /v1/staff/customers/{org}/services`, `service.create` on the
bus, permission `staff.service.manage`); every matched server row there has *Start / Restart / Stop / Příkaz / Log /
Konzole* (the customer endpoints with the organization header; the live console is the token for the relay).
## Ecosystem block, part seven (audit §5p, 2026-09-14)

**Staff console page.** `/sprava/konzole/{service}` — staff with `staff.service.manage` see the last 300 lines of the
server log (refreshed every five seconds), send console commands (`command.send`), press Start / Restart / Stop /
Kill (`power`) and take a live-console token; every call goes through the customer service endpoints with the
organization header, so the audit trail is the customer's. The provisioning view's *Konzole* opens the page in a new
tab.

**Files behind checklist items.** A listing checklist item of kind `file` (e.g. *Měsíční report (PDF)*) needs an
upload before the period report: `POST /v1/partner/marketplace/orders/{id}/evidence` (multipart `key` + `file`, 10 MB,
pdf/png/jpg/jpeg/txt/csv/zip/log) stores it under `storage/app/private/marketplace-evidence/<order>/<yyyymm>/`
(one file per item and period, `period_uploads` on the order until the report consumes it); the report answers
`marketplace_checklist_incomplete` while a file item is missing. The customer's order row shows `{file, size, mime}`
per item and downloads it from `GET /v1/account/marketplace/orders/{id}/evidence/{entry}/{key}`. The portal's
*Odevzdat měsíční plnění* opens a file picker for the first missing file item.

**Nightly regressions of the vendor checks.** `onhost:nodes:check` (05:20) keeps yesterday's prerequisites on the
instance; a warning that appeared or an API that stopped answering is *Noční kontrola integrace: … se zhoršila*
(warn) in the infra inbox, an instance whose warnings all cleared *… je zase v pořádku* (info); unchanged results
stay quiet.

**The model back to the default by rule.** A partner on `oneoff` asking for `share` (`ONHOST_PARTNER_DEFAULT_MODEL`)
after `ONHOST_PARTNER_AUTO_CLEAN_MONTHS` without a rejected payout is approved by `partners.auto_approve`; `share →
oneoff` still waits for finance.

**BMC in the fleet.** `GET /v1/staff/provisioning/board` node rows carry `bmc` (temp_max_c, fans_failed, psu_failed,
psus, at) and `power_w`; the console's fleet row reads *BMC 61 °C · 410 W*, *zdroj mimo OK* / *ventilátor mimo OK*
turn the row hot.

**Bootstrap to active.** The readiness answer (`POST …/ready`) returns `activate_token` and `activate_url`; the
readiness notice carries the playbook line with them. `site.yml` ends with the play `bootstrapped` → role
`onhost_node_activate` (`ansible.builtin.uri` POST of the token and the host facts); `POST /v1/probes/capacity/{id}/activate`
puts the node `active` (capacity from the reported RAM/cores), spends the token, delivers the request and tells
operations *Uzel je aktivní*. The playbook can be run with `-e onhost_activate_url=… -e onhost_activate_token=…`.

**Game versions in the wizard.** A template with `versions` in `config/onhost.php` (`minecraft-spigot`: 1.21.8,
1.21.7, 1.21.4, 1.20.6) is offered per version in *Systém a obraz* (`key@version`, newest marked *nejnovější*);
the order carries `config.version`, the service's `MINECRAFT_VERSION` follows.
## Ecosystem block, part eight (audit §5q, 2026-09-14)

**On-call escalation.** `OnCallService` (outbox listener) opens one `oncall_alerts` row per event + subject for the
events in `config/onhost.php → oncall.events` and pages the provider behind `ONHOST_ONCALL_PROVIDER`: `pagerduty`
(Events API v2, secret `{routing_key}`), `opsgenie` (Alerts API, `{api_key}`), `webhook` (a signed envelope
`{action, dedup_key, alert}` with `X-ONhost-Signature: v1=<hmac>` to `{url, secret}`); the secret is
`ONHOST_ONCALL_SECRET_REF` in the secret store. Unacknowledged alerts re-page after `ONHOST_ONCALL_ESCALATE_MINUTES`
with a higher severity (`[eskalace n]` in the summary), at most `ONHOST_ONCALL_MAX_ESCALATIONS` times (rule
`oncall.escalate`, `onhost:oncall:escalate` every minute); the internal inbox gets *Eskalace on-call n: …* (hot).
`GET /v1/staff/oncall/alerts?state=active|all|resolved`, `POST …/{id}/ack`, `POST …/{id}/resolve {note?}`,
`POST /v1/staff/oncall/test` (staff with `incident.manage`). The pager calls back at `POST /v1/webhooks/oncall/pagerduty`
(v3 webhook, `X-PagerDuty-Signature: v1=<hmac-sha256(body, ONHOST_ONCALL_INBOUND_SECRET)>`, events
`incident.acknowledged` / `incident.resolved` matched by `incident_key`) or `/opsgenie` and `/webhook`
(`X-ONhost-Oncall-Token: <ONHOST_ONCALL_INBOUND_SECRET>`, `action: Acknowledge|Close` with `alert.alias`, or
`{action, dedup_key, by}`). Recovery events (`oncall.resolves`: `integration.recovered`,
`integration.prereqs.recovered`, `incident.resolved`) resolve the alert of their subject on both sides.

**Error tracking and traces.** `SENTRY_DSN` turns on `ErrorReporter`: every unexpected exception (never a domain
error, validation, auth or 4xx) becomes an envelope on the store API with the message and frames through the
redactor and the tags `correlation_id`, `request_id`, `actor`, `command`, `operation`. `OTEL_EXPORTER_OTLP_ENDPOINT`
turns on `Tracer`: spans `command <name>` (actor, organization, idempotency key), `operation.step <label>`
(operation, workflow, step, service) and `provider.call <provider> <action>` (instance, method, status, provider
call id) go to `{endpoint}/v1/traces` as OTLP JSON, batched per process (50 spans or shutdown), with
`OTEL_EXPORTER_OTLP_HEADERS` (`k=v,k=v`). The trace id is `md5(correlation_id)`, so the audit row, the provider
call row and the log line of one action share the trace.

**Websocket console in the staff page.** With `ONHOST_CONSOLE_RELAY_URL` the page shows *Připojit živou konzoli*:
it takes a console token, opens `wss://relay/ws/<token>`, sends `send logs` and `send stats`, prints `console
output` / `install output` / `daemon message` frames (ANSI stripped), mirrors `status`, shows CPU/RAM from `stats`,
and on `token expiring|expired` takes a fresh token and reconnects (20 tries). A command typed while connected goes
into the socket (`send command`); otherwise through `POST /v1/services/{id}/actions command.send`. *Nahrát soubor*
reads a text file (≤ 512 kB) and stores it through `gfile.save` at the given path. VNC consoles (Proxmox) keep the
token hand-off to the customer panel.

**Files on one disk.** `FileStore` reads `ONHOST_FILES_DISK` (`local` = `storage/app/private`, or `s3` from
`config/filesystems.php`); marketplace evidence (`marketplace-evidence/<order>/<yyyymm>/…`, uploads under
`marketplace-evidence/tmp`) and data exports (`exports/<org>/<request>.json`) live there. Downloads
(`GET /v1/account/marketplace/orders/{id}/evidence/{entry}/{key}`, the signed export link) answer `302` to a temporary
URL (`ONHOST_FILES_SIGNED_TTL` minutes, content type and disposition set) when the disk signs, a stream otherwise.
`onhost:files:prune` (rule `files.prune`, daily 04:25) deletes evidence older than `ONHOST_EVIDENCE_RETENTION_MONTHS`
and export files no live request points at.

**Capacity budget.** `GET /v1/staff/capacity/budget` (`capacity.read`) shows `{monthly_minor, currency,
spent_minor, remaining_minor, orders, month, source}`; `PUT` (`capacity.manage`, `{monthly_minor|null}`) sets or
clears the console override of `ONHOST_CAPACITY_BUDGET_MONTHLY_MINOR`. The Hetzner catalogue carries
`price_monthly_minor` per type (the instance's location's net price); the planner prices what it is about to order
(named `server_type`, else the smallest fitting type; `options.node_order.monthly_cost_minor` for a vendor without
prices) and stores `cost_minor` / `cost_currency` on the ordered request. Over the cap: the automatic rule leaves the
request `approved` with `budget_hold` and publishes `capacity.budget.exceeded` (finance inbox, hot); a person gets
`409 capacity_budget_exceeded` until `decision: approve, override_budget: true, note: "…"` (the note is required and
is the finance approval on the audit row).

**Turnstile.** `TURNSTILE_SITE_KEY` + `TURNSTILE_SECRET_KEY` turn it on: the boot object carries `turnstile`, the
session bridge loads `challenges.cloudflare.com/turnstile/v0/api.js` and renders one widget
(`appearance: interaction-only`, bottom right), registration and checkout payloads carry `turnstile` (or the header
`CF-Turnstile-Response`). `POST /v1/auth/register` refuses `422 turnstile_required {result: missing|fail}` while
`ONHOST_TURNSTILE_ENFORCE_REGISTER` is on; `POST /v1/checkout/guest` and `POST /v1/orders` only score it —
`turnstile_failed` (35) joins the order check's signals and the shared weight table. A verifier that does not answer
counts as a pass. The CSP adds `https://challenges.cloudflare.com` to `script-src` and `frame-src` only with a key.

**Spigot without a jar download site (§5q follow-up).** SpigotMC ships no jar and getbukkit.org is gone, so the
`minecraft-spigot` preset carries `startup: bash onhost-start.sh` plus `startup_script`: the activation step writes
the script into the server through the panel client API (`writeFile`), the first start builds Spigot with BuildTools
(`--rev $MINECRAFT_VERSION --final-name $SERVER_JARFILE`, several minutes, Java 21 image), every later start runs the
jar. The container entrypoint runs the startup command through `exec env`, so shell logic never goes into the
command itself — always into a file. A mapping without image/startup takes the preset's, then the panel egg's own
defaults (`servers.create` needs `docker_image`). `GameToolsProvider::setStartup()` changes command, image and
environment of an existing server through the application API (`PATCH /servers/{id}/startup`).

**Live power state and game logs.** `getActualState()` of a Pterodactyl server maps the daemon's state (client API
`resources`) onto `running | starting | stopping | stopped`; a power action whose target is `running` accepts
`starting` (a first start may build for minutes) and `stopped` accepts `stopping`. The staff console's log tail of
a game server is the server's log file through the client API (`logs/latest.log`, preset key `log_file`); while the
server is offline or installing the console says so instead of asking the daemon (file reads on an offline server
answer 500 and would trip the circuit breaker).
**Node limits through the panel API (§5q follow-up).** The operator never opens the panel's own UI: the console's
*Herní uzly* row has *Limity uzlu* (memory and disk in MB, `PUT /v1/staff/integrations/{instance}/game/nodes/{node}
{memory?, disk?, memory_overallocate?, disk_overallocate?, maintenance?, reason?}`) and *Změřit RAM* (`{detect: true}`:
the node's daemon reports its host — Wings `GET /api/system?v=2` with the token from
`/api/application/nodes/{id}/configuration` — and the limit becomes host RAM minus `ONHOST_GAME_NODE_RESERVE_MB`).
The panel's `PATCH /api/application/nodes/{id}` wants the whole record, so the unchanged fields are read first;
the change is `game.node.update` (`provider.instance.manage`), audited on the instance (`node.limits`), and node
discovery runs right after so the scheduler sells the new capacity at once (discovery now takes the panel's limits
over the stored ones). Disk size is not reported by the daemon — set it by hand from the console.
**Notifications in English.** `NotificationService::notify(..., locale)` runs the title and body through `Lexicon`
(Czech phrase → English, longest first, values untouched) for `en` organizations and users and stores
`notifications.locale`; `GET /v1/notifications` rows carry `locale`. `Lexicon::untranslated()` lists Czech left in a
text (the test's yardstick); a phrase missing from the table stays Czech.
## Browser smoke (Playwright)

`tests/e2e/public-checkout.spec.js` (`npm run e2e`, config `playwright.config.js`) drives the public journey in a
real Chromium: `/webhosting` → the Standard plan into the cart (monthly by default) → the drawer's total equals
`POST /v1/cart/quote` → the 12-month chip re-quotes at the yearly list price and the drawer follows → `/kosik` guest
details → bank transfer → the confirmation carries the order number, the amount and the variable symbol → the panel's
*Fakturace* lists the proforma and *Služby* the pending service → the unpaid order is cancelled through the API so the
run leaves nothing to pay behind. Locally it runs against the dev server (`E2E_BASE_URL`, default 127.0.0.1:8001) and
creates one cancelled order per run; in CI (`.github/workflows/e2e.yml`) `E2E_START_SERVER=1` boots `php artisan
serve` on a freshly migrated and seeded SQLite database with a synchronous queue. `.github/workflows/tests.yml` runs
Pint and the Pest suite. Interrupted local runs may leave an unpaid order: cancel it in the panel or with the staff
transition (`orders/{id}/transition` → cancelled).

`tests/e2e/panel-customer.spec.js` covers the signed-in side: it gets a dev login link from `php artisan
onhost:dev:login-link` (`E2E_PHP` names the PHP binary when it is not on PATH, `E2E_CUSTOMER_EMAIL` the account,
default `demo@onhost.cz`; the workflow seeds `DevAccountSeeder`, which also attaches the catalogue's plan version to the
demo web service), opens `/panel/fakturace` from the link and `/panel#/sluzba/web` on a fresh load (deep links on
boot, `SurfaceRenderer::panelSeams`), checks the service row's *plan · období · obnova · částka*, opens the service
and clicks *Platit ročně* on the *Platba a obnova* row (the confirm dialog is accepted, an order is placed), then
leaves the account as found: an unpaid order is cancelled, a paid one is switched back for free. From Git Bash pass
`MSYS_NO_PATHCONV=1` when calling artisan with `--next=/panel/…` by hand (MSYS rewrites leading slashes to a
Windows path).

## Fulfilment that waits on a node

`GET /v1/orders/{id}` carries `provisioning` while the order is PROVISIONING: the operations in flight and `stalled`
when one of them is WAITING on a transient node error (or on a second attempt). The store maps it to `stalled`, the
panel's web-order banner then says *nasazení trvá déle než obvykle, zkoušíme znovu* instead of *nasazujeme, obvykle do
90 sekund* (`SurfaceRenderer::panelSeams`), and the workbench's operations tab labels such an operation *trvá déle než
obvykle · zkoušíme znovu* with the node's message. Amounts in the panel and the public checkout keep their haléře when
they have them (`money()`/`mny()`/`czk()` seams: 3 363,80 Kč, whole crowns stay whole).

## Environment

`ONHOST_UPLOAD_MAX_BYTES` (default 2 GiB), `ONHOST_STAGING_SUFFIX` (default `web.onhost.cz`),
`ONHOST_CDN_CLOUDFLARE_SECRET_REF`, `ONHOST_ACME_DIRECTORY` / `ONHOST_ACME_CONTACT` / `ONHOST_ACME_RENEW_DAYS`,
`ONHOST_MONITORING_USER_AGENT` / `ONHOST_MONITORING_FAILURES` / `ONHOST_MONITORING_RETENTION_DAYS`,
`ONHOST_BACKUP_OFFSITE_DISK` / `ONHOST_BACKUP_DAILY_HOUR`, `ONHOST_DISCORD_APPLICATION_ID` /
`ONHOST_DISCORD_PUBLIC_KEY` / `ONHOST_DISCORD_BOT_SECRET_REF` (+ `DISCORD_BOT_TOKEN`; an empty application id
hides Discord in the panel). Development only: `php artisan onhost:dev:login-link <email>` prints a signed
one-time sign-in link (`/dev/login/{user}`, local environment, never registered elsewhere).

## Troubleshooting

* **Terminal says "the terminal was just prepared"** — first use on ISPConfig created the agent user (job queue);
  run the command again after a minute. If it keeps failing, check the jail programs and that the instance key
  (`db://provider_instances/<key>/node-shell`) is in `authorized_keys` of the agent user.
* **Deploy clone fails** — the repository must have the site's public deploy key (shown in the panel) as a
  read-only deploy key; the node needs outbound SSH to the git host.
* **Wildcard certificate fails** — the domain's DNS must be at ONhost (DNS-01); the CA error is in the
  certificate row and in `certificate.failed`.
* **CDN stays "pending"** — the registrar delegation to the edge nameservers is missing (external domains) or
  still propagating; `onhost:cdn:refresh` activates the zone when the edge reports it active.
* **Staging copy on ISPConfig is slow** — jailed accounts cannot see each other, so files relay through the
  control plane; large sites take minutes. aaPanel copies with rsync on the node.

**Game catalogue on gamepanel.onhost.cz (2026-09-14).** The `game` product sells 22 templates, each mapped by
`onhost:game:bootstrap pterodactyl-gamepanel --eggs-only` onto the panel's eggs: nest *Minecraft* (Paper, Spigot on
the Paper egg, Purpur, Vanilla, Forge, Sponge, BungeeCord, Bedrock) and nest *Onhost Gamehosting* (7 Days To Die,
Arma Reforger, Counter-Strike 2, DayZ, Enshrouded, Factorio, Hytale, Palworld, Project Zomboid, Rust Autowipe,
Satisfactory, Terraria, V Rising, Valheim). The presets `rust` and `ark` stay in config for existing mappings but are
no longer offered.

**Template requirements (§5s).** A required egg variable without a default blocks `servers.create`. The egg sync
stores them per mapping; passwords are generated, customer inputs (CS2 `STEAM_GSLT`) are asked for by the order and
checked by the quote, read-only operator variables (DayZ `STEAM_USER`/`STEAM_PASS`) come from
`db://game/operator-variables` (`php artisan onhost:game:operator-variable STEAM_USER`). A template no panel maps, or
whose operator variables are missing, is not offered; `onhost:game:templates:verify` runs daily against the panel.

**Binary uploads and virus scan (§5r-3/4).** `POST /v1/services/{id}/game-files/upload` → clamd INSTREAM → `gfile.upload`
(signed upload URL from the panel, multipart to the daemon). Infected files are deleted and reported (`files.infected`).

**After §5t.** Operator variables are stored from the console (*Šablony her → Proměnné provozovatele*, step-up).
The Startup resource flags failing customer inputs (`attention`) and the daily template check notifies the customer.
The on-call rota exports/imports iCalendar (`/v1/staff/oncall/shifts.ics`, `/import`) and reminds an hour ahead.

**After §5u.** `docker compose -f infra/docker-compose.yml up -d clamav otel-collector tempo grafana` gives a local
scanner and trace backend (Grafana on :3000, the console's *Trasa* links open Explore). Template inputs are a form with
rule hints; the rota has a personal subscription URL; operator variables older than 180 days are reported.

## Záloha před zrušením služby (audit §5aa)

Žádná služba se nesmaže dřív, než je kompletně zazálohovaná. Krok „Záloha před zrušením“ běží jako první krok akce
`terminate` a ukládá na zálohovací disk (`ONHOST_PLATFORM_BACKUP_DISK`) sadu `service-archives/<organizace>/<služba>-<čas>`:

| Rodina | Co se archivuje |
| --- | --- |
| web, managed | soubory webu (`site-files.tar.gz`) + dump každé databáze + metadata služby |
| game | záloha serveru z panelu stažená přes podepsanou URL (`game-backup.tar.gz`) + metadata |
| mail | poštovní doména, schránky, aliasy a veřejný DKIM klíč (obsah schránek panel neexportuje) |
| cloud, data | chráněný snapshot u poskytovatele (obraz disku se nepřenáší), jeho id a velikost v záznamu zálohy |

Sada má `manifest.json` s SHA-256 každé části, záznam v `backups` je `kind=final`, `protected`, `verify_status=ok`
a drží se `ONHOST_SERVICE_ARCHIVE_DAYS` (60) dní; po expiraci ji maže `onhost:backups:run`. Když kterákoli část selže,
operace skončí chybou a **nic se nemaže**. Výjimku má jen obsluha: `service.terminate` s parametrem
`archive_before_delete=false` a důvodem v `archive_skip_reason` (zapíše se do auditu).
