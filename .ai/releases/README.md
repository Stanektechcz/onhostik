# Release records

One file per release candidate, written by `/ai-release-check <from>..<sha>` (`.claude/skills/ai-release-check/SKILL.md`,
agent `onhost-release`): `.ai/releases/<yyyy-mm-dd>-<sha7>.md`. The record is the evidence a human deploys on; the AI
never tags, pushes or deploys. No release has been cut before TASK-0032 (onboarding audit C14), so the first range is
explicit: `2426c17..<sha>`.

## Record format

```markdown
# Release <yyyy-mm-dd> <sha7>

- **Range:** <from>..<sha> (<n> commits)            - **Verdict:** READY | READY-for-staging | NOT READY
- **Target:** staging | production                   - **Tag (production only):** v<yyyy.mm.dd>[-N] (owner, signed)

## Scope            commits → task files; commits without a task or review evidence are flagged
## Gates            `.\brain.ps1 gate` in a clean release worktree; CI runs for the SHA (tests, pest-postgres, e2e,
                    deploy-scripts) with run ids; Larastan, Pint, composer audit
## Migrations       `git diff --stat <from>..<sha> -- database/migrations`; lock and rollback notes
## Env and config   new keys (`.env.example` diff), switches that stay off (ADR-0007)
## Deployer         sha256 of infra/aapanel/install.sh, deploy.sh, deploy-gate.php, install-deployer.sh at <sha>;
                    the SHA the deployer is installed from (install-deployer.sh)
## Doctor           expected non-OK rows with reasons; GATED rows that need an Accept-Gate line (production)
## Operations       exact human steps (docs/runbooks/deploy-aapanel.md, staging-launch.md), window estimate
                    (production nightly backup + verify durations from the automation ledger + build time)
## Rollback         last good release, backup set, limits (backward-compatible migrations, no down migrations)
## Monitoring       what to watch for 30 minutes (sla.burn_rate, doctor liveness rows after 6 minutes)
## Evidence         VERIFIED / ASSUMED / NOT TESTED
```

## Tags

* Name: `vYYYY.MM.DD` (the day of the release), `vYYYY.MM.DD-2`, `-3` … for further releases that day.
* **Annotated and SSH-signed by the owner** (`git tag -s -a v2026.10.01 -m "…" <sha>` with `gpg.format=ssh`), pushed by
  the owner. The production deployer refuses a lightweight or unsigned tag, and refuses every production deploy until
  the owner's public key is in the root-owned `/var/lib/onhost-deploy/<site>/allowed_signers`
  (`<email> namespaces="git" ssh-ed25519 AAAA…`).
* Message:

  ```text
  Release-Record: .ai/releases/<yyyy-mm-dd>-<sha7>.md
  Verdict: READY
  Accept-Gate: <area|check> — <reason, 10+ characters>     (optional, one line per GATED doctor row accepted)
  ```

  `Accept-Gate` accepts only the GATED rows listed in `docs/runbooks/release-and-rollback.md` (*The gate*); HARD rows
  are never accepted, and outside production the lines are ignored (staging uses the SHA-bound `ALLOW_DOCTOR_FAIL`).
* Staging deploys a SHA (`REF=<sha> EXPECTED_SHA=<sha>`); a tag is optional there and is not signature-checked.
