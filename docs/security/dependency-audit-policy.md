# Dependency audit policy

* `composer audit --locked --no-interaction` runs **once**, in `.github/workflows/security.yml` (job `security`),
  against `composer.lock`. `tests.yml` does not repeat it, so one advisory produces one red check, not two.
  The `security` job must be a required status check on `development` (branch protection, set by the human owner).
* `npm audit --audit-level=high` runs in the same job.
* A failing audit blocks the PR. Fix it by updating the package (`composer update vendor/package --with-dependencies`)
  whenever a patched release exists.

## Ignoring an advisory (exception, never the default)

Only when no fix exists or the vulnerable code path is provably unreachable. Add the advisory ID to
`config.audit.ignore` in `composer.json` with a reason and an expiry date, using the object form:

```json
"config": {
    "audit": {
        "ignore": {
            "GHSA-xxxx-xxxx-xxxx": "unreachable: we never call X; expires 2026-12-31; owner onhost-security; TASK-NNNN"
        }
    }
}
```

Rules:

1. Each entry names the reason, an **expiry date (at most 90 days ahead)**, the owner and the task.
2. Adding or extending an entry needs onhost-security review in the task's review chain.
3. An expired entry is removed or renewed with a fresh justification; the release readiness report (`/ai-release-check`)
   lists all active ignores and treats an expired one as a blocker (manual check, not automated yet).
4. Never use `--no-audit`, `audit.abandoned = ignore` or a blanket ignore to make CI green.
