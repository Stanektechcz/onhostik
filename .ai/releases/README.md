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
## Migrations       `git diff --stat <from>..<sha> -- database/migrations`; the mechanical safety search (SKILL.md step 4);
                    lock and rollback notes; a later SHA that carries a migration for staging-launch.md S8 d
## Env and config   new keys (`.env.example` diff), switches that stay off (ADR-0007); staging vs production value per key
                    (every difference marked *not rehearsed*); the automation switches of S0 and S7
## Deployer         sha256 of infra/aapanel/install.sh, deploy.sh, deploy-gate.php, install-deployer.sh at <sha>;
                    the SHA the deployer is installed from (install-deployer.sh)
## Doctor           the staging expected-nonok list (O11) — every row with its reason, copied byte for byte to the
                    host; production: the FAIL and GATED rows that need an Accept-Gate line
## Operations       exact human steps (docs/runbooks/deploy-aapanel.md, staging-launch.md), window estimate
                    (production nightly backup + verify durations from the automation ledger + build time)
## Rollback         last good release, backup set, limits (backward-compatible migrations, no down migrations)
## Monitoring       what to watch for 30 minutes (sla.burn_rate, doctor liveness rows after 6 minutes)
## Evidence         VERIFIED / ASSUMED / NOT TESTED
```

## Tags

* Name: `vYYYY.MM.DD` (the day of the release), `vYYYY.MM.DD-2`, `-3` … for further releases that day.
* **Annotated and SSH-signed by the owner** (`git tag -s -a v2026.10.01 -m "…" <sha>` with `gpg.format=ssh`), pushed by
  the owner. The production deployer (and its installer, `install-deployer.sh TAG=…`) refuses a lightweight tag, a tag
  whose last signature is not SSH (PGP/X.509 are never consulted), a tag object filed under a name its `tag` header does
  not carry, an unsigned tag, and a tag with anything but blank lines after `-----END SSH SIGNATURE-----` (git and
  OpenSSH leave those bytes unverified; `Accept-Gate:` is read only from the signed message); it refuses every
  production deploy until
  the owner's public key is in the root-owned `/var/lib/onhost-deploy/<site>/allowed_signers`
  (`<email> namespaces="git" ssh-ed25519 AAAA…`).
* Message:

  ```text
  Release-Record: .ai/releases/<yyyy-mm-dd>-<sha7>.md
  Verdict: READY
  Accept-Gate: <area|check> — <reason, 10+ characters>     (optional, one line per doctor row accepted)
  ```

  `Accept-Gate` accepts a GATED row or any other row that is `FAIL` in production (`docs/runbooks/release-and-rollback.md`,
  *The gate* — since the pre-mortem of 2026-09-27 every FAIL row stops a production release); HARD rows are never
  accepted, and outside production the lines are ignored (staging: the SHA-bound `ALLOW_DOCTOR_FAIL` for GATED rows,
  the root-owned `expected-nonok` list for the others). Draft the lines from the production doctor with
  `deploy-gate.php nonok --report <json> --production 1` before signing: a tag signed without them stops at the gate.
* Staging deploys a SHA (`REF=<sha> EXPECTED_SHA=<sha>`); a tag is optional there and is not signature-checked.
