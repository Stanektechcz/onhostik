# Metering after launch (P19, phase R)

Owner rule 3 (2026-09-24): **measure everything**; a limit raised by hand is billed. This proposal says where metering stands
on `development` (2026-10-13, after phase Q), which gaps are left, how each one closes, and in which order the switches that
change behaviour for existing customers are turned on after the launch (owner decision **P-R24** = SEZNAM PR-18). Nothing in
this package switches anything; every step below has a preview, its own "ano" and a rollback.

Sources of truth: `domains/Services/Metering/MetricRegistry.php` (every key a plan sells, how it is kept), `PlanPromises::KNOWN_GAPS`
(the ratchet), `tests/Feature/Catalog/PlanPromisesTest.php` (the guard), `onhost:doctor` (the standing WARN that lists the gaps).

## 1. Where it stands (VERIFIED from the code)

| What | State |
| --- | --- |
| `MetricRegistry::REGISTRY` | 41 keys: **6 measured** (`nvme_gb`, `traffic_gb`, `inodes`, `ram_mb`, `quota_mb`; `traffic_tb` for VPS/VDS since T3), **25 enforced** by the panel or the platform (e.g. `mailboxes`, `databases`, `domains`; `aliases` since S1), **10 gap** rows (`pids` stays one for held versions since T2) |
| Gap rows not sold any more | `dedicated_outbound_ip`, `dedicated_db`, `connections`, `pitr_days` (owner decisions 2/4/6, revision `2026-09-honest-promises`; kept for the versions customers already hold), `products` (fair use, decision 5), `pids` (T2, revision `2026-10-game-plans-without-pids`), `bot_management` (U2: the CDN add-on is off sale) |
| `PlanPromises::KNOWN_GAPS` (sold and kept by nothing) | **2** (7 until S1 closed `aliases`, TASK-0210; 6 until T2 closed `pids`, TASK-0214; 5 until U2 took the CDN add-on off sale, TASK-0218, closing `traffic_tb` and `bot_management`; 3 until U4 declared `spam_filter` fair use, TASK-0220): `php_workers`, `backup_days` |
| `usage.rotation` (automation rule, *Měření všech služeb*) | off (`default_off`); the usage watch measures only the first 200 services per run until it is on |
| `ONHOST_METERING_ENFORCE_NEW_METRICS` | `false`: a metric that never drove anything on a family (e.g. RAM of a managed database) is only observed |
| `ONHOST_LIMIT_RAISE_CUSTOMER_ORDERS` | `false`: staff place limit raises as assisted orders; customers cannot order one in the panel yet |
| `backups.as_sold`, `backups.compute`, `mail.backup_retention` | off (`backup_days` is kept only under them — see §2) |

The ratchet: `KNOWN_GAPS` may only shrink. A gap that gets closed must leave the list in the same change (the guard fails
otherwise), and a new unkept number fails the guard until it is named — it is never absorbed silently.

## 2. The seven gaps and how each closes (M1 closed in S1, M3 in T2)

| Gap | Sold on | What closes it | Needs a live panel? | Package |
| --- | --- | --- | --- | --- |
| `aliases` | mail plans (50 / 500) | `alias.create` runs the generic `$limit('aliases', …)` count check that `mailbox.create` uses. **Catch:** `IspConfigWebProvider::listAliases()` reads `mail_alias_get` by `source = %@domain`, which also returns forwards and the catch-all; the count must take rows of `type = alias` only, so the adapter has to return `type`. | Code and tests with the stateful ISPConfig fake; one read on staging that `mail_alias_get` returns `type` (mode S) | **M1 — closed in S1 (TASK-0210):** `alias.create` counts against the plan, `listAliases()` keeps `type = alias` and `listForwards()` `type = forward`. The `type` column was taken from ISPConfig's own source (`mail_forwarding.type` enum, `remoting_lib::getDataRecord` filters by the given columns only), not from a staging read: the staging mail server is shared with historical customers, and a listing there would read their rows |
| `traffic_tb` | VPS/VDS, CDN add-on | Monthly transfer of a VM from Proxmox (`rrddata` netin/netout, summed per calendar month) into `UsageWatch` for the `cloud` family; warn at 85 %, never cut off (the price list says what happens above) | Code with Proxmox fakes; on a real cluster (PX-1, staging has no Proxmox): units, the month archive's 12 h step, and does the RRD survive a VM restart and a migration? | **M2 — code in T3 (TASK-0216):** `ProxmoxComputeProvider::usage()` with a period sums `rrddata` (month, AVERAGE) netin + netout × step since the start of the calendar month (UTC); `UsageWatch` reads it as the soft metric `transfer` against `traffic_tb` (binary TB) — warns from 85 %, never blocks, throttles or orders, and as a metric new on `cloud` it tells customers only after `ONHOST_METERING_ENFORCE_NEW_METRICS=true` (D+30, §4). `KNOWN_GAPS` kept `traffic_tb` while the **CDN add-on** sold it with nothing measuring it there; **U2 (CD-3, TASK-0218)** took the add-on off sale (seeder `draft`, migration `001210`), so the key left the list and the `gap_families` note went with it. Verification on Proxmox: ASSUMED until PX-1 |
| `pids` | game plans' limits bag | Pterodactyl has no per-server PID field (`memory/swap/disk/io/cpu/threads/oom_disabled`). Either the catalogue revision drops `pids` from the bag (honest, no panel), or a PID limit in the Wings configuration of each node (operator, applies to every server of the node; key name ASSUMED, check on the node) | Revision: no. Wings: yes | **M3 — closed in T2 (TASK-0214) by the revision:** the seeder writes no `pids`, migration `001200` takes the seeded number off the plan versions nobody holds (only while it still stands), the revision `2026-10-game-plans-without-pids` publishes a version without it where a held version is current; held versions keep it (`grandfatheredGaps()`) |
| `spam_filter` | mail plans ("rspamd" / "rspamd + policies") | A string, not a number: either a spam policy per tier written to the mailbox (`spamfilter_user` + policy id in ISPConfig) or the price list words it as a description (fair use) | Policy: yes (staging). Wording: no | **M4 — default done in U4 (TASK-0220):** `PlanPromises::FAIR_USE` with its reason, the price list says "Antispam rspamd s pravidly serveru (fair use)" for every mail plan (table: "rspamd, pravidla serveru") instead of a tier; catalogue values unchanged (held versions keep them); the `MetricRegistry` row stays GAP with the fair-use reason. A per-plan policy remains the owner's choice → a package with staging |
| `php_workers` | WordPress/e-shop on aaPanel | aaPanel has one PHP-FPM pool per version per node (`configurePhp()` returns `applied=false`); decision 7 already words it as the shared pool. Closes by a dedicated pool per site (capacity decision) or as fair use | Yes for pools | **M5** — after the capacity decision |
| `bot_management` | CDN add-on | The CDN is not sold (P-R46, `cdn_cloudflare` gate). Closes with the CDN adapter, or the revision drops it from the add-on | No | **closed on sale by U2 (CD-3, TASK-0218):** the add-on is a draft (seeder + migration `001210`, only while it stands as seeded; held subscriptions untouched); the `MetricRegistry` row stays a GAP for what customers hold. Putting the CDN back on sale brings both keys back to the guard |
| `backup_days` | web/managed, mail, db-s/db-m | Kept only under the default-off rules: `backups.as_sold` (web/managed), `mail.backup_retention` (mail), `backups.compute` (data). Closes when the owner switches them (PX-3 = P-R50, PX-4 = P-R51) | Yes (PBS test VM, mail node) | owner switches, §4 |

Each package: test first against the fakes, the gap leaves `KNOWN_GAPS` and its `MetricRegistry` row changes status in the same
change, `PlanPromisesTest` green, the doctor's standing WARN loses the key. None of them changes a plan version a customer holds:
a wording change or a dropped key is a catalogue revision (`onhost:catalog:revise`, preview by default).

## 3. Baseline report (what to keep, so later switches can be judged)

| When | Command (read only) | Keep |
| --- | --- | --- |
| D0 (go-live) | `php artisan onhost:doctor --json` | the metering rows and the gap list |
| D+1, before `usage.rotation` | `php artisan onhost:metering:preview --limit=500 --json` | how many services would be told, blocked or upgraded; which answer `null` (not measured) |
| D+7 | `php artisan onhost:metering:preview --json` again | the same numbers a week later: the trend, not a single read |
| D+30, before `ENFORCE_NEW_METRICS` | the preview + the daily rows of `service_usage_samples` per family (`onhost:metering:rollup` writes them) | services a new metric would start to warn or block |
| after the first billing cycle | `php artisan onhost:limit-raise` (lists raises, dry run) | the assisted raises staff placed and what they cost |

The reports go to the owner without customer names (counts per family and plan).

## 4. Switch schedule (P-R24), each step with a preview, an "ano" and a rollback

| Step | Switch | Preview first | How (production, owner runs it — mode V) | Rollback |
| --- | --- | --- | --- | --- |
| D+1 | `usage.rotation` on | `onhost:metering:preview` (§3) | staff console *Automatizace* → *Měření všech služeb* on (`PUT /v1/staff/automation/usage.rotation`, audited) | the same switch off; the first measurement of each service is only its baseline — nothing was blocked or ordered by it |
| D+30 | `ONHOST_METERING_ENFORCE_NEW_METRICS=true` | the D+30 report (§3): which services a new metric would warn or block | one line in `app.env`, then the gated deploy of the same SHA (config cache) | `false` + the same deploy; warnings already sent stay sent, nothing else persists |
| after the 1st billing cycle | `ONHOST_LIMIT_RAISE_CUSTOMER_ORDERS=true` | `onhost:limit-raise` (dry run): raises staff placed, their prices | `app.env` + gated deploy | `false` + deploy; raises already ordered stay valid (paid) |
| owner's choice (PX-4) | `backups.as_sold` on | `onhost:backups:frequency-plan` | staff console *Automatizace* | switch off; extra copies made meanwhile are pruned by the normal retention |
| owner's choice (PX-3, after the PBS test VM) | `backups.compute` on | `onhost:backups:compute-plan` (no `MISSING`) | staff console *Automatizace* | switch off; backups already made stay until their retention |
| owner's choice | `mail.backup_retention` + `onhost:mail:backup-retention --apply --service=<id>` per service | `onhost:mail:backup-retention` (lists) | rule on, then apply per service (never a bare `--apply --allow-prune`) | rule off; copies kept so far stay |

Rules that stay: no switch on a day with an open incident; one switch per working day; after each switch the doctor and the
D+1/D+7 numbers are read again before the next one. Staging first where the switch has a staging meaning (A–C cycle of the same SHA).

## 5. What this package does and does not do

- Does: this proposal (merged), the gap packages M1–M5 defined, the schedule with previews and rollbacks.
- Does not: switch anything, close a gap in code (M1 is the first candidate and needs one staging read to settle the `type`
  field; it is the next metering task), change a plan version.
- `KNOWN_GAPS` is unchanged at 7 (the ratchet did not grow).
