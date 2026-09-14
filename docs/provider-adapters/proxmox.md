# Proxmox VE + Proxmox Backup Server

**Files:** `providers/Proxmox/ProxmoxConnector.php`, `providers/Proxmox/ProxmoxComputeProvider.php`,
`providers/Pbs/*` · **Contract test:** `tests/Contract/ProxmoxContractTest.php`

## Authentication and instance options

API token (`PVEAPIToken=user@realm!tokenid=secret`) from the `SecretStore` reference `env://PROXMOX_<KEY>`.
Instance options: `verify_tls`, `default_node`, `storage` (NVMe pool), `bridge`, `template_map`
(image key → template vmid), `pbs_datastore`.

## Capabilities

| Contract | Method | Proxmox call | Async |
| --- | --- | --- | --- |
| InfrastructureProvider | `create(spec)` | `POST /nodes/{node}/qemu/{tpl}/clone` + `PUT …/config` (cores, memory, scsi resize, cloud-init user/ssh keys, ipconfig0) | UPID |
| | `resize` | `PUT …/config` + `PUT …/resize` | UPID |
| | `destroy` | `POST …/status/stop` → `DELETE …/qemu/{vmid}?purge=1` | UPID |
| | `describe` / `actual()` | `GET …/config`, `GET …/status/current` | — |
| PowerCapable | `power(start|stop|reboot|shutdown)` | `POST …/status/{action}` | UPID |
| ConsoleCapable | `consoleAccess` | `POST …/vncproxy` → single-use `con_` token in cache (see runbook console-relay) | — |
| BackupCapable | `backup` / `restore` / `snapshot` / `rollback` | `POST /nodes/{node}/vzdump` (PBS storage), `POST …/qemu` restore, `POST …/snapshot`, `POST …/snapshot/{name}/rollback` | UPID |
| Usage | `usage()` | `GET …/rrddata?timeframe=hour` → cpu, mem, netin/netout, diskread/write | — |
| ComputeProvider | `migrate(ref, targetNode, online)` | `POST /nodes/{node}/qemu/{vmid}/migrate` (`target`, `online` when the VM runs, `with-local-disks=1`) — the handle polls the *source* node's task; the binding's `remote_node` and the service node move in `VpsMigrationWorkflow` | UPID |

Spec keys consumed: `vcpu`, `ram_mb`, `nvme_gb`, `cpu_limit`, `hostname`, `image`, `ssh_keys`, `ipv4`, `ipv6`,
`gateway`, `tags`. VM tags carry `onhost`, `svc:<ulid>`, `org:<ulid>` for reconciliation.

## Async and errors

`awaitStatus(UPID)` reads `GET /nodes/{node}/tasks/{upid}/status` (`stopped` + `exitstatus OK` → succeeded).
HTTP 401/403 → `AUTH`; 400 with parameter verification → `VALIDATION`; 500 "already exists" → `CONFLICT`
(treated as existing); 595/596 or connection errors → `TRANSIENT`; storage full → `CAPACITY`.

## Reconciliation

`actual()` maps config back to spec keys; ONHOST_MANAGED fields are cores/memory/disk size/power;
PROVIDER_MANAGED are node placement after live migration and MAC addresses.
