# Pricing rules: discounts, promo codes, add-ons and the configurator

Nothing commercial is hard-coded in the web. The catalogue holds the list prices; everything below is set by staff in
**Nastavení systému → Slevy, závazky a promo kódy** and **Doplňky a konfigurátor** (`/sprava/nastaveni/integrace`),
stored in `system_settings` / the catalogue tables and read by the quote (`QuoteService`), the public data script
(`/surfaces/onhost-data.js`: `pricing`, `addons`, `tlds`, `pages.*.details|addons|builder`) and the cart seams
(`apps/surfaces/api/onhost-cart.api.js`, `onhost-svc-pages.api.js`).

## Rules the cart enforces

* **A longer commitment is not a discount.** 12- and 24-month terms bill 1 or 2 yearly periods at the list price. A
  percentage applies only where staff approved it — per product family, with an optional default for the rest
  (`PricingRules::commitDiscountPercent`). The prototype's implied −10 % / −18 % never appears.
* **Domains** are cart lines of their own: whole years, at least one, never a commitment discount. A TLD discount
  exists only when staff set one (registration / renewal / transfer percent, optional validity window, a label the
  customer sees). Promo codes reach domains only when they name the `domain` family.
* **Add-ons belong to a line.** Every cart line carries its own add-ons: the priced options of its product
  (switch, slider, choice — `product_options`) and the add-on products staff allow next to it (`addon_products`
  mapping; seeded defaults in `products.meta.addon_products`). An add-on product ordered next to a service is a
  child line (`config.parent_line_id`); the quote refuses add-ons the parent does not offer
  (`addon_not_applicable`) and fulfilment attaches the add-on service under the parent service.
* **Option prices are monthly per unit** (`price_per_unit_minor`); yearly periods carry twelve of them. Sliders
  price the part above the option's default (add-ons of fixed plans start at 0 = "extra"), the configurator
  product's sliders are absolute (`meta.entitlement.mode = absolute`) and the base plan covers the minimum.
* **Promo codes** are validated by `GET /v1/catalog/promo?code=` and applied by the quote to the families they
  name (`applies_to`, empty = all); usage is counted on placed orders.

## Staff API (`catalog.manage`)

| Call | Purpose |
| --- | --- |
| `GET /v1/staff/pricing` | commitment discounts, domain discounts, promo codes, products with their add-on mapping and options, add-on candidates, families, TLDs |
| `PUT /v1/staff/pricing/commit-discounts` `{default:{12,24}, families:{web:{12,24}}}` | approve percentages (0–90) |
| `PUT /v1/staff/pricing/domain-discounts` `{tld, register, renew, transfer, valid_from?, valid_to?, label?}` / `DELETE …/{tld}` | TLD discounts |
| `PUT /v1/staff/pricing/promo-codes` `{code, kind, value, currency?, valid_from?, valid_to?, max_uses?, applies_to[], first_period_only, state}` / `DELETE …/{code}` | promo codes |
| `PUT /v1/staff/pricing/addon-products` `{product_key, addon_products[]}` | which add-on products a product may carry |
| `PUT /v1/staff/pricing/options` `{product_key, key, kind, label{cs,en}, desc?, unit?, min?, max?, step?, default?, price_czk, price_eur?, choices?, entitlement?}` / `DELETE …/{product}/{key}` | priced options (per-item add-ons, configurator parameters) |

Every change goes through `CatalogCommand` (audited) and drops the generated data script, so the web follows on the
next request.

## What is paid for is what is delivered

A cart line carries a free-form `config`. The quote is where it becomes a commitment, so the quote keeps only what is
priced (`CatalogService::normalizeOptions`): options **this product sells**, a slider inside its range and on whole
steps, a select with a value the list offers, an add-on as a switch. The price (`configure`) and the delivered
resources (`ServiceService::applyOptions`) are both computed from that one reading, and the order item stores it — the
order itself says what was bought. `limits` and `entitlements` in a cart's `config` are dropped (they are the plan's),
an unknown `region` is refused (`region_unknown` — otherwise an order is paid and never provisioned), and a
`project_id` of another organization is ignored. Only an order placed by staff (`source` staff/cli) may carry its own
`limits` or a `placement_instance`. Before this rule a web hosting ordered with `mailboxes: 1000` paid for the hundred
the list allows and got a thousand, and a product was given options it does not sell for free.

## Versions of a plan (Brain card H01)

A plan is never edited. Quotes, orders, services and subscriptions point at the plan VERSION they were sold with, so
a change of limits or prices is a new version: new orders get it, everybody who bought keeps their version, limits and
renewal price. Page: **Nastavení systému → Tarify a verze** (`/sprava/nastaveni/tarify`).

* `GET /v1/staff/pricing/plans/{product}/{plan}/versions` — every version with its prices and **who is on it**
  (services, live subscriptions): the impact of a change before it is made.
* `POST …/versions` `{reason, entitlements?, limits?, features?, prices?[{currency, period, amount, renewal_amount?, setup?,
  monthly_cap?}], confirm_large_change?}` — publishes version N+1 from the one on sale. What is not mentioned is carried
  over, so a currency or a billing period can neither appear nor vanish by omission. `catalog.manage`, risk HIGH, fresh
  step-up, reason kept in the audit trail; finance gets a notification.
* `POST …/versions/{n}/activate` `{reason}` — puts an existing version (back) on sale: the rollback. Customers of the
  version in between keep it; version numbers are never reused.

Refusals (422): `plan_key_unknown` (a new entitlement key comes with the code that provisions it, not from a form),
`plan_value_invalid` (a number stays a number, a switch a switch), `price_period_unknown`, `price_invalid` (a paid
plan does not become free by a version — take it off sale or use a promo code), `price_change_large` (more than 50 %
either way needs `confirm_large_change`: a slipped decimal place is the usual way to a wrong price list),
`plan_version_unchanged`, `reason_required`.

The prices of an old version stay `active` on purpose — renewals and hourly rating of the services sold with it read
them. Do not retire them by hand. A promo price (`promo_amount_minor`) belongs to its version and is not carried over.
`CatalogSeeder` writes version 1 only and never moves `current_version`; it is not part of a deployment.

## The configurator ("Tarif na míru")

Product `web-custom` (family web, executor ISPConfig) has one base plan (`custom`, 49 Kč: 1 site, 5 GB, 3 mailboxes,
1 database, 7-day backups) and every parameter as an option: `sites`, `nvme_gb`, `mailboxes`, `databases`
(absolute sliders), `backup_days` (choice), `staging`, `ssh`, `waf_cdn`, `dedicated_ipv4`, `priority_support`
(switches). The web hosting landing and `/sluzba/web-hosting` render it from `pages.web-hosting.builder`; the cart
line carries `config.options`, the quote prices it from the unit prices and `ServiceService::applyOptions` turns the
choices into the service's entitlements (absolute sliders set, extras add, selects map through
`meta.entitlement.values`, switches set `meta.entitlement.value`).

## Guest checkout

A visitor without an account finishes the order with the details of step *Údaje*: `POST /v1/checkout/guest`
creates the user (random password) and organization, places the order through the command bus as that customer
(gateway or bank transfer only), starts the browser session and mails `GuestAccountNotification` with the order
number and a 48-hour "set your password" link. An e-mail that already has an account answers `409 account_exists`;
a retried request with the same `Idempotency-Key` returns the same order without a second account.

## Checks

* `tests/Feature/Orders/PricingRulesTest.php`, `tests/Feature/Http/PricingAdminTest.php`,
  `tests/Feature/Orders/GuestCheckoutTest.php`, `tests/Feature/Http/PublicPagesSeamTest.php`.
* Doctor: `no TLD sold below its cheapest cost price` still guards the list prices against the registrar costs
  (see `domain-registrars.md`); a domain discount is applied on top of the list price, so keep the margin in mind.

## Promo codes do what their form says (2026-09-20)

* **A code for a fixed amount is spent once per order.** It was applied to every line: "100 Kč off" took 100 Kč off each
  line, so a 500 Kč voucher on a cart of ten items was worth up to 5 000 Kč (proven by a test: 300 Kč instead of 100 Kč on
  three lines). The amount is now a budget of the order, spent line by line until it runs out, never below zero. A percent
  code stays a share of every line it applies to.
* **"Only the first period" means something when it is unticked.** The box (`promo_codes.first_period_only`) was stored and
  never read: renewals always came at the list price, so a customer promised a lasting discount renewed at full price. When
  the code lasts, a percent code takes its share of every renewal and a fixed one what that line got of it
  (`config.renewal_promo`, `renewal_promo_minor` on the order item; the subscription renews at `renewal_net_minor`).
  Domains are the exception by rule: a domain renews at the list price of its TLD on the day of the renewal.
* A plan change carries no discounts and spends none of a code. `prices.promo_periods` is not offered anywhere (nothing
  sets it); the introductory price of a plan (`promo_amount_minor`) covers the first billed period.

Tests: `tests/Feature/Orders/PromoCodeRulesTest.php`.
