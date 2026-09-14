# Compliance map

| Obligation | Implementation | Evidence |
| --- | --- | --- |
| GDPR Art. 15/20 access & portability | `POST /v1/data-requests {kind: export}` → JSON archive (organization, members, services, domains, documents, tickets) | `data_requests`, audit `compliance.data_request.export`, mail `data-export` |
| GDPR Art. 17 erasure | `kind: deletion` — blocked by live services, open invoices, legal hold; anonymises org/users, keeps tax documents and audit | `data_requests.meta`, audit `compliance.data_request.deleted` |
| GDPR Art. 33 breach notification (72 h) | cyber incident with `personal_data_breach` starts `GDPR_72H` timer | `compliance_timers`, `cyber_incidents` |
| NIS2 incident reporting (24 h / 72 h / 1 month) | `nis2_scope` cyber incidents start the three timers; scope heuristic from DNS/registrar volume thresholds | timers + `authority_reference` on submission |
| DSA Art. 16–17, 20 | public notice form with mandatory fields, statement of reasons to the customer as a ticket, 6-month complaint window; Art. 18 timer for life/safety categories | `abuse_cases`, tickets channel `abuse` |
| Data Act switching (30 days) | `kind: switching` request + `DATA_ACT_SWITCHING` timer; exports include portable formats (JSON, BIND, UBL) | timers, export archive |
| Legal hold | organization + service flag set by compliance/legal (step-up), blocks deletion and retention purge | audit `compliance.legal_hold.*` |
| Tax documents (10-year retention, immutability) | issued documents frozen with hash, PDF and UBL; corrections only by DK/OD | `invoices`, `ledger_*` |
| Access control & accountability | RBAC catalog, step-up for HIGH, four-eyes for CRITICAL, hash-chained audit (`onhost:ledger:verify`, `audit:verify-chain`) | `audit_events` |
| Supply chain / CRA | `compliance/oss-inventory.yml` generated from composer.lock; executors listed with licences | inventory file |
| Security incidents | separate `security` incidents on the status page without exploit details; SOC role; evidence with SHA-256 and hold flag | `cyber_incidents.evidence` |

Runbook: `docs/runbooks/compliance-requests.md`. Configuration: `config/onhost.php` → `compliance`.
