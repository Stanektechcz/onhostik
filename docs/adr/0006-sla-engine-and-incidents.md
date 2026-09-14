# ADR-0006 — External probes with quorum, SLO error budgets, SLA credits as documents

**Status:** accepted (2026-09) · **Blueprint:** §66, §73

## Decision

* Availability is measured from outside: ≥ 3 probe locations per public component, each reporting through its
  own bearer token to `POST /v1/probes/results`. A component is down when a 2-of-3 quorum of locations fails;
  that opens a probe-sourced incident automatically and recovery moves it to MONITORING, auto-resolving after a
  stable period. Silent probes are a monitoring problem, not an outage.
* SLI per minute = quorum healthy; SLO objectives per SLA class (`onhost.sla.classes`); windows 5m/30m/1h/6h/3d/30d;
  multi-window burn-rate alerts (14.4 / 6 / 1) and an error-budget policy that escalates from review to change
  freeze. Minutes inside SLA-excluded maintenance windows do not count.
* Incidents follow DETECTED → INVESTIGATING → IDENTIFIED → MITIGATING → MONITORING → RESOLVED → POSTMORTEM with
  public/internal updates, component states derived from open incidents, mandatory customer notifications and
  blameless post-mortems with owned actions.
* SLA credits are computed after resolution from a versioned policy (bands by achieved availability, capped),
  approved by staff, and issued as a DK credit note plus a non-refundable promo-bucket wallet credit. The
  calculation is stored with the credit for audit.

## Consequences

* The status page (`GET /v1/status`) and the prototype's `status(cs)` shape are generated from the same data.
* Compliance clocks (NIS2 24 h / 72 h / 1 month, GDPR 72 h, DSA Art. 18) are started by cyber incidents and
  tracked by `onhost:compliance:timers`, independent of the public incident.
