# Grants follow one policy, access can be restored (TASK-0042)

Permission program Slice 1, **S1-01** and **S1-02** (`docs/security/permission-program-2026-09-27.md` §3, §6; ADR-0009 D1, D21).
This page is for whoever integrates the portal, an API client or an operator runbook with the change.

## 1. One policy for every grant (S1-01)

`domains/Organizations/GrantPolicy.php` decides every grant, change and removal of access in a customer organization. Its
invariants are the constant `GrantPolicy::INVARIANTS`; `tests/Feature/Organizations/GrantMatrixTest.php` decides each of them for
each entry point — a proof or a stated reason, and a completeness test that fails on a gap.

| Entry point | Where | What is new |
| --- | --- | --- |
| Invitation | `OrganizationCommand invite` | I5: the membership offered ends no later than the inviter's own |
| Acceptance | `AcceptInvitationCommand` (bus) | I8: through the bus, audited; I5: clamped to the sender's end at the click; I6: shares of a sharer who lost the right are not activated; I10: a widening snapshots the old membership |
| Role change / removal | `OrganizationCommand change_role` / `remove_member` | I5 clamp; I10 snapshot first; I6 cascade for the person who lost the right |
| Project role | `add_project_member` / `remove_project_member` | I5 clamp to the grantor's end at the project; I10 snapshot |
| Service share | `ServiceAccessCommand share` / `revoke` | now GrantPolicy (`assertMayShareService`): I3 — nobody narrows or revokes a share they could not have given (`share_above_own`, 403); I12 — share chains stop at depth 2 (`reshare_too_deep`, 403); I5 clamp; I10 snapshot |
| Ownership | `OwnershipCommand` | I4 two-step (below) |
| Restore | `OrganizationCommand restore_access` | I1–I4, I11 (below) |
| API token | `ApiTokenCommand create` | I8: only a current member (`not_found`); I5: `expires_at` no later than the membership's end — the answer's `expires_at` is the stored one |
| Staff account | `onhost:staff:create` | I11 at global scope: a staff role only (`invalid_role` for `owner` & co.) |

**New refusals are for illegitimate grants only.** I5 never refuses: the end is clamped and the effective end is what the answer
and the stored row carry. Bindings now name who gave the role (`policy_bindings.granted_by` = the grantor; for a membership that came
by a link, the link's sender) — before, an accepted membership looked self-granted.

## 2. A grantor's loss (I6)

When a member is removed or demoted (`organization.member.removed` / `.role_changed`, not an ownership transfer), `GrantCascade`:

- cancels their **pending** shares the grantor could not make now (and their pending invitations, as before) — always;
- **records** each **active** grant they gave that they could not give now — memberships, project roles, shares — as an audit row
  `organization.grant.cascade.flag` and one `organization.grants.unbacked` event for the organization;
- **revokes** those grants instead only when `ONHOST_GRANT_CASCADE_ENABLED=true` (`onhost.grants.cascade_enabled`, default
  **off**). Every revocation takes an access snapshot first, so each is one restore away.

Operator: `php artisan operator:grants:cascade --dry-run [--organization=org_…]` lists every active grant no longer backed by
whoever gave it (including grants from before this release). It changes nothing; `--apply` is refused by design. Runbook: run the
dry run, tell the organizations concerned, then decide on the switch — it acts on the next loss, never retroactively.

## 3. Access snapshots and restore (I10)

Before every removal and role change — member removed (by hand, by `onhost:access:expire`, by the cascade), role changed,
invitation that widens a role, project role changed or removed, share narrowed or revoked, ownership transferred — an
`access_snapshots` row records the person's access in the organization: the membership (role, state, end), every binding of
theirs there, their project roles and their active shares. Kept 90 days (`onhost.grants.snapshot_retention_days`), pruned by
`onhost:access:expire` (column `snapshots_pruned`).

- `GET /v1/organizations/{organization}/access-snapshots` (`organization.members.manage`) — the last 90 days, each row with
  `restore: {method, path, body}`: the exact request that gives it back (null once restored or expired).
- `POST /v1/organizations/{organization}/access-snapshots/restore` `{"snapshot_id": "asn_…"}` — HIGH (fresh step-up). Answer
  `{restored: true, snapshot_id, before_restore, role}`. Refusals: `not_found` (another organization's snapshot), `snapshot_expired`
  (410), `snapshot_restored` (409, once only), `snapshot_lapsed` (409, the access ended on its own date since),
  `owner_role_locked` (the owner binding is never restored), `self_membership_locked`, `member_above_own`, `role_above_own`,
  `invalid_role` (a legacy project role outside the allow-list), `owner_recovery_hold`.
- Exact means exact: `AccessSnapshots::capture()` before the removal equals it after the restore (`AccessRestoreTest`). What the
  restore replaces is snapshotted first (`before_restore`). **Not** restored: SSH keys and panel sub-users the listeners took off
  the panels, Discord links and hooks that were switched off — the person adds them again.

## 4. Ownership in two steps (I4, audit TD-9)

- `POST /v1/organizations/{organization}/ownership-transfer` `{"user_id"}` — the owner offers (`organization.close`, HIGH). 201
  `{transfer: {id, state: pending, from_user_id, to_user_id, expires_at, …}}`. Refusals: `owner_transfer_only`, `owner_transfer_self`
  (422), `not_found` (not a current member), `owner_recovery_hold`. A new offer replaces the pending one.
- `POST …/ownership-transfer/accept` — the heir, in person, with a fresh step-up (`organization.read`, declared HIGH). The owner
  binding moves; the previous owner becomes `org_admin`; both are snapshotted. `ownership_offer_invalid` (409) when there is no
  pending offer, it lapsed (7 days, `onhost.grants.ownership_offer_days`) or the organization has another owner by now.
- `POST …/ownership-transfer/decline` (the heir) · `DELETE …/ownership-transfer` (the owner).
- `GET /v1/organizations/{organization}` shows `ownership_transfer` (pending or null) and `owner_recovery` (pending or null).
- **Behaviour change:** `OrganizationCommand` op `transfer_ownership` now creates the offer (answer `{transfer: …}`) instead of
  moving the ownership at once. `OrganizationService::transferOwnership()` stays the internal completion step.

## 5. Owner recovery (D21)

- `POST /v1/staff/customers/{organization}/owner-recovery` `{mode: mfa_reset|transfer, new_owner_user_id?, reason (≥10), ticket_ref}`
  — `iam.mfa.reset`, **CRITICAL**: the first request answers 403 `approval_required` with `approval_id`; a second person approves;
  the repeat with `approval_ids` opens it (201). The sole approver with `ONHOST_FOUR_EYES=false` waits the time lock instead.
- It runs only after `onhost.grants.owner_recovery_days` (`ONHOST_OWNER_RECOVERY_DAYS`, never below 7): every current member and
  the owner get the mandatory mail `owner-recovery-opened` and an in-app notice at once.
- While it waits the organization is on hold (`owner_recovery_hold`, 409): no API token, no data export (`kind` export/switching),
  no ownership offer or acceptance, no access restore.
- `POST /v1/organizations/{organization}/owner-recovery/cancel` — any member manager (org_admin, the owner), HIGH.
  `DELETE /v1/staff/customers/{organization}/owner-recovery` — support.
- `POST /v1/staff/customers/{organization}/owner-recovery/complete` — after the notice period (`owner_recovery_locked` before):
  `mfa_reset` clears the owner's authenticator, recovery codes, security keys and trusted devices (mail `security-mfa`);
  `transfer` hands the ownership to the named current member.
- `POST /v1/staff/users/{user}/mfa-reset` `{reason}` — `iam.mfa.reset`, HIGH; **refused for a customer owner**
  (`owner_recovery_required`, 409) — the recovery above is the only way.

## 6. Configuration and migration

`config/onhost.php` → `grants`: `cascade_enabled` (`ONHOST_GRANT_CASCADE_ENABLED`, false), `snapshot_retention_days` (90),
`owner_recovery_days` (`ONHOST_OWNER_RECOVERY_DAYS`, 7, floor 7), `ownership_offer_days` (7). Migration
`0001_01_01_000900_grants_follow_one_policy.php` adds `access_snapshots`, `ownership_transfers`, `owner_recoveries`; nothing
existing is altered. Mail templates `ownership-offered`, `ownership-transferred` (mandatory), `owner-recovery-opened` (mandatory)
come with `NotificationTemplateSeeder` (run it on deploy as usual). Events: `docs/architecture/events-catalog.md` (TASK-0042 block).

## 7. What existing customers notice (release notes)

- Nothing changes for anybody's current access: no grant is revoked by this release (the cascade switch is off).
- Handing over ownership now needs the new owner to accept it (they get a mail and see it in Team).
- A member with access until a date can no longer hand out access — invitations, project roles, shares, API tokens — that lasts
  longer than their own; the end is shortened automatically and shown.
- Removing a member or changing their role can be undone for 90 days from the Team page.
- An admin who could not grant the console cannot take it from somebody else by re-sharing or revoking.
- A lost owner is recovered by support only with a week's notice to everybody in the organization, who can stop it.
