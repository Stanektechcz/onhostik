# Pterodactyl

> Node limits (§5q follow-up): `nodeDetail`, `updateNode` (PATCH with the whole node record) and `nodeSystem`
> (Wings `/api/system?v=2` with the node's daemon token) let the console set memory / disk / over-allocation and
> detect the host RAM; the panel's UI is never used.

**Files:** `providers/Pterodactyl/PterodactylGameProvider.php` · **Contract test:** `tests/Contract/PterodactylContractTest.php`

Two APIs: the **application** API (`/api/application`, admin key) for users, servers, nodes and allocations; the
**client** API (`/api/client`, per-user key) for power actions, console websocket and files. Keys from
`env://PTERODACTYL_<KEY>`; options `eggs` (game key → `{nest, egg}`), `default_node`, `location_id`, `terminate_force`.

**Deleting a server (2026-09-22).** `terminate()` calls the plain `DELETE /api/application/servers/{id}`: the panel asks
Wings to remove the server's files and the database host to drop its databases, and refuses when either does not
answer (a 404 from Wings — the server is not there — counts as done). The refusal fails the step and the operation
tries again when the node is back. It used to call `/force`, which deletes the record anyway and leaves the world on
the Wings disk and the databases "dangling on the host instance" — a cancelled customer's data that nothing knows about
any more. `terminate_force: true` on the instance is the operator's switch for a node that is gone for good; the result
then carries `forced: true`. Tests: `tests/Contract/PterodactylDeletionTest.php`.

| Contract | Method | Endpoint |
| --- | --- | --- |
| InfrastructureProvider (game) | `create` | `POST /users` (one per organization, external_id `onhost-<org>`), pick a free allocation `GET /nodes/{id}/allocations?filter[assigned]=0` (the backend chooses, never the panel), `POST /servers` (egg, docker image, limits: memory, swap, disk, cpu, feature limits: databases, backups, allocations) |
| | `resize` | `PATCH /servers/{id}/build` |
| | `suspend` / `resume` | `POST /servers/{id}/suspend` / `unsuspend` |
| | `destroy` | `DELETE /servers/{id}/force` |
| PowerCapable | `power(start|stop|restart|kill)` | client `POST /servers/{uuid}/power` (`reboot` maps to `restart`) |
| ConsoleCapable | `consoleAccess` | client `GET /servers/{uuid}/websocket` → single-use `con_` token (`wings_ws`) |
| BackupCapable | `backup` / `restore` | client `POST /servers/{uuid}/backups`, `…/restore` |
| Usage | `usage()` | client `GET /servers/{uuid}/resources` (state, cpu, memory, disk, network) |

Spec keys: `nest_id`, `egg_id`, `ptero_user_id`, `allocation_id`, `ram_mb`, `disk_mb`, `cpu_pct`, `slots`,
`startup`/`environment` per egg. Installation is asynchronous: `awaitStatus` polls `GET /servers/{id}` until
`container.installed` and status is null (running).

Errors: 422 (allocation taken, egg variables) → `VALIDATION`; 409 → `CONFLICT`; 429 → `RATE_LIMIT` with
`Retry-After`; node/Wings unreachable (502/504) → `TRANSIENT`; no free allocations → `CAPACITY`.

## Game tools (`GameToolsProvider`, 2026-09-13)

| Method | Endpoint |
| --- | --- |
| `status` / `serverDetail` | client `GET /servers/{uuid}/resources`, `GET /servers/{uuid}` (limits, sftp_details, allocations, egg_features) |
| `startup` / `setVariable` / `setDockerImage` | client `GET /servers/{uuid}/startup`, `PUT …/startup/variable`, `PUT …/settings/docker-image` |
| `rename` / `reinstall` | client `POST …/settings/rename`, `POST …/settings/reinstall` (async `ptero_install`, polled on the application API) |
| `listSchedules` / `setScheduleActive` / `runSchedule` / `deleteSchedule` | client `GET/POST/DELETE …/schedules[/{id}]`, `POST …/schedules/{id}/execute` (202, empty body) |
| `listDatabases(reveal)` / `createDatabase` / `rotateDatabasePassword` / `deleteDatabase` | client `GET …/databases?include=password`, `POST …/databases`, `POST …/databases/{id}/rotate-password`, `DELETE …/databases/{id}` |
| `listSubusers` / `createSubuser` / `deleteSubuser` | client `GET/POST …/users`, `DELETE …/users/{uuid}` — presets in `GameToolsProvider::SUBUSER_PRESETS` |
| `listFiles` / `readFile` / `writeFile` / `deleteFiles` / `createDirectory` / `renameFile` | client `GET …/files/list?directory=`, `GET …/files/contents?file=` (text/plain), `POST …/files/write?file=` (raw body), `POST …/files/delete`, `POST …/files/create-folder`, `PUT …/files/rename` |
| `listAllocations` / `addAllocation` / `setPrimaryAllocation` / `removeAllocation` | client `GET/POST …/network/allocations`, `POST …/network/allocations/{id}/primary`, `DELETE …/network/allocations/{id}` |
| `deleteBackup` / `lockBackup` / `backupDownloadUrl` | client `DELETE …/backups/{uuid}`, `POST …/backups/{uuid}/lock` (toggle, read first), `GET …/backups/{uuid}/download` (signed URL) |
| `panelAccount` / `setPanelPassword` | application `GET /users/{id}`, `PATCH /users/{id}` (never a root admin) |
| `listServers` / `listEggs` / `nodeAllocations` / `createAllocations` / `clientApiStatus` | application `GET /servers`, `GET /nests[/{id}/eggs]`, `GET/POST /nodes/{id}/allocations`; client `GET /api/client/account` |

Empty 2xx bodies (204, 202) are accepted as `[]`. Node discovery (`ProviderInstanceService::discoverNodes`) imports
panel nodes as scheduler nodes (`remote_id` = panel node id); `NodePrerequisites` records `client_api`, node daemons
and the template mapping; the customer's server tools are hidden while the client key is missing or refused.

Credentials without traces: `php artisan onhost:integrations:secret <instance> application_key --check` (hidden
prompt or `--stdin`), then `client_key`. Contract test: `tests/Contract/PterodactylToolsContractTest.php`.

## Bootstrap and migrations (audit §5g, 2026-09-13)

`GamePanelBootstrap` (`onhost:game:bootstrap <instance>`, console *Zprovoznit panel*): probe → `discoverNodes` →
`syncEggs` (catalogue keys from `config/onhost.php` `game.eggs` matched by regex on nest/egg names; unmapped keys
name the community egg to import) → prerequisites → `createAllocations` of `game.default_ports` where a node has no
free allocation → plan placement. Idempotent; the report lists every step.

`GameMigrationWorkflow` (`game.migrate`) uses two more adapter methods: `serverDefinition` (application
`GET /servers/{id}`: nest, egg, image, startup, environment, limits, feature limits, owner) and `importArchive`
(client `GET …/files/upload` for the signed upload link; the source backup's signed download link is streamed to a
temporary file and posted multipart to `{upload}&directory=%2F`; then client `POST …/files/decompress` and
`…/files/delete`). Both signed links carry short-lived Wings tokens and go through plain `Http` (never the provider
call log). The transfer runs in the queued `TransferGameArchive` job (hours allowed) while the saga polls a cache
record. The panel has no application-API transfer endpoint in 1.11, which is why the archive path is used.

Cross-panel (§5h-2): when the target node belongs to another panel instance the saga resolves the target adapter
from the node's instance (`GameMigrationWorkflow::targetAdapter`), ensures the customer's account there
(`ensureUser` by the organization's billing e-mail), takes the catalogue template from the target's `options.eggs`
(the source's nest/egg ids are meaningless there), polls the install on the target, and the transfer job reads the
download link from the source adapter and the upload link from the target adapter. The binding, the service and
the game server row move to the target instance at the switch.
