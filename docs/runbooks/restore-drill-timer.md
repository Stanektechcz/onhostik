# Restore drill timer (item A7)

The restore drill ([backups.md](backups.md), *Restore drill of the platform backup*) is required by the doctor row
**platform restore drill within 35 days**, but nothing starts it. `infra/systemd/onhost-restore-drill.service` and `.timer`
run `infra/aapanel/restore-drill.sh` once a month. **The repository enables nothing**: installing is an owner step with its
own "ano" (staging first, then production).

## Schedule and why `Persistent=false`

`OnCalendar=Tue *-*-01..07 04:30:00` (first Tuesday, after the 02:15 backup and the 03:15 verify), `RandomizedDelaySec=15min`.
A month missed while the host was down is **not** replayed at boot: a restore competing with recovery is worse than a late
drill. A missed month shows as the doctor warning and, after 35 days, `OnhostRestoreDrillOverdue`. A drill that never ran
(timestamp 0) does not alert on purpose — the doctor row says it.

## Credential (the password never lands in the unit or the env file)

The manual drill keeps `PGPASSWORD` in the shell. An unattended drill needs it somewhere: it goes into a root-only systemd
credential (one libpq pgpass line), loaded with `LoadCredential=` and read through `PGPASSFILE`. It is the application's own
role (CREATEDB, not a superuser — the script refuses anything else). This is a deliberate exception to "never in a file" and
part of the owner's decision to install the timer.

## Install (owner, on the application host, as root)

```bash
install -m 0644 infra/systemd/onhost-restore-drill.service infra/systemd/onhost-restore-drill.timer /etc/systemd/system/
install -d -m 0700 /etc/onhost/credentials
cat > /etc/onhost/restore-drill.env <<'ENV'
SITE=<site>
APP_DIR=/www/wwwroot/<site>
PHP=/www/server/php/85/bin/php
RUN_USER=www
PGHOST=127.0.0.1
PGPORT=5432
PGUSER=<application role>
ENV
chmod 0600 /etc/onhost/restore-drill.env
( umask 077; read -rs PW; printf '127.0.0.1:5432:*:%s:%s\n' '<application role>' "$PW" > /etc/onhost/credentials/restore-drill.pgpass; unset PW )
systemd-analyze verify /etc/systemd/system/onhost-restore-drill.service /etc/systemd/system/onhost-restore-drill.timer
systemctl daemon-reload
systemctl start onhost-restore-drill.service      # first drill by hand, watch it
journalctl -u onhost-restore-drill.service -n 100 --no-pager
systemctl enable --now onhost-restore-drill.timer  # only after the first drill exited 0
systemctl list-timers onhost-restore-drill.timer
```

Check: the doctor row is OK and `onhost_platform_restore_drill_ok` is 1.

## Outcome

Exit codes are the script's: `0` passed · `1` the restored copy failed a check (**an incident**, see backups.md) · `2` refused
before anything was created (configuration, credential, another drill running) · `3` export or restore failed. Any non-zero
exit fails the unit (`systemctl --failed`, journal).

## Roll back

```bash
systemctl disable --now onhost-restore-drill.timer
rm /etc/systemd/system/onhost-restore-drill.service /etc/systemd/system/onhost-restore-drill.timer
rm /etc/onhost/restore-drill.env /etc/onhost/credentials/restore-drill.pgpass
systemctl daemon-reload
```

Nothing else changes: the drill only ever creates and drops its own `onhost_drill_<time>` database, and the manual procedure
in backups.md stays valid.
