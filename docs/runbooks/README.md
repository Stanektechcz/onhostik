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
| [production-readiness-audit.md](production-readiness-audit.md) | Findings with evidence (§7 table), what is fixed, what stays open |
| [security-boundaries.md](security-boundaries.md) | The rules the customer-facing edge keeps (parameter allow-lists, egress, keys and tokens, roles, money) — read before adding an endpoint |
| [provider-outage.md](provider-outage.md) | Proxmox / ISPConfig / aaPanel / Pterodactyl / PowerDNS / WEDOS unreachable or erroring |
| [panel-upgrade.md](panel-upgrade.md) | upgrading a panel: what the version gate does by itself, the maintenance window, accepting a version the adapter was not verified on |
| [provisioning-queue.md](provisioning-queue.md) | failed or stuck operations, drift, freeze switch, capacity |
| [domains-registrar.md](domains-registrar.md) | WEDOS credit, renewals at risk, async registrations, reconciliation |
| [billing-dunning.md](billing-dunning.md) | past-due invoices, suspension/resume, refunds, reconciliation mismatches |
| [vat-and-vies.md](vat-and-vies.md) | VAT numbers, VIES and reverse charge: the go-live switch, `onhost:vat:verify`, the re-check rule, the staff override, partner self-billing VAT (adapter: [../provider-adapters/vies.md](../provider-adapters/vies.md)) |
| [compliance-requests.md](compliance-requests.md) | GDPR export/deletion, legal hold, DSA notices, regulatory timers |
| [console-relay.md](console-relay.md) | VNC / game console access path and the relay service |
| [release-and-rollback.md](release-and-rollback.md) | deploying the control plane, migrations, rollback, error-budget freeze |

On-call rotation, escalation contacts and SLA classes are in `docs/sre/` and `config/onhost.php` (`sla`).

- [deploy-aapanel.md](deploy-aapanel.md) — the control plane on the aaPanel host (staging.onhost.cz, then onhost.cz): `infra/aapanel/install.sh`, `deploy.sh`, the nginx site snippet, the values the operator fills in.
