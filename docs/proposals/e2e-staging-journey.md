# The bank-transfer journey on staging (P15b, phase R — proposal)

P15a proves the journey in CI against doubles (`tests/Feature/E2E/BankTransferJourneyTest.php`: order by transfer → proforma →
camt.053 / GPC import → settlement → provisioning → renewal without credit → dunning → suspension → top-up by transfer →
resume → cancellation at period end; card: `BillingDunningFlowTest`, end and purge: `CancellationFlowTest`). This is how the
same journey would run on staging. **Nothing here runs without the owner's "ano" to each write step** (staging gate of phase P/Q:
mode S = read over SSH, Z1 = one small write with a rollback, V = the owner's document).

## Where staging stops

Staging is contained (TASK-0045): its panels are the contained ISPConfig/aaPanel instances, and provisioning there is frozen
(`staff/provisioning/freeze`) outside an approved window. The journey therefore runs **up to the order being paid and settled**
and, only inside an approved provisioning window, to ACTIVE on the contained web node. Dunning and suspension are proven by the
clock in CI; on staging they would need weeks of real time or a clock that production must never have — not proposed.

## Steps

| # | Step | Mode | What changes | Check | Rollback |
| --- | --- | --- | --- | --- | --- |
| 1 | Sandbox organization `e2e-staging-<date>` signs up through the public site (owner, browser) | V | one user + organization | `/v1/me` | erase the account (owner-only, step-up, 14 days) or keep it as the sandbox |
| 2 | Cart → order "by transfer" for the cheapest web plan on a test domain under the staging zone | V (browser) | one order + proforma | proforma PDF shows the VS | cancel the unpaid order (`orders/{id}/transition` → cancelled) |
| 3 | A synthetic camt.053 file with that VS and amount (built like the CI fixture, no real account data) imported by finance in the staff console | V | one statement line, the order paid, a receipt and an invoice | staff console: line matched; `onhost:doctor` | a credit note of the invoice (`returnToCredit`) — never an edited row |
| 4 | The same file again | V | nothing (`external_id` dedupe) | the line count is unchanged | — |
| 5 | Inside an approved provisioning window only: thaw, let the saga run, freeze again | Z1 + V | one site on the contained web node | the service ACTIVE, the site answers | cancel + purge the service (E10 path) |
| 6 | The same with a GPC file for a top-up | V | one wallet credit | wallet balance | the credit stays (no cash refund of credit — owner decision 2026-10-05); note it in the protocol |

A "fake provider" for staging (a panel double that production refuses by `APP_ENV`, like `paper-parent`) is a separate decision
and is **not** proposed here: the contained panels already are the honest target.

Record: `docs/runbooks/staging-rehearsal-<date>.md` (outputs without secrets).
