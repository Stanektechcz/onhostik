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

## How long things take (`OperationLatency`)

"An action is done in seconds" is a number, not a promise: the operations board (`GET /v1/staff/provisioning/board`,
`latency`) and `onhost:doctor` (`speed`) show p50 / p95 / max **from accepted to finished** by panel and action for
the last 24 hours, with the wait in the queue apart (`wait_p95_s`) — the two have different cures: more workers, or a
slow panel. Judged against `ONHOST_LATENCY_TARGET_SECONDS` (30) is only what a person waits for (restart, PHP switch,
a new database…); backups, restores, installs, imports and the creation of a service are reported without a verdict.
The table keeps whole seconds. Three runs of a kind are needed before the doctor calls it slow.

What to expect: aaPanel and Pterodactyl answer within the call; Proxmox tasks take what the hypervisor takes;
**ISPConfig applies every change by its server cron, once a minute** — its p95 cannot be under a minute until the
node runs `server.sh` more often (a systemd timer every 10–15 s is the usual cure). See audit §7, item 2.

## Drift
`onhost:provisioning:reconcile` (daily) compares desired vs actual for every bound resource:

* `ONHOST_MANAGED` fields (size, power state we own) → auto-repair when `provisioning.auto_repair` allows,
  else `REQUIRES_APPROVAL` drift;
* `PROVIDER_MANAGED` (IP assignments by the panel) → recorded, never overwritten;
* `SHARED` → flagged for a human.

Resolve at `POST /v1/staff/resource-mappings/{id}/resolve` with `approved | ignored | repair` and a note.

## A step that is run twice (Brain card H38)

A create that was accepted while its answer got lost must not create a second resource. Proxmox cannot give a clone
any tags, so a guest in the middle of being cloned is recognised by the description the clone did get
(`ONhost service <id> [idem-…]`); while it is still locked the step waits (`TRANSIENT`) instead of cloning again. Tags
(`onhost`, the service, the first writer's idempotency tag) are written with the first resize and never taken away; the
reconciler compares `onhost` + the service tag only. An order line whose money went back to the customer is not
delivered by retrying its operation (`order_item_refunded`).

## Freeze switch

`POST /v1/staff/provisioning/freeze` stops new provider mutations (operations stay queued), reads and billing
continue. Use during provider outages, data-centre work and while an SLO error budget is exhausted
(`sla.budget.exhausted`). `thaw` when done. Both require a fresh step-up and are audited with the reason.

## What a customer may say about an action (Brain card H21)

`POST /v1/services/{id}/actions` and its shorthands hand `params` to the same workflow the platform's own callers use.
For the core actions a request from anybody who is not staff keeps only these keys — the rest is dropped before it
reaches the workflow (`Services\CustomerActionParams::filter()`, used by the API, by stored action hooks — at creation and
again at every run — and by Discord commands):

| action | kept |
| --- | --- |
| `power` | `power_action`, `reason` |
| `suspend`, `resume`, `terminate`, `purge`, `backup` | `reason` |
| `restore`, `archive.restore` | `backup_id`, `reason` |
| `snapshot` | `name`, `description`, `reason` |
| `rollback_snapshot` | `name`, `reason` |
| `resize` | refused: `resize_requires_plan_change` — the size follows the plan, a plan change order resizes after the payment |

So a customer can neither resize for free, nor skip the archive before a cancellation (`archive_before_delete`), force a
purge inside the restore window, dress a backup as the final one (`kind`, `retention_days`, `protected`), nor restore
into a VM id or storage of their choosing (`options`, `target`). A restore also refuses at once a backup that is not of
this very service (404) or not finished (`backup_not_restorable`). Staff keep their overrides (a forced purge with a
reason) through the same command, on the record. A new parameter a core step reads is not reachable from the customer
API until it is added to that list on purpose.

## Capacity

`GET /v1/staff/capacity` shows node scheduler headroom (CPU/RAM/NVMe, IP pools). `capacity.unavailable` and
`ipam.threshold/exhausted` events mean orders will wait: add a node (`InfrastructureSeeder` env or admin), extend
an IP pool, or set the family to sold-out in the catalog.

How a node's free space is judged (Brain card H04): the node's last measurement (`usage`, stamped `last_seen_at`) plus
everything **placed on it since** — services paid and waiting to be built, and services activated after the sample.
Without that hold every order between two measurements was promised the same free space. Placement itself
(`NodeScheduler::place`) locks the node rows while it decides, so two workers cannot take the same reserve; the hold is
released when the service fails or is terminated, and disappears by itself once the next measurement contains it.

* `capacity_basis: sold` in a provider instance's options judges its nodes by the RAM **sold** on them instead of the
  RAM their guests use right now (Proxmox reports real use: a node full of idle VMs looks empty). The default stays
  `measured` — switching is a commercial decision, a node that is already oversold stops taking orders at once.
* A VPS or a game server that **no registered node can take** is refused in the cart: `capacity_sold_out` (409), before
  an order or a payment exists. With no node of the kind registered at all nothing is judged (provisioning is then not
  automatic). A drained or locked node sells nothing. `ONHOST_CAPACITY_GATE=false` accepts such orders again and lets
  provisioning wait for capacity. Known limits: a plan pinned to one panel is judged against the whole region, and a
  migration in flight does not hold space on its target node.
* A **web or mail hosting for a name another service already serves** is refused in the same place, for the same
  reason: `site_name_taken` (409), before an order or a payment exists. A web vhost and a mail domain are separate
  namespaces (one name is normally both), the alias list of a web line is validated with it, and one cart cannot order
  one name twice. `ServiceService::desiredSpec` asks again inside the transaction that creates the service, so two
  carts racing for one name end there. See `security-boundaries.md` §18.