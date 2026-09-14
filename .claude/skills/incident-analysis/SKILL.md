---
name: incident-analysis
description: Use when investigating an ONHOST incident, outage, alert, failed job, provider problem, or post-incident review.
---

# Incident analysis

1. Read `docs/runbooks/incident-response.md`, the relevant provider/outage runbook, and ADR 0006.
2. Establish a UTC timeline from sanitized evidence. Preserve evidence references without copying credentials or customer data.
3. Separate confirmed facts, hypotheses, and unknowns. Test the cheapest safe hypothesis first.
4. Identify customer/tenant effect, detection gap, containment, recovery, and reconciliation needs.
5. Do not run production commands. Prepare explicit commands and rollback for human approval when needed.
6. Output timeline, root/contributing causes, corrective actions with owners, tests/alerts, and follow-up ADR/runbook changes.
