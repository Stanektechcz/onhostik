# Working rules for engineers and coding agents

This repository is the ONhost control plane. Read `README.md` for the layout and the blueprint for intent.
These rules are enforced by review and by the test suite.

## Non-negotiables

1. **No placeholders in production paths.** Every endpoint, workflow and adapter does what it says or does
   not exist. Unfinished work lives behind a feature flag with a failing-closed default, never as a stub that
   returns fake success.
2. **Writes go through the CommandBus.** New mutations = a `Command` (`OrganizationCommand` or
   `GlobalCommand` + `RiskAwareCommand`) + handler registered in `DomainServiceProvider::HANDLERS`. Risk
   levels: NORMAL (default), HIGH (fresh step-up only), CRITICAL (step-up + four-eyes approval). Routine incident
   work stays NORMAL; money, publishing to customers, credentials and legal actions are HIGH/CRITICAL. Every price or
   plan change in the admin configuration takes four eyes even under a HIGH permission (`requiresApproval()`, owner
   decision 13); a new catalogue operation is four-eyes until it is classified in `CatalogCommand`. `ONHOST_FOUR_EYES=false`
   on the server is the single-operator waiver (docs/runbooks/approvals.md).
3. **Money is `Money`.** Minor units + currency; documents and ledger postings are append-only; corrections are
   new documents/transactions.
4. **Provider adapters** implement contracts in `providers/Contracts`, throw `ProviderException` with the
   taxonomy code, never leak vendor payloads, treat "already exists" as success, and ship with a contract test
   using `Http::fake()`.
5. **Events** are published via the outbox (`GenericEvent::of`), listed in
   `docs/architecture/events-catalog.md`, and routed in `NotificationRouter` when a human must see them. No
   secrets in payloads — mail secrets with `NotificationService::queueMail`.
6. **Surfaces are read-only.** `apps/surfaces/*.dc.html`, `_ds/`, `onhost-*.js` stay byte-identical to the
   prototype. Integration happens in `SurfaceRenderer` seams and `apps/surfaces/api/*` modules.
7. **Tests are part of the change.** Feature tests per domain in `tests/Feature/<Domain>`, helpers in
   `tests/Pest.php` (Pest files share one function namespace — helper names must be unique). Run
   `php artisan test` before finishing; the suite must stay green.
8. **Audit everything staff does.** `AuditRecorder::record(context, action, result, detail, resourceType,
   resourceId)`; detail is redacted automatically but never include credentials.

## Where things go

| Change | Place |
| --- | --- |
| New API endpoint | controller in `app/Http/Controllers/Api/V1` (+ `Staff/`), route in `routes/api.php`, presenter, test in `tests/Feature/Http`, then `php artisan onhost:openapi` |
| New domain rule | service in `domains/<Domain>/`, state machine as data (`StateMachine`), command + handler, tests |
| New scheduled job | `routes/console.php` (`onhost:*` command + `Schedule::command`), runbook entry |
| New notification | template in `NotificationTemplateSeeder` (cs + en), routing in `NotificationRouter`, mandatory kinds in `config/onhost.php` |
| New provider | `providers/<Vendor>`, registration in `PlatformServiceProvider::ADAPTERS`, doc in `docs/provider-adapters`, contract test |
| Public content | `ContentService`, seeders from `database/seeders/data/prototype-content.json` (regenerate with the Node export script) |

## Coordination between parallel agents

Several AI clients work here at once. Before editing, check `.\brain.ps1 task board` (active tasks and locked paths)
and work in a task worktree (`.\brain.ps1 task start …`), never on `development` directly and never on files another
task has locked. Tasks, handoffs, gate reports and the process rules live in `.ai/` (start with `.ai/PROJECT_STATE.md`).

## Environment

PHP 8.3 (`php -v`), Composer, Node ≥ 20 (content export, e2e), SQLite for tests. No Docker is required for
tests. Never run migrations or seeders against production from a workstation.

## Style

`declare(strict_types=1)`, final classes, readonly constructor promotion, early returns, no floats for money,
Czech customer-facing copy with English fallback in `{cs, en}` JSON columns. Keep comments for *why*, not *what*.
