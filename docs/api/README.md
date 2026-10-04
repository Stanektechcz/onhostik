# API v1

Contract: `contracts/openapi/onhost-v1.yaml` (generated — `php artisan onhost:openapi`). Base path `/v1`.

## Conventions

* JSON only. Errors: `{error, message, status, errors?: {field: [msg]}, requirement?}`. Slugs worth handling:
  `unauthenticated`, `access_not_approved`, `step_up_required` (`help: /v1/auth/step-up`), `approval_required`,
  `invalid_transition`, `not_found`, `rate_limited`, `csrf_token_mismatch`, `provider_*` (503/502 with
  `retryable`).
* Lists: `?limit=&offset=` (+ filters per endpoint), `X-Total-Count` header, `data: [...]`.
* Mutations: `Idempotency-Key` header (replays return the stored result; the same key with another body is 409
  `idempotency_key_reused`; an answer that handed out a secret — a new API token, an action hook URL, a generated password —
  is not kept, and its replay is 409 `already_done`); `X-Organization` selects the organization for multi-org users.
* Money: `{minor, currency, decimal}`. Dates: ISO-8601 with offset.
* Auth: SPA session (`GET /sanctum/csrf-cookie`, `POST /v1/auth/login`, cookie + `X-XSRF-TOKEN`) or bearer
  tokens `onh_live_…` with scopes (`POST /v1/tokens`); step-up (`POST /v1/auth/step-up`, TOTP) is required for
  HIGH-risk commands and expires after the configured window.
* Rate limits: `public` (per IP), `auth` (per IP + e-mail), `api` (per user/token), `domain-check`, `probes`.
* Webhooks: see [Webhooks](#webhooks) below.

## Webhooks

* Endpoints: `GET /v1/webhooks` (endpoints, the event catalogue `events`, the subscribable `families`), `POST /v1/webhooks`
  `{url: https://…, events: ['*' | 'family.*' | event]}` (HIGH: fresh step-up; the signing secret is in this answer only;
  https on port 443 or 8443 only, otherwise 422 `webhook_port_not_allowed` — checked again at every attempt, so an older
  `http://` or other-port endpoint gets failed deliveries),
  `DELETE /v1/webhooks/{id}` (removed for good), `POST …/{id}/enable` (a suspended endpoint, failure count reset),
  `POST …/{id}/rotate-secret` (HIGH; the new secret signs every attempt from then on, retries included), `POST …/{id}/ping`
  (a `webhook.ping` delivery, 202; one per endpoint every 30 s, otherwise 429 `webhook_ping_cooldown` with `retry_after`),
  `GET …/{id}/deliveries`, `POST …/{id}/deliveries/{delivery}/redeliver` (202; the same delivery id and body again, as one
  more attempt — a delivery is attempted at most 10 times in all, then 409 `webhook_redeliver_limit`; 20 redelivery requests
  per endpoint and hour, then 429 `webhook_redeliver_rate`; a delivery already waiting for or in its attempt is not queued
  twice). At most 10 endpoints per organization. An unknown event or family is 422 `webhook_event_unknown`.
* Request: `POST` with `Content-Type: application/json` and the headers `X-ONhost-Event`, `X-ONhost-Delivery` (stable across
  retries and redeliveries; delivery is at least once, so a receiver **must** deduplicate on it), `X-ONhost-Timestamp: <unix seconds>` and
  `X-ONhost-Signature: v1=<hex HMAC-SHA256(secret, "<timestamp>.<raw body>")>`. The secret is the whole `whsec_…` string.
  Verify over the raw bytes, compare in constant time and refuse timestamps older than 5 minutes. This is the only
  signature format; the endpoint list answers it under `signature`.
* Body: `{id, event, created_at, data: {aggregate: {type, id}, organization_id, payload}}`. `payload` carries only the
  public fields listed per event (`WebhookEvents`, see `docs/architecture/events-catalog.md` → Consumers): ids, numbers,
  dates, amounts, short codes and the customer's own names — never free text written by staff or the platform (reasons,
  notes, incident and ticket titles); a secret pasted into a field is masked. Events of the
  platform's own work (reconciliation, provider notices, staff assignments, settlement failures) are never sent. Slack,
  Teams and Discord incoming-webhook URLs get that tool's message format with the same headers.
* Delivery: from the queue, only to public addresses (checked again at every attempt, pinned, no redirects; IPv4-mapped
  IPv6 is refused), 8 s timeout, a 2xx answer counts, at most 60 attempts per endpoint and minute (the rest waits). Retries after 1, 5, 30, 120 and 720 minutes — six attempts, the last one about 14.6 h after the first; dead only when that fails; after 20 failed attempts in a row the endpoint is
  suspended and the organization is told (`webhook.endpoint.suspended`: portal notice and mail).

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

## The contract, the documentation pages and how they stay true

* `php artisan onhost:openapi` writes `contracts/openapi/onhost-v1.yaml` from the routes; `php artisan onhost:openapi --check`
  writes nothing and exits 1 when the committed file differs from what the routes generate (CI and the deploy scripts run it).
  Every operation carries `x-token-scope`: the scope an API token needs for it, taken from `TokenRouteScope` itself (an
  operation without it is not reachable with a token — the portal's own session only).
* `/dokumentace/api` renders the contract with a vendored Redoc (`public/vendor/redoc`, no CDN, its own CSP without
  `unsafe-eval`) beside a guide, the header and limit tables, the webhook delivery description, the error-slug index (every
  slug found in `new DomainError('slug', …)`, anchored as `#slug-with-hyphens` — the form `DomainError::toProblem()` puts into
  `help` — and as the raw slug) and `docs/api/CHANGELOG.md`.
* `/api` and `/dokumentace` (the public surface) take their numbers from the same class: `App\Http\Support\PublicApiDocs`
  (limits and page size from `onhost.api.*`, the idempotency key length, the webhook retry from
  `WebhookDispatcher::BACKOFF_MINUTES`, the endpoint list checked against the router). The prototype files stay byte-identical;
  the replacement happens in `SurfaceController::asset` (`onhost-public.js`, `onhost-docs.js`) and in `SurfaceRenderer` (the
  sentences in `Onhost.dc.html`). `tests/Feature/Http/ApiDocsTest.php` holds all of it against the code.
* A change a client can notice (a header, a limit, a renamed operation, a new error code) gets an entry in
  `docs/api/CHANGELOG.md`; the guide "first call in five minutes" is `docs/api/FIRST-CALL.md`.
