# Monitoring

Mock-only in Phase 3 — no network checks are performed.

## Data model

- `monitors` — one per service (`Monitor`), provider `internal_mock`,
  status up/down/unknown/paused, uptime %, last check, optional SSL expiry.
- `monitor_checks` — individual check results.
- `monitor_incidents` — outages with severity + resolved_at.

## Behaviour

- A monitor is created automatically after successful provisioning
  (`ServiceActivationHooks`, audit `monitoring.monitor_created`).
- `InternalMockMonitoringProvider` returns deterministic "up" checks.
- `UptimeKumaProvider` is a reserved placeholder — every write refuses
  until the real Kuma API client lands.

## Visibility

- Customer: service detail shows monitor status, uptime, last check, SSL
  placeholder; dashboard shows the latest incident.
- Admin: `/admin/monitoring` lists all monitors, down-count and incidents.

## Later (real)

Implement `UptimeKumaProvider` against the Kuma API, store its credentials
in the `uptime_kuma` vault row, schedule periodic `checkMonitor()` runs via
`php artisan schedule:work`, and map Kuma webhooks to `monitor_incidents`.
