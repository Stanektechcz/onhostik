---
name: ai-review
description: Run the independent review chain for an ONHOST task - onhost-reviewer, onhost-qa, onhost-security when required, and the domain's mandatory reviewers - in parallel on the task's diff, then record findings and the verdict in the task file. Use when a task is SELF_VERIFIED, or for "review", "QA" or "security review" of a task.
argument-hint: "TASK-NNNN [all|code|qa|security]"
---

# Review $ARGUMENTS

1. Find the task's worktree and branch (`.\brain.ps1 task board`), read its task file and handoff. The diff under review
   is `git -C <worktree> diff development...HEAD`. If the worktree has uncommitted changes, ask the implementer to
   commit first — reviews target commits.
2. Decide the reviewers (default `all`):
   - always **onhost-reviewer** (code) and **onhost-qa** (tries to break it; may add tests on the task branch);
   - **onhost-security** when `.ai/SECURITY_RULES.md` §1 applies (auth, permissions, money, provisioning actions,
     customer parameters, files, webhooks, egress, secrets, infra);
   - mandatory domain reviewers from `.ai/DOMAIN_MAP.md` (onhost-architect for `platform/` or cross-module contracts,
     onhost-database for migrations, onhost-billing for money moved by other domains, onhost-integration for adapter
     contract use, onhost-performance for hot paths).
3. Dispatch them **in one message** (parallel). Give each: worktree path, branch, task file path, the diff command, and
   "read-only except QA tests on the task branch".
4. Collect results. Write every finding into the task file's *Findings* as `SEVERITY path:line — finding — owner`,
   merge duplicates, drop findings without evidence (say so).
5. Verdict: any BLOCKER/HIGH → status `CHANGES_REQUESTED` and send the findings to the owner; otherwise `QA_REQUIRED`
   done → orchestrator may move on to `/ai-integrate`. Commit the task file update on the task branch.
6. Report to the user: reviewers run, findings by severity, verdict, what was NOT reviewed.
