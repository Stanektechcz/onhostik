# Runbooks

Operational procedures for the ONhost control plane. Every action below is performed through the staff API
(`/v1/staff/*`) or `artisan onhost:*` commands so it is authorized, step-up protected where required, and
recorded in the hash-chained audit log. Never edit rows in the database during an incident.

| Runbook | When |
| --- | --- |
| [incident-response.md](incident-response.md) | probes or customers report an outage; severity, status page, post-mortem |
| [provider-outage.md](provider-outage.md) | Proxmox / ISPConfig / aaPanel / Pterodactyl / PowerDNS / WEDOS unreachable or erroring |
| [provisioning-queue.md](provisioning-queue.md) | failed or stuck operations, drift, freeze switch, capacity |
| [domains-registrar.md](domains-registrar.md) | WEDOS credit, renewals at risk, async registrations, reconciliation |
| [billing-dunning.md](billing-dunning.md) | past-due invoices, suspension/resume, refunds, reconciliation mismatches |
| [compliance-requests.md](compliance-requests.md) | GDPR export/deletion, legal hold, DSA notices, regulatory timers |
| [console-relay.md](console-relay.md) | VNC / game console access path and the relay service |
| [release-and-rollback.md](release-and-rollback.md) | deploying the control plane, migrations, rollback, error-budget freeze |

On-call rotation, escalation contacts and SLA classes are in `docs/sre/` and `config/onhost.php` (`sla`).
