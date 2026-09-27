---
name: ai-release-check
description: Check whether a revision of ONHOST development is ready for release and write the readiness report to .ai/releases/. Never tags, pushes, deploys or touches production. Use when the user asks about releasing, a release candidate, or go-live readiness.
argument-hint: "[from-sha..to-sha]"
---

# Release check $ARGUMENTS

1. Range: default = last release tag or the last `.ai/releases/*` report's revision → `development` HEAD. There is no
   tag and no report before the first release (onboarding audit C14): the first run needs an explicit range,
   `2426c17..<sha>`. List commits and map each to a task file; flag commits without a task or without review evidence.
2. Create a clean worktree at the release revision (`git worktree add ..\onhost-worktrees\release-<sha> <sha>`, real
   `composer install`, `node_modules` junction, empty `.env`) — never test in a checkout with someone's work.
3. Dispatch **onhost-release** with the worktree path and range. In parallel, if the range touches money, auth,
   provisioning or infra and no security review exists for it, dispatch **onhost-security** on the range diff.
4. Collect: `.\brain.ps1 gate` in the release worktree, CI runs for the SHA (`gh run list`/`gh run view`), migrations,
   env/config, operational steps, rollback limits. "Additive" is proven mechanically, not by reading the diff
   (pre-mortem 2026-09-27) — both searches print nothing, or every hit is explained in the record:

   ```bash
   M=$(git diff --name-only --diff-filter=AM <from>..<sha> -- database/migrations)
   # drops, renames and type changes outside down():
   for f in $M; do awk '/function down/{d=1} /function up/{d=0} !d && /dropColumn|dropIfExists|->drop\(|renameColumn|Schema::rename|->change\(\)|DROP |RENAME / {print FILENAME":"NR": "$0}' "$f"; done
   # a column added to an EXISTING table without nullable()/default() (NOT NULL without a default fails on a filled table):
   for f in $M; do awk '/function down/{d=1} /function up/{d=0} /Schema::table\(/{t=1} /Schema::create\(/{t=0} !d && t && /\$table->[a-zA-Z]+\(/ && !/nullable\(|default\(|drop|index\(|unique\(|foreign\(|primary\(|rename/ {print FILENAME":"NR": "$0}' "$f"; done
   ```

   For staging also: the staging-vs-production value table (every key of `docs/runbooks/staging-launch.md` S3, the
   production value from the owner's question 14, differences marked *not rehearsed*), the expected non-OK doctor rows
   with reasons (O11), and the first later SHA that carries a migration (S8 d).
5. Write `.ai/releases/<yyyy-mm-dd>-<shortsha>.md` in the format of `.ai/releases/README.md`: scope, gates with
   evidence (CI incl. `deploy-scripts`), blockers, migration/rollback notes, the sha256 of the deploy scripts at the SHA,
   monitoring plan, exact human actions, verdict READY / NOT READY. The deploy command is the gated deployer:
   `REF=<sha|tag> EXPECTED_SHA=<sha> DEPLOY_OPERATOR=<name> /usr/local/sbin/onhost-deploy` (docs/runbooks/deploy-aapanel.md,
   staging first: docs/runbooks/staging-launch.md). Production takes only an annotated tag the **owner** creates and
   signs (`vYYYY.MM.DD[-N]`, optional `Accept-Gate:` lines) — never the AI.
6. Remove the release worktree after the report (it is clean), keep the report. Tell the user the verdict; the release
   itself is theirs.
