# API v1

Contract: `contracts/openapi/onhost-v1.yaml` (generated — `php artisan onhost:openapi`). Base path `/v1`.

## Conventions

* JSON only. Errors: `{error, message, status, errors?: {field: [msg]}, requirement?}`. Slugs worth handling:
  `unauthenticated`, `access_not_approved`, `step_up_required` (`help: /v1/auth/step-up`), `approval_required`,
  `invalid_transition`, `not_found`, `rate_limited`, `csrf_token_mismatch`, `provider_*` (503/502 with
  `retryable`).
* Lists: `?limit=&offset=` (+ filters per endpoint), `X-Total-Count` header, `data: [...]`.
* Mutations: `Idempotency-Key` header (replays return the stored result); `X-Organization` selects the
  organization for multi-org users.
* Money: `{minor, currency, decimal}`. Dates: ISO-8601 with offset.
* Auth: SPA session (`GET /sanctum/csrf-cookie`, `POST /v1/auth/login`, cookie + `X-XSRF-TOKEN`) or bearer
  tokens `onh_live_…` with scopes (`POST /v1/tokens`); step-up (`POST /v1/auth/step-up`, TOTP) is required for
  HIGH-risk commands and expires after the configured window.
* Rate limits: `public` (per IP), `auth` (per IP + e-mail), `api` (per user/token), `domain-check`, `probes`.
* Webhooks: `POST /v1/webhooks` registers an endpoint for customer-facing events (see
  `docs/architecture/events-catalog.md`); deliveries are signed `X-Onhost-Signature: t=<ts>,v1=<hmac-sha256>`.

## Areas

| Prefix | Surface | Highlights |
| --- | --- | --- |
| `/catalog`, `/cart`, `/domains/check` | public web | catalog with prices per currency/period, cart quotes with tax, domain availability (WAPI) |
| `/auth/*`, `/me/*`, `/tokens` | all | register (with `partner_code`), login/lockout, TOTP, step-up, password reset, API tokens |
| `/organizations`, `/orders`, `/wallet`, `/payments`, `/invoices`, `/subscriptions`, `/usage`, `/dunning` | panel | organizations & members, checkout, wallet top-ups (Comgate/bank), documents (PDF, UBL), renewals, metering |
| `/services/*` | panel | services, actions (power/suspend/resize/terminate/backup/restore/snapshot), console tokens, usage, operations, backups |
| `/domains/*`, `/domains/{zone}/zone/*` | panel | registration, renewal, transfer, nameservers, DNSSEC; two-phase DNS editing |
| `/tickets`, `/assistant/chat`, `/notifications`, `/webhooks` | panel | support, assistant, in-app notifications and preferences, webhooks |
| `/status`, `/incidents`, `/my/incidents`, `/sla-credits`, `/probes/results` | public / panel | status page, incidents, post-mortems, SLA credits, probe ingest |
| `/posts`, `/kb`, `/changelog`, `/locations`, `/stock`, `/leads`, `/tender/request`, `/reseller/*` | public web | content and inbound forms |
| `/abuse/reports`, `/abuse-cases`, `/data-requests` | public / panel | DSA notices, complaints, GDPR/Data Act requests |
| `/partner/*` | partner portal | overview, clients, commissions, payouts (self-billing), white-label, assets |
| `/staff/*` | admin | customers, orders, services, integrations, provisioning queue, drift, freeze, reports, tickets, outbox, templates, incidents, maintenance, probes, SLO, SLA credits, security, compliance, abuse, data requests, partners, leads, content |
