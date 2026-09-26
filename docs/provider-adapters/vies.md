# VIES (EU VAT number check)

**Files:** `providers/Contracts/VatNumberValidator.php`, `providers/Contracts/VatCheckResult.php`,
`providers/Vies/ViesVatNumberValidator.php`, `providers/Vies/DisabledVatNumberValidator.php` ·
**Contract test:** `tests/Contract/ViesContractTest.php` · **Callers:** `domains/Tax/VatNumberChecks.php` (only) ·
**Operations:** [../runbooks/vat-and-vies.md](../runbooks/vat-and-vies.md) · **Task:** TASK-0031 (D31.1)

VIES is the European Commission's register of VAT numbers. The platform asks it whether a VAT ID a customer gave is
registered, because reverse charge (an invoice without VAT to a business in another member state) may rest only on a
verified number. Before TASK-0031, `domains/Tax/ViesClient.php` made the HTTP call from the domain layer and had no caller
at all. It is gone. VIES is now a provider behind a contract, called through `ProviderHttpClient` like every other vendor.

The switch is `ONHOST_VIES_ENABLED` (`onhost.vies.enabled`), **off by default**. While it is off, the container resolves the
contract to `DisabledVatNumberValidator`, which answers `unknown` (not retryable) without a request. The binding is made
per call (`PlatformServiceProvider`, TASK-0031 block), so switching it needs no restart.

## Request

`POST https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number` (`onhost.vies.endpoint`, env `VIES_ENDPOINT`),
JSON body:

| Key | Value |
| --- | --- |
| `countryCode` | the member state of the number, **EL for Greece** (a number typed as `GR…` is stored and sent as `EL…`) |
| `vatNumber` | the number **without** its country prefix |
| `requesterMemberStateCode` | our own member state (`CZ`), from `ONHOST_VIES_REQUESTER_VAT_ID` |
| `requesterNumber` | our own VAT ID **without** the prefix |

The requester pair is sent only when `ONHOST_VIES_REQUESTER_VAT_ID` (falling back to `ONHOST_VAT_ID`, then `ONHOST_DIC`)
is a well-formed VAT ID. Without it VIES still answers, but without a **consultation number** (`requestIdentifier`). That
number is the evidence a tax audit asks for when an invoice carries no VAT, so the doctor reports a missing or non-Czech
requester (row "VIES checks are on and the requester VAT ID is set"). The optional `trader*` fields are never sent: the
check is about the number, not about matching a name.

**XI (Northern Ireland)** is not used. VIES knows XI numbers only for trade in goods; ONhost sells services, and an XI
prefix is not an EU member state for this check (`VatNumber::EU_MEMBERS`). Such a number is skipped like any non-EU number:
no call, no row, no event.

Timeouts: `ONHOST_VIES_TIMEOUT` (8 s) for the queued check, the operator command and the re-check;
`ONHOST_VIES_CHECKOUT_TIMEOUT` (5 s) for the quick check before a quote; connect timeout 3 s. The request is idempotent
(a read).

## Answer

Two shapes arrive, and the adapter reads both:

* **A verdict**, HTTP 200: `countryCode`, `vatNumber`, `requestDate`, `valid` (boolean), `requestIdentifier`, `name`,
  `address`, `trader*Match`. `name`/`address` of `---` mean the member state does not disclose them and are stored as
  null. Some answers also carry `userError` (`VALID`, `INVALID`, or a fault code such as `MS_UNAVAILABLE`); a fault code
  in `userError` wins over `valid`.
* **A refusal**, HTTP 400 or 500 and sometimes 200: `{"actionSucceed": false, "errorWrappers": [{"error": CODE,
  "message": text}]}`. The codes are the SOAP fault names.

| VIES says | Result | Retryable | Breaker |
| --- | --- | --- | --- |
| `valid: true` | `valid` + consultation number, name, address, request date | — | success |
| `valid: false`, or `userError: INVALID` | `invalid` | — | success |
| `INVALID_INPUT` (the number cannot be one) | `invalid` (`invalid_input`) | — | success |
| `MS_UNAVAILABLE`, `TIMEOUT`, `MS_MAX_CONCURRENT_REQ(_TIME)` | `unknown` | yes | — (one member state is not VIES) |
| `SERVICE_UNAVAILABLE`, `GLOBAL_MAX_CONCURRENT_REQ(_TIME)` | `unknown` | yes | failure |
| `INVALID_REQUESTER_INFO` (our own requester details are wrong) | `unknown` | no — fix the configuration | — |
| `VAT_BLOCKED`, `IP_BLOCKED` | `unknown` | no — an operator matter | — |
| any other code | `unknown` (code kept, cut to 40 characters) | yes | — |
| HTTP 429 | `unknown` (`RATE_LIMITED`) | yes | — |
| HTTP 5xx without a code | `unknown` (`HTTP_<status>`) | yes | failure |
| anything else nobody documented | `unknown` (`UNEXPECTED_ANSWER`) | yes | — |
| no answer (connection failed, breaker open) | `unknown` (the taxonomy code) | yes | counted by the client |

`unknown` is never a verdict. The caller writes nothing, publishes nothing and keeps what it knew before. A customer is never
told that a number is wrong because a member state's database was down. `VatCheckResult::retryable` only tells the queued job
whether asking again later can help (`CheckVatNumber`: after 1 min, 10 min, 1 h).

A malformed number with an EU prefix (wrong length or characters for its member state) is recorded `invalid` from its shape
alone (`source: format`) by `VatNumberChecks`, **without a call**. A number without an EU prefix, or an organization outside
the EU, is never asked and never called invalid.

## Transport

* `ProviderRequest` with provider, instance and **bucket `vies`**. The breaker is keyed by `vies`. No quota is configured for
  the bucket today (`configureBucket` is not called), so VIES's own concurrency limits answer as the `*_MAX_CONCURRENT_REQ`
  codes above. The operator command pauses 500 ms between two checks (`--pause-ms`).
* `judgedByCaller: true`: the adapter records exactly one breaker verdict per answer (the table above). A refused number is
  VIES working, and one member state's outage is not VIES being down.
* **`secretResponse: true`**: the answer names the trader (company name and address), so the `provider_calls` row keeps the
  method, path, status, duration and error class, never the body. The name and address are kept only as evidence in
  `vat_validations` (and stripped from the command audit). They never enter an event or a log.
* Never called from a domain, a controller or inside a bus transaction. `VatNumberChecks` asks first and then records the
  verdict through `RecordVatCheckCommand` (system actor only).

## Contract test

`tests/Contract/ViesContractTest.php` (`Http::fake()` + `Http::preventStrayRequests()`, never a live call) pins:

* the request body (country code, number without prefix, requester pair; EL for Greece);
* valid with consultation number, name and address; an undisclosed name (`---`);
* invalid, and `INVALID_INPUT` as invalid;
* `MS_UNAVAILABLE`, `TIMEOUT`, `SERVICE_UNAVAILABLE`, `GLOBAL_MAX_CONCURRENT_REQ` and `MS_MAX_CONCURRENT_REQ` in both
  shapes (`errorWrappers` with HTTP 500, and `userError` in a 200) as `unknown` retryable;
* `INVALID_REQUESTER_INFO`, `VAT_BLOCKED`, `IP_BLOCKED` (`errorWrappers`, HTTP 400) as `unknown` not retryable; a failed
  connection as `unknown` retryable;
* a malformed number without a request; the `provider_calls` row without the trader's name;
* an open breaker without a request; the switch off resolving to `DisabledVatNumberValidator`.

Every feature test that switches VIES on also calls `Http::preventStrayRequests()` itself (`tests/Pest.php` covers
`tests/Contract` only).
