# Staging launch — "production staging", phase 1 (TASK-0032)

**Status:** PREPARED, not executed. This page is the one ordered procedure for bringing `staging.onhost.cz` onto the
gated deployer and making it a faithful rehearsal of production **without touching live customers or live panels**.
The AI prepared it and runs nothing on any host, panel or vendor. A named operator executes every step on the host as
root; the owner gives the written decisions. Stop at the first failed verification.

**Revised after the pre-mortem of 2026-09-27** (`.ai/tasks/TASK-0032.md` § Pre-mortem). The earlier text called the
instance state `disabled` the primary control. It is not one: nothing on the system path reads it (`ProviderRegistry`
hands out an adapter with the stored credentials whatever the state, `OperationRunner` never asks, `ServiceService`
applies it to customers only, and the backup scheduler prunes backups on the panels inline, as the system). Containment
is now: the scheduler and the provider lanes cannot start (disabled and masked), every stored live credential revoked at
its issuer, customer-named destinations denied (`ONHOST_EGRESS_DENY_CIDRS`), the environment asserted by the deployer on
every release. The freeze stays, as a signal, not as a control.

## Two paths — the owner chooses (question 1)

| | **Path A — the existing staging, contained** | **Path B — a fresh install** (recommended) |
| --- | --- | --- |
| Database | the kept staging database (live-panel bindings, maybe real addresses, webhooks, queued mail) | empty, seeded by `install.sh`; the old staging database stays untouched on its host |
| Scheduler | **never** (it would back up, prune, purge, renew certificates, sync DNS, relay the outbox on live data) | runs (no live credential exists) |
| Queue lanes | `default`, `mails` — only after every stored credential is revoked | `default`, `mails` |
| Proves | the release path, the gate and its refusals, rollback by SHA, a restore drill, the web flows without panels | all of Path A **plus** a fresh `install.sh` run, the scheduler, nightly backup + verify under systemd, the nightly billing jobs running |
| Does NOT prove | `install.sh`, anything the scheduler does, nightly jobs, mail leaving to a sink by the scheduler | provisioning on panels (phase 2), the production values of the keys in the S3 table |

Production promotion (F) needs **Path B** evidence. Path A alone gives a staging GO for the release path only.

Constants used below (adjust if S0 finds other paths):

```bash
SITE=staging.onhost.cz
APP=/www/wwwroot/$SITE
P=/www/server/php/83/bin/php
STATE=/var/lib/onhost-deploy/$SITE
DG=/usr/local/lib/onhost-deploy/deploy-gate.php   # the installed judge (S4b); before it exists: /root/deploy-gate.php (S0)
G="git -c safe.directory=$APP -c core.hooksPath=/dev/null -c core.fsmonitor=false -C $APP"   # read-only git as root before S1b
PROVIDER_LANES="provider-pterodactyl provider-aapanel provider-ispconfig provider-proxmox provider-powerdns provider-registrar provider-kubernetes"
# artisan by hand runs as www wherever setpriv works: www can write this tree (on an existing staging all of it), so
# root running its PHP is a www → root path (D32.13, open). AS_WWW empty = setpriv unusable: see S0.
if setpriv --reuid=www --regid=www --init-groups true 2>/dev/null; then AS_WWW="setpriv --reuid=www --regid=www --init-groups --"; else AS_WWW=""; fi
# storage and bootstrap/cache back to www after anything root ran — never `chown -R`/`chmod -R` (they follow a symlink
# given on the command line, and www can plant one); the deployer's own guarded sequence:
own() {
  local d; for d in storage bootstrap bootstrap/cache; do
    [ ! -L "$APP/$d" ] && [ -d "$APP/$d" ] && [ "$(realpath "$APP/$d")" = "$(realpath "$APP")/$d" ] || { echo "REFUSED: $APP/$d is a link or missing — report it"; return 1; }
  done
  find -P $APP/storage $APP/bootstrap/cache -exec chown -h www:www {} + && find -P $APP/storage $APP/bootstrap/cache ! -type l -exec chmod u+rwX,g+rX,o-rwx {} +
}
# a database count as the postgres superuser ($1 = database, $2 = table) — read-only
cnt() { su - postgres -c "psql -tA -d $1 -c 'select count(*) from $2'"; }
```

Every `$P artisan …` that ran as root (no setpriv) writes logs as root: end each such session on the host with `own`
(the deployer does this itself; on an installed site `INSTALL_REPAIR=1 bash /root/onhost-install.sh` does the same and
re-renders the units without enabling or starting any).

## A. Owner decisions (written, before S0 ends; a missing one is NO-GO)

| # | Decision | Recorded default until the owner answers |
| --- | --- | --- |
| O1 | Path and host: A (existing staging, contained) or B (fresh install on a host — or a new database on the staging host — sharing no PostgreSQL database, Redis server/prefix, `/etc/onhost`, backup bucket or customer sites with production or the web nodes). Isolation is **proven** in S0, not assumed | nothing changes on the host before the S0 read-only discovery; recommendation: **Path B** |
| O2 | Live resources the existing staging created on ISPConfig, aaPanel, Pterodactyl (`docs/context/CURRENT_STATE.md`: every change was tried there against the real panels) | **contained indefinitely**: scheduler and provider lanes disabled + masked, every stored credential revoked at its issuer (O4), database kept. Retirement only through the platform's purge path (`onhost:services:purge`), one panel at a time with a written go-ahead and a key issued for that purpose — never by dropping the database, never by hand on a panel (historical resources the platform did not create are untouchable) |
| O3 | Data | Path A: keep the database, contained; its backlog (unpublished outbox, active webhook endpoints, queued mail) is counted in S0 and cannot leave (egress denied, mail to the log). Path B: empty. Never a copy of production or dev data |
| O4 | Credentials | **new**: none, except the Comgate **test** merchant (`COMGATE_TEST=true`, `COMGATE_RECURRING=false`); no WEDOS or Fio production credentials; mail to the log or a sink; own Discord app or none; no pager key; no Hetzner ordering credentials; no CDN token. **Stored** (Path A): every *credentials stored* row of S0 — panels, DNS, registrars, CDN, Discord, the Comgate secret ref — named by instance key/ref and **revoked at its issuer by the owner before S5** (question 13). Production never reuses any of them. Backup disk: a staging-only S3 bucket (question 5), or local with the root-only checksum copy in `$STATE/runs/*/backup.out` |
| O5 | Front door | basic auth on the whole vhost; loopback, `/up` and `/v1/webhooks/payments/<provider>` open; `X-Robots-Tag: noindex` |
| O6 | Four eyes | `ONHOST_FOUR_EYES=false`, recorded as a deliberate solo-owner choice in the release record |
| O7 | Maintenance window (503) | staging only. For production: sized from production's nightly `platform.backup` + `platform.backup.verify` durations (automation ledger) plus the build time measured in S7; before production has run a night, from the S7 measurement scaled by database size. No deploys 02:00–03:00 |
| O8 | Who creates and pushes tags | the owner only |
| O9 | Production signing key | the owner's SSH public key in `/var/lib/onhost-deploy/onhost.cz/allowed_signers`; until it exists every production deploy is refused |
| O10 | GATED doctor rows (`APP_URL uses https`, `queue driver`, `secrets driver`, `CA bundle for outbound TLS`, `metering gap ratchet`, `platform backup disk off the server`) | all of them stop a deploy; a row moves to report-only only by a change of `deploy-gate.php`. In production every other FAIL row stops it too, unless the signed tag has an `Accept-Gate:` line for it |
| O11 | Doctor rows expected non-OK on staging (`$STATE/expected-nonok`) | the list in the release record, each row with the reason it is non-OK on staging (S4b drafts it); a row not on it stops a staging release |
| O12 | Units that must run after every release (`$STATE/expected-units`) | Path A: `onhost-queue@default.service`, `onhost-queue@mails.service` (only after question 13 b is done; before that: none). Path B: those two plus `onhost-scheduler.service`. The provider lanes never in phase 1 |

### Owner questions (defaults taken meanwhile)

1. **Path and host:** Path A or Path B (table above)? For B: a new host, or a new database on the staging host with the
   old one left untouched? *Default: nothing runs on the host beyond S0 until answered; recommendation B — only B
   rehearses `install.sh` and the scheduler, and it holds no live credential.*
2. **Live resources** the existing staging created on ISPConfig, aaPanel and Pterodactyl: retire through the platform's
   purge path with a separate go-ahead per panel, or keep contained indefinitely? *Default: contained. No panel calls, no
   database drop, no rebuild.*
3. **Phase 2 test panels:** per panel, a dedicated test panel/node, or writes limited to one new `onh_` test client?
   *Default: none. Provisioning stays frozen and no provider lane runs.*
4. **Staging credentials:** a WEDOS sub-login or none, no Fio token, the Comgate test merchant, Subreg demoreg or none,
   a separate Discord app? *Default: none, except the Comgate test merchant; mail goes to the log or a sink.*
5. **Staging backup disk:** a dedicated staging-only S3 bucket, or local? *Recommendation: the bucket — with the local
   disk the GATED row `platform backup disk off the server` is OK by definition outside production, so production's
   first S3 backup and verify would run inside its first maintenance window. Default: local.*
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
13. **Credentials the existing staging holds (Path A).** Staging has run against the real ISPConfig, aaPanel,
    Pterodactyl, Proxmox and WEDOS (`docs/context/CURRENT_STATE.md`), and its database may hold DNS, CDN, Discord and
    Comgate secrets too; anyone with the staging `APP_KEY` can decrypt them, and nothing on the system path honours the
    instance state `disabled`. (a) Do you confirm that production gets **fresh** keys issued for production and never
    one of staging's? (b) Will you revoke each key S0 lists **at its issuer** yourself (the panel's own API-key /
    remote-user screen, the registrar's or Cloudflare's token page — nothing is sent by the platform or the AI) **before
    S5**, and confirm it in writing? A key shared with the development machine's local instances stops working there
    too. *Default: (a) yes; (b) until confirmed, Path A stops after S1b — no unit starts, no S5–S10.*
14. **Production's planned values** for `DB_HOST`/`DB_DATABASE`, `REDIS_HOST`/`REDIS_PREFIX`/`CACHE_PREFIX`,
    `AWS_BUCKET`/`AWS_ENDPOINT`, PostgreSQL major version and collation: the S3 spec refuses staging values equal to
    them, and the release record's staging-vs-production table compares them. *Default: unanswered = the spec stays
    UNFILLED and the deployer refuses (S3).*

## B. Preconditions (in the repository)

- PR #24 and TASK-0032 are merged into `development`; CI is green on the SHA to deploy (`STAGING_SHA`): `tests`
  (`pest`, `pest-postgres`, **`deploy-scripts`**) and `e2e`. Check with `gh run list --branch development --limit 3`.
- `/ai-release-check 2426c17..<STAGING_SHA>` wrote `.ai/releases/<date>-<sha7>.md` with verdict READY-for-staging. It
  records the sha256 of `infra/aapanel/{install.sh,deploy.sh,deploy-gate.php,install-deployer.sh}` at `STAGING_SHA`,
  the SHA the deployer is installed from (= `STAGING_SHA`), the migrations and their mechanical safety check, the new
  env keys, the staging-vs-production value table and the expected non-OK doctor rows (O11).
- The owner decisions of section A are recorded in that release record.

## C. Steps

### S0 — Discovery (read-only; answers the unknowns, changes nothing on the site)

```bash
hostname; ls /www/wwwroot; ss -ltnp
ls -la /etc/onhost; systemctl list-units --all 'onhost-*'; systemctl list-unit-files 'onhost-*'
ls /etc/systemd/system/*.wants/ 2>/dev/null | grep onhost-                        # what a reboot would start
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
command -v pg_dump pg_restore psql; ls /www/server/pgsql/bin 2>/dev/null; pg_dump --version
$P -r 'echo ini_get("disable_functions"), PHP_EOL;'   # proc_open must not be listed
su - postgres -c "psql -c '\du'"                  # the app role has CREATEDB, or the postgres superuser is usable (S6)
```

**Isolation and parity** (pre-mortem: `REDIS_PREFIX` defaults to `slug(APP_NAME)-database-`, the same as production's
unless set; with a shared Redis, staging workers would pop production's jobs, share the scheduler's `onOneServer` lock,
and a staging freeze would freeze production). Only non-secret keys are printed:

```bash
grep -E '^(APP_NAME|APP_ENV|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|REDIS_HOST|REDIS_PORT|REDIS_DB|REDIS_CACHE_DB|REDIS_PREFIX|CACHE_STORE|CACHE_PREFIX|SESSION_DRIVER|SESSION_DOMAIN|QUEUE_CONNECTION|AWS_BUCKET|AWS_ENDPOINT|ONHOST_PLATFORM_BACKUP_DISK|ONHOST_ARCHIVE_DISK)=' /etc/onhost/app.env
getent hosts $(grep -E '^(DB_HOST|REDIS_HOST)=' /etc/onhost/app.env | cut -d= -f2 | tr -d '"')   # which machines they are
su - postgres -c "psql -tAc 'show server_version' -c 'show TimeZone' -c \"select datname, datcollate, datctype from pg_database where datname not like 'template%'\""
```

The owner confirms in writing (release record) that no host, database, Redis server + prefix, cache prefix, bucket or
endpoint printed here is production's or a web node's. **Any shared one is NO-GO before S1.** A missing `REDIS_PREFIX`
or `CACHE_PREFIX` counts as shared unless the Redis server itself is staging's alone. Record the PostgreSQL version,
collation, time zone and the `pg_dump` version next to production's planned values (question 14; CI tests on
PostgreSQL 16).

Then the application's view, as www (`$AS_WWW`), read-only:

```bash
echo "AS_WWW=${AS_WWW:-<empty: setpriv unusable>}"  # empty: STOP before the artisan lines below — see the note after this block
cd $APP && $AS_WWW $P artisan about --only=environment
$AS_WWW $P artisan onhost:staging:report          # WITHOUT --check: writes storage/app/onhost-staging-report.json, asks no panel
$AS_WWW $P artisan onhost:doctor --json > /root/staging-doctor-s0.json     # reads stored state; no provider call (DeployGateTest (b) runs it with stray HTTP forbidden)
$AS_WWW $P artisan tinker --execute="dump(DB::table('users')->whereIn('email',['admin@onhost.cz','noc@onhost.cz','finance@onhost.cz','support@onhost.cz','demo@onhost.cz','agentura@onhost.cz'])->count())"
# every instance, whatever its provider (panels, DNS, registrars, CDN) and whether a credential is stored — the O4 list
$AS_WWW $P artisan tinker --execute="foreach (Onhost\Domain\Provisioning\Models\ProviderInstance::query()->orderBy('provider')->get() as \$i) { echo \$i->key, ' ', \$i->provider, ' ', \$i->state, ' secret=', app(Onhost\Platform\Secrets\SecretStore::class)->exists(\$i->secretRef()) ? 'stored' : 'none', PHP_EOL; }"
grep -E '^[A-Z_]*SECRET_REF=' /etc/onhost/app.env | cut -d= -f1,2        # refs only; the secrets are in the database
# what could move by itself: non-terminal operations, the relay backlog, customer webhooks, queued mail, queue lengths
$AS_WWW $P artisan tinker --execute="dump(DB::table('operations')->whereNotIn('state',['succeeded','failed','cancelled'])->selectRaw('state, queue, count(*) n')->groupBy('state','queue')->get(), ['outbox_unpublished' => DB::table('outbox_messages')->whereNull('published_at')->count(), 'webhook_endpoints_active' => DB::table('webhook_endpoints')->where('state','active')->count(), 'webhook_deliveries_open' => DB::table('webhook_deliveries')->whereIn('state',['pending','failed'])->count(), 'mail_queued' => DB::table('mail_outbox')->where('state','queued')->count()])"
$AS_WWW $P artisan tinker --execute="foreach (array_merge(['default','mails'], array_map(fn (\$q) => 'provider-'.\$q, ['pterodactyl','aapanel','ispconfig','proxmox','powerdns','registrar','kubernetes'])) as \$q) { echo \$q, ' ', Illuminate\Support\Facades\Queue::size(\$q), PHP_EOL; }"
# the switches that live in the database (default-off rules included): recorded now and again after S7
$AS_WWW $P artisan tinker --execute="echo json_encode(array_map(fn (\$r) => [\$r['key'] ?? null, \$r['enabled'] ?? null], app(Onhost\Domain\Provisioning\AutomationLedger::class)->overview()));" > /root/staging-automation-s0.json
```

The artisan lines run as www (`$AS_WWW`): an existing staging's tree belongs to www entirely, and these read-only
calls load its code — as root that is a www → root path (D32.13). If `setpriv` is unusable (`AS_WWW` empty), do not run
them as root without the owner's written acceptance of that path for S0 (recorded in the release record); the git,
`stat`, `grep` and `nginx` lines above need no PHP and stay. If www cannot read the environment file yet (artisan fails
as www), record it — S0 changes nothing — and run the artisan lines right after S1b's `chown root:www … chmod 640` line.
(The `operations` state names are the model's constants; if a query fails on an older S0 HEAD, record the error, do not
adapt it by writing.)

Record: whether S0 ran artisan as www, git version, owner/mode of `.git`, whether `.env` is `/etc/onhost/app.env`, the FPM
reload command (`/etc/init.d/php-fpm-83 reload` is ASSUMED), opcache settings, the pg binary directory (`ONHOST_PG_BIN`),
the isolation block and its owner confirmation, PostgreSQL parity, every instance with `secret=stored` and every
`*_SECRET_REF` (→ O4, question 13), the dev-account count, the non-terminal operations, the backlog counts, the queue
lengths, the enabled `onhost-*` units, the automation switches.

Get the judge now (standalone PHP; it never loads the application) — S3 and S4b use it before the deployer is installed:

```bash
GIT_CONFIG_GLOBAL=/dev/null $G fetch -q origin && $G show <STAGING_SHA>:infra/aapanel/deploy-gate.php > /root/deploy-gate.php
sha256sum /root/deploy-gate.php                     # = the release record, else STOP
```

**GATE S0 — containment (Path A always; Path B when the host still runs an old staging).** Owner go-ahead; staging only;
nothing is sent to any panel:

```bash
cd $APP
# 1. nothing of the old install runs, and nothing comes back at boot, by `INSTALL_REPAIR` or by a stray `systemctl start`
for u in $( { systemctl list-units --all --plain --no-legend 'onhost-*' | awk '{print $1}'; ls /etc/systemd/system/*.wants/ 2>/dev/null | grep '^onhost-'; } | sort -u ); do systemctl disable --now "$u"; done
for q in $PROVIDER_LANES; do systemctl mask "onhost-queue@$q.service"; done   # instances have no unit file of their own: a lasting mask
systemctl mask --runtime onhost-scheduler.service                               # its unit file lives in /etc: a runtime mask, re-applied after a reboot (S10)
# 2. the signal (not a control): the freeze, re-asserted by the deployer on every release and by root's cron below
$AS_WWW $P artisan onhost:provisioning:freeze "staging containment"
install -d -m 0700 $STATE && touch $STATE/expect-freeze
# 3. the owner revokes every S0 'secret=stored' credential at its issuer (question 13 b) — confirmed in writing before S5
# KEEP the database. Report the inventory (staging report, doctor JSON, the S0 counts) to the owner for O2.
```

The freeze is a cache key: a Redis flush or restart lifts it silently, and it never held in-flight operations or the
backup scheduler. Root's cron re-asserts it and says so in the journal when it was gone:

```bash
cat > /etc/cron.d/onhost-staging-freeze <<EOF
*/5 * * * * root [ -f $STATE/expect-freeze ] || exit 0; cd $APP || exit 0; m="\$($AS_WWW $P artisan tinker --execute='echo json_encode(app(\Onhost\Domain\Provisioning\FreezeSwitch::class)->meta());' 2>/dev/null | tail -n 1)"; [ "\$m" != null ] || { logger -p user.err -t onhost-freeze 'staging freeze was missing: re-asserted'; $AS_WWW $P artisan onhost:provisioning:freeze 'staging containment (re-asserted by cron)' >/dev/null 2>&1; }
EOF
chmod 0644 /etc/cron.d/onhost-staging-freeze
```

Verify: `systemctl list-units --all --plain --no-legend 'onhost-*'` shows nothing active; `systemctl is-enabled
onhost-scheduler.service` prints `masked-runtime` and every `onhost-queue@provider-*.service` prints `masked`; no
`onhost-*` link in `/etc/systemd/system/*.wants/`; the freeze meta prints the reason. The instance state (staff console
→ Integrations) may also be set to `disabled` for the staff view — **it is not a control** (pre-mortem BLOCKER).

Operations that are not terminal cannot run while no scheduler and no provider lane exists; they stay listed in the O2
inventory. They are cancelled (staff console, `provisioning.operation.cancel` — a database change only, VERIFIED in
`OperationService::cancel`; `RUNNING` ones cannot be cancelled) only before any scheduler or provider lane ever runs on
this database, i.e. as part of phase 2 preparation.

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
[ -d $APP/.git ] && [ ! -L $APP/.git ] || { echo "REFUSED: $APP/.git is a link or missing — report it"; false; } \
  && find -P $APP/.git -exec chown -h root:root {} + && find -P $APP/.git ! -type l -exec chmod go-w {} + && chmod 700 $APP/.git
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

**Path A stops here until question 13 (b) is confirmed in writing.**

### S2 — Fresh install (Path B only)

```bash
curl -fsSL "https://raw.githubusercontent.com/Stanektechcz/onhostik/<STAGING_SHA>/infra/aapanel/install.sh" -o /root/onhost-install.sh
sha256sum /root/onhost-install.sh                  # = the value in the release record, else STOP
QUEUES='default mails' REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> START_UNITS=0 bash /root/onhost-install.sh   # clones, writes /etc/onhost/app.env, stops
```

`QUEUES='default mails'` keeps the provider lanes out of the rendered set and out of `$STATE/expected-units` (which
`install.sh` writes on a first install: the scheduler and every lane in `QUEUES`). `START_UNITS=0` renders the units
without enabling or starting any.

### S3 — `app.env` asserted, not just read (both paths)

Fill (Path B) or review (Path A) `/etc/onhost/app.env`, then write the **expected environment** — the deployer asserts
it on every staging release (rc 2 on any mismatch, nothing changed) and S10 daily. Replace every `<…>` (an unfilled line
is refused); keep one of the two mail variants:

```bash
install -m 0600 /dev/null $STATE/expected-env && cat > $STATE/expected-env <<'EOF'
# staging-launch.md S3 — KEY=value must equal · KEY= must be empty or absent · KEY? must be set · KEY!=value must differ · KEY~=regex
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.onhost.cz
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
ONHOST_SECRETS_DRIVER=db
REDIS_PREFIX?
CACHE_PREFIX?
REDIS_PREFIX!=<production REDIS_PREFIX, question 14>
CACHE_PREFIX!=<production CACHE_PREFIX, question 14>
DB_DATABASE!=<production DB_DATABASE, question 14>
AWS_BUCKET!=<production AWS_BUCKET, question 14>
MAIL_MAILER=log
# or instead of the line above:  MAIL_MAILER=smtp  and  MAIL_HOST=<the sink host, O4>
COMGATE_TEST=true
COMGATE_RECURRING=false
COMGATE_MERCHANT=<the Comgate test merchant id, O4>
WEDOS_TEST_MODE=true
ONHOST_ACME_DIRECTORY~=acme-staging-v02
ONHOST_BANK_FIO_TOKEN=
POWERDNS_HIDDEN01_URL=
ONHOST_CDN_CLOUDFLARE_SECRET_REF=
ONHOST_DISCORD_APPLICATION_ID=
ONHOST_ONCALL_PROVIDER=
ONHOST_VIES_ENABLED~=^(false)?$
ONHOST_FOUR_EYES=false
ONHOST_PLATFORM_BACKUP_DISK=<O4: s3 (the staging-only bucket) or local>
ONHOST_EGRESS_DENY_CIDRS=0.0.0.0/0,::/0
ONHOST_EGRESS_ALLOW_CIDRS=
EOF
$EDITOR $STATE/expected-env                                     # fill the <…> lines
$P $DG env-assert --file /etc/onhost/app.env --spec $STATE/expected-env; echo rc=$?   # before S4b: $P /root/deploy-gate.php env-assert …
```

`rc=0` and every line `OK`, else STOP (`MISMATCH`/`UNFILLED` name the key, never its value). Why the less obvious lines:
`COMGATE_RECURRING=false` — the kept database may hold stored card tokens, and recurring top-ups would charge them;
`ONHOST_EGRESS_DENY_CIDRS=0.0.0.0/0,::/0` — every destination a *customer* names (webhooks, uptime checks, import URLs)
goes through `EgressGuard`, and this makes none public (VERIFIED: `EgressGuard::inCidr` matches every address for
`/0`): the kept database's unpublished outbox is relayed **inline by the first command any staff member runs**
(`CommandBus` relays after every command, and `WebhookDispatcher` posts to each new delivery at once) — without this
line S5 alone would send the backlog to real customer endpoints; the Discord/CDN/on-call keys empty — their
scheduled jobs and bots would otherwise act with production's identities. A `!=` line may be deleted only when S0
proved that the machine itself is staging's alone (record why).

### S4 — Second install run (Path B only)

```bash
QUEUES='default mails' REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> START_UNITS=0 bash /root/onhost-install.sh
cd $APP && $AS_WWW $P artisan onhost:provisioning:freeze "staging phase 1 - no panels"
touch $STATE/expect-freeze
# staff console → Integrations: wedos-main (created by InfrastructureSeeder) → state "disabled" (a staff-view signal; its credentials are empty, O4)
```

Verify: `$STATE/installed` exists; `cat $STATE/expected-units` = the scheduler, `default`, `mails`; the doctor JSON has
no *credentials stored* row; `stat -c '%a %U %n' $APP/storage /etc/onhost /etc/onhost/app.env` = storage `o-rwx` www,
`750`/`640` root:www; `systemctl is-enabled onhost-scheduler.service 'onhost-queue@default.service'` = `disabled`
(rendered, not enabled). Mask the provider lanes anyway (`for q in $PROVIDER_LANES; do systemctl mask
"onhost-queue@$q.service"; done`). Only then: `systemctl enable --now onhost-scheduler.service
'onhost-queue@default.service' 'onhost-queue@mails.service'`.

### S4b — Install the gated deployer, the unit list and the doctor list (both paths)

```bash
GIT_CONFIG_GLOBAL=$STATE/gitconfig git -C $APP fetch --tags origin
GIT_CONFIG_GLOBAL=$STATE/gitconfig git -C $APP show <STAGING_SHA>:infra/aapanel/install-deployer.sh > /root/install-deployer.sh
sha256sum /root/install-deployer.sh                 # = the release record
SHA=<STAGING_SHA> FIRST=1 bash /root/install-deployer.sh   # FIRST=1 only where no deployer is installed yet (refused
                                                             # otherwise); a later upgrade: SHA=<newer> bash … (forward only)
cat /usr/local/lib/onhost-deploy/source-sha         # = STAGING_SHA
```

The units that must run after every release (O12; the deployer refuses a release when one of them is not enabled or not
running beforehand, or when an `onhost-*` unit runs that is not listed — and fails it with rc 7 when one does not come
back):

```bash
# Path A (after question 13 b): printf '%s\n' onhost-queue@default.service onhost-queue@mails.service > $STATE/expected-units
# Path B: written by install.sh — review it
chmod 0600 $STATE/expected-units; cat $STATE/expected-units
# Path A: only now the two lanes, never the scheduler
systemctl enable --now onhost-queue@default.service onhost-queue@mails.service
```

The doctor rows expected non-OK on staging (O11). The gate stops a staging release on **any** non-OK row that is neither
HARD, GATED nor a liveness row and is not on this list (pre-mortem: the gate used to wave every other row through, and
the rows WARN on staging by design are exactly the ones production FAILs on). Draft it from the current doctor, then
let the owner give each row its reason in the release record:

```bash
cd $APP && $AS_WWW $P artisan onhost:doctor --json > /root/staging-doctor-s4b.json
$P $DG nonok --report /root/staging-doctor-s4b.json --production 0      # candidates, one area|check per line
# after the owner's review — exactly the release record's O11 list, nothing more:
install -m 0600 /dev/null $STATE/expected-nonok && $EDITOR $STATE/expected-nonok
$P $DG verdict --report /root/staging-doctor-s4b.json --doctor-rc 0 --env staging --production 0 --sha <STAGING_SHA> --expected-file $STATE/expected-nonok
```

The dry verdict must show no `ROW-FAIL`. On Path A the current code is older than `STAGING_SHA`: a row the target adds
can still stop S7 (rc 5, the site stays in maintenance) — the owner reviews it, it goes into the release record and the
list, and the same S7 command is run again (the deployer lifts the maintenance it set).

### S5 — Staff

```bash
$AS_WWW $P artisan onhost:staff:create <owner-email> --name="<name>" --role=platform_owner   # hidden password prompt
```

Each person signs in and enrols MFA themselves. **Never run `DevAccountSeeder` here.** A second approver only per O6.
Verify: the S0 tinker query shows 0 development accounts (an existing staging that has them: disable them in the staff
console and report — do not delete). On Path A this is the first command through the bus on the kept database: the
S3 egress line must already be `OK`.

### S7 — First gated deploy

The units of O12 run (S4 / S4b), so the drain is exercised.

```bash
REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<reload command from S0>' \
  /usr/local/sbin/onhost-deploy; echo rc=$?
```

Verify:

- `rc=0`; `cat $APP/VERSION` = `STAGING_SHA STAGING_SHA`; `cat $STATE/last-good.json` has `sha` = STAGING_SHA.
- `tail -1 $STATE/deploy.log`: operator, `drain_s`, `window_s`, `set=platform-backups/…`, `override=""`,
  `expected_nonok=<first 12 of sha256sum $STATE/expected-nonok>`.
- Write down the set and the run directory of THIS release for the restore drill (S6 runs after S8, whose failing
  rehearsals append lines with `set=-`), and **the counts that set must hold** — immediately, before anyone uses the site:
  ```bash
  grep ' rc=0 set=platform-backups/' $STATE/deploy.log | tail -1 | tee /root/staging-s7-release.txt
  DB=$($P $DG parse-env --file /etc/onhost/app.env --key DB_DATABASE)
  for t in plans tax_rule_versions orders invoices users services migrations; do printf '%s %s\n' $t "$(cnt $DB $t)"; done | tee /root/staging-s7-counts.txt
  ```
- `$STATE/runs/<ts>-<sha12>/verdict.out` ends with `VERDICT pass`, lists no `HARD-FAIL`, `GATED-FAIL` or `ROW-FAIL`, and an
  `EXPECTED` line only for rows of the O11 list.
- Every unit of `$STATE/expected-units` is `active` and `enabled`; the scheduler (Path A) and every provider lane still
  print `masked`/`masked-runtime`.
- **Six minutes or more later:** `$AS_WWW $P artisan onhost:doctor --json` — `automation|queue worker alive` OK (Path B;
  on Path A no scheduler dispatches the heartbeat, so it stays WARN — recorded as such), `automation|scheduler running`
  OK (Path B only). The gate cannot judge these rows: it runs while the units are drained.
- The automation switches again (`/root/staging-automation-s7.json`, the S0 command): compare with S0 and put both into
  the release record.
- Record `drain_s` and `window_s` next to the backup duration in `backup.out` (O7).

### S8 — Negative rehearsals (staging only; each ends recovered)

| | Do | Expect | Recover |
| --- | --- | --- | --- |
| a | `APP_DEBUG=true` in `app.env`, run S7's command | rc 2: the S3 assertion (`MISMATCH APP_DEBUG`), nothing changed. Then also set `APP_DEBUG=true` in `$STATE/expected-env` and run again: rc 5 (HARD); the site stays 503; units stay stopped; `storage/framework/down` has `"secret":null` | both back to `false`, run the printed command → rc 0 |
| b | switch the rule `platform.backup` off (staff console → Automations), run | rc 3; `$G rev-parse HEAD` unchanged; `/up` 200; units started again | switch the rule on |
| c | `QUEUE_CONNECTION=sync` in `app.env` **and** the spec, run | rc 5 (GATED `storage|queue driver`); a plain re-run → rc 5 again (no laundering); re-run with `ALLOW_DOCTOR_FAIL="<first 12 of STAGING_SHA>:staging override rehearsal"` → rc 0, override logged | `redis` in both, deploy → rc 0 |
| d | deploy a later SHA **that changes `database/migrations`** (below), then `REF=<STAGING_SHA>` | rc 0 both; the rollback over the migration takes a backup (`set=platform-backups/…`, not skipped); `migrate:status` on the old code shows the newer migration as ran, the site works | roll forward → rc 0. Only targets that contain the gate |
| e | change a tracked file (`touch -d yesterday` is not enough: edit it), run | rc 2, no `down` in the output | `$G checkout -- <file>` |
| f | `$AS_WWW $P artisan down` by hand, run | rc 0; the site stays down ("left down") | `$AS_WWW $P artisan up` |
| g | the drain timeout with a synthetic job (below): `DRAIN_TIMEOUT=20` while a 60 s job runs | rc 3 after about 20 s; nothing switched; the site 200; the drill unit comes back **by itself** once its job ends (systemd queued the start behind the pending stop), and the job ran exactly once | below |
| h | a unit that cannot come back (below) | rc 7 (`did not come back: onhost-queue@deploy-drill.service`); the site stays 503; every expected unit stopped again and listed in `drained-units` | below |
| i | delete one line from `$STATE/expected-nonok`, run | rc 5, `ROW-FAIL <that row>`; the site stays 503 | put the line back, run the same command → rc 0 (the deployer lifts its own maintenance) |
| j | `systemctl start onhost-queue@deploy-drill.service` (not listed), run | rc 2 (`runs but is not in … expected-units`), nothing changed | `systemctl stop onhost-queue@deploy-drill.service` |
| k | `systemctl disable onhost-queue@mails.service`, run | rc 2 (`is 'disabled', not enabled`) | `systemctl enable onhost-queue@mails.service` |

Rehearsal d needs a target that really carries a migration — "additive" is otherwise a reading of the diff, not a
proof (pre-mortem). Take the first later commit with green CI that changes `database/migrations`:

```bash
$G log --format='%H %s' --reverse <STAGING_SHA>..origin/development -- database/migrations | head -3
```

None yet: the owner pushes a drill branch from `STAGING_SHA` with one additive migration (a new table, like
`infra/aapanel/ci/deploy-e2e.sh`'s `deploy_e2e_marker`), CI green, never merged; the table stays on staging after the
rollback (recorded). Until d has run with such a target it is NOT REHEARSED and D is NO-GO.

Rehearsal g — mandatory, because it proves two assumptions of section G that a quiet staging never hits: the deployer
gives up cleanly when a job outlives `DRAIN_TIMEOUT`, and systemd starts a unit whose stop was still pending. The job
is a closure that only sleeps and logs; it runs on its own queue, served by a throw-away instance of the worker template
(the drain treats every `onhost-queue@*` unit alike). 60 s stays under the queue's `retry_after` (90 s unless
`REDIS_QUEUE_RETRY_AFTER` says otherwise — check it in `app.env`), so it is never handed out twice.

```bash
cd $APP
systemctl enable --now onhost-queue@deploy-drill.service && echo onhost-queue@deploy-drill.service >> $STATE/expected-units
$AS_WWW $P artisan tinker --execute="dispatch(function () { sleep(60); \Illuminate\Support\Facades\Log::info('deploy-drill job done'); })->onQueue('deploy-drill');"
sleep 5; systemctl is-active onhost-queue@deploy-drill.service     # active, the job is running
DRAIN_TIMEOUT=20 REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<…>' \
  /usr/local/sbin/onhost-deploy; echo rc=$?                        # rc=3, "still running after 20s: … deploy-drill"
$G rev-parse HEAD                                                  # unchanged
systemctl status onhost-queue@deploy-drill.service | head -3       # deactivating (stop-sigterm) → then active again
sleep 60; systemctl is-active onhost-queue@deploy-drill.service    # active: the queued start ran after the job
grep -c 'deploy-drill job done' storage/logs/*.log | awk -F: '{s+=$2} END {print s}'   # 1
[ -n "$AS_WWW" ] || own                                            # only when tinker had to run as root
```

Rehearsal h — the drill unit keeps running, but its next start fails (a runtime drop-in; `mask` would already be
refused by the preflight, which wants every listed unit enabled):

```bash
install -d /run/systemd/system/onhost-queue@deploy-drill.service.d
printf '[Service]\nExecStartPre=/bin/false\n' > /run/systemd/system/onhost-queue@deploy-drill.service.d/drill-fail.conf && systemctl daemon-reload
REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<…>' /usr/local/sbin/onhost-deploy; echo rc=$?   # rc=7
rm -r /run/systemd/system/onhost-queue@deploy-drill.service.d && systemctl daemon-reload
# run the printed recovery command → rc 0; then the drill unit leaves again:
sed -i '/^onhost-queue@deploy-drill.service$/d' $STATE/expected-units && systemctl disable --now onhost-queue@deploy-drill.service
```

Record the observed stop duration next to `DRAIN_TIMEOUT` (300 s default) and the longest job the production queues
run (the `--timeout=900` worker limit): if production jobs regularly run longer than the window allows, the owner raises
`DRAIN_TIMEOUT` or accepts rc 3 retries.

Verify: `deploy.log` holds a line for every attempt with the expected `rc` and `stage`.

### S9 — Functional smoke without panels (provisioning stays frozen)

`GET /v1/status` 200 (through the basic auth); staff sign-in with MFA; a customer registration through Turnstile; an
order paid with the Comgate test merchant up to *paid* — its operations stay held by the freeze and no provider lane
exists to run them; mail appears only in the log or sink (Path A: in `mail_outbox`, sent by nobody — no scheduler);
optionally `onhost:vat:verify` without `--apply`.
**Do NOT run `onhost:smoke:order`, `onhost:nodes:discover` or `onhost:integrations:secret … --check`**: they reach live panels.

Billing across the night is **not** rehearsable in phase 1: no service exists without panels, so no renewal, overdue
or dunning run has anything to act on. The time-of-day failures of `LimitRaiseTest`/`WithdrawalTest` (release record)
stay a production blocker until a billing task has diagnosed them (F); S10 records only that the nightly jobs ran.

### S6 — Restore drill (after S9)

```bash
REL=$(cat /root/staging-s7-release.txt 2>/dev/null || grep ' rc=0 set=platform-backups/' $STATE/deploy.log | tail -1)
SET=$(printf '%s\n' "$REL" | sed -E 's/.* set=([^ ]+) .*/\1/')         # the set of the last SUCCESSFUL release, never
RUN=$(printf '%s\n' "$REL" | sed -E 's/.* run=([^ ]+)$/\1/')            # an S8 attempt (those log set=-)
case "$SET" in platform-backups/*) ;; *) echo "no released set found: STOP"; false ;; esac
PGBIN=/www/server/pgsql/bin                                                 # the directory S0 found (ONHOST_PG_BIN)
ROLE=$($P $DG parse-env --file /etc/onhost/app.env --key DB_USERNAME)
sha256sum $APP/storage/app/private/$SET/database.pgdump | cut -c1-16      # = the prefix in $RUN/backup.out (local disk)
D=$(mktemp -d) && chown postgres $D && install -o postgres -m 0600 $APP/storage/app/private/$SET/database.pgdump $D/db.pgdump
su - postgres -c "$PGBIN/createdb -O $ROLE onhost_restore_drill"
time su - postgres -c "$PGBIN/pg_restore --no-owner --no-privileges --role=$ROLE -d onhost_restore_drill $D/db.pgdump"; echo rc=$?   # rc=0
# the counts THIS set must hold were taken at S7 — the live database has moved on since (S8, S9)
for t in plans tax_rule_versions orders invoices users services migrations; do printf '%s %s\n' $t "$(cnt onhost_restore_drill $t)"; done > /root/staging-s6-counts.txt
diff /root/staging-s7-counts.txt /root/staging-s6-counts.txt && echo counts-equal     # plans, tax_rule_versions non-empty
# the restored database works for the application — read-only, config cache bypassed, cache in memory:
DRILL="env APP_CONFIG_CACHE=/nonexistent/onhost-restore-drill.php DB_DATABASE=onhost_restore_drill CACHE_STORE=array"
cd $APP && $DRILL $AS_WWW $P artisan tinker --execute="echo DB::connection()->getDatabaseName(), PHP_EOL;"   # onhost_restore_drill — anything else: STOP
$DRILL $AS_WWW $P artisan migrate:status | grep -c Pending                                                  # 0
$DRILL $AS_WWW $P artisan onhost:doctor --json > /root/staging-s6-doctor.json; echo doctor_rc=$?
$P $DG verdict --report /root/staging-s6-doctor.json --doctor-rc <doctor_rc> --env staging --production 0 --sha <STAGING_SHA> --expected-file $STATE/expected-nonok
su - postgres -c "$PGBIN/dropdb onhost_restore_drill"; rm -r "$D"
```

(Table names are from the migrations; the set path assumes the local backup disk — adjust for S3 per
`ONHOST_PLATFORM_BACKUP_DISK`.) A pair that differs: count the rows written after the set's timestamp
(`created_at > '<set ts>'`) — anything else is a failed drill. The verdict must show no `HARD-FAIL` (the database and
role rows); a `ROW-FAIL` is explained in the record. Record the duration. The 7-day freshness rule is a proposal
(question 9), not a gate.

### S10 — Daily, for 24 h (Path B: 72 h, three nights), then with every release

- the environment still holds: `$P $DG env-assert --file /etc/onhost/app.env --spec $STATE/expected-env` → rc 0;
- the units: `systemctl list-units --plain --no-legend 'onhost-*'` shows exactly `$STATE/expected-units` active;
  every provider lane `masked`; Path A: the scheduler `masked-runtime` — after a reboot it is `disabled`: mask it again
  (`systemctl mask --runtime onhost-scheduler.service`) and record the reboot;
- the freeze is on (`$AS_WWW $P artisan tinker --execute="dump(app(Onhost\Domain\Provisioning\FreezeSwitch::class)->meta())"`
  prints the reason; the staff console shows a banner); `journalctl -t onhost-freeze --since -1d` empty — a line there
  means the cache lost the freeze and the cron put it back: record it;
- Path A: every credential of the O4 list revoked at its issuer (the owner's written confirmation; nothing is tested
  against a panel);
- Path B, each morning: the automation ledger has `platform.backup` (02:15) and `platform.backup.verify` (03:15) of the
  night without error (the nightly run is www under `ProtectSystem=strict`, not root like the deploy backup), and
  `billing.overdue`/`billing.dunning`/renewals ran without error (they act on nothing yet — see S9);
- `find $APP/storage/logs ! -user www` prints nothing;
- mail only in the sink; no provider calls in the logs (`grep -il 'ispconfig\|aapanel\|pterodactyl\|proxmox' storage/logs/*`).

## D. Go / no-go for "staging phase 1 live" (all must hold)

- O1–O12 and questions 13–14 recorded; the release record lists the evidence below.
- S0: the isolation block confirmed by the owner (no shared database, Redis server + prefix, cache prefix, bucket,
  endpoint); PostgreSQL parity recorded.
- Path A: every S0 *secret=stored* credential and `*_SECRET_REF` revoked at its issuer, confirmed in writing before S5
  (question 13 b); the scheduler never started; the provider lanes masked.
- S1: 401 from outside (also with `X-Forwarded-For: 127.0.0.1` and `X-Real-IP: 127.0.0.1`), no `set_real_ip_from`
  trusting a range the operator does not control, 200 for `/v1/status` over loopback; `.git` root-owned;
  `$APP/.env` is `/etc/onhost/app.env`; the deployer installed from `STAGING_SHA`.
- S3: `env-assert` rc 0 with no line deleted without a recorded reason.
- S7: rc 0 with every HARD and GATED row OK, no `ROW-FAIL`, `EXPECTED` only for O11 rows; the six-minute doctor shows
  the liveness rows OK for what the path runs (Path B: scheduler and worker; Path A: recorded WARN, no heartbeat source);
  S8 (a)–(k) gave their expected rc and recovered — (d) with a migration-carrying target, (g) and (h) are not optional.
- The restore drill passed: checksum first, counts equal to S7's, `migrate:status` without Pending, no `HARD-FAIL`.
- `drain_s`, `window_s` and the backup duration are recorded next to the production nightly backup duration (or the
  S7-based estimate while production has not run).
- S10: the environment, units and freeze held for its whole period (Path B: three nights of backup + verify without error).
- S0 and every later hand-run artisan ran as www (`AS_WWW`), or the owner accepted root execution for staging in
  writing (release record). The deployer itself still runs the target's PHP as root (D32.13): accepted for staging
  phase 1 only, never carried to production by default (F).
- Anything else is NO-GO. Phase 2 (test panels) has its own go/no-go after the owner's per-panel decision, and needs
  first: the non-terminal operations of the kept database cancelled (Path A), and the code follow-up that makes a
  contained instance refuse every actor (`ProviderRegistry::forInstance`, the backup scheduler — handoff).

## E. Rollback

- A failed release: run the command the deployer printed (last good SHA, the backup is taken, not skipped); verify rc 0
  and `/up` 200.
- A staging database rebuild only after O2 retirement.
- A database restore only with the owner, after a fresh backup, with the units stopped, the checksum compared first
  (`docs/runbooks/release-and-rollback.md` § Rollback).
- Never touch a panel as part of a rollback.

## F. Promotion to production (outline; no AI action)

- Only a SHA that passed S7–S10 on **Path B**, and whose release record's staging-vs-production table marks every key
  where staging differs from production as *not rehearsed* — each such line reviewed by the owner.
- **A fresh `install.sh` run was rehearsed** with a production-shaped `app.env` (production's values, secrets blanked)
  on a throw-away PostgreSQL database or host — Path B counts only if its `app.env` differed from production solely
  in the lines of that table. Production has never been deployed: its first install goes through `install.sh`
  (`migrate` without `--isolated`, the full `db:seed` with `InfrastructureSeeder`), which no staging on Path A exercises.
- **The time-of-day failures of `LimitRaiseTest`/`WithdrawalTest` are diagnosed** (and fixed, or proven to be test
  artefacts) by a billing task — production dunning and renewals run in exactly that window (pre-mortem).
- **Precondition:** the follow-up that runs the deployer's artisan/composer steps as www (`setpriv`) with a root-owned
  code tree is done — or the owner accepts in writing, in the release record, that root runs the target's PHP in a tree
  www can write (D32.13: code, `vendor/`, `bootstrap/cache`, `storage/framework/views`, root's writes of the `down` file
  and logs in `storage/`). Without one of the two, production is NO-GO (go-live checklist row, handoff).
- In production **every FAIL row of the doctor stops the deploy** (HARD never passes; any other row only with an
  `Accept-Gate: <area|check> — <reason>` line in the signed tag). The owner drafts those lines from the production
  doctor (`deploy-gate.php nonok --production 1`) before signing — a tag signed without them stops at the gate (rc 5).
- The tag is made only with `git tag -s`, never by editing a tag object: the deployer
  refuses a tag with anything after its signature (review round 3) and reads `Accept-Gate:` only from the signed
  message.
- The owner creates and signs the annotated tag `vYYYY.MM.DD[-N]` with `Release-Record:` and `Verdict: READY`, plus an
  `Accept-Gate:` line only for a row he accepts (`.ai/releases/README.md`).
- On the production host: `allowed_signers` with the owner's key (root, 0600), git 2.34+, `$STATE/expected-units` (the
  scheduler and the lanes production runs), the deployer installed from the same signed tag (`SHA=<sha> TAG=<tag> bash
  install-deployer.sh` — in production the installer refuses a SHA without the owner's signed tag, and `FIRST=1` on a
  host that already has a deployer), then `REF=<tag> EXPECTED_SHA=<sha> DEPLOY_OPERATOR=<name> /usr/local/sbin/onhost-deploy`.
- Production instances get keys issued for production (question 13); none of staging's keys is copied over.
- A separate production runbook review (go-live checklist) precedes it.

## G. Assumptions to check on the host (NOT verified from the repository)

git ≥ 2.32 (GIT_CONFIG_GLOBAL) and ≥ 2.34 on production (SSH `verify-tag`); nginx answers on `127.0.0.1:443`; the staging
nginx block behaves as written and no `real_ip` setting trusts a foreign range (S1 proves it); the FPM reload command;
`DRAIN_TIMEOUT=300` s is enough (a worker job may run 900 s; the drain then fails with rc 3 and nothing changes — S8 g
proves the rc 3 path, not production's job lengths); systemd queues a `start` issued behind a pending `stop` (S8 g);
`systemctl is-active` shows a unit that crashes on boot within `UNIT_SETTLE` (5 s) (S8 h proves the refusal path);
a persistent `mask` of a template instance (`onhost-queue@provider-x.service`) works while the template's file lives in
`/etc/systemd/system`, and a runtime drop-in in `/run/systemd/system` applies after `daemon-reload` (S0 GATE, S8 h
verify both); `systemctl is-enabled` prints `enabled` for an instance enabled through the template's `[Install]`;
Laravel's real environment overrides `.env` and a missing `APP_CONFIG_CACHE` file makes it read the config files (the
deployer compares the doctor's environment with its own reading either way; S6 prints the database it talks to);
`Queue::size` answers for the Redis queues; every customer-named destination goes through `EgressGuard` (webhooks,
uptime checks, imports — as the code reads today); `PGOPTIONS` reaches `pdo_pgsql` through libpq; the pg binary
directory; Comgate's retry behaviour after a 503.
