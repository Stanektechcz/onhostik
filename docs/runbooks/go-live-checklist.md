# Go-live checklist

Everything the control plane needs before the first paying customer. Each line names where it is configured and
how it is verified; nothing here is optional for production. Run through it top to bottom on the production host.

**Status** (2026-09-26, stack tip of TASK-0027 with TASK-0029 … TASK-0031; nothing is deployed to production yet): **done** — in place and
verified in the repository or CI; **open** — work or procurement still missing; **operator** — the code is ready, an
operator does it on the host at or after deploy; **owner-decision** — waits for the owner's decision; **legal** — waits
for a lawyer. Every new behaviour that reaches existing services ships switched off (ADR-0007): section 6 lists those
switches with the read-only command to run before each; section 7 lists the operator steps of Phase 0 of the
permission program (2026-09-27, TASK-0033 … TASK-0041), the forensic baseline first.

**Status of sections 0, 9 and 10 on 2026-10-07 (phase I, I7 preparation, TASK-0132).** Filled from the repository only: the command
and the doctor row each step names were looked up in the code on `development` at `c0fc7ff1` (CI green). **Nothing has been run on
staging or production yet** — the staging rehearsal ([staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md), protocol
[staging-rehearsal-2026-10-07.md](staging-rehearsal-2026-10-07.md)) is where each *operator* row is first proved on a real host; its
step is named in the status cell (R0–R24). What still blocks production is listed in *Blocking for production* at the end of
section 10.

## 0. Operator steps in order (E11)

One ordered list for the day of go-live. Run `php artisan onhost:doctor` after every step: each row that is not OK carries a
*Remedy* column (and a `remedy` field in `--json`) with the command or setting that fixes it. Nothing here is run from a
workstation against production, and no step prints a password, a TOTP secret or a token into a ticket or chat.

| # | Step | Command / setting | Doctor result expected afterwards | Rollback | Status (2026-10-07) |
| --- | --- | --- | --- | --- | --- |
| 0 | **Before the deploy that carries H0** (owner decision H-R3 switches `services.reinstate` on by default): see who is affected while the old release still runs | `php artisan onhost:billing:reinstatement-audit` (read only) on the running release; keep its output with the release record (`docs/runbooks/release-and-rollback.md`). After the deploy bill every listed undone cancellation one service at a time: `php artisan onhost:billing:reinstatement-audit --apply --service=<id>` | *pay and restore (services.reinstate)* OK | switch the rule off in Automatizace (`PUT /v1/staff/automation/services.reinstate`); nothing is billed by the switch itself | operator, not run — `ReinstatementAudit.php`, doctor row in `Doctor.php`; rehearsal R13 |
| 1 | PHP binary. The deploy and the queue/scheduler units use one PHP 8.3+ CLI | deploy with `PHP=<path>` (staging-launch.md S7: `PHP=/www/server/php/83/bin/php … onhost-deploy`), the same path in `infra/systemd/*.service` | *PHP binary is the one the deploy and the workers must use* OK (shows version and path) | none needed; redeploy with the right `PHP=` | operator, not run — row in `GoLiveChecks`; rehearsal R2 |
| 2 | Proxy addresses — **decided (H-R2, 2026-10-06): no proxy and no CDN in front of the origin**; the only proxy is aaPanel's own nginx on the same host | nothing to set: without `TRUSTED_PROXIES` only `127.0.0.1` and `::1` (the local nginx) are trusted (`bootstrap/app.php`). An explicit empty value trusts nothing (right while nginx talks FastCGI to PHP-FPM). If you set it, set it as a real process environment variable (systemd unit / FPM pool env; **not** `.env`: `bootstrap/app.php` reads `env()` and `config:cache` skips `.env`). Never `*`; a CDN or proxy in front is a new owner decision first | *trusted proxies are exact addresses* OK ("only the local aaPanel nginx …"); a wildcard is FAIL, another address WARN in production | unset the variable and `config:cache` | decided (H-R2), code done (`bootstrap/app.php` loopback default); operator verifies the row — rehearsal R4 |
| 3 | Shared cache store for rate limits | `CACHE_STORE=redis`, own `CACHE_PREFIX`, `config:cache`, restart the queue workers | *cache store is shared (rate limits)* OK | set the previous store back; limits become per worker again | operator, not run — row in `GoLiveChecks`; rehearsal R5 |
| 4 | Catalogue revisions: read first, then apply (four eyes apply unless `ONHOST_FOUR_EYES=false`) | `php artisan onhost:catalog:revise` (dry run), then `php artisan onhost:catalog:revise --apply`; the revision of the web plans alone: `onhost:catalog:revise 2026-10-deliverable-web-plans [--apply]` | *every catalogue revision is applied* and *catalogue revision 2026-10-deliverable-web-plans applied* OK | revisions publish new plan versions, never edit old ones; undo = a new revision/plan version through the catalogue, customers keep what they hold | operator, not run — revisions in `CatalogRevisions` (web plans, `2026-10-penpot-on-sale`); rehearsal R6/R7, R9/R20 |
| 5 | Exchange rates of the national bank | `php artisan onhost:fx:sync` once, then the scheduler keeps it (weekdays 14:40, daily 06:10) | *exchange rates are fresh* OK | none (data only) | operator, not run — `onhost:fx:sync`; rehearsal R11 |
| 6 | Staff authenticators. **The owner enrols on the server and types every password and code himself**; nobody else sees a secret | per staff account: `php artisan onhost:staff:totp <email>` (secret goes into the authenticator), then `php artisan onhost:staff:totp <email> --code=<6 digits>`; recovery codes are shown once, the owner stores them | *staff and demo accounts have an authenticator* OK (count 0 missing) | `--reset` replaces a lost authenticator; `ONHOST_STAFF_MFA_REQUIRED` stays `true` | operator (owner types every secret), not run — `StaffTotp.php`; rehearsal R10 |
| 7 | Capacity basis (decision 19) | read `php artisan onhost:capacity:basis`, then `ONHOST_CAPACITY_DISK_BASIS=sold` and `config:cache` | *capacity basis as decided (disk sold, RAM and CPU measured)* OK | back to `measured` | operator, not run — `CapacityBasisReport.php`, row in `CapacityDoctor` |
| 8 | Turnstile keys (presence is judged, values never shown) | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `config:cache` | *Turnstile protects registration and public forms* OK | empty both and set `ONHOST_TURNSTILE_ENFORCE_REGISTER=false`, `ONHOST_TURNSTILE_ENFORCE_FORMS=false` deliberately | open — Turnstile keys not procured yet; row in `Doctor.php` |
| 9 | API base URL for the documentation (information only) | `ONHOST_API_BASE_URL=https://api.<domain>/v1` if the API answers on its own host | *API base URL for the documentation* shows the value | unset | operator, optional — row in `GoLiveChecks` |
| 10 | R9 API tokens must name an organisation. **Order matters**: first look, then tell the holders, then switch | `php artisan operator:tokens:unbound --dry-run` (and `--past-cap`), contact the holders, then `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` and `config:cache` | *API tokens must name an organisation (R9)* OK | set the switch back to `false` and `config:cache`; tokens work as before | operator, not run — `TokensUnbound.php`, row in `GoLiveChecks`; rehearsal R12 |
| 11 | Phase G, part 1 — **the legal entity and the VAT mode, before the first document is issued** (a document keeps the seller it was frozen with). Owner decision first: is the seller a VAT payer on the day of the first invoice? Details: section 10, steps G-1 to G-3 | real DIČ in the legal entity (`onhost:production:prepare --legal`), `ONHOST_VAT_PAYER`, `onhost:vat:payer-mode` (read), staff route with a second person | *VAT payer mode agrees with the legal entity* OK (both the row of `documents` and this one) | before any document exists: the same route with the other mode; afterwards only a new document is affected, never an issued one | decided (H-R0: not a VAT payer); operator `production:prepare --legal` with the real values — rehearsal R14; accountant questions open |
| 12 | Phase G, part 2 — **the infrastructure of custom ISO, before any plan sells it**: clamd, the image volume, PHP/nginx limits, the Proxmox storage. Section 10, steps G-4 to G-7 | the server steps 1–4 of `custom-iso.md`, then `php artisan onhost:isos:scanner-check` | both ISO rows stay OK **without looking at clamd or the disk** while nothing sells custom ISO (steps G-4 to G-7 are therefore verified **by hand**: `onhost:isos:scanner-check --fresh`, mount, owner, mode, `df`); the rows judge them for real only after step 13 | leave the catalogue revision of step 13 unapplied: nothing is sold, nothing is uploaded | operator, not run — infrastructure (clamd, ISO volume, Proxmox storage) not built; rehearsal R15–R17 |
| 13 | Phase G, part 3 — **the catalogue revision `2026-10-custom-iso`, last of the ISO steps** (it makes custom ISO sellable; read it, then apply, four eyes apply). Section 10, step G-8 | `php artisan onhost:catalog:revise 2026-10-custom-iso` (dry run), then `… --apply` | the dry run lists every plan with `custom_iso null → true`; after the apply the two ISO rows above are judged for real | publish the previous plan versions in the plan editor; customers on the new versions keep their images (the way out stays open) | decided (H-R4); operator only after step 12 is green — rehearsal R18 (owner decision on the order: phase I item 9) |
| 14 | Phase G, part 4 — **loyalty**: the owner's rules in `config/loyalty.php`, the daily expiry in the scheduler. Section 10, steps G-9 and G-10 | `php artisan schedule:list` shows `onhost:loyalty:expire` at 05:35 | *loyalty expiry runs and balances are sane* OK | none (data and schedule only) | done in code (`config/loyalty.php`, scheduler 05:35); operator checks `schedule:list` |
| 15 | Phase G, part 5 — **the webhook lane and the secret overlap**: `onhost-queue@webhooks` running, the overlap minutes chosen. Section 10, steps G-11 and G-12 | `systemctl enable --now onhost-queue@webhooks`, `ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES` (0–1440, default 60) | *rotated webhook secrets overlap only briefly* OK | stop the unit: deliveries fall back to the default lane | operator, not run — `infra/systemd/onhost-queue@.service`; rehearsal R21 |
| 16 | Phase G, part 6 — **dead letters are heard**: the rules of `infra/monitoring/slo-alerts.yml` loaded and routed. Section 10, step G-13 | `promtool check rules infra/monitoring/slo-alerts.yml`, reload Prometheus, a route for `severity: ticket` and `page` in Alertmanager | *no outbox dead letters* OK and `onhost_outbox_dead_letters` visible in Prometheus | remove the rule file from the Prometheus configuration | operator, not run — `infra/monitoring/slo-alerts.yml`; needs a Prometheus/Alertmanager to load it; rehearsal R22 |
| 17 | Final doctor | `php artisan onhost:doctor` | 0 FAIL; every remaining WARN has a remedy in its row and an owner decision in section 6/7 | n/a | operator, not run — rehearsal R23 |

Rows are WARN outside production and FAIL in production only where the row is blocking (a PHP older than 8.3, the wildcard proxy, the older `staff MFA required` row
and the other rows marked blocking in the doctor); the rest are standing WARNs that this list clears.

## 1. Platform

| Item | Where | Verify | Status |
| --- | --- | --- | --- |
| `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generated, `APP_URL` = public origin | `.env` | `php artisan about` | operator |
| PostgreSQL + Redis (cache, queue, sessions), `QUEUE_CONNECTION=redis` | `.env` | `php artisan migrate --force`, `php artisan queue:monitor` | operator |
| Queue workers per provider queue and the scheduler as systemd units | `infra/systemd/*.service` | `systemctl status onhost-queue@default onhost-scheduler` | operator |
| Sanctum stateful domains = the public origins of the surfaces | `SANCTUM_STATEFUL_DOMAINS` | sign in on `/prihlaseni`, `GET /v1/me` returns the user | operator |
| Security headers / CSP, HTTPS only, `upgrade-insecure-requests` (automatic on HTTPS) | `App\Http\Middleware\SecurityHeaders` | `curl -I https://…/panel` | done (code); verify on host |
| Secrets driver `bao` (OpenBao) or `db` (encrypted with `APP_KEY`); `env` is refused in production | `ONHOST_SECRETS_DRIVER` | `php artisan onhost:doctor` (see below) | operator |
| CA bundle for outbound TLS (system bundle on Linux; `curl.cainfo` on Windows workstations) | php.ini | provider probes pass | operator |
| Metrics endpoint token + Prometheus scrape, `/healthz` behind the load balancer | `ONHOST_METRICS_TOKEN`, `infra/monitoring` | `curl -H "Authorization: Bearer …" /metrics` | operator |
| Prometheus rules → Alertmanager → `POST /v1/webhooks/alertmanager` (bearer `ONHOST_ONCALL_INBOUND_SECRET`): burn rate, outbox lag, provider down, dead automation, stale backup, clamd, on-call; Grafana dashboard *ONhost · provoz* provisioned from `infra/monitoring/grafana/dashboards` | `infra/monitoring/{slo-alerts,alertmanager}.yml`, `docker-compose.yml` (alertmanager, grafana) | fire a test rule; the alert appears under *Incidenty → on-call* and pages | open |
| Log shipping (Loki) and error tracking | `LOG_CHANNEL`, `infra/monitoring` | a test error appears in the sink | open |
| Backups of the database and `storage/app/private` (invoice PDFs, evidence, exports) — `onhost:platform:backup` daily 02:15, `onhost:platform:backup:verify` 03:15, `ONHOST_PLATFORM_BACKUP_DISK` = an S3-compatible disk off the server, retention `ONHOST_PLATFORM_BACKUP_RETENTION_DAYS` | `.env`, rule `platform.backup` | `onhost:doctor` → *platform backup verified within 26 h* OK; a restore drill: `pg_restore --clean --if-exists -d onhost database.pgdump` on a staging host | open (off-server S3 bucket, restore drill) |
| Production preparation: development accounts purged, legal entity from `ONHOST_LEGAL_*` / `ONHOST_BANK_*`, config/route/event caches | `php artisan onhost:production:prepare --purge-dev-accounts --legal --cache` | the command ends with the doctor; 0 FAIL | operator |
| Static analysis and dependency audit in CI (`vendor/bin/phpstan analyse`, `composer audit`) | `.github/workflows/tests.yml`, `phpstan.neon` (Larastan level 5, baseline = today's typing debt) | the workflow is green on the release commit | done |
| Catalogue revisions applied — `2026-09-honest-promises` (TASK-0022: plans stop promising PITR, a connection count, a dedicated IP/DB; `1h` backups become `hourly`), `2026-09-limit-raise` (the product `limit-raise`), `2026-09-shared-php-workers` (TASK-0027: shop-peak stops promising dedicated PHP workers). Customers keep the versions they hold | `php artisan onhost:catalog:revise` (dry run), then `--apply`; docs/runbooks/pricing.md *Catalogue revisions* | `onhost:doctor` → *every catalogue revision is applied* OK | operator |
| Deletion lifecycle (audit §5ab): restore window, archive retention, identity points and the download fee | *Nastavení systému → Životní cyklus služeb*, `config/onhost.php` `services.deletion` | `onhost:doctor` → the *lifecycle* rows are OK; `onhost:services:archive <service> --create` proves the archive path of every panel | open (staging lifecycle run first) |
| Service archives on the backup disk, re-hashed against their manifest and pruned after the retention | `ONHOST_PLATFORM_BACKUP_DISK`, `onhost:backups:run` (every 15 min), `onhost:services:purge` (daily 03:40) | `onhost:doctor` → *archive disk writable*, *archives verified*, *nothing past its restore window* | operator |
| Secret scan and dependency advisories in CI, pre-commit hook enabled in every clone | `.github/workflows/security.yml`, `.gitleaks.toml`, `git config core.hooksPath .githooks` | the security workflow is green; a staged `.env` is refused locally | done |

## 2. Providers (Nastavení systému → Integrace providerů)

| Item | Verify | Status |
| --- | --- | --- |
| Proxmox instance per region with API token, `storage`, `bridge`, `backup_storage` (the Proxmox storage ID backups go to; the code reads `backup_storage`, not `pbs_datastore`); nodes imported (Discover) | Probe UP, nodes listed with capacity, prerequisite `backup_storage` OK | open (only lab instances today) |
| PBS datastore reachable from the Proxmox instance | backup of a test VM completes | open |
| ISPConfig remote user with client, sites, mail, dns, server, monitor functions; `server_id` resolved | Probe UP with `permissions.server = true` | operator |
| aaPanel API key, control-plane egress IP on the allow-list, certificate pinned if self-signed | Probe UP with the panel version | operator |
| Pterodactyl application key, nodes and allocations registered, eggs mapped | Probe UP, a test game server provisions | operator (keys stored on the dev control plane; store them again in production and rotate) |
| PowerDNS API key, NS set in option `nameservers`, secondaries receive NOTIFY | zone commit shows the serial on both servers | open (only lab) |
| WEDOS WAPI login + password, `WEDOS_TEST_MODE=false`, control-plane IP allowed | `wapi ping` UP, a `.cz` availability check answers | operator (production IPv4 on the allow-list) |
| Subreg API user + password, control-plane IP allowed; registrar price book filled (*Registrátoři domén → Aktualizovat ceníky z API*, WEDOS costs entered by hand), TLD pins reviewed | `onhost:doctor` shows `registrar available` and `registrar cost prices known for every TLD` OK; the matrix shows a winner per TLD | open (the API login was refused with `500.104`, audit §2 item 6) |
| RKE2 service account token + cluster CA pinned | Probe UP, `apps.create` capability on | open (only lab) |
| IPAM pools per region for VPS addresses | `POST /v1/staff/ipam/pools`; a VPS order no longer waits with `ipam.exhausted` | operator |
| Console relay key shared with the relay service | `ONHOST_CONSOLE_RELAY_KEY`; opening a console in the panel connects | open (relay service to deploy) |
| Server and database backups (`backups.compute`, owner decision 1): `php artisan onhost:backups:compute-plan` shows no `MISSING` backup storage, a staging VM backup carries `onhost backup:<id>` in its notes, then the rule is switched on (Automations → "Zálohy serverů a databází podle plánu") — see [backups.md](backups.md) | after 24 h `onhost:doctor` shows the `backups:` rows and `every server sold backups has one from the last 3 days` OK | owner-decision, then operator |
| Backups as sold (`backups.as_sold`, owner decision 18): `php artisan onhost:backups:frequency-plan` reviewed (services that change, extra storage against the backup disk), retention meaning confirmed by the owner, then the rule is switched on | `onhost:doctor` row `backups: every plan is backed up as often and as long as sold` OK; the backup disk has room for the estimate | owner-decision, then operator |
| Panel versions (H530): every panel runs a version its adapter was verified on, or one an operator accepted on passing checks | `php artisan onhost:integrations:versions` shows no `held` and no `baseline`; ISPConfig and aaPanel report a version (see [panel-upgrade.md](panel-upgrade.md)) | operator |

## 3. Money and documents

| Item | Verify | Status |
| --- | --- | --- |
| Comgate merchant + secret (`env://COMGATE` or the secret store), `COMGATE_TEST=false` (or the administration's mode switch, H-R8), callback IPs | *Ověřit spojení* and *Testovací platba 1 Kč* in Nastavení → Integrace pass ([comgate.md](comgate.md)); a 1 CZK card top-up settles and appears in the wallet | open |
| Bank account (`ONHOST_BANK_IBAN`, `ONHOST_BANK_BIC`, `ONHOST_BANK_ACCOUNT`) printed on proformas and top-up instructions | a proforma PDF shows the account and QR code | open |
| Bank statement import matches by variable symbol | a test transfer settles the proforma and starts fulfilment | open (`ONHOST_BANK_FIO_TOKEN`) |
| Legal entity, VAT registration, document series (`FV`, `PF`, `DK`, `PP`, `TU`), `ONHOST_LEGAL_ENTITY` | `php artisan db:seed --class=LegalEntitySeeder` on the production values | open (placeholder values today) |
| Tax rules (CZ 21 %, OSS, reverse charge) and VIES: `ONHOST_VIES_ENABLED=true`, `ONHOST_VIES_REQUESTER_VAT_ID` = the operator's DIČ, `ONHOST_VIES_PER_MINUTE` (150) reviewed, and rule `tax.vies_recheck` switched on **together with** the switch (without it a verified customer pays destination VAT from its first renewal more than 30 days after its check) ([vat-and-vies.md](vat-and-vies.md)) | `onhost:doctor` area `tax` green (incl. the re-check row); a DE B2B quote with a verified VAT ID shows reverse charge; `onhost:vat:verify` (dry run) reviewed, its `name_mismatch` group handed to finance | operator + accountant (wording, past invoices, partner self-billing paid gross) |
| Documents in EUR: the control plane reaches `www.cnb.cz` (exchange rate list, public) and the accountant confirmed the daily ČNB rate | `php artisan onhost:fx:sync` stores today's list; an EUR invoice prints "Kurz ČNB" and its VAT in CZK; `onhost:doctor` area `money` is green | operator (accountant confirms the ČNB rate) |
| Dunning ladder (`onhost.billing.dunning`) matches the terms in the panel (14 days) | `docs/runbooks/billing-dunning.md` | legal (terms must match) |
| Transactional mail (`MAIL_*`), templates rendered in Czech and English, SPF/DKIM/DMARC of the sending domain | request a password reset on `/prihlaseni`, run `php artisan onhost:mail:send`, check the message and its authentication headers | open |

## 4. Content and legal

| Item | Verify | Status |
| --- | --- | --- |
| Terms, privacy, DPA, SLA, registrar terms published with version numbers (checkout consents) | `/v1/catalog` lists the document versions the cart requires | legal |
| Status page components and locations | `/stav` renders, incidents post to it | operator |
| Knowledge base, changelog and marketing copy reviewed (no prototype narrative) | `docs/ui/data-seams.md` "prototype-only" list is empty or accepted | open |
| Dev accounts removed (`DevAccountSeeder` refuses production; verify no `*@onhost.cz` demo users exist) | `php artisan tinker` → `User::where('email','demo@onhost.cz')->exists()` is false | operator |

## 5. Smoke tests on the production host

```bash
php artisan test --compact tests/Feature/Http/EndpointSweepTest.php   # every /v1 route answers by contract
php artisan onhost:integrations:health                                # probes every provider instance
php artisan onhost:openapi && git diff --stat contracts/               # the published contract matches the routes
```

Then, as a real customer: register, order web hosting by bank transfer, pay the proforma, watch the service turn
ACTIVE, open a ticket, download the invoice PDF and cancel the service. Every step is audited (`/v1/organizations/{id}/audit`).

## 6. Switches and operator steps of the stack TASK-0017 … TASK-0031

Everything below ships **off** (an AutomationLedger rule with `default_off`, or an `ONHOST_*` switch whose default keeps
the old behaviour) or as an operator command with a dry run; the exceptions are named (the password-change switch, the
backup tick, and the authorization tightening of TASK-0029/TASK-0030, which changes no service or customer but refuses
more at deploy). Run the read-only command first,
switch on second, then watch `onhost:doctor`. Rules are switched in the staff console (Automatizace, fresh step-up);
`ONHOST_*` switches go into the server's environment followed by `php artisan config:cache`.

| Step | Read first | Switch / command | Verify | Status |
| --- | --- | --- | --- | --- |
| Roles after deploy: billing_admin gains `billing.wallet.spend`, only `owner` holds `service.panel_account.manage`, an org_admin can no longer grant billing_admin (TASK-0021) | release notes of TASK-0021 | `AuthorizationSeeder` runs in `infra/aapanel/deploy.sh`; migrations `000860` (usage samples) and `000870` (withdrawals) only add tables | `onhost:doctor` → *roles in the database match the catalog* OK | operator |
| A solo owner runs without a second person — **before** deploying owner decision 13, or every price change waits for nobody | `docs/runbooks/approvals.md` (*One operator alone*) | `ONHOST_FOUR_EYES=false` | the doctor reports the mode; critical actions are audited `waived:single-operator` | owner-decision |
| Password change ends personal API tokens (owner decision 14) — **on by default**, the one exception | tell customers whose integrations run on a personal token | `ONHOST_PASSWORD_CHANGE_REVOKES_API_ACCESS` (default `true`; `false` = old behaviour) | a password change revokes the user's personal tokens only | operator |
| Catalogue revisions (decisions 2, 4, 5, 6, 7, 8, 11, 18) | `php artisan onhost:catalog:revise` (dry run) | `php artisan onhost:catalog:revise --apply` | *every catalogue revision is applied* OK (row in §1) | operator |
| Server and database backups `backups.compute` (decision 1) | `php artisan onhost:backups:compute-plan` — no `MISSING` backup storage | set `backup_storage` per Proxmox instance, then the rule on | `backups:` doctor rows (§2) | owner-decision |
| Backups as sold `backups.as_sold` (decision 18) | `php artisan onhost:backups:frequency-plan` — extra storage against the backup disk | rule on | *every plan is backed up as often and as long as sold* | owner-decision |
| Every web/managed/mail service in every backup tick (TASK-0019; before, only the first 100 by id) — **no switch, active with the merge** | the last line of `onhost:backups:compute-plan` (how many services are newly visited) | none | backup tick age and budget rows | owner-decision (open, `docs/runbooks/backups.md`) |
| Mailbox backups `mail.backup_retention` (decision 3) | staging ISPConfig: `mail_user_add`/`mail_user_update` accept `backup_interval=daily` and `backup_copies`; size the mail server's backup directory; `php artisan onhost:mail:backup-retention` (dry run) | rule on, then `onhost:mail:backup-retention --apply --service=<id>` per service; `--allow-prune` only per service after telling the customer | *mail backups: mailboxes keep the backups the plan sells* | owner-decision |
| Disk capacity by what is sold (decision 19) | `php artisan onhost:capacity:basis` | `ONHOST_CAPACITY_DISK_BASIS=sold` | doctor capacity rows; new web orders not refused `capacity_sold_out` without reason | operator |
| Usage watch over every service `usage.rotation` (decisions 9, 12) | `php artisan onhost:metering:preview` | rule on; new metrics count only with `ONHOST_METERING_ENFORCE_NEW_METRICS=true` | `service_usage_samples` grows, `onhost:metering:rollup`/`prune` run | operator (rotation), owner-decision (new metrics) |
| Plan total of files, databases and mail (decision 10) | staging: `databasequota_get_by_user` on a test ISPConfig; then `php artisan onhost:usage:disk-total-notice` (dry run) | `ONHOST_WEB_DISK_TOTAL_DATABASE_SIZES=true`; `onhost:usage:disk-total-notice --send`; `ONHOST_WEB_DISK_TOTAL_PARTS_VERIFIED=true`; `ONHOST_WEB_DISK_TOTAL_ENFORCE_FROM=<date>` at least `ONHOST_WEB_DISK_TOTAL_NOTICE_DAYS` (30) after the notice | the plan total shows no `partial` for ISPConfig sites; only noticed or newer services count it | owner-decision (date and terms wording) |
| Customer approval of credit orders (decision 20) | `php artisan onhost:orders:credit-approval-report` | `ONHOST_ORDER_CREDIT_APPROVAL=true` | a member's credit order waits for approval; `credit_spend_not_allowed` for immediate credit payments | owner-decision |
| Paid limit raises for customers, renewals of other add-ons (decision 8) | `onhost:limit-raise list` | `ONHOST_LIMIT_RAISE_CUSTOMER_ORDERS=true`. Add-on renewals need no switch: `ONHOST_ADDON_RENEWALS` is **on by default** (owner decision R12; `false` = the old single payment) and applies to new orders only — an add-on sold before keeps its one payment; ending an IPv4/CDN add-on still does not reach the panel | *every limit raise is billed or approved* | owner-decision |
| Pay and restore `services.reinstate` (decision 23; **on by default since H-R3, 2026-10-06**) | `php artisan onhost:billing:reinstatement-audit` **before the deploy** (section 0, step 0) | nothing to switch (staff can still switch it off in Automatizace); `--apply --service=<id>` one service at a time for undone cancellations that ran unbilled before the deploy | restored services keep their auto-renew (off stays off) | operator |
| Consumer withdrawal `billing.withdrawal` (decision 17) | legal review of `resources/legal/LEGAL_REVIEW_withdrawal.md` and the terms | `ONHOST_WITHDRAWAL_LEGAL_REVIEWED=true`, then the rule on | *consumer withdrawal reviewed by a lawyer*, *consumer withdrawals move on* | legal |
| Was the ISPConfig ownership hole used before TASK-0005? (decision 16) | a production copy on staging | `php artisan onhost:audit:provider-calls` (read-only; report under `storage/app/private/reports`) | no CRITICAL row, or the incident steps of `docs/runbooks/provider-calls-audit.md` | operator |
| Staging lifecycle run and the lost site | `docs/context/CURRENT_STATE.md` (*Next decision*) | `onhost:services:archive <service> --create` on each panel, then the purge; `onhost:ispconfig:restore-site` for `s4s.electree.cz` | archives verified; the site answers again | open |
| Service actions and API tokens tightened (TASK-0029, TASK-0030, ADR-0008) — **no switch, active with the merge**; no service, customer or plan changes | the release note below; tell customers whose integrations use API keys | none (roles re-seeded by `AuthorizationSeeder`: only the descriptions of `svc_manage` / `svc_console` change) | an old "operate services" key gets 403 `The API token lacks the services:console scope.` on `…/console-token`; staff dunning run, capacity run and panel login ask a step-up | operator (announce before deploy) |
| VIES and reverse charge (TASK-0031) — switch on together with the re-check | `docs/runbooks/vat-and-vies.md`; `php artisan onhost:vat:verify` (dry run: no HTTP call, no write) | `ONHOST_VIES_ENABLED=true` and `ONHOST_VIES_REQUESTER_VAT_ID=CZ<our DIČ>` (then `php artisan config:cache`, restart the queue workers); **at the same time** the rule `tax.vies_recheck` on (`default_off`, daily 04:20 `onhost:vat:recheck`) — without it a customer verified at the order pays destination VAT from its first renewal more than 30 days later | `onhost:doctor` area `tax`: the first and the last row green; a DE business quote with a verified VAT ID shows `AE` 0 % (row 61 in §3) | operator |
| Accountant sign-off (D31.9) — **before** `--apply` | the past-invoice list: `php artisan onhost:vat:verify --csv=vat-<date>.csv` (written to `storage/app/private/reports/`, cells that start a formula neutralised) | the accountant signs off: the reverse-charge legend and the line with both VAT IDs and the consultation number ("registrace k DPH doložena mimo VIES" under an override); the past VAT invoices to EU business customers (never changed; a correction is a new document); partner self-billing categories (S at `standard_rates.CZ`, AE, E) incl. the *identifikovaná osoba* case and the gross payout with input VAT on `liability:vat`; "Registrace dodavatele k DPH neověřena."; SLA credits following the credited line; retention of `vat_validations` on erasure | written sign-off kept with the release | accountant + legal |
| Existing organizations and partners checked (TASK-0031) | the dry run's groups: unchecked, stale, legacy, `partner`, `name_mismatch`, `supplier_identity` | `php artisan onhost:vat:verify --apply` after the sign-off, and before the first partner payout after go-live; `name_mismatch` and `supplier_identity` go to finance (a Czech partner is paid VAT only after finance confirms the supplier with the override, four eyes) | the dry run again lists only the finance groups; doctor row *no EU business customer with a VAT ID waits for a VIES answer* OK | operator + finance |

### Release note: service actions and API tokens (TASK-0029, TASK-0030)

For the announcement to customers (in Czech from TASK-0030's handoff, `.ai/handoffs/TASK-0030.md`) and the pull request.
No existing service, customer or plan is changed; this is a tightening of authorization that applies at deploy.

- API keys: consoles, the terminal, running commands and SSH keys are a scope of their own, `services:console`. Keys created
  earlier ("operate services", "all scopes") no longer open them; create a new key with the scope ticked. High-risk actions
  (terminating a service, restoring a backup, deleting backups, game backups, snapshots, databases or staging copies,
  pushing staging, restoring an archive, DNSSEC) never go through an API key — only in the portal with a fresh
  confirmation. Downloading the final archive of a cancelled service works in the portal only.
- Deleting backups, game backups and VM snapshots takes its own permission and a fresh step-up (owner, org admin; a game
  operator for game backups, a cloud operator for snapshots) — also through the backup schedule: keeping fewer days or
  generations, or a more frequent schedule after which the kept history reaches less far back, counts as deleting.
  Unlocking a game backup takes `game.manage`. `developer`, "Service: manage" guests and `services:power` keys lose these.
- A fresh step-up is now asked for `database.delete`, `staging.push`, `staging.delete`, `archive.restore`, `backup.delete`,
  `gbackup.delete` and `snapshot.delete`.
- "Service: manage" guests can no longer reset root access, start rescue mode, create game sub-users or schedule console
  commands. SSH keys and game sub-users of somebody who loses the console are revoked — when a role or a share ends, and
  when a service is shared again without the console (the organization sees "Konzole služby odebrána"). A guest with the
  "Service: console" role now gets the console the role promises.
- Stored action hooks for `staging.push` stop with `step_up_required` (the hook stays enabled and says why); the Discord
  "push staging" button and the assistant's "replace production with staging" proposal are gone; a repeated click on the
  same Discord button no longer starts the action twice. Spec apply reports refused steps in `skipped`.
- Staff: running dunning or the capacity pass by hand and signing into a customer's panel ask for a fresh step-up.

## 7. Operator steps of Phase 0 of the permission program (TASK-0033 … TASK-0041)

The program is `docs/security/permission-program-2026-09-27.md`, its decisions ADR-0009. Pure security fixes apply at deploy
without a switch (they refuse only illegitimate requests); everything that takes away legitimate behaviour from existing
customers is an operator command whose default is a dry run. **The forensic baseline comes first**, because the fixes and
some `--apply` steps change the state the look-back reads. **Phase 0 is not signed off:** TASK-0039 (P0-08, P0-09, P0-14) is
on the chain, but staff reach on customer keys and tokens bound to no organization stay allowed (only logged) until the two
switches below are on, and `GET /v1/me` still hands a token its person's other organizations — the open list is
`docs/runbooks/breach-register.md`.

| Step | Read first | Switch / command | Verify | Status |
| --- | --- | --- | --- | --- |
| Forensic look-back **baseline** — on production **before the first Phase-0 deploy**, or on a restore of the last backup taken before it (TASK-0038, D16). Blocks go-live | `docs/runbooks/breach-register.md` (mandatory baseline, *Isolation for every run*: SELECT-only database role, separate checkout, array mailer, null queue, no outbound network on a restore host) | `php artisan onhost:forensics:lookback --json` (read-only; exit 0 = no hit, 1 = hits, 2 = bad options) | the JSON's sha256 attached first to the cyber incident under legal hold; every hit followed up per source; the owner's Art. 33/34 decision recorded — drafts only, nothing sent without it (O3) | operator, owner-decision |
| Deploy Phase 0: `AuthorizationSeeder` (staff read keys, `partner.portal.read`, the partner payout keys) and migration `000890` (payout accounts + the cut-over row that ends IBAN grandfathering) | release notes below | `infra/aapanel/deploy.sh` (runs the seeder); no switch | `onhost:doctor` → *roles in the database match the catalog* OK | operator |
| Token listing — who loses what through an API token (TASK-0037, IF-13) | release notes below | `php artisan onhost:iam:risk-floor-report --days=30` (read-only) — tokens and service accounts that ran an operation now HIGH+; `php artisan operator:tokens:unbound --dry-run` (tokens bound to no organization, and tokens used on another organization — refused from this release with `token_organization_mismatch`) | the owners of every listed token were told before the release | operator |
| Tokens bound to no organization (TASK-0039, PA-04) — closes a breach-register entry | the `operator:tokens:unbound --dry-run` list and the notice above | `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` (default off) | a token stored with no organization gets 403 `token_unbound`; strike `PA-04` in `breach-register.md` | operator |
| Every API token ends (TASK-0044, owner decision R9 of the 2026-10 audit) | `config/onhost.php` → `tokens`, `domains/Identity/Tokens/TokenLifetime.php` | `ONHOST_TOKEN_DEFAULT_DAYS=365`, `ONHOST_TOKEN_MAX_DAYS=365` (the defaults in code; lower the cap and the default follows). Then `php artisan operator:tokens:unbound --past-cap` (read-only) lists live tokens with no end or ending after the cap — made before R9; nothing shortens them, their owners are told to rotate | a new token without `expires_in_days` ends after 365 days, one asking for more than the cap is cut to it (the HTTP form answers 422), an expired token gets 401 | operator |
| Order of the token switches (R9) | the two rows above | **first** run `operator:tokens:unbound --dry-run` and send the notice to every listed owner; **only then** set `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` on the server. The code default stays `false` on purpose: switching it on before the notice would cut off CI tokens nobody was told about. Owner decision R9 of the 2026-10 audit keeps it so: this is the operator's step on the server after the dry run, not a code change (TASK-0079). A service account's token (`/v1/service-accounts`, TASK-0079) is always bound to its organization, so the switch does not touch it | after the switch, the unbound list holds only tokens whose owners were told | operator |
| Staff reach on customer keys (TASK-0039, IF-4, then P0-15) — closes two breach-register entries | `php artisan operator:authz:staff-reach --days=7` (read-only) daily; P0-15 gives staff keys to what it lists | `ONHOST_STAFF_REACH_ENFORCED=true` (default off) once the report has stayed empty for seven days | staff reach on a customer key (and staff `archive.restore` through a global binding) is refused; strike `IF-4` and `archive.restore` in `breach-register.md` | operator, after P0-15 |
| Staff tools after the P0-16 re-check (TASK-0039) | release notes below | none: `/v1/staff/services/{id}/actions` asks `staff.service.manage`, `…/reinstate` `billing.dunning.manage` | staff who lift holds or reinstate have those keys (support L2, the service admins, finance); an auditor, IAM admin or sales account no longer does | operator |
| Deploy with nothing in flight (TASK-0039) | — | let queued staff `suspend` runs and token-started runs finish before the deploy | runs queued before the release carry no `desired.staff_mode` / `desired.token_id` | operator |
| Solo operator: CRITICAL actions and price changes of the sole approver wait a time lock (TASK-0037, O4) | `docs/runbooks/approvals.md` | `ONHOST_FOUR_EYES_TIME_LOCK_HOURS` (default `24`, minimum 1) with `ONHOST_FOUR_EYES=false` | the first attempt opens a time-locked request; the repeat after the lock with a fresh step-up runs it; the requester can cancel it | owner-decision (default taken) |
| Pterodactyl identity triage (TASK-0033, PA-01) | `.ai/handoffs/TASK-0033.md` (*Docs for integration*: triage steps) | `php artisan onhost:game:panel-identity --dry-run [--instance=]` (`--apply` is refused) | contact both organizations for every `foreign`/`unmarked` row; with anything in `dříve přes platformu` rotate that panel user's password and remove the collaborators added through the platform; re-home by hand and make the platform's record name the same user; rerun until every row reads `owned` | operator |
| aaPanel tenancy (TASK-0034, TASK-0041, D11, O1) | the dry run's list; **owner decision first** on the terminal, Node.js projects and existing cron on a closed node (they run as the shared `www` user) | `php artisan onhost:aapanel:tenancy` (dry run) → notify the listed organizations (notice text in `.ai/handoffs/TASK-0034.md`) → let queued web operations on the node finish or cancel them → `--apply` (`--apply --instance=<key> --force` for a node with historical sites) → `onhost:services:cron-confine --apply`; back: `--reopen --instance=<key>` | the closed node's sites show no file manager, import, PHP settings, apps, deploy, staging or shell cron; SFTP and backups work; a first restore on a closed and an open test node confirms UnZip/upload from `/www/.onhost-stage` | owner-decision |
| Orphaned Discord links and action hooks (TASK-0035, IF-15) | the dry run's list | `php artisan operator:integrations:orphan-links [--organization=]` (dry run) → review → `--apply` | the dry run again lists nothing | operator |
| Legacy project roles outside the allow-list (TASK-0041, IF-3) | the dry run's list | `php artisan onhost:projects:role-audit --dry-run [--organization=]` (read-only; no `--apply`) | the owner decides each membership; changes go through the organization's own remove/add project member | owner-decision |
| Partner masking notice (TASK-0040, O9) | the dry run's list of active partners | `php artisan onhost:partners:masking-notice` (dry run) → `--send` the same day as the deploy | each active partner got the in-app notice and the `legal-notice` mail once | operator |
| Anomalous partner payouts (TASK-0040, IF-14) | `php artisan onhost:partners:payout-anomalies` (dry run; prints `List digest: …`) reviewed with the owner | `--apply --digest=<that digest>` (refused when the list changed — run the dry run again); first payouts and unconfirmed leftovers: freeze, confirm the account with the partner, release with a reason — or reject | frozen payouts cannot be approved or paid; finance pays only `approved` payouts, and the payment asks a second person (or the solo time lock) | owner-decision |
| Evacuating a shared aaPanel node (TASK-0041) | — | target a closed node (or an empty one) | moves of other organizations onto an open node fail with the tenancy reason and the `--apply --instance=` hint | operator |
| Phase 0 sign-off | the program's status header; `breach-register.md` open list; audit rows 129, 133 | TASK-0039 is integrated (pins retired, `daca3b5`). Left: a fix for `GET /v1/me` with a failing-first test, a read-only review of `c4c43e2`, the two switches above | the breach register's open list is empty and the review passes | open |

### Release note: Phase 0 of the permission program

For the announcement to customers and the pull request (from the handoffs of TASK-0033 … TASK-0041). No plan, price or
issued document changes.

- Team access: a current member can no longer be invited again (409 — change their role instead), and an old link never
  lowers anybody's role. An organization admin can no longer change or remove a member whose role holds more than their
  own — in practice the billing admin; the owner can. Adding or removing a project role asks for a fresh confirmation, and
  project roles come from a fixed list (existing ones keep working). The team page lists every role, removing asks for
  confirmation, and "partner" reads "partner (commission only)".
- Discord and action hooks: someone who left the organization, or whose role no longer allows it, loses the linked Discord
  account and their hooks are switched off (not deleted; the list shows why). `/onhost` shows only what your own role shows.
- Restoring the archive of a cancelled service needs the right to restore backups for the whole project or organization and
  to read that service's data; the live target is copied first and nothing is overwritten when the copy fails.
- Game servers: a panel account that is not your organization's is no longer shown or changed through the platform; new
  panel accounts get a technical e-mail — use the owner's panel-password action instead of the panel's "forgot password".
- aaPanel: archives that reach outside the site, and files that are links or second names, are no longer unpacked or
  downloaded. On a server shared with other customers — only after the operator's notice — files, PHP settings, one-click
  apps, git deployments, staging copies and scheduled shell commands can no longer be changed from the control panel
  (SFTP/FTP stay; existing jobs keep running; backups can still be restored).
- Step-up is now asked for publishing DNSSEC keys, changing a domain's registrant and downloading the archive of a
  cancelled service; these are no longer possible with an API key. Domain requests that reuse an `Idempotency-Key` for a
  different request get 409.
- Partners: a payout request asks for a fresh confirmation and goes only to the payout account the partner's owner confirmed
  (set once, usable after 7 days, with a notice); client contacts and overdue states are masked; members without the partner
  portal permission (viewer, developer, …) no longer see the portal.
- Staff: 58 staff operations take a fresh step-up; a solo operator's critical actions and price changes wait 24 hours;
  paying a partner needs a second person.
- API keys act for their own organization only: naming another organization is refused, and without one the key's own
  organization applies. Keys stored with no organization keep working until the operator's notice and switch.
- Staff (TASK-0039): staff powers — lifting an abuse, review or payment hold, staff parameters, forced flags, a restore on the
  customer's behalf — go through `/v1/staff/services/{id}/actions` and `/v1/staff/services/{id}/reinstate` and need
  `staff.service.manage` / `billing.dunning.manage`; on the customer routes a member of staff is treated as the customer. The
  early purge takes a second person (or the sole approver's 24 h time lock) and always the final archive. Signing on to a
  customer's panel needs an open ticket the customer opened about that service, a reason, and without the customer's consent a
  second person; the customer is told at once (in-app and a mail to the owner).

## 8. Operator steps of Slice 1 of the permission program (TASK-0042)

S1-01 and S1-02 (`docs/security/permission-program-2026-09-27.md`, Slice-1 status block; ADR-0009 "Slice 1 outcome";
integration notes `docs/security/grant-policy.md`). They ride on the Phase-0 chain, so every step of §7 comes first. Nothing
in this release revokes anybody's current access: the only switch that would is off, and its dry run comes first. **Slice 1 is
not signed off:** S1-07 is open and the MEDIUMs of `docs/runbooks/breach-register.md` "Still open after Slice 1" have no fix.

| Step | Read first | Switch / command | Verify | Status |
| --- | --- | --- | --- | --- |
| Deploy Slice 1: migrations `000900` (tables `access_snapshots`, `ownership_transfers`, `owner_recoveries`, additive) and `000910` (nullable `access_snapshots.taken_by_role`); `NotificationTemplateSeeder` (mail templates `ownership-offered`, `ownership-transferred`, `owner-recovery-opened`, `member-mfa-reset`) | release notes below | `infra/aapanel/deploy.sh` (migrations; run the notification seeder as usual); no switch | the three tables exist and are empty; `php artisan onhost:access:expire` prints the column `snapshots_pruned`; the four templates are listed | operator |
| Environment | `.env.example` (block TASK-0042) | `ONHOST_GRANT_CASCADE_ENABLED=false` (default), `ONHOST_OWNER_RECOVERY_DAYS=7` (never below 7; a lower value is raised to 7) | `config('onhost.grants')` shows `cascade_enabled` false and `owner_recovery_days` ≥ 7 | operator |
| Grants no longer backed by who gave them (TD-6, I6) — the backlog from before the release included | `docs/security/grant-policy.md` §2 | `php artisan operator:grants:cascade --dry-run [--organization=org_…]` (read-only; `--apply` is refused by design) → tell the organizations concerned → decide on `ONHOST_GRANT_CASCADE_ENABLED=true` | the switch acts only on the **next** loss of a grantor, never retroactively: the listed backlog stays active until each organization removes it (or a later loss revokes it); every revocation leaves a snapshot one restore away | owner-decision |
| Staff procedure: a lost customer owner (D21) | `docs/security/grant-policy.md` §5 | `POST /v1/staff/customers/{organization}/owner-recovery` `{mode: mfa_reset\|transfer, new_owner_user_id?, reason (≥10), ticket_ref}` → a second person approves (`approval_required` first; the sole approver's time lock with `ONHOST_FOUR_EYES=false`) → after the notice period `…/owner-recovery/complete`; support cancels with `DELETE …/owner-recovery` | `iam.mfa.reset` of a customer owner answers 409 `owner_recovery_required`; every member of every reached organization got `owner-recovery-opened`; the organization is on hold (`owner_recovery_hold`) until it completes or is cancelled. **Until the breach-register MEDIUM is fixed:** the approving second person must not be the owner, the heir or a member of a reached organization — check it by hand | operator |
| Staff procedure: MFA reset of anybody else | `docs/security/grant-policy.md` §5 | `POST /v1/staff/users/{user}/mfa-reset` `{reason}` — CRITICAL (a second person) for a staff account and for a member manager | the person's organizations are told (`member-mfa-reset`). **Until the breach-register MEDIUMs are fixed:** a member without member management (e.g. a developer with console on every service) is reset by one person, and the reset leaves their sessions and tokens — end them by hand when the device was stolen | operator |
| API tokens of removed members | release notes below | none: from this release a removal revokes the person's tokens bound to the organization (a demotion those the new role cannot carry); removals before the deploy are untouched | a token of a member removed after the deploy carries `revoked_at` and is refused; restoring the member does not bring it back | operator |
| Slice 1 sign-off | the program's Slice-1 status block; `breach-register.md` "Still open after Slice 1"; audit rows 134–136 | S1-03 … S1-06, S1-08 … S1-10 built or decided; S1-07 run again with no open HIGH; the Slice-1 MEDIUMs fixed with failing-first tests; an independent review of `2856e3c` | the Slice-1 list of the breach register is empty and the review passes | open |

### Release note: Slice 1 of the permission program

From `docs/security/grant-policy.md` §7. No plan, price or issued document changes.

- Nobody's current access changes with this release; no grant is revoked (the cascade switch is off).
- Handing over ownership needs the new owner to accept it (they get a mail).
- A member with access until a date can no longer hand out access — invitations, project roles, shares, API tokens — that lasts
  longer than their own; the end is shortened automatically and shown.
- Removing a member or changing their role can be undone for 90 days, by somebody whose role covers the one that made the change
  (the owner's removals by the owner). A removed member's API tokens of the organization stop for good, also when the access is
  restored; a demoted member's tokens that need the old role stop too.
- An admin who could not grant the console cannot take it from somebody else by re-sharing or revoking.
- A lost owner is recovered by support only with a week's notice to everybody in every organization the owner owns or manages,
  and any of their admins can stop it.
- Support resetting the second factor of an administrator or of a staff account takes a second person, and the organization's
  owner and admins are told of any member's reset.
- There is no screen for these yet: undo, the ownership offer and the recovery notice are API endpoints until the access
  wizard (S1-04).

## 9. Operator steps after Phase E

| Step | Read first | Command | Verify | Status |
| --- | --- | --- | --- | --- |
| Deploy migration `000950` (R7: partner commissions wait 30 days after payment; unique `(invoice_id, kind)`) | `database/migrations/0001_01_01_000950_partner_commissions_wait_out_their_grace.php` | `infra/aapanel/deploy.sh` | The migration stops with `partner_commissions: N (invoice, kind) group(s) hold more than one commission …` when production holds a genuine double accrual. Nothing was changed before it stopped. **Finance** decides per listed `invoice/kind` which row is right and cancels or merges the other one through the usual correction path (never by editing amounts by hand); then the deploy is run again. Payout remainders are recognised and marked automatically | finance — rule for a genuine double accrual is open (phase I owner decision 6); not run |
| Staging usranalyse directive (aaPanel `libusranalyse.so`) | `docs/runbooks/staging-aapanel.md` | Verify the drop-in as that runbook describes, after `staging.sh setup` | Queue workers stay up (no exit code 7); the per-host omit list was reviewed | operator, not run — rehearsal R3 |

## 10. Operator steps of Phase G (G1 … G7) — prepared, not executed (G10, TASK-0119)

Phase G (owner decisions G-R1 … G-R5 of 2026-10-05, merged as #103 … #108) changed behaviour without a deploy switch, so the
order below matters. **Nothing here has been run on any server**: the steps describe what the operator does on the production
host after the release, one doctor run after each (`php artisan onhost:doctor`, every row names its remedy). No step prints a
password, a token or a signing secret into a ticket or chat. On **staging** each new row that is not OK stops the deploy until
the operator lists it in `expected-nonok` (release-and-rollback.md, *The gate*); in **production** a FAIL row stops the
release unless the owner's signed tag carries an `Accept-Gate:` line — so finish these steps before the tag, not after.

| Step | What | Read first | Command / setting | Verify | Status |
| --- | --- | --- | --- | --- | --- |
| G-1 | **Owner decision: VAT payer on the day of the first invoice, yes or no.** — **decided 2026-10-06 (H-R0): not a VAT payer now** (a payer later, through G-3). Nothing is switched yet | `vat-payer-mode.md` (*The mode*, *Proforma → payment → tax document → final invoice*), `billing-dunning.md` (G1, G2) | none — the owner answers; the accountant's open questions at the end of `vat-payer-mode.md` are answered first | the answer is written down with the date | decided 2026-10-06 (H-R0): not a payer; when to switch is open (phase I owner decision 8) |
| G-2 | The legal entity carries the real values: name, IČO, **DIČ**, the bank account. A payer issues tax documents under its DIČ; the seeded `CZ00000000` is a placeholder. **Set `ONHOST_VAT_PAYER` to the owner's decision (G-1) and `config:cache` before this step** (step G-3 below describes the switch itself): the default is `false` since H-R0 (not a payer); a legal entity that already says *payer* (created before H0) and the declaration `false`, or the other way round, report FAIL between G-2 and G-3 — switch it in G-3 | `vat-payer-mode.md` | `php artisan onhost:production:prepare --legal` (writes the declared mode only into an entity it creates, never over an existing one) | *legal entity bank details real*, *legal entity identification* and *VAT payer mode agrees with the legal entity* OK | operator, not run — rehearsal R14 |
| G-3 | Switch the VAT mode (the declaration `ONHOST_VAT_PAYER` was already set before G-2). The declaration comes first, then the switch **by a person, never from the command line**: finance with a fresh step-up and a second person who approves | `vat-payer-mode.md` (*Switching*, steps 1–4) | `ONHOST_VAT_PAYER=true|false`, `php artisan config:cache`; `php artisan onhost:vat:payer-mode` (read-only, exits 1 while the declaration and the legal entity disagree); `POST /v1/staff/tax/vat-payer-mode {payer, reason}` → 403 `approval_required` → another person approves (`POST /v1/staff/approvals/{id}/decision`) → the same request with `approval_ids: [id]` | `onhost:vat:payer-mode` exits 0; audit `tax.vat_payer_mode.set`; doctor *VAT payer mode* (documents) and *VAT payer mode agrees with the legal entity* OK. With `ONHOST_FOUR_EYES=false` the sole approver's own switch waits the time lock | not now (H-R0); owner decision 8, then finance with step-up and a second person |
| G-4 | **clamd** reachable from the platform, with limits that cover the largest image sold and `AlertExceedsMax yes` | `custom-iso.md`, *Server steps* 2 | `ONHOST_CLAMAV_HOST`, `ONHOST_CLAMAV_PORT`; `StreamMaxLength`, `MaxScanSize`, `MaxFileSize` ≥ `ONHOST_CUSTOM_ISO_SCAN_MAX_MB` (at most 4096); `config:cache` | **by hand**: `php artisan onhost:isos:scanner-check --fresh` answers OK (EICAR found, a file beyond the limits reported; `--fresh` forgets the answer cached for minutes). The doctor row *custom ISO virus scan passes its self-test* is OK without asking clamd until G-8 | operator, not run — clamd not built; rehearsal R15 |
| G-5 | **A dedicated volume for the images**, outside the web root, owned by the PHP/queue user, mode 0750, sized for the organizations you expect × `ONHOST_CUSTOM_ISO_ORG_QUOTA_MB` | `custom-iso.md`, *Server steps* 1 | `ONHOST_CUSTOM_ISO_ROOT=/srv/onhost-isos`, `config:cache` | **by hand**, on the host: `findmnt <root>` shows the dedicated mount, `stat -c '%U %a' <root>` the PHP/queue user and `750`, `df -h <root>` free space above `ONHOST_CUSTOM_ISO_ORG_QUOTA_MB` × the organizations you expect, `realpath <root>` is not under `public/`. The doctor row *custom ISO storage has room* does not look at the disk until G-8 (before it, it is OK because nothing is sold); afterwards it shows the free space | operator, not run — rehearsal R16 |
| G-6 | PHP and the web server take the upload: `upload_max_filesize`, `post_max_size`, `max_execution_time`, `request_terminate_timeout`, nginx `client_max_body_size`, `client_body_timeout`, `proxy_read_timeout`, `upload_tmp_dir` | `custom-iso.md`, *Server steps* 3 | host configuration, reload FPM and nginx | an upload of an image of the largest sold size on **staging** (manual tests `05-vps.md`) | operator, not run — rehearsal R16 (limits), R19 (upload) |
| G-7 | Proxmox: a storage with content type `iso` for customers' images, separate from the rescue storage; instance option `custom_iso_storage`; API token rights `Datastore.AllocateTemplate` and `Datastore.Audit`; queue timeout of `provider-proxmox` ≥ `ONHOST_CUSTOM_ISO_UPLOAD_TIMEOUT` | `custom-iso.md`, *Server steps* 4 | the instance options in the staff console (Integrace providerů) | without the option the feature answers `reason: node` and nothing is uploaded — check on staging first | operator, not run — rehearsal R17 |
| G-8 | **Catalogue revision `2026-10-custom-iso`** — **approved for apply by the owner on 2026-10-06 (H-R4)**: the plans and the 4096 MB limit of `docs/proposals/custom-iso-plans.md`; a server step of the operator (not run by the H0 package), **only after G-4 … G-7 are green** | `docs/proposals/custom-iso-plans.md` | `php artisan onhost:catalog:revise 2026-10-custom-iso` (dry run: every plan, `custom_iso null → true`), then `… --apply` (new plan versions, same prices; four eyes unless `ONHOST_FOUR_EYES=false`). Add the price-list line by hand, e.g. „Vlastní ISO do 4 GB“ | the dry run is empty afterwards; the two ISO doctor rows are now judged for real (FAIL while clamd or the volume is wrong) | decided (H-R4); operator only after G-4 … G-7 are green — rehearsal R18 |
| G-9 | **Loyalty rules** are the owner's, not tuning knobs: 1 point = 1 CZK before VAT, minimum 100 points, cap 20 % of the list price, no points on domains and add-ons, expiry after 24 months with a warning 30 days before, `counted_from` = the day the rule started | `config/loyalty.php` (every change is an owner decision recorded in `docs/audit/2026-10-full-readiness/ROZHODNUTI.md` first) | none by default — the file is the configuration; check that `counted_from` is today or earlier and `php artisan config:cache` after any change | the doctor row *loyalty expiry runs and balances are sane* reports the schedule, overdue expiry and any negative balance or debt | done in code (`config/loyalty.php`); operator confirms `counted_from` |
| G-10 | The scheduler runs the daily expiry (05:35) and the campaigns (05:15, 05:20) | `routes/console.php`, `infra/systemd/onhost-scheduler.service` | `systemctl status onhost-scheduler`; `php artisan schedule:list` shows `onhost:loyalty:expire` | *loyalty expiry runs and balances are sane* OK the day after the first points are 24 months old; before that it only proves the schedule | operator, not run — `schedule:list` on the host |
| G-11 | The **webhook queue lane**: one worker on `webhooks`; without it deliveries use the default lane (nothing piles up) | `webhooks.md`, `infra/systemd/onhost-queue@.service` | `systemctl enable --now onhost-queue@webhooks`; `ONHOST_WEBHOOK_QUEUE` stays `webhooks` | `systemctl status onhost-queue@webhooks`; a test delivery on staging | operator, not run — rehearsal R21 |
| G-12 | **Rotated webhook secrets overlap** for a chosen time: the previous secret signs `X-ONhost-Signature-Previous` meanwhile (0 = the new one at once, at most 1440 minutes) | `webhooks.md` | `ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES=60` (default), `config:cache`; a leaked secret is ended at once with `POST /v1/webhooks/{endpoint}/rotate-secret` and `overlap=false` | *rotated webhook secrets overlap only briefly* OK; the count of endpoints in an overlap is in its detail; the scheduler's minute pass clears an ended overlap | operator, not run — rehearsal R21 |
| G-13 | **Dead letters are heard**: the rules `OnhostOutboxDeadLetters` (ticket, after 10 minutes) and `OnhostOutboxDeadLettersOld` (page, older than a day) are loaded into Prometheus and routed to a person | `outbox-dead-letters.md`, `infra/monitoring/slo-alerts.yml` | `promtool check rules infra/monitoring/slo-alerts.yml`, add the file to `rule_files`, reload Prometheus; Alertmanager routes for `severity: ticket` and `severity: page`; Prometheus scrapes `/metrics` | `onhost_outbox_dead_letters` is returned by a query (0 is the healthy value); *no outbox dead letters* OK; a drill on staging: a listener that throws, then `onhost:outbox:dead-letters --requeue` | operator, not run — needs Prometheus/Alertmanager; rehearsal R22 |
| G-14 | **G7 follow-ups need no switch**: token scopes (deny by default, `services:console` apart), the audit event `staff.read.<what>` per person and thing per quarter of an hour, dead letters, webhook secret overlap | `security-boundaries.md` (§ on token scopes and § 17) | deploy only; tokens that must reach consoles are re-issued with the scope | manual tests of the token and audit pages | done |
| G-15 | G1 (only the invoice is a tax document), G4 (credit is never paid out in cash) and the rules of G5 in code need no operator step beyond the deploy | `billing-dunning.md`, `vat-and-vies.md` | none | `onhost:doctor` *every tax document in another currency states its VAT in CZK* OK | done |

Rollback of Phase G is mostly "do not switch": G-8 is the only step that changes what customers can buy, and publishing the
previous plan versions undoes it for new orders. The VAT mode never changes an issued document, so a wrong switch before the first
document costs nothing and one after it affects only documents issued from then on (`vat-payer-mode.md`).


### Blocking for production (2026-10-07, phase I)

From the repository and the open owner decisions (`ROZHODNUTI-podklady-I-2026-10-07.md` on the owner's desktop lists the options).
**Blocking** = a doctor row that is FAIL in production, or a legal or money risk the owner has not accepted; **should** = a promise
or a safeguard that is not in place, not a deploy stop by itself.

| # | Item | Why | Kind | Cleared by |
| --- | --- | --- | --- | --- |
| B1 | The staging rehearsal R0–R24 has not run | no step of sections 0, 9 and 10 is proved on a real host | blocking | rehearsal protocol complete, R23 without an unexpected FAIL, `expected-nonok` updated |
| B2 | Legal entity with the real name, IČO, DIČ and bank account | *legal entity carries its VAT mode* / identification rows are FAIL in production (H-R0a); a document freezes the seller | blocking | `onhost:production:prepare --legal` (step 11, G-2) |
| B3 | Staff authenticators for every staff account | staff MFA row is blocking in production | blocking | step 6 (the owner himself) |
| B4 | Lawyer's answer on withdrawal, credit top-up and forfeited credit (H-R5, questions 1–9) | consumer law risk; document versions stay `2026-09` until answered | blocking (legal) | owner decision 4, package I5 |
| B5 | Comgate has never answered a real request | card payments unverified end to end | blocking for card sales | owner decision 3, package I2 (test account, fixtures, manual card test on staging) |
| B6 | Reinstatement audit before the deploy that carries H0 | undone cancellations ran without billing before H-R3 | blocking (order) | step 0 on the running release |
| B7 | Migration `000950` stops on a genuine double commission | the deploy halts until finance decides | blocking if found | owner decision 6 (rule in advance), section 9 |
| B8 | Turnstile keys | registration and public forms unprotected against bots | should | step 8 |
| B9 | clamd for uploads | in production uploads that need a scan are refused without it | should (blocking for custom ISO) | G-4 / rehearsal R15 |
| B10 | `backups.compute` off | `backup_days` sold on `data` plans is not kept as sold | should | owner decision 1, package I4 |
| B11 | Penpot on sale without a node | the panel says "Penpot v ceně" but every order is refused (and refunded, with a notice) | should | owner decision 7: build the node, or withdraw Penpot until then |
| B12 | Dead-letter alerts loaded in Prometheus | a stuck outbox is not heard | should | step 16 / G-13 |
| B13 | Shared cache store, PHP binary, catalogue revisions, exchange rates, token switch | each has its doctor row WARN until done | should | steps 1, 3, 4, 5, 10 |

The first day after these steps is run by [first-day-production.md](first-day-production.md).

## One report of how the installation stands

`php artisan onhost:staging:report --check` asks every panel the read-only questions (`SelfProbing`), runs the doctor
and writes `storage/app/onhost-staging-report.json`: the doctor's findings that are not OK, what each panel answered
(including the field names of ISPConfig's change log), p50/p95 of actions for the last week, whether four eyes are in
effect, how many finished operations still hold secrets. Nothing in it is a secret (an allow-list of facts, passed
through the redactor) — it is the file to hand to whoever reviews the installation without access to the panels.

## A node nobody has qualified sells nothing (2026-09-22)

`nodes.state` defaulted to `active`, and both ways a node comes into being put it there at once:

* discovery from a panel — four branches in `ProviderInstanceService` (Proxmox cluster nodes, ISPConfig servers, the
  aaPanel host, game-panel nodes);
* `NodeBootstrap::activate()`, which a freshly installed machine calls **from its own boot script**. One curl from
  cloud-init and the scheduler would place a paying customer on a host nobody had looked at.

A node is now born `qualifying` (H471). It is listed and watched, and `NodeScheduler` does not see it —
`Node::isSchedulable()` is `state === active`, and that was already the filter everywhere, so the gate needed no
change to the scheduler itself.

`NodeQualification::inspect()` reports on every point; `REQUIRED` are the ones that must pass:

| point | what it establishes |
| --- | --- |
| `instance` | the provider instance is usable and its last prerequisite check did not find the API down |
| `seen` | the panel confirmed this node within the last two hours |
| `capacity` | the node says how many cores, how much memory and how much disk it has — without that the scheduler cannot size anything on it |
| `placement` | region, role and **failure domain** are set; without a failure domain nothing can be spread away from this host |
| `headroom` | at least 15 % of the disk is free |

The points that need a shell on the node itself — its clock (H472), what it can resolve (H473) and reach (H474), how
the management interface is exposed (H480) — and the synthetic service (H479) are reported as `not_checked` **with
the reason**, never as passed. A check nobody made is not a check.

`accept()` refuses while any required point fails, and records who accepted, when, and any exception knowingly
accepted anyway (H478) — written on the node, never hidden. `CapacityPlanner::track()` treats `qualifying` as
delivered: the vendor did their part, qualifying it is ours.

Operator: `php artisan onhost:nodes:qualify` lists what is waiting and what each one still fails on;
`--accept=<node> [--exception="…"]` puts one into the offer. `onhost:doctor` has two rows: nodes waiting, and nodes
already in the offer that have never been qualified at all.

Tests: `tests/Feature/Provisioning/NodeQualificationTest.php`.

### The synthetic service (H479)

A node can pass every point above and still not be able to make the one thing it is there for. The only proof is to
make one. `SyntheticService::run()`, on exactly this node:

1. `provision()` a throw-away resource — the adapter contract every panel implements — and waits for its task;
2. `getActualState()` must show it;
3. `terminate()` it and waits;
4. `getActualState()` must no longer show it.

The result is `ok` only when it was made **and** removed. A resource that could not be removed fails the node — the
card is explicit that creating is not enough, and a node that leaves things behind will leave a customer's cancelled
service behind too. The removal is tried once more in a `finally`; what stays is named (`leftover`), published as
`node.synthetic.leftover` and listed by `onhost:doctor` ("no synthetic test resource was left on a node").

What is created is what the owner writes down per role in `onhost.provisioning.qualification.synthetic.<role>` —
the attributes of the smallest thing that role sells, as the adapter reads them (for compute e.g. `template`,
`cores`, `memory_mb`, `disk_gb`, `storage`). **Nothing is guessed, and the default is empty**: a role without a
template creates nothing and touches no panel. Configure it on a test range first.

Once a template exists for a role, the synthetic run becomes a **required** point of that role's qualification, and
only a run from the last seven days counts.

```bash
php artisan onhost:nodes:qualify --synthetic=<node>
php artisan onhost:nodes:qualify --accept=<node>
```

Tests: `tests/Feature/Provisioning/SyntheticServiceTest.php`.
