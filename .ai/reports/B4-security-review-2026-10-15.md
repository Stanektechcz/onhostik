# B4 — independent security review of 2856e3c and c4c43e2 (2026-10-15)

Scope: read-only review of `git show 2856e3c` (TASK-0042, S1-07 red team: restore events, token revocation, `taken_by_role`,
owner-recovery take-out) and `git show c4c43e2` (TASK-0039, P0-16 re-check: staff mode asks staff keys). Line numbers refer to
`development @ cd65f8e` (later tasks moved some code; e.g. OwnerRecoveries now ends sessions via SessionKill, TASK-0044).
Input for Ž12 / B3. No code changed. Nothing executed (vendor/ not installed): every finding is ASSUMED from reading, not VERIFIED.

## Summary

No CRITICAL regression found. Both commits close what they claim at the call sites reviewed. Residual risks below.

| # | Sev | Where | Finding |
|---|-----|-------|---------|
| F1 | MEDIUM | domains/Services/Commands/ServiceActionCommand.php:276-287 | Staff-mode key fallthrough: actions whose customer key has no staff counterpart (console, restore, copy delete, panel password) are asked with the CUSTOMER key in staff mode, so a staff member's own membership / `svc_*` share still satisfies them on `/v1/staff/services/{id}/actions`. Only the belongsTo→CRITICAL bump (IdentityCommandAuthorizer.php:92) mitigates, and only for a binding in that organization. Intentional per docblock, but it is an allow-by-default map. |
| F2 | MEDIUM | domains/Identity/ApiAccessRevocation.php:65-72 | Tokens with `organization_id = null` and no `org:` ability are never revoked on removal (PA-04, behind `ONHOST_TOKEN_ORGANIZATION_REQUIRED`). Until that flag is on in all envs, a removed member's unbound token survives and regains reach on re-invite/restore. Listed in breach register; B3 should not treat S1-07 as closed while the flag is off. |
| F3 | LOW | domains/Organizations/AccessSnapshots.php:100 | `roleOf` fallback reads `PolicyBinding` by `principal_id` without `principal_type` and without the `expires_at` filter used elsewhere; an expired or foreign-typed binding can set `taken_by_role` to a LOWER role than the remover holds now → a lower restorer passes `snapshot_above_own`. Fail-open direction (narrow: needs ID collision or expired binding as only source). |
| F4 | LOW | domains/Organizations/GrantPolicy.php:484-490 | `taken_by === null` (system context) skips the remover-role check entirely; any removal recorded as system (expiries, automated jobs, any future caller passing `CommandContext::system`) is restorable by any admin who covers the person's bindings. Verify no security-motivated removal runs as system. |
| F5 | LOW | domains/Identity/ApiAccessRevocation.php:70 + Listeners/RevokeMemberSideDoors.php:49 | `issuedBefore = $message->created_at`: a token created in the same second as the outbox row (or with clock skew between app and DB) is kept (`gt`, not `gte`). Edge case, token created by the removed person just before removal survives. |
| F6 | LOW | domains/Organizations/AccessSnapshots.php:186-190 (`releaseEmptyGuest`) | Calls `removeMember` inside the restore transaction, which takes another snapshot attributed to the restorer; restoring THAT snapshot re-creates an empty guest membership (a "way back in" the code says it closes). Loop is bounded by restore rules but the empty guest can be re-materialised. |
| F7 | INFO | domains/Identity/Authorization/StaffActor.php (`may`) | Correctly requires `acts()` (staff mode, self-acting, active, is_staff) AND a platform-level staff role carrying the key; JIT global elevations count per docblock — confirm JIT elevations are time-bound and audited (not re-checked here). |
| F8 | INFO | app/Http/Controllers/Api/V1/ServiceController.php:~416 | Staff-mode resolve authorizes only `staff.customer.read` globally before returning the service; write power then relies fully on the bus (StaffModeCommand). Any future non-StaffModeCommand dispatched from this resolve path would be asked the customer key — see F1. |

## Failing-first test ideas (for CI / B3)

- F1: `StaffModeTest` — auditor (no staff.console) who is member of org with `svc_manage` share calls `/v1/staff/services/{id}/actions` with `action=console` → expect 403 (today: allowed via membership, at CRITICAL). Decide policy first; then add explicit staff keys or deny-by-default for unmapped keys in staff mode.
- F2: `AccessRestoreTest` — member owns token with `organization_id=null`, abilities without `org:`; remove, restore → expect token revoked (run with flag off to document the gap).
- F3: snapshot taken by admin whose org-admin binding has `expires_at` in past and membership role `owner`-level elsewhere; assert `taken_by_role` equals current live role; restore by org_admin must hit `snapshot_above_own`.
- F4: remove admin via a system-context path; restore by another org_admin → expect `snapshot_above_own` if the removal was security-relevant.
- F5: freeze time; create token at exactly `message->created_at`; run listener → expect revoked.
- F6: restore a snapshot with role guest and no shares → guest released; restore the resulting `member_removed` snapshot → expect guest NOT re-created with nothing behind it.

## Status

- VERIFIED: nothing executed (no vendor/, no Pest).
- ASSUMED: all findings above, from diff + current source reading.
- NOT TESTED: runtime behaviour, Postgres (pest-postgres) behaviour, JIT elevation expiry.
