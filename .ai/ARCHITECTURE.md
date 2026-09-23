# Architecture (orchestration view)

Authoritative intent: `docs/adr/0001`–`0006`, `AGENTS.md`, `README.md`. This page is the map an agent needs before
touching code; every class named here exists (checked 2026-09-23).

## Layers

```
HTTP  routes/api.php (/v1, ~480 routes) · routes/web.php (surfaces, /sprava admin, auth, legal)
  │   app/Http/Controllers/Api/V1 (+ Staff/) · app/Http/Presenters · platform/Http/Middleware
  ▼
WRITE Command (OrganizationCommand | GlobalCommand + RiskAwareCommand)
  │   platform/Commands/CommandBus.php: authorize (domains/Identity/Authorization/Authorizer.php),
  │   risk NORMAL / HIGH (step-up) / CRITICAL (step-up + four-eyes), idempotency, audit (platform/Audit/AuditRecorder.php)
  ▼   handler registered in app/Providers/DomainServiceProvider.php (HANDLERS)
DOMAIN domains/<Module>/ services, state machines as data, Eloquent models
  │   events: GenericEvent::of(...) → platform/Outbox/OutboxPublisher.php → domains/Notifications/NotificationRouter.php
  ▼
PROVIDERS providers/<Vendor> behind providers/Contracts/*; registered in app/Providers/PlatformServiceProvider.php;
          resolved through domains/Provisioning/ProviderRegistry.php; ProviderException taxonomy; redacted provider_calls
```

Reads may go straight from controller to domain service to presenter. Writes never skip the bus.

## Flows that matter

- **Order to cash:** cart → `domains/Orders/QuoteService.php` → `CheckoutService.php` → invoice/proforma
  (`domains/Invoicing/InvoiceService.php`) → payment (`domains/Payments/PaymentService.php`, provider callbacks) →
  `OrderSettlement.php` (capture delivered lines, credit the rest) → wallet ledger (`domains/WalletLedger/WalletService.php`, append-only).
- **Provisioning:** an `Operation` runs a workflow from `domains/Provisioning/Workflows/*` on a `provider-*` queue via
  `domains/Provisioning/OperationRunner.php`; each step calls an adapter; failures compensate; async provider results
  (e.g. ISPConfig) are confirmed later; `Reconciler.php` compares desired vs actual; placement by `Scheduling/NodeScheduler.php`.
- **Service actions:** customer API → `domains/Services/Commands/ServicesCommandHandler.php` →
  `CustomerActionParams.php` (allow-list boundary) → `Workflows/ServiceActionWorkflow.php`.
- **Renewal / dunning:** scheduled commands in `routes/console.php` → `domains/Billing/DunningService.php`, renewals, wallet.
- **Surfaces (UI):** `app/Http/Controllers/Web/SurfaceController.php` serves the prototype HTML transformed by
  `app/Http/Support/SurfaceRenderer.php` seams, fed by `SurfaceDataController.php`; behaviour lives in `apps/surfaces/api/*.js`.

## Cross-cutting guards (do not bypass)

`platform/Http/EgressGuard.php` (SSRF), `platform/Http/Middleware/IdempotencyKey.php`, `platform/Redaction/Redactor.php`
(outbox + logs redact by key fragment), `platform/Secrets/SecretStore.php`, `ApiController::idempotencyKey()`/`onceKey()`,
`onhost:doctor`. Security rules and past incidents: `docs/runbooks/security-boundaries.md`.

## Known structural facts

- `Services` ⇄ `Provisioning` are mutually dependent (see `.ai/DEPENDENCY_MAP.md`) — one ownership unit.
- `providers/` imports `Onhost\Domain\Provisioning` (15) and `Payments` (2); `platform/` imports `Compliance` (1).
  These are existing exceptions to "providers/platform do not know domains"; do not add new ones without an ADR.
- Prototype files (`apps/surfaces/*.dc.html`, `_ds/`, `onhost-*.js`) are read-only by rule (ADR 0005).
