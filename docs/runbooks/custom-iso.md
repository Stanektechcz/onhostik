# Custom ISO on a VPS (owner decision G-R5, TASK-0110)

A customer may boot an installation image of their own on a VPS/VDS — **only when the plan they ordered includes it**. This
runbook is for the operator: what has to exist on the servers, what the platform enforces, and how to clean up by hand.

## What the platform enforces

| Rule | Where |
| --- | --- |
| The plan sells it: entitlement `custom_iso: true` (and `custom_iso_max_mb`, the size of one image). Without it the feature is `{enabled: false, reason: plan}` (the panel says „Není v tarifu“) and an upload or an attach answers **403 `custom_iso_not_in_plan`**. | `CustomIsoPolicy::inPlan`, `ServiceFeatures`, `ServiceService::featureParams` |
| No per-service override. A service's entitlements are written only by its order, a plan change (`PlanChangeService` → resize) and an add-on patch (`Addons`). Staff enable it by moving the customer to a plan version that has it — there is no switch on one service. | by construction; `PlanVersioning::ADDABLE_KEYS` lets a `cloud` plan version add the two keys |
| The ways out stay open: after a plan change without the feature, `iso.detach` and `iso.delete` still work (`custom_iso_exit`). | `ServiceFeatures`, `CustomIsoPolicy::hasWayOut` |
| Upload: an ISO 9660 image (`CD001` at byte 32769), no larger than `min(custom_iso_max_mb, ONHOST_CUSTOM_ISO_SCAN_MAX_MB)` — measured on the bytes that arrived — scanned by clamd **before** it is kept. Infected: 422 `upload_infected`, file deleted, `files.infected` to security. clamd missing, unreachable or timed out: **503 `iso_scan_unavailable`**, file deleted, never kept „to scan later“. clamd stopped at a limit (`Heuristics.Limits.Exceeded`): 422 `iso_scan_incomplete`. | `CustomIsoLibrary::stage`, `ClamdIsoScanner` |
| Quota per organization: `ONHOST_CUSTOM_ISO_ORG_QUOTA_MB` in total and `ONHOST_CUSTOM_ISO_ORG_MAX_IMAGES` images. Before a byte is copied, a **staging row** reserves the upload's bytes under a lock on the organization, so uploads in flight count against the quota; at most `ONHOST_CUSTOM_ISO_ORG_MAX_INFLIGHT` (2) at once (429 `iso_upload_in_progress`). The same file twice is one image. Per person `ONHOST_CUSTOM_ISO_UPLOADS_PER_HOUR` uploads (rate limiter `custom-iso-upload`). Anything that breaks after the reservation removes the row and the files; `onhost:isos:sweep` (hourly) removes what a dead process left (staging rows and `incoming/` files older than `ONHOST_CUSTOM_ISO_STAGING_HOURS`). Another file under the same Idempotency-Key is 409 (the fingerprint hashes uploaded files). | `CustomIsoLibrary::stage/store/sweep`, `IdempotencyKey` |
| The scanner proves itself before uploads are taken: `selfTest()` must find EICAR **and** report a file nested deeper than clamd reads (`AlertExceedsMax yes`); otherwise every upload is 503 `iso_scanner_untrusted`. Cached 10 min (a failure 1 min). Check by hand: `php artisan onhost:isos:scanner-check`. clamd's "size limit exceeded" is the file's fault: 422 `iso_too_large_for_scan`, not a retryable 503. | `ClamdIsoScanner` |
| An upload or attach needs a running server (409 `service_not_active`); the attach step asks the plan, the server state and where the image is again, with the server and the image locked. A delete waits for the hypervisor's task before a copy leaves `node_copies`; a failed task fails the step and the retry deletes it again. | `CustomIsoDrive` |
| Names: the customer's file name is only a label (sanitised, ends in `.iso`). On the platform disk the file is `<organization>/<id>.iso`, on the hypervisor `onhost-ciso-<id>.iso`; the adapter refuses any other name. | `CustomIsoPolicy::displayName`, `ProxmoxComputeProvider::customIsoFilename` |
| Attach (`iso.attach`, permission `service.console`, NORMAL): copy to the node's custom storage (Proxmox checks the SHA-256), then `ide2` = the image as CD-ROM and — unless `boot_first: false` — first in the boot order. The boot order found before is stored on the image row. No reboot unless `reboot: true`. | `CustomIsoWorkflow`, `CustomIsoDrive::attach` |
| Detach (`iso.detach`, `service.manage`, NORMAL): `ide2` and the boot order exactly as found before the first image. | `CustomIsoDrive::detach` |
| Delete (`iso.delete`, `service.data.delete`, **HIGH** — fresh step-up): detached from this server first, every node copy deleted, then the platform file and the row (`state: deleted`). An image attached to another server is refused (409 `iso_attached_elsewhere`). | `CustomIsoWorkflow`, `CustomIsoDrive::delete` |
| One CD drive: an attach or detach is refused while a rescue session is open (409 `iso_rescue_active`); a rescue started with an image attached puts the image back at its end. A customer image is never listed as a rescue image, even on the rescue storage. | `CustomIsoPolicy::assertNoRescue`, `ProxmoxComputeProvider::listIsoImages` |

Events (outbox, not routed to a notification — the customer acted and got the answer): `service.iso.uploaded`
(`iso_id`, `name`, `size_bytes`), `service.iso.attached` (`iso_id`, `name`, `boot_first`), `service.iso.detached` (`iso_id`, `reason`),
`service.iso.deleted` (`iso_id`, `name`). Audit actions: `service.iso.upload` (also `denied` for a refused scan), `service.iso.attach`,
`service.iso.detach`, `service.iso.delete`. **To do:** list the four events in `docs/architecture/events-catalog.md` (the file was locked
by TASK-0112 when this landed) and add a doctor row for `onhost:isos:scanner-check` (`Doctor.php` was locked by TASK-0113).

## Server steps (operator, before a plan with `custom_iso` goes on sale)

1. **Dedicated disk for the images**, outside the web root, e.g. a volume mounted at `/srv/onhost-isos`, owned by the PHP-FPM / queue
   user (`www` on aaPanel), mode 0750. Set `ONHOST_CUSTOM_ISO_ROOT=/srv/onhost-isos`. The platform refuses to store anything when the
   root resolves inside `public/` (503 `custom_iso_storage_unsafe`). Size it for `organizations × ONHOST_CUSTOM_ISO_ORG_QUOTA_MB` you expect.
2. **clamd** reachable from the platform (`ONHOST_CLAMAV_HOST`, `ONHOST_CLAMAV_PORT`), with limits that cover the largest image you sell:
   `StreamMaxLength`, `MaxScanSize` and `MaxFileSize` ≥ `ONHOST_CUSTOM_ISO_SCAN_MAX_MB` (clamd's limits top out at about 4 GB — verify on the installed ClamAV version and keep the
   scan limit at 4096 MB or lower), and `AlertExceedsMax yes` so a file clamd could not read in full is reported, not passed.
   Fresh signatures: the doctor row for the virus scanner. Raise `ONHOST_CUSTOM_ISO_SCAN_TIMEOUT` (default 900 s) for slow disks.
3. **PHP and the web server** accept the upload: `upload_max_filesize` and `post_max_size` ≥ the largest plan size (+ a few MB),
   `max_execution_time`/`request_terminate_timeout` and nginx `client_max_body_size` / `client_body_timeout` / `proxy_read_timeout`
   long enough for upload + scan of that size. The PHP temp dir (`upload_tmp_dir`) must have room for one image per concurrent upload.
4. **Proxmox**: a storage for customers' images, separate from the rescue storage (`iso_storage`), with content type `iso`, on every
   node that runs VPS (a shared storage — NFS/CephFS — avoids one copy per node). Set the instance option `custom_iso_storage` to its
   id. The API token needs `Datastore.AllocateTemplate` on that storage (upload, delete) and `Datastore.Audit` (list). Without the
   option the feature answers `reason: node` and nothing is uploaded. Queue worker timeout for `provider-proxmox` ≥
   `ONHOST_CUSTOM_ISO_UPLOAD_TIMEOUT` (default 3600 s).
5. **Catalogue**: the owner decides which plans include it ([proposal](../proposals/custom-iso-plans.md)); then
   `php artisan onhost:catalog:revise 2026-10-custom-iso` (dry run) and `--apply`, or publish the versions in the plan editor. Add the
   price-list line by hand (features of the version), e.g. „Vlastní ISO do 4 GB“.

## Manual clean-up

- An image row in `custom_isos` with `state: ready` whose file is missing: the attach fails with `iso_file_missing`; let the customer
  delete it (`iso.delete`) and upload again.
- Leftovers of a crashed upload: files in `<root>/incoming/` older than a day (`*.part`, `*.json`) can be deleted by hand; nothing
  refers to them.
- An organization with images and no cloud server left cannot reach them through the API (they hang off a server). Delete by hand:
  every `node_copies` volume with `pvesm free <volume>` on the node, then the file under the root, then set the row to `deleted`.
  A sweep for this is not built yet (follow-up).
- Rolling back the migration drops the table only; files and node copies stay and are removed as above.
