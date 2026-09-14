# SLOs, error budgets and on-call

## Classes (config `onhost.sla.classes`)

| Class | Objective (internal SLO) | Contractual SLA | Products |
| --- | --- | --- | --- |
| standard | 99.9 % | none (best effort, credits by goodwill) | web hosting, game servers, AI |
| business | 99.95 % | 99.9 % | managed hosting, apps, cloud, mail, payments, portal |
| ha | 99.995 % | 99.99 % | HA plans, replicated databases |
| critical | 99.999 % | 99.99 % | DNS (anycast) |

Objectives are measured monthly (30-day rolling) per status component from external probes; internal HTTP
SLIs (Prometheus rules in `infra/monitoring/slo-alerts.yml`) must agree before an alert pages.

## Error-budget policy (`onhost.sla.error_budget_policy`)

| Budget consumed | State | Consequence |
| --- | --- | --- |
| < 25 % | normal | releases as usual |
| ≥ 25 % | normal + review | weekly reliability review of the component |
| ≥ 50 % | review_high_risk | high-risk changes (migrations, provider upgrades) need SRE review |
| ≥ 75 % | reliability_priority | reliability work outranks features for that component |
| ≥ 100 % | freeze | change freeze (`sla.budget.exhausted`), only fixes for the causing incident |

## Burn-rate alerts (`onhost.sla.burn_rate_windows`)

| Short / long window | Threshold (× budget rate) | Meaning | Response |
| --- | --- | --- | --- |
| 5 m / 1 h | 14.4 | 2 % of the monthly budget in an hour | page now |
| 30 m / 6 h | 6 | 5 % in six hours | page |
| 6 h / 3 d | 1 | on track to exhaust the budget | ticket for the next working day |

## Probes (`onhost.sla.probe_locations_min` = 3, `probe_quorum` = 2)

Each public component has ≥ 3 probes in independent locations (CZ, SK, EU). A component is down when ≥ 2
locations fail in their latest interval; one failing location is a probe problem. Silent probes for
10 minutes raise `OnhostProbesSilent` (monitoring gap, not an outage).

## On-call

* Primary SRE + secondary; incident commander for p1/p2 (see `docs/runbooks/incident-response.md`).
* Pages come from the burn-rate alerts, `integration.down`, `OnhostOutboxLag` and probe quorum incidents.
* Post-mortem within 5 working days for p1/p2 and for any incident that burned more than 20 % of a budget.
* MTTR/MTTA and availability per component are on `GET /v1/staff/incidents/metrics` and
  `GET /v1/staff/reports/slo` (admin `#/reporty`).
