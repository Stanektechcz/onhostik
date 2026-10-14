# Comgate gateway: credentials, mode and the administration's check (H-R8, TASK-0129)

Owner decision H-R8 (2026-10-07): ONhost has **no Comgate test account yet**; the live integration is not solved now. What exists
is a staff-only check in the administration, so the gateway can be verified the day credentials arrive — without reading `.env`,
without a shell and without a real payment.

Code: `providers/Payments/Comgate/ComgatePaymentProvider.php` (`credentialState`, `probe`, `testPayment`), `ComgateMode`,
`domains/Payments/ComgateCheck.php`, `ComgateCheckCommand`. Tests: `tests/Feature/Payments/ComgateCheckTest.php` (Http::fake only).

## Where the credentials live

`COMGATE_SECRET_REF` (default `env://COMGATE` = `COMGATE_MERCHANT` + `COMGATE_SECRET`) or a vault entry (`db://…`, set with
`php artisan onhost:secrets:set`). The administration shows only **whether** the merchant id and the secret are there and which
store holds them; never a value. The merchant id is shown masked (last three characters).

## The administration (Nastavení → Integrace → *Platební brána Comgate — test*)

| Action | API | Who | What it does |
| --- | --- | --- | --- |
| State | `GET /v1/staff/payments/comgate` | `provider.instance.read` | credentials present?, store, mode and who set it, gateway host, recurring flag, callback allow-list size, last check |
| Ověřit spojení | `POST /v1/staff/payments/comgate/check` `{kind: connection}` | `provider.instance.manage`, fresh step-up | `GET /v2.0/method.json` with the merchant credentials: proves merchant, secret and address; moves no money |
| Testovací platba 1 Kč | `… {kind: payment}` | same | `POST /v2.0/payment` with **`test: true` always** (also when the gateway is live), `prepareOnly`, 1 Kč, then reads the status back; no payment intent, no ledger posting |
| Režim brány | `PUT /v1/staff/payments/comgate/test-mode` `{enabled: true|false|null, reason}` | same + **second person** (CRITICAL) | overrides `COMGATE_TEST` (setting `payments.comgate.test_mode`); `null` returns the decision to the deployment |

Missing credentials: the check answers `comgate_credentials_missing` (which of the two) and **sends nothing**. Refused credentials
(HTTP 401/403): `comgate_credentials_refused`. Anything else: `comgate_unreachable` with the HTTP status and the gateway's code.
The last result (without any credential; a credential echoed by the gateway is masked) is kept in `payments.comgate.last_check`.
Every run is a bus command: audit `payments.comgate.check` / `payments.comgate.test_mode.set`. The provider-call log redacts the
`Authorization` header by name.

## Payment notifications: the source allow-list (W1)

`POST /v1/webhooks/payments/comgate` accepts a notification only from an address on `COMGATE_CALLBACK_IPS` (comma-separated; an
address or a CIDR range, IPv4 or IPv6; entries are trimmed, blank ones and allow-all `/0` ranges are ignored). **An empty list
refuses every notification** (`webhook_source_rejected`, HTTP 403) and Comgate is not asked about it; the administration's state
then says so (`callback_allowlist: 0` with `callback_notice`). The list is required in production; the owner supplies Comgate's
notification addresses (input CG-6). Until it is filled, a payment is confirmed only when the payer returns to the portal or by
the sweep of card payments left pending (W6, below).

The check reads the sender address Laravel resolves (`$request->ip()`). Behind a reverse proxy (nginx on another host, a load
balancer, a CDN) `TRUSTED_PROXIES` **must** include that proxy, otherwise every notification appears to come from the proxy's
address: either all are refused, or — if someone lists the proxy's address — anybody can send one through it. Never put the
proxy's own address into `COMGATE_CALLBACK_IPS`.

## Card payments left pending (W6, TASK-0228)

A card payment is settled by Comgate's notification or by the payer's return to the portal. When both are lost the money is
taken and the intent would stay open, so `onhost:payments:sweep` (every ten minutes, one server, no overlap) re-reads the
gateway's status of every card intent still open (`CREATED`, `PENDING_CUSTOMER`, `AUTHENTICATION_REQUIRED`, `PROCESSING`) and
applies it through `PaymentService::syncFromProvider`, the same idempotent path the notification takes: paid → settled,
cancelled/failed → closed, still pending → asked again next run. There is no daily reconciliation job that would do this
(`PaymentService::reconcile()` is not scheduled).

| Setting | Env | Default | Meaning |
| --- | --- | --- | --- |
| `onhost.payments.sweep.providers` | `ONHOST_PAYMENTS_SWEEP_PROVIDERS` | `comgate,gopay,stripe` | gateways asked; `bank` never (transfers are paired by statement lines); empty = the job does nothing |
| `onhost.payments.sweep.grace_minutes` | `ONHOST_PAYMENTS_SWEEP_GRACE_MINUTES` | 10 | younger intents are left to the notification and the return |
| `onhost.payments.sweep.max_age_hours` | `ONHOST_PAYMENTS_SWEEP_MAX_AGE_HOURS` | 72 | older intents are finance's (merchant portal, `POST /v1/payments/{intent}/sync`) |
| `onhost.payments.sweep.batch` | `ONHOST_PAYMENTS_SWEEP_BATCH` | 50 | intents per run, the one asked about longest ago first: every intent asked about goes to the back of the queue (`updated_at` is touched), so payments payers abandoned never keep a newer paid one waiting; `--limit N` (1–999999) overrides once, anything else is refused |

Output, one line: `payments sweep: checked N, settled N, failed N, open N, errors N, skipped <providers>`. A gateway without
credentials is **skipped** with the warning `payments sweep: provider skipped, no credentials` (staging today, which has no
Comgate account) and the job still exits 0, so the doctor's *every scheduled job ran within its budget* stays OK. An intent the
gateway cannot answer is logged as `payments sweep: intent not re-read` with the intent id, the provider and the exception class
only, and the run goes on. A state the gateway reports is written under a row lock and only onto a state that can still take
it, so a stale answer never overwrites a payment a concurrent notification or return settled.

**Amount mismatch — left to finance.** When the gateway reports the payment paid with another amount than the intent, the
intent is not settled; it is marked (`raw.amount_mismatch`) and a `payment_amount_mismatch` reconciliation item is opened
(`finance.reconciliation.mismatch`). The sweep never asks about such an intent again, so the item is opened once, not every ten
minutes. Finance resolves it (with the merchant portal: refund the difference, or correct by a new document — never edit a
posting) and then re-reads the intent by hand with `POST /v1/payments/{intent}/sync`. While the gateway still reports the other
amount, that re-read opens a new item again (the existing behaviour of `syncFromProvider`): re-read only once the gateway's side
is corrected.

## Doctor

* *card gateway live mode* reads the mode payments are really created in (administration override first, then `COMGATE_TEST`) and
  says who set it; it blocks a production deploy while the gateway is in test mode (as before).
* *Comgate answered the last administration check* (WARN only): never checked, or the last check failed.

## Not verified live

No request has reached Comgate from this code: there is no account. `method.json` and the test-payment parameters follow the Comgate
REST API v2.0 documentation (apidoc.comgate.cz). On the day the test merchant exists: put the credentials in the store, run
*Ověřit spojení*, then *Testovací platba 1 Kč*, then the manual test of a card order on staging (`docs/manual-tests/01-registrace-objednavka.md`).

**Owner decision I-R3 (2026-10-08):** the owner opens the Comgate test merchant account and puts its credentials into the store on the
server himself (`php artisan onhost:secrets:set`; never into a chat, the repository or a log). Then, each step with his yes: *Ověřit
spojení*, *Testovací platba 1 Kč*, recording the real answers (`php artisan onhost:fixtures:record comgate`, no secret in the fixture),
tests over them, and the manual card payment on staging (package I2). Until then card payments stay unverified (go-live B5).

**Owner decision I-R10 (2026-10-08):** Comgate issues the test account only after its support manually reviews a fully functional deployed website, so the account comes after the public site is complete: checklist, repository state and gaps in [comgate-merchant-review.md](comgate-merchant-review.md).
