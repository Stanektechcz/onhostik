# Staging rehearsal protocol, started 2026-10-07 (phase I, I6)

**Status: SKELETON — no step has been run.** This is the protocol of the rehearsal scripted in
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
| Shell variables + `/root/rehearsal-2026-10` | | | not run | — | |
| R0 release and gate lists | | | not run | `staging.sh status` lines not OK | |
| R1 doctor before | | | not run | rc, FAIL count; 13 watched rows below | |
| R2 PHP binary | | | not run | *PHP binary is the one the deploy and the workers must use* | |
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

## Outputs per step

Paste only non-secret output (doctor rows, exit codes, `ls -l` lines, SHAs). The full doctor reports stay in
`/root/rehearsal-2026-10/` on the staging host.

### R0

```
(not run)
```

### R1

```
(not run)
```

### R2

```
(not run)
```

(Sections R3 … R24 are added as the steps are run, in the same form.)

## Closing paragraph (after the last step)

The doctor at R1 and R23, which rows moved, which steps were skipped and why, which rollback was used, the updated
`expected-nonok`, and the list of items that must go into the production plan
([go-live-checklist.md](go-live-checklist.md), section *Blocking for production*).
