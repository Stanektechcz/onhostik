# Go-live checklist

Everything the control plane needs before the first paying customer. Each line names where it is configured and
how it is verified; nothing here is optional for production. Run through it top to bottom on the production host.

**Status** (2026-09-25, stack tip of TASK-0027; nothing is deployed to production yet): **done** — in place and
verified in the repository or CI; **open** — work or procurement still missing; **operator** — the code is ready, an
operator does it on the host at or after deploy; **owner-decision** — waits for the owner's decision; **legal** — waits
for a lawyer. Every new behaviour that reaches existing services ships switched off (ADR-0007): section 6 lists those
switches with the read-only command to run before each.

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
| Comgate merchant + secret (`env://COMGATE` or the secret store), `COMGATE_TEST=false`, callback IPs | a 1 CZK card top-up settles and appears in the wallet | open |
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

## 6. Switches and operator steps of the stack TASK-0017 … TASK-0027

Everything below ships **off** (an AutomationLedger rule with `default_off`, or an `ONHOST_*` switch whose default keeps
the old behaviour) or as an operator command with a dry run; the one exception is named. Run the read-only command first,
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
| Paid limit raises for customers, renewals of other add-ons (decision 8) | `onhost:limit-raise list` | `ONHOST_LIMIT_RAISE_CUSTOMER_ORDERS=true`; `ONHOST_ADDON_RENEWALS=true` (ending an IPv4/CDN add-on does not reach the panel) | *every limit raise is billed or approved* | owner-decision |
| Pay and restore `services.reinstate` (decision 23) | `php artisan onhost:billing:reinstatement-audit` | rule on; `--apply --service=<id>` one service at a time for undone cancellations that run unbilled | restored services keep their auto-renew (off stays off) | owner-decision |
| Consumer withdrawal `billing.withdrawal` (decision 17) | legal review of `resources/legal/LEGAL_REVIEW_withdrawal.md` and the terms | `ONHOST_WITHDRAWAL_LEGAL_REVIEWED=true`, then the rule on | *consumer withdrawal reviewed by a lawyer*, *consumer withdrawals move on* | legal |
| Was the ISPConfig ownership hole used before TASK-0005? (decision 16) | a production copy on staging | `php artisan onhost:audit:provider-calls` (read-only; report under `storage/app/private/reports`) | no CRITICAL row, or the incident steps of `docs/runbooks/provider-calls-audit.md` | operator |
| Staging lifecycle run and the lost site | `docs/context/CURRENT_STATE.md` (*Next decision*) | `onhost:services:archive <service> --create` on each panel, then the purge; `onhost:ispconfig:restore-site` for `s4s.electree.cz` | archives verified; the site answers again | open |

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
