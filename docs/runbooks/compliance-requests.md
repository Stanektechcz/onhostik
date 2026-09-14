# Compliance requests: GDPR, Data Act, DSA, regulatory timers

## Data export / switching (GDPR Art. 20, Data Act)

1. The customer requests it in the panel (`POST /v1/data-requests {kind: export|switching}`), owner role required.
2. `onhost:compliance:data-requests` (every 30 min) builds the archive on the private disk
   (`exports/<org>/<request>.json`), notifies (`compliance.data_export.ready`, mandatory mail) and keeps it for
   `compliance.data_export_grace_days`. Download: `GET /v1/data-requests/{id}/download` (session only).
3. Switching requests also start the `DATA_ACT_SWITCHING` timer (30 days); DNS zones (BIND export), backups and
   UBL invoices are the portable formats.

## Deletion (GDPR Art. 17)

Blocked while services are live, invoices are open, or a legal hold applies (`deletion_blocked` with the list).
Once allowed, the job anonymises the organization and users that belong to no other organization, revokes
tokens, and keeps tax documents, ledger and audit rows (legal retention). Nothing is physically deleted from
the audit chain.

## Legal hold

`POST /v1/staff/customers/{org}/legal-hold {hold, reason}` (compliance/legal, step-up). Sets the organization
flag and every service's `legal_hold`; blocks deletion and retention purges until lifted. Evidence on cyber
incidents carries its own hold flag.

## DSA notice-and-action

1. Notice arrives via `POST /v1/abuse/reports` (public form; Art. 16 fields enforced). The reporter gets an
   acknowledgement; `abuse.case.opened` lands in the security inbox; life/safety categories start the
   `DSA_ART18_PROMPT` timer.
2. Triage (`/triage`: action | no_action with reasons) → customer statement of reasons (`/notify`, delivered as
   a support ticket on the customer's organization) → action (`/action`: content_removed | service_suspended |
   warning | none; suspension needs step-up and runs the suspend saga) → 6-month complaint window
   (`POST /v1/abuse-cases/{id}/appeal` by the customer) → close.

## Regulatory timers

Cyber incidents start NIS2 (24 h early warning, 72 h notification, 1 month final report), GDPR 72 h and DSA
Art. 18 clocks from `config('onhost.compliance.timers')`. `onhost:compliance:timers` warns at 75 % and marks
missed deadlines. Record filings with `POST /v1/staff/compliance/timers/{id}/submit {authority_reference}`;
waive only with a documented legal reason (step-up). A cyber incident cannot be closed with running timers.
