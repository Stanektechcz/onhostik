# aaPanel (webhosting execution layer)

## Phase 2: MOCK only

`App\Domains\Provisioning\Drivers\AapanelMockDriver` implements the full
`ProvisioningDriverInterface` with **zero network I/O**:

| method | mock behaviour |
|---|---|
| `create()` | idempotent (existing `external_id` short-circuits); returns `MOCK-AAP-XXXXXXXX`, generated panel credentials (returned exactly once, never persisted), doc-root/php metadata; honours `simulate_failure` |
| `suspend()` / `unsuspend()` / `terminate()` | success result with operation metadata |
| `changePackage()` | success result echoing the new resource set |
| `getUsageStats()` | deterministic `UsageStats` (≈18 % disk used of the plan limit) |
| `resetPassword()` | new random secret |
| `loginAsUser()` | fake one-time SSO URL (`null` when unprovisioned) |
| `testConnection()` | `true` |

Credential handling: the provisioning job persists only the username into the
task result; passwords are never stored or logged (the real flow will deliver
them out of band).

## The real driver (later phase — requires explicit approval)

- aaPanel HTTP API with `request_token = md5(timestamp + md5(api_key))`,
  IP-whitelisted, HTTPS only; credentials encrypted at rest on `servers`.
- Operations map 1:1 to the interface (site create, PHP version, DB + FTP
  account, SSL via Let's Encrypt, suspend = stop site, terminate = delete
  after the retention window from `provisioning.retention_days`).
- Honour `provisioning.aapanel.timeout`; wrap errors into
  `ProvisioningException` (retryable vs not); same task lifecycle as mock.
- The mock stays the default for local dev and tests forever.

## Phase 3: real-ready client

`App\Domains\Integrations\Clients\AapanelClient` implements
connectionTest/createSite/createDatabase/createFtpAccount/setPhpVersion/
configureSsl/suspendSite/reactivateSite/deleteSite/getUsage/getServerHealth.
Every write routes through dry-run unless ALL refusal gates open
(vault row active, mock off, dry-run off, `AAPANEL_ALLOW_REAL_WRITES=true`,
credentials present). Auth model for the live path:
`request_token = md5(time . md5(api_key))`. Provisioning still uses the
mock driver in this phase.
