# ADR-0004 — WEDOS WAPI as registrar, PowerDNS as canonical DNS, WEDOS Zone as fallback

**Status:** accepted (2026-09) · **Blueprint:** §60–§64

## Decision

* Domain lifecycle (register, renew, transfer in/out, nameservers, DNSSEC keys at the registry) goes through
  `WedosRegistrarProvider` over `WapiGateway`: SHA-1 hourly auth in Europe/Prague, allow-listed command schema,
  token buckets (`wapi:all` 1000/h, `wapi:domain` 100/h with a 15 % reserve for renewals), clock-skew gate,
  invalid-request circuit breaker, deterministic `clTRID onhost:v4:<cmd>:<op>` and a full `registrar_operations`
  log. Result codes are mapped by `WedosErrorMap` (1001 = async accepted → poll until active).
* Authoritative DNS is PowerDNS (`PowerDnsProvider`, RRset PATCH + NOTIFY). Zones are edited in two phases
  (staged changes → atomic commit with versions and rollback). WEDOS Zone (`WedosZoneDnsProvider`) implements
  the same `ZoneDnsProvider` contract as a fallback/secondary and can be selected per zone.
* Renewals are scheduled by `DomainRenewalScheduler` with wallet holds, critical-domain policy (never let a
  critical domain lapse — escalate instead) and registrar-credit monitoring (`registrar.credit.low`).

## Consequences

* Registrar and DNS can be swapped per domain without customer migration; the control plane remains the truth
  for records and contacts.
* WAPI contract tests (`tests/Contract/WedosContractTest.php`) pin request encoding, auth and rate limiting;
  production keys are never used in tests.
