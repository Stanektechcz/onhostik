# Provisioning queue, drift and the freeze switch

## Reading the queue

`GET /v1/staff/provisioning/jobs` lists operations (sagas) with state QUEUED → RUNNING → WAITING → SUCCEEDED |
FAILED | COMPENSATING | CANCELLED | DEAD. `GET …/jobs/{id}` adds attempts and the redacted provider-call log
(method, path, status, duration, error class) — enough to diagnose without vendor payloads.

* **WAITING** — the step is polling an async handle (UPID, ISPConfig job, WAPI async, Kubernetes rollout).
  `next_run_at` says when it polls again. Nothing to do unless it exceeds `retry_until`.
* **FAILED (retryable)** — provider `TRANSIENT/RATE_LIMIT/CIRCUIT_OPEN`. Retry after the cause is fixed:
  `POST …/retry` with a reason. Retries resume at the failed step; earlier steps are idempotent.
* **FAILED (not retryable)** — `VALIDATION`, `CONFLICT`, `AUTH`. Fix the cause (spec, credentials, capacity),
  then retry; the retry re-validates the desired state.
* **DEAD** — exhausted `retry_until`. The service was settled to a safe state (`settleTransient`); the customer
  was notified (`service.failed`) and support has a ticket. Retry only after a root-cause fix.
* **Cancel** (`POST …/cancel`, infrastructure admin, step-up) triggers compensation of completed steps
  (delete the half-created VM, release addresses, release the wallet hold). Cancelling a WAITING operation
  whose remote task already succeeded creates drift; run reconcile afterwards.

## Drift

`onhost:provisioning:reconcile` (daily) compares desired vs actual for every bound resource:

* `ONHOST_MANAGED` fields (size, power state we own) → auto-repair when `provisioning.auto_repair` allows,
  else `REQUIRES_APPROVAL` drift;
* `PROVIDER_MANAGED` (IP assignments by the panel) → recorded, never overwritten;
* `SHARED` → flagged for a human.

Resolve at `POST /v1/staff/resource-mappings/{id}/resolve` with `approved | ignored | repair` and a note.

## Freeze switch

`POST /v1/staff/provisioning/freeze` stops new provider mutations (operations stay queued), reads and billing
continue. Use during provider outages, data-centre work and while an SLO error budget is exhausted
(`sla.budget.exhausted`). `thaw` when done. Both require a fresh step-up and are audited with the reason.

## Capacity

`GET /v1/staff/capacity` shows node scheduler headroom (CPU/RAM/NVMe, IP pools). `capacity.unavailable` and
`ipam.threshold/exhausted` events mean orders will wait: add a node (`InfrastructureSeeder` env or admin), extend
an IP pool, or set the family to sold-out in the catalog.
