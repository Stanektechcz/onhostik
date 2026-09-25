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

### Prices and plans (owner decision 13, 2026-09-25)

HIGH means a fresh step-up and nothing more. **Every change of a price or a plan in the admin configuration takes a second
person as well**, although `catalog.manage` is only HIGH: `CatalogCommand::requiresApproval()` asks for it. A catalogue
operation the command does not classify is treated as a price change (fail closed).

| Takes a step-up and a second person | Takes a step-up (one person) | Ordinary |
| --- | --- | --- |
| commitment discounts, regional pricing, a domain discount, an **active** promo code, an option or add-on unit price and deleting an option, publishing a plan version, rolling back to an earlier version, putting a product on sale, the deletion lifecycle (archive download fee, windows) | deleting a domain discount, deleting a promo code, **pausing or retiring** a promo code, taking a product off sale, a product's add-on list | the customer panel sidebar |

- **Emergency brakes stay with one person**: pause or delete the promo code, delete the discount, or take the product off
  sale — in the console with a step-up, or on the server with `php artisan onhost:catalog:state draft <product>` (the
  system actor; no approval exists there). Putting it back on sale is a price change again.
- **Who approves**: somebody else holding `iam.approval.decide` **and** `catalog.manage` — `billing_finance_admin` or
  `platform_owner`. A `product_manager` may ask, not approve; an `iam_admin` may approve other things, not prices
  (`approver_lacks_permission`). `onhost:doctor` shows `price changes have a second person` (WARN with fewer than two such
  people: the only one of them can never have their own price change approved).
- **Checked first, bound to its base**: a change that would be refused (a wrong key, a slipped decimal place without
  `confirm_large_change`, nothing changed) is refused before a request is opened. A plan change carries `base_version`
  (the version on sale when it was asked), a whole-value setting carries `base` (a digest of the value it replaces); when
  somebody else changes it in between, repeating the approved request opens a new one, and a stale binding that reaches
  the handler is `409 catalog_changed_since_request`. The approved request is repeated **unchanged** (the pages keep the
  form as it was and say so).
- The approver reads why: the price endpoints take an optional `reason` (plan versions require one).
- Catalogue revisions defined in code (`php artisan onhost:catalog:revise --apply`, docs/runbooks/pricing.md) publish plan
  versions as the system actor: no second person exists there, shell access is the gate and the revision itself is
  reviewed as code (it cannot pass prices or features; the prices of the current version are carried over).
- Automation switches (`PUT /v1/staff/automation/{rule}`) take a fresh step-up: switching on a rule that ships default-off
  reaches every existing service at once.

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
(`iam_admin`, `platform_owner`; for price changes one that also holds `catalog.manage`: `billing_finance_admin`,
`platform_owner`).

**Before deploying owner decision 13 with one operator: set `ONHOST_FOUR_EYES=false` first.** Otherwise every price,
discount, promo code and plan change of the only operator waits for a second person who does not exist — the price
list freezes (withdrawals and `onhost:catalog:state draft` keep working).

## Staging checks

1. As `compliance_legal`: place a legal hold → refused with an approval id; the page shows the request.
2. As another person (`platform_owner`): approve after the step-up dialog → the first person repeats the hold with
   `approval_ids` → it is placed; the request shows „použito".
3. With one staff account only: the page warns; decide whether staging runs `ONHOST_FOUR_EYES=false`.
4. Prices (decision 13): as `product_manager` with a step-up, publish a plan version and create a promo code → both
   refused with an approval id; approve both as `billing_finance_admin`; send them again unchanged → applied, the audit rows
   carry the approval ids. Ask for another version, publish a different one in between with its own approval, send the
   first again → a new request (the old approval is not spent). Pause and delete a promo code as `product_manager` → a
   step-up alone. `onhost:doctor` → `price changes have a second person` is OK.

## A role reads what it may change (2026-09-20)

`support_l1`, `support_l2`, `support_l3` and `support_manager` hold `support.ticket.read` (they had `manage`/`assign`
without it and got 403 on the queue and on every ticket); `billing_finance_admin` and `billing_operator` hold
`billing.invoice.read`; `backup_dr_admin` holds `backup.read`. The operations boards *deletions* and *SSH key
revocations* ask for `provisioning.operation.read`. `AuthorizationSeeder` runs on every deploy, so the roles are updated
by deploying. **Walk the console under a support role, not as the platform owner** — the owner holds everything, so a
missing permission never shows.
