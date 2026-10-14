# Staging rehearsal, 2026-10-18: protocol (phase V, V1: A–C — attempt 1 of `259cbea6` stopped at B, attempt 2 of `82bc988f` passed)

Protocol of phase V on staging (`staging.onhost.cz`, 45.67.217.22), filed for the protocol day 2026-10-18 (the days up to 2026-10-17
already have a protocol). It continues [staging-rehearsal-2026-10-17.md](staging-rehearsal-2026-10-17.md).
**Attempt 1** ran on 2026-10-14 between 10:45 and 10:47 UTC under the owner's "T-ano" for `259cbea6` and stopped at step B.
**Attempt 2** is the A–C cycle of the new frozen target `82bc988f` per the owner's document
`GENERALKA-V-A-C-deploy-prikazy-2026-10-14.md` (desktop; it replaces `GENERALKA-T-…` for this cycle); attempt 2 ran on the owner's "T-ano 82bc988f + řádek B" (2026-10-14 12:20 UTC)
and passed A–C4, V2 and C4b (registrar credit after the next full hour, read 13:10 UTC): **V1 PASS**.
**Outputs only, no secrets**: `app.env` is not read; no password, TOTP, private key, cookie value or log content is printed (only exception
class names and a secret-reference prefix). Metric values are left out (names and counts only).

## Pokus 1 (259cbea6, 2026-10-14)

| Item | Value |
| --- | --- |
| Owner "ano" | 2026-10-14, "T-ano" (relayed by the phase V coordinator): the A–C cycle of `259cbea6f3ab7e673436778dccaf251c66de21ce` per `GENERALKA-T-A-C-deploy-prikazy-2026-10-14.md`; rollback on failure; nothing around the classifier |
| Operator | Claude (root over `ssh -o BatchMode=yes onhost-staging`, key only), one step at a time, the document's shell preparation piped in with each step |
| Release | stays **`fda0cedf443a3c603ef2aeae1a6184fbaf7dfc6a`** (nothing deployed) |
| Doctor | 0 FAIL / 121 OK / 30 WARN — 1 WARN not in `expected-nonok` (finding 1) |
| Migrations | 86 (unchanged) |

| Step | Time (UTC) | Mode | Result | Notes |
| --- | --- | --- | --- | --- |
| Read-only state | 10:45:08 | S | OK | `VERSION` = `current` = `fda0cedf`; nothing parked; scheduler + queue default / mails / webhooks `active`; 264 GB free; migrations 86; `expected-nonok` 31 rows; vhost still 1× `Strict-Transport-Security` (HS-2 not done); `nginx -t` OK; public `/up` 200; `cdn` `active` |
| CI gate | 10:45 | — | OK | at `259cbea6` (run of merge #216): `tests` (pest, pest-postgres 1–4, php 8.5, monitoring, restore-drill, deploy-scripts, openapi-lint), `e2e smoke`, `security` green |
| A backup | 10:45:25 | Z (A) | **DONE** | `/root/t-2026-10` (0700): dump / state / tree `rc=0` (3×); 5× `OK` in `sha256sum -c SHA256SUMS-t`; `TABLE DATA` 213; migrations 86 |
| B check | 10:45:53 | S | **STOP** | doctor 121 OK / 30 WARN, 0 FAIL; `comm -13` printed **one** row not in `expected-nonok`: `automation\|every scheduled job ran within its budget` — `1 of 92 past their budget: onhost:registrar:credit (never ran, budget 2.2 h, failed 26× in a row)` |
| Cause (read) | 10:46 | S | — | `scheduled_job_runs`: `onhost:registrar:credit` `failures_in_a_row` 26 since 2026-10-13 22:00 (the R3 deploy started the tracking); log line: `RuntimeException`, no environment secrets for the prefix `WEDOS_MAIN_` — staging's registrar instance has no secrets and the credit monitor did not skip that case (the poll does) |
| C1–C4 | — | — | **not run** | the document: a new non-OK row is not added without a separate "ano"; the gate would have refused it as `ROW-FAIL` |
| Rollback | — | — | not needed | nothing but the backup set `/root/t-2026-10` was written |

Findings of attempt 1:

1. **`onhost:registrar:credit` failed every hour on staging.** `RegistrarCreditMonitor::sampleAll()` caught only `ProviderException`;
   the secret store throws a plain `RuntimeException`, so one registrar without credentials ended the loop and the job exited 1.
   Fixed in TASK-0222 (PR #218, merge `82bc988f`, all checks green): the instance is skipped with a warning, as
   `onhost:registrar:poll` does. The doctor row turns OK only after the fixed code has run the hourly job once, so attempt 2 lists the
   row in `expected-nonok` in step B (owner input **V-B** in the new "T-ano"); after the first successful run the gate reports it `CLEARED`.
2. **Blocklist answers through `8.8.8.8`.** The staging host resolves through `8.8.8.8`; Spamhaus answers queries from a public
   resolver with `127.255.255.254`. Staging has 0 nodes, so nothing was reported. Handled by the antispam package (SP1a, TASK-0223,
   not part of `82bc988f`); an own resolver or a Spamhaus DQS key is needed before that package is verified on staging.

## Pokus 2 (82bc988f, 2026-10-14) — PASS (A–C4, V2, C4b)

### Header

| Item | Value |
| --- | --- |
| Owner "ano" | **given 2026-10-14 12:20 UTC in the owner's own message: "T-ano 82bc988f + řádek B, staging A–C, provést přes ssh onhost-staging"** (names `82bc988f` and approves the V-B row; the T-ano of attempt 1 named `259cbea6` and is used up) |
| Operator | Claude over `ssh -o BatchMode=yes onhost-staging` (key only), one step at a time, each verified before the next |
| Release | before `fda0cedf443a3c603ef2aeae1a6184fbaf7dfc6a` → after **`82bc988f3de320bc828d1200da5385f2c9a8e71b`** (merge PR #218; phase S #206–#209, phase T #210–#213, phase U #214–#217, V1b #218) |
| CI of the target | green at `82bc988f`: `tests` run 38556971485, `e2e smoke` 38556971429, `security` 38556971556 (2026-10-14 11:29 UTC) — re-check right before A |
| Doctor | before: expected 0 FAIL / 121 OK / 30 WARN · after C4: **PASS — viz *Výsledek pokusu 2*** |
| Migrations | 86 → expected **88** · result: **PASS — viz *Výsledek pokusu 2*** |

### What changed against attempt 1 (verified from the repository)

- `git diff --name-only 259cbea6 82bc988f` touches only `.ai/tasks/TASK-0221.md`, `.ai/tasks/TASK-0222.md`, the audit README and
  `ROZHODNUTI.md`, `domains/Domains/RegistrarCreditMonitor.php` and its new test. **No migration, no `infra/aapanel/staging.sh`,
  `deploy.sh` or `deploy-gate.php`, no `routes/`, `config/` or `.env.example` change** — and none in `fda0cedf..82bc988f` either for
  `routes/`, `config/`, `infra/` and `.env.example`.
- SHA-256 at `82bc988f` (identical at `259cbea6` and `fda0cedf`): `staging.sh` `6aa3ad41c494d07d1f9fadd3bc33cb717ada45f48eb20a2a8d98cce725175f9c`,
  `deploy.sh` `9baa1a2276d809cc306ff44dfe99a1ad5567cfe2a1497b93cdd4cd0c2f3d1496`,
  `deploy-gate.php` `653a7a2bbd6cd311b7725bdb4df79b328714b12e7c2cf534ffa70672845e1644`.
- Migrations staging runs now (`git diff --name-only fda0cedf 82bc988f -- database/migrations`), 86 → 88:
  `0001_01_01_001200_game_plans_without_unkept_pids` (data only: `limits.pids` out of the game plan versions nobody holds, only while
  the seeded number stands; `down()` puts it back) and `0001_01_01_001210_cdn_addon_back_to_draft` (data only: product `cdn`
  `active` → `draft`, only while it carries the seeded name and description; plans, prices and subscriptions unchanged; `down()`).
- **Step A takes a fresh backup** into a new set `/root/v-2026-10` (files `*-before-v*`); `/root/t-2026-10` is kept as it is and not
  reused. Reason: Rollback C restores the database dump, and the attempt-1 dump (10:45 UTC 2026-10-14) would throw away everything
  staging wrote since (scheduled job runs, audit, outbox, rates); a fresh set costs about half a minute and leaves the old one as history
  (deleting it is the owner's ST-2).

### Step log

| Step | Time | Mode | Expected | Result |
| --- | --- | --- | --- | --- |
| Read-only state | — | S | `VERSION` = `current` = `fda0cedf`; nothing parked; 4 units `active`; migrations 86; `expected-nonok` 31 rows; `nginx -t` OK; `/up` 200; `cdn` `active` | PASS — viz *Výsledek pokusu 2* |
| CI gate | — | — | `tests` (pest, pest-postgres 1–4, php 8.5, monitoring, restore-drill, deploy-scripts, openapi-lint), `e2e smoke`, `security` green at `82bc988f` | PASS — viz *Výsledek pokusu 2* |
| A backup | — | Z (A) | `/root/v-2026-10` (0700): dump / state / tree `rc=0`; 5× `OK` in `sha256sum -c SHA256SUMS-v`; migrations 86 | PASS — viz *Výsledek pokusu 2* |
| B check | — | S | doctor 0 FAIL; `comm -13` prints exactly `automation\|every scheduled job ran within its budget` (any other row = STOP; nothing printed = the job already passed, V-B is not added) | PASS — viz *Výsledek pokusu 2* |
| B `expected-nonok` (V-B) | — | Z (B) | 1 row appended under `# V-B <date>: … target 82bc988f…`; `-rw------- root root`; diff only 1× `>`; 32 rows | PASS — viz *Výsledek pokusu 2* |
| C1 script + check | — | Z (C1) | `staging.sh` from `82bc988f` = `6aa3ad41…` (same as installed); `deploy.sh` `9baa1a22…`, `deploy-gate.php` `653a7a2b…`; `check passed` | PASS — viz *Výsledek pokusu 2* |
| C2 deployer | — | Z (C2) | rc 0; `app.env matches expected-env`; `source-sha` = `82bc988f…` | PASS — viz *Výsledek pokusu 2* |
| C3 gated deploy | — | Z (C3) | rc 0; **`VERDICT pass`**; no HARD-FAIL / GATED-FAIL / ROW-FAIL; migrations `001200` and `001210` DONE; `EXPECTED` includes the V-B row; `CLEARED` only rows already OK (bank statement import, R9 tokens, as at R3); `current release` = `82bc988f` | PASS — viz *Výsledek pokusu 2* |
| C4 verification | — | S | see *C4 expected values* | PASS — viz *Výsledek pokusu 2* |
| C4b after the next full hour | — | S | `onhost:registrar:credit` `failures_in_a_row` 0 with `last_success_at` set; budget row OK | PASS — viz *Výsledek pokusu 2* |
| V2 public site (read) | — | S | see *V2 expected values* | PASS — viz *Výsledek pokusu 2* |
| D restore drill | — | V | not part of V1 (DR-1, the owner types the DB role's password) | NEPROBĚHLO — čeká na DR-1 |
| Rollback | — | — | only on a failed step: DB from `/root/v-2026-10/onhost_staging_b-before-v.dump`, then deploy `fda0cedf` | not needed (no step failed) |

### C4 expected values

| Check | Expected | Result |
| --- | --- | --- |
| `VERSION` = `current` | `82bc988f3de320bc828d1200da5385f2c9a8e71b`; 4 units `active`; `/up` 200 loopback and public | PASS — viz *Výsledek pokusu 2* |
| `SELECT count(*) FROM migrations` | **88** | PASS — viz *Výsledek pokusu 2* |
| doctor | 0 FAIL; every non-OK row listed; 121 OK / 30 WARN right after the deploy, 122 OK / 29 WARN after C4b | PASS — viz *Výsledek pokusu 2* |
| game plan versions | `limits.pids` gone on every version of product `game` (`-` in every row) | PASS — viz *Výsledek pokusu 2* |
| product `cdn` | `cdn\|draft` | PASS — viz *Výsledek pokusu 2* |
| *no known metering gap* | WARN, **2** gaps (`php_workers, backup_days`); *the metering gap ratchet is not growing* OK; *every catalogue revision is applied* WARN as before, without `2026-10-game-plans-without-pids` | PASS — viz *Výsledek pokusu 2* |
| `/metrics` (S3) | after ~90 s: `onhost_schedule_last_success_seconds{command="…"}` with real scheduled commands; `command="none"` count **0** | PASS — viz *Výsledek pokusu 2* |
| status grid (S2) | `D.cs.status` rows with `u` in percent; `bad` holds only day indexes **0–29** (empty without an incident in 30 days) | PASS — viz *Výsledek pokusu 2* |
| registrar credit (V1b) | after the next full hour: `failures_in_a_row` 0, the budget row OK and the gate's `expected-nonok` row `CLEARED` | PASS (C4b; `CLEARED` shows at the next gate) |

Commands (shell preparation of the owner document; read-only):

```bash
pq "SELECT count(*) FROM migrations"   # 88
pq "SELECT p.key, coalesce(v.limits->>'pids','-') FROM plan_versions v JOIN plans p ON p.id=v.plan_id JOIN products pr ON pr.id=p.product_id WHERE pr.key='game' ORDER BY 1"
pq "SELECT key, state FROM products WHERE key='cdn'"
art onhost:doctor --json | jq -r '.checks[] | select(.check | test("metering gap|catalogue revision is applied|within its budget")) | "\(.status) \(.check): \(.detail[0:160])"'
curl -s http://127.0.0.1/metrics -H "Host: $SITE" | grep -c '^onhost_schedule_last_success_seconds{command="none"}'
pq "SELECT command, failures_in_a_row, last_success_at IS NOT NULL FROM scheduled_job_runs WHERE command LIKE '%registrar:credit%'"
```

### V2 expected values (public site, read only, mode S, loopback)

| Check | Expected | Result |
| --- | --- | --- |
| CDN page | served `onhost-svc-web.js` carries the S2 page (`CDN a WAF · připravujeme`); `onhost-data.js` `pages.cdn` is a coming page (label `Připravujeme`, no price, no order button) or absent | PASS — viz *Výsledek pokusu 2* |
| Mail price list | `onhost-data.js` names `Antispam rspamd s pravidly serveru (fair use)`; no `Antispam rspamd + …` tier wording | PASS — viz *Výsledek pokusu 2* |
| Database without PITR | `art onhost:catalog:revise` (dry run, **without `--apply`**) shows whether `2026-09-honest-promises` is applied on staging; served `onhost-svc-web.js` without `PITR 7 dní` / `PITR 30 dní` / `PITR 90 dní`; when the revision is applied, `pages.database` in `onhost-data.js` without PITR | PASS — viz *Výsledek pokusu 2* |
| API changelog | served `onhost-public.js` holds `      changes: [],` once (the prototype's invented changelog removed; the live list comes from `docs/api/CHANGELOG.md`) | PASS — viz *Výsledek pokusu 2* |

```bash
curl -s http://127.0.0.1/surfaces/onhost-svc-web.js -H "Host: $SITE" | grep -c 'CDN a WAF · připravujeme'          # ≥ 1
curl -s http://127.0.0.1/surfaces/onhost-svc-web.js -H "Host: $SITE" | grep -c -E 'PITR (7|30|90) dní'               # 0
curl -s http://127.0.0.1/surfaces/onhost-data.js -H "Host: $SITE" | grep -c 'Antispam rspamd s pravidly serveru (fair use)'   # ≥ 1
curl -s http://127.0.0.1/surfaces/onhost-public.js -H "Host: $SITE" | grep -c '^      changes: \[\],$'                # 1
art onhost:catalog:revise                                                                                             # dry run only, no --apply
```

### Výsledek pokusu 2 (2026-10-14, UTC; operátor Claude přes `ssh onhost-staging`)

- **Čtení 12:22:** `VERSION` = `current` = `fda0cedf`, nic zaparkované, 4 jednotky `active` (scheduler, queue@default, @mails,
  @webhooks), migrations 86, `expected-nonok` 31 řádků, disk 33 %. CI na `82bc988f` zelené (14 kontrol).
- **A 12:23:** `/root/v-2026-10`: dump/state/tree `rc=0` 3×, `sha256sum -c SHA256SUMS-v` 5× OK, TABLE DATA 213, migrations 86.
- **B 12:25:** doctor 121 OK / 30 WARN, FAIL žádný; `comm -13` = přesně `automation|every scheduled job ran within its budget`;
  přidán pod `# V-B 2026-10-14 …`; `-rw------- root root`; diff 1× `>`; 32 řádků; zbývající nové 0.
- **C1:** `staging.sh` `6aa3ad41…f9c`, `deploy.sh` `9baa1a22…496`, `deploy-gate.php` `653a7a2b…644` (= tabulka); `bash -n` OK; `check passed`.
- **C2:** rc 0; `app.env matches expected-env` (2×), `expected-env written`; `source-sha` = `82bc988f3de320bc828d1200da5385f2c9a8e71b`.
- **C3** (běh `20261014-122709-82bc988f3de3`): rc 0; **`VERDICT pass`**; 30 EXPECTED včetně V-B; ROW-FAIL / GATED-FAIL / HARD-FAIL
  žádný; CLEARED 2 (`payments|bank statement import`, `security|API tokens must name an organisation (R9)`); `001200` DONE 65.42 ms,
  `001210` DONE 4.21 ms; `released` i `current release` = `82bc988f`.
- **C4:** `VERSION` = `current` = `82bc988f`; nic zaparkované; 4× `active`; `/up` 200 loopback i zvenku; migrations **88**; herní tarify
  `game-8/16/32/custom` bez `pids` (`-`); `cdn|draft`; doctor **0 FAIL**, 121 OK / 30 WARN, žádný non-OK řádek mimo `expected-nonok`;
  *no known metering gap* WARN se 2 mezerami (`php_workers, backup_days`), ratchet OK; *every scheduled job ran within its budget*
  WARN `1 of 92 past their budget: onhost:registrar:credit (never ran, budget 2.2 h, failed 30× in a row)` (očekávané do C4b).
- **C4 metriky (po 90 s):** 92 řádků `onhost_schedule_last_success_seconds` se skutečnými příkazy, `command="none"` 0×. Mřížka:
  web-cz1, managed, apps-cz1, cloud-cz1, games-cz1, dns, domains, mail, payments, portal, ai — vše `100 %`, `bad=[]`.
- **V2:** `CDN a WAF · připravujeme` 1×; `PITR (7|30|90) dní` v `onhost-svc-web.js` 0×; `Antispam rspamd s pravidly serveru (fair use)`
  1×; `changes: [],` 1×. `onhost:catalog:revise` (suchý běh, bez `--apply`): revize `2026-09-honest-promises` (db-s/db-m −pitr_days
  −connections, e-shop a další) a `2026-09-shared-php-workers` **na stagingu nejsou aplikované**; nikdo nedrží staré verze. Zapsáno jako
  stav, nic se neaplikovalo (aplikace = operátor na serveru, bez druhé osoby). Data plánů db-s/db-m tedy na stagingu dál nesou `pitr_days`.
- **C4b 13:10:** `onhost:registrar:credit` proběhl 13:00:09 bez chyby (`failures_in_a_row` 0, `last_success_at` nastaven); doctor
  *every scheduled job ran within its budget* **OK 92/92**. Řádek V-B v `expected-nonok` tím přestal být potřeba; brána příštího
  cyklu ho ukáže jako `CLEARED`. **V1 = PASS.**
- **Nespuštěno:** D (DR-1) a HS-2 — kroky vlastníka. Produkce: nic. `/root/t-2026-10` zůstává (úklid ST-2).

### Findings

1. Obě revize katalogu z 2026-09 čekají na stagingu na `--apply` (operátor na serveru, bez druhé osoby); do té doby řádek *every catalogue revision is applied* zůstává WARN.
2. Řádek V-B: první úspěšný běh `onhost:registrar:credit` na `82bc988f` proběhl 13:00:09 (C4b); příští brána ho ukáže jako `CLEARED`.
3. Další cyklus po sloučení SP1a (PR #221) přinese nový WARN „mail blocklist resolver is accepted“, dokud staging nemá vlastní resolver nebo klíč DQS.

### Left for the owner (codes of the SEZNAM)

DR-1 (step D), HS-2 (HSTS on the vhost), PP-1 … PP-7, ST-1, ST-2 (incl.
`/root/r-2026-10` and `/root/t-2026-10` after a successful cycle), ST-5, ST-7, ST-8 rest, WF-2. Attempt 2 passed, so the production
document `PRODUKCE-prikazy-…` moves to `82bc988f` (prepared diff on the desktop: `PRODUKCE-prikazy-repoint-diff-2026-10-14.md`).
Production: nothing ran.

### Rollback

Release rollback: Rollback C of the owner document (database from `/root/v-2026-10/onhost_staging_b-before-v.dump`, then deploy
`fda0cedf…`). `expected-nonok` rollback: `install -m 0600 -o root -g root /root/v-2026-10/expected-nonok.before-v …`.
