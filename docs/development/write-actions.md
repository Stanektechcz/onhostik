# Write actions — how to add one

Decision N5 (wave 2, 2026-10-15, variant B): a checklist with links instead of a code generator.
F5 (`tests/Feature/Platform/WriteActionConventionsTest.php`) already enforces the convention for every command.

## Checklist

1. **Command** — implements `Onhost\Platform\Commands\Command` (via `GlobalCommand` / `OrganizationCommand`,
   `platform/Commands/`). `permission()` returns literal keys that exist in `PermissionCatalog`; `name()` is a
   stable audit name; `idempotencyKey()` comes from the request (`Idempotency-Key`) or a natural key.
2. **Risk** — chosen explicitly: either the permission key carries it in `PermissionCatalog`, or the command
   implements `RiskAwareCommand` (`riskLevel()`, `requiresStepUp()`, `requiresApproval()`).
   HIGH = fresh step-up, CRITICAL = step-up + four-eyes approval. There is no default risk.
3. **Handler** — registered in `App\Providers\DomainServiceProvider::HANDLERS` or named `<Command>Handler`.
4. **Controller** — validates, dispatches through the `CommandBus`, presents. No writes, no vendor calls.
5. **Events** — `GenericEvent::of(...)` through the outbox, documented in
   [events-catalog](../architecture/events-catalog.md) and routed in `NotificationRouter`.
6. **Provider work** — behind a contract in `providers/*`, contract test with `Http::fake()`.
7. **Tests** — Pest feature test (unique helper names); F5 must stay green without new allowlist entries.
8. **Routes** — run `php artisan onhost:openapi` after route changes.

## Reference action

`IncidentCommand` — command with per-op permission and risk (`domains/Incidents/Commands/IncidentCommand.php`),
handler `domains/Incidents/Commands/IncidentsCommandHandler.php`, controller
`app/Http/Controllers/Api/V1/Staff/IncidentController.php`, tests `tests/Feature/Incidents/IncidentsTest.php`
and `tests/Feature/Identity/RiskFloorTest.php`.

## Generator

Not built now (frequency of new actions not demonstrated). If one is ever added, `--risk` must be a mandatory
option with no default value, so a generated action can never silently bypass step-up.
