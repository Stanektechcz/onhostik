# Infrastructure

| Path | Purpose |
| --- | --- |
| `docker-compose.yml`, `docker/app.Dockerfile` | local stack: app, queue worker, scheduler, console relay, PostgreSQL 16, Redis 7, Mailpit, Prometheus |
| `console-relay/` | websocket relay for VNC/game consoles (see `docs/runbooks/console-relay.md`) |
| `monitoring/` | Prometheus scrape config and SLO burn-rate / platform alert rules (mirrors `config/onhost.php` → `sla`) |
| `rke2/policies/` | Kyverno baseline + default-deny NetworkPolicy for customer app namespaces (mirrors `ManifestFactory`) |
| `opentofu/` | cloud/DC resources for the control plane (PostgreSQL HA, Redis, object storage for exports/backups, load balancer, DNS records) |
| `ansible/` | provider hosts: Proxmox API tokens, ISPConfig remote users, PowerDNS API, probes (3 external locations) |

Environments: `dev` (this compose file, DevInfrastructureSeeder), `staging` (real provider labs, WEDOS test
account, Comgate sandbox), `production`. Secrets are never in git: `SecretStore` resolves `env://` references
from the environment (systemd credentials / Kubernetes secrets) or `openbao://` paths.

External probes run from three independent locations (CZ, SK, EU) as tiny agents that POST results to
`/v1/probes/results` with their own token (`POST /v1/staff/probes` registers them); the ansible role
`onhost_probe` installs them.
