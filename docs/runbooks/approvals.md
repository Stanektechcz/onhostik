# Four eyes — the second person of a critical action

Page: **Nastavení systému → Schvalování** (`/sprava/nastaveni/schvalovani`). API: `GET /v1/staff/approvals`,
`POST /v1/staff/approvals/{id}/decision`. Code: `ApprovalService`, `IdentityCommandAuthorizer`, `CommandBus`.

## Why this page exists

The gate was in the authorizer from the first day: a command whose permission is CRITICAL needs an approved, unused
approval of **exactly that command** (its name and the hash of its audited payload), decided by **somebody else**.
But nothing could ever create an approval. So a critical action was either impossible (`organization.close`), or its
command quietly declared itself "high" and lost its second person — a **legal hold was placed and lifted by one
member of staff alone** (the old `ComplianceTest` documented exactly that). Review 2026-09-20, finding 6.

## How it works

1. Somebody with the permission and a fresh step-up tries the action alone → `403 approval_required`. The refusal
   **opens the request** and carries it: `approval_id`, `approval_state: pending`, `approval_expires_at`. Asking twice
   opens it once. Staff hear about it (`iam.approval.requested`).
2. Somebody else decides it on the page. They need `iam.approval.decide` **and** the permission of the action itself
   (the second person is somebody who could do it, not merely somebody who may press a button), and a fresh step-up of
   their own. Never the requester (`approval_own_request`), never a token, the system or the assistant. A rejection
   says why (`note_required`).
3. The requester repeats **the same action** (the same button; an API client may name the approval in `approval_ids`, it
   need not — the approval of exactly this command by this person is found). The approval is spent by it: one command,
   one payload, once. Another payload — or the same action a second time (lifting the hold) — is a new request.
4. A request nobody decided in `ONHOST_APPROVAL_TTL_HOURS` (24) runs out; `onhost:access:expire` closes the record.

The audit row of the action carries `approval_ids` and the step-up method; `iam.approval.request` and
`iam.approval.decide` are audited on their own.

## What is critical

Permissions the catalogue marks CRITICAL for staff: `compliance.legal_hold.manage`, `iam.role.manage`,
`iam.break_glass`, `billing.refund.execute_large`, `billing.credit.adjust_mass`, `billing.tax_rule.manage`,
`provider.secret.view`, `secret.rotate`, `dns.global.write`, `domain.critical.manage`. A command can no longer talk
such a permission down to "high" (`riskLevel()` is ignored for them). Commands may still say that one of their
operations under a HIGH permission needs no step-up (a draft, a note) — those per-operation decisions are listed in
`production-readiness-audit.md` §7 for the owner's review.

## One operator alone

Four eyes need two heads. With fewer than two people who may decide approvals the page says so, `onhost:doctor`
warns (`four eyes in effect`), and a critical action of the only operator stays refused — **by design, the switch is
not in the application**: whoever takes over a staff account must not be able to turn the second person off.

To run the platform alone, set on the server and reload the configuration:

```
ONHOST_FOUR_EYES=false        # /etc/onhost/app.env, then: php artisan config:cache
```

The step-up stays, every critical action is audited with `approval_ids: ["waived:single-operator"]`, the doctor
reports the mode. Switch it back on the day a second person joins and grant them a role with `iam.approval.decide`
(`iam_admin`, `platform_owner`).

## Staging checks

1. As `compliance_legal`: place a legal hold → refused with an approval id; the page shows the request.
2. As another person (`platform_owner`): approve after the step-up dialog → the first person repeats the hold with
   `approval_ids` → it is placed; the request shows „použito".
3. With one staff account only: the page warns; decide whether staging runs `ONHOST_FOUR_EYES=false`.
