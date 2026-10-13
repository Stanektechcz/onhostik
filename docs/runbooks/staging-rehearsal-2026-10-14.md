# Staging rehearsal, 2026-10-14: protocol (A–C deploy of `199071b8`, privacy cookie list, D6 preparation)

Protocol of the run on staging (`staging.onhost.cz`, 45.67.217.22) in the night of 2026-10-12/13 (UTC), filed for the protocol day
2026-10-14. It continues [staging-rehearsal-2026-10-13.md](staging-rehearsal-2026-10-13.md) and follows the owner's procedure
`GENERALKA-K-A-C-deploy-prikazy-2026-10-12.md` (A–C, only the frozen SHA differs) and `GENERALKA-Penpot-D-G-prikazy.md` (D6).
**Outputs only, no secrets**: no `app.env` or private storage was read; no password, TOTP, private key, cookie value or TXT value
was typed or printed. Backups and logs stay on the host in `/root/o-2026-10/` (root, 0700) and `/root/penpot-stg/`.

## Header

| Item | Value |
| --- | --- |
| Owner "ano" | 2026-10-12, "ano ke všem bodům stagingu, pokračuj": (4) a new A–C cycle of the current `development` tip (after the phase O PRs TASK-0177/0178, or the tip at start), frozen SHA, K A–C procedure; (2) active `wedos-main` tolerated in the Penpot document (F0); (1) D6 prepared — certbot only in a way that leaves the TXT values and the Let's Encrypt terms to the owner; (5) the aaPanel WAF cookie on the privacy page via a seam (repo task, firewall unchanged); (3) F1/F4–F7 listed as owner actions; (6) old dumps left to the owner |
| Operator | Claude (root over `ssh onhost-staging`, key only), one step at a time, each verified before the next |
| Release | before `fa2ea75d6a4f276cc2a248c7829e76f1735e65ed` → after **`199071b862e872a0edf496b2f567de9128ac8207`** (merge PR #175; contains phase L–O incl. PR #165–#175) |
| Doctor | before: 0 FAIL, 29 WARN · after: **0 FAIL, 28 WARN**, gate `VERDICT pass` (1 CLEARED) |
| Migrations | 82 → **83** (`0001_01_01_001160_backup_addon_named_for_every_parent`) |

**Why `199071b8`:** the owner preferred to wait for the phase O PRs. PR #173 (TASK-0177), #174 (TASK-0179, this run's cookie list)
and #175 (TASK-0178) were merged at 21:05, 21:37 and 21:38 UTC; `199071b8` is the merge of #175, and CI on `development`
(tests, e2e smoke, security) was green before C2. The later tip `136fc312` (PR #176, TASK-0180) changes documentation only.
Against `fa2ea75d` the deployer files are byte-identical (`staging.sh` `cc3827f4…`, `deploy.sh` `9baa1a22…`, `deploy-gate.php`
`653a7a2b…` = the table of the K document), so the owner document did not need a new edition; one doctor row changed its
condition (*bank statement import* is OK since M3, J-R7) and one migration was added.

## Step log (times UTC)

| Step | Time | Result | Notes |
| --- | --- | --- | --- |
| Shell prep | 10-12 21:10 | OK | the K document's prep in `/root/o-env.sh` (0600) with `B=/root/o-2026-10`, `OLD=fa2ea75d…`, `art()` with `HOME=/tmp` (finding 3 of 2026-10-13); `$B/SHA` holds the frozen SHA |
| A backup | 10-12 21:11 | OK | `VERSION` = `current` = `fa2ea75d`; dump / state / tree `rc=0`; 5× `OK` in `sha256sum -c SHA256SUMS-o`; `TABLE DATA` 212; migrations 82; 263 GB free. Also copied `expected-units` (R21 line of 2026-10-12) |
| B `expected-nonok` | 10-12 21:12 | OK, **no write** | doctor before: 119 OK, 29 WARN; every WARN listed, nothing non-OK unlisted. Diff `fa2ea75d..199071b8`: no new doctor row; *payments\|bank statement import* turns OK (would be CLEARED). So no row needed adding; the file was not changed (hash `7621ce8f…` before and after). CLEARED rows stay listed (clean-up is R23) |
| C1 script + check | 10-12 21:45 | OK | downloaded `staging.sh` = `cc3827f4…` (identical to the installed one), `bash -n` OK; `deploy.sh` `9baa1a22…`, `deploy-gate.php` `653a7a2b…`; `staging.sh check`: no ✖, `vhost PHP-FPM matches the deploy PHP`, `check passed` |
| (pause) | 10-12 21:45 – 10-13 00:57 | — | waited for green CI on `development` at `199071b8`; the session restarted; a read-only re-check at 00:55 found staging untouched (`fa2ea75d`, nothing parked, not in maintenance, 4 units active, `/up` 200, 82 migrations) |
| C2 deployer | 00:57 | OK | `source-sha 199071b8…`, `expected-env written`, `app.env matches expected-env`, rc 0 |
| C3 gated deploy | 00:58:03 – 00:58:56 | OK | rc 0; run `runs/20261013-005805-199071b862e8`; `VERDICT pass`; no HARD-FAIL / GATED-FAIL / ROW-FAIL; `CLEARED payments\|bank statement import (OK now)`; migration `001160` DONE (24 ms); `released` / `current release` = `199071b8`. Downtime under one minute |
| C4 verification | 00:59 – 01:02 | OK | `VERSION` = `current` = `199071b8`; nothing parked; scheduler + queue default / mails / webhooks active; `/up` 200 loopback and public; migrations 83; PHP binary row OK (`/www/server/php/85/bin/php`, 8.5.8); 6 of 6 commands in `art list`; doctor `{"OK":120,"WARN":28}`, 0 FAIL; `backup-plus` is now "Rozšířené zálohy" (B2 fix, TASK-0178); gate re-check of the post-deploy doctor against `expected-nonok` → `VERDICT pass`; `scheduler running`, `queue worker alive` (all four) OK |
| Privacy cookie list | 01:00 | OK | `/dokumenty/ochrana-osobnich-udaju` shows `id="cookies"` "Seznam cookies" with `onhost-session` and `XSRF-TOKEN` (2 hodiny), `onhost_ref` (30 dní), `server_session_<hash>` (aaPanel WAF, 10 dní). Matches the live headers: `XSRF-TOKEN` / `onhost-session` Max-Age 7200, `server_session_64d01d7e` Max-Age 864000. The aaPanel firewall was not touched |
| D6 preparation | 01:00 | OK (package only) | `apt-get install certbot`: simulation 0 removals, 12 packages, `install rc=0`, `certbot 1.21.0`, `certbot.timer` enabled (does nothing without a certificate); Docker not restarted (`ActiveEnterTimestamp` unchanged), rootful containers unchanged, docker / wings / caddy active, `/up` 200. **certbot was not run**: no Let's Encrypt account, no terms accepted, no TXT value exists. DNS facts (read only): NS `ns.wedos.cz/.com/.net/.eu`; no CAA on `onhost.cz` or `penpot.onhost.cz`; no TXT/CNAME at `_acme-challenge.penpot.onhost.cz`; `penpot.onhost.cz` and `*.penpot.onhost.cz` → 45.67.217.22, no AAAA |
| D7, D8, E, F, G1 | — | not run | D7 needs the D6 certificate; the rest follows D |

## Changes outside the host

- **Repo, TASK-0179 (PR #174, merged):** `App\Http\Support\CookieInventory` + `legal.document` view: the privacy page lists every
  cookie under the document text; the published privacy text and its hash are unchanged; `onhost.privacy.hosting_waf_cookie`
  (default true) drops the firewall row on a host whose WAF cookie is off.
- **Owner document `GENERALKA-Penpot-D-G-prikazy.md` (desktop, not in the repo):** header, D0 and F0 now tolerate
  `wedos-main | wedos | active | 0` when the registrar has 0 nodes, `secret=no` (new per-instance line in F0) and
  `onhost-queue@provider-registrar` stays masked through F3–F8 (F3 never unmasks it); F0 also checks that no domain service exists;
  the D–G shell prep redefines `art()` with `HOME=/tmp`; D6 lists what the owner supplies (below) and drops `--agree-tos`, so the
  owner answers the Let's Encrypt terms himself, and checks both TXT values at `ns.wedos.cz` and `1.1.1.1` before the last Enter.
  Verified on staging: the F0 secret line prints `wedos-main wedos active secret=no`; no service of any family exists.

## Findings

1. **The nginx systemd unit is `failed` since 2026-09-07** (`/run/systemd/generator.late/nginx.service` from `/etc/init.d/nginx`).
   aaPanel runs nginx outside systemd (master since 2026-08-28), so `/up` is unaffected; pre-existing, not caused by this run.
   Owner documents should not use `systemctl is-active nginx` as a health signal (they use `nginx -t` + `/etc/init.d/nginx reload`).
2. **The aaPanel WAF attack log keeps whole request headers.** `/www/server/btwaf/totla_db/http_log/*.log` (seen while looking for
   the cookie's origin) store blocked requests with every header, including session cookies of other sites on the host. Nothing was
   copied; retention of that log is a privacy question for the owner (aaPanel WAF settings), outside this run.
3. **`Strict-Transport-Security` is still sent twice** (app and nginx), as on 2026-10-13 B1. Harmless; noted again.
4. **`onhost_ref`** (partner attribution, 30 days, only after a `?ref=` link) is now named on the privacy page; whether it counts
   as strictly necessary or needs consent is for the lawyer (TASK-0179 out of scope).
5. **`development` CI turned red at 2026-10-12 22:30 UTC, after the freeze** (the deployed `199071b8` was green at 21:40 UTC): the
   TASK-0159 test `RefundEstimateTest › counts the accounting day…` travelled to the fixed moment 2026-10-11 22:30 UTC, which lies
   before the prices the seeders date `now()->subDay()` (`No year price in CZK for web-hosting/standard`). Test-only defect; the
   released code is unaffected. Fixed by TASK-0182 (PR #178): the test travels to tomorrow 23:30 UTC and asserts the Prague day split.

## Left for the owner

- **D6 certificate** (then Claude continues D7 nginx vhost + selftest and D8 `verify-node.sh`):
  1. your e-mail for the Let's Encrypt account;
  2. accept the Let's Encrypt Subscriber Agreement yourself — certbot asks, the command has no `--agree-tos`;
  3. WEDOS DNS for `onhost.cz`: add the two TXT records `_acme-challenge.penpot` (TTL 300) that certbot shows, delete both after issue;
  4. command (aaPanel → Terminál, root): `certbot certonly --manual --preferred-challenges dns --no-eff-email -m <your e-mail> -d penpot.onhost.cz -d '*.penpot.onhost.cz'`;
     before the second Enter: `dig +short TXT _acme-challenge.penpot.onhost.cz @ns.wedos.cz` and `… @1.1.1.1` both show both values;
  5. tell Claude "D6 hotovo"; for D7 open `https://selftest.penpot.onhost.cz/` from your PC when asked.
- **F1, F4–F7** (after D7, D8 and E): F1 Sandbox switch in the staff console (staff login + TOTP + step-up); F4 order in the customer
  panel as the sandbox customer (customer login); F5 "Nastavit heslo", login and upload from your PC; F6 suspend / resume and F7 cancel
  in the staff console (TOTP + step-up). F0 no longer stops on the active `wedos-main` (see above).
- **Old dumps (J-R12), unchanged:** `shred -u /root/r2b-2026-10/onhost_staging_b-before-r2b.dump /root/r2b-2026-10/onhost_staging_b-before-r2b5.dump`.
  The new backup set of this run (`/root/o-2026-10/`, about 100 MB) stays until the owner clears it with the others.
- **Browser checks on the new release** (customer / staff login needed): F1.7 steps 3–4 (guest order creates an account), F7-19 staff
  bank-statement import with a synthetic statement, F1.8 / F1.9 manual tests; the cart drawer label of B2 now comes from the renamed
  add-on (a guest browser check without ordering is possible in a later run).
- Unchanged from 2026-10-13: R21 test ping with your endpoint, VAT entity switch, R12 token switch, R15/R16, R25 test backup.

## Rollback (not needed; kept for reference)

DB from `/root/o-2026-10/onhost_staging_b-before-o.dump` and `bash /root/onhost-staging.sh deploy fa2ea75d6a4f276cc2a248c7829e76f1735e65ed`
as in *Rollback B* of the K document (with `$B=/root/o-2026-10`, dump `…-before-o.dump`, expected 82 migrations). certbot:
`apt-get purge -y certbot` (no certificate exists, so nothing else to remove).
