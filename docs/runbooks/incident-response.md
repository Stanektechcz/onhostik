# Incident response

Roles: **incident commander** (IC, role `incident_commander` or SRE), **communications** (status page updates),
**operations** (hands on providers). One person may hold several roles below p2.

## 1. Detect

* Probe quorum (2 of 3 external locations) opens a `source=probes` incident automatically
  (`SlaService::evaluateQuorum`, every 5 min via `onhost:sla:evaluate`, immediately on ingest).
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
* For registrar/DNS incidents see [domains-registrar.md](domains-registrar.md); for billing side effects
  (renewals failing during the incident) see [billing-dunning.md](billing-dunning.md).

## 5. Resolve

`POST /v1/staff/incidents/{id}/resolve` with a customer-readable note. The component returns to
`operational`, customers receive `incident-resolved` mail, MTTR is recorded.

## 6. Post-mortem (p1/p2, or > 20 % of an error budget)

Within 5 working days: `POST /v1/staff/incidents/{id}/postmortem` with summary, root cause, timeline and
corrective actions (owner + deadline each). Publish it (`publish: true`) unless legal or security says no.
The status page lists published post-mortems at `GET /v1/incidents/postmortems`.

## 7. SLA credits

After resolution, `POST /v1/staff/incidents/{id}/sla-credits` computes candidates for contractual services
(business/ha/critical). Finance approves (`/v1/staff/sla-credits/{id}/approve`) and issues with a fresh step-up
(`/issue`) → DK credit note + promo wallet credit + customer mail. Never issue credits by wallet adjustment.
