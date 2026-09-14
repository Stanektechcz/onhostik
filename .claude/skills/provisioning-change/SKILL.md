---
name: provisioning-change
description: Use when changing ONHOST service provisioning, domains, DNS, registrars, asynchronous jobs, provider adapters, or reconciliation.
---

# Provisioning change

1. Read ADR 0002/0004, provider contracts, and the matching provisioning or registrar runbook.
2. Model the transition and recovery path before code; define idempotency and “already exists” behavior.
3. Write failing domain and `Http::fake()` contract tests, including timeouts, retryable failures, permanent failures, and replay.
4. Keep vendor payloads inside the adapter and publish documented outbox events.
5. Add reconciliation, audit, and operator visibility for asynchronous work.
6. Run focused tests, `brain.ps1 test`, and architecture/security reviews. Never call a live provider during development.
