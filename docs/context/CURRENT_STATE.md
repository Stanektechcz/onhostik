# Current state

**Updated:** 2026-09-16
**Branch:** `development`

## Now

- Finish the production version of the control plane; every change is tried on `staging.onhost.cz` against the real
  panels (ISPConfig, aaPanel, Pterodactyl, Proxmox, WEDOS) before it is called done.
- The whole cancellation path is in place (audit §5aa, §5ab): identity check → archive → deactivation → restore
  window → removal → archive retention, with the numbers set in *Nastavení systému → Životní cyklus služeb*.
- What remains is operational: the staging run of the new lifecycle, the payment gateway and bank tokens, staff MFA,
  the virus scanner, the console relay and the production deployment itself (`docs/runbooks/go-live-checklist.md`).

## Verified baseline

- Remote: `github.com/Stanektechcz/onhostik`, default branch `development`.
- Pest: 387 tests, 9 740 assertions green; Pint clean; Larastan level 5 clean (the baseline holds the older typing
  debt, new code passes without it).
- CI: `tests.yml` (Pint, Pest, Larastan, Composer audit, the same suite on PostgreSQL 16), `security.yml` (gitleaks
  over the history, Composer and npm advisories, frontend build), `e2e.yml`, `edge-role.yml`; Dependabot weekly.
- Live orders were placed and verified through the panels for web hosting (ISPConfig and aaPanel), WordPress, e-shop,
  a custom web, mail and a Minecraft Vanilla 1.21.8 game server.
- `onhost:doctor` is the readiness gate: environment, storage, automation, secrets, TLS, providers, payments,
  documents, identity, mail, observability and the deletion lifecycle.

## Known risk

- A local `.env` holds provider credentials. It never enters prompts, logs, commits or generated context; the
  pre-commit hook (`git config core.hooksPath .githooks`) and the security workflow enforce that.
- One staging web service is still stuck mid-termination from before the archive fallback existed; it is unblocked
  with `onhost:services:purge --service=… --force --reason=…`.
- `s4s.electree.cz` was deleted on the live ISPConfig node by the resource-type confusion fixed in §5z; the site is
  recreated from the panel's data log with `onhost:ispconfig:restore-site` and its node backup.
- The production-readiness audit still lists open P0/P1 items: `docs/runbooks/production-readiness-audit.md`.
- Playwright: five panel navigation/session scenarios fail and keep the E2E gate advisory rather than blocking.

## Next decision

Run the lifecycle verification on staging (archive on each panel with `onhost:services:archive --create`, then the
purge), restore `s4s.electree.cz`, and only then move the go-live checklist to the production host.
