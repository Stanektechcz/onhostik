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
  over, so a currency or a billing period can neither appear nor vanish by omission. `catalog.manage`, a fresh step-up
  **and a second person** (owner decision 13, docs/runbooks/approvals.md), reason kept in the audit trail; finance gets a
  notification. A `null` takes a key off the new version.
* `POST …/versions/{n}/activate` `{reason}` — puts an existing version (back) on sale: the rollback. Customers of the
  version in between keep it; version numbers are never reused.

Refusals (422): `plan_key_unknown` (a new entitlement key comes with the code that provisions it, not from a form),
`plan_value_invalid` (a number stays a number, a switch a switch), `price_period_unknown`, `price_invalid` (a paid
plan does not become free by a version — take it off sale or use a promo code), `price_change_large` (more than 50 %
either way needs `confirm_large_change`: a slipped decimal place is the usual way to a wrong price list),
`plan_version_unchanged`, `reason_required`.

The prices of an old version stay `active` on purpose — renewals and hourly rating of the services sold with it read
them. Do not retire them by hand. A promo price (`promo_amount_minor`) belongs to its version and is not carried over by a
staff publish; a catalogue revision (below) carries it over unchanged, because a revision changes no price.
`CatalogSeeder` writes version 1 only and never moves `current_version`; it is not part of a deployment (only a fresh
`install.sh` seeds it). It used to rewrite version 1 — entitlements, features and prices — on every run, i.e. the version
customers hold; since TASK-0022 it leaves a version alone as soon as a service or a subscription points at it.

## Catalogue revisions (2026-09)

A change the code base decides — a promise the platform turned out not to keep — reaches a running catalogue as new plan
versions, never as an edit of the seeder or a migration. The revision is written down in
`domains/Catalog/CatalogRevisions.php` and published by an operator:

```bash
php artisan onhost:catalog:revise            # dry run: plans, keys each loses, who keeps the old version, promo prices carried over
php artisan onhost:catalog:revise --apply    # publishes (asks first; --yes for a scripted window)
```

Each plan is one `CatalogCommand plan.publish` through the bus (system actor `cli:catalog:revise`, bound to the version it
was previewed against): audited, finance gets one *Nová verze tarifu* notification per plan, the prices of the current
version are carried over unchanged (a revision cannot pass prices or features), everybody on an older version keeps it.
The command is stateless — what is pending is read from the current versions — so a key staff already removed is skipped,
a second run prints *Nothing pending*, and a rollback to an old version makes it pending again. A refused plan does not
undo the others; run it again after fixing the cause. Shell access to the server is the gate (no second person exists for
the system actor); the content of a revision is reviewed as code.

**`2026-09-honest-promises`** (owner decisions 2, 4, 6 and 18):

| Plan | New version without | Also |
| --- | --- | --- |
| `database/db-s`, `database/db-m` | `pitr_days`, `connections` | product description no longer says PITR (`product.describe`, a step-up operation) |
| `mail/mail-enterprise` | `dedicated_outbound_ip` | |
| `wordpress/managed-woo` | `dedicated_db` | `backup_frequency` `1h` → `hourly` |
| `eshop/shop-growth` | — | `backup_frequency` `1h` → `hourly` |
| `eshop/shop-peak` | `dedicated_db` | |

Without a new version: `products` on the e-shop plans is fair use (decision 5, worded *Doporučeno do N produktů*),
`cron_concurrency` is shown as *Naplánované úlohy* / *N naplánovaných úloh* (decision 11), and a new managed database
instance never claims `pitr` (decision 2; an existing row keeps its flag). Before `--apply`, read the dry run: a promo
price on the current version is carried into the new one unchanged (`keep_promos`, CLI revisions only — the console API
does not accept it; ending an introductory price would be a price rise nobody approved), and a features line staff wrote that
still names PITR, connections or a dedicated IP/DB is only reported — edit it in *Tarify a verze*. Afterwards
`onhost:doctor` shows *every catalogue revision is applied* OK and lists, under *no customer holds a version promising an
unkept number*, the old versions customers still hold (support answers them; nothing about them changes).

**`2026-09-shared-php-workers`** (owner decision 7, TASK-0027): every plan whose current version sells
`php_workers_dedicated` on a product whose panel runs one PHP pool for the whole node (`PlacementRules::undelivered()`:
today `eshop/shop-peak` on aaPanel) gets a new version without it — found at run time (`drop_undelivered`), not from a fixed
list. The price list then says *Sdílené PHP workery* instead of *24 PHP workerů (dedikované)*; `php_workers` itself stays.
`web-hosting/profi` keeps the promise (ISPConfig, one PHP-FPM pool per site). Versions customers hold keep it; the services
on them are listed by `onhost:capacity:basis` and the doctor's capacity rows (never moved). Run together with
`2026-09-honest-promises`, `shop-peak` goes v1 → v2 (−`dedicated_db`) → v3 (−`php_workers_dedicated`): each revision reads
what is pending just before it publishes, on top of the version the one before it made.

**`2026-09-limit-raise`** (owner decision 8): creates the product `limit-raise` (family `addon`, no executor, `meta.listed:
false`) when the catalogue does not have it — a revision's `create` list, one `CatalogCommand product.create` per product
(only a product `CatalogRevisions::PRODUCTS` defines; four eyes in the console, the system actor on the CLI). It has **no plan
and no price**: a raise is priced by `LimitRaiseLine` at the parent product's option price per unit × units × months of the
service's period (no commitment, promo, loyalty or regional adjustment), so the option unit prices in *Slevy a doplňky* are
now also the raise prices. It is never on the price list and never a cart upsell. Staff order a raise as an assisted order
(`items: [{product_key: limit-raise, config: {limit_raise: {service_id, metric, units}}}]`); customers only once
`ONHOST_LIMIT_RAISE_CUSTOMER_ORDERS=true`; at no charge only with a second person (docs/runbooks/approvals.md). Only a number
`MetricRegistry` marks enforced for the family and priced by an option can be raised; no cloud, and no vCPU/RAM/disk of a
game server in v1. The service must run, renew (`limit_raise_parent_ending` when its subscription ends at the period end or
does not auto-renew: the raise would be paid for months the service never runs) and still do so when the order is paid —
a raise paid after the service was cancelled is not delivered, the line fails and the settlement returns the money to
credit. The option's `max` counts raises already ordered and not yet delivered. A raise whose service ended without taking
it along stops renewing (`SubscriptionService::tick`) and the doctor names it. Unpaid, a raise is kept through the dunning
suspension stage (a payment then keeps it) and ends at the termination stage, like every unpaid service. **No refund:** a
prepaid raise that ends early — its service terminated, or the customer ending it — is not credited for the unused days. `onhost:limit-raise list` shows the raises, `onhost:limit-raise push {raise} --apply` repeats a panel push
that was refused; the doctor row *every limit raise is billed or approved* names both kinds of problem.

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

## What a plan may promise (2026-09-21)

**The hole.** A plan version carries two machine-read bags, `entitlements` and `limits`, and the platform is supposed to
enforce, apply or measure every number in them. Five numbers on plans that were on sale were read by **no code at all**:
`cpu_seconds_per_day` (web hosting, Managed WordPress, e-shop, custom), `db_connections` and `outbound_mail_per_hour`
(web hosting), `pps_limit` (VPS). The customer never saw them either — `CatalogPresentation` has no wording for them, so
they only looked like limits in the plan editor. `inodes` was sold, both panels count the files of a site
(`inodes_used` in the quotas of `AaPanelTools` and `IspConfigTools`), and nothing ever compared the two. The transfer was
sold as a sentence (`traffic: 'fair-use 500 GB/měs'`) while `UsageWatch` reads the number `traffic_gb` — so the transfer
limit of a hosting plan never applied to anything.

**The rule** (`Domain\Catalog\PlanPromises`):

* a number in `entitlements` or `limits` is **read by code outside the catalogue**, or it is a declared **fair-use**
  promise (`PlanPromises::FAIR_USE`) kept by how the platform is operated rather than by a setting — and then the price
  list says "fair use" where the customer reads it (`relay_per_hour`, `capacity_gbps`, `pops`, `io_class`, `slots`, …);
* the guard test scans the source (`tests/Feature/Catalog/PlanPromisesTest.php`) and `onhost:doctor` reports plans on
  sale that break it, because a version published in the administration can put a number back at any time;
* `traffic_gb` and `inodes` are now measured against what the panel reports, so the customer hears about the transfer and
  the file count on the same path as the disk (85 % warn, 95 % critical, one message per level and day);
* **a number can leave a plan**: `plan.publish` with a `null` value removes the key. The schema could only grow before —
  a key nothing applied had no way off the price list. A key the plan never had is still refused.

After a deploy the seeded plans are only the starting point: plan versions already published on the server keep whatever
they carried, so publish a new version (reason: "nikdo to neuplatňuje") to drop the old numbers. The doctor lists them.

**"Read by code outside the catalogue" is not trusted by name alone any more (2026-09-25, TASK-0017, audit §5ad).**
The price list itself (`CatalogPresentation`) names every key it sells, so the plain text-scan rule above was
satisfied by the very file it should have caught: `products`, `connections` and `dedicated_outbound_ip` passed
because only the price list ever said the word. The presentation/prototype-surface files
(`app/Http/Support/CatalogPresentation.php`, `app/Http/Support/SurfaceRenderer.php`) are now excluded from the scan,
and every **numeric** promise (an int, a float, or a numeric string such as `"500"` — `is_numeric()`, not the
narrower `is_int()`/`is_float()` this used to check) is instead checked against a hand-verified table,
`domains/Services/Metering/MetricRegistry.php`: what actually measures or enforces each entitlement/limit key today,
with a source cited for every row that claims one. A non-numeric capability flag keeps the original text-scan rule.

The registry check is **scoped to the plan's own product family** (`Plan → Product::family`, looked up from the
version being checked): a row verified only for `families => ['mail']` must not pass for a web-hosting plan that
happens to sell the same key name — `MetricRegistry::isKept($key, $family)` fails the family check before it ever
looks at `status`. Family-scoping this way surfaced two rows that were simply incomplete (their real enforcement
does reach a family the row hadn't listed yet — `nvme_gb` on the managed-database family and `mailboxes` sold
through the `mail-hosting` add-on — both extended once the enforcing code was confirmed) and one genuinely new,
honest gap (`backup_days` sold on mail plans and on a managed database, neither of which `BackupScheduler` ever
schedules). `vcpu` on game plans is enforced: provisioning raises `cpu_pct` to at least `vcpu` × 100.

A key that is neither measured, enforced, fair use, nor read may still be listed once, honestly, in
`PlanPromises::KNOWN_GAPS` — a ratchet that may only shrink (fixing a gap without removing the line, or a new gap
appearing without one, both fail the guard test) — currently 8 entries. The boolean `dedicated_outbound_ip` and
`dedicated_db`, the numeric `pitr_days` and `connections` left it with the revision `2026-09-honest-promises` (the owner
decided they are not provided), `products` became fair use, and `php_workers_dedicated` left it once placement binds a
dedicated-PHP web plan to ISPConfig, one PHP-FPM pool per site (`PlacementRules`, decision 7). Its `MetricRegistry` row is
kept for the web family only; the managed-family promise (`eshop/shop-peak`) is retired by the revision
`2026-09-shared-php-workers`, and `PlanPromisesTest` holds every plan on sale to that row once the revisions are applied
(a capability flag otherwise passes the text scan as soon as any code names it). `onhost:doctor` shows the tracked list as a standing WARN
(`catalog: no known metering gap`) and any *new*, untracked gap as a production FAIL
(`catalog: the metering gap ratchet is not growing`) — except a key a revision not yet applied still has to remove,
which is the WARN `catalog: every catalogue revision is applied` naming `onhost:catalog:revise`.

Tests: `tests/Feature/Catalog/PlanPromisesTest.php`, `tests/Feature/Platform/DoctorCommandTest.php`.
