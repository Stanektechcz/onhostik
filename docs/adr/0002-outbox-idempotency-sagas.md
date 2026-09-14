# ADR-0002 — Transactional outbox, idempotency keys and step-based sagas

**Status:** accepted (2026-09) · **Blueprint:** §11, §14, §31

## Context

Provider calls are slow, asynchronous (Proxmox UPIDs, ISPConfig job queue, WAPI 1001 accepted) and fail
transiently. Customers retry buttons; webhooks arrive twice; a crash between "charged the wallet" and
"created the VM" must never leave money or resources dangling.

## Decision

* **Outbox:** domain events are written in the same transaction as the change and relayed afterwards; consumers
  are idempotent on `message id`. No direct side effects (mail, webhooks, provider calls) inside transactions.
* **Idempotency:** every command carries an idempotency key (client `Idempotency-Key` header or a derived hash);
  replays return the stored result within the TTL. Provider adapters use deterministic remote names
  (`onhost-<service ulid>`) and treat "already exists" as success (`ProviderResult::alreadyExisted`).
* **Sagas:** provisioning and service actions are `Operation`s with ordered steps; each step is idempotent,
  can wait on an async handle (`AsyncHandle` polled by `awaitStatus`), and declares a compensation. Retries
  follow `RetryPolicy` with a hard `retry_until`; failures compensate in reverse order and never leave a
  service in a transient state (`settleTransient`).
* **Freeze switch:** a global switch pauses new provider mutations during incidents without stopping reads.

## Consequences

* Tests drive sagas with `driveOperation()` because the sync queue never re-dispatches waiting operations.
* Every provider adapter must expose an `awaitStatus` that maps vendor task states to `AsyncStatus`.
* Operators retry/cancel through the bus (audited), never by editing rows.
