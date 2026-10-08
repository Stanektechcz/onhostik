# Runbooks

Operational procedures for the ONhost control plane. Every action below is performed through the staff API
(`/v1/staff/*`) or `artisan onhost:*` commands so it is authorized, step-up protected where required, and
recorded in the hash-chained audit log. Never edit rows in the database during an incident.

| Runbook | When |
| --- | --- |
| [incident-response.md](incident-response.md) | probes or customers report an outage; severity, status page, post-mortem |
| [service-sharing-and-assistant.md](service-sharing-and-assistant.md) | One service shared with another person (capabilities, guest, expiry, revocation) and what the AI assistant may see and offer — for customers, guests and staff |
| [approvals.md](approvals.md) | Four eyes: a critical staff action waits for a second person — how a request is opened, who may decide it, the single-operator switch on the server |
| [backups.md](backups.md) | What a backup of a service is (web: the platform's own set of files + every database, fresh or failed), download, restore, delete, retention, off-site; what to check when one fails |
| [historical-site-import.md](historical-site-import.md) | A site on a live panel that the platform did not create: only at its owner's request, imported into a NEW site the platform creates; the historical resource is never bound or touched (ADR-0007, decision 22) |
| [provider-calls-audit.md](provider-calls-audit.md) | Read-only check whether an ISPConfig record was acted on by a service that did not own it (the TASK-0005 hole, before the fix); incident steps |
| [pricing.md](pricing.md) | Discounts, promo codes, add-ons, plan versions, catalogue revisions (`onhost:catalog:revise`), paid limit raises and what a plan may promise |
| [go-live-checklist.md](go-live-checklist.md) | Everything production needs before the first paying customer, with the state of each item and the operator steps of the default-off switches |
| [first-day-production.md](first-day-production.md) | Hour-by-hour checks of the first production day (doctor, dead letters, payments and Comgate reconciliation, dunning, webhook lane, metrics) and the rollback triggers |
| [production-readiness-audit.md](production-readiness-audit.md) | Findings with evidence (§7 table), what is fixed, what stays open |
| [security-boundaries.md](security-boundaries.md) | The rules the customer-facing edge keeps (parameter allow-lists, egress, keys and tokens, roles, money) — read before adding an endpoint |
| [provider-outage.md](provider-outage.md) | Proxmox / ISPConfig / aaPanel / Pterodactyl / PowerDNS / WEDOS unreachable or erroring |
| [panel-upgrade.md](panel-upgrade.md) | upgrading a panel: what the version gate does by itself, the maintenance window, accepting a version the adapter was not verified on |
| [provisioning-queue.md](provisioning-queue.md) | failed or stuck operations, drift, freeze switch, capacity |
| [webhooks.md](webhooks.md) | customer webhooks: the `webhooks` queue lane and its fallback, rotating a signing secret with an overlap window, suspended endpoints |
| [outbox-dead-letters.md](outbox-dead-letters.md) | events the outbox relay gave up on after 10 attempts: doctor row, `OnhostOutboxDeadLetters` alert, `onhost:outbox:dead-letters` (list, `--requeue`) |
| [domains-registrar.md](domains-registrar.md) | WEDOS credit, renewals at risk, async registrations, reconciliation |
| [billing-dunning.md](billing-dunning.md) | past-due invoices, suspension/resume, refunds, reconciliation mismatches |
| [vat-and-vies.md](vat-and-vies.md) | VAT numbers, VIES and reverse charge: the go-live switch, `onhost:vat:verify`, the re-check rule, the staff override, partner self-billing VAT (adapter: [../provider-adapters/vies.md](../provider-adapters/vies.md)) |
| [compliance-requests.md](compliance-requests.md) | GDPR export/deletion, legal hold, DSA notices, regulatory timers |
| [console-relay.md](console-relay.md) | VNC / game console access path and the relay service |
| [release-and-rollback.md](release-and-rollback.md) | deploying the control plane, migrations, rollback, error-budget freeze |
| [staging-aapanel.md](staging-aapanel.md) | `infra/aapanel/staging.sh` on an aaPanel host: the usranalyse drop-ins and how to verify them, `public/build` and file modes, the setup commit, a parked release and how to go back |
| [comgate-merchant-review.md](comgate-merchant-review.md) | What the public site must show for Comgate's manual merchant review (I-R10): checklist mapped to the repository, gaps, owner inputs |
| [comgate.md](comgate.md) | Comgate gateway (H-R8): where the credentials live, the administration's check (connection, 1 Kč test payment), the four-eyes test-mode switch, what is not verified live |
| [penpot.md](penpot.md) | Penpot for web hosting (H8): the delivery model (one compose stack per service on a dedicated Penpot node), the server prerequisites before the first sale, the node build scripts (`infra/penpot/`) with run order, sizing and rollback, secrets, backups, outages, upgrades |
| [staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md) | The go-live rehearsal on staging (H6): numbered steps R0–R24 with precondition, command, expected result, doctor rows, rollback and the owner's yes per step, plus the protocol table to fill in — prepared, not executed |
| [staging-rehearsal-2026-10-07.md](staging-rehearsal-2026-10-07.md) | Protocol of the rehearsal (phase I, I6): header, step log R0–R24 with the owner's yes per step, the R1/R23 doctor rows, outputs only (no secrets) — skeleton, no step run yet |

On-call rotation, escalation contacts and SLA classes are in `docs/sre/` and `config/onhost.php` (`sla`).

- [deploy-aapanel.md](deploy-aapanel.md) — the control plane on the aaPanel host (staging.onhost.cz, then onhost.cz): `infra/aapanel/install.sh`, `deploy.sh`, the nginx site snippet, the values the operator fills in.
