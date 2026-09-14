# ONHOST engineering rules

This repository is the ONHOST control plane. Start with `docs/context/PROJECT.md`, then read only the domain docs, ADRs, tests, and source files needed for the task. Active priorities and known risks are in `docs/context/CURRENT_STATE.md`; generated snapshots are in `docs/generated`.

## Non-negotiables

1. **No placeholders in production paths.** Incomplete work is absent or behind a failing-closed feature flag.
2. **Writes use the CommandBus.** Add a `Command` (`OrganizationCommand` or `GlobalCommand` + `RiskAwareCommand`) and handler in `DomainServiceProvider::HANDLERS`. Money, publishing, credentials, and legal actions are HIGH or CRITICAL risk.
3. **Money is `Money`.** Use minor units plus currency. Documents and ledger entries are append-only; corrections create new records.
4. **Providers stay behind contracts.** Adapters in `providers/*` throw taxonomy-coded `ProviderException`, do not leak vendor payloads, treat “already exists” as success, and have `Http::fake()` contract tests.
5. **Events use the outbox.** Publish with `GenericEvent::of`, update `docs/architecture/events-catalog.md`, and route human-facing events through `NotificationRouter`. Never include secrets in payloads.
6. **Prototype surfaces are immutable.** Do not modify `apps/surfaces/*.dc.html`, `_ds/`, or `onhost-*.js`. Integrate through `SurfaceRenderer` and `apps/surfaces/api/*`.
7. **Tests ship with behavior changes.** Put domain tests in `tests/Feature/<Domain>`; Pest helper names are globally unique. Run `brain.ps1 test` before finishing.
8. **Staff actions are audited.** Use `AuditRecorder::record`; do not pass credentials even though details are redacted.

## Change map

| Change | Required path and follow-up |
| --- | --- |
| API endpoint | `app/Http/Controllers/Api/V1`, `routes/api.php`, presenter, `tests/Feature/Http`; run `php artisan onhost:openapi` |
| Domain rule | `domains/<Domain>`, state machine data, command + handler, tests |
| Scheduled job | `routes/console.php`, `onhost:*` command, scheduler, runbook |
| Notification | `NotificationTemplateSeeder` in Czech and English, `NotificationRouter`, `config/onhost.php` |
| Provider | `providers/<Vendor>`, `PlatformServiceProvider::ADAPTERS`, provider doc, contract test |
| Public content | `ContentService`; regenerate prototype content with the existing Node export script |
| UI | Preserve the prototype; follow `docs/design/DESIGN_SYSTEM.md` and `docs/ui/template-inventory.md` |

## Quality and safety

- PHP 8.3, Composer, Node 20+, Pest, Pint, Larastan, Playwright; SQLite is used for tests.
- Use `declare(strict_types=1)`, final classes, readonly constructor promotion, early returns, and no floats for money.
- Customer copy is Czech with English fallback in `{cs, en}` JSON columns. Comments explain why.
- Never read, print, commit, or send `.env*`, credentials, private storage, databases, logs, auth files, or production data to an AI service.
- Do not run production migrations, seeders, deploys, DNS/provider writes, billing operations, or destructive Git commands without explicit human approval.
- Refresh compact context with `brain.ps1 update`; use Git history or Serena only when the current task needs it.
