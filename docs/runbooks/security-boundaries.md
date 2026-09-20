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
  imports from a URL, the site check between a staging update and the production one (every redirect hop is judged),
  the host mail is collected from (`checkHost` — not pinned, the node connects), reverse-proxy upstreams (`checkUpstream`: loopback of the node is allowed except the node's own
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

## 6. Secrets that are handed out once are not kept

* `domains.registry_status` is filtered by the model when written and by the endpoint when returned — Subreg's
  `Info_Domain` carries the transfer code. Test: `tests/Feature/Domains/TransferCodeSecrecyTest.php`.
* `provider_calls`: XML elements (`<password>`, `<ssid>`, `<authid>`), PEM private keys whatever the field is called,
  passwords on a command line (`DB_PASSWORD '…'`, `--dbpass=`, `-p'…'`) and one-time links (`?pozvanka=`, `?code=`,
  `?signature=`) are masked. Test: `tests/Unit/RedactorTest.php`.
* The replay store (`idempotency_keys.result`) and a **sent** mail (`mail_outbox.vars`) keep the shape, not the secret;
  `GET /v1/staff/outbox` never shows it; a mail whose link is gone is not resent (`mail_secret_not_kept`).
  Tests: `tests/Feature/Domains/TransferCodeSecrecyTest.php`, `tests/Feature/Notifications/MailOutboxSecretsTest.php`.
* Still open: `operations.desired/context/result` keep generated passwords (the customer reads the WordPress admin
  password from the operation result). See `production-readiness-audit.md` §"Review 2026-09-20".

## 7. Accounts

* A locked account answers *locked* whatever the password is (422 vs 423 was a password oracle), and judges no guesses.
* A wrong authenticator code counts towards the lock like a wrong password.
* A password-reset link signs in only an account whose one factor is the password; with TOTP (and for staff) it sets the
  password and sends the user to the sign-in page (`signed_in: false`).
* Enrolling TOTP takes a step-up (`step_up_required` → the portal's confirmation dialog repeats the request).
* The GDPR export is downloaded by whoever may `organization.manage` — not by a read-only member and not by staff who
  may only read customers — and every download is audited (`compliance.data_export.download`).
* A console token is judged by the service it was issued for; a token with no known owner is nobody's.
* A CSV cell never starts a formula (`'` is put in front of `= + - @`).
  Tests: `tests/Feature/Http/AuthApiTest.php`, `tests/Feature/Compliance/ComplianceTest.php`,
  `tests/Feature/Http/SurfaceTest.php`, `tests/Feature/Orders/OrderRiskFeedbackTest.php`.

## 8. One panel id is not another panel id

ISPConfig numbers web domains and mail domains separately. The adapter refuses site calls (FTP, shell users, cron,
databases, sub-domains) for anything but a web binding, and termination revokes web delegations on a web binding only —
cancelling a **mail** service used to delete the FTP and shell accounts of the stranger whose web domain had the same
number. Test: `tests/Contract/IspConfigContractTest.php`.

## 9. Whoever acts on a service is asked the same question

A shared service (`service_access_grants` → resource-scoped bindings), a project role and an organization role all end
in `Authorizer::can(person, permission, CommandScope::resource(service, organization, project))`. The authorizer is one
per process and is emptied after every request and before every queued job, so a permission taken away is not answered
from memory. The assistant reads and proposes through `AssistantScope`, built from the same authorizer.
Details: `docs/runbooks/service-sharing-and-assistant.md`.

## 10. An action belongs to the families that have it; a backup id belongs to the site that made it

* The core actions (`backup`, `restore`, `snapshot`, `rollback_snapshot`, `power`) were never asked whether the service
  offers them — only the feature actions were. A `backup` on a **mail** service reached the ISPConfig web adapter with
  the mail domain's id, and a mail domain's id among web sites is a stranger's site: its backup plan was rewritten and
  its archives listed (a restore of them was one request away). `ServiceService::assertCoreActionOffered` refuses by
  family before an operation exists (`feature_unavailable`), and `backup` / `listBackups` / `restore` of the adapter
  carry the same `assertWebDomain` guard as the other site calls (§8).
* ISPConfig's `sites_web_domain_backup` and `mail_user_backup` take the **backup's** id and do not ask whose backup it
  is. The adapter acts only on an id that stands in the site's (mailbox's) own list — `backupAction()`,
  `restoreMailbox()`; the operator command `onhost:ispconfig:restore-site` does the same.
* A backup row is deleted or restored only through the service it belongs to (`where service_id`), never when it is
  `protected`, `final` or under a legal hold; the final archive is not served by the ordinary download.
* The customer chooses nothing about a backup row (`CustomerActionParams`: kind, retention, protection are the
  platform's) and holds at most `ONHOST_BACKUP_MANUAL_MAX` manual backups — they live on our disk.

Tests: `tests/Feature/Services/WebBackupArchiveTest.php`, `tests/Contract/IspConfigToolsContractTest.php`.

## What to look at on staging after deploying this
* migration `000720` scrubs `domains.registry_status`; afterwards `select count(*) from domains where registry_status like '%authid%' and registry_status not like '%[redacted]%'` is 0;
* orders that were delivered and never charged (the query is in `billing-dunning.md`);
* `provider_calls` of the last 90 days still hold what was logged before the new masks (Subreg password and session ids, private keys) — rotate the Subreg API password after deploying and let retention age the rows out, or delete `provider_calls` of `subreg` older than the deploy;
* audit trail: `service.action.resize` by an actor who is neither staff nor the system;
* order items whose `config.options` hold keys the product does not sell or values above the option's range;
* orders/invoices of EU organizations with reverse charge whose `vat_status` was never verified by VIES;
* uptime monitors, webhooks and proxies pointing at private addresses (they now fail with `destination_not_allowed`);
* promo codes with `max_uses`: their `uses` start from zero now — set the real count by hand if a campaign is running.
