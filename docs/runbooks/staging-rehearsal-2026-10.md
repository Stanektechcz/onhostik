# Staging rehearsal of go-live, October 2026 (H6)

**Status: PREPARED, nothing executed.** This page was written without any access to a server. Every command was checked against
the repository only (section "Command check" at the end). The owner (or a named operator he sits next to) runs the steps on
`staging.onhost.cz`, **in order, one at a time**, and stops at the first result that differs from "Expected".

It is the rehearsal of [go-live-checklist.md](go-live-checklist.md) sections 0, 9 and 10, run on staging so that production is the
second time. The day after go-live is [first-day-production.md](first-day-production.md); the aaPanel hardening is
[staging-aapanel.md](staging-aapanel.md); custom ISO, Penpot and the VAT mode are [custom-iso.md](custom-iso.md),
[penpot.md](penpot.md) and [vat-payer-mode.md](vat-payer-mode.md). Where this page and one of them disagree, the runbook wins and
this page gets a fix.

## Rules of the rehearsal

1. **Every step needs the owner's explicit yes before it runs.** Each step ends with `Owner approval: ☐ yes`; tick it (and write
   the time in the protocol) before the command is typed. One yes covers one step, never the next one.
2. **No secret on this page, in a ticket, in chat or in a shell history.** Passwords, TOTP secrets, recovery codes, API keys and the
   clamd/Proxmox/Turnstile values are typed or pasted by the owner on the server (hidden prompt, `read -s`, an editor on the env
   file). A placeholder in angle brackets (`<…>`) is something the owner fills in.
3. **Staging only.** Nothing here is run against production. `ONHOST_VAT_PAYER`, TRUSTED_PROXIES and every other value are the
   staging values; production gets its own pass of [go-live-checklist.md](go-live-checklist.md).
4. **Read before write.** Every revision and every audit command is first a dry run; the `--apply` is its own step with its own yes.
5. **Never edit a row in the database**, never roll a money or audit row back. Corrections are new documents.
6. After **every** step run the doctor line of that step and write the result into the protocol (section "Protocol"). A row that is
   not OK and was not expected stops the rehearsal.
7. A step that fails is rolled back with its own "Rollback" line, then the rehearsal stops; the cause is written into the protocol
   and into the release record before anything is repeated.

## Variables (once per shell, as root on the staging host)

Taken from [staging-launch.md](staging-launch.md) (S-block). Nothing here is a secret.

```bash
SITE=staging.onhost.cz
APP=/www/wwwroot/$SITE
P=/www/server/php/83/bin/php                       # the PHP 8.3+ CLI the deploy and the units use (checklist 0, step 1)
S=/var/lib/onhost-deploy/$SITE                     # the deployer's state directory (root only)
DG=/usr/local/lib/onhost-deploy/deploy-gate.php    # the installed gate (staging-launch.md S4b)
if setpriv --reuid=www --regid=www --init-groups -- true 2>/dev/null; then
  AS_WWW="setpriv --reuid=www --regid=www --init-groups -- setsid --wait"
else
  echo "STOP: no way to run artisan as www (staging-launch.md note after S0)"; fi
cd "$APP"
art() { $AS_WWW $P artisan "$@"; }                 # every artisan call below runs as www, like the deployer
doc() { art onhost:doctor --json; }                # full doctor as JSON
row() { doc | jq -r --arg r "$1" '.checks[] | select(.check == $r) | "\(.area)|\(.check)  \(.status)  \(.detail)  remedy: \(.remedy)"'; }
mkdir -p /root/rehearsal-2026-10 && chmod 0700 /root/rehearsal-2026-10
```

`row "<check name>"` prints one doctor row by its name (the *check* column; the JSON is `{environment, fail, warn, checks:[{area, check, status, detail, remedy}]}`). If `jq` is missing, `art onhost:doctor | grep -i "<name>"`
reads the same row. (If `row` prints nothing, the check name was mistyped: copy it from `art onhost:doctor`.)

Every step below that writes into an environment file edits the **root-owned** `/etc/onhost/app.env` (the same file as
`$APP/.env`, [staging-aapanel.md](staging-aapanel.md) *Ownership*), by hand in an editor, never with `echo >>` from a command line that
lands in history. After any change of it: `art config:cache` and `systemctl restart 'onhost-queue@*' onhost-scheduler` (restart the
workers after every change of configuration).

---

## Phase 0 — Baseline

### R0. Release and the three gate lists

* **Purpose:** know exactly which commit is on staging and that the deployer's lists exist before any change.
* **Precondition:** staging is installed and deployed through `staging.sh` ([staging-aapanel.md](staging-aapanel.md)); `$S` exists.
* **Command:**
  ```bash
  cat $S/releases/current; cut -d' ' -f1 $APP/VERSION
  ls -l $S/expected-units $S/expected-nonok $S/expected-env $S/egress-blocked
  bash /root/onhost-staging.sh status
  ```
* **Expected:** the two SHAs are equal; the four files exist (root-owned, 0600); `status` shows nothing parked.
* **Verify:** `bash /root/onhost-staging.sh status` ends without a ✖ line.
* **Rollback:** none (read only). Anything parked: [staging-aapanel.md](staging-aapanel.md) *When a release is parked*.
* Owner approval: ☐ yes

### R1. The doctor before anything is touched

* **Purpose:** the starting picture; every later change is compared with it.
* **Precondition:** R0 done.
* **Command:**
  ```bash
  doc > /root/rehearsal-2026-10/doctor-R1.json; echo "rc=$?"
  art onhost:doctor | tee /root/rehearsal-2026-10/doctor-R1.txt | tail -60
  ```
* **Expected:** rc 0, **0 FAIL** (a FAIL here is a defect of staging, not part of this rehearsal). WARN rows are the open items this
  page clears; each carries a *Remedy*.
* **Verify:** the rows this page watches — `PHP binary is the one the deploy and the workers must use`, `trusted proxies are exact
  addresses`, `cache store is shared (rate limits)`, `every catalogue revision is applied`, `catalogue revision
  2026-10-deliverable-web-plans applied`, `staff and demo accounts have an authenticator`, `exchange rates are fresh`, `API tokens must
  name an organisation (R9)`, `VAT payer mode agrees with the legal entity`, `custom ISO virus scan passes its self-test`, `custom ISO
  storage has room`, `rotated webhook secrets overlap only briefly`, `no outbox dead letters` — are noted in the protocol with their
  starting status.
* **Rollback:** none (read only).
* Owner approval: ☐ yes

### R2. PHP binary, one for the deploy and the units (checklist 0 / step 1)

* **Purpose:** the deployer, artisan by hand and the systemd units use the same PHP 8.3+.
* **Precondition:** R1 done.
* **Command:**
  ```bash
  $P -v | head -1
  grep -n '^ExecStart' /etc/systemd/system/onhost-queue@.service /etc/systemd/system/onhost-scheduler.service
  row "PHP binary is the one the deploy and the workers must use"
  ```
* **Expected:** `PHP 8.3.x` or newer; both units name the same `$P` path; the row is OK and shows version and path.
* **Verify:** the row above.
* **Rollback:** none needed; redeploy with the right `PHP=` (`PHP=$P … onhost-deploy`).
* Owner approval: ☐ yes

---

## Phase 1 — aaPanel and the host (usranalyse, proxy, cache)

### R3. usranalyse directives (the P0-5 / E0 drop-ins)

* **Purpose:** prove that the workers stay up under aaPanel's `libusranalyse.so` with the compensating hardening, and record which
  directives (if any) had to be left out.
* **Precondition:** R1 done; `staging.sh` at the commit being rehearsed ([staging-aapanel.md](staging-aapanel.md), owner step 1).
* **Command (read first):**
  ```bash
  grep -n usranalyse /etc/ld.so.preload || echo "usranalyse not preloaded"
  bash /root/onhost-staging.sh check
  ```
  **Command (apply and verify — only when the module is preloaded or the drop-ins are expected):**
  ```bash
  bash /root/onhost-staging.sh harden
  systemctl restart onhost-scheduler.service onhost-queue@default.service
  sleep 10; systemctl is-active onhost-scheduler.service onhost-queue@default.service onhost-queue@mails.service
  journalctl -u onhost-scheduler.service --since -2min | grep -i -E 'segfault|SIGSEGV|core-dump' || echo "no crash"
  ls -l /etc/systemd/system/{onhost-queue@,onhost-scheduler}.service.d/10-aapanel-usranalyse.conf
  ls -l /etc/systemd/system/php-fpm-85.service.d/10-aapanel-usranalyse.conf 2>/dev/null || echo "no php-fpm drop-in (unit not under systemd)"
  systemctl show -p ProtectSystem,NoNewPrivileges,PrivateTmp,ProtectKernelTunables,ProtectKernelModules,ProtectControlGroups,RestrictSUIDSGID,LockPersonality,CapabilityBoundingSet onhost-scheduler.service
  stat -c '%U:%G %a %n' $APP/public $APP/public/build /etc/onhost /etc/onhost/app.env
  cat $S/usranalyse-omit 2>/dev/null || echo "omit list empty (every directive held)"
  ```
* **Expected:** `harden` ends without ✖; units `active` ten seconds after the restart; "no crash"; with the module preloaded the drop-ins
  exist and `ProtectSystem=no`, `NoNewPrivileges=yes`, `PrivateTmp=yes`, the compensating directives as listed in
  [staging-aapanel.md](staging-aapanel.md); modes `root:root 755 public`, `www:www 755 public/build`, `root:www 750 /etc/onhost`,
  `root:www 640 app.env`.
* **Verify:** doctor rows `scheduler running` and `queue worker alive` (area `automation`) OK six minutes after the restart (they are
  not judged while a unit is stopped). If a worker crashed: leave out one directive at a time, mount-namespace ones first
  (`ProtectKernelTunables`, `ProtectKernelModules`, `ProtectControlGroups`): `echo ProtectControlGroups >> $S/usranalyse-omit;
  chmod 0600 $S/usranalyse-omit; bash /root/onhost-staging.sh harden`, restart, check again. **Write the active directives (the omit
  list) into the release record.**
* **Rollback:** `rm -f /etc/systemd/system/{onhost-queue@,onhost-scheduler,php-fpm-85}.service.d/10-aapanel-usranalyse.conf;
  systemctl daemon-reload; systemctl restart onhost-scheduler.service onhost-queue@default.service onhost-queue@mails.service` — with
  the module preloaded the workers then crash again (rc 7); the next `harden` writes the drop-ins back.
* Owner approval: ☐ yes

### R4. TRUSTED_PROXIES: loopback only (checklist 0 / step 2)

* **Purpose:** the origin trusts only the proxy that really sits in front of it. On staging that is the local nginx on the same host,
  so the value is the **loopback addresses and nothing else**. (Open owner question for production: the exact address(es) of the
  reverse proxy / CDN in front of the origin; do not guess them.)
* **Precondition:** R3 done; the owner confirms staging is served by the local nginx with no CDN in front.
* **Command:** the owner adds the line to the **process environment** (a systemd drop-in for `onhost-queue@.service` /
  `onhost-scheduler.service` and the PHP-FPM pool `env[…]`; **not** only `.env` — `bootstrap/app.php` reads `env()`, which
  `config:cache` skips for `.env`, checklist 0 / step 2):
  ```
  TRUSTED_PROXIES=127.0.0.1,::1
  ```
  then:
  ```bash
  art config:cache
  systemctl daemon-reload && systemctl restart onhost-scheduler.service 'onhost-queue@*'
  systemctl reload php-fpm-85 2>/dev/null || /etc/init.d/php-fpm-83 reload      # the pool that serves staging
  row "trusted proxies are exact addresses"
  ```
* **Expected:** the row is OK (never `*`: a wildcard is FAIL in production, WARN on staging); `curl -sI https://$SITE/up` still answers.
* **Verify:** the row; and, from outside, `curl -s https://$SITE/up -H 'X-Forwarded-For: 203.0.113.9' -o /dev/null -w '%{http_code}\n'`
  must not change the client address the platform logs (a spoofed header from a non-trusted peer is ignored).
* **Rollback:** remove the line, `art config:cache`, restart; every client then appears as the proxy (rate limits are shared) but
  nothing is spoofable.
* Owner approval: ☐ yes

### R5. Shared cache store for the rate limits (checklist 0 / step 3)

* **Purpose:** the rate limiters count across all workers.
* **Precondition:** Redis runs and is reachable on staging; R4 done.
* **Command:** the owner edits `/etc/onhost/app.env`: `CACHE_STORE=redis` and an own `CACHE_PREFIX=<staging-prefix>` (Redis password,
  if any, typed by the owner), then:
  ```bash
  art config:cache
  systemctl restart onhost-scheduler.service 'onhost-queue@*'
  art cache:clear 2>&1 | tail -1
  row "cache store is shared (rate limits)"
  ```
* **Expected:** the row is OK (store `redis`).
* **Verify:** the row; `art tinker --execute="Cache::put('rehearsal', 1, 60); echo Cache::get('rehearsal');"` prints `1`.
* **Rollback:** set the previous store back, `art config:cache`, restart; limits become per worker again.
* Owner approval: ☐ yes

---

## Phase 2 — Catalogue revisions

`onhost:catalog:revise` without an id acts on **every** pending revision; the proposal `2026-10-custom-iso` is (since H-R7 the Penpot revision `2026-10-penpot-on-sale` is an ordinary revision, see R9/R20) — proposals are
only acted on by their id. **Always pass the id.** Four eyes apply to `--apply` unless `ONHOST_FOUR_EYES=false` (staging-launch.md O6:
a recorded solo-owner choice; the system actor applies it from the command line). A revision publishes **new plan versions** and
never edits old ones: customers keep what they hold.

### R6. Revision `2026-10-deliverable-web-plans`, dry run

* **Purpose:** read what the revision will withdraw (mailboxes in e-shop plans, a dedicated IPv4 on web products) before it is applied.
* **Precondition:** R1 done.
* **Command:** `art onhost:catalog:revise 2026-10-deliverable-web-plans`
* **Expected:** a list of plans and the keys that will be dropped from their new versions; **nothing is written** (dry run). If it
  says `Nothing pending`, the revision was already applied: stop and note it in the protocol.
* **Verify:** the owner reads the list against the price list; `art onhost:catalog:revise` (no id) shows the same pending set without
  the proposals.
* **Rollback:** none (read only).
* Owner approval: ☐ yes

### R7. Revision `2026-10-deliverable-web-plans`, apply

* **Purpose:** plans stop promising what their server cannot deliver (owner decision R4 of the 2026-10 audit).
* **Precondition:** R6 read and accepted by the owner; no catalogue change in flight.
* **Command:**
  ```bash
  art onhost:catalog:revise 2026-10-deliverable-web-plans --apply
  row "catalogue revision 2026-10-deliverable-web-plans applied"
  row "every catalogue revision is applied"
  ```
* **Expected:** new plan versions published; both rows OK. (`--yes` skips the confirmation prompt; leave it out and answer the
  prompt by hand.)
* **Verify:** the rows; the plan editor shows the new versions with the same prices.
* **Rollback:** a revision is not edited back: publish the previous plan versions in the plan editor (a new version through the
  catalogue); customers keep what they hold.
* Owner approval: ☐ yes

### R8. Revision `2026-10-custom-iso`, dry run (**decision first**)

* **Purpose:** read which plans would sell a custom ISO. The **owner has to approve the revision (which plans, 4096 MB per image;
  [proposal](../proposals/custom-iso-plans.md)) before the dry run is meaningful**; it is run now only to read it.
* **Precondition:** owner decision on the proposal recorded with the date. The ISO infrastructure (R13–R17) need **not** exist for a
  dry run, but must for R9's apply (R18).
* **Command:** `art onhost:catalog:revise 2026-10-custom-iso`
* **Expected:** every plan of the proposal listed with `custom_iso null → true; custom_iso_max_mb null → 4096` (`vps/compute-4/8/16`,
  `vds/vds-4/8/16`); nothing written.
* **Verify:** the list equals the table in `docs/proposals/custom-iso-plans.md`.
* **Rollback:** none (read only).
* Owner approval: ☐ yes

### R9. Revision `2026-10-penpot-on-sale`, dry run (H-R7: an ordinary revision since 2026-10-07)

* **Purpose:** read what putting Penpot on sale does (owner decision H-R7 of 2026-10-07, TASK-0128): the product `penpot` priced at
  29 Kč a month (a year = 12 months) and the per-tariff rules "included" for every web hosting tariff. A plain
  `art onhost:catalog:revise` lists it with the other pending revisions.
* **Precondition:** none; read only.
* **Command:** `art onhost:catalog:revise 2026-10-penpot-on-sale`
* **Expected:** `create product penpot (…) with its plan(s) at the defined monthly prices …, state active` (or `price product penpot`
  on a catalogue that applied the old proposal) and `write the Penpot rules per web hosting tariff`; nothing written.
* **Verify:** the owner reads it; the apply is **R20**.
* **Rollback:** none (read only).
* Owner approval: ☐ yes

---

## Phase 3 — People, money, tokens

### R10. Staff authenticators (TOTP) — the owner does this himself (checklist 0 / step 6)

* **Purpose:** every staff account has a second factor; the doctor row stops being WARN.
* **Precondition:** a staff account exists (`<email>`); the owner holds the authenticator app; **nobody else is looking at the screen**.
* **Command (per account, typed by the owner):**
  ```bash
  art onhost:staff:totp <email>                        # prints the secret ONCE: the owner puts it into the authenticator
  art onhost:staff:totp <email> --code=<6 digits>      # confirms; recovery codes are shown ONCE
  row "staff and demo accounts have an authenticator"
  ```
  Do **not** paste the secret, the code or the recovery codes anywhere (not into the protocol, not into chat). The recovery codes
  go into the owner's password manager.
* **Expected:** the first call prints a secret and an enrolment line; the second says the authenticator is confirmed and prints the
  recovery codes; the row is OK (0 accounts missing).
* **Verify:** the row; a staff login on staging asks for the code.
* **Rollback:** `art onhost:staff:totp <email> --reset` starts over with a new secret. `ONHOST_STAFF_MFA_REQUIRED` stays `true`.
* Owner approval: ☐ yes

### R11. Exchange rates of the national bank (checklist 0 / step 5)

* **Purpose:** the CZK recap of EUR documents has a fresh rate.
* **Precondition:** outbound access to the ČNB list from staging (if the egress list blocks it, say so in the protocol and stop).
* **Command:** `art onhost:fx:sync && row "exchange rates are fresh"`
* **Expected:** the sync prints the day it fetched; the row is OK; the scheduler keeps it (weekdays 14:40 and daily 06:10 in
  `routes/console.php`).
* **Verify:** the row.
* **Rollback:** none (data only).
* Owner approval: ☐ yes

### R12. API tokens that name no organisation (checklist 0 / step 10)

* **Purpose:** **order matters** — first look, then tell the holders, then switch. The rehearsal does the look, the notice text and the
  switch on staging.
* **Precondition:** none for the dry run.
* **Command:**
  ```bash
  art operator:tokens:unbound --dry-run
  art operator:tokens:unbound --dry-run --past-cap
  art operator:tokens:unbound --dry-run --days=90
  ```
* **Expected:** two read-only lists (tokens bound to no organization; live tokens with no end or ending after the cap, R9). On a
  clean staging both are empty. Nothing is written (`--dry-run` is the only mode).
* **Verify:** each listed holder is told (staging: the owner confirms the notice text); **only then** the owner edits
  `/etc/onhost/app.env`: `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true`, `art config:cache`, restart the workers, then
  `row "API tokens must name an organisation (R9)"` is OK and a token stored with no organization gets 403 `token_unbound`.
* **Rollback:** set the switch back to `false`, `art config:cache`; tokens work as before.
* Owner approval: ☐ yes (the dry run) · ☐ yes (the switch, after the notice)

### R13. Reinstatement audit (pay and restore)

* **Purpose:** list the services that pay-and-restore concerns touch (unbilled undone cancellations, paid services awaiting
  removal, payment holds, lost carried sites) before anything is switched on; **the dry run is the default and never writes**.
* **Precondition:** none.
* **Command:** `art onhost:billing:reinstatement-audit --dry-run`
* **Expected:** four lists, empty on a clean staging (up to 500 rows each). `--apply --service=<id>` restarts the billing of **one**
  service from the unbilled list and is **not** part of this rehearsal unless the owner names a service.
* **Verify:** the doctor row *pay and restore (services.reinstate)* (area `billing`) shows its state and the switch it needs; the
  rest is the owner's reading of the lists.
* **Rollback:** none (read only).
* Owner approval: ☐ yes

### R14. VAT entity check (ONhost is **not** a VAT payer: `ONHOST_VAT_PAYER=false`)

* **Purpose:** the seller's legal entity and the declared mode agree **before the first document is issued** (a document keeps the
  seller it was frozen with). Owner decision (G-1, checklist §10): **ONhost is not a VAT payer on the day of the first invoice.**
* **Precondition:** R1 done; the real IČO, name and bank account are known (the DIČ stays empty or absent for a non-payer — nothing
  is invented). Placeholders such as the seeded `CZ00000000` must not remain.
* **Command:** the owner edits `/etc/onhost/app.env`: `ONHOST_VAT_PAYER=false` **before** the legal-entity step (otherwise, with the
  default `true`, the VAT rows report FAIL in between), and the `ONHOST_LEGAL_*` / `ONHOST_BANK_*` values (typed by the owner), then:
  ```bash
  art config:cache
  art onhost:production:prepare --legal                  # writes the declared mode only into an entity it CREATES, never over an existing one
  art onhost:vat:payer-mode; echo "rc=$?"                # read only; 1 while declaration and legal entity disagree; --apply is refused
  row "VAT payer mode agrees with the legal entity"
  row "legal entity identification"
  row "legal entity bank details real"
  ```
  `onhost:production:prepare` without `--legal` also offers `--purge-dev-accounts` and `--cache`: **do not add them on staging**
  unless the owner asks; the demo accounts are the staging test accounts.
* **Expected:** `onhost:vat:payer-mode` shows the mode **non-payer** (`false`) and exits 0; the three rows OK. A legal entity that
  already exists with the other mode is **not** rewritten by the command: switching it is a person's CRITICAL action (finance with a
  fresh step-up + a second person, or the sole approver's time lock): `POST /v1/staff/tax/vat-payer-mode {payer:false, reason}` →
  403 `approval_required` → another person approves (`POST /v1/staff/approvals/{id}/decision`) → the same request with
  `approval_ids:[id]`. **Never from the command line.**
* **Verify:** the rows; `art onhost:vat:export kh --period=<YYYY-MM> --format=csv` lists no documents for a non-payer (KH/SH drafts
  contain none); audit action `tax.vat_payer_mode.set` after a switch. Before any document exists a wrong mode costs nothing;
  afterwards only new documents are affected.
* **Rollback:** before any document exists: the same route with the other mode. After one: only a new document is affected, never an
  issued one.
* Owner approval: ☐ yes

---

## Phase 4 — Custom ISO infrastructure (G-4 … G-8)

The ISO doctor rows (`custom ISO virus scan passes its self-test`, `custom ISO storage has room`) stay OK **without looking at clamd or
the disk** while no plan sells custom ISO; steps R15–R17 are therefore verified **by hand**. They are judged for real only after R18.

### R15. clamd (G-4)

* **Purpose:** the virus scanner covers the largest image sold and flags what it could not read.
* **Precondition:** clamd runs somewhere reachable from the staging host (Ansible role `onhost_clamav` or the compose service).
* **Command:** the owner sets `ONHOST_CLAMAV_HOST`, `ONHOST_CLAMAV_PORT` (3310) in `/etc/onhost/app.env`; in `clamd.conf`
  `StreamMaxLength`, `MaxScanSize`, `MaxFileSize` ≥ `ONHOST_CUSTOM_ISO_SCAN_MAX_MB` (at most 4096) and `AlertExceedsMax yes`; reload
  clamd; then:
  ```bash
  art config:cache
  grep -E '^(StreamMaxLength|MaxScanSize|MaxFileSize|AlertExceedsMax)' /etc/clamd.d/scan.conf /etc/clamav/clamd.conf 2>/dev/null
  art onhost:isos:scanner-check --fresh; echo "rc=$?"
  ```
* **Expected:** `custom ISO virus scan: OK — …` (EICAR found **and** a file beyond the limits reported), rc 0. `FAILED` means every
  upload would be refused with 503 (fail closed) — fix clamd, never bypass it.
* **Verify:** by hand (the doctor row does not look yet). Also the doctor row for the virus-scanner signatures is fresh.
* **Rollback:** unset `ONHOST_CLAMAV_HOST`, `config:cache`; with no custom ISO on sale nothing else depends on it.
* Owner approval: ☐ yes

### R16. ISO storage volume (G-5) and PHP / nginx limits (G-6)

* **Purpose:** a dedicated volume outside the web root for the images, and a web stack that takes a 4 GB upload.
* **Precondition:** a volume exists (mounted at e.g. `/srv/onhost-isos`), sized for the organizations × `ONHOST_CUSTOM_ISO_ORG_QUOTA_MB`
  (20480).
* **Command:**
  ```bash
  ROOT=/srv/onhost-isos                               # the owner's choice; the same value as ONHOST_CUSTOM_ISO_ROOT
  findmnt "$ROOT"; stat -c '%U %a' "$ROOT"; df -h "$ROOT"; realpath "$ROOT"
  chown www:www "$ROOT"; chmod 0750 "$ROOT"            # only if the stat line above is wrong
  # the owner sets ONHOST_CUSTOM_ISO_ROOT=/srv/onhost-isos in /etc/onhost/app.env, then:
  art config:cache
  # PHP / nginx (owner edits, then reload):
  #   php.ini / pool: upload_max_filesize, post_max_size >= the largest plan size (+ a few MB); max_execution_time,
  #                   request_terminate_timeout, upload_tmp_dir with room for one image per concurrent upload
  #   nginx:          client_max_body_size, client_body_timeout, proxy_read_timeout long enough for upload + scan
  $P -r 'echo ini_get("upload_max_filesize"), " ", ini_get("post_max_size"), PHP_EOL;'
  nginx -t && systemctl reload nginx
  ```
* **Expected:** a dedicated mount; owner is the PHP/queue user (`www`) and mode `750`; free space above quota × organizations;
  `realpath` is **not** under `$APP/public`; the PHP and nginx limits cover 4 GB + a few MB; `nginx -t` OK.
* **Verify:** by hand. An upload of an image of the largest sold size on **staging** (manual test `docs/manual-tests/05-vps.md`)
  after R18 and R19. The row *custom ISO storage has room* shows the free space only after R18.
* **Rollback:** unset `ONHOST_CUSTOM_ISO_ROOT`, `config:cache`; restore the previous PHP/nginx values and reload. Nothing is stored
  yet. The platform refuses a root inside `public/` (503 `custom_iso_storage_unsafe`).
* Owner approval: ☐ yes

### R17. Proxmox storage for customer images (G-7)

* **Purpose:** a storage with content type `iso`, separate from the rescue storage.
* **Precondition:** a Proxmox instance is registered on staging; the owner has the API token (the owner types or pastes secrets
  himself on the server).
* **Command:** in the staff console (Integrace providerů) set the instance option `custom_iso_storage=<storage id>`; the API token
  needs `Datastore.AllocateTemplate` and `Datastore.Audit` on it; the `provider-proxmox` queue timeout is at least
  `ONHOST_CUSTOM_ISO_UPLOAD_TIMEOUT` (default 3600 s). Then:
  ```bash
  art onhost:integrations:secret <proxmox-instance-key> --check      # hidden prompt for a changed secret; --check runs the connection test and the prerequisites
  ```
* **Expected:** the connection test passes. **Without the option the feature answers `reason: node` and nothing is uploaded.**
* **Verify:** by hand on staging first (a test upload and attach after R18/R19). A Proxmox adapter test against a fake is not a
  proof of the storage.
* **Rollback:** unset the option; nothing is uploaded.
* Owner approval: ☐ yes

### R18. Revision `2026-10-custom-iso`, apply (G-8, **only after R15–R17 are green**)

* **Purpose:** custom ISO becomes sellable on the plans of the proposal.
* **Precondition:** R8 read and the owner's decision recorded; R15, R16 and R17 verified by hand.
* **Command:**
  ```bash
  art onhost:catalog:revise 2026-10-custom-iso --apply
  art onhost:catalog:revise 2026-10-custom-iso          # dry run again
  row "custom ISO virus scan passes its self-test"
  row "custom ISO storage has room"
  row "every catalogue revision is applied"
  ```
* **Expected:** new plan versions with the same prices; the second dry run says nothing is pending; **the two ISO rows are now judged
  for real** (FAIL while clamd or the volume is wrong). The price-list line („Vlastní ISO do 4 GB“) is added by hand in the plan
  editor.
* **Verify:** the rows; the plan editor shows `custom_iso true`, `custom_iso_max_mb 4096` on the seven proposed versions and
  **not** on `vps/compute-2`.
* **Rollback:** publish the previous plan versions in the plan editor; customers on the new versions keep their images (the way out
  — `iso.detach`, `iso.delete` — stays open).
* Owner approval: ☐ yes

### R19. ISO upload smoke on staging

* **Purpose:** one real upload through nginx, PHP, clamd and the volume.
* **Precondition:** R18; a VPS on a plan that sells it; a harmless ISO (a small distribution image) and, separately, the EICAR test
  file renamed `.iso` (a harmless test string; the platform must refuse it with 422 `upload_infected`).
* **Command:** upload and attach through the portal (manual test `05-vps.md`); then
  ```bash
  art onhost:isos:sweep --dry-run                       # counts, changes nothing
  ```
* **Expected:** the clean image is accepted (`ready`) and attaches; the EICAR file is refused with 422 `upload_infected` and no file
  stays under `$ROOT`; with clamd stopped, an upload gives 503 `iso_scan_unavailable` (fail closed — do not leave it stopped).
* **Verify:** `ls -R "$ROOT"` has only the kept image; audit action `service.iso.upload` (and `denied` for the infected one).
* **Rollback:** the customer deletes the image (`iso.delete`, fresh step-up); leftovers: `onhost:isos:sweep` (hourly) or by hand per
  [custom-iso.md](custom-iso.md) *Manual clean-up*.
* Owner approval: ☐ yes

---

## Phase 5 — Penpot, webhooks, dead letters

### R20. Revision `2026-10-penpot-on-sale`, apply (on sale; every order refused while no Penpot node is qualified)

* **Purpose:** Penpot on sale (H-R7). Without a qualified Penpot node ([penpot.md](penpot.md) *Server prerequisites*, steps 1–10,
  **not part of this rehearsal**) the cart refuses every Penpot order (`409 penpot_unavailable`), so nothing is charged.
* **Precondition:** R9 read.
* **Command:**
  ```bash
  art onhost:catalog:revise 2026-10-penpot-on-sale --apply
  row "Penpot is sold only with a Penpot node to run it"
  row "Penpot has a price before it is on sale"
  ```
* **Expected:** the product is created on sale with its prices; *Penpot has a price before it is on sale* is OK; *Penpot is sold only
  with a Penpot node to run it* is a **non-blocking FAIL** until the node exists (orders are refused, not taken).
* **Verify:** the rows; Nastavení → Integrace → *Penpot k tarifům webhostingu* lists every web hosting tariff as included.
* **Rollback:** `art onhost:catalog:state draft penpot` (one person, step-up in the console) takes it off sale.
* Owner approval: ☐ yes

### R21. Webhook queue lane (G-11) and the secret overlap (G-12)

* **Purpose:** one worker on the `webhooks` lane; rotated secrets overlap only briefly.
* **Precondition:** the unit file `infra/systemd/onhost-queue@.service` is installed. Staging installs with `QUEUES='default mails'`,
  so the lane has to be added to the root-owned `$S/expected-units` (one unit per line) **or** the gate stops the next deploy because
  an unexpected `onhost-queue@*` unit is running.
* **Command:**
  ```bash
  echo 'onhost-queue@webhooks' >> $S/expected-units          # the deployer's list; root's decision, not the target's
  systemctl enable --now onhost-queue@webhooks
  systemctl status onhost-queue@webhooks --no-pager | head -5
  # the owner may set ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES (0-1440, default 60) and then: art config:cache
  row "rotated webhook secrets overlap only briefly"
  ```
  Then a test delivery with the owner's own endpoint: `POST /v1/webhooks/{endpoint}/ping`, `GET /v1/webhooks/{endpoint}/deliveries`.
* **Expected:** the unit is `active`; the row is OK; the ping shows a delivered attempt.
* **Verify:** `journalctl -u onhost-queue@webhooks -n 30`. Without the unit deliveries fall back to the default lane (nothing piles
  up), so a missing unit is not an error.
* **Rollback:** `systemctl disable --now onhost-queue@webhooks` and remove the line from `$S/expected-units`.
* Owner approval: ☐ yes

### R22. Outbox dead letters are heard (G-13)

* **Purpose:** a dead letter is visible in the doctor and in Prometheus.
* **Precondition:** Prometheus scrapes `/metrics` of staging (if staging has no Prometheus, do the doctor part only and say so).
* **Command:**
  ```bash
  art onhost:outbox:dead-letters                       # lists them; --requeue and --id/--name only after reading the list
  row "no outbox dead letters"
  promtool check rules infra/monitoring/slo-alerts.yml # on the monitoring host, from a checkout of this commit
  ```
* **Expected:** an empty list; the row is OK; `promtool` reports the rules valid; `onhost_outbox_dead_letters` is returned by a query
  (0 is the healthy value). Optional drill (owner decides): a listener that throws, then `art onhost:outbox:dead-letters --requeue`.
* **Verify:** the row and the query.
* **Rollback:** remove the rule file from the Prometheus configuration.
* Owner approval: ☐ yes

---

## Phase 6 — The gate and the final doctor

### R23. Final doctor and `expected-nonok` (staging-launch.md S4b)

* **Purpose:** on staging every non-OK row must be named on the root-owned `expected-nonok` list **with its reason**, otherwise the
  next deploy stops. This step drafts the list from the real report; the owner edits it.
* **Precondition:** R2–R22 done or consciously skipped (each skip written in the protocol).
* **Command:**
  ```bash
  doc > /root/rehearsal-2026-10/doctor-final.json; echo "rc=$?"
  $P $DG nonok --report /root/rehearsal-2026-10/doctor-final.json --production 0      # candidates, one area|check per line
  # the owner edits $S/expected-nonok: a line "area|check" for every row that is non-OK on staging ON PURPOSE, each reason in the release record
  $EDITOR $S/expected-nonok
  $P $DG verdict --report /root/rehearsal-2026-10/doctor-final.json --doctor-rc 0 --env staging --production 0 --sha <STAGING_SHA> --expected-file $S/expected-nonok
  art onhost:staging:report --check                    # asks every panel the read-only questions, runs the doctor, writes storage/app/onhost-staging-report.json
  ```
* **Expected:** 0 FAIL; every remaining WARN has a remedy in its row and a reason on `expected-nonok` (typically: Turnstile keys not set
  on staging, no off-server backup disk, the open owner question about the production proxy address, the rows that are non-OK on
  staging by design); the verdict prints no `NOT EXPECTED` row; a listed row that turned OK prints `CLEARED` (take it off the list).
* **Verify:** the verdict, and the first 12 characters of `sha256sum $S/expected-nonok` into the release record.
* **Rollback:** restore the previous `expected-nonok` (kept as a copy before editing: `cp -p $S/expected-nonok $S/expected-nonok.bak`).
* Owner approval: ☐ yes

### R24. A real deploy through the gate (optional, the owner decides)

* **Purpose:** prove that the gate accepts the staging as configured in this rehearsal.
* **Precondition:** R23 done; a commit to deploy (the same one, or the tip of development).
* **Command:** `bash /root/onhost-staging.sh deploy "$SHA"` ([staging-aapanel.md](staging-aapanel.md), steps 3a); six minutes later
  `art onhost:doctor` (the drained rows `scheduler running`, `queue worker alive`, `mail outbox is leaving` are judged only then).
* **Expected:** exit 0 and `/up` answering; no new non-OK row; the units from `expected-units` are `active` (including the
  `webhooks` lane of R21).
* **Verify:** `bash /root/onhost-staging.sh status`; `cat $S/releases/current` equals `cut -d' ' -f1 $APP/VERSION`.
* **Rollback:** `bash /root/onhost-staging.sh deploy "$(cat $S/releases/current)"` ([staging-aapanel.md](staging-aapanel.md)
  *Rollback*; migrations are additive for one release and are not undone; a database restore is never automatic).
* Owner approval: ☐ yes

---

## Protocol (fill in while running)

Copy this table into the release record of the rehearsal (`.ai/releases/<date>-<sha7>.md`) and fill one row per step. "Doctor output"
is the one row of the doctor that the step watches (status and detail), not the whole report; the whole reports are the JSON files in
`/root/rehearsal-2026-10/`. **No secret, code or token goes into this table.**

Rehearsal date: `____`  Staging commit: `____`  Operator: `____`  Owner present: `☐`

| Step | Time (start–end) | Owner yes (time) | Result (OK / differs / skipped) | Doctor output (row, status) | Notes (what differed, rollback used, follow-up) |
| --- | --- | --- | --- | --- | --- |
| R0 release and gate lists | | | | | |
| R1 doctor before | | | | | |
| R2 PHP binary | | | | | |
| R3 usranalyse directives | | | | | |
| R4 TRUSTED_PROXIES loopback | | | | | |
| R5 shared cache | | | | | |
| R6 revise web plans, dry run | | | | | |
| R7 revise web plans, apply | | | | | |
| R8 revise custom ISO, dry run | | | | | |
| R9 revise Penpot, dry run | | | | | |
| R10 staff TOTP | | | | | |
| R11 exchange rates | | | | | |
| R12 tokens (dry run, switch) | | | | | |
| R13 reinstatement audit | | | | | |
| R14 VAT entity (non-payer) | | | | | |
| R15 clamd | | | | | |
| R16 ISO volume, PHP and nginx | | | | | |
| R17 Proxmox ISO storage | | | | | |
| R18 revise custom ISO, apply | | | | | |
| R19 ISO upload smoke | | | | | |
| R20 revise Penpot, apply (draft) | | | | | |
| R21 webhook lane and overlap | | | | | |
| R22 dead letters | | | | | |
| R23 final doctor, expected-nonok | | | | | |
| R24 gated deploy | | | | | |

After the last row: one paragraph (the doctor at R1 and R23, which rows moved, which steps were skipped and why, which rollback was
used, what is left for production). Open items go into [go-live-checklist.md](go-live-checklist.md) with their owner.

## Command check

Every command was looked up in the repository on the commit this page is based on (`app/Console/Commands`, `routes/console.php`,
`domains/**`, `infra/`). **Nothing was executed.**

| Command | Found in | State |
| --- | --- | --- |
| `onhost:doctor [--json]` | `app/Console/Commands/Doctor.php` | exists |
| `onhost:catalog:revise [id] [--apply] [--yes]` | `app/Console/Commands/CatalogRevise.php` | exists; revisions `2026-10-deliverable-web-plans`, `2026-10-custom-iso`, `2026-10-penpot-on-sale` are in `domains/Catalog/CatalogRevisions.php` (`2026-10-custom-iso` is a proposal applied only by id) |
| `onhost:staff:totp <email> [--code=] [--reset]` | `app/Console/Commands/StaffTotp.php` | exists |
| `onhost:fx:sync` | `routes/console.php` (scheduled) | exists |
| `operator:tokens:unbound [--dry-run] [--past-cap] [--days=]` | `app/Console/Commands/TokensUnbound.php` | exists |
| `onhost:billing:reinstatement-audit [--dry-run] [--apply --service=]` | `app/Console/Commands/ReinstatementAudit.php` | exists (the audit asked for as "reinstatement-audit") |
| `onhost:vat:payer-mode` | `app/Console/Commands/VatPayerModeCommand.php` | exists (read only, `--apply` refused) |
| `onhost:vat:export kh\|sh --period= --format=` | `app/Console/Commands/VatReportExport.php` | exists |
| `onhost:production:prepare [--legal] [--purge-dev-accounts] [--cache] [--yes]` | `app/Console/Commands/` | exists |
| `onhost:isos:scanner-check [--fresh]` | `routes/console.php` | exists |
| `onhost:isos:sweep [--dry-run] [--hours=]` | `routes/console.php` | exists |
| `onhost:integrations:secret <instance> [key] [--check] [--stdin] [--force]` | `app/Console/Commands/` | exists |
| `onhost:outbox:dead-letters [--requeue] [--id=] [--name=] [--json]` | `app/Console/Commands/` | exists |
| `onhost:staging:report [--check] [--path=]` | `routes/console.php` | exists |
| `onhost:penpot:sweep` | `app/Console/Commands/PenpotSweep.php` | exists (not used above; named in [penpot.md](penpot.md)) |
| `config:cache`, `cache:clear`, `tinker` | Laravel | exists |
| `staging.sh check\|harden\|deploy\|status\|setup` (`/root/onhost-staging.sh`) | `infra/aapanel/staging.sh` | exists |
| `deploy-gate.php nonok\|verdict` (`$DG`) | `infra/aapanel/deploy-gate.php`, installed by `install.sh` | exists (as used in staging-launch.md S4b) |
| `systemctl enable --now onhost-queue@webhooks` | `infra/systemd/onhost-queue@.service` | unit template exists |
| `promtool check rules infra/monitoring/slo-alerts.yml` | `infra/monitoring/slo-alerts.yml` | file exists; `promtool` is Prometheus's own tool, not in the repo |
| `onhost:catalog:revise --dry-run`, `onhost:billing:dunning --dry-run` | — | **do not exist**: the dry run of `catalog:revise` is the default (no `--apply`); dunning has no dry run (first-day-production.md) — neither is used above |

Not covered by any repository check: that the host's real `clamd`, Proxmox storage, nginx and PHP-FPM accept what the steps ask for
(R15–R17), and that systemd applies the usranalyse drop-ins on the real host (R3). Those are exactly what the rehearsal is for.
