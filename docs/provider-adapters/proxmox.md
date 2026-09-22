# Proxmox VE + Proxmox Backup Server

**Files:** `providers/Proxmox/ProxmoxConnector.php`, `providers/Proxmox/ProxmoxComputeProvider.php`,
`providers/Pbs/*` · **Contract test:** `tests/Contract/ProxmoxContractTest.php`

## Authentication and instance options

API token (`PVEAPIToken=user@realm!tokenid=secret`) from the `SecretStore` reference `env://PROXMOX_<KEY>`.
Instance options: `verify_tls`, `default_node`, `storage` (NVMe pool), `bridge`, `templates` (image key → template
vmid, `<image>_node` → the node holding it), `template_node`, `backup_storage`, `os_disk`, `pool`, `vmid_min` / `vmid_max`
(the range customer VMs are numbered in; defaults 100 / 999999999).

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
Proxmox puts the reason of a refusal into the **HTTP status line** and answers `{"data":null}`; the connector reads the
body's `message`/`errors` first and the status line otherwise (before 2026-09-22 every refusal read "server error").
HTTP 401/403 → `AUTH`; 400 with parameter verification → `VALIDATION`; 500 "already exists" → `CONFLICT`; 500
"Configuration file … does not exist" / "no such VM" → `NOT_FOUND` (Proxmox has no 404 for a guest); anything else,
including a guest locked by a running task (retry after 15 s), → `TRANSIENT`; connection errors → `TRANSIENT`.

`getActualState()` reports a VM missing only when the **whole cluster** does not list it: asked on a node that does not
hold it, Proxmox says the configuration does not exist — a VM moved by HA or by hand looks deleted from its old node.
Found elsewhere, the state is read there and carries `node`, which `ServiceIdentityCheck` compares with the binding: a
cancellation never deletes a VM on the strength of a binding that names another node.

## Reconciliation

`actual()` maps config back to spec keys; ONHOST_MANAGED fields are cores/memory/disk size/power;
PROVIDER_MANAGED are node placement after live migration and MAC addresses.

## VMID allocation

Proxmox's own `/cluster/nextid` gives the **lowest** free number, so the number of a VPS that was just purged went to the
next order: the cancelled service's binding still held it (bindings are history; instance/type/number is unique), the
clone was made, the binding failed and the order failed with the new VM left on the node — and a bound successor's
backups would have joined the predecessor's PBS group `vm/<vmid>`.

* `Domain\Provisioning\VmidReservations::hold()` gives the clone step a number **held for the operation** in
  `vmid_reservations` (unique per cluster and number): above every number the platform ever held or bound on the cluster
  (`highWater()`), asked of the adapter with `reserveVmid($atLeast)`. Two orders running at once get two numbers.
* `ProxmoxComputeProvider::reserveVmid($atLeast)` takes the lowest number at or above `$atLeast`, `vmid_min` and the
  cluster's own `next-id` floor that **no guest has** (VM, container, template) and **no backup on `backup_storage`
  carries**, and asks `/cluster/nextid?vmid=N` to confirm it free. An unreadable backup storage does not stop an order
  (the platform's own numbers are below the floor anyway). Above `vmid_max` → `CAPACITY`.
* `provision()` with a held number looks at it first: nobody there → clone; this service's finished clone (its
  description) → adopted; **a clone still being built** — its answer and task id were lost; while the first disk is copied
  Proxmox shows only a temporary config (`lock: clone`, no name, no description) — is followed like any clone, by the
  lock on the guest (async handle `pve_clone`, no attempt spent while it waits), and is done only when the finished guest
  carries this service's description. A lost answer used to make a second VM. Anything else under the number →
  `CONFLICT` with `ComputeProvider::VMID_TAKEN`: the step burns the number and holds the next one (three in a row → retry
  in a minute). A clone refused with "already exists" is the same case.
* Doctor: "guest numbers are the platform's own" counts numbers taken from under an order in the last 30 days — VMs
  made by hand inside the platform's range. Give those their own range, or raise `vmid_min`.

Tests: `tests/Feature/Provisioning/VmidAllocationTest.php`.
