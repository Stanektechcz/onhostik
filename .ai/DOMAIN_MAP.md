# Domain ownership map

Derived from the real tree (2026-09-23): `domains/` (23 modules), `providers/`, `platform/`, `app/`, `apps/surfaces/`,
`infra/`, `database/`. Business meaning of each area: `docs/context/DOMAIN_MAP.md`. Agents: `.claude/agents/onhost-*.md`.

An **owner** implements; **reviewers** must review before integration. Ownership follows coupling
(`.ai/DEPENDENCY_MAP.md`): tightly coupled modules share one owner.

| Area | Paths | Owner | Reviewers |
| --- | --- | --- | --- |
| Money & commerce | `domains/{Billing,Invoicing,Payments,Tax,WalletLedger,Orders,Catalog}`, `providers/Payments/` | onhost-billing | onhost-security, onhost-qa, onhost-reviewer |
| Services & provisioning | `domains/{Services,Provisioning,Domains,Dns}` | onhost-provisioning | onhost-integration (adapter contracts), onhost-qa, onhost-reviewer; onhost-security for customer params, secrets, destructive actions |
| Vendor adapters | `providers/*` except `Payments/`, `tests/Contract/` | onhost-integration | onhost-provisioning, onhost-security |
| Accounts & access | `domains/{Identity,Organizations,Risk}` | onhost-backend | **onhost-security (mandatory)**, onhost-qa |
| Customer operations | `domains/{Support,Incidents,Notifications,Compliance}` | onhost-backend | onhost-reviewer, onhost-qa; onhost-security for Compliance |
| Growth & content | `domains/{Partners,Loyalty,Marketplace,Content,Integrations}` | onhost-backend | onhost-billing when money moves (commissions, payouts, points), onhost-reviewer |
| Platform kernel | `platform/` | onhost-backend | **onhost-architect (mandatory)**, onhost-security |
| HTTP transport | `app/Http/Controllers`, `app/Http/Presenters`, `routes/api.php`, `routes/web.php` | owner of the domain the controller dispatches to | onhost-security, onhost-reviewer |
| Surfaces & admin UI | `apps/surfaces/api/*.js`, `app/Http/Support/SurfaceRenderer.php`, `app/Http/Controllers/Web/Surface*`, `resources/views`, `resources/css|js`, `lang/` | onhost-frontend | onhost-qa, onhost-reviewer |
| Schema & data | `database/migrations`, `database/seeders`, `database/factories` | onhost-database (author or reviewer of every migration) | onhost-reviewer, the owning domain agent |
| Infrastructure & CI | `infra/`, `.github/`, `.githooks/`, `config/` (structure), deploy runbooks | onhost-infra | onhost-security, onhost-release |
| Docs & knowledge | `docs/`, `.ai/*.md`, `README.md`, `AGENTS.md`, `CLAUDE.md` | onhost-docs | orchestrator |
| AI tooling | `.claude/`, `scripts/ai/`, `brain.ps1` | orchestrator | onhost-reviewer |

**Read-only by rule:** `apps/surfaces/*.dc.html`, `_ds/`, `apps/surfaces/onhost-*.js` (prototype; ADR 0005).
Generated, never hand-edited: `contracts/openapi/onhost-v1.yaml` (`php artisan onhost:openapi`),
`compliance/oss-inventory.yml` (`php artisan onhost:oss-inventory`), `docs/generated/*` (`brain.ps1 update`).

## Hot files — one task at a time (lock them explicitly)

These are edited by almost every feature; two parallel tasks touching one of them will conflict. Claim them with
`brain.ps1 task claim` before editing, or route the edit through the task that already holds them.

| File | Why it is hot |
| --- | --- |
| `routes/api.php`, `routes/web.php` | every endpoint |
| `routes/console.php` | every scheduled job / command |
| `app/Providers/DomainServiceProvider.php` | `HANDLERS` registry of every command |
| `app/Providers/PlatformServiceProvider.php` | `ADAPTERS` registry |
| `database/migrations/` (the next number) | one global sequence in steps of 10 |
| `tests/Pest.php` | shared fixtures; one global function namespace |
| `domains/Notifications/NotificationRouter.php`, `database/seeders/NotificationTemplateSeeder.php` | every notification |
| `domains/Identity/Authorization/{RoleCatalog,PermissionCatalog}.php` | every permission (reseed `AuthorizationSeeder`) |
| `config/onhost.php`, `.env.example` | every config key (`EnvTemplateTest` guards the template) |
| `app/Http/Support/SurfaceRenderer.php` | every UI seam (from/to pairs must stay aligned) |
| `docs/architecture/events-catalog.md`, `docs/context/CURRENT_STATE.md`, `docs/runbooks/production-readiness-audit.md` | every event / feature / audit row |
| `phpstan-baseline.neon` | never add entries; removals only |
| `contracts/openapi/onhost-v1.yaml` | regenerate once, after integration |

## Critical flows and their guards

`.ai/baseline/baseline.json` → `critical_flows` lists the test files that protect: registration/login/step-up, tenant
isolation and permissions, cart→quote→order, payment/invoice/ledger/tax, fulfilment/provisioning, suspend/cancel/delete,
renewal/dunning, domains/registrars, staff actions, secrets/outbox/egress, and the endpoint sweep + surfaces.
`brain.ps1 gate -Quick` runs exactly these.
