# WEDOS WAPI (registrar) and WEDOS Zone (fallback DNS)

**Files:** `providers/Wedos/WapiGateway.php`, `WedosErrorMap.php`, `WedosRegistrarProvider.php`,
`WedosZoneDnsProvider.php` · **Contract test:** `tests/Contract/WedosContractTest.php`

## Gateway

* Endpoint `https://api.wedos.com/wapi/json`, form field `request` = JSON `{user, auth, command, clTRID, data}`.
* `auth = sha1(user + sha1(password) + HH)` where `HH` is the current hour in Europe/Prague; the gateway
  refuses to send when the local clock is more than `WEDOS_CLOCK_MAX_OFFSET_SECONDS` (5 s) off the registrar clock as seen in HTTP `Date` headers (clock gate, S33) to avoid auth failures.
* Allow-listed commands and a per-command data schema; unknown keys are rejected before sending.
* Token buckets: `wapi:all` 1000/h and `wapi:domain` 100/h with a 15 % reserve kept for renewals; a burst of
  2xxx/4xxx responses opens the invalid-request breaker (protects the account from lock-out).
* `clTRID = onhost:v4:<command>:<operation ulid>` for end-to-end tracing; every request/response is logged
  in `registrar_operations` (password/auth redacted).

## Result codes (`WedosErrorMap`)

| Code | Meaning | Taxonomy |
| --- | --- | --- |
| 1000 | OK | completed |
| 1001 | accepted, asynchronous | async → poll `domain-info` |
| 2001–2013 | auth / user errors | `AUTH` |
| 3201, 3204, 3205, 3206 | domain not available / not renewable / wrong state | `CONFLICT` (`DOMAIN_NOT_AVAILABLE`) |
| 3002, 2283 | credit / limit exhausted | `CAPACITY` (→ `registrar.credit.low`) |
| 4205, 4207, 4218 | temporary registry errors | `TRANSIENT` (retry with backoff) |
| other 4xxx/5xxx | unexpected | `UNKNOWN` (no retry, human) |

## Registrar operations

`domain-check`, `domain-create` (contacts, nsset, DNSSEC keys), `domain-renew`, `domain-info`,
`domain-update-ns`, `domain-transfer` (AUTH-ID in), `domain-send-auth-info` (AUTH-ID out, mailed by the registry),
`domain-update-dnssec`, `contact-create/-update`, `nsset-create`, `credit-info`, `poll-req`/`poll-ack`
(registrar notifications processed by `RegistrarPollWorker`).

## WEDOS Zone

Same two-phase model as the control plane: `dns-row-add/-update/-delete` stage rows, `dns-domain-commit`
publishes. `WedosZoneDnsProvider` implements `ZoneDnsProvider` so a zone can be switched from PowerDNS to
WEDOS Zone (or used as secondary) without changing the customer's records.

Verified against the live WAPI on 2026-09-07 (account `wedos-main`):

* WAPI must be called over **IPv4** — IPv6 clients get `302 → https://wedos.com/404.html` or `2051 Access not allowed`;
  the gateway sets `force_ip_resolve` (`WEDOS_FORCE_IP_RESOLVE=v4`) and the IPv4 egress goes into the WAPI allow-list.
* `domain-check` answers `1000` with `data.name` only for a **free** name and `3201 Domain is registered` for a taken
  one; `checkAvailability()` maps 3201 to `available: false, reason: registered`.
* Reads (`domain-info`, `domains-list`, `credit-info`, `contact-info`, `nsset-info`, `poll-req/-ack`, `account-list`)
  are sent **without** the `test` flag — WAPI returns empty `data` for test-mode reads; `credit-info` returns
  `{amount, currency}`.
* The clock gate is measured from HTTP `Date` headers (1 s granularity), so the tolerance is 5 s.

WEDOS publishes no wholesale price API: its cost prices live in the registrar price book (`registrar_tld_costs`, source
`manual`/`seed`) maintained in *Nastavení systému → Registrátoři domén*; `RegistrarSelector` compares them with the other
registrars (Subreg pulls its prices from `Prices`) and registers each new domain with the cheapest one.

Never call WAPI from tests with live credentials; the contract test asserts encoding, auth, rate limiting and
error mapping against `Http::fake()`.
