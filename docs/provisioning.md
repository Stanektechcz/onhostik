# Provisioning

## Architecture

```text
InvoicePaid ──► HandleInvoicePaid (sync listener, inside payment tx)
                 ├─ order → processing (audited)
                 ├─ Service firstOrCreate per order item   (idempotent)
                 ├─ dispatch ProvisionHostingServiceJob    (queue: provisioning-high)
                 └─ dispatch RegisterDomainJob             (queue: provisioning-high)

Job ──► ProvisioningTask (one row per logical operation, reused on retry)
    ──► DriverResolver ──► AapanelMockDriver / WedosMockRegistrar (Phase 2)
    ──► Service / DomainRegistration state + activity log
```

Rules (enforced by the `ProvisioningDriverInterface` contract and reviews):

- Drivers are **never** called from controllers — only from queued jobs
  (the single exception: cheap, rate-limited domain availability checks).
- Every operation is **idempotent**: `Service.external_id` and the
  task-per-operation model make re-runs no-ops.
- Every operation is **logged**: a `ProvisioningTask` row carries status,
  attempts, sanitized payload/result, error message and timestamps; business
  events additionally go to the Spatie activity log
  (`provisioning.started/succeeded/failed/manual_review/retry_requested`,
  `domain.registration_started/registered/registration_failed`).
- Every operation is **retryable & reviewable**: failed / manual-review tasks
  expose a retry button in `/admin/provisioning`; the job re-checks
  idempotency and attempt limits (`max_attempts`, default 3 → `ManualReview`).

## Task lifecycle

`pending → running → success | failed`; `failed → retrying → running …`;
after `max_attempts`: `manual_review`. Retries **reuse the same task row**
(`attempts++`) so history stays auditable in one place.

## Simulated failures (mock mode)

The checkout exposes *DEV: simulate provisioning failure* (mock mode only).
The flag travels in the order item `config` → task `payload` → driver. It is
**consumed on first failure** (stripped from the payload), so the admin
retry path then succeeds — the full failure → retry → success loop is
exercisable end to end without any real backend.

## Server selection

`HandleInvoicePaid` picks the active server for the product's driver,
preferring `is_default`. Local/dev uses the seeded `MOCK-AAP-01`
(`MockServerSeeder`, `mock_mode=true`). Capacity-aware placement and health
checks arrive with the real drivers.

## Driver slots

| driver | Phase 2 status |
|---|---|
| `aapanel` | **MOCK implemented** (`AapanelMockDriver`) — see [aapanel.md](aapanel.md) |
| `wedos` | **MOCK implemented** (`WedosMockRegistrar`, registrar contract) — see [wedos-wapi.md](wedos-wapi.md) |
| `proxmox` | reserved slot — intentionally NOT implemented |
| `pterodactyl` | reserved slot — intentionally NOT implemented |

With `PROVISIONING_MOCK_MODE=false` the `DriverResolver` throws — there is
no code path that can reach a real API in Phase 2.
