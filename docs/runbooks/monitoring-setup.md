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
4. **Start** Prometheus, Alertmanager, Grafana; in Prometheus *Status → Targets* the `onhost-app` target is UP; *Alerts* lists every alert of `slo-alerts.yml` (28, including the 4 of `onhost-meta`).
5. **Test page:** `amtool alert add OnhostTest severity=page --annotation=summary="test page" --alertmanager.url=http://localhost:9093`
   → an on-call alert appears in the staff console (*Incidenty → On-call*) and the e-mail arrives; resolve it in the console.

## Dead man's switch: Watchdog and external heartbeat (A3)

Who watches the monitoring? `slo-alerts.yml` group `onhost-meta`:

| Alert | Expression | Severity | Meaning |
| --- | --- | --- | --- |
| `Watchdog` | `vector(1)` | `heartbeat` | always firing; reaches the heartbeat service every minute while Prometheus → Alertmanager works |
| `OnhostMetricsAbsent` | `absent(onhost_http_requests_total)` for 10m | `page` | `/metrics` is not scraped (token, network, app down) |
| `OnhostOutboxMetricAbsent` | `absent(onhost_outbox_pending_oldest_seconds)` for 10m | `page` | `OnhostOutboxLag` would never fire |
| `OnhostAutomationMetricAbsent` | `absent(onhost_automation_alive)` for 10m | `page` | `OnhostAutomationDead` would never fire |

`alertmanager.yml` routes `severity = heartbeat` only to the receiver `heartbeat` (webhook, `repeat_interval: 1m`, never e-mail or
the platform). Its URL is **not in the repository**: `url_file: /etc/alertmanager/heartbeat.url`.

1. Create a check at an external heartbeat service (Healthchecks.io, Better Stack, … — open question A3 of the 2026-10-15 decision:
   which service and who receives its alerts); period 1 minute, grace 5 minutes; its notification goes to the OP-4 person.
2. Write the ping URL yourself (never in chat or the repo): `install -m 0600 /dev/null /etc/alertmanager/heartbeat.url`, then put the
   URL on its single line; reload Alertmanager (`kill -HUP` or `POST /-/reload`).
3. Check: within 2 minutes the service shows the check as up; stop Alertmanager for longer than the grace period → the service alerts.

**Without the file the heartbeat is disabled:** the config still loads, the Watchdog notification fails and is logged, nothing else
changes. Rollback: delete `heartbeat.url` and pause the check at the service.

## Quarterly alert-delivery drill (N10)

The rules and the routing are checked in CI (`promtool check rules`, `amtool check-config`); what CI cannot prove is that a page
actually **reaches a person**. Once per quarter (first working week of January, April, July, October) the owner (Adrian, recipient
of A3) runs this drill on the monitoring VM only — never against production data and never by stopping the platform's relay or queue.

1. **Page path:** `amtool alert add OnhostDrill severity=page --annotation=summary="quarterly delivery drill"
   --alertmanager.url=http://localhost:9093` → within 5 minutes an on-call alert appears in the staff console (*Incidenty → On-call*)
   and the e-mail arrives; acknowledge and resolve it in the console. Note both arrival times.
2. **Ticket path:** the same with `severity=ticket` and alert name `OnhostDrillTicket` → only the e-mail arrives, no on-call alert.
3. **Dead man's switch:** stop Alertmanager for longer than the heartbeat grace period (5 minutes + margin) → Healthchecks.io alerts
   the owner; start Alertmanager again and confirm the check returns to up.
4. **Rule sanity:** in Prometheus *Alerts* the count still matches step 4 of the setup above, and `Watchdog` is firing.

**Pass:** all four steps within their time limits. **Fail:** any step missing or late → open an incident, fix the route or receiver,
repeat the failed step. **Record (no secrets):** date, arrival times per step, pass/fail, in the operations log or the release
readiness report. Rollback: none needed; expire leftover drill alerts with `amtool silence add alertname=~"OnhostDrill.*"` or let them
resolve.

**Rollback:** stop the three services; nothing on the production host changes except the metrics token (remove it + `config:cache`).

**What to send back (no secrets):** the outputs of step 3, a screenshot or the text of *Status → Targets* and *Alerts* (counts),
and whether the test page reached the console and the mailbox.
