# Provider outage

Symptoms: `integration.down` in the admin inbox, provider health `down/degraded` on `GET /v1/staff/integrations`,
operations piling up in WAITING/FAILED, circuit breaker open (`provider_circuit_open` errors).

## First 10 minutes

1. `GET /v1/staff/integrations` — which instance, since when, last error (vendor payloads are redacted).
2. `POST /v1/staff/integrations/probe` — re-run the health probe once; do not loop it.
3. Decide whether customers are affected: `GET /v1/staff/provisioning/jobs?state=FAILED` and
   `?state=WAITING`. If yes, open an incident ([incident-response.md](incident-response.md)).
4. If the provider is flapping and mutations would make it worse: `POST /v1/staff/provisioning/freeze`
   (reason mandatory, step-up). Reads, billing and support continue.

## Per provider

* **Proxmox VE / PBS** — check cluster quorum and the API token permissions before retrying. Operations wait on
  UPIDs; a node reboot leaves UPIDs unknown → operations fail with `TRANSIENT` and retry. Console tokens expire
  in 2 minutes; nothing to clean up.
* **ISPConfig** — the remote API uses a session per call; `jobqueue` entries may stay queued when the server
  daemon is stopped. Restart `ispconfig_server` on the node, then retry operations. Never re-run site creation
  by hand: adapters treat "already exists" as success.
* **aaPanel** — API access is IP allow-listed; a changed egress IP shows as `AUTH`. Update the allow-list in
  aaPanel, then `probe`.
* **Pterodactyl** — application API for nodes/servers, client API for power/console. A Wings node offline shows
  as `TRANSIENT` on power actions; game servers keep running. Allocation exhaustion → `capacity.unavailable`.
* **PowerDNS** — zone changes are staged; a failed commit rolls back the version. Check `pdns_control` and the
  API key; secondaries keep serving from the last NOTIFY.
* **WEDOS WAPI** — see [domains-registrar.md](domains-registrar.md); the invalid-request breaker opens after
  repeated 2xxx/4xxx codes to protect the account.
* **RKE2 apps** — `kubectl get pods -n <service namespace>`; deploy jobs are BuildKit rootless jobs with a
  timeout; a failed deploy keeps the previous revision live.

## Is it the panel, or is it us?

Every panel quota (`provider_instances.rate_limits`, per minute or per hour) is split three ways: ordinary work, a
reserve for critical calls (suspend, renewals) and a top slice only health reads may use
(`ONHOST_PROVIDER_DIAGNOSTIC_RESERVE`, 5 % of the window, never fewer than 3 calls, none for quotas under 20 calls;
Brain card H323). The critical reserve is a share of the work, so the slice never eats it. The total never
passes the vendor's limit. A flood of our own operations therefore cannot blind the probe: it still measures the panel.
When even the slice is gone the probe is *not sent* — the health record keeps its last verdict with
`last_error = probe not sent: local quota exhausted`, no `integration.down` is published and a maintenance lock is
neither lifted nor declared overdue. Seeing that text means our quota is the bottleneck, not the panel: look at
`budget_used_pct` on the integration and at the operations queue before touching the vendor.

## After recovery

1. `POST /v1/staff/provisioning/thaw`.
2. Retry failed operations in order of age: `POST /v1/staff/provisioning/jobs/{id}/retry` (bulk via the admin
   console). Cancel only duplicates, with a reason.
3. Run `onhost:provisioning:reconcile` for the affected instance; resolve drifts
   (`/v1/staff/resource-mappings/{id}/resolve`) with a note.
4. Resolve the incident and write the post-mortem; attach the provider's own RCA if there is one.
