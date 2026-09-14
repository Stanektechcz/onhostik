# ADR-0003 — Integer money, double-entry ledger, tax engine, document series

**Status:** accepted (2026-09) · **Blueprint:** §46–§52

## Decision

* Money is an immutable value object in minor units with an explicit currency (`Money::minor`, `decimal`,
  `percent`, `share`); JSON form `{minor, currency, decimal}`. No floats in business code.
* Wallet balances are derived from a double-entry ledger (`ledger_transactions` / `ledger_postings`) with
  buckets main / promo / credit line; every posting has an idempotency key and the invariant
  (Σ debits = Σ credits, wallet balance = Σ postings) is verified daily (`onhost:ledger:verify`).
* Tax decisions come from versioned rule sets (`TaxEngine::calculate`) covering CZ domestic, EU B2B reverse
  charge, OSS B2C and out-of-scope supplies; the decision note is stored on the document.
* Documents are typed and numbered per legal entity series: FV invoice, PF proforma, PP receipt, VY statement,
  DK credit note, OD correction. Issued documents are frozen (PDF + hash + UBL 2.1) and only corrected by a
  new document.
* Payments are provider-abstracted (Comgate first) with an intent state machine and reconciliation against
  bank statements; orphan callbacks raise `payment.orphan_callback`.

## Consequences

* SLA credits and partner payouts are ledger postings (promo top-up / commission expense), never balance edits.
* Refunds and adjustments are commands with risk levels (step-up, four-eyes above thresholds).
