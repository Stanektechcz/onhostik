# Monitoring: Prometheus, Alertmanager and Grafana on a small VM (P12, P-R19)

**Prepared, not deployed.** Decision P-R19: monitoring runs on a small separate VM outside production; Alertmanager sends pages to
the platform (`POST /v1/webhooks/alertmanager`, which opens an on-call alert) **and** by e-mail to the owner, tickets by e-mail
(P-R40: the owner, the second person of PR-5 as stand-in). Still open: **PR-13** (which machine) and **OP-4** (the e-mail address
and stand-in). Nothing here is done before both are answered and the owner says "ano" to the steps (mode V).

## What is in the repository

| File | What |
| --- | --- |
| `infra/monitoring/prometheus.yml` | scrape of `/metrics` (bearer `ONHOST_METRICS_TOKEN`), rule file, Alertmanager target |
| `infra/monitoring/slo-alerts.yml` | 22 alerts in three groups: SLO burn rates, platform (outbox, provisioning, providers, registrar credit, platform backup, scheduler/worker heartbeat, scanner, on-call, probes) and **operations (P12)**: a scheduled job past its budget (P14), certificates expiring within 7 days / expired, the bank statement import older than 4 days, unmatched bank lines for a day, disk below 20 % / 5 % |
| `infra/monitoring/slo-alerts.test.yml` | a `promtool test rules` unit test for every alert (CI job **monitoring**, `infra/monitoring/ci/promtool.sh`; `AlertRulesTest` fails on an alert without a test or on a metric `/metrics` does not export) |
| `infra/monitoring/alertmanager.yml` | `severity: page` → platform webhook + e-mail; everything else → e-mail (`amtool check-config` in CI) |
| `infra/monitoring/grafana/` | data source and the dashboard *ONhost · provoz* |

The scheduler tick and the queue worker heartbeat are `OnhostAutomationDead` (`onhost_automation_alive{machine="scheduler"|"worker"}`);
the platform backup verification is `OnhostBackupStale`.

## Steps for the owner on the monitoring VM (after PR-13 and OP-4)

Assumed: Debian/Ubuntu VM with Docker or the distribution packages of Prometheus 2.53 / Alertmanager 0.27 / Grafana; it reaches
the production host's `https://<site>/metrics` and the SMTP server of PR-11.

1. **Metrics access on the app host:** `ONHOST_METRICS_TOKEN=<long random>` in `app.env` (the owner types it; never in chat) and
   `config:cache`; check from the VM: `curl -s -H "Authorization: Bearer $(cat /etc/prometheus/onhost-metrics.token)" https://<site>/metrics | grep -c '^onhost_'` (> 30).
2. **Files:** copy `prometheus.yml` and `slo-alerts.yml` to `/etc/prometheus/`, `alertmanager.yml` to `/etc/alertmanager/`;
   replace `app:8000` with the site, `onhost.cz` in the webhook URL with the production origin and `provoz@onhost.cz` with the OP-4
   address; write the secrets yourself: `/etc/prometheus/onhost-metrics.token`, `/etc/alertmanager/oncall.token`
   (`ONHOST_ONCALL_INBOUND_SECRET`), `/etc/alertmanager/smtp.password`, each `0600`.
3. **Check before starting:** `promtool check rules /etc/prometheus/slo-alerts.yml`, `promtool check config /etc/prometheus/prometheus.yml`,
   `amtool check-config /etc/alertmanager/alertmanager.yml` — all OK.
4. **Start** Prometheus, Alertmanager, Grafana; in Prometheus *Status → Targets* the `onhost-app` target is UP; *Alerts* lists 22 alerts.
5. **Test page:** `amtool alert add OnhostTest severity=page --annotation=summary="test page" --alertmanager.url=http://localhost:9093`
   → an on-call alert appears in the staff console (*Incidenty → On-call*) and the e-mail arrives; resolve it in the console.

**Rollback:** stop the three services; nothing on the production host changes except the metrics token (remove it + `config:cache`).

**What to send back (no secrets):** the outputs of step 3, a screenshot or the text of *Status → Targets* and *Alerts* (counts),
and whether the test page reached the console and the mailbox.
