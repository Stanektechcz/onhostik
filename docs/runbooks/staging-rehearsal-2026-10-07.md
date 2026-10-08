# Staging rehearsal protocol, started 2026-10-07 (phase I, I6)

**Status: R0–R2 run 2026-10-08 (read-only, approved by the owner) — R0 differs, R1 OK (0 FAIL), R2 OK for the units; the
rehearsal stops before R3 (staging carries a 2026-09-28 release, see *Run 2026-10-08*). R2a (read-only) found why `/up`
answers 500: the vhost serves PHP through PHP-FPM 8.3, the release runs on 8.5 (see *R2a*); the fix and the deploy wait for
the owner's R2b. R2b-0 to R2b-4 run 2026-10-08 02:40–02:42 UTC (owner "ano"): vhost on PHP 8.5, `/up` 200 over loopback
and from outside, backups in `/root/r2b-2026-10`, new `onhost-staging.sh` and deployer from `0f3cde26`; the gated deploy
(R2b-5) waits for its own owner "yes" (see *R2b*).** This is the protocol of the rehearsal scripted in
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
| R2b-0 checks (read-only) | 2026-10-08 ("ano" R2b-1 to R2b-4, prep + R2b-0 included) | 02:40:30Z | OK | `/up` loopback 500 (expected) | one `include enable-php-83.conf` directive (line 81; lines 15 and 47 are comments that mention it); `enable-php-85.conf` → `php-cgi-85.sock`; `VERSION` = last-good = `e711b7c7`; three units active; `pg_dump` 18.0; 266 GB free; five other vhosts include 83 (unchanged) |
| R2b-1 vhost → PHP 8.5 | 2026-10-08 | 02:40:45–02:40:54Z | OK | `/up` loopback **200**, outside **200**, access log 200 | shell edit (the proposal's equivalent of the panel switch); only line 81 changed; `nginx -t` ok (pre-existing `proxy_headers_hash` warnings), reload rc 0; copy of the old vhost in `$B` |
| R2b-2 backups | 2026-10-08 | 02:41:00–02:41:20Z | OK | — | dump 206 `TABLE DATA` entries; tree archive 8522 `vendor/` entries; state archive 278 `repo.git` entries; `sha256sum -c` all OK |
| R2b-3 `onhost-staging.sh` | 2026-10-08 | 02:41:50–02:42:00Z | OK | `check passed`, no ✖ | `SHA` = `0f3cde26b8efc4c60b758fb4dc2b20f2733d0ea1` (development tip = merge of PR #133, as named in the proposal); `bash -n` clean; usranalyse line present |
| R2b-4 deployer | 2026-10-08 | 02:42:05–02:42:21Z | OK | `app.env matches expected-env` | `source-sha` = `0f3cde26…`; `expected-env` rewritten (key names only); `expected-nonok` unchanged (2026-09-28); app, DB, units unchanged (`VERSION` still `e711b7c7`, `/up` 200) |
| R2b-5 gated deploy | | | not run | gate verdict | waits for the owner's "ano R2b-5 0f3cde26…" |
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
expectation "`/up` keeps returning 200" is not true today. **Explained by R2a below: the vhost serves PHP through PHP-FPM 8.3.**

### Other observations (read-only, not findings)

* The usranalyse drop-in `10-aapanel-usranalyse.conf` already exists for `onhost-queue@` and `onhost-scheduler` (listing only,
  content not read), so R3b is likely a restart only.
* The host also runs `gamepanel.onhost.cz` and another site with their own cron; per the owner rule on historical
  sites they are untouchable, and anything that flushes a shared Redis database (`cache:clear`) stays excluded.

## R2a 2026-10-08 — why `/up` answers 500 (read-only diagnosis)

The owner approved R2a: a read-only diagnosis of F3, 2026-10-08 ~02:05–02:15 UTC, over the same key-only root SSH route.
**Nothing was written, restarted, reloaded, cleared or migrated on the host.** Read: directory listings and modes, `VERSION`,
the deploy state directory listing, the vhost's nginx configuration and its access and error logs, PHP-FPM 8.3/8.5 master logs,
the message lines of the application log (first 200 characters of each entry, no context or stack), the aaPanel WAF's Lua
source around its 500 path, the composer platform check, the session cookie *name* from the cached configuration (one key, by
`grep`). Not read: `.env`/`app.env`, private storage, sessions, any secret. Diagnostic requests, each equivalent to one
`GET /up`: one FastCGI request (`cgi-fcgi`, as `www`) to each PHP-FPM socket, and one in-process request of `/up` through the
HTTP kernel under PHP 8.3 CLI as `www` (the script came on stdin; nothing was saved on the host). Reading the PHP-FPM 8.3 pool
and `php.ini` was refused by the workstation's permission check and was not pursued.

### Cause

**The nginx vhost of `staging.onhost.cz` hands PHP to PHP-FPM 8.3, and the PHP-FPM 8.3 instance answers every application
request with an empty 500.** The release, the units, `staging.sh` and the deployer all use 8.5.

| Evidence | What it shows |
| --- | --- |
| vhost `/www/server/panel/vhost/nginx/staging.onhost.cz.conf` (unchanged since 2026-09-15): `include enable-php-83.conf` → `fastcgi_pass unix:/tmp/php-cgi-83.sock` | the site runs on PHP-FPM 8.3; `enable-php-85.conf` (→ `/tmp/php-cgi-85.sock`) exists and the 8.5 pool runs |
| public `GET /up` and `GET /`: `500`, empty body (access log size 0); `/build/manifest.json`: 200 | static files are fine; every PHP route fails |
| FastCGI `GET /up` straight to `php-cgi-83.sock` (as `www`, no nginx, no WAF) | `Status: 500`, empty body |
| FastCGI `GET /up` straight to `php-cgi-85.sock` (same request) | 200 with the application's own security headers |
| `/up` in process under PHP 8.3.32 CLI as `www` | `STATUS 200` — the code runs on 8.3; the fault is the FPM 8.3 instance, not the code |
| the `server_session_*` cookie on the 500 | set by aaPanel's WAF (`btwaf/public/public.lua`); the application's cookie is `onhost-session`. The WAF's only 500 path sends a captcha page with a body, so the empty 500 is not the WAF |
| access log timeline | PHP routes 200 through FPM 8.3 until 2026-09-29 23:19 (+0200), the first 500 at 2026-09-30 00:24, only 500 since. `/up` was 200 right after the 2026-09-28 18:26 deploy |
| PHP-FPM 8.3 master log | running since 2026-09-15 17:10, never reloaded since; one child SIGKILL 2026-10-01 18:43. The deployer reloads only 8.5 (`PHP_FPM_RELOAD=/etc/init.d/php-fpm-85 reload`), so FPM 8.3's OPcache never saw a release reset |
| application log | no entry for any web 500 since 2026-09-30; it holds only scheduler (8.5 CLI) entries: `registrar:poll skipped` / `registrar:credit` (no `WEDOS_MAIN_*` secrets on staging, expected) and `files:prune` (`League\Flysystem\AwsS3V3\PortableVisibilityConverter` missing, not related to `/up`) |
| vhost nginx error log | no FastCGI or upstream error, only `access forbidden by rule` for scanners |

**Not established:** the PHP error inside FPM 8.3 itself. The application cannot report it (nothing in its log), FPM 8.3 has
no `error_log`, and its configuration was not read (see above). Leading hypothesis, unconfirmed: the never-reloaded 8.3 pool
serves a stale OPcache mix of pre- and post-2026-09-28 files, or a pool-level setting differs. It does not change the fix:
FPM 8.3 should not serve this site at all.

### Fix (a write: own owner "yes", proposed in R2b)

Switch the vhost to PHP 8.5 (aaPanel → Website → `staging.onhost.cz` → PHP version 8.5, i.e. `include enable-php-85.conf`),
`nginx -t`, reload nginx, expect `/up` 200 over loopback and from outside. It has to come **before** any gated deploy: the
deployer's gate requires `GET /up` 200 through the maintenance bypass (rc 5 otherwise, site left in maintenance), and its
OPcache reset reaches only FPM 8.5. Rollback: the `83` include back, `nginx -t`, reload. FPM 8.3 itself stays untouched (the
game panel's cron uses the 8.3 CLI, not the FPM pool; whether another vhost uses `php-cgi-83.sock` is checked before, not changed).

### Repository follow-ups (not changed here)

* `infra/aapanel/nginx-site.conf` says to keep aaPanel's `include enable-php-83.conf` and that it maps to PHP-FPM 8.3 — wrong
  for the 8.5 staging that `staging.sh` builds; it should name the include matching `$PHP`.
* `staging.sh check`/`status` do not compare the vhost's `fastcgi_pass` socket with the PHP the deploy uses; a check would have
  caught F3 on 2026-09-30.

### Facts recorded for the R2b proposal (read-only)

* Staging database `onhost_staging_b` (owner `onhost_b`, 67 MB) on aaPanel's PostgreSQL 18 (`/www/server/pgsql`, socket in
  `/tmp`). `/usr/bin/pg_dump` is version 14 and cannot dump an 18 server: a dump uses `/www/server/pgsql/bin/pg_dump`.
* Tree 124 MB without `node_modules` (108 MB), `storage` 29 MB, state directory 17 MB, 266 GB free on `/`.
* aaPanel runs nginx and PHP-FPM from `/etc/init.d`; their systemd units show `failed`/`inactive` and are not used.
* `e711b7c7..9eddcd05` (development tip at R2a): 456 commits, 11 new migrations, and `deploy.sh`, `install.sh`, `staging.sh`,
  `onhost-queue@.service` changed.

## R2b 2026-10-08 — `/up` fixed, backups, script and deployer (R2b-0 to R2b-4)

The owner approved R2b-1 to R2b-4 of `GENERALKA-R2b-navrh.md` as written ("ano", 2026-10-08); R2b-5 (gated deploy), R2b-5a
and everything later were **not** approved and were not run. Same key-only root SSH route, shell variables exactly as in
the proposal (`B=/root/r2b-2026-10`, 0700). Times are UTC (the host runs CEST). Every command was the proposal's own; no
step failed and no rollback was used. `.env`/`app.env` and private storage were not read; `app.env` was only compared by
the deployer (key names, no values printed) and packed into a root-only archive.

* **R2b-1:** the vhost was changed in the shell (`sed` on the one `include enable-php-83.conf;` directive), not in the
  aaPanel UI. aaPanel's own site record therefore still says PHP 8.3: **saving `staging.onhost.cz` in the panel would write
  `enable-php-83.conf` back and bring the 500 back.** Setting PHP-85 for the site in the panel (Website → PHP version)
  makes the panel agree; until then nobody saves that site in the panel. PHP-FPM 8.3 and the other vhosts were not touched.
* **R2b-2 backups** (all in `/root/r2b-2026-10`, root, directory 0700; archives and dump 0600):

  | File | Bytes | SHA-256 |
  | --- | --- | --- |
  | `onhost_staging_b-before-r2b.dump` (`pg_dump -Fc`, PostgreSQL 18) | 4404808 | `d0416f1554b74196064915a5a261d399f26a72727d47b02797b9ed9e5b0b13c3` |
  | `tree-e711b7c70f559276ebeb5d95dc7956874b6c8da4.tgz` (without `node_modules`) | 36133668 | `245b274a7680eaed7534ed8f033c59ceb7c437889576dfd84325d424b62c29df` |
  | `deploy-state-before-r2b.tgz` (state dir with `repo.git`, deployer, units, drop-ins, `/etc/onhost`, old script) | 15865766 | `624f83c2f49442b5374761ec37c4f63608abeccec1818d08b064e5a31f8ec03f` |
  | `onhost-staging.sh.e711b7c70f559276ebeb5d95dc7956874b6c8da4` | 19843 | `e1cd96b61eb913e22ad2a8cb54279c84f7fef70a6264661cfcc8a045b738ea11` |
  | `vhost-before-r2b.conf` (R2b-1 rollback copy) | 4721 | — |

  Also there: `SHA256SUMS` and `deployer-0f3cde26b8ef.log`.
* **R2b-3:** new `/root/onhost-staging.sh` from `0f3cde26` (SHA-256
  `ad577b004f0e697ff814e1d86d92cb4b882b0e650e005dc147c2917d1bc50337`). `check`: PHP 8.5.8 with every extension, FPM reload
  `/etc/init.d/php-fpm-85 reload`, Node v24.18.1, tools, `fs.protected_hardlinks=1`, `setpriv`/`setsid` to `www`, Redis
  `requirepass` set, PostgreSQL 18.0, the usranalyse line, three units active, `check passed`.
* **R2b-4:** deployer installed from `0f3cde26` (`/usr/local/sbin/onhost-deploy`, `/usr/local/lib/onhost-deploy/`), expected
  units = scheduler + queue@default + queue@mails, `expected-env` written, `app.env matches expected-env`. The script also
  left its installer copy `/root/install-deployer.sh`.

**Before R2b-5:** the target is pinned to `0f3cde26b8efc4c60b758fb4dc2b20f2733d0ea1` (the deployer came from it). Merging
this protocol moves the `development` tip; deploying a later tip means R2b-3/R2b-4 again. `expected-nonok` is still the
2026-09-28 snapshot, so the proposal's rc 5 risk (new doctor rows as `ROW-FAIL`, R2b-5a) stands.

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

### R2b-0 to R2b-4

```
R2b-0 $ curl … --resolve $SITE:443:127.0.0.1 https://$SITE/up        → 500
      $ grep -n 'enable-php-' $V                                      → 81:    include enable-php-83.conf;  (+ comment lines 15, 47)
      $ grep fastcgi_pass …/enable-php-85.conf                        → fastcgi_pass  unix:/tmp/php-cgi-85.sock;
      $ cut -d' ' -f1 $APP/VERSION; jq -r .sha last-good.json         → e711b7c7… (both); $S/releases: absent
      $ systemctl is-active (3 units)                                 → active active active
      $ $PGBIN/pg_dump --version; df -h /                             → 18.0; 266G free
R2b-1 $ grep -n 'enable-php-' $V                                      → 81:    include enable-php-85.conf;
      $ nginx -t && nginx reload                                      → syntax is ok, test is successful, rc=0
      $ curl … loopback /up; curl https://staging.onhost.cz/up        → 200; 200
      $ tail -3 access log                                            → GET /up 500 (R2b-0), GET /up 200
R2b-2 $ pg_dump / tar / tar / cp; echo rc                             → 0 0 0 0
      $ pg_restore -l | grep -c 'TABLE DATA'                          → 206
      $ tar -tzf tree | grep -c '^staging.onhost.cz/vendor/'          → 8522
      $ tar -tzf state | grep -c repo.git                             → 278
      $ sha256sum -c SHA256SUMS                                       → 4 × OK
R2b-3 $ git ls-remote … refs/heads/development                        → 0f3cde26b8efc4c60b758fb4dc2b20f2733d0ea1 (40)
      $ curl -fsSL …/0f3cde26…/infra/aapanel/staging.sh; bash -n; mv  → rc 0
      $ bash /root/onhost-staging.sh check                            → ✔ check passed, rc 0
R2b-4 $ bash /root/onhost-staging.sh deployer "$SHA"                  → deployer installed from 0f3cde26…, ✔ source-sha,
                                                                         ✔ expected-env written, ✔ app.env matches expected-env, rc 0
      $ cat /usr/local/lib/onhost-deploy/source-sha                   → 0f3cde26b8efc4c60b758fb4dc2b20f2733d0ea1
after $ VERSION; units; loopback /up                                  → e711b7c7…; active ×3; 200
```

(Sections R3 … R24 are added as the steps are run, in the same form. The owner's proposal for R3–R5 is
`GENERALKA-R3-R5-navrh.md` on the owner's desktop; after this run it waits for F2 (a current release on staging) and F3
(`/up` 500) — see *Run 2026-10-08* and *R2a*. The proposal that resolves both (vhost to PHP 8.5, backups, a gated deploy of
the development tip, rollback) is `GENERALKA-R2b-navrh.md` on the owner's desktop.)

## Closing paragraph (after the last step)

The doctor at R1 and R23, which rows moved, which steps were skipped and why, which rollback was used, the updated
`expected-nonok`, and the list of items that must go into the production plan
([go-live-checklist.md](go-live-checklist.md), section *Blocking for production*).
