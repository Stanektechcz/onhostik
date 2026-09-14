# ONhost Cloud Platform — control plane

Laravel 13 / PHP 8.3 monolith that runs the ONhost hosting business: catalog and checkout, wallet and
double-entry ledger, tax and documents (FV/PF/PP/VY/DK), payments (Comgate), provisioning sagas over
Proxmox VE/PBS, ISPConfig, aaPanel, Pterodactyl and RKE2, domains over WEDOS WAPI and Subreg (cheapest registrar wins), canonical DNS in PowerDNS,
billing (subscriptions, metering, dunning), support (tickets, SLA, AI assistant with human handoff),
notifications and webhooks, status page and SLO engine, compliance (GDPR/DSA/NIS2), partner programme and
public content — all behind one authorization model (RBAC + step-up + four-eyes) and a hash-chained audit log.

Blueprint: `ONhost_Cloud_Platform_v4_Fable5_Execution_Blueprint_2026-08-24.md`. UI: the five prototype
surfaces in `apps/surfaces`, served verbatim with data seams (ADR-0005).

## Layout

| Path | Contents |
| --- | --- |
| `platform/` | Money, StateMachine, CommandBus pipeline, audit chain, outbox, provider HTTP client, secrets, redaction |
| `domains/` | Identity, Organizations, Catalog, Tax, WalletLedger, Orders, Invoicing, Payments, Provisioning, Services, Domains, Dns, Billing, Notifications, Support, Incidents, Compliance, Partners, Content |
| `providers/` | vendor adapters (contracts in `providers/Contracts`) |
| `app/Http` | `/v1` API controllers and presenters, surface controllers (`Web/`), `ApiContext` |
| `apps/surfaces` | prototype surfaces + `api/` data-seam modules |
| `routes/api.php`, `routes/web.php`, `routes/console.php` | API, surfaces/relay, `onhost:*` commands and schedule |
| `contracts/openapi/onhost-v1.yaml` | generated API contract (`php artisan onhost:openapi`) |
| `docs/` | ADRs, events catalog, provider adapters, runbooks, SRE, compliance, UI inventory, handoff docs |
| `infra/` | docker-compose (dev), monitoring rules, RKE2 policies, ansible/opentofu skeletons |
| `e2e/` | Playwright smoke and visual checks of the surfaces |

## Run locally

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # BaseSeeder + tax, legal entity, catalog, DNS templates, content, dev infrastructure
php artisan serve                   # http://localhost:8000 → surfaces; /v1 → API
php artisan schedule:work           # outbox relay, provisioning tick, renewals, dunning, SLA evaluation …
php artisan queue:work --queue=default,mails,provider-proxmox,provider-ispconfig,provider-aapanel,provider-pterodactyl,provider-powerdns,provider-registrar,provider-kubernetes
```

Provider credentials are referenced as `env://NAME`, `bao://path` or `db://provider_instances/<key>` (registered
from the console, encrypted with `APP_KEY`) in `provider_instances.secret_ref`. Real Proxmox / ISPConfig / aaPanel /
Pterodactyl / PowerDNS / WEDOS / Subreg / RKE2 instances are connected in **Nastavení systému → Integrace providerů**
(`/sprava/nastaveni/integrace`: register, pin a certificate, probe, discover nodes, set state) or through the staff
API (`docs/runbooks/provider-onboarding.md`). Without real providers use `DevInfrastructureSeeder` (RFC 5737/3849
addresses, refuses to run in production) and the HTTP fakes from the test suite.

What a customer can do with a service is derived from the executor behind it (`GET /v1/services/{id}/features`,
`…/resources/{kind}`, `POST …/actions` — databases, FTP, cron, PHP, certificates, redirects, extra domains, logs,
backups, snapshots, firewall, console commands, mailboxes); the panel renders only the tabs a service really offers
and never names the vendor panel. Which panel and server a plan runs on is set in the same settings page
(**Umístění tarifů**, `/v1/staff/placements`); see `docs/runbooks/preproduction-audit.md` for the feature matrix.

Domains: ONhost sets its own selling prices; the wholesale prices of every connected registrar (WEDOS, Subreg, …) live in
the price book (**Registrátoři domén**, `/v1/staff/registrars`, refreshed from registrar price APIs, scraped weekly from
the registrars' public price lists, or entered by staff)
and `RegistrarSelector` registers each new domain with the cheapest registrar for its TLD. Commercial rules (commitment
discounts, TLD discounts, promo codes, which add-ons a product carries, option unit prices for per-item add-ons and
the web hosting configurator) are set by staff in the settings page and read by the quote and the web alike
(`docs/runbooks/pricing.md`); a visitor can finish an order without an account (`POST /v1/checkout/guest`) — renewals stay where the
domain is held, and no customer surface ever names a registrar (`docs/runbooks/domain-registrars.md`). Customers manage
domains in the panel (registration, renewals, transfer lock, AUTH-ID, nameservers, the ONhost zone with staged DNS
changes, DNSSEC) and order new ones from the "Nová služba" wizard. Legal documents live at `/dokumenty/<slug>`
(Markdown in `resources/legal`, versions in `consent_documents`).

`php artisan onhost:doctor` prints the production readiness report (environment, storage, secrets, outbound TLS,
providers and their health, payments, documents, identity policy, mail, observability); it exits non-zero on a
blocking finding in production. On Windows workstations point `curl.cainfo` / `openssl.cafile` in `php.ini` at a
CA bundle (Git ships one under `usr/ssl/certs/ca-bundle.crt`), otherwise every vendor API call fails TLS verification.

Local walkthrough accounts (`php artisan db:seed --class=DevAccountSeeder`, never in production):

| Account | Password | Surface |
| --- | --- | --- |
| demo@onhost.cz (Demo s.r.o.: VPS, web hosting, game server, 3 domains, documents, ticket) | `Demo-heslo-2026!` | `/panel` |
| agentura@onhost.cz (partner Agentura Pixel, referral of Demo s.r.o.) | `Demo-heslo-2026!` | `/partner` |
| admin@onhost.cz (platform owner) · noc@ (SRE) · finance@ (billing) · support@ (support manager) | `Admin-heslo-2026!` | `/sprava` |

Staff sign-in needs TOTP in production (`ONHOST_STAFF_MFA_REQUIRED=true`); the local `.env` disables it. Set
`WEDOS_TEST_MODE=false`, `ONHOST_SECRETS_DRIVER=openbao|db`, `ONHOST_METRICS_TOKEN` and `ONHOST_CONSOLE_RELAY_KEY`
before going live (`docs/runbooks/release-and-rollback.md`).

## Tests

```bash
php artisan test            # Pest: unit, feature (SQLite in memory), contract tests with HTTP fakes
php artisan test tests/Feature/Http/EndpointSweepTest.php   # every /v1 route as visitor, customer and staff: no 5xx, JSON error contract (report in storage/logs/endpoint-sweep.txt)
php artisan onhost:openapi  # regenerate the contract after route changes
php artisan onhost:oss-inventory
```

## Key conventions

* Every mutation is a command through `CommandBus` (authorization → step-up/approval → idempotency →
  transaction → audit → outbox). Controllers never touch models for writes.
* Money in minor units; documents are immutable once issued; balances derive from the ledger.
* Provider calls only from sagas/adapters; identifiers stay in `provider_bindings`; secrets never leave the
  `SecretStore`/`Redactor` boundary.
* Events: publish through the outbox, document in `docs/architecture/events-catalog.md`, route in
  `NotificationRouter`.
* Surfaces stay pristine; only the seams in `docs/ui/template-inventory.md` §6 may change.

See `AGENTS.md` for the working rules used by engineers and coding agents.
