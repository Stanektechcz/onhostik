# Domain registrars: price book and cheapest-registrar selection

ONhost sells domains at its own prices (`domain_prices`, per TLD and currency) and buys them from whichever
connected registrar is cheapest for the TLD. Nothing outside the console ever names the registrar.

## Model

| Piece | Where | Notes |
| --- | --- | --- |
| Registrar instances | `provider_instances` with capability `registrar` (`wedos`, `subreg`, …) | onboarded in *Nastavení systému → Integrace providerů*; every adapter implements `RegistrarProvider` |
| Wholesale prices | `registrar_tld_costs` (registrar × TLD, currency, register/renew/transfer/restore, `source` api/manual/seed, `fetched_at`) | `RegistrarPricing::refresh()` pulls them from registrars that implement `RegistrarPricingProvider` (Subreg `Prices`); the others are entered by staff |
| Selection | `RegistrarSelector::choose(tld, operation, testMode)` | pinned TLD (`tld_policies.registrar_provider` ≠ `auto`) → that registrar; else lowest cost in CZK (`onhost.domains.registrar.fx_czk` converts EUR/USD); tie or no price data → `onhost.domains.registrar.preference` order; registrars that cannot honour test mode are skipped while `WEDOS_TEST_MODE=true` |
| Holding registrar | `domains.registrar_provider` | renewals, nameserver changes, DNSSEC and AUTH-ID requests always go to the registrar that holds the domain; the daily reconcile corrects the column when a listing shows the domain elsewhere (`domain.reconcile.registrar_changed`) |
| Contacts | `registrar_contacts.registrar_provider` + `source_contact_id` | handles are registrar-specific: the first use at another registrar creates a linked copy (`RegistrarContact::siblingFor`) |
| Audit | `domains.meta.registrar_selection` | provider, reason (`cheapest`, `tie_preference`, `pinned`, `no_cost_data`), cost, every candidate — the margin ledger reads it |

Example: `.cz` costs 165 Kč at WEDOS and 140 Kč at Subreg → `testujeme.cz` is registered at Subreg
(`tests/Feature/Domains/RegistrarSelectionTest.php`).

## Operating it

* **Settings page** *Nastavení systému → Registrátoři domén*: the matrix (ONhost price, each registrar's cost, winner,
  margin), *Aktualizovat ceníky z API*, the manual cost form, a per-TLD pin.
* **API** (`provider.instance.manage`): `GET /v1/staff/registrars`, `POST /v1/staff/registrars/costs/refresh`,
  `PUT /v1/staff/registrars/costs {registrar_provider, tld, currency, register, renew, transfer, restore}`,
  `PUT /v1/staff/registrars/policy {tld, registrar_provider|auto}`.
* **Subreg login**: the API signs in as `<api user>#<account login>` (e.g. `onhost_api#Niasee`) with the API user's password;
  `SUBREG_MAIN_LOGIN` must carry that full form (a bare `onhost_api` or `Niasee` answers `500.104 Incorrect username or
  password`). The instance's `base_url` is `https://subreg.cz` (no `demo` option) for production.
* **Public price lists**: WEDOS and Subreg publish their retail price lists on the web; `onhost:registrar:scrape-prices
  [--registrar=wedos|subreg] [--file=path.html]` (weekly, Monday 04:10; button *Načíst veřejné ceníky* on the settings
  page; `POST /v1/staff/registrars/costs/scrape`) parses them into the price book as source `scrape` (retail price kept in
  `meta.retail`, promo and minimum years flagged). API and manual rows are never overwritten by a scrape; scraped rows
  are refreshed by a later API refresh. Event `registrar.costs.scraped`.
* **Schedule**: `onhost:registrar:costs` daily 03:40 (price APIs), `onhost:registrar:scrape-prices` weekly,
  `onhost:registrar:credit` hourly (every registrar), `onhost:registrar:poll` every 5 min (every registrar's
  notification queue), `onhost:registrar:reconcile` daily.
* **Doctor**: `registrar available` (blocking), `registrar cost prices known for every TLD` (warning: missing or
  stale rows) and `no TLD sold below its cheapest cost price` (warning: the ONhost CZK price is under the cheapest
  registrar's cost — raise the price or pin a registrar) in `php artisan onhost:doctor`.
* **Queue**: registrar sagas run on `provider-registrar` (was `provider-wedos`) — update the queue worker list.

## Adding a registrar

1. Adapter in `providers/<Vendor>/` implementing `RegistrarProvider` (+ `RegistrarPricingProvider` when a price API
   exists); `capabilities()` must state `test_mode` (can it dry-run?) and `pricing`.
2. Register it in `PlatformServiceProvider::ADAPTERS`, `ProviderInstanceService::CREDENTIALS` / `CAPABILITIES`
   (`registrar: true`), a contract test with `Http::fake()`, a page in `docs/provider-adapters/`.
3. Onboard an instance; the selector, poll worker, credit monitor and reconcile pick it up automatically. Add its
   preference position to `ONHOST_REGISTRAR_PREFERENCE` if ties should favour it.
