# Subreg.CZ (registrar)

**Files:** `providers/Subreg/SubregSoapGateway.php`, `SubregErrorMap.php`, `SubregRegistrarProvider.php` ·
**Contract test:** `tests/Contract/SubregContractTest.php` · **Manual:** https://subreg.cz/manual/ · **WSDL:** https://subreg.cz/wsdl

Subreg is the second registrar behind ONhost domains (next to WEDOS). It is used **only for domain
registration, editing and management** — the Web/World hosting families of the API are refused by the
gateway allow-list. Customers never see the registrar behind a domain; the console shows it in
*Nastavení systému → Registrátoři domén*.

## Gateway

* Document/literal SOAP as defined by the WSDL: `POST https://subreg.cz/soap/cmd.php?soap_format=1`,
  `SOAPAction: "http://subreg.cz/wsdl#<Function>"`, request body `<ns:<Function> xmlns:ns="http://subreg.cz/types">…`,
  response `<Function_Container><response>{status, data, error{errormsg, errorcode{major, minor}}}</response>`.
  Envelopes are hand-built (no `ext/soap`), responses are folded into arrays (repeated elements → lists).
* `Login` once per session; the `ssid` is cached per instance (`onhost.subreg.session_ttl_seconds`) and re-issued once on
  `500/101 You are not logged`.
* Allow-list: `Login, Check_Domain, Info_Domain, Info_Domain_CZ, Domains_List, Set_Autorenew, In_Subreg, Make_Order,
  Info_Order, Cancel_Order, Create/Update/Info_Contact, Contacts_List, Check_Object, Info_Object, Get_Pricelist, Pricelist,
  Prices, Get_TLD_Info, TLD_List, Get_Credit, Get_Accountings, POLL_Get, POLL_Ack`; order types `Create_Domain,
  Renew_Domain, Transfer_Domain, Modify_Domain, ModifyNS_Domain, Restore_Domain, Delete_Domain, Transfer*_Domain,
  Create/Update/Transfer_Object`.
* Token bucket `subreg:<instance>` (`onhost.subreg.limits`), TLS pinning through `TlsOptions` like every adapter,
  password/ssid/AUTH-ID never logged (the HTTP client records a redacted summary only).
* Sandbox: instance option `demo: true` (or base URL `demoreg.net`) switches to `https://demoreg.net/soap/cmd.php?soap_format=1`.
  Subreg has no per-request test flag, so a **test-mode registration is refused on a production instance**; the selector
  skips such instances while `WEDOS_TEST_MODE=true`.

## Mapping to the registrar contract

| Contract | Subreg |
| --- | --- |
| `checkAvailability` | `Check_Domain` (`avail`, live price incl. premium flag, TMCH claim ⇒ not registrable) |
| `tldPeriods` | `Get_TLD_Info.periodsCreate` |
| `domainInfo` | `Info_Domain` → statuses normalised to `active / pending / expired / redemption`, `exDate`, `hosts`, `options.nsset/keyset/dsdata`, `authid` |
| `listDomains` | `Domains_List` (name, expiry, autorenew) |
| `register` | `Make_Order Create_Domain` {period, registrant{id}, contacts{admin}, ns{hosts|nsset}, params (DNSSEC)} |
| `renew` | `Make_Order Renew_Domain` {period, curExpDate from Info_Domain} — `506/1008` means the expiry already moved |
| `transferCheck` / `transferIn` | `Check_Domain` + `In_Subreg`; `Make_Order Transfer_Domain` {authid, new{registrant, admin, ns}} |
| `sendAuthInfo` | `Info_Domain.authid` returned as `delivery: inline` — ONhost stores it encrypted (`domain_transfer_secrets`, direction `out`) and shows it once to the step-up-verified user |
| `updateNameservers` | `Make_Order ModifyNS_Domain` {ns{hosts|nsset}} |
| `updateKeyset` | `Make_Order Modify_Domain` params: `keyset` handle, `keydata[]` (DNSKEY) or `dsdata[]` (DS) |
| `createContact` | `Create_Contact` (E.164 phone `+420.123456789`, `params.vat/ident_type=ico/ident_number` for .cz/.ee) → `contactid` (`G-…`) |
| `createNsset` | `Check_Object nsset` then `Make_Order Create_Object` {type nsset, registry CZ-NIC, params[{tech{id}, hosts[]}]} |
| `creditInfo` / `accountMovements` | `Get_Credit`, `Get_Accountings` |
| `costPrices` (`RegistrarPricingProvider`) | `Prices` per TLD (register/renew/transfer/restore) in the account currency from `Get_Credit` |
| `pollRequest` / `pollAck` | `POLL_Get` (+ `Info_Order` for the domain and order type) / `POLL_Ack` |

Every order is asynchronous: `Make_Order` returns an `orderid`; the adapter looks at `Info_Order` once and returns
`completed` when the order already finished, otherwise an `AsyncHandle(kind subreg_order, meta.order_id)`. `awaitStatus`
polls `Info_Order` (`Completed` → `Info_Domain` truth; `Failed/Cancelled/…` → failed) — a create is never resent (S34).

## Error codes (`SubregErrorMap`)

| major / minor | Meaning | Taxonomy |
| --- | --- | --- |
| 500/101 | not logged | `AUTH` (`NOT_LOGGED` → automatic re-login) |
| 500/105 | IP not allowed | `AUTH` (`IP_NOT_ALLOWED`) — allow the control-plane IP in the Subreg account |
| 500/other | login problems | `AUTH` |
| 501/1004, 501/1007, 503/1004, 507/1009, 506/1007 | object / domain not on the account | `NOT_FOUND` |
| 502/* | registry timeout / system error | `TRANSIENT` (retry after 300 s) |
| 506/1008 | curExpDate ≠ exDate (renewal already applied) | `CONFLICT` (`EXPIRY_MISMATCH`) |
| 507/1010 | object exists | `CONFLICT` (`ALREADY_EXISTS`) |
| 600/* | an order for the domain is already pending | `CONFLICT` (`ORDER_ALREADY_PENDING`) |
| 602/*, "credit" in the message | billing / credit | `CAPACITY` (`INSUFFICIENT_REGISTRAR_CREDIT` → `registrar.credit.low`) |
| 503–524, 601, 603–606 | invalid values / missing fields | `VALIDATION` |

## Onboarding

`POST /v1/staff/integrations` (or the settings page) with `provider: subreg`, credentials `login` + `password`
(the API user of the Subreg account, API access enabled, control-plane IP allowed). Probe = `Login` + `Get_Credit`.
Then *Registrátoři domén → Aktualizovat ceníky z API* (or `php artisan onhost:registrar:costs`) fills the wholesale
price book; the daily schedule keeps it fresh.
