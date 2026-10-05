# Proposal: which plans include a custom ISO (owner decision G-R5, TASK-0110)

**Status:** proposal — prepared, **not applied**. The owner decides; nothing in the catalogue changes until the revision is applied.

The code is in place: a plan that sells `custom_iso: true` (with `custom_iso_max_mb`) lets its servers upload, attach, detach and delete
their own installation images ([runbook](../runbooks/custom-iso.md)). No plan in the catalogue sells it today, so today nobody has it.

## Proposed plans

| Product / plan | Today | Proposed | Why |
| --- | --- | --- | --- |
| `vps/compute-2` | — | **no** | the entry plan (80 GB disk, 2 vCPU): reinstall from the golden templates covers it; keeps a reason to upgrade |
| `vps/compute-4` | — | `custom_iso: true`, `custom_iso_max_mb: 4096` | sysadmin plans: own distributions, appliances, BSD |
| `vps/compute-8` | — | `custom_iso: true`, `custom_iso_max_mb: 4096` | |
| `vps/compute-16` | — | `custom_iso: true`, `custom_iso_max_mb: 4096` | |
| `vds/vds-4` | — | `custom_iso: true`, `custom_iso_max_mb: 4096` | dedicated cores: own systems and appliances |
| `vds/vds-8` | — | `custom_iso: true`, `custom_iso_max_mb: 4096` | |
| `vds/vds-16` | — | `custom_iso: true`, `custom_iso_max_mb: 4096` | |

**Default size limit:** 4096 MB per image (`ONHOST_CUSTOM_ISO_DEFAULT_MAX_MB`, used when a plan sells the switch without a size), and
never more than clamd scans in full (`ONHOST_CUSTOM_ISO_SCAN_MAX_MB`, default 4096). **Why not 8 GB for VDS:** Windows Server images
are 5–6 GB, but clamd's size limits top out at about 4 GB (to be verified on the installed ClamAV version) — a plan selling 8 GB would promise what the scan limit cuts to 4 GB (a broken promise in
the sense of `PlanPromises`). Larger images need a scanner that reads them in full (a second engine or a scan per file inside the
image — not built); only then may a plan sell more, and the revision's number can be raised before it is applied.

**Quota per organization:** 20 GB and 5 images (`ONHOST_CUSTOM_ISO_ORG_QUOTA_MB=20480`, `ONHOST_CUSTOM_ISO_ORG_MAX_IMAGES=5`).

**Price:** none proposed — the feature is part of the plan. An add-on that grants it per server (an `Addons` patch of `custom_iso`) is
possible later; it is not built.

## How it is applied (after the owner's yes)

1. Server steps of the [runbook](../runbooks/custom-iso.md) (disk, clamd, PHP/nginx limits, Proxmox storage + `custom_iso_storage`).
2. `php artisan onhost:catalog:revise 2026-10-custom-iso` — dry run, lists every plan and `custom_iso null → true; custom_iso_max_mb null → …`.
3. `php artisan onhost:catalog:revise 2026-10-custom-iso --apply` — new plan versions with the same prices; the revision is a
   **proposal** (`CatalogRevisions::REVISIONS['2026-10-custom-iso']['proposal']`), so `onhost:catalog:revise` without an id and the doctor
   never apply or ask for it.
4. Price-list wording (features of each new version, plan editor), e.g. „Vlastní ISO do 4 GB“ / „Custom ISO up to 4 GB“.

Customers on the versions they already hold keep them and do not get the feature until they change plan (a version is never edited).
