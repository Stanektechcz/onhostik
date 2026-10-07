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

## Doctor

* *card gateway live mode* reads the mode payments are really created in (administration override first, then `COMGATE_TEST`) and
  says who set it; it blocks a production deploy while the gateway is in test mode (as before).
* *Comgate answered the last administration check* (WARN only): never checked, or the last check failed.

## Not verified live

No request has reached Comgate from this code: there is no account. `method.json` and the test-payment parameters follow the Comgate
REST API v2.0 documentation (apidoc.comgate.cz). On the day the test merchant exists: put the credentials in the store, run
*Ověřit spojení*, then *Testovací platba 1 Kč*, then the manual test of a card order on staging (`docs/manual-tests/01-registrace-objednavka.md`).
