---
name: ai-task
description: Open a new ONHOST task with its own branch, worktree, task record and lock, after deriving the owner, owned paths, risk and acceptance criteria from the code. Use when the user asks to start a task or when the orchestrator has a READY unit of work.
argument-hint: "<short outcome>"
---

# New task: $ARGUMENTS

1. Find where the change lives: entry points, domain module, tests (`.ai/DOMAIN_MAP.md`). Choose the owner agent from
   the map; if two owners are needed, it is two tasks with a contract (`/ai-orchestrate`).
2. Owned paths: the smallest set of directories/files the task will change, plus any hot file it must edit.
3. Risk: HIGH or CRITICAL for money, auth/permissions, provisioning actions, migrations, infra; MEDIUM for normal
   features; LOW for docs/tests/tooling. Priority P0–P3 (`.ai/DEVELOPMENT_RULES.md` §11).
4. `.\brain.ps1 task board` — resolve overlaps before starting (the script refuses them anyway).
5. Start it:

   ```powershell
   .\brain.ps1 task start -Title "<outcome>" -Owner onhost-<agent> -Paths "<p1>,<p2>" -Type feat|fix|chore|docs|refactor|test|perf|ci -Risk <R> -Priority <P>
   ```

6. In the new worktree, fill the task file (`.ai/tasks/TASK-NNNN.md`): objective, context, out of scope, dependencies,
   contracts, acceptance criteria (testable), required tests, security/migration/rollback notes; set status `READY`;
   commit (`chore(ai): plan TASK-NNNN`).
7. Tell the user the task id, branch, worktree and how to continue: dispatch the owner agent from this session, or open
   a new Claude Code session in the worktree with "Read CLAUDE.md, then work on .ai/tasks/TASK-NNNN.md".
