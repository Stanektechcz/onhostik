# Backups of customer services

What a backup is, where it lives, who may do what with it and what to check when one fails. The final archive
before a deletion (`FinalArchive`, 60 days) is described in `docs/runbooks/provisioning-queue.md`; this page is about
the backups a service has while it lives.

## The rule

**A backup somebody asked for now is fresh and whole, or the operation fails and says why.** No step ever "completes"
with an archive that already existed before it started.

Why it needed saying (review 2026-09-20, all proven by a failing test first):

* ISPConfig's remote API has no "make a backup now". `backup()` rewrote the site's nightly plan, reported success, and
  the step adopted the newest archive on the panel — **last night's** — as the backup the customer had just asked for.
* aaPanel packs the site on request, but the **files only**. A WordPress backup without its database restores nothing.
* The final archive of a VM or a game server took "the newest backup in the list" as its own.
* The aaPanel database export downloaded "the newest dump" — and then deleted it; when the panel made none, that was
  an older dump, handed over as today's data and removed.
* Both file transports refused the path `.` (their guard took it for a way out of the site root), so "pack the whole
  site" and "unpack here" never worked against a real node — only against the mocks.
* A `backup` / `restore` on a **mail** service reached the web adapter with the mail domain's id. A mail domain's id
  among web sites is a stranger's site: its backup plan was rewritten, its archives listed.

## Web and managed services: the platform makes the backup (`ServiceBackups`)

| | |
| --- | --- |
| What | site files (`site-files.tar.gz`) + a dump of **every** database (`database-<name>.sql`) + `service.json` + `manifest.json` with SHA-256 of every part |
| Where | the platform backup disk (`ONHOST_PLATFORM_BACKUP_DISK`), set `service-archives/<org>/<service>-<time>-<rand>`; never on the node it protects |
| How | databases through the panel's export, files by the node transport (`archive(['.'])` + download); when the transport fails, the panel's own site backup — **only a fresh one** (`backup_on_demand` in `siteFeatures()`: aaPanel yes, ISPConfig no, and then it is not even asked) |
| Kinds | `manual` (customer, hook, assistant, Discord), `scheduled` (`BackupScheduler`), `pre-push` (before a staging push overwrites production; 7 days) |
| Identity | `ServiceIdentityCheck` runs first, as for the final archive: a binding that points at somebody else's site must never put their files into this customer's download |
| Row | `backups.remote_id` is `null`, `meta.set` names the set, `verify_status = ok`; one row per operation however often the step was retried |

Consumers of a set:

* **Download** — `GET /v1/services/{id}/backups/{backup}/download` (`backup.download`): one zip with `SHA256SUMS` and a
  `README.txt` (`FinalArchive::package`). The archive of a cancelled service (`kind = final`) is **not** served here —
  it has its own door with the fee, the waiver and the audit (`ServiceArchiveService`).
* **Restore** — `restore` with the backup's id: databases first (each dump into the database of the same name), then the
  files over the site root. *Overlay*: files added after the backup stay (the panels' own restores do the same). Nothing
  is touched until every dump has a database to go into — a dropped database fails the restore with its name
  (`restore_database_missing`). The final archive never goes back this way (`archive.restore` onto a new service).
* **Delete** — `backup.delete` with the row's id. Refused for `protected`, `final` and under a legal hold
  (`backup_protected`). Both panels offer it now: deleting a set does not depend on what the panel can do.
* **Ceiling** — `ONHOST_BACKUP_MANUAL_MAX` (5) manual backups per service at once, or the plan's `manual_backups`
  entitlement (`backup_limit_reached`, 409). Scheduled backups have the plan's `backup_generations`.
* **Retention** — `FinalArchive::prune()` (from `onhost:backups:run`) removes every expired, unprotected set whatever
  its kind; `BackupScheduler` trims generations and removes the **files** with the row. Before, only services with a
  schedule were pruned, and only their rows.
* **Off-site** — the newest set is copied part by part to `ONHOST_BACKUP_OFFSITE_DISK` (`backup.offsite`).
* **Re-hash** — every stored set, not only final archives, is re-hashed against its manifest every
  `ONHOST_ARCHIVE_VERIFY_DAYS` days; a mismatch is `service.final_archive.corrupt` and an `onhost:doctor` failure.

### Sizing — read before production

Every web backup now travels node → control plane → backup disk. A plan with `backup_frequency = 15m` means a full
site archive every quarter of an hour. Decide per plan; the honest defaults are `daily` for shared hosting and
`hourly` at most for managed plans. The node transport packs for at most 15 minutes (`SshShell` cap); a site that
cannot be packed in that time fails its backup with `Packing failed` — that is the signal to move the customer to a
VPS or to raise the cap deliberately. `tar` exiting 1 ("file changed as we read it") is **not** a failure: a live
site writes caches and sessions all day and the archive is complete.

## Game servers, VMs, databases: the panel's own backups

`BackupCapable::backup()` on Pterodactyl / Proxmox. The step writes down what the panel already holds and adopts the
archive the panel **named** in its answer (Pterodactyl `uuid`), else the newest one that **was not there before**.
When the list holds nothing new the step fails (`backup_unconfirmed`, retryable) instead of completing.

A Pterodactyl **restore** is over when the application API no longer reports the server `restoring_backup`. The power
state is `offline` for the whole restore and used to read as "finished" at the first poll.

## ISPConfig specifics (verify on staging — the source is not reachable from the build machine)

* `sites_web_domain_backup(session, primary_id, action_type)`: `primary_id` is the **backup's** id, the action is
  `backup_restore` | `backup_download` | `backup_delete`. The adapter used to send the site's id and `restore`; the
  panel would have answered `invalid_action`. Because the panel does not ask whose backup it is, the adapter first
  checks that the id stands in the site's own `sites_web_domain_backup_list` (`IspConfigWebProvider::backupAction`).
* `mail_user_backup(session, primary_id, action_type)`: `backup_restore_mail` | `backup_delete_mail`, same ownership
  check against `mail_user_backup_list`. There is no "back up this mailbox now": the feature key `mail_backup_now` is
  off, the button is hidden, `backupMailbox()` says so.
* The nightly ISPConfig archives stay on the node as before (`backup_interval`, `backup_copies` from provisioning);
  existing rows with a `remote_id` keep working through the panel (download, restore).

Staging checks: (1) manual backup of an ISPConfig site → a set with the files and the database appears, the panel's
list is untouched; (2) restore it → the site shows the old content, the database too; (3) restore a **panel** archive
from an older row → the panel accepts `backup_restore`; (4) the same for a mailbox backup; (5) aaPanel manual backup →
set with `site-files.tar.gz` made by `files?action=Zip` of the root entries.

## When a backup fails

1. `GET /v1/staff/provisioning/jobs/{id}` (or the service's „Operace“ tab) — the step error names the path that failed: `přenos: …` (node transport) and
   `záloha panelu: …` (panel backup). `backups.meta.attempts` keeps both.
2. `the agent user cannot log in` / `Packing failed` → the node shell: `onhost:nodes:check` (node prerequisites) and the agent user of the site.
3. `the panel makes no site backup on demand` → ISPConfig and the transport is down: fix the agent user; there is no
   second path on that panel, by design.
4. `restore_database_missing` → the customer recreates the database under the same name and repeats the restore.
5. A set that fails its re-hash: `service.final_archive.corrupt`; take a new backup, keep the broken set for forensics.

## What a paid add-on does to the service it was bought for (2026-09-21)

**The hole.** An add-on has no resource of its own: it is a billing row whose whole job is to change its parent. Five of the
seven add-ons on sale changed nothing at all — hourly backups, mailboxes, the CDN, the OV certificate and the anti-DDoS
profile were charged every month and delivered nothing. The two that did something (`ipv4`, `backup-plus`) wrote a backup
policy in a shape `BackupScheduler` cannot read: it asks for `schedule.frequency`, `retention.days` and
`retention.generations`, the row carried `schedule: {daily: '02:30'}` and `retention: {daily: 30, weekly: 4, monthly: 6}`,
so the scheduler found neither and fell back to the parent plan's own daily/7/7. And **no add-on could be cancelled**: an
add-on has no provider binding, so `requestAction` refused it (`service_not_provisioned`, 409) and the identity check behind
it would have refused it too — the subscription billed on for ever. Proven against the old code by
`tests/Feature/Orders/AddonDeliveryTest.php`.

**The rule** (`Domain\Services\Addons`):

* **an add-on the platform cannot deliver is not on sale.** `Addons::handled()` is the list; ordering anything else fails
  with `addon_not_delivered` instead of billing for nothing, `onhost:doctor` has a control point, and a guard test holds the
  other direction. `ssl` (a paid OV certificate is ordered at no registrar) and `anti-ddos-pro` (no L7 profile reaches any
  router) are `draft` until they are implemented — a draft product is offered nowhere, on the price list or as an add-on;
* what an add-on changes on the parent is written down with the value it replaced (`tags.addon.patch` / `.before`), so a
  cancellation gives back **exactly** what was taken — and a key somebody changed meanwhile is left alone (`tags.addon.kept`);
* the backup add-ons write the policy in the scheduler's words: `backup-hourly` → `hourly`, 30 days, 720 generations;
  `backup-plus` → `daily`, 30 days, 40 generations (30 daily + 4 weekly + 6 monthly). The number of copies is capped at
  `Addons::MAX_GENERATIONS`;
* cancelling an add-on runs its own short chain (`detachAddonStep` → the usual grace or release), not the provider one:
  there is no resource to archive or delete, and the parent keeps everything of its own.

Today's add-ons: `ipv4` (an address on the parent), `backup-plus` and `backup-hourly` (the backup policy), `mail-hosting`
(mailboxes, quota and DKIM on the parent — it was on sale and no cart line could reach it), `cdn` (the CDN feature and the
WAF level on the parent).
