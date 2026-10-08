# Staging rehearsal protocol, started 2026-10-07 (phase I, I6)

**Status: R0–R2 approved by the owner (2026-10-08), NOT RUN — no operator shell on the staging host (see *Attempt 2026-10-08*
below). No step has been run.** This is the protocol of the rehearsal scripted in
[staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md) (steps R0–R24). It holds **outputs only**: the doctor row a step
watches, the exit code, what differed, which rollback was used. **No password, TOTP secret, recovery code, token, key or env value
ever goes into this file** — not even redacted fragments.

Rules (from the script, unchanged): one explicit owner "yes" per step, before the command is typed; staging only, nothing on
production; read before write; a step that fails is rolled back by its own *Rollback* line and the rehearsal stops — it is not
retried "another way" without a new yes, and the cause becomes a package. The owner's proposal for the first steps is
`GENERALKA-R0-R2-navrh.md` on the owner's desktop (outside the repository).

## Header

| Field | Value |
| --- | --- |
| Rehearsal date | ____ |
| Staging commit (`$S/releases/current`) | ____ |
| `VERSION` on staging | ____ |
| Operator | ____ |
| Owner present | ☐ |
| Repository state the script was checked against | `development` at `c0fc7ff1` (2026-10-07), CI `tests`, `security`, `e2e smoke` green |

## Step log

Result: **OK** (as expected) / **differs** (stopped, see notes) / **skipped** (with reason) / **not run**.

| Step | Owner yes (time) | Start–end | Result | Doctor row watched (status, detail) | Notes (what differed, rollback, follow-up package) |
| --- | --- | --- | --- | --- | --- |
| Shell variables + `/root/rehearsal-2026-10` | 2026-10-08 (prep + R0–R2, one approval) | — | skipped | — | no root shell on the staging host from the AI workstation; see *Attempt 2026-10-08* |
| R0 release and gate lists | 2026-10-08 | — | skipped | `staging.sh status` lines not OK | same cause; nothing was read or written on staging |
| R1 doctor before | 2026-10-08 | — | skipped | rc, FAIL count; 13 watched rows below | same cause |
| R2 PHP binary | 2026-10-08 | — | skipped | *PHP binary is the one the deploy and the workers must use* | same cause; pre-flight finding F1 (8.3 vs 8.5 path) must be settled first |
| R3 usranalyse directives | | | not run | queue workers up (no exit 7) | |
| R4 TRUSTED_PROXIES loopback | | | not run | *trusted proxies are exact addresses* | |
| R5 shared cache | | | not run | *cache store is shared (rate limits)* | |
| R6 revise web plans, dry run | | | not run | — | |
| R7 revise web plans, apply | | | not run | *catalogue revision 2026-10-deliverable-web-plans applied* | |
| R8 revise custom ISO, dry run | | | not run | — (owner decision 9 first) | |
| R9 revise Penpot, dry run | | | not run | — | |
| R10 staff TOTP (owner himself) | | | not run | *staff and demo accounts have an authenticator* | |
| R11 exchange rates | | | not run | *exchange rates are fresh* | |
| R12 tokens (dry run, switch) | | | not run | *API tokens must name an organisation (R9)* | |
| R13 reinstatement audit | | | not run | *pay and restore (services.reinstate)* | |
| R14 VAT entity (non-payer) | | | not run | *VAT payer mode agrees with the legal entity* | |
| R15 clamd | | | not run | `onhost:isos:scanner-check --fresh` | |
| R16 ISO volume, PHP and nginx | | | not run | by hand: `findmnt`, `stat`, `df`, `realpath` | |
| R17 Proxmox ISO storage | | | not run | instance option `custom_iso_storage` | |
| R18 revise custom ISO, apply | | | not run | *custom ISO virus scan passes its self-test*, *custom ISO storage has room* | only after R15–R17 OK |
| R19 ISO upload smoke | | | not run | manual test `05-vps.md` | |
| R20 revise Penpot, apply | | | not run | *Penpot is sold only with a Penpot node to run it* (FAIL expected, non-blocking) | |
| R21 webhook lane and overlap | | | not run | *rotated webhook secrets overlap only briefly* | |
| R22 dead letters | | | not run | *no outbox dead letters* | |
| R23 final doctor, `expected-nonok` | | | not run | 0 unexpected FAIL | |
| R24 gated deploy (optional) | | | not run | gate verdict | owner decides |

## R1 starting picture (fill from `doctor-R1.json`)

| Doctor row | Status at R1 | Status at R23 |
| --- | --- | --- |
| PHP binary is the one the deploy and the workers must use | | |
| trusted proxies are exact addresses | | |
| cache store is shared (rate limits) | | |
| every catalogue revision is applied | | |
| catalogue revision 2026-10-deliverable-web-plans applied | | |
| staff and demo accounts have an authenticator | | |
| exchange rates are fresh | | |
| API tokens must name an organisation (R9) | | |
| VAT payer mode agrees with the legal entity | | |
| custom ISO virus scan passes its self-test | | |
| custom ISO storage has room | | |
| rotated webhook secrets overlap only briefly | | |
| no outbox dead letters | | |

## Attempt 2026-10-08 (prep, R0–R2)

The owner approved the shell preparation and R0, R1 and R2 exactly as proposed in `GENERALKA-R0-R2-navrh.md`
("Generálka R0-R2 ano - připravit a zpracovat", 2026-10-08). R3 and later were **not** approved and were not touched.

* **Result: skipped, nothing run.** The AI workstation has no operator shell on the staging host: no SSH host entry or
  configured user for `staging.onhost.cz`, and a non-interactive key-only connection attempt to port 22 of the name was
  refused at the TCP level (no authentication was attempted, no command reached the host). The proposal itself says the steps
  are typed by the owner, or an operator sitting with him, as root on the host. Nothing was read or written on staging, on
  production or on any panel; no secret was used.
* **What is needed to run them:** either the owner (or his operator) types the prep block and R0–R2 in a root shell on the
  staging host and pastes the non-secret output back for this protocol, or the owner provides a root (or sudo) SSH route to the
  host for the AI workstation (port, user, the workstation key authorised there). The second is a new decision of the owner.

### Pre-flight finding F1 — the PHP path of the script does not match the staging host

Checked against the repository only (`development` at `fd9ed798`):

* The rehearsal script and the proposal set `P=/www/server/php/83/bin/php`.
* `infra/aapanel/staging.sh` (which installed and deploys staging) uses `PHP=/www/server/php/85/bin/php`, and `install.sh`
  writes that path into `ExecStart` of `onhost-queue@.service` and `onhost-scheduler.service`.
* [staging-launch.md](staging-launch.md) (the note after S7) already says that on a staging running 8.5 the constant must be
  `P=/www/server/php/85/bin/php`.

Consequence if R0–R2 are typed as written: every `art` call (and so the doctor in R0, R1, R2) runs under 8.3 if that binary
exists, or fails if it does not; R2's "both units name the same `$P`" would report a difference that is a script error, not a
staging defect. **Before the prep block is typed** the owner checks `ls /www/server/php/*/bin/php` and
`grep -n '^ExecStart' /etc/systemd/system/onhost-queue@.service` (both read-only) and sets `P` to the path the units name.
Changing `P` is a change of the approved command, so it needs the owner's word; the rehearsal script gets the fix in a
follow-up package.

## Outputs per step

Paste only non-secret output (doctor rows, exit codes, `ls -l` lines, SHAs). The full doctor reports stay in
`/root/rehearsal-2026-10/` on the staging host.

### R0

```
(not run 2026-10-08: no shell on the staging host, see "Attempt 2026-10-08")
```

### R1

```
(not run 2026-10-08: no shell on the staging host, see "Attempt 2026-10-08")
```

### R2

```
(not run 2026-10-08: no shell on the staging host; settle finding F1 first)
```

(Sections R3 … R24 are added as the steps are run, in the same form. The owner's proposal for R3–R5 is
`GENERALKA-R3-R5-navrh.md` on the owner's desktop; none of them may run before R0–R2 are done and recorded here.)

## Closing paragraph (after the last step)

The doctor at R1 and R23, which rows moved, which steps were skipped and why, which rollback was used, the updated
`expected-nonok`, and the list of items that must go into the production plan
([go-live-checklist.md](go-live-checklist.md), section *Blocking for production*).
