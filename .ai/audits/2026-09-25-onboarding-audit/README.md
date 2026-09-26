# Onboarding audit — 2026-09-25

A second developer joined the project to harden security and add features. Before changing anything, the whole
repository was judged on the surface: development state, production readiness, security posture, and whether the
AI-facing documentation still describes reality. This folder is that judgement.

- **Audited revision:** `origin/fix/TASK-0027-stack-coherence-and-the-docs-that-descri` @ `36bf495` (the stack tip),
  with the feature and readiness sweeps run against `development` @ `2426c17` and marked where they differ.
- **Working branch:** `chore/TASK-0028-portable-toolchain-and-ai-docs`
- **Machine:** macOS 25.5, PHP 8.4.23 (Herd), Composer 2.10.2, Node 25.2.1, npm 11.6.2, Docker 29.6.1.
  **No PowerShell, no `gh`, no local PostgreSQL.**

## The pages

| Page | What it answers |
| --- | --- |
| [`development-state.md`](development-state.md) | What is built, what is half-built, what is dead code |
| [`production-readiness.md`](production-readiness.md) | Can this go live, and what stops it |
| [`security-posture.md`](security-posture.md) | Where the security model holds and where it leaks |
| [`ai-docs-and-tooling.md`](ai-docs-and-tooling.md) | Which prompt/doc files are stale or wrong, and the portable `./brain` port |
| [`needs-verification.md`](needs-verification.md) | **Every claim that is not proven yet, and the exact command or test that would prove it** |

## Evidence discipline

This folder follows `.ai/DEVELOPMENT_RULES.md` §9. Every claim is tagged:

- **VERIFIED** — a command was run in this session and its output shows the claim.
- **ASSUMED** — read from code or docs and reasoned about; no run.
- **NOT TESTED** — no evidence either way.

Most code-level findings in `development-state.md`, `production-readiness.md` and `security-posture.md` come from
three read-only sub-agents that could not execute anything (`vendor/` was absent while they ran). They are static
code traces: precise about *what the code says*, unproven about *what the system does*. They are recorded here as
leads, not as verdicts. `needs-verification.md` exists because of that distinction — read it before acting on any
single finding.

What was genuinely executed in this session (VERIFIED): the git topology, the toolchain inventory, `composer install`,
`npm install`, the full Pest suite, and the portable gate.

## Headline

1. **`development` is not the project's current state.** A stack of TASK-0017 … TASK-0027 sits unmerged, 106+ commits
   ahead and 0 behind, gate PASS, one pull request pending the owner's go-ahead. It carries roughly a third of the
   product's present behaviour and nearly all documentation freshness. Judge nothing from `development` alone.
   The stack tip moved twice on 2026-09-25 *after* its own "final gate PASS" commit — treat it as live work and
   coordinate before rebasing. **VERIFIED.**
2. **Development state: late beta.** A built system with a long tail, not a half-finished one. Zero `TODO`/`FIXME`
   markers, 41 commands with 41 matching handlers, 4 dead classes out of 484.
3. **Production readiness: not a release candidate.** The engineering gates are strong; the operational perimeter is
   not. No release has ever been cut, and the deploy script discards the readiness gate it was built to obey.
4. **Security: strong core, gaps at the edges.** No unauthenticated path, no cross-tenant path, no step-up bypass, no
   secret in tracked files. Two HIGH findings, both about a permission falling through a `default` arm.
5. **The AI docs are high quality and on the wrong platform.** Libraries are current; the documentation assumes
   Windows and PowerShell. The commands that the process rules depend on now have a portable twin (`./brain`).

## First three moves

1. `git config core.hooksPath .githooks` — one command. Until it runs, the secret-blocking pre-commit hook is inert
   in this clone and nothing stops a `.env` from being committed. **VERIFIED inert.**
2. Commit TASK-0028 (the portable `./brain`) with a task file from `.ai/tasks/TEMPLATE.md`.
3. Fix `ServiceActionCommand::permissionFor()`'s default arm (`security-posture.md` finding H1) — an S-sized change
   that closes a real four-eyes hole.
