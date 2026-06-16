# Invoices & tax documents

## Document types

- **Proforma** (`type=proforma`) — payment request, NOT a tax document.
  Issued at order time (or for credit top-ups, `purpose=credit_topup`).
- **Tax document** (`type=invoice`, daňový doklad) — issued AFTER payment,
  linked to the proforma via `parent_invoice_id`.
- Credit note — enum exists, flow arrives later.

## Tax document flow (Phase 3)

`IssueTaxDocumentAction`:

- requires a PAID proforma with `purpose=order`,
- **requires complete billing details** (name + street + city + zip via
  the customer's current data) — otherwise
  `IncompleteBillingDetailsException`,
- taxable_supply_date = proforma payment date, status Paid,
- snapshot from CURRENT customer details, items copied, idempotent.

Triggered automatically inside `HandleInvoicePaid`; when details are
missing the payment continues and the block is audited
(`invoice.tax_document_blocked`) — the admin can issue manually later
from the invoice detail once the customer completes
`/panel/ucet/fakturacni-udaje`.

## PDF

`/panel/fakturace/faktury/{uuid}/tisk` renders a print-ready HTML invoice
(`resources/views/pdf/invoice.blade.php`) — use browser print locally.
Real PDF generation: `spatie/laravel-pdf` is installed but needs Chromium
(Browsershot/puppeteer), which is not set up on the Windows dev box.
Production setup: install Node + puppeteer on the Linux host and queue a
`GenerateInvoicePdfJob` that stores `pdf_path`.

## ACCOUNTANT REVIEW NOTE

Numbering series, the advance-payment regime (proforma → daňový doklad),
DUZP and the top-up 0 % treatment are implemented to common Czech
practice but MUST be confirmed by the accountant before real invoicing.
