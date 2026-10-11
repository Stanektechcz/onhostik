# Staging rehearsal, phase J — protocol (opened 2026-10-11)

Protocol of the phase J rehearsal on staging (`PROMPT-faze-J-2026-10-11.md`, package J3). **Outputs only, no secrets**; the full
doctor reports stay on the host in `/root/rehearsal-2026-10/` and `/root/j-2026-10/`. Steps follow the owner's documents on the
desktop: `GENERALKA-J-A-C-deploy-prikazy-2026-10-11.md` (A–C), `GENERALKA-Penpot-D-G-prikazy.md` (D–G, aligned with J2), then
[staging-rehearsal-2026-10.md](staging-rehearsal-2026-10.md) R3–R24 and R25. Every step has the owner's own "ano"; a step that is
skipped is written down with its reason, never as green. The previous protocol is [staging-rehearsal-2026-10-07.md](staging-rehearsal-2026-10-07.md).

## Header

| Item | Value |
| --- | --- |
| Frozen deploy target (J1) | `f630612f7adbfd60238d1ff0b96bd6ef236cc47c` (`development` after the merges of #146–#151 (J0, J8, J9, J2, J4)) |
| Release on staging when the protocol opened | `e711b7c7` (2026-09-28); installed deployer `b3ac309d` (refuses a downgrade of itself, deploys an older app ref) |
| Migrations | 65 on staging → 81 at the target (16 new: 000920 … 001110 as in R2b-5, plus 001125, 001130, 001140, 001145, 001150) |
| Gate prediction (J1, repository only) | `VERDICT pass` once section B adds 6 WARN rows: 26 EXPECTED (current list), 6 new EXPECTED, 4 CLEARED, 0 ROW-FAIL. No doctor row was added between `b3ac309d` and the target; two existing rows changed their test (*legal entity bank details real*: IBAN check digits; *Penpot is sold only with a Penpot node to run it*: sandbox nodes apart) |
| Deployer files changed since `b3ac309d` | none (`deploy.sh`, `deploy-gate.php`, `staging.sh`, `install*.sh` identical) |
| Irreversible data in the migrations | 001145 writes an SLA credit policy version, 001150 archives the prototype posts and publishes three starter posts; `down()` keeps both → rollback B restores the database from the dump of section A. 000950 stops before any change on a genuine double commission (passed on this database during R2b-5) |

## Step log

| Step | Owner "ano" | Time (UTC) | Result | Doctor / gate | Notes |
| --- | --- | --- | --- | --- | --- |
| A backup | | | not run | | |
| B `expected-nonok` (+6 WARN) | | | not run | | |
| C1 `staging.sh` + `check` | | | not run | | |
| C2 deployer | | | not run | | |
| C3 gated deploy | | | not run | | |
| C4 verification (R2b-6) | | | not run | | |
| `--legal` (R14, G-2) | | | not run | | |
| D0–D8 Penpot node | | | not run | | |
| E1–E6 registration, qualification | | | not run | | |
| F0–F8 Penpot E2E (F2 paper parent: own "ano"; F8 always) | | | not run | | |
| G1 Penpot clean-up (decision 10) | | | not run | | |
| R3–R24 | | | not run | | |
| R25 PBS (read only) | | | not run | | |
| G2 failed DB copies (decision 10) | | | not run | | |

## Known limits (skipped with a reason, never green)

- R17 (Proxmox storage) cannot be green: staging has no Proxmox instance → R18 (custom ISO revision) and R19 (upload) are refused
  by `CustomIsoReadiness` (decision 3).
- R25 stays read only: no PBS on staging (decision 4).

## Outputs per step

(Paste only non-secret output: exit codes, gate verdict lines, doctor rows that are not OK — `status area|check` only —, `ls -l`
lines, SHAs.)
