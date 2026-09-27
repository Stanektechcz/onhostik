# ADR-0009 — The permission and delegation program: one enforcement point, a hard tenant boundary, honest recovery

**Status:** accepted (2026-09-27). Phase 0 built in TASK-0033 … TASK-0041 and integrated in one chain (PR #25 + the local
commits on top of `45017b7`), **not signed off** (see "Phase 0 outcome"). Slices 1–5 not started. · **Decided by:** the owner
delegated the whole program to Claude ("celé to promysli"); the orchestrator wrote it with independent critics and a judge who
ruled on 45 objections; the owner's twelve questions (§10) are decided by their stated defaults. · **Recorded by:** the docs
commits on the Phase-0 chain (first on `fix/TASK-0041-wave-one-leftovers-of-the-permission-pro`, the outcome on
`fix/TASK-0039-staff-act-as-staff-and-a-token-only-for` after `650f675`) · **Built by:** TASK-0033, TASK-0034, TASK-0035,
TASK-0036, TASK-0037, TASK-0038, TASK-0040, TASK-0041, TASK-0039

The program itself, with every exploit, fix, task and ruling: [`docs/security/permission-program-2026-09-27.md`](../security/permission-program-2026-09-27.md).
This record keeps the decisions, not the evidence.

## Context

A security review of permissions and delegation in five lenses (tenancy and delegation, staff and support, partners, panels and
APIs, safe execution and undo) plus an enforcement-completeness pass found holes that were exploitable on the code of PR #24:

- an organization admin could invite the owner at a lower role and demote them through the accepted link (TD-1, CRITICAL);
  anybody who knew a user id could make that person a member without an invitation and read their e-mail (TD-2);
- the game panel user was found by the customer-set billing e-mail, so a customer could take over a stranger's panel account
  and reset its password (PA-01, CRITICAL);
- the aaPanel file API runs as root and checked paths only lexically, so a tenant's symlink reached other sites and the node
  (PA-02, CRITICAL); all sites share the `www` user and group (PA-03);
- staff global roles reached customer permissions; a member of staff who was also a customer member bypassed customer
  protections (force purge, lifting holds) on customer routes (SS-1, EXPL-1..3); force purge was only HIGH (SS-5, SE-3);
- an API token ignored its own organization (PA-04); Discord links and action hooks outlived member removal (G1);
- partner payouts had no lock against a double payout and the IBAN could be rewritten without a step-up (P1, P2);
- commands could declare a lower risk than their permission (G4); the four-eyes waiver applied to everybody (SS-13).

The owner's standing rules frame every decision (ADR-0007): historical resources the platform did not create are untouchable,
the client account survives its services, existing customers are never changed en masse without a `default_off` rule or a
dry-run operator command, issued documents are append-only, published plan versions are never edited.

## Principles

1. **One enforcement point.** Every write — invitations, hooks, Discord, staff SSO, grants, undo — goes through the CommandBus,
   authorizes the real actor at a scope read from stored rows (never the payload), records its authorization basis and fails
   closed on anything unmapped.
2. **Authorization knows its mode.** Customer mode counts only bindings of the organization acted on; staff mode (set only by
   `/v1/staff/*`) counts global, family and JIT bindings, and only for staff-audience permissions.
3. **A token or linked credential is a narrowed view** of its person: one organization, never global bindings, never grants.
4. **One GrantPolicy** decides every grant, change and removal: nobody gives more than they hold or changes someone they do not
   cover; the owner changes only by a two-sided transfer.
5. **The tenant boundary is hard.** Attribution, referral, shared e-mail and panel-side identity grant nothing.
6. **Risk only goes up.** Effective risk = max(declared, catalogue). HIGH = fresh step-up; CRITICAL = a second person, or a
   time lock (delay, notice, cancel) where only one approver exists — never a silent bypass.
7. **Honest recoverability.** Every action is class A (platform pre-copy + undo), B (inverse command), C (grace + final
   archive) or D (irreversible outside the platform: preview, confirm, higher risk). Undo never restores revoked access.
8. **Copies live outside the tenant's reach**, hash-verified, pruned at the promised retention.
9. **Panels are not trusted to enforce tenancy.** The platform proves ownership of every panel identity before acting; where a
   panel cannot isolate tenants, code execution and file writes are closed there.
10. **Identifiers are scoped.** Idempotency keys are namespaced organization/target/action/actor; audit rows carry the
    session or token identity.
11. **Existing customers are never changed en masse silently** — a `default_off` switch or a `--dry-run`/`--apply` command plus
    notice.
12. **Small serial PRs, evidence before claims:** a failing-first test per exploit, an independent read-only review closing
    every phase.

## The model

- **Principals and views:** user, service account, API token (person + organization + scopes + expiry), linked credential
  (Discord link, hook: one organization), partner organization. A principal view (principal, mode, token organization,
  delegation, support grant) is built per request and persisted on operations and approvals.
- **Scopes:** global > family (staff only) > organization > project > resource > sub-resource (proven by ownership, never a
  new binding).
- **Permissions and roles:** `PermissionCatalog` is the one list (key, audience, risk, family, token scope, cs/en sentence);
  `RoleResolver` is the one role→permission mapper; unknown keys hold nothing for a grant. Staff roles end with staff-audience
  keys only (`staff.*`). Customers get granularity through a closed family × level matrix and the new presets
  `svc_operate` / `svc_data_delete`, not free custom roles.
- **GrantPolicy invariants I1–I12** (program §3): cover what you give (I1), no self-grant (I2), cover the target (I3), the owner
  binding survives except by transfer (I4), expiry within the grantor's (I5), grantor loss cascades (I6), HIGH for
  membership changes (I7), only current members are changed (I8), accepting never lowers (I9), an access snapshot before
  removal (I10), project-role allow-list (I11), reshare depth ≤ 2 (I12).
- **Staff and support:** every staff read or write of customer data needs a basis — ticket-bound consent from a
  portal-authenticated, customer-originated ticket, a standalone support grant, or an incident basis. Staff SSO is a bus
  command needing a console-level basis of the service's family. JIT and break-glass replace standing `platform_owner`.
- **Panels:** a provenance ledger for platform-made panel identities, a per-service revocation epoch; Pterodactyl identity by
  exact `external_id` = organization id; multi-tenant aaPanel nodes closed to customer file writes and shell cron.
- **Partners:** attribution grants no access; client contacts and dunning masked; payouts row-locked, paid from `approved` by a
  third person, to a confirmed payout account.

## Decisions

The decisions D1–D21 of the program (§4), as they stand after Phase 0:

| # | Decision | State |
| --- | --- | --- |
| D1 | One `GrantPolicy` decides every grant, change and removal (I1–I12). | Skeleton + IF-1..IF-3 built (TASK-0036); every grant path compares the person acted for (TASK-0041 (f)). Full invariants: S1-01. |
| D2 | Mode-aware Authorizer; staff roles lose customer-audience keys and gain `staff.*`; shadow log before enforcement. | Built (TASK-0039): `StaffActor` and staff mode on `/v1/staff/*` only, asking staff keys (P0-16 re-check `c4c43e2`); staff reach on customer keys is shadow-logged and still allowed until `ONHOST_STAFF_REACH_ENFORCED=true` (default off); P0-15 not started. |
| D3 | Tokens, Discord links and hooks are narrowed views bound to one organization. | Discord/hooks re-check the current membership (TASK-0035); a token sees only its organization (TASK-0039). Open: tokens bound to no organization until `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true`, and `GET /v1/me` lists the person's other organizations to a token (re-check MEDIUM). |
| D4 | Customer granularity = closed family × level matrix + `svc_operate`; free custom roles deferred. | Slice 1 (S1-03), not started. |
| D5 | One access wizard + `ShareAccessCommand` with a per-person access page. | Slice 1 (S1-04); the P0 UI hotfix (fail closed, role select, confirm) is done (TASK-0035). |
| D6 | `RoleResolver` is the single mapper; transactional seeder; expand/contract catalogue changes. | Done (TASK-0037). |
| D7 | Staff need an access basis (portal-authenticated ticket consent or incident basis). | Staff SSO part built (TASK-0039, `PanelLoginCommand`: a ticket the customer opened, consent or a second person, customer notice); the rest Slice 2 (S2-02). |
| D8 | CRITICAL = second person; the waiver belongs to the sole approver only, whose own action waits a cancellable time lock. | Done (TASK-0037, 24 h); a further approver made from the command line waits the time lock (`7789c94`). Open (re-check LOW): that lock skips roles that decide no approvals and a sole approver who is suspended. |
| D9 | Recovery classes A–D, platform-held copies, `UndoEligibility`, revocation epoch, provenance ledger. | Slice 2 / S1-06, not started; `archive.restore` pre-copy fails closed (TASK-0035). |
| D10 | `pre_*` retention stays at the promised 60 days (floor 30); prune fixed. | S2-06, not started. |
| D11 | Multi-tenant aaPanel nodes closed via `operator:aapanel:tenancy --dry-run/--apply`; new shared sales elsewhere. | Tool built (TASK-0034); open nodes never become shared by placement or move (TASK-0041 (a)); `--apply` waits for the owner (O1). |
| D12 | Pterodactyl identity by exact `external_id`, synthetic e-mails for new users, re-verify before credentials. | Done (TASK-0033); same-panel game moves need a proven owner (TASK-0041 (d)). |
| D13 | Partners: masking now; reseller MVP in Slice 3; payout safety grandfathers IBANs already paid to. | Masking and payout safety done (TASK-0040), with a recorded cut-over for grandfathering; reseller MVP Slice 3. |
| D14 | Tokens: `services:code` + automation grants for HIGH; CRITICAL never via token; default expiry. | S1-05, not started (HIGH via token stays refused, TASK-0030). |
| D15 | Phase 0 in three serial lanes, then customer-visible slices; one small PR per task. | Phase 0 ran as two waves of parallel worktrees plus TASK-0039, rebased into one chain on PR #25 (`45017b7` → wave 2 → TASK-0039). |
| D16 | A read-only forensic look-back and breach register precede the fixes going live; the owner decides on Art. 33. | Tool done (TASK-0038); the production baseline run is a go-live blocker. |
| D17 | PR #24 lands first; new work branches afterwards. | Done: PR #24 merged 2026-09-26 (`3b2a9fb`). |
| D18 | Staff ticket/backup/billing read keys before customer keys leave staff roles. | Done (TASK-0037). |
| D19 | Ticket visibility scoped per queue/category (staff) and per service/project (customers). | S1-09, S2-09, not started. |
| D20 | Revocation also kills live sessions (console relay, console tickets, panel SSO). | S1-08, not started. |
| D21 | `OwnerRecoveryCommand` for a genuinely lost owner. | S1-02, not started. |

## The judge's rulings

Independent critics raised 45 objections (program §8). The judge accepted 37 and modified 8; none was rejected. The rulings
that changed the design:

- **Kill switch instead of a race check on aaPanel** (#8, #22): isolation on a root-API panel with a shared `www` user cannot
  be engineered around a TOCTOU race; shared nodes are closed and new shared sales go elsewhere (D11).
- **Waiver only for the sole approver, with a time lock** (#3, #29): no global four-eyes bypass (D8).
- **Consent only from a customer-originated, portal-authenticated ticket** (#4, #42, #43): staff cannot satisfy it
  themselves; `disclosure_restricted` is its own flag; class-B incident writes stay HIGH so abuse takedowns stay fast (D7).
- **Undo never resurrects revoked access** (#5, #6, #16): provenance ledger, revocation epoch, `UndoEligibility`; your own
  operation is undone with its original permission + step-up (D9).
- **Copies outside the docroot, 60-day promise kept** (#7, #17, #36, #37): no in-site trash; prune actually fixed (D9, D10).
- **Pterodactyl keeps the raw organization id** (#9, #23, #34): exact lookup; existing hijacks are refused and triaged, not
  grandfathered (D12).
- **Closed matrix, not free custom roles** (#14, #38, #39): no second role store (D4).
- **Resellers right after the wizard, annual reconfirmation** (#18); **grantor TOTP behind a default-off switch** (#19);
  **look-back before go-live** (#35); **audit-in-transaction only after measuring** (#44).

## Phase 0 outcome

| Task | Program keys | Outcome |
| --- | --- | --- |
| TASK-0033 | P0-02 / IF-6 (PA-01) | A game panel user is the organization's by exact `external_id` only; credentials and collaborators re-verified against the live owner; `onhost:game:panel-identity --dry-run` triage. |
| TASK-0034 | P0-03 / IF-7 (PA-02, PA-03) | Archive preflight and proven downloads on every aaPanel node; root-only temp dirs; `operator:aapanel:tenancy` closes shared nodes (dry run default). |
| TASK-0035 | P0-04, P0-05, P0-06 / IF-15, IF-11, IF-17 | Removed or demoted members lose Discord links and hooks; `archive.restore` needs source `backup.read` + project/org `backup.restore` over a fail-closed pre-copy; the team page fails closed. |
| TASK-0036 | P0-07, P0-10 / IF-1..IF-3, IF-12 | `GrantPolicy`: the owner cannot be demoted, strangers cannot be added, members are changed only by somebody covering them, project roles take a step-up and an allow-list; service-action keys scoped per org/service/action/actor with a request hash. |
| TASK-0037 | P0-11, P0-12, P0-18 / IF-13, IF-10, IF-18 | `effectiveRisk`, empty `LOWERED_RISK`; waiver for the sole approver with a 24 h time lock; staff read keys; `RoleResolver` and a transactional seeder; `onhost:iam:risk-floor-report`. |
| TASK-0038 | P0-01 / IF-0 | `onhost:forensics:lookback` (read-only) and `docs/runbooks/breach-register.md`. |
| TASK-0039 | P0-08, P0-09, P0-14 / IF-4, IF-5, IF-8, IF-9, IF-16 | Integrated last on the chain (the nine P0-16 pins retired in `daca3b5`). Staff act as staff only in staff mode on `/v1/staff/*`, with staff keys (`StaffActor`, `c4c43e2`); forced purge CRITICAL `staff.service.delete`; staff reach on customer keys shadow-logged (`authz.staff_reach`, switch `ONHOST_STAFF_REACH_ENFORCED`); a token sees only its organization (`token_organization_mismatch`; unbound tokens behind `ONHOST_TOKEN_ORGANIZATION_REQUIRED`); staff panel sign-on is `PanelLoginCommand`. |
| TASK-0040 | P0-13 / IF-14 | Payouts row-locked, amount = allocated, IBAN only from a confirmed payout account (owner, step-up, 7-day cooling-off), CRITICAL payment by a third person; anomaly freeze by digest; masked partner view. |
| TASK-0041 | follow-ups of P0-02/03/07/10 + the P0-16 record | Open aaPanel nodes never become shared by placement or move; legacy project roles listed; domain keys scoped + 409; same-panel game moves need OWNED; hardlink exception narrowed to the site's own agent; sharing compares the person acted for; the P0-16 holes pinned. |

**P0-16, first round (red team on `c59e08a`):** CHANGES_REQUESTED in all four lenses (cross-tenant, staff, token and
automation, money and undo). The six HIGH findings all came from TASK-0039 missing; they were pinned, and TASK-0039's
integration turned every pin red and replaced it with a proof. The six MEDIUMs were fixed on the chain (`888a61e`, `53ea45c`,
`7789c94`, `5ec856a`, and the credit gate by TASK-0039).

**P0-16, re-check (on `02b5bc5`, lenses cross-tenant-and-token and staff-and-money):** CHANGES_REQUESTED in both. The HIGH —
EXPL-1..3 and SS-1 had moved to `/v1/staff/services/{id}/actions` and `…/reinstate`, which asked the customer keys, so any staff
account that was a member or share guest of a service lifted ONhost's holds there — and the MEDIUM that those routes would fill
the shadow log were fixed in `c4c43e2` (failing-first 5/5; gate PASS, Pest 1 825/1 825). Open: `GET /v1/me` lists a token's
person's other organizations with their full records (MEDIUM), and four LOW (program status header).

**Phase 0 is not signed off.** Every HIGH finding has its fix in code, but (1) IF-4 staff reach (with staff `archive.restore`) and
PA-04 for tokens bound to no organization are logged and still allowed until the operator turns on `ONHOST_STAFF_REACH_ENFORCED`
and `ONHOST_TOKEN_ORGANIZATION_REQUIRED`; (2) the `GET /v1/me` MEDIUM has no fix; (3) `c4c43e2` has had no independent review of
its own; (4) the forensic baseline and the owner's Art. 33 decision are open. Phase 0 is signed off when the breach register's
open list is empty, `/v1/me` is fixed with a failing-first test, and a read-only review of the fixes passes.

## Owner questions: the defaults taken by delegation

The owner asked for the program to be thought through as a whole ("celé to promysli") and gave no other answers, so each
default of §10 is the decision:

| # | Decision (the default) | State |
| --- | --- | --- |
| O1 | `operator:aapanel:tenancy --apply` may run after the dry run and a customer notice; SFTP and URL cron stay. | Tool built (TASK-0034), not applied. **Not covered by O1 and open:** whether the terminal, Node.js projects and existing cron are also closed on a closed node (they run as the shared `www`); decide before the first `--apply`. |
| O2 | Synthetic e-mails for new Pterodactyl users only; existing users and self-service reset untouched. | Applied (TASK-0033). |
| O3 | Findings go to the breach register and Art. 33/34 drafts; nothing is sent without the owner's decision. | Applied (TASK-0038 runbook). |
| O4 | Solo-operator time lock 24 h, cancellable, with notice. | Applied (TASK-0037, `ONHOST_FOUR_EYES_TIME_LOCK_HOURS=24`). **Deviation:** only staff are told; the customer notice waits for `disclosure_restricted` (D7) so a legal hold is not tipped off. |
| O5 | The client pays; the partner earns commission only. | Slice 3. |
| O6 | Closed matrix only; custom roles only if enterprise customers ask. | Slice 1 (S1-03). |
| O7 | Sharing stays at service/project level; domains stay organization-wide. | Slice 1 (S1-10 records the scope note). |
| O8 | Support consent preselected for view only, ends 72 h after close; org default "ask per ticket". | Slice 2 (S2-02). |
| O9 | Mask partner contacts now and notify partners the same day. | Applied (TASK-0040; `onhost:partners:masking-notice --send` is an operator step). |
| O10 | Keep the 60-day safety-copy retention (floor 30). | Slice 2 (S2-06). |
| O11 | Grantor TOTP off by default; dry run and notice first, flip 30 days after S4-04. | Slice 4. |
| O12 | PR #24 may be rebased locally and the task branch force-pushed if merge commits are disabled. | Moot: PR #24 was merged on 2026-09-26. |

## Consequences

- **Customers** see refusals only of illegitimate requests at deploy (demotions by invitation, members added without an
  invitation, grants beyond the granter, reused idempotency keys, game panel accounts that are not theirs, archives that leave
  the site). Visible changes: an org admin can no longer change a billing admin; project roles and DNSSEC/registrant changes
  take a step-up; a removed member's Discord link and hooks stop; partners lose client contacts and dunning; partner
  members without `partner.portal.read` lose the portal; a payout needs a confirmed account. Everything that closes a
  legitimate feature (shared aaPanel nodes, legacy project roles, anomalous payouts, orphan links) is an operator command with
  a dry run.
- **Operators** run the Phase-0 steps in `docs/runbooks/go-live-checklist.md` §7, the forensic baseline first. A solo operator's
  CRITICAL actions and price changes wait 24 h. 58 staff operations take a step-up. Paying a partner needs a third person.
- **Developers** write every new grant path through `GrantPolicy`, read roles only through `RoleResolver` (architecture
  allow-list), never lower a risk below the catalogue (`LOWERED_RISK` is empty and pinned), and keep staff endpoints on staff
  keys. A new permission needs its token decision (`TokenScopes`) and its risk.
- **Staff tools** reach staff powers only through `/v1/staff/*` with a staff key: `/v1/staff/services/{id}/actions` needs
  `staff.service.manage` (auditor, IAM admin and sales lose it; support L2 and the service admins have it), `…/reinstate`
  `billing.dunning.manage`. Keys with no staff counterpart (`service.console`, `backup.restore`, `backup.delete`,
  `game.manage`, `compute.vm.delete`, `service.panel_account.manage`) stay customer keys in staff mode, reached through staff
  reach or membership.
- **API tokens** act for their own organization only; operators list unbound tokens (`operator:tokens:unbound --dry-run`),
  tell the owners, then switch `ONHOST_TOKEN_ORGANIZATION_REQUIRED` on.

## Not decided here (open)

- The terminal / Node.js / existing cron question on closed aaPanel nodes (above, O1).
- Customer four-eyes (S4-03); until then a customer CRITICAL key floors at HIGH for members.
- The red-team items that are neither fixed nor pinned (program status header; `.ai/PROJECT_STATE.md` → Known issues).
- Whether any staff account is also a customer member in production, and whether `ONHOST_FOUR_EYES` is set there (program §9
  unknowns); both decide how exploitable the P0-08 holes were before the Phase-0 deploy (the look-back reads it).
- When the operator turns on `ONHOST_STAFF_REACH_ENFORCED` (after P0-15) and `ONHOST_TOKEN_ORGANIZATION_REQUIRED` (after the
  token notice); until then those two holes are only logged.
