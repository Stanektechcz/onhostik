# Current state

**Updated:** 2026-09-19
**Branch:** `development`

## Now

- Finish the production version of the control plane; every change is tried on `staging.onhost.cz` against the real
  panels (ISPConfig, aaPanel, Pterodactyl, Proxmox, WEDOS) before it is called done.
- The whole cancellation path is in place (audit §5aa, §5ab): identity check → archive → deactivation → revocation
  of delegated access → restore window → removal → archive retention, with the numbers set in *Nastavení systému →
  Životní cyklus služeb*.
- The Brain's requirement cards drive the hardening now: a changed panel address locks the instance until a probe
  confirms it (H311), a maintenance lock never lifts itself (H322), a panel answer larger than the ceiling is refused
  before it can exhaust a worker (H318). A long run never binds a service to a resource the panel did not identify
  (H319), keeps a timed-out provider task on record and refuses a blind retry (H327), and a customer-started run asks
  for its permission again before each step, customer and staff runs alike, at the scope the bus checked (H315).
  What the panel shows about a service carries its age: a reading the reconciler has not refreshed is named stale
  (H325), and the panel API is reported apart from the service: a panel under maintenance refuses a customer's change
  with the reason and the planned end (`control_plane_maintenance`, 503 with `Retry-After`) while the service keeps its
  own state, and staff and system runs are not locked out (H324). Servicing a customer and moving their money are
  two permissions: the refunded share and an assisted order paid from the customer's credit or on invoice need the
  finance permission and a step-up, and no servicing role holds a billing, member or ownership right (H348). Every
  panel quota keeps a slice for health reads, so our own flood cannot make a working panel look down (H323). A new
  panel access is tried against the panel before it replaces the working one, and one instance can never be pointed at
  the stored access of another (H314). SSH keys on shell accounts are recorded by fingerprint with the member they
  belong to; a removed member loses them on every site, and a revocation a panel has not confirmed stays visibly open
  and is repeated (H185). A member removed from an organization loses their collaborator accounts on its game servers at once, and a
  weekly review reports panel accounts of people who are not members (H333, H332). Assessments live in the
  vault (`Hosting/ASSESSMENT-2026-09-17`, `Hosting/ASSESSMENT-2026-09-19`).
- What remains is operational: the staging run of the new lifecycle, the payment gateway and bank tokens, staff MFA,
  the virus scanner, the console relay and the production deployment itself (`docs/runbooks/go-live-checklist.md`).

## Verified baseline

- Remote: `github.com/Stanektechcz/onhostik`, default branch `development`.
- Pest: 416 tests, 10 211 assertions green; Pint clean; Larastan level 5 clean (the baseline holds the older typing
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
