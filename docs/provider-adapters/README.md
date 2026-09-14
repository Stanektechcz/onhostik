# Provider adapters

Adapters live in `providers/<Vendor>/` and implement capability contracts from `Onhost\Providers\Contracts`
(`InfrastructureProvider`, `PowerCapable`, `ConsoleCapable`, `BackupCapable`, `RegistrarProvider`,
`ZoneDnsProvider`, `AppsProvider`, `AiProvider`, …). They are registered centrally in
`PlatformServiceProvider::ADAPTERS` and resolved per `ProviderInstance` by the `ProviderRegistry`.

Shared rules (blueprint §30–§31):

* **Never business identity.** Adapters receive/return provider references (`ProviderRef`) stored in
  `provider_bindings`; customer, plan and price never reach the vendor.
* **Idempotent create.** Remote names are deterministic (`onhost-<service ulid>`); "already exists" returns
  `ProviderResult::alreadyExisted()`.
* **Async handles.** Long operations return `ProviderResult::async(AsyncHandle)`; the saga polls
  `awaitStatus()` → `AsyncStatus` (pending | running | succeeded | failed).
* **Errors are taxonomy, not vendor codes.** `ProviderException` carries `errorCode` (`AUTH`, `VALIDATION`,
  `NOT_FOUND`, `CONFLICT`, `CAPACITY`, `RATE_LIMIT`, `CIRCUIT_OPEN`, `TRANSIENT`, `UNKNOWN`), `vendorCode`,
  `retryAfterSeconds`; payloads are redacted before they reach logs or audit.
* **Every call is logged** in `provider_calls` (method, path, status, duration, error class) with the operation
  and correlation id; secrets never.
* **Rate limits and breakers** are per instance (`ProviderHttpClient`: token buckets, circuit breaker with
  half-open probes); WAPI has its own gateway.
* **Contract tests** (`tests/Contract/*`) pin request shapes with HTTP fakes; live credentials are never used in CI.

| Adapter | Executor for | Doc |
| --- | --- | --- |
| `Proxmox\ProxmoxComputeProvider`, `Pbs\PbsBackupProvider` | VPS / VDS / managed VPS, backups & snapshots | [proxmox.md](proxmox.md) |
| `IspConfig\IspConfigProvider` | shared web hosting, mail domains | [ispconfig.md](ispconfig.md) |
| `AaPanel\AaPanelProvider` | managed web hosting | [aapanel.md](aapanel.md) |
| `Pterodactyl\PterodactylGameProvider` | game servers | [pterodactyl.md](pterodactyl.md) |
| `PowerDns\PowerDnsProvider` | authoritative DNS (canonical) | [powerdns.md](powerdns.md) |
| `Wedos\WedosRegistrarProvider`, `Wedos\WedosZoneDnsProvider` | registrar, fallback DNS | [wedos.md](wedos.md) |
| `Kubernetes\KubernetesAppsProvider` | apps / dev hosting on RKE2 | [kubernetes.md](kubernetes.md) |
| `Ai\OpenAiCompatibleProvider`, `Ai\AnthropicProvider` | support assistant | [ai.md](ai.md) |

Operational notes per provider are in `docs/runbooks/provider-outage.md`; the vendor API references the
prototype was built against are in `docs/development/docs-provider-apis.md`.
