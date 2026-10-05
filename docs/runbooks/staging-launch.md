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
its issuer, every live endpoint rejected by the host itself and probed by the deployer before each release (review
round 0: `$STATE/egress-blocked`, S0 GATE), customer-named destinations denied (`ONHOST_EGRESS_DENY_CIDRS`), the
environment asserted by the deployer on every release. The freeze stays, as a signal, not as a control.

## Two paths — the owner chooses (question 1)

| | **Path A — the existing staging, contained** | **Path B — a fresh install** (recommended) |
| --- | --- | --- |
| Database | the kept staging database (live-panel bindings, maybe real addresses, webhooks, queued mail) | empty, seeded by `install.sh`; the old staging database stays untouched on its host (same host: S1c parks the old install) |
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
R=$STATE/repo.git                                  # the deployer's repository: root's, outside the site tree (S0 makes it)
# git as root runs only on $R, never on a .git in the tree (review round 2, security HIGH: www can replace entries of
# $APP, so a .git there can carry config, attributes, alternates and hooks root's git would obey) — the deployer's `g`
G="env GIT_DIR=$R GIT_WORK_TREE=$APP GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null git -C $APP -c core.hooksPath=/dev/null -c core.fsmonitor=false"
PROVIDER_LANES="provider-pterodactyl provider-aapanel provider-ispconfig provider-proxmox provider-powerdns provider-registrar provider-kubernetes"
# artisan by hand runs as www, as the deployer does (review round 0): www can write this tree (on an existing staging
# all of it), so root running its PHP is a www → root path. In a session of its own (setsid, review round 1): in
# root's session code www controls could push keystrokes into root's shell (TIOCSTI, CVE-2016-2779's class). `www`
# also gives it no terminal at all — stdin /dev/null, output through root's cat — so nothing it leaves running reads
# what root types next. WWW_CMD is the bare prefix, for cron and for the one prompt that needs the terminal (S5).
# AS_WWW empty = setpriv or setsid --wait (util-linux 2.31+) unusable: see S0 — the deployer then refuses every release (rc 2).
www() { ( set -o pipefail; setpriv --reuid=www --regid=www --init-groups -- setsid --wait "$@" </dev/null 2> >(cat >&2) | cat ); }
if www true 2>/dev/null; then AS_WWW=www; WWW_CMD="setpriv --reuid=www --regid=www --init-groups -- setsid --wait"; else AS_WWW=""; WWW_CMD=""; fi
# storage and bootstrap/cache back to www after anything root ran — never `chown -R`/`chmod -R` (they follow a symlink
# given on the command line, and www can plant one). Review round 2 (security HIGH): `find … -exec chown -h {} +`
# resolved every directory of a batched path at chown time — a window in which a directory www swapped for a link
# re-owned files outside the tree. Now only what is not www's, each entry from inside its directory (-execdir: one
# path component; -h: never a link's target), and `o-rwx` set by www itself (skipped without setpriv; it is privacy
# only). The deployer's own function, by hand:
own() {
  local d; for d in storage bootstrap bootstrap/cache; do
    [ ! -L "$APP/$d" ] && [ -d "$APP/$d" ] && [ "$(realpath "$APP/$d")" = "$(realpath "$APP")/$d" ] || { echo "REFUSED: $APP/$d is a link or missing — report it"; return 1; }
  done
  find -P $APP/storage $APP/bootstrap/cache ! -user www -execdir chown -h www:www {} + \
    && { [ -z "$WWW_CMD" ] || $WWW_CMD find -P $APP/storage $APP/bootstrap/cache ! -type l -perm /o=rwx -exec chmod o-rwx {} +; }
}
# a database count as the postgres superuser ($1 = database, $2 = table) — read-only
cnt() { su - postgres -c "psql -tA -d $1 -c 'select count(*) from $2'"; }
```

Every `$P artisan …` that ran as root (no setpriv) writes logs as root: end each such session on the host with `own`
(on an installed site `INSTALL_REPAIR=1 bash /root/onhost-install.sh` does the same and re-renders the units without
enabling or starting any). The deployer does **not** do it for you any more (review round 2): it refuses a release while
anything in storage or bootstrap/cache is not www's, printing the first 20 entries, and re-owns only what its own
checkout writes there.

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
| O12 | Units that must run after every release (`$STATE/expected-units`) | Path A: `onhost-queue@default.service`, `onhost-queue@mails.service` (only after question 13 b is done **and the first S7 has run** — S7, Path A; before that: none). Path B: those two plus `onhost-scheduler.service`. The provider lanes never in phase 1 |

### Owner questions 1–14 (defaults taken meanwhile)

Fourteen questions; the decisions O1–O12 above answer several of them. The release record lists all fourteen with the
answer or the default taken (a default taken by delegation is marked as such, and the owner may overrule it).

1. **Path and host:** Path A or Path B (table above)? For B: a new host, or a new database on the staging host with the
   old one left untouched (S1c: the old install contained and parked, nothing deleted)? *Default: nothing runs on the
   host beyond S0 until answered; recommendation B — only B rehearses `install.sh` and the scheduler, and it holds no
   live credential.*
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
11. **Git access** of the host to `Stanektechcz/onhostik`. The repository is **public** (checked 2026-09-27 with
    `gh repo view Stanektechcz/onhostik --json visibility`) and the owner keeps it public for now, so the host needs
    **no deploy key** while it stays so: `install.sh` clones over anonymous HTTPS, S2 fetches it from
    `raw.githubusercontent.com`, and root's `$R` fetches `origin` without a credential. The consequence is recorded in
    the release record: every pushed branch — the breach register with its open holes included — is world-readable.
    *If it turns private: before the next fetch the operator installs a read-only deploy key for `$R`'s origin (and S2
    downloads `install.sh` another way); no credential goes through the AI.*
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
- The owner decisions of section A (O1–O12 and questions 1–14) are recorded in that release record. The first one:
  `.ai/releases/2026-09-27-40d6f1b.md` (Path B on the same host, decisions taken by delegation).

## C. Steps

### S0 — Discovery (read-only; answers the unknowns, changes nothing on the site)

```bash
hostname; ls /www/wwwroot; ss -ltnp
ls -la /etc/onhost; systemctl list-units --all 'onhost-*'; systemctl list-unit-files 'onhost-*'
ls /etc/systemd/system/*.wants/ 2>/dev/null | grep onhost-                        # what a reboot would start
git --version                                     # 2.32+ (production host: 2.34+ for SSH-signed tags)
curl --version | head -1
ss -ltnp | grep ':443'                            # nginx must answer on 127.0.0.1:443 (the deployer's HTTP gate)
stat -c '%F %U %a %n' $APP/.git; cat $APP/.git/HEAD; cut -d' ' -f1 $APP/VERSION   # plain reads: git never runs on this .git
#   HEAD "ref: refs/heads/X" → cat $APP/.git/refs/heads/X (or grep refs/heads/X $APP/.git/packed-refs) → <OLD_SHA>
sysctl -n fs.protected_hardlinks                  # 1 (the distribution default): www cannot hard-link a root file into storage — the
                                                  # deployer's hand-over and own() rely on it; 0: STOP, set it to 1 (sysctl.d)
ls -l $APP/.env; stat -c '%U:%G %a %n' /etc/onhost /etc/onhost/app.env   # .env -> /etc/onhost/app.env; root:www 750/640
[ $APP/.env -ef /etc/onhost/app.env ] && echo same-file                  # the deployer refuses otherwise (S1b fixes it)
stat -c '%F %n' $APP/storage $APP/bootstrap $APP/bootstrap/cache           # directories, not symbolic links
stat -c '%F %U %n' $APP/vendor; find -P $APP/vendor ! -user www -print -quit   # composer runs as www: S1b hands vendor over
nginx -T 2>/dev/null | grep -nE 'real_ip|set_real_ip_from|X-Forwarded-For|X-Real-IP'   # review: see S1
git config --file $APP/.git/config --get remote.origin.url   # a plain read → <ORIGIN>: the repository of question 11, else STOP
# the deployer's repository (review round 2, security HIGH): root's own, in the root-only state dir, filled from origin —
# never $APP/.git, which www can replace. Nothing in the site tree changes: the index is built from <OLD_SHA> and the
# status compares it with the files as they are.
install -d -m 0700 $STATE && [ ! -e $R ] && (umask 077; GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null git init -q --bare $R) \
  && $G config core.bare false && $G remote add origin <ORIGIN>
$G fetch -q --tags origin && $G update-ref --no-deref HEAD <OLD_SHA> && $G read-tree HEAD
$G rev-parse HEAD; $G status --porcelain --untracked-files=all    # review: what in the tree differs from <OLD_SHA>
# proxies the HTTP clients or ssh would use (review round 2, security MEDIUM: the per-address rules do not cover a
# proxy): each line prints nothing, else STOP and record it
systemctl show-environment | grep -i proxy
for u in $(systemctl list-unit-files --plain --no-legend 'onhost-*' | awk '{print $1}'); do systemctl show -p Environment "$u"; done | grep -i proxy
grep -rniE 'env\[[^]]*proxy' /www/server/php/83/etc/ 2>/dev/null; grep -ni proxy /etc/environment 2>/dev/null
grep -niE 'ProxyCommand|ProxyJump' ~www/.ssh/config /etc/ssh/ssh_config /etc/ssh/ssh_config.d/* 2>/dev/null
command -v setpriv setsid && www id   # uid=www (setpriv, and setsid --wait: util-linux 2.31+): the deployer needs both (else rc 2, NO-GO)
sysctl -n dev.tty.legacy_tiocsti 2>/dev/null   # record: 1 or empty (kernel < 6.2) = TIOCSTI works; setsid is what stops it (review round 1)
nft list tables 2>/dev/null; iptables -S OUTPUT 2>/dev/null | head; systemctl is-enabled nftables firewalld 2>/dev/null   # S0 GATE step 4
nft -j list tables >/dev/null && echo nft-json    # the deployer reads the table as JSON (review round 2): no JSON support = Path A NO-GO
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
getent hosts $(grep -E '^(DB_HOST|REDIS_HOST)=' /etc/onhost/app.env | cut -d= -f2 | awk '{print $1}' | tr -d '"')   # which machines they are
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
# every instance, whatever its provider (panels, DNS, registrars, CDN), whether a credential is stored, and the
# host:port it talks to — the O4 list and the first lines of $STATE/egress-blocked (S0 GATE step 4)
$AS_WWW $P artisan tinker --execute="foreach (Onhost\Domain\Provisioning\Models\ProviderInstance::query()->orderBy('provider')->get() as \$i) { \$u = parse_url((string) \$i->base_url) ?: []; echo \$i->key, ' ', \$i->provider, ' ', \$i->state, ' secret=', app(Onhost\Platform\Secrets\SecretStore::class)->exists(\$i->secretRef()) ? 'stored' : 'none', ' endpoint=', isset(\$u['host']) ? \$u['host'].':'.(\$u['port'] ?? ((\$u['scheme'] ?? '') === 'http' ? 80 : 443)) : '-', PHP_EOL; }"
grep -E '^[A-Z_]*SECRET_REF=' /etc/onhost/app.env | cut -d= -f1,2        # refs only; the secrets are in the database
# what could move by itself: non-terminal operations, the relay backlog, customer webhooks, queued mail, queue lengths
$AS_WWW $P artisan tinker --execute="dump(DB::table('operations')->whereIn('state',['PENDING','RUNNING','WAITING'])->selectRaw('state, queue, count(*) n')->groupBy('state','queue')->get(), ['outbox_unpublished' => DB::table('outbox_messages')->whereNull('published_at')->count(), 'webhook_endpoints_active' => DB::table('webhook_endpoints')->where('state','active')->count(), 'webhook_deliveries_open' => DB::table('webhook_deliveries')->whereIn('state',['pending','failed'])->count(), 'mail_queued' => DB::table('mail_outbox')->where('state','queued')->count()])"
$AS_WWW $P artisan tinker --execute="foreach (array_merge(['default','mails'], array_map(fn (\$q) => 'provider-'.\$q, ['pterodactyl','aapanel','ispconfig','proxmox','powerdns','registrar','kubernetes'])) as \$q) { echo \$q, ' ', Illuminate\Support\Facades\Queue::size(\$q), PHP_EOL; }"
# the switches that live in the database (default-off rules included): recorded now and again after S7
$AS_WWW $P artisan tinker --execute="echo json_encode(array_map(fn (\$r) => [\$r['key'] ?? null, \$r['enabled'] ?? null], app(Onhost\Domain\Provisioning\AutomationLedger::class)->overview()));" > /root/staging-automation-s0.json
```

The artisan lines run as www (`$AS_WWW`): an existing staging's tree belongs to www entirely, and these read-only
calls load its code — as root that is a www → root path (D32.13). If `setpriv` is unusable (`AS_WWW` empty), do not run
them as root without the owner's written acceptance of that path for S0 (recorded in the release record); the git,
`stat`, `grep` and `nginx` lines above need no PHP and stay. If www cannot read the environment file yet (artisan fails
as www), record it — S0 changes nothing — and run the artisan lines right after S1b's `chown root:www … chmod 640` line.
(The `operations` states are `Operation`'s constants — PENDING, RUNNING, WAITING can still move; if a query fails on an older S0 HEAD, record the error, do not
adapt it by writing.)

Record: whether S0 ran artisan as www, git version, the old `.git` (owner, `<OLD_SHA>`, `<ORIGIN>`) and `$G status` against
it, `fs.protected_hardlinks`, the proxy lines (none), whether `.env` is `/etc/onhost/app.env`, the FPM
reload command (`/etc/init.d/php-fpm-83 reload` is ASSUMED), opcache settings, the pg binary directory (`ONHOST_PG_BIN`),
the isolation block and its owner confirmation, PostgreSQL parity, every instance with `secret=stored` and every
`*_SECRET_REF` (→ O4, question 13), the dev-account count, the non-terminal operations, the backlog counts, the queue
lengths, the enabled `onhost-*` units, the automation switches.

Get the judge now (standalone PHP; it never loads the application) — S3 and S4b use it before the deployer is installed:

```bash
GIT_CONFIG_GLOBAL=/dev/null $G fetch -q origin && $G show <STAGING_SHA>:infra/aapanel/deploy-gate.php > /root/deploy-gate.php
sha256sum /root/deploy-gate.php                     # = the release record, else STOP
```

**GATE S0 — containment (Path A always; Path B when the host still runs an old staging — steps 1–2, then S1c).** Owner go-ahead; staging only;
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

**Step 4 — the live endpoints become unreachable from this host, verifiably** (review round 0, security MEDIUM: the
revocation of step 3 is a written statement nobody on the host can check, and `EgressGuard` guards only the
destinations customers name — the provider clients, the SSH toolkit and the mailers do not pass through it). Path A:
**NO-GO without it.** One `host:port` per line (`[v6]:port` for IPv6), comments with `#`: every S0 `endpoint=`, every
node address the panels' own inventories name (Wings nodes, Proxmox/PBS nodes, web and mail nodes — their SSH port and
agent ports), the registrars' and DNS/CDN APIs, Discord. The operator reads the node addresses in the panels' own UIs;
nothing is asked of a panel by the platform or the AI.

```bash
install -m 0600 /dev/null $STATE/egress-blocked && $EDITOR $STATE/egress-blocked
# a CDN-fronted API name (api.cloudflare.com, discord.com) resolves to rotating addresses: pin it in /etc/hosts to
# 192.0.2.1 (TEST-NET-1) instead, and list the name — the rule below rejects that range
names() { sed -e 's/#.*//' -e 's/[[:space:]]//g' -e '/^$/d' $STATE/egress-blocked | sed -E 's/^\[?([^]]*)\]?:[0-9]+$/\1/' | sort -u; }
nft add table inet onhost_containment
nft add chain inet onhost_containment out '{ type filter hook output priority 0 ; policy accept ; }'
nft add rule inet onhost_containment out ip daddr 192.0.2.0/24 reject
for a in $(names | xargs -r -n1 getent ahosts | awk '{print $1}' | sort -u); do
  case $a in *:*) nft add rule inet onhost_containment out ip6 daddr $a reject ;; *) nft add rule inet onhost_containment out ip daddr $a reject ;; esac
done
nft list table inet onhost_containment > /etc/nftables.d/onhost-containment.nft   # persisted — how the host loads it at boot: S0 (record)
# the probe the deployer runs before every staging release (and S10 daily): a bare TCP connect as www, nothing is sent
probe() { sed -e 's/#.*//' -e 's/[[:space:]]//g' -e '/^$/d' $STATE/egress-blocked | while read -r hp; do h=${hp%:*}; h=${h#[}; h=${h%]}; p=${hp##*:}; $AS_WWW timeout 5 bash -c 'exec 3<>"/dev/tcp/$1/$2"' _ "$h" "$p" 2>/dev/null && echo "OPEN $hp"; done; }
probe                                                  # prints nothing; any OPEN line: STOP, the rule is incomplete
# and each address has its own reject rule ON THE OUTPUT HOOK — the judge reads the table the way the deployer does
# (review round 2: a refused probe alone proves nothing, and a rule in a chain on another hook filters nothing outbound)
nft -j list table inet onhost_containment > /root/onhost-containment.json && $P /root/deploy-gate.php nft-rejects --file /root/onhost-containment.json > /root/onhost-rejected.txt; echo rc=$?   # rc=0, else STOP
for a in $(names | xargs -r -n1 getent ahosts | awk '{print $1}' | sort -u); do case $a in 192.0.2.*) grep -qx '192.0.2.0/24' /root/onhost-rejected.txt ;; *) grep -qx "$a" /root/onhost-rejected.txt ;; esac || echo "NO RULE $a"; done   # prints nothing
```

The rule rejects in the host's own output path, so a probe of a covered address never leaves the machine; a probe of
an address the rule misses is a bare TCP connect without a byte of payload (no credential, no request) — and the
release is refused. The deployer refuses a staging release (rc 2, nothing changed) when `$STATE/egress-blocked` is
missing or malformed, when any address on it answers, and — review round 1, security MEDIUM: NXDOMAIN, a DNS outage or
a panel that is down refused a connection exactly like the rule — when a listed name does not resolve now (`getent
ahosts`), when `nft -j list table inet onhost_containment` does not exist, or when an address a line resolves to has no
`ip daddr <a> reject` / `ip6 daddr <a> reject` rule in it (a name pinned to TEST-NET-1: the `192.0.2.0/24` rule).
nftables is therefore required (on an iptables-only host: install nftables, or the owner accepts Path A as NO-GO). An
**empty** list passes only on Path B without any live credential, only with the root-owned marker — never on Path A — and
only while the deployer, asking the application as www, counts **no** provider instance with a stored secret (review round
2, security MEDIUM: the marker alone was a claim as unverifiable as the revocation; the count refuses the release when it
is not 0 or cannot be read). Review round 2 also holds the rule to the output hook: the deployer reads the table with
`nft -j` and counts a rule only in a `type filter hook output` chain; any rule of another shape in the table (an accept, a
jump, a port match), a set or a dormant table refuses it:

```bash
install -m 0600 /dev/null $STATE/path-b    # Path B only (O1 recorded), with the release record's owner confirmation
```

The freeze is a cache key: a Redis flush or restart lifts it silently, and it never held in-flight operations or the
backup scheduler. Root's cron re-asserts it and says so in the journal when it was gone — only with `setpriv` (`WWW_CMD`
set): without it the cron would run www's PHP as root every five minutes (D32.13), so there is no cron and S10 checks
the freeze by hand:

```bash
[ -n "$WWW_CMD" ] && cat > /etc/cron.d/onhost-staging-freeze <<EOF
*/5 * * * * root [ -f $STATE/expect-freeze ] || exit 0; cd $APP || exit 0; m="\$($WWW_CMD $P artisan tinker --execute='echo json_encode(app(\Onhost\Domain\Provisioning\FreezeSwitch::class)->meta());' 2>/dev/null | tail -n 1)"; [ "\$m" != null ] || { logger -p user.err -t onhost-freeze 'staging freeze was missing: re-asserted'; $WWW_CMD $P artisan onhost:provisioning:freeze 'staging containment (re-asserted by cron)' >/dev/null 2>&1; }
EOF
chmod 0644 /etc/cron.d/onhost-staging-freeze
```

Verify: `systemctl list-units --all --plain --no-legend 'onhost-*'` shows nothing active; `systemctl is-enabled
onhost-scheduler.service` prints `masked-runtime` and every `onhost-queue@provider-*.service` prints `masked`; no
`onhost-*` link in `/etc/systemd/system/*.wants/`; the freeze meta prints the reason; `probe` prints nothing and
`$STATE/egress-blocked` covers every S0 `endpoint=` (Path A: an empty list is NO-GO). The instance state (staff console
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

### S1b — One-time git hardening (Path A, after the S0 review found nothing foreign; Path B on the same host: S1c instead)

```bash
# the repository is $R (S0 made it; review round 2, security HIGH): the old .git leaves the tree — kept under /root for
# review, never used by root's git again — and so does the old global config that named $APP safe.directory
{ [ -e $APP/.git ] || [ -L $APP/.git ]; } && mv -T $APP/.git /root/onhost-site-git-before-s1b
rm -f $STATE/gitconfig
[ "$($G rev-parse HEAD)" = <OLD_SHA> ] && [ -z "$($G status --porcelain --untracked-files=all -- . ':(exclude)VERSION')" ] \
  || echo "STOP: the tree differs from <OLD_SHA> (S0's status) — report it"
# storage and bootstrap/cache: what an older root run left there goes to www once (the deployer only reports it)
own
# vendor/ is www's from now on — composer runs as www (review round 0); a root-owned one of an older root build is
# handed over without following a link (the deployer refuses a release while anything in it is not www's):
[ ! -e $APP/vendor ] || { [ -d $APP/vendor ] && [ ! -L $APP/vendor ] && find -P $APP/vendor ! -user www -execdir chown -h www:www {} + ; } \
  || echo "REFUSED: $APP/vendor is a link — report it"
chown root:www /etc/onhost /etc/onhost/app.env && chmod 750 /etc/onhost && chmod 640 /etc/onhost/app.env
# only if S0 found .env NOT to be /etc/onhost/app.env — keep a copy, compare, and link only when nothing differs:
install -m 0600 $APP/.env /root/env-before-s1b
diff $APP/.env /etc/onhost/app.env && ln -sfn /etc/onhost/app.env $APP/.env   # a difference: STOP, the owner decides
                                                                              # which values app.env takes
```

The deployer decides production from `/etc/onhost/app.env` (root-owned) and refuses when `$APP/.env` is not that very
file, or when `storage`, `bootstrap` or `bootstrap/cache` is a symbolic link.

No deploy key while the repository is public (question 11); once it is private, the host's read-only deploy key is the
operator's and never passes through the AI.

**Path A stops here until question 13 (b) is confirmed in writing.**

### S1c — Path B on the same host: contain the old staging, park it, start from an empty site (Path B only)

When Path B reuses the host of an existing staging (question 1; the record `.ai/releases/2026-09-27-40d6f1b.md` takes this
by delegation), the old install is contained, then moved aside whole — **nothing is deleted**. `install.sh` refuses a site
that is already installed (the state marker, or an `APP_KEY` in `/etc/onhost/app.env`), and the old tree, its
environment file and its deployer state would otherwise mix with the new install. The old database stays in the
cluster, untouched; its stored live keys stay unreadable without the old `APP_KEY`, which is parked with `/etc/onhost`.
S1b is **not** run on this path: the old `.git` leaves with the tree, and `install.sh` makes `$R` and never a `.git` in
the site tree.

Before it: S0 (read-only) recorded the old `<OLD_SHA>`, its `DB_DATABASE`/`DB_USERNAME`, `REDIS_DB`/`REDIS_CACHE_DB`
and `REDIS_PREFIX`/`CACHE_PREFIX` (an unset prefix is Laravel's `onhost-database-` / `onhost-cache-`), and the owner
confirmed that nothing on this host is production's. Then the old staging is contained — S0 GATE steps 1 and 2
(disable + mask, runtime mask of the scheduler, the freeze) — and also:

```bash
cd $APP && $AS_WWW $P artisan down                 # the old site answers 503 until it is parked
crontab -l 2>/dev/null | grep -nE 'artisan|onhost'; crontab -l -u www 2>/dev/null | grep -nE 'artisan|onhost'
grep -rlE 'artisan|onhost' /www/server/cron /etc/cron.d 2>/dev/null   # aaPanel's Cron page writes /www/server/cron
supervisorctl status 2>/dev/null | grep -i onhost
```

Switch off every entry these print in aaPanel (**Cron**: each task that calls `artisan`, `schedule:run` above all;
**Supervisor**: every onhost program) — an aaPanel cron runs as root, and after the park it would run the **new** tree's
`artisan` as root. `/etc/cron.d/onhost-staging-freeze` (S0 GATE) may stay: it acts only while `$STATE/expect-freeze`
exists, and on the new install that is S4's freeze. Verify:

```bash
{ crontab -l 2>/dev/null; crontab -l -u www 2>/dev/null; } | grep -v '^[[:space:]]*#' | grep -E 'artisan|onhost'   # nothing
# aaPanel's crontab lines call a script in /www/server/cron: none of the scripts that name artisan may still be scheduled
for f in $(grep -rlE 'artisan|onhost' /www/server/cron 2>/dev/null); do crontab -l 2>/dev/null | grep -v '^[[:space:]]*#' | grep -F "$(basename "$f")"; done   # nothing
supervisorctl status 2>/dev/null | grep -i onhost | grep -v -E 'STOPPED|EXITED'                                  # nothing
ps -eo user,cmd | grep -E 'artisan|queue:work|horizon' | grep -v grep                                            # nothing
systemctl list-units --all --plain --no-legend 'onhost-*' | grep -w active                                       # nothing
```

Park — a rename on one filesystem, never a copy (a copy would stop half-way at aaPanel's immutable `.user.ini` and
leave two trees):

```bash
TODAY=$(date +%Y%m%d)
PARK=/root/onhost-staging-old-$TODAY
install -d -m 0700 $PARK                            # root only; the parked tree keeps its owners and modes (exact undo)
park() { # $1 = what, $2 = name under $PARK
  { [ -e "$1" ] || [ -L "$1" ]; } || { echo "absent: $1"; return 0; }
  [ "$(stat -c %d "$1")" = "$(stat -c %d "$PARK")" ] || { echo "STOP: $1 is on another filesystem than $PARK — see below"; return 1; }
  mv -T "$1" "$PARK/$2" && echo "parked $1 -> $PARK/$2"
}
install -d -m 0700 $PARK/systemd && cp -a /etc/systemd/system/onhost-* $PARK/systemd/ 2>/dev/null   # a copy for the record:
                                                    # install.sh re-renders the two unit files by the same names; the masks stay
cd /root                                            # no shell of this session stays inside the tree it moves
park $APP wwwroot && park /etc/onhost etc-onhost && park $STATE state
install -d -o www -g www -m 0755 $APP               # the empty site directory, as aaPanel creates one (www's; nginx keeps $APP/public)
ls -la $APP; ls -la $PARK; stat -c '%a %U %n' $PARK
```

`STOP … another filesystem`: the site tree lives on another filesystem than `/root` (e.g. a separate `/www`). Park it on
its own filesystem instead — `PARK_SITE=/www/onhost-staging-old-$TODAY; install -d -m 0700 $PARK_SITE`, then
`mv -T $APP $PARK_SITE/wwwroot` after the same `stat -c %d` comparison, then the other two `park` lines and the
`install -d … $APP` line — and record both park directories in the release record. Verify: `$PARK` (and `$PARK_SITE`) `700 root`; `$APP` empty and www's; `/etc/onhost` and `$STATE` absent.

**Recommended (reversible) — the old database cannot be reached by the new role:** the old role stops logging in and
new roles do not get `CONNECT` on the old database by default (PostgreSQL grants it to `PUBLIC`):

```bash
su - postgres -c "psql -c 'ALTER ROLE <old DB_USERNAME, S0> NOLOGIN' -c 'REVOKE CONNECT ON DATABASE <old DB_DATABASE, S0> FROM PUBLIC'"
# undo: ALTER ROLE <old> LOGIN; GRANT CONNECT ON DATABASE <old db> TO PUBLIC
```

The new database and role — the password is generated on the host and never printed; keep S1c's last block, S2 and S3
in **one** SSH session (the two passwords live in shell variables until S3 writes them into `app.env`):

```bash
NEWDB=onhost_staging_b; NEWROLE=onhost_b
DBPW=$(openssl rand -hex 24)
printf "create role %s login createdb password '%s';\n" "$NEWROLE" "$DBPW" | su - postgres -c psql   # CREATEDB: S6's restore drill
su - postgres -c "createdb -O $NEWROLE -E UTF8 $NEWDB"
su - postgres -c "psql -tAc \"select datname, datcollate, datctype from pg_database where datname='$NEWDB'\""   # record next to Q14
REDISPW=$(awk '/^requirepass/ {print $2}' /www/server/redis/redis.conf)
[ -n "$REDISPW" ] && echo redis-password-read || echo "STOP: Redis has no requirepass"
```

Redis is the host's one server, shared with the parked staging: the new install takes **other database numbers and
another prefix** than S0 printed for the old one — `REDIS_DB=2`, `REDIS_CACHE_DB=3` (any pair the old staging does not
use), `REDIS_PREFIX=onhost_staging_b_`, `CACHE_PREFIX=onhost_staging_b`. S3 writes them with `DB_DATABASE=$NEWDB`,
`DB_USERNAME=$NEWROLE`, `DB_PASSWORD="$DBPW"`, `REDIS_PASSWORD="$REDISPW"` (then `unset DBPW REDISPW`), and the S3 spec
pins them and refuses the old ones — append before `*UNLISTED=`:

```text
DB_DATABASE=onhost_staging_b
DB_USERNAME=onhost_b
REDIS_DB=2
REDIS_CACHE_DB=3
REDIS_PREFIX=onhost_staging_b_
CACHE_PREFIX=onhost_staging_b
DB_DATABASE!=<the old staging's DB_DATABASE, S0>
REDIS_PREFIX!=<the old staging's REDIS_PREFIX, S0 — onhost-database- when it was unset>
CACHE_PREFIX!=<the old staging's CACHE_PREFIX, S0 — onhost-cache- when it was unset>
```

Then S2, S3, S4 as written, with two differences. S4b: `install -m 0600 /dev/null $STATE/path-b` in the **new** state
dir (the old one is parked), and `$STATE/egress-blocked` preferably lists production's panel and node addresses with
the S0 GATE step 4 table and probe — the parked database holds live keys (question 13 b). S4, before `systemctl enable
--now`: the scheduler still carries S0 GATE's runtime mask, so lift it first — the unit file is now the one `install.sh`
rendered for the new tree:

```bash
systemctl cat onhost-scheduler.service | grep -E 'ExecStart|WorkingDirectory'   # the new tree's artisan (install.sh run 2)
systemctl unmask --runtime onhost-scheduler.service
```

The provider lanes stay masked (S0 GATE's lasting masks survive the re-render).

**Undo** (the whole Path B on this host; the owner decides, the new database stays until the owner drops it):

```bash
systemctl disable --now onhost-scheduler.service 'onhost-queue@default.service' 'onhost-queue@mails.service'
systemctl mask --runtime onhost-scheduler.service
UNDO=/root/onhost-staging-b-parked-$(date +%Y%m%d%H%M); install -d -m 0700 $UNDO
mv -T $APP $UNDO/wwwroot && mv -T /etc/onhost $UNDO/etc-onhost && mv -T $STATE $UNDO/state   # the new install, parked in turn
mv -T $PARK/wwwroot $APP && mv -T $PARK/etc-onhost /etc/onhost && mv -T $PARK/state $STATE   # $PARK_SITE/wwwroot if used
```

The old staging comes back **contained**: its units disabled and masked (the unit files are the new install's rendering;
the old ones are in `$PARK/systemd` for the record), its site in maintenance, its aaPanel cron and supervisor entries off.
It is Path A from here — nothing of it starts without the owner and the Path A steps. The deployer in
`/usr/local/sbin` stays installed; `/usr/local/lib/onhost-deploy/source-sha` names `STAGING_SHA`. If the recommended
PostgreSQL lines ran, their undo lines restore the old role.

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
# staging-launch.md S3 — KEY=value must equal · KEY= empty or absent · KEY? set · KEY!=value differ · KEY~=regex · PREFIX_*= family empty
#   · KEY alone: named, any value (reviewed) · *UNLISTED= every other key the file sets is refused (the allow-list)
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
MAIL_HOST=
MAIL_PASSWORD=
# or instead of the three lines above:  MAIL_MAILER=smtp  and  MAIL_HOST=<the sink host, O4>
PAYMENT_GATEWAY=comgate
COMGATE_TEST=true
COMGATE_RECURRING=false
COMGATE_MERCHANT=<the Comgate test merchant id, O4>
COMGATE_SECRET
GOPAY_RECURRING=false
STRIPE_RECURRING=false
PEPPOL_SENDER_ID=
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
# proxies: phpdotenv puts app.env into the environment (so does systemd's EnvironmentFile), and the HTTP clients honour
# both spellings — a proxy would carry provider traffic past every per-address rule (review round 2)
HTTP_PROXY=
HTTPS_PROXY=
ALL_PROXY=
NO_PROXY=
http_proxy=
https_proxy=
all_proxy=
no_proxy=
# the keys every install sets, reviewed once: named, any value
APP_NAME
APP_KEY
DB_HOST
DB_PORT
DB_USERNAME
DB_PASSWORD
REDIS_HOST
REDIS_PORT
REDIS_PASSWORD
# outward credentials: staging phase 1 holds none of them — single keys, and whole env:// families (PREFIX_*=)
ONHOST_CONSOLE_RELAY_URL=
ONHOST_CONSOLE_RELAY_KEY=
ONHOST_NODE_BOOTSTRAP_SSH_KEY=
ONHOST_GAME_OPERATOR_VARIABLES_REF=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
# or, with the staging-only bucket (O4), instead of the two lines above:  AWS_ACCESS_KEY_ID?  AWS_SECRET_ACCESS_KEY?
#   and  AWS_ACCESS_KEY_ID!=<production AWS_ACCESS_KEY_ID, question 14>
SENTRY_DSN=
LOG_SLACK_WEBHOOK_URL=
AI_ANTHROPIC_*=
AI_OPENAI_*=
GOPAY_*=
STRIPE_*=
PEPPOL_*=
OIDC_CLIENT_*=
DISCORD_BOT_*=
ONHOST_DISCORD_*=
ONHOST_ONCALL_*=
CLOUDFLARE_*=
OPENBAO_*=
OTEL_EXPORTER_OTLP_*=
SLACK_*=
POSTMARK_*=
RESEND_*=
PROXMOX_*=
PBS_*=
ISPCONFIG_*=
AAPANEL_*=
PTERODACTYL_*=
POWERDNS_*=
RKE2_*=
WEDOS_MAIN_*=
SUBREG_*=
# the allow-list (review round 2, security MEDIUM): every other key app.env sets needs a line — the draft below
*UNLISTED=
EOF
$EDITOR $STATE/expected-env                                     # fill the <…> lines
# the keys app.env sets that no line names yet (names only, never values) — review EACH: a tunable of this staging (a
# limit, a timeout, a locale, this host's own URL) → append the bare name; an address, a token, a secret, a reference,
# a proxy → a rule of its own (KEY= or KEY!=<production value>), or empty it in app.env; record the review
$P $DG env-assert --file /etc/onhost/app.env --spec $STATE/expected-env | sed -n 's/^UNLISTED \([A-Za-z0-9_]*\):.*/\1/p' > /root/expected-env.unlisted
$EDITOR /root/expected-env.unlisted && cat /root/expected-env.unlisted >> $STATE/expected-env   # only the reviewed tunables
$P $DG env-assert --file /etc/onhost/app.env --spec $STATE/expected-env; echo rc=$?   # before S4b: $P /root/deploy-gate.php env-assert …
```

`rc=0` and every line `OK`, else STOP (`MISMATCH`/`UNFILLED`/`UNLISTED` name the key, never its value; the deployer also
refuses a spec without the `*UNLISTED=` line; a key defined twice with
different values or a value with `${…}` is a `MISMATCH` whatever the line says — phpdotenv would load something the
assertion never saw). Why the less obvious lines:
`COMGATE_RECURRING=false` (and `GOPAY_`/`STRIPE_RECURRING=false`, which default to `true`; `PAYMENT_GATEWAY=comgate`
keeps the only test merchant the one in use) — the kept database may hold stored card tokens, and recurring top-ups
would charge them; `PEPPOL_SENDER_ID=` — no real e-invoice leaves; `MAIL_HOST=`/`MAIL_PASSWORD=` with the log mailer —
no live SMTP login lies in the file for a later edit or a stale cache to use;
`ONHOST_EGRESS_DENY_CIDRS=0.0.0.0/0,::/0` — every destination a *customer* names (webhooks, uptime checks, import URLs)
goes through `EgressGuard`, and this makes none public (VERIFIED: `EgressGuard::inCidr` matches every address for
`/0`): the kept database's unpublished outbox is relayed **inline by the first command any staff member runs**
(`CommandBus` relays after every command, and `WebhookDispatcher` posts to each new delivery at once) — without this
line S5 alone would send the backlog to real customer endpoints; the Discord/CDN/on-call keys empty — their
scheduled jobs and bots would otherwise act with production's identities. A `!=` line may be deleted only when S0
proved that the machine itself is staging's alone (record why).

The outward keys (review round 0, security MEDIUM — the spec used to miss them). A family line `PREFIX_*=` holds every
key of the file that starts with `PREFIX_` and has no line of its own empty or absent: `EnvSecretStore` reads an
`env://X` reference as every `X_*` key (`env://AI_ANTHROPIC`, `env://PROXMOX_CZ1`, …), so a live key under a name
nobody listed would otherwise pass — and a `*_SECRET_REF` naming a `db://` or `bao://` secret is refused by its family
too, not left to the revocation alone. The console relay's URL and key are empty: no console exists without panels,
and a key shared with production's relay would mint console tokens production accepts. The AWS key pair is empty with
the local disk; with the staging-only bucket it is set and its id is not production's (another id, another key).
`SENTRY_DSN`, the OTLP exporter and Slack would report staging into production's projects and channels;
`ONHOST_NODE_BOOTSTRAP_SSH_KEY` opens nodes. A tunable inside a family (`ONHOST_ONCALL_ESCALATE_MINUTES`) that must
stay gets a line of its own. S0's `grep … SECRET_REF` lists every `*_SECRET_REF` of the file: one outside these
families gets its own `KEY=` line (record it).

**Path A — the old code, until S7.** `env-assert` checks the file; the old release serves with whatever it cached in
`bootstrap/cache/config.php` (older installs ran `config:cache`) and may predate the egress deny list (`925f126`,
2026-09-20). So on Path A nothing reaches the old code over the web, no unit starts and no command goes through the bus
until S7 has deployed the target (review of the post-round-3 commits, HIGH):

```bash
cd $APP && $AS_WWW $P artisan down                 # payment call-backs included: 503 until after S7 (the deployer leaves
                                                   # an operator's maintenance in place)
$AS_WWW $P artisan config:clear && <reload command from S0>   # what artisan by hand (S4b's doctor) reads is app.env
$AS_WWW $P artisan tinker --execute="echo json_encode(['mail' => config('mail.default'), 'smtp_host' => config('mail.mailers.smtp.host'), 'deny' => config('onhost.egress.deny_cidrs')]), PHP_EOL;" | tail -n 1
[ -n "$AS_WWW" ] || own
```

Expect `"mail":"log"` (or `"smtp"` with the sink host) and `"deny":["0.0.0.0/0","::/0"]`. A different value: STOP —
PHP loads something the assertion did not see; report it. `"deny":null`: the old code cannot honour the line — record
it; the site stays down and S4b–S7 are the only next steps (nothing else may run on this code).

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
$G fetch -q --tags origin                           # $R, root's repository (S0) — never a .git in the site tree
$G show <STAGING_SHA>:infra/aapanel/install-deployer.sh > /root/install-deployer.sh
sha256sum /root/install-deployer.sh                 # = the release record
SHA=<STAGING_SHA> FIRST=1 bash /root/install-deployer.sh   # FIRST=1 only where no deployer is installed yet (refused
                                                             # otherwise); a later upgrade: SHA=<newer> bash … (forward only)
cat /usr/local/lib/onhost-deploy/source-sha         # = STAGING_SHA
```

The units that must run after every release (O12; the deployer refuses a release when one of them is not enabled or not
running beforehand, or when an `onhost-*` unit runs that is not listed — and fails it with rc 7 when one does not come
back):

```bash
# Path A: install -m 0600 /dev/null $STATE/expected-units    (EMPTY until S7 has run: the first release caches the
#   target's config from the asserted app.env; the two lanes are written, enabled and started only after it — S7,
#   Path A — never the scheduler)
# Path B: written by install.sh — review it
chmod 0600 $STATE/expected-units; cat $STATE/expected-units
# the addresses www must not reach (the deployer refuses a staging release without the file): Path A wrote it in the
# S0 GATE (step 4); Path B holds no live credential — an empty file, or production's panel addresses (recommended)
[ -f $STATE/egress-blocked ] || install -m 0600 /dev/null $STATE/egress-blocked
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
$WWW_CMD $P artisan onhost:staff:create <owner-email> --name="<name>" --role=platform_owner   # hidden password prompt
exit   # the prompt needed the terminal (own session, so no TIOCSTI): end this SSH login, continue in a fresh one
```

Each person signs in and enrols MFA themselves. **Never run `DevAccountSeeder` here.** A second approver only per O6.
Verify: the S0 tinker query shows 0 development accounts (an existing staging that has them: disable them in the staff
console and report — do not delete). **Path A: not here — only after S7, in its Path A block** (the first command
through the bus on the kept database relays the outbox backlog: it must run on the target, whose config S7 cached from
the asserted `app.env` and whose effective egress line was printed, never on the old code).

#### S5b — Demo accounts for walking through the surfaces (optional, staging only)

`onhost:demo:accounts` makes six sign-ins that share **one** password, under `@demo.onhost.cz` (`--domain=` changes it):
`zakaznik@` (owner of "Demo zákazník s.r.o.", CZ/CZK, no VAT number, attributed to the reseller), `reseller@` (partner,
model `share`, white-label scope `full`), `affil@` (partner, model `oneoff`), `partner@` (partner, `share`, default
terms), `podpora@` (staff, `support_manager`) and `admin@` (staff, `auditor_read_only` — read-only; `--admin-role=`
takes another global staff role, but never one holding `iam.approval.decide`: a shared password is never the second
person of four eyes). It makes users, organizations, partner records and role bindings only — no service, subscription,
provider binding, domain, invoice, wallet movement or ticket — so nothing a panel, provisioning or billing job could act
on. Unlike `DevAccountSeeder` (never here) it runs with `APP_ENV=production`, but only with the explicit flag. Staff go
through the command bus like `onhost:staff:create` (audit; MFA is enrolled at first sign-in, `onhost:staff:totp` helps).

```bash
# hidden prompt (needs the terminal: the WWW_CMD prefix, no </dev/null; end this SSH login afterwards, as above)
setpriv --reuid=www --regid=www --init-groups -- setsid --wait /www/server/php/85/bin/php /www/wwwroot/staging.onhost.cz/artisan onhost:demo:accounts --i-know-this-is-not-production
# or from stdin, without a terminal prompt and without the password in the shell history or the process list
read -rs PW && printf '%s\n' "$PW" | setpriv --reuid=www --regid=www --init-groups -- setsid --wait /www/server/php/85/bin/php /www/wwwroot/staging.onhost.cz/artisan onhost:demo:accounts --i-know-this-is-not-production --stdin; unset PW
```

(Use the PHP binary the site runs — `$P` above — if it is not `php/85`.) The password must pass the platform policy
(12+ characters, letters and numbers, not in a breach list). The output is a table of e-mail / role / organization /
partner / result, never the password. Re-running skips the accounts that exist (and asks no password when none is
missing); `--reset-password` sets a new shared password on the demo accounts. Only an account with **both** the
`demo_account` marker (`users.preferences`, `organizations.settings`) and the demo domain is ever changed again — a real
person holding one of the addresses is reported and left alone.

When the walk-through is over: `… onhost:demo:accounts --i-know-this-is-not-production --remove` disables them — state
`suspended` (sign-in refused), a random password, sessions, step-ups and personal API tokens ended, the partner records
suspended. Nothing is deleted (erasure is the owner's path: owner-only, step-up, 14 days); the organizations stay.
`--reset-password` brings them back. After the outbox relays, the partner approvals also give the demo partner
organizations the usual loyalty points (200, below the first paid level with the default levels) and in-app notices to
staff ("Nová partnerská přihláška", the white-label change) — expected, nothing leaves the platform.

### S7 — First gated deploy

Path B: the units of O12 run (S4), so the drain is exercised. Path A: the first run has none (the list is empty, the
site is in the maintenance S3 set, which the deployer leaves in place); the Path A block after the checks starts the
lanes and runs the same command a second time.

```bash
PHP="$P" REF=<STAGING_SHA> EXPECTED_SHA=<STAGING_SHA> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='<reload command from S0>' \
  /usr/local/sbin/onhost-deploy; echo rc=$?
```

`PHP=` is the site's PHP. Without it the deployer uses its default, `/www/server/php/83/bin/php`, which is the wrong PHP
on a staging that runs 8.5 (set `P=/www/server/php/85/bin/php` in the constants above; `infra/aapanel/staging.sh deploy`
passes it itself, see `staging-aapanel.md`).

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
- **Path A only, here** (the checks above belong to the first run; the ones below to the second):
  ```bash
  cd $APP && $AS_WWW $P artisan tinker --execute="echo json_encode(['cached' => app()->configurationIsCached(), 'mail' => config('mail.default'), 'smtp_host' => config('mail.mailers.smtp.host'), 'deny' => config('onhost.egress.deny_cidrs')]), PHP_EOL;" | tail -n 1
  [ -n "$AS_WWW" ] || own
  ```
  `"cached":true`, `"mail":"log"` (or `"smtp"` with the sink host) and `"deny":["0.0.0.0/0","::/0"]`, else STOP — the
  site stays down, nothing starts. Then, and only with question 13 b confirmed:
  ```bash
  $AS_WWW $P artisan up
  printf '%s\n' onhost-queue@default.service onhost-queue@mails.service > $STATE/expected-units   # never the scheduler
  systemctl enable --now onhost-queue@default.service onhost-queue@mails.service
  ```
  Then S5, then S7's command once more (the same SHA; rc 0 — this run drains the lanes) and the checks below on it.
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
| i | delete the line `app|APP_ENV is production` from `$STATE/expected-nonok` (a row that is never OK on staging), run | rc 5, `ROW-FAIL app|APP_ENV is production`; the site stays 503 | put the line back, run the same command → rc 0 (the deployer lifts its own maintenance) |
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
- the live endpoints stay unreachable: `probe` (S0 GATE step 4) prints nothing — after a reboot, `nft list table inet
  onhost_containment` first (the rule is loaded at boot, or the reboot is recorded and the rule restored);
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

- O1–O12 and all fourteen owner questions (1–14) recorded — an answer, or the default taken and marked as such; the
  release record lists the evidence below.
- S0: the isolation block confirmed by the owner (no shared database, Redis server + prefix, cache prefix, bucket,
  endpoint); PostgreSQL parity recorded.
- Path A: every S0 *secret=stored* credential and `*_SECRET_REF` revoked at its issuer, confirmed in writing before S5
  (question 13 b); every S0 `endpoint=` and node address on `$STATE/egress-blocked`, rejected by the host and the probe
  silent (S0 GATE step 4 — an empty list is NO-GO); the scheduler never started; the provider lanes masked.
- S1: 401 from outside (also with `X-Forwarded-For: 127.0.0.1` and `X-Real-IP: 127.0.0.1`), no `set_real_ip_from`
  trusting a range the operator does not control, 200 for `/v1/status` over loopback; no `.git` in the site tree and
  `$R` root's (S0/S1b); `fs.protected_hardlinks` 1; no proxy in the units', FPM's or www's ssh environment (S0);
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
  writing (release record). The deployer runs every artisan and composer call as www itself (review round 0), in a
  session of its own with no terminal (review round 1), and refuses a host where `setpriv` or `setsid --wait` cannot do that.
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
- The former precondition — the deployer's artisan/composer steps as www — is met since review round 0: root runs no
  site code (`install.sh` neither). What root still does in a tree www can write is the recorded residual: the git
  checkout, the per-file race of the guarded ownership repair, the rename of `VERSION` into place.
  A root-owned code tree (atomic release directories, D32.11) removes it; the owner decides on the go-live checklist
  whether production waits for it (handoff).
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

`setpriv` (util-linux) switches root to www with `--init-groups`, and www can read the tree and `app.env` (S0 prints
`uid=www`), and `setsid --wait` exists (util-linux 2.31+; an older host is refused, rc 2); bash has `/dev/tcp` and
coreutils `timeout` (the egress probe); nftables is available (the deployer reads `table inet onhost_containment`)
and the table survives a reboot the way S0 records; `getent ahosts` answers the way the application resolves;
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
