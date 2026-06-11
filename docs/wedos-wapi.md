# WEDOS WAPI / VEDOS (domain registry layer)

## Phase 2: MOCK only

`App\Domains\Provisioning\Drivers\WedosMockRegistrar` implements
`DomainRegistrarInterface` with **zero network I/O**:

| method | mock behaviour |
|---|---|
| `checkDomain()` | deterministic: unsupported TLD → `unsupported_tld`; bad syntax → `invalid_syntax`; configured taken list (`onhost.cz`, `google.cz`, `seznam.cz`, `wedos.cz`) or SLD starting with `taken` → `taken`; otherwise available |
| `registerDomain()` | re-checks availability, returns `MOCK-WD-NNNNNN`, registered/expiry (+1 year) timestamps and default nameservers (`ns1/ns2.onhost.cz`); honours `simulate_failure` (one-shot) |
| `renewDomain()` | placeholder success (`placeholder: true`) |
| `updateNameservers()` | placeholder success |
| `getDomainInfo()` | placeholder array |

Mock knobs (optional, `config/provisioning.php` → `wedos.mock.*`):
`taken_domains`, `supported_tlds`, `nameservers`.

Where it is used in Phase 2:

- public `/domeny` availability search (rate-limited `throttle:domain-check`),
- checkout pre-check + in-action re-validation (`CreateOrderAction`),
- `RegisterDomainJob` → creates/updates the `DomainRegistration` row
  (`wedos_domain_id` set ⇒ registered; row visible in panel + admin).

## The real WAPI (later phase — requires explicit approval)

- JSON API (`https://api.wedos.com/wapi/json`), auth = login + SHA1 password
  hash + hour-based token; **quotas: 1000 req/h total, 100 req/h for
  domain-check/create/transfer-check** — config already carries the limits;
  the real client must budget them (cache checks, queue registrations).
- Async commands (domain-create returns a WAPI request id → poll) map onto
  `ProvisioningTask.external_request_id`.
- `WAPI_TEST_MODE=true` keeps even the real client on the WEDOS test
  endpoint; production credentials never enter `.env.example`.
