# Staging launch — "production staging", phase 1 (TASK-0032)

**Status:** PREPARED, not executed. This page is the one ordered procedure for bringing `staging.onhost.cz` onto the
gated deployer and making it a faithful rehearsal of production **without touching live customers or live panels**.
The AI prepared it and runs nothing on any host, panel or vendor. A named operator executes every step on the host as
root; the owner gives the written decisions. Stop at the first failed verification.

What phase 1 proves: the release path (drain → maintenance → backup + verify → switch → build → gate → start → up), the
gate's refusals, rollback by SHA, a restore drill, the application's own flows without panels (sign-in, registration,
order and Comgate test payment up to *paid*, mail to a sink). What it does NOT prove: provisioning on panels — that is
phase 2, per panel, after the owner decides (question 3).

Constants used below (adjust if S0 finds other paths):

```bash
SITE=staging.onhost.cz
APP=/www/wwwroot/$SITE
P=/www/server/php/83/bin/php
STATE=/var/lib/onhost-deploy/$SITE
G="git -c safe.directory=$APP -c core.hooksPath=/dev/null -c core.fsmonitor=false -C $APP"   # read-only git as root before S1b
```

Every `$P artisan …` run as root writes logs as root: end each session on the host with
`chown -R www:www $APP/storage $APP/bootstrap/cache && chmod -R o-rwx $APP/storage $APP/bootstrap/cache` (the deployer does
this itself).

## A. Owner decisions (written, before S0 ends; a missing one is NO-GO)

| # | Decision | Recorded default until the owner answers |
| --- | --- | --- |
| O1 | Host: own host sharing no PostgreSQL, Redis, `/etc/onhost`, backup bucket or customer sites with production or the web nodes | the existing `staging.onhost.cz`, **contained** (S0 gate); nothing changes on it before the S0 read-only discovery |
| O2 | Live resources the existing staging created on ISPConfig, aaPanel, Pterodactyl (`docs/context/CURRENT_STATE.md`: every change was tried there against the real panels) | **contained indefinitely**: freeze, drained units, instances disabled, database kept. Retirement only through the platform's purge path (`onhost:services:purge`), one panel at a time with a written go-ahead — never by dropping the database, never by hand on a panel (historical resources the platform did not create are untouchable) |
| O3 | Data | keep the existing staging database, contained. An empty database only on a new host, or after O2 retirement. No copy of production or dev data |
| O4 | Credentials | **new** credentials: none, except the Comgate **test** merchant; no WEDOS or Fio production credentials; mail to the log or a sink; own Discord app or none; no pager key; no Hetzner ordering credentials. **Contained** credentials: the panel keys the existing staging already holds (ISPConfig, aaPanel, Pterodactyl, Proxmox, WEDOS — S0 lists them) are named here one by one (instance key, panel, who issued it) with a **revocation date**; they stay stored but unused (instance disabled) until then. Production never reuses any of them: it gets fresh keys issued for production only (question 13). Backup disk: local, with the root-only checksum copy in `$STATE/runs/*/backup.out` (or a staging-only S3 bucket) |
| O5 | Front door | basic auth on the whole vhost; loopback, `/up` and `/v1/webhooks/payments/<provider>` open; `X-Robots-Tag: noindex` |
| O6 | Four eyes | `ONHOST_FOUR_EYES=false`, recorded as a deliberate solo-owner choice in the release record |
| O7 | Maintenance window (503) | staging only. For production: sized from production's nightly `platform.backup` + `platform.backup.verify` durations (automation ledger) plus the build time measured in S7; before production has run a night, from the S7 measurement scaled by database size. No deploys 02:00–03:00 |
| O8 | Who creates and pushes tags | the owner only |
| O9 | Production signing key | the owner's SSH public key in `/var/lib/onhost-deploy/onhost.cz/allowed_signers`; until it exists every production deploy is refused |
| O10 | GATED doctor rows (`APP_URL uses https`, `queue driver`, `secrets driver`, `CA bundle for outbound TLS`, `metering gap ratchet`, `platform backup disk off the server`) | all of them stop a deploy; a row moves to report-only only by a change of `deploy-gate.php` |

### Owner questions (defaults taken meanwhile)

1. **Staging host:** does `staging.onhost.cz` run on its own host that shares no PostgreSQL, Redis, `/etc/onhost`, backup
   bucket or customer sites with production or the web nodes? *Default: treat it as the existing staging, contained, and
   do nothing on the host until the S0 read-only discovery has been done.*
2. **Live resources** the existing staging created on ISPConfig, aaPanel and Pterodactyl: retire through the platform's
   purge path with a separate go-ahead per panel, or keep contained indefinitely? *Default: contained. No panel calls, no
   database drop, no rebuild.*
3. **Phase 2 test panels:** per panel, a dedicated test panel/node, or writes limited to one new `onh_` test client?
   *Default: none. Provisioning stays frozen and no panel instance is active.*
4. **Staging credentials:** a WEDOS sub-login or none, no Fio token, the Comgate test merchant, Subreg demoreg or none,
   a separate Discord app? *Default: none, except the Comgate test merchant; mail goes to the log or a sink.*
5. **Staging backup disk:** a dedicated S3 bucket, or local? *Default: local, with a root-only copy of the checksums in
   `/var/lib/onhost-deploy`.*
6. **Maintenance window:** a 503 window of about the production nightly backup + verify duration plus the build time,
   with no deploys between 02:00 and 03:00? *Default: staging only; production is decided after S7 has measured it.*
7. **Production release signing:** which SSH public key goes into `allowed_signers`, and do you accept that production
   deploys are refused until it is there? *Default: production deploys are refused.*
8. **GATED rows:** keep all six as deploy-stopping rows, or move one to report-only? *Default: all of them gate.*
9. **Restore-drill freshness** before production go-live: is 7 days the right rule? *Default: one drill is required
   before go-live; the freshness rule stays a proposal.*
10. **Four eyes on staging:** two deciders, or `ONHOST_FOUR_EYES=false` recorded as a deliberate solo-owner choice?
    *Default: `ONHOST_FOUR_EYES=false` recorded in the release record.*
11. **Git access** of the host to `Stanektechcz/onhostik` (private repository?): who provides a read-only deploy key?
    *Default: the operator installs a read-only deploy key; no credential goes through the AI.*
12. **Comgate:** does it retry payment callbacks that got a 503 during the window? *Default: ASSUMED no; deploy outside
    business hours and reconcile payments by hand after each deploy.*
13. **Panel credentials the existing staging holds.** Staging has run against the real ISPConfig, aaPanel, Pterodactyl,
    Proxmox and WEDOS (`docs/context/CURRENT_STATE.md`); containment disables the instances but keeps their keys, and
    anyone holding the staging `APP_KEY` can decrypt them — on a host where a www→root path is still open (the
    deployer's follow-up). (a) Do you confirm that production gets **fresh** panel keys issued for production and never
    one of staging's? (b) When O2 is decided, will you revoke staging's keys **at each panel** yourself (the panel's own
    API-key/remote-user screen — nothing is sent by the platform or the AI), and by which date? *Default meanwhile: (a)
    yes, production keys are issued fresh; (b) the contained keys are listed in O4 by name with a revocation date of
    O2 decision + 14 days; a key past its date without an owner extension is NO-GO for staging (S10).*

## B. Preconditions (in the repository)

- PR #24 and TASK-0032 are merged into `development`; CI is green on the SHA to deploy (`STAGING_SHA`): `tests`
  (`pest`, `pest-postgres`, **`deploy-scripts`**) and `e2e`. Check with `gh run list --branch development --limit 3`.
- `/ai-release-check 2426c17..<STAGING_SHA>` wrote `.ai/releases/<date>-<sha7>.md` with verdict READY-for-staging. It
  records the sha256 of `infra/aapanel/{install.sh,deploy.sh,deploy-gate.php,install-deployer.sh}` at `STAGING_SHA`,
  the SHA the deployer is installed from (= `STAGING_SHA`), the migrations (`git diff --stat 2426c17..STAGING_SHA --
  database/migrations`) and the new env keys.
- The owner decisions of section A are recorded in that release record.

## C. Steps

### S0 — Discovery (read-only; answers the unknowns, changes nothing)

```bash
hostname; ls /www/wwwroot; ss -ltnp
ls -la /etc/onhost; systemctl list-units 'onhost-*' --all
git --version                                     # 2.32+ (production host: 2.34+ for SSH-signed tags)
curl --version | head -1
ss -ltnp | grep ':443'                            # nginx must answer on 127.0.0.1:443 (the deployer's HTTP gate)
stat -c '%U %a %n' $APP/.git $APP/.git/config
ls -l $APP/.env; stat -c '%U:%G %a %n' /etc/onhost /etc/onhost/app.env   # .env -> /etc/onhost/app.env; root:www 750/640
[ $APP/.env -ef /etc/onhost/app.env ] && echo same-file                  # the deployer refuses otherwise (S1b fixes it)
stat -c '%F %n' $APP/storage $APP/bootstrap $APP/bootstrap/cache           # directories, not symbolic links
nginx -T 2>/dev/null | grep -nE 'real_ip|set_real_ip_from|X-Forwarded-For|X-Real-IP'   # review: see S1
git config --file $APP/.git/config --list         # review: no foreign core.hooksPath, core.fsmonitor, url.*.insteadOf, filters
ls $APP/.git/hooks | grep -v '\.sample$'          # review: nothing expected
$G rev-parse HEAD; $G status --porcelain --untracked-files=all
command -v setpriv && setpriv --reuid=www --regid=www --init-groups id   # a fact for the follow-up (www execution)
grep -E 'opcache.validate_timestamps|opcache.revalidate_freq' /www/server/php/83/etc/php.ini; ls /etc/init.d | grep -i php-fpm
command -v pg_dump pg_restore psql; ls /www/server/pgsql/bin 2>/dev/null
$P -r 'echo ini_get("disable_functions"), PHP_EOL;'   # proc_open must not be listed
su - postgres -c "psql -c '\du'"                  # the app role has CREATEDB, or the postgres superuser is usable (S6)
cd $APP && $P artisan about --only=environment
$P artisan onhost:staging:report                  # WITHOUT --check: writes storage/app/onhost-staging-report.json, asks no panel
$P artisan onhost:doctor --json > /root/staging-doctor-s0.json
grep -n '"providers"' -A3 /root/staging-doctor-s0.json | grep 'credentials stored'   # instances holding credentials
$P artisan tinker --execute="dump(DB::table('users')->whereIn('email',['admin@onhost.cz','noc@onhost.cz','finance@onhost.cz','support@onhost.cz','demo@onhost.cz','agentura@onhost.cz'])->count(), DB::table('provider_instances')->get(['key','state']))"
```

Record: git version, owner/mode of `.git`, whether `.env` is `/etc/onhost/app.env`, the FPM reload command
(`/etc/init.d/php-fpm-83 reload` is ASSUMED), opcache settings, the pg binary directory (`ONHOST_PG_BIN`), the
instances and their state, the dev-account count, and every provider row whose detail says *credentials stored* — that
list, by instance key, goes into O4 with the revocation date of question 13.

**GATE S0.** If any panel instance is active or holds credentials, or live-panel bindings exist, contain first (owner
go-ahead; staging only; nothing is sent to any panel):

```bash
cd $APP
$P artisan onhost:provisioning:freeze "staging containment"
systemctl stop onhost-scheduler.service 'onhost-queue@*'
# staff console → Integrations: set every panel instance to state "disabled" (keeps its bindings and credentials unused)
install -d -m 0700 $STATE && touch $STATE/expect-freeze   # the deployer re-asserts the freeze on every staging release
# KEEP the database. Report the inventory (staging report + doctor JSON) to the owner for O2.
```

Verify: `onhost:staging:report` again shows no instance `active`; `systemctl list-units 'onhost-*'` shows the units
inactive. The freeze is secondary (cache-based, it can be lost with the cache): the primary controls are *no active
instance* and *no credentials beyond O4*, checked daily in S10.

### S1 — Front door

Paste the **staging block** of `infra/aapanel/nginx-site.conf` into the vhost (basic-auth realm switched off for
`127.0.0.1`, `::1`, `/up` and `/v1/webhooks/payments/<provider>`; `X-Robots-Tag: noindex`), create the password file,
`nginx -t && nginx -s reload`.

Verify:

```bash
curl -sI https://$SITE/ | head -1                                  # from OUTSIDE the host: 401
curl -s -o /dev/null -w '%{http_code}\n' https://$SITE/up          # from outside: 200 (503 while in maintenance)
curl -sI https://$SITE/ | grep -i x-robots-tag                     # noindex, nofollow
curl -s -o /dev/null -w '%{http_code}\n' --resolve $SITE:443:127.0.0.1 https://$SITE/v1/status   # ON the host: 200, not 401
# the loopback exemption keys on $remote_addr: no header may turn an outside client into 127.0.0.1
nginx -T 2>/dev/null | grep -nE 'real_ip_header|set_real_ip_from'   # ON the host: nothing, or set_real_ip_from ONLY for
                                                                     # a proxy you run (never 0.0.0.0/0, ::/0 or a CDN range)
curl -sI -H 'X-Forwarded-For: 127.0.0.1' https://$SITE/ | head -1   # from OUTSIDE: 401
curl -sI -H 'X-Real-IP: 127.0.0.1' https://$SITE/ | head -1         # from OUTSIDE: 401
```

If any of the last two answers anything but 401, or `set_real_ip_from` trusts a range you do not control: STOP — the
basic auth can be skipped with one header. Remove the `real_ip` setting (or narrow it to your own proxy) and re-verify.

### S1b — One-time git hardening (after the S0 review found nothing foreign)

```bash
chown -R root:root $APP/.git && chmod -R go-w $APP/.git && chmod 700 $APP/.git
install -d -m 0700 $STATE
printf '[safe]\n\tdirectory = %s\n' "$APP" > $STATE/gitconfig && chmod 600 $STATE/gitconfig   # the deployer also creates it
chown root:www /etc/onhost /etc/onhost/app.env && chmod 750 /etc/onhost && chmod 640 /etc/onhost/app.env
# only if S0 found .env NOT to be /etc/onhost/app.env — keep a copy, compare, and link only when nothing differs:
install -m 0600 $APP/.env /root/env-before-s1b
diff $APP/.env /etc/onhost/app.env && ln -sfn /etc/onhost/app.env $APP/.env   # a difference: STOP, the owner decides
                                                                              # which values app.env takes
```

The deployer decides production from `/etc/onhost/app.env` (root-owned) and refuses when `$APP/.env` is not that very
file, or when `storage`, `bootstrap` or `bootstrap/cache` is a symbolic link.

The host's read-only deploy key (question 11) is the operator's; it never passes through the AI.

### S2 — Fresh install (ONLY a new host, or after O2 retirement; an existing staging skips S2 and S4)

```bash
curl -fsSL "https://raw.githubusercontent.com/Stanektechcz/onhostik/<STAGING_SHA>/infra/aapanel/install.sh" -o /root/onhost-install.sh
sha256sum /root/onhost-install.sh                  # = the value in the release record, else STOP
REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> START_UNITS=0 bash /root/onhost-install.sh   # clones, writes /etc/onhost/app.env, stops
```

### S3 — `app.env` isolation (new host: fill; existing staging: review)

Keys from `.env.example`: `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://staging.onhost.cz`; `MAIL_MAILER=log` or SMTP
to a sink; `COMGATE_TEST=true` with the test merchant; `WEDOS_TEST_MODE=true`; the Let's Encrypt staging directory in
`ONHOST_ACME_DIRECTORY`; empty `ONHOST_BANK_FIO_TOKEN` and `POWERDNS_HIDDEN01_URL`; `ONHOST_FOUR_EYES` per O6;
`ONHOST_PLATFORM_BACKUP_DISK` per O4; `QUEUE_CONNECTION=redis`; `ONHOST_SECRETS_DRIVER=db`; a distinct `REDIS_PREFIX` if Redis
is ever shared; Turnstile test keys.

```bash
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|MAIL_MAILER|COMGATE_TEST|WEDOS_TEST_MODE|ONHOST_ACME_DIRECTORY|ONHOST_FOUR_EYES|ONHOST_PLATFORM_BACKUP_DISK|QUEUE_CONNECTION|ONHOST_SECRETS_DRIVER)=' /etc/onhost/app.env
grep -cE '^(ONHOST_BANK_FIO_TOKEN|POWERDNS_HIDDEN01_URL)=.+' /etc/onhost/app.env    # 0
```

### S4 — Second install run (new host only)

```bash
REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> START_UNITS=0 bash /root/onhost-install.sh
cd $APP && $P artisan onhost:provisioning:freeze "staging phase 1 - no panels"
touch $STATE/expect-freeze
# staff console → Integrations: wedos-main (created by InfrastructureSeeder) → state "disabled"
```

Verify: `$STATE/installed` exists; the doctor JSON has no *credentials stored* row beyond the O4 list and no `active`
instance beyond it; `stat -c '%a %U %n' $APP/storage /etc/onhost /etc/onhost/app.env` = storage `o-rwx` www, `750`/`640`
root:www. Only then start the units: `systemctl start onhost-scheduler.service 'onhost-queue@default.service'
'onhost-queue@mails.service'` (the provider queues stay stopped in phase 1).

### S4b — Install the gated deployer (both paths)

```bash
GIT_CONFIG_GLOBAL=$STATE/gitconfig git -C $APP fetch --tags origin
GIT_CONFIG_GLOBAL=$STATE/gitconfig git -C $APP show <STAGING_SHA>:infra/aapanel/install-deployer.sh > /root/install-deployer.sh
sha256sum /root/install-deployer.sh                 # = the release record
SHA=<STAGING_SHA> FIRST=1 bash /root/install-deployer.sh   # FIRST=1 only where no deployer is installed yet (refused
                                                             # otherwise); a later upgrade: SHA=<newer> bash … (forward only)
cat /usr/local/lib/onhost-deploy/source-sha         # = STAGING_SHA
```

### S5 — Staff

```bash
$P artisan onhost:staff:create <owner-email> --name="<name>" --role=platform_owner   # hidden password prompt
```

Each person signs in and enrols MFA themselves. **Never run `DevAccountSeeder` here.** A second approver only per O6.
Verify: the S0 tinker query shows 0 development accounts (an existing staging that has them: disable them in the staff
console and report — do not delete).

### S7 — First gated deploy

On an existing staging start the units that belong to phase 1 first (S4 list) so the drain is exercised.

```bash
REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<reload command from S0>' \
  /usr/local/sbin/onhost-deploy; echo rc=$?
```

Verify:

- `rc=0`; `cat $APP/VERSION` = `STAGING_SHA STAGING_SHA`; `cat $STATE/last-good.json` has `sha` = STAGING_SHA.
- `tail -1 $STATE/deploy.log`: operator, `drain_s`, `window_s`, `set=platform-backups/…`, `override=""`.
- Write down the set and the run directory of THIS release for the restore drill (S6 runs after S8, whose failing
  rehearsals append lines with `set=-`):
  `grep ' rc=0 set=platform-backups/' $STATE/deploy.log | tail -1 | tee /root/staging-s7-release.txt`
- `$STATE/runs/<ts>-<sha12>/verdict.out` ends with `VERDICT pass` and lists no `HARD-FAIL`/`GATED-FAIL`; `report.json` has
  every HARD and GATED row `OK`.
- The units listed in `drained-units` before the run are active again; units stopped for containment stay stopped.
- `$P artisan onhost:doctor` six minutes or more later: the liveness rows (scheduler, worker) OK for the running units.
- Record `drain_s` and `window_s` next to the backup duration in `backup.out` (O7).

### S8 — Negative rehearsals (staging only; each ends recovered)

| | Do | Expect | Recover |
| --- | --- | --- | --- |
| a | `APP_DEBUG=true` in `app.env`, run S7's command | rc 5 (HARD); the site stays 503; units stay stopped; `storage/framework/down` has `"secret":null` | `APP_DEBUG=false`, run the printed command → rc 0 |
| b | switch the rule `platform.backup` off (staff console → Automations), run | rc 3; `$G rev-parse HEAD` unchanged; `/up` 200; units started again | switch the rule on |
| c | `QUEUE_CONNECTION=sync`, run | rc 5 (GATED `storage|queue driver`); a plain re-run → rc 5 again (no laundering); re-run with `ALLOW_DOCTOR_FAIL="<first 12 of STAGING_SHA>:staging override rehearsal"` → rc 0, override logged | `QUEUE_CONNECTION=redis`, deploy → rc 0 |
| d | deploy the next SHA (TASK-0032+1), then `REF=<STAGING_SHA>` | rc 0 both; the rollback over a migration takes a backup | roll forward → rc 0. Only targets that contain the gate |
| e | change a tracked file (`touch -d yesterday` is not enough: edit it), run | rc 2, no `down` in the output | `$G checkout -- <file>` |
| f | `$P artisan down` by hand, run | rc 0; the site stays down ("left down") | `$P artisan up` |
| g | the drain timeout with a synthetic job (below): `DRAIN_TIMEOUT=20` while a 60 s job runs | rc 3 after about 20 s; nothing switched; the site 200; the drill unit comes back **by itself** once its job ends (systemd queued the start behind the pending stop), and the job ran exactly once | stop the drill unit |
| h | a unit that cannot come back: `systemctl start onhost-queue@deploy-drill.service && systemctl mask --runtime onhost-queue@deploy-drill.service`, run | rc 7 (`did not come back: onhost-queue@deploy-drill.service`); the site stays 503; every drained unit stopped again and listed in `drained-units` | `systemctl unmask --runtime onhost-queue@deploy-drill.service`, run the printed command → rc 0, then `systemctl stop onhost-queue@deploy-drill.service` |

Rehearsal g — mandatory, because it proves two assumptions of section G that a quiet staging never hits: the deployer
gives up cleanly when a job outlives `DRAIN_TIMEOUT`, and systemd starts a unit whose stop was still pending. The job
is a closure that only sleeps and logs; it runs on its own queue, served by a throw-away instance of the worker template
(the drain treats every `onhost-queue@*` unit alike). 60 s stays under the queue's `retry_after` (90 s unless
`REDIS_QUEUE_RETRY_AFTER` says otherwise — check it in `app.env`), so it is never handed out twice.

```bash
cd $APP
systemctl start onhost-queue@deploy-drill.service
$P artisan tinker --execute="dispatch(function () { sleep(60); \Illuminate\Support\Facades\Log::info('deploy-drill job done'); })->onQueue('deploy-drill');"
sleep 5; systemctl is-active onhost-queue@deploy-drill.service     # active, the job is running
DRAIN_TIMEOUT=20 REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<…>' \
  /usr/local/sbin/onhost-deploy; echo rc=$?                        # rc=3, "still running after 20s: … deploy-drill"
$G rev-parse HEAD                                                  # unchanged
systemctl status onhost-queue@deploy-drill.service | head -3       # deactivating (stop-sigterm) → then active again
sleep 60; systemctl is-active onhost-queue@deploy-drill.service    # active: the queued start ran after the job
grep -c 'deploy-drill job done' storage/logs/*.log | awk -F: '{s+=$2} END {print s}'   # 1
systemctl stop onhost-queue@deploy-drill.service
chown -R www:www storage bootstrap/cache && chmod -R o-rwx storage bootstrap/cache   # tinker ran as root
```

Record the observed stop duration next to `DRAIN_TIMEOUT` (300 s default) and the longest job the production queues
run (the `--timeout=900` worker limit): if production jobs regularly run longer than the window allows, the owner raises
`DRAIN_TIMEOUT` or accepts rc 3 retries.

Verify: `deploy.log` holds a line for every attempt with the expected `rc` and `stage`.

### S9 — Functional smoke without panels (provisioning stays frozen)

`GET /v1/status` 200 (through the basic auth); staff sign-in with MFA; a customer registration through Turnstile; an
order paid with the Comgate test merchant up to *paid* — its operations stay held by the freeze and nothing reaches a
panel; mail appears only in the log or sink; optionally `onhost:vat:verify` without `--apply`.
**Do NOT run `onhost:smoke:order`, `onhost:nodes:discover` or `onhost:integrations:secret … --check`**: they reach live panels.

### S6 — Restore drill (after S9, or on the contained database)

```bash
REL=$(cat /root/staging-s7-release.txt 2>/dev/null || grep ' rc=0 set=platform-backups/' $STATE/deploy.log | tail -1)
SET=$(printf '%s\n' "$REL" | sed -E 's/.* set=([^ ]+) .*/\1/')         # the set of the last SUCCESSFUL release, never
RUN=$(printf '%s\n' "$REL" | sed -E 's/.* run=([^ ]+)$/\1/')            # an S8 attempt (those log set=-)
case "$SET" in platform-backups/*) ;; *) echo "no released set found: STOP"; false ;; esac
PGBIN=/www/server/pgsql/bin                                                 # the directory S0 found (ONHOST_PG_BIN)
sha256sum $APP/storage/app/private/$SET/database.pgdump | cut -c1-16      # = the prefix in $RUN/backup.out (local disk)
D=$(mktemp -d) && chown postgres $D && install -o postgres -m 0600 $APP/storage/app/private/$SET/database.pgdump $D/db.pgdump
su - postgres -c "$PGBIN/createdb onhost_restore_drill"
time su - postgres -c "$PGBIN/pg_restore --no-owner --no-privileges -d onhost_restore_drill $D/db.pgdump"; echo rc=$?   # rc=0
for t in plans tax_rule_versions orders invoices users services; do
  printf '%s ' $t; su - postgres -c "$PGBIN/psql -tA -d onhost_staging -c 'select count(*) from $t'"; su - postgres -c "$PGBIN/psql -tA -d onhost_restore_drill -c 'select count(*) from $t'"
done                                                                        # each pair equal; plans, tax_rule_versions non-empty
su - postgres -c "$PGBIN/dropdb onhost_restore_drill"; rm -r "$D"
```

(Table names are from the migrations; the set path assumes the local backup disk — adjust for S3 per `ONHOST_PLATFORM_BACKUP_DISK`.) Record
the duration. The 7-day freshness rule is a proposal (question 9), not a gate.

### S10 — Daily, for 24 h, then with every release

- the freeze is on (`$P artisan tinker --execute="dump(app(Onhost\Domain\Provisioning\FreezeSwitch::class)->meta())"` prints the
  reason, `null` = not frozen; the staff console shows a banner);
- no `active` instance and no *credentials stored* row beyond O4 (`onhost:staging:report`, without `--check`); every
  contained credential O4 names is still before its revocation date (question 13) — one past it without the owner's
  written extension is NO-GO;
- `find $APP/storage/logs ! -user www` prints nothing;
- mail only in the sink; no provider calls in the logs (`grep -il 'ispconfig\|aapanel\|pterodactyl\|proxmox' storage/logs/*`).

## D. Go / no-go for "staging phase 1 live" (all must hold)

- O1–O10 and question 13 recorded; the release record lists the evidence below.
- O4 names every credential the staging database still holds (S0's *credentials stored* rows), each with its
  revocation date, and the owner confirmed that production gets fresh panel keys (question 13 a). "No credentials
  beyond O4" is judged against that list — contained keys count as listed, not as absent.
- S1: 401 from outside (also with `X-Forwarded-For: 127.0.0.1` and `X-Real-IP: 127.0.0.1`), no `set_real_ip_from`
  trusting a range the operator does not control, 200 for `/v1/status` over loopback; `.git` root-owned;
  `$APP/.env` is `/etc/onhost/app.env`; the deployer installed from `STAGING_SHA`.
- S7: rc 0 with every HARD and GATED row OK; S8 (a)–(h) gave their expected rc and recovered — (g) and (h) are not
  optional.
- The restore drill passed, with the checksum compared first.
- `drain_s`, `window_s` and the backup duration are recorded next to the production nightly backup duration (or the
  S7-based estimate while production has not run).
- The containment state matches the O2 decision: no active panel instance, no credentials beyond the O4 list (none
  past its revocation date), freeze on.
- Anything else is NO-GO. Phase 2 (test panels) has its own go/no-go after the owner's per-panel decision.

## E. Rollback

- A failed release: run the command the deployer printed (last good SHA, the backup is taken, not skipped); verify rc 0
  and `/up` 200.
- A staging database rebuild only after O2 retirement.
- A database restore only with the owner, after a fresh backup, with the units stopped, the checksum compared first
  (`docs/runbooks/release-and-rollback.md` § Rollback).
- Never touch a panel as part of a rollback.

## F. Promotion to production (outline; no AI action)

- Only a SHA that passed S7–S10.
- The owner creates and signs the annotated tag `vYYYY.MM.DD[-N]` with `Release-Record:` and `Verdict: READY`, plus an
  `Accept-Gate:` line only for a GATED row he accepts (`.ai/releases/README.md`).
- On the production host: `allowed_signers` with the owner's key (root, 0600), git 2.34+, the deployer installed from
  the same signed tag (`SHA=<sha> TAG=<tag> bash install-deployer.sh` — in production the installer refuses a SHA
  without the owner's signed tag, and `FIRST=1` on a host that already has a deployer), then
  `REF=<tag> EXPECTED_SHA=<sha> DEPLOY_OPERATOR=<name> /usr/local/sbin/onhost-deploy`.
- Production panel instances get keys issued for production (question 13); none of staging's keys is copied over.
- A separate production runbook review (go-live checklist) precedes it.

## G. Assumptions to check on the host (NOT verified from the repository)

git ≥ 2.32 (GIT_CONFIG_GLOBAL) and ≥ 2.34 on production (SSH `verify-tag`); nginx answers on `127.0.0.1:443`; the staging
nginx block behaves as written and no `real_ip` setting trusts a foreign range (S1 proves it); the FPM reload command;
`DRAIN_TIMEOUT=300` s is enough (a worker job may run 900 s; the drain then fails with rc 3 and nothing changes — S8 g
proves the rc 3 path, not production's job lengths); systemd queues a `start` issued behind a pending `stop` (S8 g);
`systemctl is-active` shows a unit that crashes on boot within `UNIT_SETTLE` (5 s) (S8 h proves the refusal path);
Laravel's real environment overrides `.env` (the deployer compares the doctor's environment with its own reading either
way); `PGOPTIONS` reaches `pdo_pgsql` through libpq; the pg binary directory; Comgate's retry behaviour after a 503.
