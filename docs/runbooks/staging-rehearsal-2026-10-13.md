# Staging rehearsal, 2026-10-13: protocol (Penpot D0–D5, R21, parked record, browser check N2)

Protocol of the run on staging (`staging.onhost.cz`, 45.67.217.22) on the evening of 2026-10-12 (UTC), filed for phase N. It continues
[staging-rehearsal-2026-10-12.md](staging-rehearsal-2026-10-12.md) and follows the owner's documents on the desktop:
`GENERALKA-Penpot-D-G-prikazy.md` (D–G, version of 2026-10-12 that tolerates `wedos-main`), then
[staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md) (R21) and decision J-R12. **Outputs only, no secrets**: no `app.env` or
private storage was read; no password, TOTP, private key or TXT value was typed or printed. Logs stay on the host in
`/root/penpot-stg/` and `/root/rehearsal-2026-10/` (root, 0700).

## Header

| Item | Value |
| --- | --- |
| Owner "ano" | 2026-10-12, "ano ke stagingu i Penpotu, pokračuj": Penpot D–G (incl. the temporary thaw + unmask, restored in F8 always; F2 paper parent; E2E; G clean-up of the test instance), phase N packages N2 (browser check as K7; F1.7 / F7-19 with a synthetic statement only) and N3 (remaining rehearsal steps without the owner's secrets; old dumps and the parked record per J-R12) |
| Operator | Claude (root over `ssh onhost-staging`, key only), one step at a time, each verified before the next |
| Release | `fa2ea75d6a4f276cc2a248c7829e76f1735e65ed` (VERSION = `releases/current`), unchanged — **no deploy in this run** |
| Doctor | before: 0 FAIL, 29 WARN (end of 2026-10-12 protocol) · after: **0 FAIL, 29 WARN**, gate `VERDICT pass` against `expected-nonok` |

## Step log (times UTC, 2026-10-12)

| Step | Time | Result | Notes |
| --- | --- | --- | --- |
| D0 Penpot checks | 17:39 | OK | VERSION = current = `fa2ea75d`; `e89d7fa9` (PR #144) and `4a494d04` (PR #150, J2) are ancestors; `docker_host` ×5; Ubuntu 22.04 / 5.15 / kvm; Docker 29.6.1, compose 5.3.1; no `policy-rc.d`; 8081/8444/2019/19xxx free, 8080 = Wings; cgroup2, no apparmor userns sysctl; sshd 666 on `0.0.0.0` + `[::]`, no AllowUsers/Groups, sftp present; no `onhost` user, none of the leftovers; 14 rootful containers; docker + wings active; ufw active (deny incoming); 6.7 GB available RAM, 265 GB free; nginx 1.31.2, `vhost/nginx/*.conf` included, listen lines without an IP; DNS → 45.67.217.22, no AAAA; region `cz1`; `provider_instances` = only `wedos-main | wedos | active | 0`; `stored-secrets=0`. Note: `docker-ce-rootless-extras` 29.6.1 was already installed (not a leftover of ours); `/etc/docker/daemon.json` exists but is empty. The `stored-secrets` tinker line needs `HOME=/tmp` (psysh cannot write `/root/.config` as www) — see *Findings* 3 |
| D1 key pair | 17:40 | OK | private key `-rw------- root root`, public `256 SHA256:QZYuWz79QTXeDzXiLfmNQG9ZaYqxnazry7AD/P8wFls onhost-penpot-stg1 (ED25519)` |
| D2 Caddy apt key | 17:40 | OK | fingerprint `65760C51EDEA2017CEA2CA15155B6D79CA56EA34` = the document's value; keyring `0644` (side effect: gpg created `/root/.gnupg`) |
| D3 scripts + dry run | 17:41 | OK | both scripts identical to `repo.git` at the release SHA (`0273a8ab…` provision, `90c873d9…` verify); `grep -c` 6/3/5/4; `WARN: OS 'ubuntu 22.04'`; `a shared host`; `left exactly as it is … NO restart`; `Docker Engine 29.6.1 … left alone`; `WARN: no /etc/docker/daemon.json` (informational); dry-run lines only as expected, no `usermod -aG docker`; "dry-run: Docker/ufw/sshd beze změn" |
| D4 provision ×2 | 17:41–17:42 | OK | `rc=0` twice; run1 `7. Done (12 change(s)…`, run2 `7. Done (0 change(s)…`; docker not restarted; game containers unchanged; docker/wings/caddy active; Caddy only on `127.0.0.1:8081` and `127.0.0.1:2019`; Caddyfile managed header, `http_port 8081`, `https_port 8444`, `default_bind 127.0.0.1`, `local_certs`, `import /etc/caddy/onhost-penpot/*.caddy`; sudoers as expected; key `from="127.0.0.1,::1",no-port-forwarding,…`; `id -nG onhost` = `onhost`; no sshd drop-in; ufw unchanged; `/up` 200. Expected WARN: not XFS with prjquota |
| D5 rootless Docker | 17:42–17:43 | OK | `onhost` not in docker; subuid/subgid `755360:65536`; apt simulation only `libslirp0`, `slirp4netns`, `uidmap` (no docker-ce/cli/containerd, no `Remv`); `install rc=0`; docker not restarted; controllers `cpuset cpu io memory pids`; setuptool rc 0; socket `/run/user/1010/docker.sock`; `29.6.1 cgroup2 driver=systemd json-file [… name=rootless …]`; `Hello from Docker!`; `porttest 404` on `127.0.0.1:19999` only; rootful socket `permission denied`; game containers unchanged; 0 segfaults. The journal grep matched AppArmor denials of `tcpdump` opening `libusranalyse.so` — pre-existing (720 since 13:44 local), not caused by D5 |
| D6 wildcard certificate | — | **skipped (owner)** | DNS-01 TXT in the live zone `onhost.cz`, the owner's e-mail and the Let's Encrypt terms are the owner's own steps |
| D7 nginx vhost + selftest, D8 verify-node | — | not run | both need the certificate of D6 (`ssl_certificate` in the vhost, `--dns-name selftest…`) |
| E1–E6, F0–F8, G1 | — | not run | branch stopped at D6. E2 would make the host Path A (stored secret → every `staging.sh deploy` rc 2), so it waits for a run that can go on to F. F0 would also STOP today — see *Findings* 1. F3 (thaw) was **not** done, so F8 was not needed: freeze and all `provider-*` masks are as before |
| R21 webhook lane | 17:44 | OK (lane part) | copy `/root/rehearsal-2026-10/expected-units.before-r21`; `onhost-queue@webhooks.service` appended to `$S/expected-units` (fixed line of 2026-10-12, *Findings* 3 there); `systemctl enable --now onhost-queue@webhooks` → `active (running)`; default, mails, scheduler still active; row *rotated webhook secrets overlap only briefly* OK (0 endpoints in an overlap, 60 min). The test ping needs the owner's endpoint and API credentials → owner. Rollback: `systemctl disable --now onhost-queue@webhooks` and restore the copy |
| J-R12 parked record | 17:44 | OK (renamed, not deleted) | `releases/b3ac309d….parked` renamed to `….parked-20261012T174432Z.cleared` — the convention `release_good()` of `staging.sh` uses for a cleared park; `staging.sh status` no longer prints `PARKED`. The record is kept as history |
| J-R12 old dumps | — | **owner** | `onhost_staging_b-before-r2b.dump` and `…-before-r2b5.dump` (about 4.4 MB each) in `/root/r2b-2026-10/` were **not** deleted: permanent deletion of data is left to the owner (command below) |
| Doctor + gate after N3 | 17:45 | OK | `doctor-n3-2026-10-12.json`: 0 FAIL, 29 WARN; `deploy-gate.php verdict … --expected-file $S/expected-nonok` → `VERDICT pass`, no NOT EXPECTED / CLEARED row; Penpot rows unchanged (*sold only with a Penpot node* WARN, listed); `/up` 200 |
| N2 browser check | 17:45–17:47 | done as far as possible, findings | see *Browser check*. No form was filled in, no account created, nothing ordered |

## Browser check (N2, as K7, on the deployed release `fa2ea75d`)

| # | What | Result |
| --- | --- | --- |
| B1 | Cookies (Comgate row 6) | the application sets `XSRF-TOKEN` (Secure, SameSite=Lax, 2 h) and `onhost-session` (Secure, HttpOnly, SameSite=Lax, 2 h); no banner. **Extra:** `server_session_64d01d7e` (Secure, HttpOnly, SameSite=Lax, 10 days) comes from the aaPanel WAF (`/www/server/btwaf/public/public.lua`), not from the app; privacy section 4 does not name it. Also `Strict-Transport-Security` is sent twice (app: `max-age=31536000; includeSubDomains; preload`, nginx: `max-age=31536000`) |
| B2 | Pricing page → cart (Comgate row 12) | the web hosting "Standard" card reaches the catalogue (checkout summary "Webhosting Standard · 1 měsíc", 189 Kč — TASK-0162 works), **but** the cart drawer still labels the line "VPS Standard" and offers VPS add-ons for it ("Zálohy VPS (PBS)", "k položce VPS Standard"); checkout step 1 lists the web add-ons and the PBS backups together. New finding (prototype label + add-on lookup by the label) |
| B3 | F1.7 step 1 (`#/sluzba/legal`) | old page as expected for a release without PR #165: "VOP v4.2", ISO 27001, NIS2, "cookie lišta", "12 stran", 99,99 % / 15 min |
| B4 | F1.7 step 2 (`#/kosik`) | old checkout copy as expected without PR #167/#168: "Karta, převod nebo faktura", "Doplňky (ceny bez DPH)", credit "+ 100 Kč …", the CDN add-on offered, "Prioritní podpora — reakce do 10 minut, 24/7"; `DPH 21 %` shown (the staging legal entity is still a payer, 2026-10-12 protocol) |
| B5 | Console | `GET /%7B%7B%20gcx.gameArt%20%7D%7D` → 404 on the home page (fixed by M2, not deployed); no other error |
| — | F1.7 steps 3–4, F7-19 | **not tested**: the release has no M1–M3 code (PR #165–#168 are after `fa2ea75d`); step 3 needs a guest order (creates an account), F7-19 needs a staff login with TOTP — both the owner's |

## Findings

1. **F0 would STOP although D0 passed.** The updated document assumes `wedos-main | wedos | disabled | 0`; on staging it is
   **`active`** (0 nodes, 0 stored secrets, provider queue `provider-registrar` masked). D0's STOP list does not cover an active
   registrar, F0's does ("registrátor `active` … se počítá jako „jiná“"). Before F the owner decides: amend F0 to accept an active
   registrar with 0 nodes and 0 secrets (its lane stays masked during the thaw), or set `wedos-main` to `disabled` for the test
   with its own documented line.
2. **Cart label (B2).** A web hosting card puts a line named "VPS Standard" in the drawer and the drawer offers VPS add-ons for
   it. The order itself is the web plan; the label and the add-on list are wrong. Proposed follow-up task (surface seam, not this
   one).
3. **Shell detail.** `art tinker` as www with root's `HOME` fails (psysh cannot write `/root/.config/psysh`, rc 1, no output).
   `export HOME=/tmp` before the call fixes it; the owner documents' `art()` should set it.

## Left for the owner

- **D6** (then D7, D8, E, F, G1 can continue): `apt-get install certbot`, then
  `certbot certonly --manual --preferred-challenges dns --agree-tos --no-eff-email -m <your e-mail> -d penpot.onhost.cz -d '*.penpot.onhost.cz'`,
  add the two TXT values at `_acme-challenge.penpot.onhost.cz`, check with `dig +short TXT _acme-challenge.penpot.onhost.cz @1.1.1.1`,
  Enter, then delete the TXT records. After that D7 needs a check of `https://selftest.penpot.onhost.cz/` from your PC.
- **F0 decision** (finding 1), then F1 (Sandbox switch in the staff console with step-up), F4–F7 (customer panel order, "Nastavit
  heslo", login and upload from your PC, suspend/resume/cancel in the staff console).
- **Old dumps (J-R12):** `shred -u /root/r2b-2026-10/onhost_staging_b-before-r2b.dump /root/r2b-2026-10/onhost_staging_b-before-r2b5.dump`.
- **R21 test ping** with your own endpoint: `POST /v1/webhooks/{endpoint}/ping`, `GET /v1/webhooks/{endpoint}/deliveries`.
- **Cookie (B1):** name `server_session_<hash>` in privacy section 4, or switch the WAF cookie off on the production host.
- **F1.7 / F7-19 on staging:** a new A–C cycle with a frozen SHA that contains PR #165–#168 (and phase N), then the guest order and
  the staff import with a synthetic statement.
- Unchanged from 2026-10-12: VAT entity switch, R12 token switch, R15/R16, R25 test backup.

## State left on the host (Penpot, for the continuation)

`onhost` user (uid 1010, linger on, rootless Docker 29.6.1 with `hello-world` and `busybox` images), Caddy on `127.0.0.1:8081`/`:2019`
(`/etc/caddy`), `/srv/onhost-penpot`, `/var/backups/onhost-penpot`, `/etc/sudoers.d/onhost-penpot`, `/usr/local/sbin/onhost-penpot-quota`,
`/etc/systemd/system/user@.service.d/delegate.conf`, packages `caddy`, `uidmap`, `slirp4netns`, `libslirp0`, keyring and
`caddy-stable.list`; `$K=/root/penpot-stg` holds the key pair (private 0600, never printed), scripts and logs. No provider instance,
node, secret, certificate or vhost exists, so deploys stay Path B. Full removal: *D-rollback* of the owner document (its own "ano").
