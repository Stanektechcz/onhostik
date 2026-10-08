# Staging rehearsal protocol, started 2026-10-07 (phase I, I6)

**Status: R0–R2 run 2026-10-08 (read-only, approved by the owner) — R0 differs, R1 OK (0 FAIL), R2 OK for the units; the
rehearsal stops before R3 (staging carries a 2026-09-28 release, see *Run 2026-10-08*).** This is the protocol of the rehearsal scripted in
[staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md) (steps R0–R24). It holds **outputs only**: the doctor row a step
watches, the exit code, what differed, which rollback was used. **No password, TOTP secret, recovery code, token, key or env value
ever goes into this file** — not even redacted fragments.

Rules (from the script, unchanged): one explicit owner "yes" per step, before the command is typed; staging only, nothing on
production; read before write; a step that fails is rolled back by its own *Rollback* line and the rehearsal stops — it is not
retried "another way" without a new yes, and the cause becomes a package. The owner's proposal for the first steps is
`GENERALKA-R0-R2-navrh.md` on the owner's desktop (outside the repository).

## Header

| Field | Value |
| --- | --- |
| Rehearsal date | 2026-10-08 (R0–R2, 01:37–01:38 UTC) |
| Staging commit (`$S/releases/current`) | **file absent** (pre-release layout); last deploy `e711b7c7` 2026-09-28T16:26:46Z (`deploy.log`) |
| `VERSION` on staging | `e711b7c70f559276ebeb5d95dc7956874b6c8da4` |
| Operator | AI workstation (TASK-0137), root over key-only SSH (`onhost-staging`), no password used |
| Owner present | ☐ (approval in chat 2026-10-08, not present at the shell) |
| Repository state the script was checked against | `development` at `c0fc7ff1` (2026-10-07), CI `tests`, `security`, `e2e smoke` green |

## Step log

Result: **OK** (as expected) / **differs** (stopped, see notes) / **skipped** (with reason) / **not run**.

| Step | Owner yes (time) | Start–end | Result | Doctor row watched (status, detail) | Notes (what differed, rollback, follow-up package) |
| --- | --- | --- | --- | --- | --- |
| Shell variables + `/root/rehearsal-2026-10` | 2026-10-08 (prep + R0–R2, one approval) | 01:37 UTC, run before each step | OK | — | `setpriv` present, no `STOP`; `P=/www/server/php/85/bin/php` (F1 settled: the path the units name); directory created 0700 |
| R0 release and gate lists | 2026-10-08 | 01:37:12–01:37:15Z | **differs** | `staging.sh status`: 0 ✖, no `PARKED`, 26 WARN, `/up over loopback: 500` | `$S/releases/current` does not exist (staging predates the release layout, F2); `/up` answers 500 over loopback (F3); the four gate lists exist, root, 0600 |
| R1 doctor before | 2026-10-08 | 01:37:30–01:37:35Z | OK | `rc=0`; 119 checks, **0 FAIL**, 26 WARN | 12 of the 13 watched rows do not exist in the staging release (F2); see *R1 starting picture* |
| R2 PHP binary | 2026-10-08 | 01:37:46–01:37:48Z | OK (units) | *PHP binary is the one …*: row absent in this release | `PHP 8.5.8`; both `ExecStart` and the effective `systemctl show` path are `/www/server/php/85/bin/php`; 8.3 is used only by another application's cron on the same host (F1) |
| R3 usranalyse directives | | | not run | queue workers up (no exit 7) | |
| R4 TRUSTED_PROXIES loopback | | | not run | *trusted proxies are exact addresses* | |
| R5 shared cache | | | not run | *cache store is shared (rate limits)* | |
| R6 revise web plans, dry run | | | not run | — | |
| R7 revise web plans, apply | | | not run | *catalogue revision 2026-10-deliverable-web-plans applied* | |
| R8 revise custom ISO, dry run | | | not run | — (owner decision 9 first) | |
| R9 revise Penpot, dry run | | | not run | — | |
| R10 staff TOTP (owner himself) | | | not run | *staff and demo accounts have an authenticator* | |
| R11 exchange rates | | | not run | *exchange rates are fresh* | |
| R12 tokens (dry run, switch) | | | not run | *API tokens must name an organisation (R9)* | |
| R13 reinstatement audit | | | not run | *pay and restore (services.reinstate)* | |
| R14 VAT entity (non-payer) | | | not run | *VAT payer mode agrees with the legal entity* | |
| R15 clamd | | | not run | `onhost:isos:scanner-check --fresh` | |
| R16 ISO volume, PHP and nginx | | | not run | by hand: `findmnt`, `stat`, `df`, `realpath` | |
| R17 Proxmox ISO storage | | | not run | instance option `custom_iso_storage` | |
| R18 revise custom ISO, apply | | | not run | *custom ISO virus scan passes its self-test*, *custom ISO storage has room* | only after R15–R17 OK |
| R19 ISO upload smoke | | | not run | manual test `05-vps.md` | |
| R20 revise Penpot, apply | | | not run | *Penpot is sold only with a Penpot node to run it* (FAIL expected, non-blocking) | |
| R21 webhook lane and overlap | | | not run | *rotated webhook secrets overlap only briefly* | |
| R22 dead letters | | | not run | *no outbox dead letters* | |
| R23 final doctor, `expected-nonok` | | | not run | 0 unexpected FAIL | |
| R24 gated deploy (optional) | | | not run | gate verdict | owner decides |

## R1 starting picture (fill from `doctor-R1.json`)

"absent" = the doctor of the staging release (`e711b7c7`, 2026-09-28) has no row of that name; it is not a status.

| Doctor row | Status at R1 | Status at R23 |
| --- | --- | --- |
| PHP binary is the one the deploy and the workers must use | absent | |
| trusted proxies are exact addresses | absent | |
| cache store is shared (rate limits) | absent | |
| every catalogue revision is applied | WARN — `2026-09-honest-promises` (db-m, db-s, shop-growth, shop-peak, mail-enterprise, managed-woo) and `2026-09-shared-php-workers` (shop-peak) not applied | |
| catalogue revision 2026-10-deliverable-web-plans applied | absent | |
| staff and demo accounts have an authenticator | absent | |
| exchange rates are fresh | absent under this name; the older row *the national bank's exchange rates are fresh* is OK, latest list 2026-10-07 | |
| API tokens must name an organisation (R9) | absent | |
| VAT payer mode agrees with the legal entity | absent | |
| custom ISO virus scan passes its self-test | absent | |
| custom ISO storage has room | absent | |
| rotated webhook secrets overlap only briefly | absent | |
| no outbox dead letters | absent | |

All 26 WARN rows at R1 (no FAIL): `app` APP_ENV is production · `providers` wedos-main (wedos, active), active nodes, WEDOS
test mode off, every product can be provisioned · `capacity` dedicated PHP workers are sold only where a site has its own pool,
plans with dedicated PHP workers have a panel to run on, capacity basis as decided · `payments` card gateway (comgate)
configured, card gateway live mode, stored cards for automatic top-ups, bank account for transfers, bank statement import ·
`documents` legal entity bank details real · `identity` four eyes in effect, who decides approvals · `mail` transactional
mailer · `observability` traces exported and linked, console relay key · `files` virus scanner (clamd) · `catalog` no plan on
sale promises a number nothing applies, no known metering gap, every catalogue revision is applied, every plan on sale keeps
its WAF promise on its own panel · `tax` VIES checks are on and the requester VAT ID is set · `security` Turnstile protects
registration and public forms. (`expected-nonok` was not read; comparing against it is R23's job.)

## Attempt 2026-10-08 (prep, R0–R2)

The owner approved the shell preparation and R0, R1 and R2 exactly as proposed in `GENERALKA-R0-R2-navrh.md`
("Generálka R0-R2 ano - připravit a zpracovat", 2026-10-08). R3 and later were **not** approved and were not touched.

* **Result: skipped, nothing run.** The AI workstation has no operator shell on the staging host: no SSH host entry or
  configured user for `staging.onhost.cz`, and a non-interactive key-only connection attempt to port 22 of the name was
  refused at the TCP level (no authentication was attempted, no command reached the host). The proposal itself says the steps
  are typed by the owner, or an operator sitting with him, as root on the host. Nothing was read or written on staging, on
  production or on any panel; no secret was used.
* **What is needed to run them:** either the owner (or his operator) types the prep block and R0–R2 in a root shell on the
  staging host and pastes the non-secret output back for this protocol, or the owner provides a root (or sudo) SSH route to the
  host for the AI workstation (port, user, the workstation key authorised there). The second is a new decision of the owner.

### Pre-flight finding F1 — the PHP path of the script does not match the staging host

Checked against the repository only (`development` at `fd9ed798`):

* The rehearsal script and the proposal set `P=/www/server/php/83/bin/php`.
* `infra/aapanel/staging.sh` (which installed and deploys staging) uses `PHP=/www/server/php/85/bin/php`, and `install.sh`
  writes that path into `ExecStart` of `onhost-queue@.service` and `onhost-scheduler.service`.
* [staging-launch.md](staging-launch.md) (the note after S7) already says that on a staging running 8.5 the constant must be
  `P=/www/server/php/85/bin/php`.

Consequence if R0–R2 are typed as written: every `art` call (and so the doctor in R0, R1, R2) runs under 8.3 if that binary
exists, or fails if it does not; R2's "both units name the same `$P`" would report a difference that is a script error, not a
staging defect. **Before the prep block is typed** the owner checks `ls /www/server/php/*/bin/php` and
`grep -n '^ExecStart' /etc/systemd/system/onhost-queue@.service` (both read-only) and sets `P` to the path the units name.
Changing `P` is a change of the approved command, so it needs the owner's word; the rehearsal script gets the fix in a
follow-up package.

## Run 2026-10-08 (prep, R0–R2)

After the attempt above the owner gave the AI workstation a key-only root SSH route to the staging host and confirmed the
approval of the prep and R0–R2, with `P=/www/server/php/85/bin/php` (F1). The prep block and R0, R1, R2 were run exactly as
proposed, each in its own non-interactive shell (the prep block re-run before each step). Nothing else was run: no deploy, no
`config:cache`, `cache:clear`, `migrate`, restart, file edit or `.env` read. What was written on the host is what the proposal
names: `/root/rehearsal-2026-10/` (0700) with `doctor-R1.json` and `doctor-R1.txt`, `/root/doctor-status.json` (overwritten by
`staging.sh status`), and the doctor's own probe file (written and removed). R3 and later were not touched.

### F1 settled — the PHP the units and cron use

* `/www/server/php/83/bin/php` and `/www/server/php/85/bin/php` both exist on the host.
* `onhost-queue@.service` and `onhost-scheduler.service` name `/www/server/php/85/bin/php` in `ExecStart`, and
  `systemctl show -p ExecStart` of `onhost-queue@default`, `onhost-queue@mails` and `onhost-scheduler` resolves to the same path
  (the `10-aapanel-usranalyse.conf` drop-ins do not change it). ONhost has no cron entry: it schedules through
  `onhost-scheduler.service` (`schedule:work`). `staging.sh` defaults to the same 8.5 path.
* The only user of 8.3 is `/etc/cron.d/pterodactyl` (the game panel `gamepanel.onhost.cz`, another application on the same host);
  root's crontab runs another site's scheduler with 8.5. Neither belongs to ONhost and neither is touched by the rehearsal.
* **Open:** line 36 of [staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md) still says `P=/www/server/php/83/bin/php`.
  The file was locked by TASK-0136 when this run was recorded, so it is not changed here; the fix (`85`) belongs to the next task
  that holds that file. The owner's proposals on the desktop were corrected.

### F2 — staging carries a release older than the rehearsal script

`VERSION` and `deploy.log` show `e711b7c7`, deployed 2026-09-28T16:26:46Z (`stage=done rc=0`). That release predates the release
layout (`$S/releases/current` is missing) and most rows the rehearsal watches: 12 of the 13 rows of the R1 picture do not exist
in its doctor, including the three R3–R5 watch (*PHP binary …*, *trusted proxies …*, *cache store is shared …*) and every R7–R23
row. `/root/onhost-staging.sh` is the copy of the same day (2026-09-28, 19 843 bytes): its `status` prints no release, `PARKED`
or usranalyse lines and writes `/root/doctor-status.json` without `umask 077` (the file sits in `/root`). The rehearsal script
assumes a staging at or near `development`; with this release the remaining steps cannot show what they are meant to show.
**The rehearsal stops here.** Bringing staging to a current `development` commit (a new `/root/onhost-staging.sh` and a gated
deploy, i.e. R24's command moved to the front) is a write on the server and needs its own proposal and owner "yes".

### F3 — `/up` answers 500 over loopback

`staging.sh status` ends with `/up over loopback: 500` (`curl --resolve staging.onhost.cz:443:127.0.0.1`). The doctor itself
runs and the three workers are `active running`, so the application boots in the CLI. The cause was not looked for: the
application log is private storage and the check is outside R0–R2. It has to be explained (read-only: nginx error log for the
vhost, `curl -sk -o /dev/null -w '%{http_code}' https://staging.onhost.cz/up` from outside) before any write step; R4's own
expectation "`/up` keeps returning 200" is not true today.

### Other observations (read-only, not findings)

* The usranalyse drop-in `10-aapanel-usranalyse.conf` already exists for `onhost-queue@` and `onhost-scheduler` (listing only,
  content not read), so R3b is likely a restart only.
* The host also runs `gamepanel.onhost.cz` and another site with their own cron; per the owner rule on historical
  sites they are untouchable, and anything that flushes a shared Redis database (`cache:clear`) stays excluded.

## Outputs per step

Paste only non-secret output (doctor rows, exit codes, `ls -l` lines, SHAs). The full doctor reports stay in
`/root/rehearsal-2026-10/` on the staging host.

### R0

```
$ cat $S/releases/current; cut -d' ' -f1 $APP/VERSION
cat: /var/lib/onhost-deploy/staging.onhost.cz/releases/current: No such file or directory
e711b7c70f559276ebeb5d95dc7956874b6c8da4
$ ls -l $S/expected-units $S/expected-nonok $S/expected-env $S/egress-blocked
-rw------- 1 root root    0 Sep 28 02:10 .../egress-blocked
-rw------- 1 root root 6634 Sep 28 17:56 .../expected-env
-rw------- 1 root root 1291 Sep 28 13:26 .../expected-nonok
-rw------- 1 root root  186 Sep 28 02:10 .../expected-units
$ bash /root/onhost-staging.sh status
▶ Status
  version: e711b7c70f559276ebeb5d95dc7956874b6c8da4 e711b7c70f559276ebeb5d95dc7956874b6c8da4
  php: 8.5.8
  node: v24.18.1
onhost-queue@default.service loaded active running ONhost queue worker (default)
onhost-queue@mails.service   loaded active running ONhost queue worker (mails)
onhost-scheduler.service     loaded active running ONhost scheduler
2026-09-28T16:26:46Z site=staging.onhost.cz operator=root … from=e711b7c7… to=e711b7c7… stage=done rc=0 … expected_nonok=500fbcfd339b …
  OK  automation|scheduler running
  OK  automation|queue worker alive   (also: default, mails)
  WARN … 26 rows, listed under "R1 starting picture"
  /up over loopback: 500
```

### R1

```
$ doc > /root/rehearsal-2026-10/doctor-R1.json; echo "rc=$?"
rc=0
$ art onhost:doctor | tee /root/rehearsal-2026-10/doctor-R1.txt | tail -60
… (table; non-OK rows as listed under "R1 starting picture")
staging · 119 checks · 0 FAIL · 26 WARN
$ jq over doctor-R1.json: OK=93 WARN=26; no FAIL row
```

### R2

```
$ $P -v | head -1
PHP 8.5.8 (cli) (built: Jul 12 2026 23:08:52) (NTS)
$ grep -n '^ExecStart' /etc/systemd/system/onhost-queue@.service /etc/systemd/system/onhost-scheduler.service
/etc/systemd/system/onhost-queue@.service:15:ExecStart=/www/server/php/85/bin/php artisan queue:work redis --queue=%i --tries=1 --timeout=900 --sleep=2 --max-jobs=500 --max-time=3600
/etc/systemd/system/onhost-scheduler.service:14:ExecStart=/www/server/php/85/bin/php artisan schedule:work
$ row "PHP binary is the one the deploy and the workers must use"
(no output: the row does not exist in release e711b7c7)
$ systemctl show -p ExecStart --value <unit>   # path only
onhost-queue@default, onhost-queue@mails, onhost-scheduler: path=/www/server/php/85/bin/php
```

(Sections R3 … R24 are added as the steps are run, in the same form. The owner's proposal for R3–R5 is
`GENERALKA-R3-R5-navrh.md` on the owner's desktop; after this run it waits for F2 (a current release on staging) and F3
(`/up` 500) — see *Run 2026-10-08*.)

## Closing paragraph (after the last step)

The doctor at R1 and R23, which rows moved, which steps were skipped and why, which rollback was used, the updated
`expected-nonok`, and the list of items that must go into the production plan
([go-live-checklist.md](go-live-checklist.md), section *Blocking for production*).
