# Staging on aaPanel — `staging.sh` (E0, TASK-0082)

**Status:** PREPARED, not executed. The scripts were changed and tested in a sandbox only. Nobody ran anything on
`staging.onhost.cz` for this change. The owner (or a named operator) runs every step below on the host as root and
stops at the first ✖.

The ordered first-launch procedure is still `docs/runbooks/staging-launch.md`; the release and rollback model is
`docs/runbooks/release-and-rollback.md`. This page covers what `infra/aapanel/staging.sh` now does on an aaPanel host,
what to check after it, and how to go back.

## What changed and why

| Finding (audit 2026-10) | What happened on staging | What the scripts do now |
| --- | --- | --- |
| P0-5 | aaPanel loads `/usr/local/usranalyse/lib/libusranalyse.so` for every process through `/etc/ld.so.preload`. Under systemd's `ProtectSystem=` it segfaults every process of `www` (even `/bin/sh`). The queue and scheduler units died right after start and the deploy ended with rc 7. `ReadWritePaths` for the module's paths did not help. | While `/etc/ld.so.preload` names `usranalyse`, `staging.sh harden` writes `/etc/systemd/system/<unit>.d/10-aapanel-usranalyse.conf` for `onhost-queue@.service` and `onhost-scheduler.service`: `ProtectSystem=no` plus compensating hardening (see below). It writes one with `ProtectSystem=no` alone for `php-fpm-85.service`, but only if that unit exists. Then it runs `systemctl daemon-reload`. When the module is gone, it removes the drop-ins again. `install.sh` warns when it finds the module and writes the same drop-ins for the two units it renders. |
| P1-16 | `public/` is root's, so `npm run build` as `www` could not create `public/build`. | `harden` creates `public/build` and hands it to `www`. `public/` itself stays root's. |
| P0-6 | `setup` deployed whatever `VERSION` said, not the development tip. A re-run after a cut-short install "contained and parked" its own new tree, `/etc/onhost` and the DB password. | `setup` resolves the target commit once (the given SHA, else the development tip), prints it, records it in `<state>/setup-sha` and uses that commit for install, deployer and deploy. A tree with one of the script's own markers (`setup-sha`, `installing`, `installed`) is never parked again. |
| — | A failed release left nothing behind that said so. A frontend build failure was only printed. | Any failure in `setup`, `install` or `deploy` **parks** the release: `<state>/releases/<sha>.parked` records the stage, exit code, time, operator and the previous release. `<state>/releases/current` keeps naming the last good release, the rollback command is printed, and the script exits non-zero. A frontend build failure (or no Node 24) is now a failure with exit code 8. |

`<state>` is `/var/lib/onhost-deploy/staging.onhost.cz` (root only, 0700).

### Why the drop-in only while the module is preloaded

`ProtectSystem=strict` is a hardening of the units (`infra/systemd/*.service`). It is given up only where something
breaks it. The check runs at every `harden` (also run by `install` and `deploy`) and at `start`. A module that aaPanel
installs later gets its drop-in before the next deploy restarts the workers. A module that is removed gets the
hardening back at the next deploy. `NoNewPrivileges=true` and `PrivateTmp=true` stay in every case.

The `php-fpm-85` drop-in is only a precaution. PHP-FPM already serves the site under the module. The drop-in is
written only while the module is preloaded **and** `systemctl cat php-fpm-85.service` finds the unit. It holds
`ProtectSystem=no` alone, because the PHP-FPM master runs as root and needs its capabilities to switch to `www`. A
drop-in takes effect at the unit's next start. The deployer restarts the queue and scheduler units, but it only
*reloads* PHP-FPM. Restart PHP-FPM by hand only if it crashes.

### Compensating hardening on the worker units — verify each directive on the server

`ProtectSystem=no` gives up the read-only `/usr`, `/boot` and `/etc`. For the platform's own units, which are
unprivileged and run as `www`, the drop-in adds back what does not make `/`, `/usr` or `/etc` read-only:

| Directive | What it stops | Note |
| --- | --- | --- |
| `ProtectKernelTunables=yes` | writes to `/proc/sys`, `/sys` | uses systemd's mount namespace |
| `ProtectKernelModules=yes` | loading kernel modules; `/usr/lib/modules` hidden | uses systemd's mount namespace |
| `ProtectControlGroups=yes` | writes to the cgroup tree | uses systemd's mount namespace |
| `RestrictSUIDSGID=yes` | creating set-uid or set-gid files | seccomp only |
| `LockPersonality=yes` | changing the execution domain | seccomp only |
| `CapabilityBoundingSet=` | every capability (the workers need none) | no mount namespace |

None of this has been verified against usranalyse. The module is closed source, and the three mount-namespace
directives use the same mechanism as `ProtectSystem`, so they are the most likely to bring the crash back.
`PrivateTmp=true` also uses that mechanism and did run on staging. **Verify on the host once, with the module
loaded:**

```bash
S=/var/lib/onhost-deploy/staging.onhost.cz
bash /root/onhost-staging.sh harden
systemctl restart onhost-scheduler.service onhost-queue@default.service
sleep 10; systemctl is-active onhost-scheduler.service onhost-queue@default.service    # both "active"
journalctl -u onhost-scheduler.service --since -2min | grep -i -E 'segfault|SIGSEGV|core-dump' || echo "no crash"
systemctl show -p ProtectSystem,ProtectKernelTunables,ProtectKernelModules,ProtectControlGroups,RestrictSUIDSGID,LockPersonality,CapabilityBoundingSet onhost-scheduler.service
```

If a worker crashes, find the directive by leaving them out one at a time. Start with the three mount-namespace
directives. Name the directive (without `=…`) on a line of its own in `$S/usranalyse-omit`, run `harden` again and
restart. `harden` rewrites the drop-in on every run, so a hand edit of the drop-in would not last. The omit list does.

```bash
echo ProtectControlGroups >> $S/usranalyse-omit     # one directive name per line
chmod 0600 $S/usranalyse-omit
bash /root/onhost-staging.sh harden                 # rewrites the drop-ins without it, daemon-reload
systemctl restart onhost-scheduler.service onhost-queue@default.service
```

Record in the staging release record which directives are active. An empty omit list means every directive held.

### What "parked" means here (there is no release directory)

The site tree is not a set of release directories behind a `current` symlink. Root's repository
(`<state>/repo.git`) has the site folder as its work tree, and the gated deployer checks the new commit out in place.
"Keep the previous current" therefore means two things:

- `<state>/releases/current` (and the deployer's `last-good.json`) keeps naming the last good release.
- The printed rollback is `bash /root/onhost-staging.sh deploy <previous>`, which checks the previous commit out again
  through the gated deployer.

After rc 4, 5 or 7 the tree **already holds the parked commit**, and `VERSION` names it. That is why the script never
uses `VERSION` to decide what is current.

### Ownership and modes after `harden`

| Path | Owner | Mode | Notes |
| --- | --- | --- | --- |
| tracked code (`app/`, `config/`, `public/`, `routes/` …) | root | no group/other write | Inside each root-owned top-level code directory, entries owned by someone else are re-owned with `chown -h`, and group/other write is removed. A top-level code directory that is not root's is reported, not taken over: the run stops and is parked. |
| `storage/`, `bootstrap/cache/` | www | owner read/write, nothing for others | Same hand-over as the deployer (`repair_ownership`, entry by entry, never `chown -R`). |
| `public/build/` | www | 0755 | vite writes it. A symlink there is refused. |
| `vendor/`, `node_modules/` | www | — | composer and npm run as www. Not touched. |
| `/etc/onhost` | root:www | 0750 | |
| `/etc/onhost/app.env` | root:www | 0640 | Re-owned and re-moded, never opened by `harden`. `<site>/.env` must be this same file. |

## Owner steps on the server

Run as root, in a root shell (`sudo -i`), from any directory (the script changes to `/` itself).

### 1. Get the script at the commit you deploy

```bash
SHA=<40-character commit, the tip of development or the release record's>
curl -fsSL "https://raw.githubusercontent.com/Stanektechcz/onhostik/$SHA/infra/aapanel/staging.sh" -o /root/onhost-staging.sh
sha256sum /root/onhost-staging.sh      # compare with the release record, if it names one
```

### 2. Read-only check

```bash
bash /root/onhost-staging.sh check
grep -n usranalyse /etc/ld.so.preload || echo "usranalyse not preloaded"
```

`check` now prints one line about usranalyse. Either "not preloaded (the units keep ProtectSystem=strict)", or that
`harden` will write the drop-ins.

### 3a. Existing staging (already installed): harden, then deploy

```bash
bash /root/onhost-staging.sh harden
bash /root/onhost-staging.sh deploy "$SHA"
```

### 3b. Fresh staging: one setup

```bash
bash /root/onhost-staging.sh setup            # the development tip, resolved once and printed
# or: bash /root/onhost-staging.sh setup "$SHA"
```

The first lines say `setup target: <sha> (tip of development|given)`. The last line says
`Setup done: deployed commit <sha>`. If a run stops halfway, run the same command again. It continues and does not park
its own tree a second time.

## What to check afterwards

```bash
S=/var/lib/onhost-deploy/staging.onhost.cz
# 1. drop-ins exist exactly when the module is preloaded, and systemd uses them
ls -l /etc/systemd/system/{onhost-queue@,onhost-scheduler}.service.d/10-aapanel-usranalyse.conf
ls -l /etc/systemd/system/php-fpm-85.service.d/10-aapanel-usranalyse.conf 2>/dev/null || echo "no php-fpm drop-in (unit not under systemd)"
systemctl show -p ProtectSystem onhost-scheduler.service onhost-queue@default.service   # ProtectSystem=no while preloaded
systemctl show -p NoNewPrivileges,PrivateTmp,ProtectKernelTunables,CapabilityBoundingSet onhost-scheduler.service
#    NoNewPrivileges/PrivateTmp yes; the compensating directives as written (minus the omit list)
# 2. the workers stay up (the old failure was SIGSEGV seconds after start)
systemctl is-active onhost-scheduler.service onhost-queue@default.service onhost-queue@mails.service
journalctl -u onhost-scheduler.service --since -10min | grep -i -E 'segfault|SIGSEGV|core-dump' || echo "no crash"
# 3. which commit runs, and nothing parked
cat $S/releases/current; cut -d' ' -f1 /www/wwwroot/staging.onhost.cz/VERSION   # the same SHA
ls $S/releases/*.parked 2>/dev/null || echo "nothing parked"
cat $S/setup-sha 2>/dev/null                                                     # after setup: target= and deployed=
# 4. ownership and modes
stat -c '%U:%G %a %n' /www/wwwroot/staging.onhost.cz/public /www/wwwroot/staging.onhost.cz/public/build /etc/onhost /etc/onhost/app.env
#    expect root:root 755 public · www:www 755 public/build · root:www 750 /etc/onhost · root:www 640 app.env
ls /www/wwwroot/staging.onhost.cz/public/build/manifest.json                     # the vite build wrote it
# 5. the site and the doctor
bash /root/onhost-staging.sh status
```

`status` now also prints the current release, every parked release and the drop-ins.

## When a release is parked

The script printed `✖ release <sha> PARKED (stage <stage>, rc <n>)` and the rollback command.

```bash
cat /var/lib/onhost-deploy/staging.onhost.cz/releases/<sha>.parked
```

| stage / rc | Meaning | What to do |
| --- | --- | --- |
| `harden` / 2 | A drop-in could not be written, `public/build` is a symlink, storage or cache is not a real directory, a top-level code directory is not root's, or `.env` is not `/etc/onhost/app.env`. Nothing was deployed. | Fix what the ✖ line names, then run `deploy <sha>` again. |
| `deployer` / 2, 3 | Refused, or drain/backup failed. Nothing switched. | Read the deployer's output, fix, run again. |
| `deployer` / 4, 5, 7 | The tree holds the new code. The site stays in maintenance and the drained units stay stopped (the deployer's own recovery text is above). | Either fix and run `deploy <sha>` again, or go back (below). For rc 7 with usranalyse, first check that the drop-ins exist (`harden`). |
| `deployer` / 4 (after "answered 0") | The deployer reported success but `VERSION` does not name the commit. | Treat as failed. Compare `VERSION`, `last-good.json` and the deploy log. |
| `frontend` / 8 | The code is live but `public/build` is not this release's (no Node 24, `npm ci` or `npm run build` failed). | Install Node 24 or fix the build, then run `deploy <sha>` again. Or go back. |
| `check`, `install`, `start` | Setup stopped there. | Fix, then run `setup` again (it continues). |

A later successful deploy of the same commit renames the marker to `<sha>.parked-<time>.cleared` (kept as history).

## Rollback

1. **Code**: deploy the last good commit through the gated deployer. It is printed by the parked run and stored in
   `releases/current`.

   ```bash
   bash /root/onhost-staging.sh deploy "$(cat /var/lib/onhost-deploy/staging.onhost.cz/releases/current)"
   ```

   **Migrations.** After a deployer rc 4, 5, 6 or 7, and after a frontend failure (stage `frontend`), the target's
   migrations may already have run. The script prints this warning with the park. Deploying the previous commit puts
   the old code onto the new schema. That works only because migrations are additive for one release
   (`release-and-rollback.md` § Before a release). It does not undo them. Read `release-and-rollback.md` § Rollback before going
   back. A database restore is never automatic and needs the owner.
2. **One compensating directive** that crashes the workers: add it to the omit list (section "Compensating hardening"
   above), not to the drop-in.
3. **Drop-ins** (only if they cause a problem): remove them and reload. The unit's own `ProtectSystem=strict` applies
   again at its next start. With usranalyse still preloaded, the workers will crash again (rc 7). The next
   `harden`/`deploy` writes the drop-ins back while the module is preloaded.

   ```bash
   rm -f /etc/systemd/system/{onhost-queue@,onhost-scheduler,php-fpm-85}.service.d/10-aapanel-usranalyse.conf
   systemctl daemon-reload
   systemctl restart onhost-scheduler.service onhost-queue@default.service onhost-queue@mails.service
   ```
4. **Ownership**: `public/build` back to root (`chown -h root:root public/build`) only if the frontend is built some other
   way. The next `harden` hands it to www again. Do not hand `storage/` or `bootstrap/cache/` back to root: the site
   cannot write its cache and logs then.
5. **The script itself**: the previous version is
   `https://raw.githubusercontent.com/Stanektechcz/onhostik/<previous sha>/infra/aapanel/staging.sh`. The state it
   writes (`releases/`, `setup-sha`) is ignored by older versions.

## What the tests cover, and what they do not

`tests/Feature/Platform/DeployGateTest.php` § E0 runs `staging.sh` against stubs in
`infra/aapanel/ci/staging-sandbox.sh`, and runs `install.sh` against the existing deploy sandbox. The CI
`deploy-scripts` job runs `bash -n` and ShellCheck over `infra/aapanel/*.sh` and `infra/aapanel/ci/*.sh`.

Covered:
- drop-in written only while preloaded, removed when not, no reload when unchanged;
- the compensating directives on the worker units, and the omit list;
- the PHP-FPM drop-in only when the unit exists, with `ProtectSystem=no` alone;
- install.sh warning and drop-ins;
- `setkey`: a value with backslashes written intact and never on awk's command line;
- the `public/build` hand-over, and a symlink there refused;
- group-writable code closed, with a link in the code never chmodded (Linux CI only);
- app.env `root:www 0640` with no read;
- park on deployer rc 2/5/7, on a frontend failure, on no Node 24, and on a VERSION mismatch;
- the migrations warning after the switch;
- `current` kept, the park cleared by a good release;
- setup at the development tip, recorded and printed, not parked on a re-run, parked on a failing step, not parked
  when the read-only host check fails;
- the deployer mode refusing anything but a full SHA;
- the root-only doctor reports.

Not covered (needs the real host):
- that systemd really applies the drop-in under aaPanel's module, i.e. the workers stay up;
- which compensating directives usranalyse tolerates (the verification above);
- the real `chown`/`chmod` (the stubs only log them);
- PHP-FPM under a sysv-generated unit;
- vite writing into a www-owned `public/build` on the server.

Run the checks in "What to check afterwards" once and record the result in the staging release record.
