# Go-live checklist

Everything the control plane needs before the first paying customer. Each line names where it is configured and
how it is verified; nothing here is optional for production. Run through it top to bottom on the production host.

## 1. Platform

| Item | Where | Verify |
| --- | --- | --- |
| `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generated, `APP_URL` = public origin | `.env` | `php artisan about` |
| PostgreSQL + Redis (cache, queue, sessions), `QUEUE_CONNECTION=redis` | `.env` | `php artisan migrate --force`, `php artisan queue:monitor` |
| Queue workers per provider queue and the scheduler as systemd units | `infra/systemd/*.service` | `systemctl status onhost-queue@default onhost-scheduler` |
| Sanctum stateful domains = the public origins of the surfaces | `SANCTUM_STATEFUL_DOMAINS` | sign in on `/prihlaseni`, `GET /v1/me` returns the user |
| Security headers / CSP, HTTPS only, `upgrade-insecure-requests` (automatic on HTTPS) | `App\Http\Middleware\SecurityHeaders` | `curl -I https://…/panel` |
| Secrets driver `bao` (OpenBao) or `db` (encrypted with `APP_KEY`); `env` is refused in production | `ONHOST_SECRETS_DRIVER` | `php artisan onhost:doctor` (see below) |
| CA bundle for outbound TLS (system bundle on Linux; `curl.cainfo` on Windows workstations) | php.ini | provider probes pass |
| Metrics endpoint token + Prometheus scrape, `/healthz` behind the load balancer | `ONHOST_METRICS_TOKEN`, `infra/monitoring` | `curl -H "Authorization: Bearer …" /metrics` |
| Prometheus rules → Alertmanager → `POST /v1/webhooks/alertmanager` (bearer `ONHOST_ONCALL_INBOUND_SECRET`): burn rate, outbox lag, provider down, dead automation, stale backup, clamd, on-call; Grafana dashboard *ONhost · provoz* provisioned from `infra/monitoring/grafana/dashboards` | `infra/monitoring/{slo-alerts,alertmanager}.yml`, `docker-compose.yml` (alertmanager, grafana) | fire a test rule; the alert appears under *Incidenty → on-call* and pages |
| Log shipping (Loki) and error tracking | `LOG_CHANNEL`, `infra/monitoring` | a test error appears in the sink |
| Backups of the database and `storage/app/private` (invoice PDFs, evidence, exports) — `onhost:platform:backup` daily 02:15, `onhost:platform:backup:verify` 03:15, `ONHOST_PLATFORM_BACKUP_DISK` = an S3-compatible disk off the server, retention `ONHOST_PLATFORM_BACKUP_RETENTION_DAYS` | `.env`, rule `platform.backup` | `onhost:doctor` → *platform backup verified within 26 h* OK; a restore drill: `pg_restore --clean --if-exists -d onhost database.pgdump` on a staging host |
| Production preparation: development accounts purged, legal entity from `ONHOST_LEGAL_*` / `ONHOST_BANK_*`, config/route/event caches | `php artisan onhost:production:prepare --purge-dev-accounts --legal --cache` | the command ends with the doctor; 0 FAIL |
| Static analysis and dependency audit in CI (`vendor/bin/phpstan analyse`, `composer audit`) | `.github/workflows/tests.yml`, `phpstan.neon` (Larastan level 5, baseline = today's typing debt) | the workflow is green on the release commit |
| Deletion lifecycle (audit §5ab): restore window, archive retention, identity points and the download fee | *Nastavení systému → Životní cyklus služeb*, `config/onhost.php` `services.deletion` | `onhost:doctor` → the *lifecycle* rows are OK; `onhost:services:archive <service> --create` proves the archive path of every panel |
| Service archives on the backup disk, re-hashed against their manifest and pruned after the retention | `ONHOST_PLATFORM_BACKUP_DISK`, `onhost:backups:run` (every 15 min), `onhost:services:purge` (daily 03:40) | `onhost:doctor` → *archive disk writable*, *archives verified*, *nothing past its restore window* |
| Secret scan and dependency advisories in CI, pre-commit hook enabled in every clone | `.github/workflows/security.yml`, `.gitleaks.toml`, `git config core.hooksPath .githooks` | the security workflow is green; a staged `.env` is refused locally |

## 2. Providers (Nastavení systému → Integrace providerů)

| Item | Verify |
| --- | --- |
| Proxmox instance per region with API token, `storage`, `bridge`, `pbs_datastore`; nodes imported (Discover) | Probe UP, nodes listed with capacity |
| PBS datastore reachable from the Proxmox instance | backup of a test VM completes |
| ISPConfig remote user with client, sites, mail, dns, server, monitor functions; `server_id` resolved | Probe UP with `permissions.server = true` |
| aaPanel API key, control-plane egress IP on the allow-list, certificate pinned if self-signed | Probe UP with the panel version |
| Pterodactyl application key, nodes and allocations registered, eggs mapped | Probe UP, a test game server provisions |
| PowerDNS API key, NS set in option `nameservers`, secondaries receive NOTIFY | zone commit shows the serial on both servers |
| WEDOS WAPI login + password, `WEDOS_TEST_MODE=false`, control-plane IP allowed | `wapi ping` UP, a `.cz` availability check answers |
| Subreg API user + password, control-plane IP allowed; registrar price book filled (*Registrátoři domén → Aktualizovat ceníky z API*, WEDOS costs entered by hand), TLD pins reviewed | `onhost:doctor` shows `registrar available` and `registrar cost prices known for every TLD` OK; the matrix shows a winner per TLD |
| RKE2 service account token + cluster CA pinned | Probe UP, `apps.create` capability on |
| IPAM pools per region for VPS addresses | `POST /v1/staff/ipam/pools`; a VPS order no longer waits with `ipam.exhausted` |
| Console relay key shared with the relay service | `ONHOST_CONSOLE_RELAY_KEY`; opening a console in the panel connects |

## 3. Money and documents

| Item | Verify |
| --- | --- |
| Comgate merchant + secret (`env://COMGATE` or the secret store), `COMGATE_TEST=false`, callback IPs | a 1 CZK card top-up settles and appears in the wallet |
| Bank account (`ONHOST_BANK_IBAN`, `ONHOST_BANK_BIC`, `ONHOST_BANK_ACCOUNT`) printed on proformas and top-up instructions | a proforma PDF shows the account and QR code |
| Bank statement import matches by variable symbol | a test transfer settles the proforma and starts fulfilment |
| Legal entity, VAT registration, document series (`FV`, `PF`, `DK`, `PP`, `TU`), `ONHOST_LEGAL_ENTITY` | `php artisan db:seed --class=LegalEntitySeeder` on the production values |
| Tax rules (CZ 21 %, OSS, reverse charge) and VIES endpoint | a B2B EU quote shows reverse charge |
| Documents in EUR: the control plane reaches `www.cnb.cz` (exchange rate list, public) and the accountant confirmed the daily ČNB rate | `php artisan onhost:fx:sync` stores today's list; an EUR invoice prints "Kurz ČNB" and its VAT in CZK; `onhost:doctor` area `money` is green |
| Dunning ladder (`onhost.billing.dunning`) matches the terms in the panel (14 days) | `docs/runbooks/billing-dunning.md` |
| Transactional mail (`MAIL_*`), templates rendered in Czech and English, SPF/DKIM/DMARC of the sending domain | request a password reset on `/prihlaseni`, run `php artisan onhost:mail:send`, check the message and its authentication headers |

## 4. Content and legal

| Item | Verify |
| --- | --- |
| Terms, privacy, DPA, SLA, registrar terms published with version numbers (checkout consents) | `/v1/catalog` lists the document versions the cart requires |
| Status page components and locations | `/stav` renders, incidents post to it |
| Knowledge base, changelog and marketing copy reviewed (no prototype narrative) | `docs/ui/data-seams.md` "prototype-only" list is empty or accepted |
| Dev accounts removed (`DevAccountSeeder` refuses production; verify no `*@onhost.cz` demo users exist) | `php artisan tinker` → `User::where('email','demo@onhost.cz')->exists()` is false |

## 5. Smoke tests on the production host

```bash
php artisan test --compact tests/Feature/Http/EndpointSweepTest.php   # every /v1 route answers by contract
php artisan onhost:integrations:health                                # probes every provider instance
php artisan onhost:openapi && git diff --stat contracts/               # the published contract matches the routes
```

Then, as a real customer: register, order web hosting by bank transfer, pay the proforma, watch the service turn
ACTIVE, open a ticket, download the invoice PDF and cancel the service. Every step is audited (`/v1/organizations/{id}/audit`).

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
