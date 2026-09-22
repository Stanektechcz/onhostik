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

## What a destructive action replaces is kept first (2026-09-21)

**The hole.** The owner's rule — nothing is deleted, cleared or otherwise disturbed before it has been fully backed up —
was held only by the cancellation chain. Three other actions overwrite what is there and kept no copy of it:

* `restore` writes a backup over the live service (files over the site root, every dump into its database),
* `rollback_snapshot` throws away everything the server did since the snapshot,
* `reinstall` (game) runs the egg's install script again and rewrites the server's files.

The panel simply told the customer to take a backup first. Whoever restored the wrong backup, or rolled back a day too
far, had no way back — and that is the single most common way a hosting customer loses data.

**The rule.** Each of those three chains now begins with `safetyCopyStep`:

* a server (family `cloud`/`data`) gets a provider **snapshot** — instant, and the rollback stops the machine anyway;
* everything else gets the same archive a cancellation takes (`ServiceBackups::take`, `fresh_only`): the files and every
  database, fresh, or the step fails;
* the copy is `protected` and kept for `DeletionPolicy::retentionDays()` (60 days), so no prune touches it while it matters;
* **if the copy cannot be made, nothing is overwritten** — the operation fails with the reason and the service is untouched;
* one copy per operation: the step is re-entered while the provider task runs (`afterAsyncSuccess`) and never starts a second.

The copies are `pre_restore`, `pre_rollback` and `pre_reinstall` in `backups.kind`, and a guard test holds the order of the
steps, so a new destructive action has to pass it.

Tests: `tests/Feature/Provisioning/SafetyCopyTest.php`, `tests/Feature/Services/WebBackupArchiveTest.php`.

## Before a destructive action: the preview and its fingerprint (2026-09-21)

Brain card H414 asks a confirmation to show the concrete service, the data, the dependencies and the way back — and its
acceptance scenario is the security of it: *changing the target after the preview requires a new confirmation*.

`GET /v1/services/{id}/actions/{action}/preview` (read-only, no step-up, the caller's own token scope) answers with:

* **service** — by the name the customer gave it;
* **what** — the concrete things that would go: the sentences of the action plus the databases and mailboxes the panel
  really lists right now, not "your data";
* **depends** — the add-ons that would be cancelled with it, the staging copy, the DNS zone that would be left pointing
  nowhere, the subscription that stops;
* **recovery** — named, because it is real: the final archive with its retention for a cancellation, the safety copy for
  a restore, a rollback or a reinstall (and, for deleting a backup, the plain truth that there is no way back);
* **fingerprint** — a digest of everything the action would touch.

Send that fingerprint back as `confirm` with `POST /v1/services/{id}/actions` and the platform checks it still describes
what would happen. A backup that is no longer the one that was previewed, a database added in the meantime, a service
that changed state — any of these answers **409 `target_changed`** and nothing is destroyed. The field is optional, so
no existing client breaks; every surface that offers a destructive action should send it.

Tests: `tests/Feature/Services/DestructivePreviewTest.php`.

## A missed slot is not silent (2026-09-21)

The scheduler asks for one `backup` operation per slot. When it cannot have one — most often because the previous
backup of that service is still running, and the service lock refuses a second — the slot used to be counted as
"skipped" and nothing else. An hourly plan on a site whose backup takes longer than an hour therefore missed **every**
slot without a word, and the customer would find out on the day they needed it. Since the hourly backup add-on really
works now, that is a plan somebody can buy.

The overlap itself was never possible: the per-service operation lock is what refuses the second run. What was missing
was the record and the alarm (H434, H435, H446):

* every slot is written down on the service (`tags.backup_schedule`): the slot that ran and when, or how many slots in
  a row have been missed, the last reason and the frequency — `BackupScheduler::health($service)` reads it;
* one miss per slot, however many times the tick looks at it inside the same window;
* the **third** missed slot in a row publishes `service.backup.schedule.stalled` (customer and operator), and after
  that only rarely, so a long outage is one conversation and not a flood;
* a slot that runs clears the count, so the alarm means "right now", not "once upon a time";
* `onhost:doctor` lists the services that are behind ("backup schedules keeping up").

Tests: `tests/Feature/Services/BackupScheduleMissesTest.php`.

## A schedule that keeps failing stops itself (2026-09-22)

The miss record (above) covers a slot the scheduler could not **start**. A backup that starts and then **fails** was
a different story and nobody was watching it:

* `due()` treated only a `running` or `completed` backup as "this slot has run", so a failed one left the slot due —
  and the scheduler started the whole packing run again at **every tick** until the window passed. On an hourly plan
  with a broken node that is the site packed over and over, all day.
* Nothing counted the failures, so a schedule that could never succeed went on trying for ever.

Now (H447): a failed attempt counts as the slot having been tried — the operation runner has already retried it — and
`BackupScheduler::noteOutcome()` reads what became of the last scheduled backup. Five failures in a row and the
schedule **stops itself**: `tags.backup_schedule.paused_at` is set, `service.backup.schedule.paused` is published once
to the customer and the operator, and no tick will start it again.

The resume is deliberately a person's: setting the backup schedule (`PUT /v1/services/{id}/backups/schedule`) clears
the pause and the failure count. Nothing is deleted while a schedule is paused and every backup already made stays
where it is — pruning and off-site copying are skipped along with it, which keeps more than the retention asks for,
never less. `onhost:doctor` lists them under "no backup schedule is waiting for a person".

Tests: `tests/Feature/Services/BackupScheduleMissesTest.php`.

## An import keeps a copy of what it overwrites (2026-09-22)

`database.import` writes a SQL dump **over a live database**. It was the one destructive action still without a copy
of what it replaces: the safety copies added earlier cover `restore`, `rollback_snapshot` and the game `reinstall`,
and the import was missed. MySQL applies a dump statement by statement, so a file that breaks half way leaves the
database half old and half new while the site goes on serving from it (H467).

The chain is now **room → copy → import**:

* **Room** (`DatabaseImport`, H456). The size of a gzipped dump is read from the four bytes gzip writes at the end of
  the file, not from the compressed size — a 90 kB file can be 1.2 MB of SQL. What it needs is twice that (the rows
  written again, the indexes built beside them, and what the engine needs while it loads) and it is compared with
  what is left of the plan's disk. A panel that does not measure disk is not guessed about: the check says so and
  lets the import through, with the node's own guard behind it.
* **Copy.** `safetyCopyStep('pre_import', onlyTargetDatabase: true)` exports **that one database** — not the files,
  which nothing is touching — as a protected set kept for the deletion-policy retention. If the copy fails, nothing
  is imported.
* **Import**, then the record: `tags.db_import` carries the target database, the phase reached and the copy's id.

When the import does not finish, what happens next depends on **the panel's own error code**, not on the operation's
`retryable` flag — once the retries are spent the operation says it will not try again, which is true of a timeout as
well, and a timeout is exactly the case that may still be running on the node:

* a code that is **not** retryable (the panel read the file and refused it) → the copy goes back in. If nothing had
  been applied this writes the same rows again and is harmless; if part of it had, this is the only way back.
* a retryable code (timeout, the panel stopped answering) → nothing is touched, and the notification says the copy
  is there to be restored by hand once somebody has looked at the node.

Either way `service.database.import.failed` reaches the customer and the operator, and says which of the two it was.

Tests: `tests/Feature/Services/DatabaseImportSafetyTest.php`.

## A backup nobody has ever restored is not a backup (2026-09-22)

`backup-7` and `backup-30` are sold with `restore_test: monthly`. The price list says "Test obnovy měsíčně", the plan
writes it into `backup_policies.restore_test` — and **no code ever tested a restore**. The only other mention of it in
the platform is a loyalty mission asking the customer to try one themselves (H458).

`onhost:services:restore-test` (nightly at 04:20, cadence per service) asks for a `restore.test` operation on every
service that is owed one. The test:

* takes the newest finished set of the service that holds at least one database dump;
* for each dump **makes a database of its own** (`<prefix>_rt<random>`) — a test may not answer the question by
  overwriting the live database;
* imports the dump into it, **exports it straight back**, and compares the tables that come back with the tables the
  dump carried. That round trip uses only what both panels already do, needs no database connection of ours, and
  proves the thing that matters: the archive on the backup disk really becomes a database again;
* **removes the test database whatever happens**, so neither the customer's disk nor their database count keeps it;
* writes the result to `tags.restore_test` (`RestoreTest::health($service)`) and publishes
  `service.restore_test.failed` when the archive did not come back whole. A passing test is read in the panel — a
  monthly "it worked" mail would be noise.

Worth knowing: a part of a set is named `database-<slug>.sql` whatever it holds, and aaPanel hands out `.sql.gz`, so
`RestoreTest::tablesIn()` decides how to read it from the first two bytes and not from the extension.

`onhost:doctor` lists services sold a test that have none that passed, under "every promised restore test has been
made".

Tests: `tests/Feature/Services/RestoreTestTest.php`.
