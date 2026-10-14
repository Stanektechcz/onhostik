# Staging rehearsal, 2026-10-15: protocol (phase P: P4 guest browser check, P5 state, ST-* items)

Protocol of the read-only run on staging (`staging.onhost.cz`, 45.67.217.22) on 2026-10-13 between 10:26 and 10:45 UTC, filed for
the protocol day 2026-10-15 (the days 2026-10-13 and 2026-10-14 already have a protocol). It continues
[staging-rehearsal-2026-10-14.md](staging-rehearsal-2026-10-14.md) and follows `PROMPT-faze-P-2026-10-13.md` (section *Brána kroků na
stagingu*, packages P4 and P5) and `GENERALKA-Penpot-D-G-prikazy.md`. **Outputs only, no secrets**: no `app.env` or private storage was
read; no password, TOTP, private key, cookie value or TXT value was typed or printed.

## Header

| Item | Value |
| --- | --- |
| Owner "ano" | 2026-10-13, "ano ke stagingu, pokračuj": the staging steps of phase P that need no data only the owner can supply — P4 browser check (read-only / guest), P5 Penpot steps that do not depend on PP-1, ST-* items without owner data. Permanent deletion (old dumps) stays the owner's |
| Operator | Claude (root over `ssh onhost-staging`, key only); every SSH command of this run was mode **S** (read only) |
| Release | **`199071b862e872a0edf496b2f567de9128ac8207`** unchanged (`VERSION` = `releases/current`); nothing deployed, no new A–C cycle (P5: only after G1 or a separate Path A proposal) |
| Doctor | **0 FAIL**, 120 OK, 28 WARN (same as after C4 of 2026-10-14) |
| Migrations | 83 |
| Writes to the host | **none**. The only state the run created is one anonymous guest cart (`PUT /v1/cart`, `POST /v1/cart/quote`) from the browser check; no order, no account, no form submitted |

**Why nothing was written:** every remaining staging write of P4/P5/ST either needs data only the owner holds (Let's Encrypt e-mail and
terms, WEDOS TXT, staff login with TOTP, a webhook endpoint, a second person), is a permanent deletion (owner's), or turned out to be
already done or blocked by the deployer (ST-3, ST-4, ST-6 below).

## Step log (times UTC, 2026-10-13)

| Step | Time | Mode | Result | Notes |
| --- | --- | --- | --- | --- |
| Host state | 10:26 | S | OK | `VERSION` = `current` = `199071b8`; no `*.parked` (only `b3ac309d….parked-20261012T174432Z.cleared`, 153 B, root 0600); not in maintenance; scheduler + queue default / mails / webhooks `active`; `onhost-queue@provider-registrar` and `@provider-penpot` `inactive`, registrar `masked`; `nginx -t` successful; `/up` 200 loopback and public; 265 GB free on `/`, 5 GB free RAM |
| Doctor | 10:28 | S | OK | `{"OK":120,"WARN":28}`, no FAIL; `staging.sh status` lists the 28 WARN (unchanged set, incl. *VAT payer mode agrees with the legal entity*, *API tokens must name an organisation (R9)*, *Penpot is sold only with a Penpot node*) and `/up over loopback: 200` |
| Provider state (F0 precondition) | 10:28 | S | OK | `provider_instances`: only `wedos-main | wedos | active | 0` (tolerated: 0 nodes); `stored-secrets=0` (Path B); no service of any family; databases: only `onhost_staging_b` (90 MB) |
| Penpot D0–D5 state | 10:30 | S | OK, unchanged | user `onhost` (uid 1010), groups `onhost` only (not `docker`); rootless Docker `29.6.1` (`name=rootless`, `cgroupns`); Caddy only on `127.0.0.1:8081` and `127.0.0.1:2019`; docker / wings / caddy `active`; Docker `ActiveEnterTimestamp` 2026-07-07 (never restarted); 14 rootful containers, 0 named `penpot` |
| D6 precondition | 10:30 | S | waiting for PP-1 | `certbot 1.21.0`, `certbot.timer` active; **no** `/etc/letsencrypt/live` and **no** `/etc/letsencrypt/accounts` (no account, no terms accepted); no nginx vhost for Penpot. DNS: 0 TXT at `_acme-challenge.penpot.onhost.cz` (`@ns.wedos.cz`); `penpot.onhost.cz` and `x.penpot.onhost.cz` → 45.67.217.22; no AAAA; no CAA on `onhost.cz` |
| D7, D8, E1–E5, F0–F8, G1 | — | — | not run | D7 needs the D6 certificate; the rest follows in order. Each still needs its own "ano" (PP-3) |
| ST-3 / G2 | 10:28 | S | **already done** | `onhost_staging_b_r2b5_failed` was dumped (`/root/j-2026-10/onhost_staging_b_r2b5_failed.dump`, 211 `TABLE DATA`) and dropped on 2026-10-12 09:11 ([protocol 2026-10-12](staging-rehearsal-2026-10-12.md), step G2); `…_r2b5a_failed` never existed. Nothing left to do |
| ST-4 parked record | 10:26 | S | **already cleared** | renamed (not deleted) to `….parked-20261012T174432Z.cleared` on 2026-10-12 ([protocol 2026-10-13](staging-rehearsal-2026-10-13.md)); `staging.sh status` prints no `PARKED`. Deleting the 153-byte history record is optional and the owner's (deletion) |
| ST-1 old dumps | 10:28 | S | owner | both files still exist (`onhost_staging_b-before-r2b.dump` 4.4 MB, `…-before-r2b5.dump` 4.4 MB, root 0600). Backup sets: `/root/o-2026-10` 95 MB, `/root/j-2026-10` 87 MB, `/root/r2b-2026-10` 79 MB (ST-2: kept until the next successful A–C, P-R61) |
| ST-6 token switch | 10:31 | S | **blocked by the deployer spec** | `config('onhost.token_organization_required')` = `false`; `operator:tokens:unbound --dry-run` → 0 unbound tokens, 0 used across organisations. But `staging.sh` writes `ONHOST_TOKEN_ORGANIZATION_REQUIRED=false` into `expected-env` (C2) and `deploy.sh` stops with rc 2 when `app.env` differs (`env-assert`). Setting it `true` in `app.env` alone would stop the next deploy — finding 1 |
| P4 browser check | 10:27–10:45 | guest | see below | read-only guest walk on the deployed site; no order |
| Cookies (Comgate row 6) | 10:44 | S (curl) | OK | `XSRF-TOKEN` (Max-Age 7200, secure, samesite=lax), `onhost-session` (Max-Age 7200, secure, httponly, samesite=lax), `server_session_64d01d7e` (aaPanel WAF, Max-Age 864000, HttpOnly, Secure, SameSite=Lax) — the three cookies the privacy page names. `Strict-Transport-Security` still sent twice (P11, HS-1) |

## P4: browser check on `199071b8` (guest only)

Release `199071b8` contains M1, M2, N1 and O1, but **not** phase P (PR #180, TASK-0184: support hours, DDoS, benchmarks), so the P1–P3
texts are expected to be still the old ones here. The staging legal entity is still in **VAT payer mode** (ST-5 open), so every check
reads the VAT payer variant of the manual tests (prices "s DPH", "Doplňky (ceny bez DPH)", footer "Všechny ceny včetně DPH 21 %").

| Manual test | Steps | Result | Notes |
| --- | --- | --- | --- |
| F1.7 | 1 | PASS | `#/sluzba/legal`: 7 documents in force, each "Verze 2026-09", rows open `/dokumenty/vop`, `…/odstoupeni`, `…/ochrana-osobnich-udaju`, `…/sla`; `/dokumenty` lists the same 7 (`vop`, `ochrana-osobnich-udaju`, `dpa`, `sla`, `odstoupeni`, `obnovovani`, `podminky-registrace-domen`); no "v4.2", ISO 27001, NIS2 or cookie banner; SLA strip 99,9 % and 4 h |
| F1.7 | 2 | PASS (payer variant) / finding | `#/kosik` with Webhosting Standard: strip "Platba převodem — Převodem podle zálohové faktury s QR platbou. Platbu kartou zatím nenabízíme."; no "Karta, převod nebo faktura"; "Doplňky (ceny bez DPH)" is the payer variant; the prepaid credit has no "+ 100 Kč". **But** the plan options offer "WAF + CDN — firewall aplikací a anycast cache + 99 Kč / měs." and "Prioritní podpora — reakce do 10 minut, 24/7 + 149 Kč / měs." (finding 2) |
| F1.7 | 3–4 | owner | completing the order creates a guest account and an order (step 3 needs personal data; the step-4 button is on the payment step) |
| F1.8 | 1–4 | PASS (payer variant) | VPS: supertitle "AMD EPYC · NVMe", no "root do 55 s", "0 % přeprodej", "99,99"; SLA strip "99,9 % měsíční dostupnost (Standard, SLA)" and "4 h reakce na incident (Standard)". Managed: no "15 min", no "99,99"; "Reakce na incident podle SLA" ×2, "SLA s kompenzací kreditem", "Standard do 4 hodin, Business do 1 hodiny". FAQ intro "Napište nám přes zákaznický panel nebo e-mailem", closing "Měsíčně nebo ročně, výpověď ke konci období." / 14-day withdrawal. Game configurator (Minecraft Java): "102 Kč / měsíc s DPH" + "bez DPH 84 Kč" (payer variant), perks "Konzole, SFTP a plánované restarty" and "Podpora přes panel a e-mail", no "podpora 24/7", no "Anti-DDoS ochrana v ceně" |
| F1.9 | 1–5 | PASS | Home: closing block as expected, no "14 dní zdarma". Registration: "Vytvořit účet" (no trial wording on it). VPS: new title, "Třída CPU podle tarifu", no steal time, 2× N+1 or 10 Gbit/s. Inference, vector DB, object storage and the solution pages Pro vývojáře / Energetika / Pro agentury / Pro e-shopy / Pro startupy: no "99,99", top plan "SLA podle individuální smlouvy", comparison "podle smlouvy" (vector DB has no SLA row), solution strip "99,9 % měsíční dostupnost (Standard, SLA)". Pricing `#/ceny` → Standard "Objednat": cart line **"Webhosting Standard"**, add-on **"Rozšířené zálohy · 7 denních + 4 týdenní"**, the same in the checkout summary (B2 fix TASK-0178 VERIFIED on the deployed site) |
| F7-19 | — | owner | staff import of a synthetic statement needs a staff login with TOTP (`billing.reconcile`) |
| English variant | — | NOT TESTED | |

Browser console: no error; no request to `/%7B%7B%20gcx.gameArt%20%7D%7D`. Network: only `PUT /v1/cart` and `POST /v1/cart/quote` as writes (anonymous cart).

**Expected on this release, fixed on `development` by PR #180 (not deployed):** "Zásahy 24/7" (Managed), "24/7" and "30 min" on the
solution pages, the game page badge "ANTI-DDOS 1,2 TBPS · 24/7" and "Anti-DDoS profily … pro každou hru", the VPS benchmark "1 842 pts" /
"VPS s přeprodanými jádry 790 pts". They go with the next A–C cycle.

## Findings

1. **ST-6 (R12) cannot be done on `app.env` alone.** `infra/aapanel/staging.sh` (`write_expected_env`) pins
   `ONHOST_TOKEN_ORGANIZATION_REQUIRED=false` in `expected-env`, and `deploy.sh` refuses a deploy (rc 2, `env-assert`) when `app.env`
   holds anything else. Rehearsing step 8 of the production plan on staging therefore needs a repo change first (the spec line, e.g.
   `ONHOST_TOKEN_ORGANIZATION_REQUIRED~=^(true|false)?$` or `=true`), then C2 of the next A–C cycle rewrites `expected-env`, and only then
   the `app.env` line + `config:cache`. Staging has 0 unbound tokens, so the switch itself is harmless. Package for the coordinator.
2. **Two plan options still promise what was decided against** (catalogue `CatalogSeeder`, plan options of `web-hosting`, `wordpress`,
   `eshop`, `web-custom`; unchanged on `development` tip `55a0e7e6`): `priority_support` "reakce do 10 minut, 24/7" (support hours
   decided Mon–Fri 8–17, PR #180 changed only the page copy) and `waf_cdn` "WAF + CDN — firewall aplikací a anycast cache" (owner
   decision 2026-10-06: no CDN; M2b gates only the `cdn` product through `CdnAvailability`). Both are offered in the checkout on staging.
   Needs a data migration in the `001160` pattern plus seeder change (coordinator, P1/P2 follow-up).
3. **Minor copy leftovers outside the manual tests:** the header CTA "Vyzkoušet zdarma" (4× on the home page, every page header) although
   there is no trial (O1; only `PricingPageSeam` swaps the pricing-page button); the vector DB page shows "14 ms p95 dotaz nad 5 mil.
   vektorů" and "98,6 % recall@10" with no measurement behind them (J-R2 class). For the coordinator's next seam package.
4. **SEZNAM is stale on ST-3 and ST-4:** G2 was done on 2026-10-12, the parked record was cleared the same day (see the step log).
5. **The staging VAT mode hides the non-payer texts:** with the entity still a VAT payer (ST-5), the deployed site shows "s DPH",
   "Doplňky (ceny bez DPH)" and the footer "Všechny ceny včetně DPH 21 %"; the non-payer wording (Comgate review row 9) can be
   checked on staging only after ST-5.

## Left for the owner (codes of `SEZNAM-vstupu-vlastnika-2026-10-13.md`)

- **PP-1 (D6 certificate)** — exactly as in `GENERALKA-Penpot-D-G-prikazy.md` D6: your e-mail for the Let's Encrypt account; answer the
  Subscriber Agreement yourself (`Y`; the command has no `--agree-tos`); in WEDOS add the two TXT records `_acme-challenge.penpot`
  (TTL 300) that certbot shows, check both with `dig +short TXT _acme-challenge.penpot.onhost.cz @ns.wedos.cz` and `@1.1.1.1` before the
  second Enter, delete both after issue; command (aaPanel → Terminál, root):
  `certbot certonly --manual --preferred-challenges dns --no-eff-email -m <your e-mail> -d penpot.onhost.cz -d '*.penpot.onhost.cz'`;
  send back only the `openssl x509 … -subject -issuer -enddate -ext subjectAltName` output. Preconditions re-verified today (above).
- **PP-2 / PP-3** — after D6: "ano D7" (nginx vhost + selftest, Claude), then open `https://selftest.penpot.onhost.cz/` from your PC;
  then "ano" to D8, E1–E5, F0 one by one.
- **PP-4 … PP-6** — F1 sandbox switch (staff login, TOTP, step-up), F2 "ano", F4–F7 in the panels.
- **ST-1** — `shred -u /root/r2b-2026-10/onhost_staging_b-before-r2b.dump /root/r2b-2026-10/onhost_staging_b-before-r2b5.dump` (both still
  present) or "ST-1: ano" for Claude to run it as one Z1 step.
- **ST-4 (optional)** — the cleared record can stay as history; deleting it is
  `rm /var/lib/onhost-deploy/staging.onhost.cz/releases/b3ac309d88de89243ba1c52daf04a6bed760ffcb.parked-20261012T174432Z.cleared`.
- **ST-5** — VAT payer → non-payer switch of the staging legal entity in the staff console (finance, step-up, second person).
- **ST-6** — waits for finding 1 (repo change of the deployer spec, then a new A–C cycle), then the `app.env` line with your "ano".
- **ST-7** — test ping of a webhook to your own endpoint.
- **ST-8 rest** — F1.7 steps 3–4 (guest order creates an account) and F7-19 (staff import of a synthetic statement): your customer and
  staff logins.
- Nothing needed any more: **ST-3** (done 2026-10-12), **ST-9** (skipped, P-R68).

## Rollback

Nothing was changed on the host. The anonymous guest cart expires with the cart retention like any visitor's cart.
