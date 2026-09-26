# ADR-0008 — The three HIGH findings of the onboarding audit: service actions, API tokens, VIES

**Status:** accepted (2026-09-25, built and integrated on the stack branch 2026-09-26) · **Decided by:** the owner
delegated the decisions to Claude on 2026-09-25 ("Ano, začni opravovat ty tři HIGH nálezy"); the orchestrator set them in
the work-package briefs, critics adjusted them, the review rounds changed them where recorded below ·
**Recorded by:** TASK-0027 (docs integration) · **Built by:** TASK-0029, TASK-0030, TASK-0031

## Context

The onboarding audit of 2026-09-25, verified against the stack tip
(`.ai/audits/2026-09-25-onboarding-audit/response-verified-2026-09-25.md`), left three HIGH findings that had to be fixed
before go-live:

1. **Service actions skipped their own permission and risk** (C13, C13-H1, C13-H1b, C13-H1c, C13-FM): 121 of 132 service
   actions fell through `ServiceActionCommand::permissionFor()` to `service.manage` at NORMAL risk. Whoever could restart a
   site could delete its backups and snapshots without a step-up, a `svc_manage` guest ("without a shell") reached root and
   the console, and `archive.restore` overwrote a site without a step-up.
2. **A read-only API token opened a console** (C13-H2c): `ApiContext::assertTokenScope` derived the scope from the
   permission's prefix, so every `service.*` permission that was not managing or deleting fell to `services:read`. Staff
   writes outside the bus (dunning run, capacity forecast run, staff panel login) asked no step-up (audit §4).
3. **Reverse-charge VAT was unreachable** (C1a–C1d): `ViesClient` had no caller, so `vat_status` never became `valid`;
   every EU business customer outside CZ paid destination VAT without a review flag while the public content promised VIES
   checks and reverse charge. Partner self-billing was always 0 %.

The owner's standing rules (ADR-0007) frame every decision: historical resources are untouchable, the client account
survives its services, existing services and customers never change en masse without a `default_off` rule or a
dry-run operator command, issued documents are append-only, published plan versions are never edited.

The orchestrator's briefs with the full texts of D29.1–D29.10 and D31.1–D31.10 are not in the repository. The tables below
record the decisions **as implemented** (task files, handoffs and code); the column *Brief* gives the brief's number where the
task records cite it. Evidence: `.ai/tasks/TASK-0029.md`, `TASK-0030.md`, `TASK-0031.md` and `.ai/handoffs/` of the same
numbers.

## Decisions — service actions (TASK-0029)

| # | Decision as implemented | Brief |
| --- | --- | --- |
| S1 | One exhaustive map, no default: `ServiceActionCommand::PERMISSIONS` names each of the 132 `ServiceActionWorkflow::ACTIONS` once; anything else is refused with 422 `service_action_unknown` before anything runs, the system actor included. A test keeps the map and the workflow equal in both directions. | — |
| S2 | Deleting a copy is its own permission at HIGH with a fresh step-up: `backup.delete` → `backup.delete`, `gbackup.delete` → `game.manage`, `snapshot.delete` → `compute.vm.delete`. No customer four-eyes: the catalogue's CRITICAL rating of `backup.delete` stays documentation (the authorizer forces CRITICAL only for staff-audience permissions). | D29.2 |
| S3 | The console line: everything that hands over a shell or root asks `service.console` — `command.run`, `command.send`, `shell.*`, `access.reset`, `rescue.start`, `subuser.create`, and `schedule.create` unless every task is a power or backup task (payload-aware, fail closed). Ending access (`rescue.stop`, `subuser.delete`) stays managing; FTP and database logins stay `service.manage`. | D29.3 |
| S4 | Every `DestructivePreview` action is HIGH with a fresh step-up (`archive.restore`, `database.delete`, `staging.push`, `staging.delete` among them), plus `panel.password`, `access.reset`, `rescue.start`; `resize` and `mailbox.backup_retention` are HIGH. No service action is CRITICAL. | — (review round 1 reads the step-up list with D29.3/D29.5) |
| S5 | A backup schedule stays managing, but thinning it is a deletion (review round 2, fix round 1): keeping fewer days or generations, or a frequency after which the kept history reaches less far back, asks the bus's own decision on `backup.delete` with a fresh step-up (`WebToolsCommandHandler::assertMayThin`, reach from `BackupScheduler::historyReach()` = the prune's own keeper decision). Unlocking a game backup asks `game.manage`. | D29.4 (schedules stay managing) |
| S6 | Protected, final and legal-hold copies are not deleted or unlocked by a service action: 409 `backup_protected` at request, asked again at run time. | — |
| S7 | The secondary gates ask the same map: spec apply authorizes every step as its own `ServiceActionCommand` and reports a refused step in `skipped`; action hooks and Discord buttons never run a step-up action and refuse an unmapped one; `staging.push` left hooks, Discord, the assistant proposal and the chat intent. Each run carries the step's own permission for the H315 re-check. A Discord button keeps its idempotency key (review round 2). | D29.7 |
| S8 | Delegated access goes with the console: `RevokeDelegatedAccess` keeps SSH keys and game sub-users only for somebody who still holds `service.console`; a share given again without the console publishes `service.access.reduced` and takes them down the revocation's path (fix round 1). | — |
| S9 | `mailbox.backup_retention` is the operator's (`backup.policy.manage`); four eyes only when it prunes. | — |

## Decisions — API tokens and staff step-up (TASK-0030)

| # | Decision as implemented | Brief |
| --- | --- | --- |
| T1 | One explicit permission → scope map, deny by default (`domains/Identity/Authorization/TokenScopes.php`): one row per `PermissionCatalog` key; a permission not in the map, `null`, or a command without a permission is refused to a token. A new permission gets its token decision in the same commit (test fails otherwise). | — |
| T2 | New scope `services:console` for consoles, the terminal, commands and SSH keys; no preset of the panel's key form carries it, the customer ticks it separately with a warning. Existing keys ("operate services", "all scopes") lose the console on purpose. | — |
| T3 | A token never holds a step-up: `StepUpService::activeGrant` returns no grant for a `token:` session, and `ApiContext::sessionId()` takes the token before a started session (review round 1), so every HIGH action through a token answers `step_up_required`. | — |
| T4 | A token is asked for what the action does on `POST /v1/services/{id}/actions` (`permissionFor` → `TokenScopes`): a console-only token runs its commands and cannot restart (review round 1). | D-8 |
| T5 | A console token is issued and judged on the service (resource scope, the stored service's project, never the payload's), so a `svc_console` guest gets the console the role promises. | — |
| T6 | Archives are not available to tokens: the final-archive download is `backup.download`, `null` in the map (review round 1; it had been `backup.restore` = `services:power`). | — |
| T7 | Staff writes outside the bus ask the step-up themselves: `ApiContext::authorizeAction()` for `POST /v1/staff/dunning/run`, `POST /v1/staff/capacity/forecast/run`, `GET /v1/staff/services/{id}/panel-login`; a CRITICAL permission there is a programming error. Every `->authorize()` with a HIGH/CRITICAL permission in `app/Http/Controllers` is classified by `StaffTriggerStepUpTest`. | D-10 |
| T8 | Commands without a permission stay off token-reachable route families (checked by grep). | D-4 |

## Decisions — VAT numbers, VIES and reverse charge (TASK-0031)

| # | Decision as implemented | Brief |
| --- | --- | --- |
| V1 | VIES only through the provider contract `providers/Contracts/VatNumberValidator` (`providers/Vies`, contract test against `Http::fake`); `domains/Tax/ViesClient.php` deleted. **Off by default** (`ONHOST_VIES_ENABLED=false`, `DisabledVatNumberValidator`). | D31.1 |
| V2 | A number given at registration, guest checkout or organization update is checked on the queue after commit by the system actor (`tax.vat_number.record`, NORMAL, system-only); synchronous short checks only at cart quote, guest checkout and staff quote/assisted order. No HTTP inside a bus transaction. | — |
| V3 | The answer is evidence (`vat_validations`, `organizations.vat_checked_*`, consultation number; migration `000880` adds nullable columns and one index, **no data UPDATE** — critic). An unknown answer writes and publishes nothing; a malformed number is invalid only with an EU prefix; a non-EU number is never checked or called invalid (critic); a result for a number that changed meanwhile is discarded. | — |
| V4 | Reverse charge only for a business of another EU state whose number VIES confirmed at most 30 days before the quote (orders) or the issue (everything else), and whose country matches; otherwise destination VAT with `vat_review`. Every tax input is built by `VatStanding::taxCustomer()` (arch test). | — |
| V5 | Legacy rows are read, not migrated: a stored `valid` without a source keeps reverse charge flagged `legacy_unverified`; a stored `payer` keeps self-billing VAT; normalisation happens at read time (critic). | — |
| V6 | Staff override `tax.vat_status.override`: reuses `billing.tax_rule.manage` (no new permission, the `TokenScopes` TASK-0031 block stays empty), CRITICAL (step-up + four eyes), 1–30 days, evidence row, audit, event; bus-only route `POST /v1/staff/customers/{organization}/vat-status` (202). It belongs to its number and country and holds against every automatic check; only the operator's explicit check replaces it (review rounds 1 and 2). | — |
| V7 | Re-check under rule `tax.vies_recheck` (**default_off**, daily 04:20, never for legacy rows). Review round 1: the rule and the switch are **coupled** — switching VIES on without the rule is a go-live error (renewals judge the 30 days at issue and never ask VIES), and the doctor row is red while VIES is on and the rule off. | — |
| V8 | `onhost:vat:verify` is a dry run without HTTP; `--apply` checks; `--csv` lists past VAT invoices to EU business customers for the accountant (formula cells neutralised, review round 1). Issued documents are never changed. | D31.6 (partners reached, review round 2) |
| V9 | The VIES trader name is compared with the organization name: for a customer a mismatch keeps the verdict and raises `vat_review` (`name_mismatch`); `vat_review` and its reason are staff-only on every customer and partner response (review rounds 1 and 3). | — |
| V10 | Partner self-billing from the same standing at `standard_rates` (409 `tax_rate_missing` before any write); an S payout is paid the document's total with input VAT on `liability:vat` (review round 1). One acceptance rule for customers and partners (`VatStanding::verdict()`); a partner is a VAT payer only for a VIES-valid number of its own country registered to its name (review round 3), and a Czech partner is paid VAT only after finance confirmed the supplier once through the CRITICAL override — the evidence row keeps number and name, outlives the override, and any later change of either needs finance again (closing review, decided under the owner's delegation). | D31.6 |
| V11 | Per-organization VIES budget 5/hour (operator exempt) and the adapter's own quota 150/min (`ONHOST_VIES_PER_MINUTE`); an SLA credit note takes the tax of the credited invoice line; a Czech DIČ not in VIES is an info note, never a warning mail (review round 2). | — |
| V12 | The public KB article `faktury-dph` keeps its promise word for word and gains one qualifier (content data only; every `apps/surfaces/*` file byte-identical). | — |
| V13 | **Accountant / legal sign-off before go-live** (see Consequences). | D31.9 |

## Decision at integration (2026-09-26)

- **Spec apply asks the token scope map** (commit `a28c103`, TASK-0029 file, TASK-0030 map): on a `token:` session
  `ServiceSpecService::tokenMay()` asks `TokenScopes::for(<the step's permission>)` and the token's `can()` for every
  step — the same decision `/actions` makes. A power-only token still cannot schedule a console command; a token whose
  owner gave it `services:console` now can (as on `/actions`), and a token session whose token is gone is refused. This
  replaces TASK-0029's "skip every console step on a token session". Reverting that hunk restores the stricter rule; the
  console-token test would go with it.
- `TokenScopes` decides every permission the service action map can return (`service.console` → `services:console`;
  `service.manage`, `service.delete`, `backup.restore`, `backup.delete`, `game.manage`, `compute.vm.delete` →
  `services:power`; `backup.policy.manage`, `service.panel_account.manage` → none), pinned by `ApiTokenScopeMapTest`.

## Consequences

- **Not default-off — a tightening at deploy** (TASK-0029, TASK-0030): no service, customer or plan is changed, but
  integrations on API tokens that deleted backups, pushed staging, restored or terminated get 403; old "operate
  services"/"all scopes" keys lose consoles and commands; `svc_manage` loses root, rescue, game sub-users and console
  schedules; staff dunning/capacity runs and panel login ask a step-up. The combined release note is in
  `docs/runbooks/go-live-checklist.md` §6.
- **Default-off** (TASK-0031): `ONHOST_VIES_ENABLED=false`, rule `tax.vies_recheck` `default_off`, `onhost:vat:verify` a
  dry run. Money of existing customers is unchanged until the operator switches VIES on or runs `--apply`.
- **Go-live item — the accountant's sign-off (D31.9)**, before `--apply`: the reverse-charge legend and the line with both
  VAT IDs and the consultation number ("registrace k DPH doložena mimo VIES" under an override); the list of past VAT
  invoices (`onhost:vat:verify --csv=…`); partner self-billing categories (S at `standard_rates.CZ`, AE, E) incl. the
  *identifikovaná osoba* case and the gross payout with input VAT; retention of `vat_validations` on erasure; how finance
  treats `name_mismatch`, `vat_country_mismatch`, `identity_unconfirmed` / `identity_changed`; the wording
  "Registrace dodavatele k DPH neověřena."; `--apply` for the `partner` group before the first payout; SLA credits
  following the credited line. The steps are in `docs/runbooks/vat-and-vies.md` and the go-live checklist.
- A solo owner with `ONHOST_FOUR_EYES=false` can confirm a partner supplier alone — and so have VAT paid out; the known
  single-operator design (ADR-0007 decision 13).

## Not decided here (open, listed in the handoffs)

- A fresh step-up for `subuser.create` and a console-command `schedule.create` (persistent console grants; pinned as
  "no step-up" so a change is a deliberate diff). Whether a Pterodactyl scheduled backup task rotates unlocked backups
  away (not verified; then `game.manage` for backup tasks or no sub-hourly backup cron).
- Console access made before TASK-0029 through the old default (game sub-users, root password / SSH keys, console
  schedules): an operator command, `--dry-run` by default, is still to be built.
- Family permissions in the day-to-day actions (D29.8), four eyes for staff deletion of customer copies, `site.delete`
  asking `service.delete`, `monitoring.set` in hooks/Discord, `schedule.toggle`/`schedule.run` of an owner's console
  schedule, code execution through `service.manage` (cron, files, deploy hooks, game files and variables).
- Tokens: `ServiceController::fileDownload` still asks `service.manage` (a power token reads site files one by one); the
  route layer (`TokenRouteScope`, `ServiceController::action`) calls `permissionFor($action)` without the params, so a
  console-only token is refused a console schedule on `/actions` (the dispatch still refuses a power-only one); a stored
  `*` ability would pass every scope (no path mints one today).
- VAT: a per-IP limit on guest checkouts with a VAT number; paying self-billing VAT only to the account published for the
  DIČ (§109 ZDPH, needs a new provider); the erasure policy for `vat_validations`; the qualifier in the prototype's
  `onhost-content.js`.
