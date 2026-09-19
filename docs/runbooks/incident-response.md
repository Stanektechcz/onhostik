# Incident response

Roles: **incident commander** (IC, role `incident_commander` or SRE), **communications** (status page updates),
**operations** (hands on providers). One person may hold several roles below p2.

## 1. Detect

* Probe quorum (2 of 3 external locations) opens a `source=probes` incident automatically
  (`SlaService::evaluateQuorum`, every 5 min via `onhost:sla:evaluate`, immediately on ingest).
* One customer's service (Brain card H14) is watched per service, not per component: sites by the HTTP uptime monitor
  (`monitoring.down` / `monitoring.up`, every minute), a VPS or a game server by the power state the reconciler reads
  from the panel anyway (`AvailabilityWatch`: `service.stopped_unexpectedly` after
  `ONHOST_MONITORING_POWER_PASSES` = 2 consecutive readings, `service.running_again` on recovery). Every alarm carries
  the service id (event aggregate, notification and mail reference). The customer is told once per episode and can
  switch the mail off per service (`PUT /v1/services/{id}/policy {availability_alerts:false}`); operations are told only
  for a service with an SLA class. It stays quiet for a stop ordered through the platform, an operation in flight, a
  suspended service, a panel under a maintenance lock and a game server whose own schedule stops it. Detection takes
  one to two reconcile intervals (15 min standard, 5 min for SLA classes) — many of these at once on one node is a
  node problem: open an incident.
* The platform's **own mail** (Brain card H24) is watched by `onhost:mail:health` every five minutes from the outbox
  the sender already keeps: `platform.mail.failing` when deliveries errored and none went through in the last
  `ONHOST_MAIL_HEALTH_WINDOW` (30) minutes, or when a due mail waited `ONHOST_MAIL_HEALTH_STALLED` (15) minutes without
  an attempt (nobody runs `onhost:mail:send`). It goes to the staff inbox and the pager — never by mail — once per
  outage. **While it lasts, dunning suspends and terminates nobody**: reminders that did not arrive are no ground for
  it. The alarm ends only on a delivery that went through (silence after everything gave up is not recovery; one dead
  mail is put back as a probe each pass) or on a successful `php artisan onhost:mail:test <address>`. Mails that gave
  up during the outage are queued again automatically. The doctor shows the same reading (`mail · outbox is leaving`).
* Customer reports arrive as tickets in the `dostupnost` topic; support opens an incident with
  `POST /v1/staff/incidents` when more than one customer or a shared component is affected.
* Security suspicion → open a **cyber incident** first (`POST /v1/staff/security/incidents`), which starts the
  NIS2/GDPR clocks; link a public incident only with `security: true` (no exploit detail on the status page).

## 2. Classify

| Severity | Meaning | Status page | Post-mortem |
| --- | --- | --- | --- |
| p1 | shared component down for many customers (major_outage) | immediately, mandatory mails | mandatory |
| p2 | partial outage / degraded shared component | immediately | mandatory |
| p3 | single customer or minor degradation | if visible externally | optional |
| p4 | cosmetic / internal | internal only | no |

## 3. Communicate

1. `POST /v1/staff/incidents` — title in plain language, components, impact, affected services (customers of
   those services receive the mandatory `incident.affecting` notification and mail).
2. Every 30 minutes for p1/p2 (15 minutes while MITIGATING): `POST /v1/staff/incidents/{id}/updates` with a
   public note. Internal notes use `public: false`.
3. State transitions: INVESTIGATING → IDENTIFIED → MITIGATING → MONITORING. Reopen with a transition back to
   INVESTIGATING if the fix does not hold.

## 4. Mitigate

* Provider-side actions go through the provisioning queue (retry/cancel) or the service action API, never by
  hand on the hypervisor. If mutations must stop: `POST /v1/staff/provisioning/freeze` (step-up required).
* **Overload: overviews go first (Brain card H139).** When operations pile up behind their due time
  (`onhost.provisioning.backlog`: more than `threshold` operations overdue by `age_minutes`), the scheduled
  `onhost:integrations:health` pass switches load shedding on by itself and says so
  (`platform.load_shedding.started` / `.ended`). Reports (`/v1/staff/reports/*`), analytics and the customer's
  cross-service overviews (`/v1/monitors`, `/v1/backups`) then answer 503 `load_shedding` with `Retry-After` — refused,
  never served stale. Service actions, restores, access management, payments, the operations board and every
  per-service page are **not** behind the switch. State and override: `GET /v1/staff/provisioning/load`,
  `PUT /v1/staff/provisioning/load {mode: on|off|auto, reason}` (permission `provisioning.freeze`; `on`/`off` need a
  reason, `auto` hands it back to the measurement). A verdict nobody refreshed for 15 minutes expires, so a stopped
  scheduler cannot keep reports dark. New routes that can wait get the `shed` middleware; nothing else may.
* For registrar/DNS incidents see [domains-registrar.md](domains-registrar.md); for billing side effects
  (renewals failing during the incident) see [billing-dunning.md](billing-dunning.md).

## 5. Resolve

`POST /v1/staff/incidents/{id}/resolve` with a customer-readable note. The component returns to
`operational`, customers receive `incident-resolved` mail, MTTR is recorded.

## 6. Post-mortem (p1/p2, or > 20 % of an error budget)

Within 5 working days: `POST /v1/staff/incidents/{id}/postmortem` with summary, root cause, timeline and
corrective actions (owner + deadline each). Publish it (`publish: true`) unless legal or security says no.
The status page lists published post-mortems at `GET /v1/incidents/postmortems`.

## What counts as planned maintenance (Brain card H15)

One definition, in `Maintenance::excludesFromSla()`, used by the SLO windows and by the downtime behind SLA credits:
a window takes minutes out of the SLA only when it was **approved by a second person at least
`onhost.status.maintenance_lead_hours` (48 h) before it starts** — the approval is what announces it to the customers,
and its moment is kept as `announced_at`. Everything else is downtime like any other:

* an **emergency** window (short notice) is allowed and always `counted`, whatever the request asked for;
* a window scheduled in time but **approved late** becomes `counted` at approval (audit detail `late: true`);
* a window **cannot start in the past** (`maintenance_in_past`) — an outage that already happened is an incident — and
  cannot be approved after it ended (`maintenance_window_passed`).

The staff record shows `announced_at`, `emergency` and `sla_excluded`; the public status page shows
`counts_toward_sla` for every coming window. Windows approved before this rule have `announced_at` = their creation.

## 7. SLA credits

After resolution, `POST /v1/staff/incidents/{id}/sla-credits` computes candidates for contractual services
(business/ha/critical). Finance approves (`/v1/staff/sla-credits/{id}/approve`) and issues with a fresh step-up
(`/issue`) → DK credit note + promo wallet credit + customer mail. Never issue credits by wallet adjustment.
