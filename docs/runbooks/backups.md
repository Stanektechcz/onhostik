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
* The nightly ISPConfig archives stay on the node as before; a **site** gets `backup_interval`/`backup_copies` from
  provisioning (`backup_generations`). A **mailbox** got neither until TASK-0024 — the panel kept its own form default —
  and gets them now only under the rule `mail.backup_retention` (see "Mailbox backups follow the plan" below).
  Existing rows with a `remote_id` keep working through the panel (download, restore).

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

## An expired VPS archive is gone from the backup server too (2026-09-22)

The final archive of a VPS is a **protected** vzdump backup on the backup server (`FinalArchive::snapshot()`); the set
on the platform disk holds only its metadata. Protection is what keeps it for the retention — no prune job of the
backup server may take a protected backup. It also meant nothing ever took it: when the retention ran out,
`FinalArchive::prune()` deleted the metadata set and marked the archive `expired`, and the whole disk image of the
cancelled customer's server stayed on the backup server for good (H488).

Now `prune()` removes the provider's copy first, through `Providers\Contracts\ExpiringBackups::expireBackup()`:

* Proxmox: the storage is the part of the volid before the colon; any online node reaches a shared backup storage
  (the VM, and often its node, is long gone); the volume is **unprotected, then deleted**; a volume that is already
  gone counts as removed.
* Until the provider's copy is gone the archive is **not** expired: it stays `completed`, keeps its metadata, and
  `meta.expiry_blocked` says why. The next run tries again; `onhost:doctor` lists them under "expired archives are gone
  from the provider too".
* A legal hold still stops everything, as before.

The number of a purged VPS is not given again (since 2026-09-22): `VmidReservations` keeps every new VM above every
number the platform ever held or bound on the cluster and skips numbers the backup storage holds backups of, so no
successor's backups land in the PBS group `vm/<vmid>` of a predecessor (docs/provider-adapters/proxmox.md).

Tests: `tests/Feature/Services/ArchiveExpiryTest.php`.

## Servers and managed databases: scheduled backups behind a switch (2026-09-25, TASK-0019)

**The hole.** The managed databases `db-s` / `db-m` (family `data`, one KVM VM on Proxmox) are sold with 14 / 30 days of
backups, and nothing ever took one: `BackupScheduler::tick()` looked at `web`, `managed` and `mail` only, and the
features of a server had no `backup_schedule` to read. The same held for a VPS whose customer bought `backup-plus` or
`backup-hourly`: the add-on wrote a policy (above) that nothing scheduled for family `cloud`. The only backup a server
ever got was the one somebody asked for by hand.

**The rule — off until the owner switches it on.** Starting backups on existing services is the owner's decision, so
the whole thing sits behind the automation rule **`backups.compute`** (`AutomationLedger::RULES`, `default_off`, the same
mechanism as `capacity.auto_order`). While it is off nothing changes for anybody: no server is looked at, no record is
written on it, and its feature list is the same as before.

1. **Look first:** `php artisan onhost:backups:compute-plan` lists, read-only, every server the rule would start
   backing up (and, on its last line, what visiting every web service changes — below) — service, family, product/plan, frequency, days, generations, and the Proxmox instance's
   `backup_storage` (`MISSING` means the backups would have nowhere to go: set the instance option first). It writes
   nothing and asks no provider anything.
2. **Switch on** in the staff console (Automations → "Zálohy serverů a databází podle plánu", a step-up), which lands in
   the settings `automation.enabled`. Switching it off again puts everything back as it was; backups already made stay.
   Owner decision (ADR-0007 §1): switch it on once `compute-plan` lists no `MISSING` backup storage.

**What it does when on** (every 15 minutes inside `onhost:backups:run`):

* who: `data` and `cloud` services that are ACTIVE/DEGRADED, have a provider instance **and** a binding (only what the
  platform provisioned), after the web services of the same tick;
* what is sold: a managed database — `backup_days` from its plan (`backup_frequency` or daily, `backup_generations` or
  one per day); a VPS — only while an **active** backup add-on belongs to it and its policy row exists (a policy left
  behind by a cancelled add-on takes no more backups); with two active add-ons the most generous of them in each
  dimension. The customer may set the schedule within that ceiling as on the web
  (`PUT /v1/services/{id}/backups/schedule`) — the `backup_schedule` feature appears for these servers only while the
  rule is on. A stored policy is always **capped** to the ceiling sold now (frequency, days, generations), so a
  downgrade or a cancelled add-on does not keep an old hourly/long policy in force;
* how: one ordinary `backup` operation per slot (`backup:auto:{service}:{slot}`, `kind = scheduled`,
  `retention_days` = the plan's days): a vzdump to the instance's `backup_storage`. The volume's notes carry
  `onhost backup:<backup row id>` (every server backup through the `backup` action, manual ones too); a caller's label
  keeps only plain characters, so it cannot forge another row's marker. Where the storage reports notes, the step adopts
  **only** the new volume with this row's marker — an operator's own vzdump of the same guest that finished meanwhile
  is never taken for ours (no marked volume → `backup_unconfirmed`, retried). A storage that reports no notes at all
  falls back to "the newest volume that was not there before", as before;
* retention: an expired or surplus **scheduled** backup is removed through `RetainedBackups::deleteRetainedBackup()` —
  never `expireBackup()`, which unprotects first and stays the final archive's alone. The scheduler asks only for a
  volume whose id is exactly `<backup_storage>:backup/vzdump-qemu-<vmid>-…` or `<backup_storage>:backup/vm/<vmid>/…` of
  the service's own VMID; the adapter then **reads** the volume and refuses it unless it is unprotected, of that guest
  and carries this row's marker — nothing but the read is sent then. A volume an operator protected in the Proxmox UI
  is kept. Never touched either: `kind = final`, `protected` rows (safety copies), manual backups at the hypervisor,
  anything under a legal hold. A row whose volume cannot go stays `completed` with `meta.delete_blocked` saying why —
  written once per reason, not at every tick.

**Every service, every tick — OWNER DECISION still open** (not one of the 25 questions of ADR-0007; it reaches
`development` with the stack pull request of TASK-0017 … TASK-0027). `onhost:backups:run --limit=100` used to look at the first
100 services by id and never at the rest: from the 101st web hosting on, nobody got a scheduled backup. `--limit` is
now the size of one chunk; the tick walks all eligible services by id (keyset), web/managed/mail first, then the
servers under the rule. This is the sold behaviour, but for every web/managed/mail service beyond the old window it is
a change on the day of deploy: new scheduled backups, the prune of their expired backups and off-site copies start.
There is deliberately no switch for it; `onhost:backups:compute-plan` ends with one read-only line for the decision:

    web/managed/mail: <eligible> eligible · <n> beyond the old first-100 window (newly visited by every tick) · <m> of them with a backup schedule

The `final` guard applies to web services too: before, an **unprotected** final archive past its date on a service
the scheduler looked at could be deleted by the generation/retention prune instead of by `FinalArchive::prune()`.

**Still NOT built — do not promise it:**

* **PITR.** There is no WAL archiving and no restore to a point in time. Decided (ADR-0007 §2): `pitr_days` leaves the
  price list in a new plan version (revision `2026-09-honest-promises`, TASK-0022, applied by
  `onhost:catalog:revise --apply`); a new managed database instance never claims `pitr`, existing contracts keep theirs.
* **Mail `backup_days`** is not scheduled by `BackupScheduler` (mail keeps no `backup_schedule`). Decided (ADR-0007 §3):
  the panel keeps the retention itself, set from `backup_days` behind the rule `mail.backup_retention` (TASK-0024, below).
* **Off-site copies of server backups.** The add-ons sell `offsite`; a vzdump volume is not copied anywhere else.
* **VDS plans** (`backup: daily 7d`) and the configurator's `backup` option write a string entitlement no schedule
  reads; not covered by this rule.

Tests: `tests/Feature/Services/ComputeBackupScheduleTest.php`.

### Going live with server backups (operator steps)

1. `php artisan onhost:backups:compute-plan` on production (read-only): note every service with `backup storage
   MISSING` and the web-window line.
2. For each Proxmox instance behind a MISSING row set the instance option **`backup_storage`** (the storage ID of the
   PBS/vzdump storage in Proxmox — the code reads `backup_storage`; older docs said `pbs_datastore`, which nothing
   reads) in Nastavení systému → Integrace providerů (`instance.upsert`, HIGH, step-up). Probe the instance; its
   `backup_storage` prerequisite must be OK.
3. Re-run `compute-plan` until it shows no MISSING. On staging, first back a test VM up through the platform and check
   that the volume's notes carry `onhost backup:<id>` (the notes/protected read-back is ASSUMED from the PVE API docs,
   not yet seen on a real cluster).
4. Switch **`backups.compute`** on (Automations → "Zálohy serverů a databází podle plánu").
5. Watch `onhost:doctor` for 24 h: the `backups:` rows below, stalled/paused schedules and the server coverage row.

## Doctor rows for backups (TASK-0024)

`onhost:doctor` (area `lifecycle`; logic in `Onhost\Domain\Services\Web\BackupOperationsCheck`):

| Row | OK when | Blocking |
| --- | --- | --- |
| backup schedules keeping up / no backup schedule is waiting for a person | no service of **any** scheduled family (`web`, `managed`, `mail`, `cloud`, `data`) has 3+ missed slots / a paused schedule | no |
| backups: servers and databases sold backups get them | `backups.compute` is on, or no server/database is sold backups; otherwise names how many get none | no |
| backups: every Proxmox instance carrying sold backups has a backup_storage | every instance of a server sold backups names one; lists the others | **yes, once `backups.compute` is on** |
| backups: expired server backups are gone from the backup storage | no scheduled server backup carries `meta.delete_blocked` (first five ids with the reason) | no |
| backups: no orphaned backup volume | no row carries `meta.orphan_volumes` (see below) | no |
| backups: the backup tick ran within 30 min and inside its budget | the last `backups.run` is younger than 30 min, took at most `TICK_BUDGET_SECONDS` (720 s, 80 % of the cadence) and had no errors; also warns when `backups.run` is switched off | no |
| backups: every plan is backed up as often and as long as sold | `backups.as_sold` is on, or no web/managed service sells a sub-daily frequency | no |
| every server sold backups has one from the last N days | only while `backups.compute` is on: every server sold backups older than `coverage_days` has a completed backup in that window | no |
| mail backups: mailboxes keep the backups the plan sells | no live mail service sells `backup_days`, or `mail.backup_retention` is on and every one is at its plan (`tags.mail_backup`); otherwise names how many are behind, how many hold a downgrade, how many had a mailbox the panel refused | no |

`onhost:backups:run` is `withoutOverlapping`: a tick that runs longer than fifteen minutes makes the next one skip in
silence and a 15m plan loses slots nobody counts. The tick now records its `seconds` in the automation ledger; the
doctor row above is the only place that shows it. Shortening the tick (off-site streaming is synchronous inside it) is a
separate performance task.

## A server backup that is retried (TASK-0024)

When the POST /vzdump started the dump but its answer was lost (a 5xx from a proxy, a timeout — retryable), the retry
ran the step from the top: it listed the storage as "before" (the first volume already in it) and dumped a second
time. The first volume carried the row's marker but was never the row's `remote_id`, so no retention ever removed it.

Now a retry of the `backup` step on a `RetainedBackups` adapter (Proxmox) first lists the storage for a volume that
carries **this row's** marker (`onhost backup:<id>`, derived from the row, never from the request) and adopts it without
a new vzdump; only when none is there does it dump again. When several volumes carry the marker the newest is the row's
and the others are written to **`meta.orphan_volumes`** — report only. Nothing deletes them automatically: look at the
hypervisor, and remove a volume by hand only when it is provably the platform's. A retry after the task finished (a
re-poll) never dumped twice and is unchanged. There is no "legacy" adoption without the marker: an operation that
started before the marker existed fails with `backup_unconfirmed` and is retried as before.

## Frequency and history as sold — `backups.as_sold` (owner decision 18, TASK-0024)

**The hole.** managed-woo and shop-growth sell `backup_frequency: 1h`, a key `BackupScheduler::FREQUENCIES` does not
know: the scheduler fell back to one slot a day at 00:00, and the customer could not even choose `hourly`
(`backup_frequency_above_plan`). And every sub-daily plan kept only `backup_generations` (default 7) backups whatever its
`backup_days`: "Zálohy 30 dní" on managed-wp was 42 hours of history, shop-peak (15 min, 90 days) 105 minutes. The
configurator's `backup-30`/`backup-90` set only `backup_days`, so history stayed at 7 generations there too.

**The rule — off until the owner switches it on** (`backups.as_sold`, `default_off`, "Zálohy přesně podle ceníku"):

* `1h`/`60m` read as `hourly` (and `24h`/`1d` as `daily`) — in the schedule and in the customer's schedule ceiling;
* on `web` and `managed` services: the newest `generations` backups are kept as before and, beyond them, **the last
  backup of every calendar day** inside the last `backup_days` days (`BackupDailyKeepers`); the prune deletes at most 50
  per tick as before; expired rows go as before; `data`/`cloud` keep TASK-0019's rule (their generations already default
  to the days).

While it is off `scheduleFor()`, the customer's schedule check and the prune behave exactly as before (tests assert it).
Switching it off again stops new hourly slots and new daily keepers, and **deletes nothing en masse**: while the rule is
on, the prune stamps the keeper of every ended day (`backups.meta.kept_as_sold`), and with the rule off the generation
prune (`BackupDailyKeepers::beyondGenerations`) passes stamped rows by — they leave only through their own
`retention_until` (at most `backup_days` after they were made), like any expired backup. Backups the rule did not keep
are still trimmed to the generation cap at 50 per tick. (Before review round 1 the switch-off deleted every keeper of
every web/managed customer at the next ticks, with no way back. Switching a rule is `automation.toggle`, HIGH with a
fresh step-up since owner decision 13, but it still has no dry run.)
To free the disk sooner, delete individual backups as staff, never by switching rules.

| Plan | Sold | Today (rule off) | As sold (rule on) |
| --- | --- | --- | --- |
| web-hosting start / standard / profi, web-custom | daily, 7 / 30 / 90 days | daily 02:30, history = generations (7 / 30 / 90) | same |
| managed-wp, shop-start | 6h, 30 days | 6h, 42 h of history | 6h, 7 generations + one a day for 30 days |
| managed-woo, shop-growth | 1h, 30 days | **daily at 00:00**, 7 days | hourly, 7 generations + one a day for 30 days |
| shop-peak | 15m, 90 days | 15m, 105 min of history | 15m, 7 generations + one a day for 90 days |

**Before switching on:** `php artisan onhost:backups:frequency-plan` (read-only) lists every web/managed service —
sold, now, as sold, history now and as sold, extra copies and an estimate of the extra storage (last completed backup ×
extra copies) — and ends with `N service(s) change · about X GB more on the backup disk · rule backups.as_sold: on|off ·
nothing was changed`. Check the estimate against the capacity of `ONHOST_PLATFORM_BACKUP_DISK` and confirm the retention
meaning (frequency for the generations + one a day for `backup_days`; every sub-daily copy for the whole `backup_days`
would be 8 640 full archives per site on shop-peak). New plan versions spell `hourly` instead of `1h` (revision
`2026-09-honest-promises`, TASK-0022); the alias stays for the versions customers already hold.

The frequency is what the plan version sells (ADR-0007 §18): there is no lower platform default, and the `1h` gap is closed
only through this rule, reviewed first with the read-only operator command above.

## Mailbox backups follow the plan — `mail.backup_retention` (owner decision 3, TASK-0024)

**The hole.** Mail Business sells `backup_days` 14, Mail Enterprise 30. ISPConfig backs mailboxes up on its own nightly
run and keeps `backup_copies` of them only where `backup_interval` is set — and `mail_user_add` never sent either, so
every mailbox the platform made kept whatever the panel's form default is (ASSUMED `none`: then no mailbox has had a
backup at all; check on staging). `BackupScheduler` selects mail services and skips them (no `backup_schedule`); it stays
out of mail on purpose, because the panel keeps the retention itself. An autoresponder change also sent a partial
`mail_user_update` that could reset the backup fields to form defaults; it now goes through the same merge as every other
mailbox update.

**The rule — off until the owner switches it on** (`mail.backup_retention`, `default_off`, "Zálohy schránek podle tarifu"):

* a new mailbox of a **mail** plan is created with `backup_interval=daily`, `backup_copies=backup_days`
  (`MailboxBackupPolicy::onCreate`, read from the service, never from the request); web plans' mailboxes are not touched
  (a web plan's `backup_days` means its site sets);
* a **paid plan change** of a mail service (`PlanChangeService` sets `apply_mailbox_backup`) ends its resize with
  `MailboxBackupRetentionStep`: more copies are applied to the service's own mailboxes at once; **fewer copies are held**
  (the panel would delete the difference at its next run) until an operator applies them with `--allow-prune`. The step
  never fails the plan change — a refused mailbox lands in `tags.mail_backup.failed` and the audit
  (`service.mail_backup.retention`). A drift repair or an add-on resize never carries the flag and never touches mailboxes;
* a mail domain made while the rule is on is recorded as at its plan (`tags.mail_backup.source = provision`).

While it is off, mailboxes are created and plans changed exactly as before (tests assert it). Switching it off stops new
mailboxes and plan changes from setting retention; what the panel already keeps stays as it is.

**Existing mailboxes** reach it only through `php artisan onhost:mail:backup-retention`:

* without options it only **reads** the panels and lists every mailbox of every live mail service — `now`
  (interval/copies), `plan` (daily/backup_days) and a status: `would_set`, `ok`, `not_ours` (never written),
  `would_prune — will prune N existing copies (--allow-prune)`, `unreadable` — and ends with
  `N service(s) behind · … · rule mail.backup_retention: on|off · nothing was changed`;
* `--apply` (refused while the rule is off) asks for one `mailbox.backup_retention` operation per service that is behind
  (system actor, audited); a service holding a downgrade is skipped unless `--allow-prune` is given too;
  `--service=<id>` (repeatable) limits the run. The operation proves every mailbox again before it writes;
* **`--allow-prune` is all-or-nothing for the run.** Without `--service=` it applies every held downgrade the run finds,
  across all customers, in one go (a test holds this). Always scope it: list first, then
  `--apply --allow-prune --service=<id>` per service whose customer was told — never a bare `--apply --allow-prune`;
* a downgrade is any owned mailbox keeping **more copies than the plan, whatever its interval** (`weekly`/`monthly` set by
  hand, or `none` that may still hold older copies) — rewriting it to `daily/<plan>` would make the panel delete the
  rest, so it is `would_prune` and held like a daily one;
* a second `--apply` while a service's operation is still in flight is refused for that service
  (`operation_in_progress`, printed as a warning) — never a second write; run it again once the first settled.

**Ownership.** Nothing is written in a mail domain the adapter cannot prove is the platform's: the binding names the
organisation's client, `client_get` says it is an `onh_…` client, `client_get_groupid` gives its group and the
`mail_domain_get` row is this domain and carries that group (CONFLICT otherwise). Each mailbox must carry the same group
and an address inside the domain, or it is `not_ours`. A customer cannot run the action (`operator_only`, 403); staff
through the API need HIGH risk (step-up).

**Before switching on (operator):** on a **staging** ISPConfig check that `mail_user_add`/`mail_user_update` accept
`backup_interval=daily` and `backup_copies` up to 30, that `mail_user_get` returns `sys_groupid`, and after a night that
`mail_user_backup_list` shows backups and a restore works; size the mail server's backup directory for the worst case
(Mail Enterprise: 30 daily copies of up to 100 × 25 GB mailboxes) and monitor it. Then switch the rule on, run the
command without `--apply`, apply to one or two services with `--apply --service=<id>`, then to the rest, and check the
doctor row. Downgrades: tell the customer before `--allow-prune` — it deletes backups.
