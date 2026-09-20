# Security boundaries — the rules the customer-facing edge keeps

A review of the order, payment, egress and write paths (2026-09-20) found and closed the holes listed here. Each rule
has a test that fails on the old code. When adding an endpoint or a parameter, check it against this page first.

## 1. A customer's words never reach an internal parameter bag unfiltered

Workflows and services are called both by the platform (trusted callers) and from the customer API. Their parameters
live in one array, so everything the customer sends has to pass an **allow-list at the boundary**.

| Boundary | Rule | Code | Test |
| --- | --- | --- | --- |
| Core service actions (`power`, `backup`, `restore`, `terminate`, …) | only the listed keys survive; `resize` is refused (the size follows the plan) | `Services\CustomerActionParams::filter()` — used by the API, stored action hooks (create **and** run) and Discord | `tests/Feature/Services/CustomerActionParamsTest.php` |
| Feature actions | parameters are rebuilt from scratch | `ServiceService::featureParams()` | the feature tests |
| Cart line `config` | options normalized to what the product sells, within range; `limits`/`entitlements` dropped; unknown `region` refused; foreign `project_id` ignored | `CatalogService::normalizeOptions()`, `QuoteService`, `ServiceService::create()` | `tests/Feature/Orders/PaidForIsWhatYouGetTest.php` |
| Tax treatment and price region | facts of the organization, never of the request; a guest never claims a verified VAT number | `QuoteService::quote()`, `CartController::quote()` | same file |
| Custom vhost directives | judged statement by statement: allow-list for nginx, deny-list for Apache, no inline `#`, Apache line continuations joined | `Services\Web\CustomDirectives` | `tests/Unit/CustomDirectivesTest.php` |

## 2. Destinations a customer names are public, pinned and not redirected

The control plane sits in the management network. `Platform\Http\EgressGuard` resolves the name (every address must be
public — no loopback, private, link-local, CGNAT, multicast; local names such as `localhost`, bare labels, `*.internal`,
`*.mgmt` are refused by name), pins the connection to the checked address (`CURLOPT_RESOLVE`) and disables redirects.

* used by: uptime monitors (when saved **and** at every check), customer webhooks (when created and at every delivery),
  imports from a URL, reverse-proxy upstreams (`checkUpstream`: loopback of the node is allowed except the node's own
  service ports `onhost.egress.node_service_ports`).
* `ONHOST_EGRESS_DENY_CIDRS` — the operator's own public management ranges; `ONHOST_EGRESS_ALLOW_CIDRS` — a lab on
  private addresses (empty in production).
* a new feature that fetches a customer-supplied URL uses `Http::withOptions(app(EgressGuard::class)->options($url))`.
  Test: `tests/Feature/Platform/EgressGuardTest.php` (tests never touch DNS: `Tests\FakeHostResolver`).

## 3. Keys and tokens

* **An Idempotency-Key is not a credential.** A replay never signs anybody in and never returns another
  organization's order; a key that belongs to somebody else answers 409 `idempotency_key_reused`.
  Test: `tests/Feature/Orders/GuestCheckoutTest.php`.
* **A bearer token reaches only the route families its scopes name** (`token.scope` middleware, deny by default,
  decided before the controller runs): `services`, `invoices`/`documents`, `wallet`, `tickets`, `dns`/`zones`,
  `domains`, and `GET /v1/me`. The account, the step-up, tokens, organizations, webhooks and orders are portal-only.
  A new route family for tokens is added to `TokenRouteScope::FAMILIES` on purpose. Test: `tests/Feature/Http/PanelApiTest.php`.
* **Step-up:** once TOTP is enrolled the password is not a second factor (customers and staff). The setting is
  `onhost.identity.staff_mfa_required`; `tests/Feature/Platform/ConfigKeysTest.php` fails on any `config('onhost.*')`
  key the configuration does not define.

## 4. Roles

`organization.members.manage` says that somebody manages members, not what they may hand out: the owner role is not
granted (ownership moves by a transfer), nobody edits their own membership — neither the role nor the end of the
access — and a role is granted only by somebody whose own role covers every permission in it. Staff are not bound.
Test: `tests/Feature/Organizations/AccessExpiryTest.php`.

## 5. Money

* A captured payment is credited on its own; applying it to an order or an invoice runs afterwards. When that fails
  (frozen wallet) the credit and the receipt stay and finance gets `finance.reconciliation.mismatch` with kind
  `paid_but_not_applied`. A hard budget is asked when a card/bank order is **placed**.
* A payment callback that was "seen before" but did not settle asks the provider again (the event id is
  `transId:STATUS` and the customer knows their transId). Callbacks are limited to 120/min per source.
* A promo code is counted under a lock when an order is placed with it (`promo_exhausted` at the limit) and given back
  when an unpaid order is cancelled.
* `throttle:auth` has an e-mail bucket only when the request carries an e-mail (`email` or `customer.email`).
  Tests: `tests/Feature/Orders/CheckoutTest.php`.

## What to look at on staging after deploying this

* audit trail: `service.action.resize` by an actor who is neither staff nor the system;
* order items whose `config.options` hold keys the product does not sell or values above the option's range;
* orders/invoices of EU organizations with reverse charge whose `vat_status` was never verified by VIES;
* uptime monitors, webhooks and proxies pointing at private addresses (they now fail with `destination_not_allowed`);
* promo codes with `max_uses`: their `uses` start from zero now — set the real count by hand if a campaign is running.
