---
name: billing-change
description: Use when changing ONHOST prices, invoices, payments, tax, dunning, wallet, ledger, refunds, credits, or other money behavior.
---

# Billing change

1. Read ADR 0003, `docs/runbooks/pricing.md`, `docs/runbooks/billing-dunning.md`, and relevant domain tests.
2. Specify examples in integer minor units and every supported currency/tax case.
3. Write failing tests for rounding, retry/idempotency, tenant isolation, correction records, and failure paths.
4. Implement through a risk-aware CommandBus handler. Keep invoices and ledger postings append-only.
5. Require provider contract tests for payment calls and verify no sensitive payload reaches logs/events.
6. Run focused tests, `brain.ps1 test`, and security and architecture reviews.
