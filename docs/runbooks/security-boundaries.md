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
| Tax treatment and price region | facts of the organization, never of the request; a guest never claims a verified VAT number. A customer can never claim a VAT status: the cart and the organization quote ignore request input; only the system actor records a VIES verdict (`tax.vat_number.record`, 403 `system_only` for anyone else); a staff override (`tax.vat_status.override`) is CRITICAL (step-up + four eyes), audited, evidenced in `vat_validations` and lapses after 1–30 days. The VIES answer (trader name/address) never enters `provider_calls`, the command audit or the event (TASK-0031) | `QuoteService::quote()`, `CartController::quote()`, `Tax\VatStanding`, `Tax\Commands\RecordVatCheckHandler` | same file; `tests/Feature/Tax/VatStatusOverrideTest.php`, `tests/Feature/Tax/VatNumberCheckTest.php` |
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
* **What a token may do is one explicit map** (`domains/Identity/Authorization/TokenScopes.php`, TASK-0030): one row per
  catalogue permission, the scope that carries it or `null` = not available to tokens; a permission that is not in the
  map — or a command without a permission — is refused to a token. Consoles, the terminal, commands and SSH keys are the
  scope `services:console` (a console is neither a read nor a restart); no preset of the panel's key form carries it, the
  customer ticks it on purpose. `ApiContext::can()` asks the token too, so a read-only token of a manager sees only what a
  reader sees. A new permission gets its token decision in the same commit (ApiTokenScopeMapTest fails otherwise).
  Test: `tests/Feature/Http/ApiTokenScopeMapTest.php`.
* **A token never holds a step-up.** It cannot step up (`/v1/auth/step-up` is closed to tokens) and a grant made without a
  session is not matched for a `token:` session (`StepUpService::activeGrant`), so every HIGH/CRITICAL action through a
  token answers `step_up_required`. A token request is `token:<id>` whatever it carries: an Origin/Referer of a stateful
  domain makes Sanctum start a session for a bearer request, and `ApiContext::sessionId()` asks the token first.
* **A token is asked for what the action does** on `POST /v1/services/{id}/actions` (`permissionFor` → TokenScopes, at the
  route and in the controller): a console command needs `services:console` alone, a restart `services:power`. The route,
  the controller and the dispatch (`ApiController::dispatch`) all ask with the action's params, so a schedule with a
  console command needs `services:console` at each of them: a `services:power` or read-only token is refused it already at
  the route, a console-only token gets it (closed at the stack polish, TASK-0030 LOW; before, the route and the controller
  asked without the params and saw such a schedule as managing). Spec apply asks the same map for every step
  (`ServiceSpecService::tokenMay`, §26).
* **Archives and dumps are not available to tokens:** `backup.download` (final archive, backup download) is `null` in the
  map. A `services:power` token can still read and write site files one by one (`GET …/files/download` is `service.manage`),
  so a power token is as sensitive as the site's credentials.
* **A password change ends the person's API tokens** (owner decision 14, TASK-0021). Changing the password revokes every
  personal API token of the user in every organization (a reset always did); the browser that made the change stays
  signed in. A service account's tokens and the integration secrets (action hooks, on-call feeds, SLA probes, Discord)
  are not personal and are never touched: `Identity\ApiAccessRevocation` goes through the morph-scoped `User::tokens()`.
  Switch `onhost.identity.password_change_revokes_api_access` (`ONHOST_PASSWORD_CHANGE_REVOKES_API_ACCESS`, default
  **on**); off restores the old "tokens kept" path with its truthful mail (`security-password-kept`).
  Test: `tests/Feature/Http/PanelApiTest.php`.
* **Step-up:** once TOTP is enrolled the password is not a second factor (customers and staff). The setting is
  `onhost.identity.staff_mfa_required`; `tests/Feature/Platform/ConfigKeysTest.php` fails on any `config('onhost.*')`
  key the configuration does not define.
* **A staff write that does its work outside the bus asks for the step-up itself** (`ApiContext::authorizeAction`,
  TASK-0030): a HIGH permission needs the same fresh grant the bus would ask for (403 `step_up_required` → the console's
  dialog repeats the request); a CRITICAL permission there is a programming error (it goes through the bus for the second
  person). Today: `POST /v1/staff/dunning/run`, `POST /v1/staff/capacity/forecast/run`,
  `GET /v1/staff/services/{id}/panel-login`. Reads keep `authorize()`. Every `->authorize()` with a HIGH/CRITICAL
  permission in `app/Http/Controllers` is classified in `tests/Feature/Http/StaffTriggerStepUpTest.php`; a new one fails
  there until it is classified.

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
* **Paying to restore a cancelled service** (owner decision 23, TASK-0025, rule `services.reinstate`, default off) goes
  through the one credit gate of §22: `POST /v1/services/{id}/reinstate` needs `billing.wallet.topup` on the bus (the
  permission of paying an invoice from the credit; no API-token scope) and `CreditOrderPolicy::assertMaySpend()`; money
  lifts only the `payment` hold, never a quarantine or a staff hold. Taking a cancellation back bills again and asks the
  same gate. The payment-required answer shows the organization's credit and invoices only to `billing.wallet.read`. A
  restore never switches auto-renew on (TASK-0027 C2). Test: `tests/Feature/Billing/PayAndRestoreTest.php`.
* **Withdrawing from a contract** (owner decision 17, TASK-0025, rule `billing.withdrawal`, default off) needs
  `service.delete` at organization scope with a fresh step-up (not a service guest, an API token or the assistant); the
  staff record of a mailed notice is `billing.refund.execute`, CRITICAL (four eyes), because it can be dated back. The
  estimate is read with `billing.wallet.read` at organization scope. An order held for the staff risk review cannot be
  withdrawn by the customer, and a service the consumer withdrew from never gets a chargeback. Test:
  `tests/Feature/Billing/WithdrawalTest.php`.

## 6. Secrets that are handed out once are not kept

* `domains.registry_status` is filtered by the model when written and by the endpoint when returned — Subreg's
  `Info_Domain` carries the transfer code. Test: `tests/Feature/Domains/TransferCodeSecrecyTest.php`.
* `provider_calls`: XML elements (`<password>`, `<ssid>`, `<authid>`), PEM private keys whatever the field is called,
  passwords on a command line (`DB_PASSWORD '…'`, `--dbpass=`, `-p'…'`) and one-time links (`?pozvanka=`, `?code=`,
  `?signature=`) are masked. Test: `tests/Unit/RedactorTest.php`.
* The replay store (`idempotency_keys.result`) and a **sent** mail (`mail_outbox.vars`) keep the shape, not the secret;
  `GET /v1/staff/outbox` never shows it; a mail whose link is gone is not resent (`mail_secret_not_kept`).
  Tests: `tests/Feature/Domains/TransferCodeSecrecyTest.php`, `tests/Feature/Notifications/MailOutboxSecretsTest.php`.
* Generated passwords in `operations.desired/context/result` are forgotten after the run (the WordPress admin password
  is shown for 30 minutes to `service.manage` only): §11 below.

## 7. Accounts

* A locked account answers *locked* whatever the password is (422 vs 423 was a password oracle), and judges no guesses.
* A wrong authenticator code counts towards the lock like a wrong password.
* A password-reset link signs in only an account whose one factor is the password; with TOTP (and for staff) it sets the
  password and sends the user to the sign-in page (`signed_in: false`).
* Enrolling TOTP takes a step-up (`step_up_required` → the portal's confirmation dialog repeats the request).
* The GDPR export is downloaded by whoever may `organization.manage` — not by a read-only member and not by staff who
  may only read customers — and every download is audited (`compliance.data_export.download`).
* A console token is issued on the service (resource scope: a `svc_console` guest and a project role get it, a `svc_manage`
  guest and a viewer do not) and judged by the service it was issued for; a token with no known owner is nobody's.
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

## 11. An operation forgets the secrets it carried

* An operation needs the password of the database, the FTP account, the mailbox, the customer's mailbox at another provider
  (fetchmail), the private key of an uploaded certificate — **until it has acted**. The row kept them for good, in plain
  JSON: in the database, in every backup of it, in the staff console. The WordPress administrator password the platform
  generated was in the answer to **anybody who may list the operations of the service** — a read-only viewer, a guest.
* `OperationSecrets`: succeeded or cancelled → the request forgets at once; what the run generated for the customer to
  read once (`admin_password`) is shown for `ONHOST_OPERATION_SECRET_REVEAL_MINUTES` (30) and only to somebody with
  `service.manage`; a failed run keeps its input for `ONHOST_OPERATION_SECRET_FAILED_DAYS` (7) so a retry still works.
  Queued and running operations are not touched — a change asked for while a panel is away is still carried out.
* `onhost:operations:forget-secrets` (every ten minutes, no switch) takes what is left — **including every row written
  before this rule**; `operations.secrets_scrubbed_at` says a row is clean. Staff see the context of a run without its
  secrets. `key` is a secret when it is key material, not when it is the name of a game variable; the value of a variable
  whose name says password/token/secret is forgotten too.

Tests: `tests/Feature/Provisioning/OperationSecretsTest.php`.

## 12. A conversation with the assistant belongs to one person

* Without a `session_id` the transcript key fell back to the HTTP session — or to the **IP address**. Two people behind one
  address (an office, a mobile carrier, the next user of the same API client) continued each other's conversation: the
  earlier questions and the assistant's answers — invoices, variable symbols, DNS records, services — went into the
  model's context for somebody else. Found while testing the new read tools; proven by the test before the fix.
* Now a signed-in person's key always starts with their id (`<user>:…`, staff `staff:<user>:<organization>:…`), whoever
  calls the service; the previous run is looked up by key **and** person. A visitor's key is their browser session; with
  none there is no memory at all (a one-off key) — never the memory of everybody behind the same address.
* The read tools `get_dns_records` and `get_invoice` are offered only to somebody who may read that in the panel
  (`domain.read`, `billing.invoice.read`); asked for anyway, they answer like an unknown zone or document, as they do
  for another organization's.

Tests: `tests/Feature/Support/AssistantReadToolsTest.php`.

## 13. The state of an order is not written by hand

* `POST /v1/orders/{id}/transition` took **any** target state from whoever held `staff.order.manage`, outside the command
  bus (no idempotency, no audit of the command, no step-up). A support agent who is also somebody's customer — an
  organization of their own is enough — declared their unpaid order `PAID`: the transition published `order.paid`, the
  fulfilment provisioned the services, and no money, no reservation and no tax document ever existed. Proven by a test
  against the old code (`state=PROVISIONING services=1 hold=NULL docs=proforma`).
* Now the only thing a person does to the state of an order is **cancel** it, through the command bus:
  `order.cancel` (customer, `catalog.order.create`, only `NEW`/`PENDING_PAYMENT`) and `order.cancel.staff`
  (`staff.order.manage`, also `PAID` and `FAILED`, a reason is required once the order was paid). Anything else answers
  `422 order_transition_not_offered`. "Paid" comes from a payment (gateway, bank matching — `bank.line.record` for a
  transfer with a wrong symbol —, credit), "active" from delivered lines. An order with running services is not cancelled
  here (`409 order_not_cancellable`): its services are.
* Cancelling asked for `organization.read`: a read-only member, an auditor or a support contact cancelled the unpaid
  orders of the organization (and voided their proformas). It asks for the permission that places orders.
* The console's order buttons posted to `/v1/staff/orders/{id}/transition`, a route that did not exist — every one of
  them failed silently behind an optimistic state. The route exists now (cancellation only), the store seam offers only
  what the API offers and asks staff for the reason, and a refused change is rolled back on the screen.
* `onhost:doctor` — area `money`: *every order being delivered was paid and documented* (finds orders the old door
  produced) and *no document is credited for more than it was issued for*.

Tests: `tests/Feature/Orders/OrderTransitionGateTest.php`.

## 14. A name on a shared node belongs to one service

* What a service owns on a shared panel is recognised by its **name prefix** — `oh` + the last six characters of the
  service id (`Naming::prefix`): databases and their users, FTP accounts, the unix agent user. Six characters are thirty
  bits; with thousands of sites on one panel two services end up with the same prefix sooner or later, and each of them
  then lists, changes and drops the other's databases (aaPanel lists by `search=<prefix>`).
* `services.name_prefix` (migration `000760`) carries the prefix under a **unique index**. `Service::creating` replaces an
  id whose prefix is taken — by a running service or a cancelled one, whose databases stay on the node through the
  restore window — before anything exists; the index refuses what a race would let through. The aaPanel adapter matches
  `prefix_` (the prefix **and** its separator), not just the first eight characters.
* Services that already collided when the migration ran stay without a prefix of their own; `onhost:doctor` names them
  (area `security`: *every service has a node name prefix of its own*). Nothing can fix that from here — their
  resources on the node have to be told apart by hand and one of the services moved.

Tests: `tests/Feature/Services/ServiceNamePrefixTest.php`.

## 15. A failed operation takes back only what it made

* The compensations of the provisioning sagas called `terminate()` on whatever binding the service had — no proof that
  the thing under that number was theirs. The VPS clone step binds the service to the vmid it **reserved** the moment
  the clone is accepted; when the clone then failed because somebody else had taken that number in the meantime (the
  panel's own UI, another automation, a race on `nextid`), the binding pointed at a stranger's machine and the
  compensation **stopped and destroyed it**. Proven by a test against the old code: `POST …/qemu/1042/status/stop`,
  `DELETE …/qemu/1042`.
* `CompensationGuard::takeBack()` is now the only way a compensation deletes: the binding must be the one **this
  operation** wrote (`StepContext::bind` keys it by the operation — never simply "the service's first binding"), and the
  panel must confirm the resource by the same proof a cancellation needs (`ServiceIdentityCheck`, check `created_here`
  added; the states of a service that is still being set up are accepted). Used by the VPS, web site, game server and
  app sagas and by the game migration's half-built target.
* A panel that cannot be asked, a name that does not match, a resource somebody else's service also points at: **nothing
  is deleted**. The fact is audited (`provisioning.compensation.kept`) and operations get *Po nezdařeném zřízení zůstal
  zdroj na panelu* with the type, the number, the node and the checks that failed. An orphan costs a look; a wrong delete
  costs a customer's server.

Tests: `tests/Feature/Provisioning/CompensationGuardTest.php`.

## 16. A link the game panel hands out is followed only to the panel's own daemons

* The game panel is the one internet-facing panel here, and the control plane **follows links it returns**: the signed
  download of a backup (the archive kept after a cancellation, the data half of a migration) and the signed upload of a
  file. They were followed wherever they pointed, by a bare HTTP client, from inside the management network — a panel
  that was broken into could name any address there and have the answer stored as a customer's archive (which its
  owner then downloads), or pushed into a game server it controls.
* `PterodactylGameProvider::assertDaemonUrl()`: http(s), no credentials, and the host must be the FQDN of one of the
  panel's **own nodes** (`/api/application/nodes`, cached ten minutes, re-read when a host is unknown). Checked where
  the link is made (`backupDownloadUrl`, the upload links), so every consumer gets a checked one; redirects are not
  followed. The final archive no longer fetches a vendor link from the domain layer — it asks the adapter
  (`GameToolsProvider::downloadBackup`).
* The instance's TLS settings (`tls_ca` pinned certificate or path, `verify_tls` for development) were read by every
  adapter **but this one**: a panel behind a private CA could not be connected at all. They apply now to the panel calls;
  the daemons use `wings_tls_ca` when they carry another certificate than the panel, and the panel's otherwise.

Tests: `tests/Contract/PterodactylToolsContractTest.php`.

## 17. Staff reading a customer's data is an event; a role reads what it may change

* Every write goes through the command bus and leaves a trail. A **look** left none: the customer's account (members,
  credit, documents, services), a ticket's conversation, the mail queue, an integration. "Who opened this customer's
  account last week" had no answer — for us or for the customer.
* `StaffReadAudit`: one audit event `staff.read.<what>` per person and thing per quarter of an hour (a console that
  refreshes itself does not flood the trail), with the screen it came from. Events about one organization are scoped to
  it, so they are part of **that customer's own audit trail** (`GET /v1/organizations/{id}/audit?action=staff.read`):
  the customer sees that support opened their account, the same way they see what support changed. Wired into the
  customer detail, the ticket detail, the mail queue and the integration detail.
* Found while testing it: **no support role could read tickets.** `support.ticket.read` was held by the platform owner
  alone — the queue and the ticket detail answered 403 to L1, L2, L3 and the support manager, who could reply to
  (`manage`) and assign tickets they could not open; finance could credit an invoice (`billing.invoice.manage`) they
  could not open, the backup administrator delete a backup they could not list. And two operations boards (key
  revocations a panel has not taken, services waiting out their restore window) asked for `service.read` — a customer's
  permission — at the global scope. The owner is who tested all of these. Two guard tests now keep both rules: a role
  reads what it may change, and a staff endpoint asks only for a permission some staff role holds besides the owner.

Tests: `tests/Feature/Identity/StaffReadAuditTest.php`, `tests/Feature/Identity/PermissionMatrixTest.php`.

## 18. The domain a site serves belongs to one service too

* Section 14 is about the names a service owns **on the node** (databases, FTP, the unix user). This is about the name
  the world uses: on a shared node a web server picks the site that answers a request purely by the host name in it,
  so whoever holds the name gets the requests, the certificate and everything that follows.
* Only one place enforced it — `ServiceSites::assertRoomFor`, where a further site is added to an existing hosting.
  Everywhere else a customer could name somebody else's domain:
  * **`ssl.issue`** took whatever host names the request carried. `DomainPointing` (audit row 82) then checks each name
    really answers at the node — and a neighbour's domain **does**, because they share the node, so the guard that
    exists for exactly this cannot tell them apart. The platform therefore asked a certificate authority for a
    certificate for another customer's domain: every failed validation spends one of the five an hour that authority
    allows **that hostname**, so a customer could keep a neighbour (or a competitor hosted here) from getting a
    certificate at all, and a name that does validate lands on somebody else's certificate. `ssl.wildcard` was the
    same, one field further on.
  * **`subdomain.add`** took any host name, so a customer could write a neighbour's domain into their own vhost's
    server names — the textbook shared-hosting hijack — and spend their own plan's subdomain allowance on it.
  * **The order**, which is how every hosting is really created, copied `config.domain` (or `fqdn`) and
    `config.aliases` out of the cart into `desired_spec` unread: `items.*.config` is validated as `array` and nothing
    more. Two services could claim one name, and the alias list was not even checked for being host names — whatever
    the cart sent went into the panel's `server_name`.
* `domains/Services/Web/SiteNames.php` is now the one place that answers it, and `ServiceSites` uses it too:
  a name another **live** service in the same namespace holds is that service's; a name **under** a name another
  organization holds is that organization's (their wildcard would hand it to whoever serves it here); a name under one
  of your own names is always yours; anything else is free and left to the layers that already answer for it. A web
  vhost and a mail domain are **two namespaces** — one customer's `muj-web.cz` is normally both a website and a mailbox
  domain — and inside each the name belongs to one service, which also ends two mail hostings for one domain.
* The cart refuses before anybody pays (`site_name_taken`, 409 — the same place a sold-out server is refused), naming
  the customer's **own** service when the name is already theirs so they can act on it, and never naming somebody
  else's. `ServiceService::desiredSpec` refuses again inside the transaction that creates the service, so a race
  between two carts for one name ends there instead of at two vhosts.

* **A claim has to be proved, or it is a squat** (audit row 90). Giving a name to one service hands anyone a weapon
  as long as nothing has to be true for a claim to stick: order the cheapest hosting for `firma.cz`, never point it
  anywhere, and its real owner can never be hosted here. Nothing new is asked of an honest customer — the platform
  already knows three proofs (the domain is registered here by that organization, its DNS zone is here, or the name
  already answers with the node that serves it), and the daily DNS check works the third one out anyway, so
  `SiteClaim` rides along with it and asks the resolver nothing extra. The answer is written to
  `services.tags.name_claim`; a claim unproved past `ONHOST_SITE_CLAIM_GRACE_DAYS` (30) raises
  `service.name_unproved` to the operators once a day. **The platform never takes a live site's name away on its
  own** — a customer cannot undo somebody else's squat, and neither should a cron job.
* The refusal is no longer a dead end: it says to write to support, and when the person being refused can prove the
  name while the holder cannot, `service.name_disputed` lands in the audit trail with the evidence, so support is
  not deciding on one customer's word against another's. Both sides are answered without a DNS lookup — the holder's
  proof is the one the daily check already wrote down.

Tests: `tests/Feature/Services/SiteNameClaimsTest.php`, `tests/Feature/Services/SiteClaimProofTest.php`.

## 19. An id that arrives with a customer's request is not proof of ownership

* ISPConfig's remote API is reached through **one administrator session per instance** and never asks whose record a
  `primary_id` names. The platform validates a customer's `remote_id` for its **shape only**
  (`/^[A-Za-z0-9:_.-]{1,120}$/` in `ServiceService`). On a shared node the ids are consecutive integers, so the
  neighbour's are one apart and cost nothing to guess.
* Nine of the eleven id-taking methods in `IspConfigWebProvider` always resolved the id against the **site's own**
  listing first and refused what they did not find — the comments say why ("a mail domain's id among web sites is a
  stranger's site"). Three did not:
  * `setShellKey` — install your own SSH key on a neighbour's jailed shell user (their files, and through
    `wp-config.php` their database), or send an empty key and lock them out of their own;
  * `setDbUserPassword` — reset a neighbour's MySQL password;
  * `deleteDbUser` — the listing was read, but only to raise the "still owns databases" conflict, so an id that was
    **not** ours (`$user === null`) fell through the `!== null` test and was deleted.
* The same class ran through mail: `mailbox.update`, `mailbox.delete` and `alias.delete` built the `ResourceRef`
  straight from `$p('remote_id')` in `ServiceActionWorkflow`, so any mailbox on the mail server could have its
  password set (read the mail, send as them) or be deleted.
* The rule now has one place — `IspConfigWebProvider::ownRow()` — and mail is resolved where the certificate check
  already sits, before the `match` in the saga, through the existing `MailDomains::across` so every domain the
  service holds is covered. A listing that fails is a provider error and retries; it never reads as "not yours".
* **aaPanel was clean**: every method there resolves the id against the site's own listing, and the two it cannot do
  (`deleteDbUser`, `setShellKey`) refuse outright. The defect was ISPConfig-only.
* The tools that act on **one mailbox** (autoresponder, spam policy, filters, mailbox backup/restore, fetchmail) had the
  same hole and resolve the mailbox in the service's own mail domains first (`ServiceActionWorkflow::OWN_MAIL_TARGETS`,
  `MailDomains::ownRow`, TASK-0016).
* **Past exploitation.** Whether the hole was used before the fix is answered read-only by
  `php artisan onhost:audit:provider-calls` (TASK-0020, runbook `provider-calls-audit.md`): verdict and severity per
  action from the platform's own logged listings, probing after the fix, unexplained writes, log coverage. It writes
  nothing and calls no panel; to be run by the operator on staging with a production copy.

Tests: `tests/Contract/IspConfigOwnershipTest.php`, `tests/Feature/Provisioning/MailboxOwnershipTest.php`,
`tests/Feature/Provisioning/ProviderCallsAuditTest.php`.

## 20. Managing a shared service is not a shell on it

* The catalogue draws the line itself. `svc_manage` — "Service: manage" — says what it gives: *actions and settings:
  restart, PHP, databases, cron, files, deploys, mailboxes*. `svc_console` — "Service: console" — says *terminal, VNC
  and game console*, and carries `service.manage` with it, because a console is more than managing, never less (H334).
  Two roles exist so that a customer can hand an agency the day-to-day work **without** handing over a shell.
* `ServiceActionCommand::permissionFor()` sent everything but a handful of actions to `service.manage` — the web
  terminal (`command.run`), the SSH accounts (`shell.create`, `shell.key`, `shell.delete`) and the game console
  (`command.send`) among them. So the one role that exists to grant managing without a shell granted a shell: the
  agency could open a terminal on the site, read `wp-config.php` and with it the database, or put their own key on it.
* Those actions ask for `service.console`, and since TASK-0029 so does everything else that hands over the server itself:
  `access.reset` (new root password / keys), `rescue.start`, a game panel sub-user (`subuser.create` — the panel and its console,
  under another e-mail that outlives a revocation) and a schedule with a console command (`schedule.create` unless every task is
  power or backup — fail closed). Ending access (`rescue.stop`, `subuser.delete`) stays managing. FTP and database logins stay
  `service.manage`: they give what managing already gives. SSH keys and game sub-users of somebody who loses the console are
  revoked (`RevokeDelegatedAccess`), even when they keep managing — also when the owner shares the service again with the same
  person without the console: `ServiceAccessService::share` publishes `service.access.reduced` when the dropped capabilities
  took `service.console`, and the listener takes it down the revocation's path.
* `permissionFor()` is one map for the bus, for the operation row (a long run asks again before each privileged step,
  H315) and for `ServiceFeatures::features($service, $actor)` — so the panel stops offering the terminal to a
  colleague who may not use it, instead of showing a button that answers 403.

Tests: `tests/Feature/Services/ServiceAccessShareTest.php`.

## 21. A panel account password belongs to the organization owner

* `panel.password` (the customer's game-panel account, which opens every server of that account) maps to
  `service.panel_account.manage` (HIGH, step-up), which only the `owner` role holds — `PermissionCatalog::OWNER_ONLY`,
  `RoleCatalog::orgAdminWithheld()` and `RoleCatalog::STAFF_NEVER` keep it off org_admin, the `svc_*` share roles and
  platform_owner (owner decision 15, TASK-0021).
* `Services\Access\OwnerOnlyActions` refuses in `ServiceService::requestAction` anyone who is not the organization's
  `owner_user_id` acting in person — staff, the system, the assistant and impersonation included (`owner_only_action`).
  The platform never shows the panel password.

Tests: `tests/Feature/Identity/PermissionMatrixTest.php`, `tests/Feature/Provisioning/GameToolsFeatureTest.php`.

## 22. Spending the organization's credit

* `Orders\CreditOrderPolicy` decides who may pay from the credit (owner decision 20, TASK-0021). With
  `ONHOST_ORDER_CREDIT_APPROVAL` **off** (the default) nothing changes: whoever holds the command's own permission pays.
  With it **on**, only holders of `billing.wallet.spend` in the organization (owner, billing_admin; withheld from org_admin)
  or the literal `owner_user_id`. The principal is read as the bus reads it (`onBehalfOfUserId ?? actorId`), so an AI run
  or a service account without a person fails closed; the `system` actor is exempt.
* Orders paid from credit by anybody else are held (`CreditOrderApprovals`: nothing is reserved, issued or provisioned
  until the owner or a billing admin approves, `POST /v1/orders/{id}/approval`; 7 days without a decision cancel it).
  Immediate payments from credit call `assertMaySpend()` → 403 `credit_spend_not_allowed` with the permission named:
  invoice from credit, manual domain renewal, marketplace, an approved work offer, the archive download fee, and
  **pay-and-restore** — paying for a restore, taking back a cancellation that bills again and a recorded restore request
  (TASK-0027 C1: one gate for all three; before, the restore had a second gate with its own switch). Card and bank
  transfer stay open to everybody.
* A role that carries `billing.wallet.spend` cannot be granted by an org_admin (`mayGrant` → `role_above_own`).
* **Every new path that spends credit calls `CreditOrderPolicy::assertMaySpend()` or goes through a held order.**
* Before switching on: `php artisan onhost:orders:credit-approval-report` (read-only).

Tests: `tests/Feature/Orders/CreditOrderApprovalTest.php`, `tests/Feature/Orders/CreditSpendGateTest.php`,
`tests/Feature/Billing/PayAndRestoreTest.php`, `tests/Feature/Identity/PermissionMatrixTest.php`.

## 23. Prices, plans and the second person

* Catalogue writes go through `Staff\PricingController::catalog()`: the permission is checked before the pre-flight
  check (nobody without `catalog.manage` learns anything from validation), the binding (`base` / `base_version`) is part
  of the approved payload and of the idempotency key, and header-less keys carry the minute (`onceKey`). The rules of the
  second person: `docs/runbooks/approvals.md` (owner decision 13, TASK-0022).
* A handler that must prove its second person reads `CommandContext::verifiedApprovalIds` — the approval ids (or
  `waived:single-operator`) the authorizer consumed for this very command, set by the CommandBus — never `approvalIds`
  (what the caller offered) and never a search by payload hash. The free limit raise checks that the consumed approval's
  own payload names the organization, service, metric, units and price.
* An approval is spent exactly once: the authorizer marks it with one conditional UPDATE (state approved, `consumed_at`
  null) and denies unless exactly one row changed.
* Staff `resize` / staff `service.create` hold every entitlement and limit to what the service (plan) has: a value passes
  only if it is the same, a smaller positive number, or switching off; -1, words, `true`, null/0 on a counted number and
  new keys are refused (`limit_raise_required`); a product with plans needs a plan with a current version
  (`plan_required`). A customer-ordered limit raise requires `catalog.order.create` at the parent service's resource scope
  and a context project equal to the service's (`limit_raise_scope`).
* `onhost:catalog:revise --apply` and `onhost:catalog:state` publish as the system actor without a second person; the
  gate is shell access and code review of `CatalogRevisions` (a revision cannot pass prices or features).

Tests: `tests/Feature/Catalog/CatalogFourEyesTest.php`, `tests/Feature/Orders/LimitRaiseTest.php`,
`tests/Unit/Catalog/CatalogCommandRiskTest.php`.

## 24. A panel number is read only from what is provably the service's

* **Mailbox backup retention** (TASK-0024): `mailbox.backup_retention` is operator-only (`CustomerActionParams` → 403
  `operator_only`; HIGH for staff). The ISPConfig adapter writes a mailbox's retention only after proving the mail domain
  (`onh_` client, its group, the domain name) and the mailbox (same group, address inside the domain); the copy count
  comes from the service's entitlements, never from a request.
* **Database sizes for the plan total** (TASK-0023): the ISPConfig read (`databasequota_get_by_user`) is per client; only
  databases the panel lists under the service's own web domain (`parent_domain_id`) are counted, so a historical site of
  the same client never becomes this customer's usage; a remote id that is not a positive domain id is refused and a row
  without its own `parent_domain_id` is not counted. Nothing is called until `ONHOST_WEB_DISK_TOTAL_DATABASE_SIZES=true`.
  The disk-total notice command is system-only (a user context is refused by the handler). A guest shared one included
  site sees the plan figures and only that site's row (`WebDiskTotal::shownFor`), never the sibling sites or test copies.
* A shared web node's whole-node CPU, memory and disk are never shown as the customer's usage (`CustomerUsage`, H286).

Tests: `tests/Feature/Services/MailBackupRetentionTest.php`, `tests/Contract/IspConfigDatabaseSizeContractTest.php`,
`tests/Feature/Services/WebDiskTotalTest.php`, `tests/Feature/Services/DiskTotalNoticeTest.php`.

## 25. A resource the platform did not create is not ours

* Historical sites, mail domains and databases on the live ISPConfig and aaPanel are never bound, changed, suspended or
  deleted (owner rule of 2026-09-24, TASK-0014): a pre-existing resource is ours only when the panel proves it — the
  `onhost:<service>` remark on aaPanel, the organization's `onh_…` client as owner on ISPConfig — and anything else is
  refused with `CONFLICT` before a single write. A mail binding carries its domain name; an empty name is refused before
  any mailbox query (it once read `'%@'`, every mailbox on the shared mail server).
* Taking over a historical site never binds it: the only procedure is a customer-run import into a NEW site with an
  operator's help (`docs/runbooks/historical-site-import.md`, ADR-0007 decision 22), and no staff path runs `import.run`
  for a customer.

Tests: `tests/Feature/Provisioning/ResourceProvenanceTest.php`.

## 26. A service action asks for its own permission

* `ServiceActionCommand::PERMISSIONS` names every `ServiceActionWorkflow::ACTIONS` entry once and has no default: an action
  nobody placed in the map is refused with 422 `service_action_unknown` before anything runs, whoever asks (the system included).
  A default arm had sent 121 of 132 actions to `service.manage` at NORMAL risk (audit C13-H1; TASK-0029, ADR-0008).
* Deleting a copy is its own permission — `backup.delete` for web/managed/mail sets, `game.manage` for a game backup,
  `compute.vm.delete` for a VM snapshot — at HIGH with a fresh step-up. The catalogue rates `backup.delete` CRITICAL; that stays
  documentation, because the authorizer forces CRITICAL only for staff-audience permissions and customers have no four-eyes
  (D29.2). The day-to-day manager (developer, a `svc_manage` guest, a `services:power` token) cannot thin out the owner's backups
  — neither by deleting them nor through the backup schedule: keeping fewer days or generations than the schedule keeps now, or
  a frequency after which the kept history reaches less far back (with the same generations a more frequent schedule covers
  less time), is a deletion (the next tick prunes to it) and asks the same `backup.delete` decision with a fresh step-up
  (`WebToolsCommandHandler::assertMayThin`). The reach is `BackupScheduler::historyReach()` — the prune's own keeper decision
  and `BackupDailyKeepers::historyMinutes()`, pinned against the prune's selectors for five schedules — so the gate compares
  what the prune really leaves. Unlocking a game backup asks `game.manage`, as deleting one does.
* Every `DestructivePreview` action is HIGH with a fresh step-up (`archive.restore` was the one restore without it, C13-H1c).
  No service action is CRITICAL; the operator's `mailbox.backup_retention` (`backup.policy.manage`) takes four-eyes only when it
  prunes.
* A copy the platform keeps — protected, the final archive, under a legal hold — is not deleted or unlocked by a service action on
  any panel: 409 `backup_protected` at request, and the run asks again before the panel call.
* Secondary gates ask the same map: spec apply authorizes every step as its own `ServiceActionCommand` and reports a refused step
  in `skipped`; on a token session every step also asks the token scope map as `/actions` does (`ServiceSpecService::tokenMay`,
  integration of TASK-0029 with TASK-0030): a step the token's scopes do not carry is skipped as `token_scope:<action>`, so a
  `services:power` token cannot schedule a console command while a token given `services:console` can, and a token session
  whose token is gone is refused. Action hooks and Discord buttons never run an action that needs a fresh step-up (a URL or a
  chat click cannot give one), and refuse an unmapped action. Each run carries the step's own permission for the H315 re-check.
* Residual: `service.manage` still reaches code execution as the site user (cron, `file.save`, `node.create`, uploads, deploy
  hooks) and inside a game container (`gfile.save`, `gfile.upload`, `variable.set`); `schedule.run` fires an existing schedule,
  also a console one the owner made, and `schedule.toggle` can switch such a schedule back on. The console line covers shells
  and root, not code execution.
* Residual (not verified): if the installed Pterodactyl rotates the oldest unlocked backup away when a scheduled backup task
  reaches the limit, a backup schedule made by somebody who only manages can thin out the copies the owner left unlocked; a
  locked copy is the owner's protection.
* Residual: console access made before TASK-0029 through the old default (game sub-users, root password / SSH keys, console
  schedules) stays until an operator command (`--dry-run` by default, not written yet) lists and revokes it;
  `RevokeDelegatedAccess` acts only on future events. A game sub-user and a console schedule ask `service.console` but no fresh
  step-up (owner decision open).

Tests: `tests/Feature/Services/ServiceActionPermissionMapTest.php`, `tests/Feature/Services/ServiceActionRoleMatrixTest.php`,
`tests/Feature/Services/ServiceActionSecondaryGatesTest.php`, `tests/Feature/Http/ApiTokenScopeMapTest.php`.

## What to look at on staging after deploying this

* migration `000720` scrubs `domains.registry_status`; afterwards `select count(*) from domains where registry_status like '%authid%' and registry_status not like '%[redacted]%'` is 0;
* orders that were delivered and never charged (the query is in `billing-dunning.md`);
* `provider_calls` of the last 90 days still hold what was logged before the new masks (Subreg password and session ids, private keys) — rotate the Subreg API password after deploying and let retention age the rows out, or delete `provider_calls` of `subreg` older than the deploy;
* audit trail: `service.action.resize` by an actor who is neither staff nor the system;
* order items whose `config.options` hold keys the product does not sell or values above the option's range;
* every reverse-charge (AE) decision carries its evidence: `orders.meta.vat` / `invoices.buyer.vat_check` with status valid
  and reason fresh or staff_override (legacy_unverified only on rows from before TASK-0031, flagged `vat_review`). A row
  without it is a finding. Query (PostgreSQL; columns checked against migration `000200`, the query itself not run yet):
  `SELECT i.id, i.number, i.issued_at, i.buyer->>'vat_id' AS vat_id, i.buyer->'vat_check'->>'reason' AS reason FROM invoices i WHERE EXISTS (SELECT 1 FROM invoice_lines l WHERE l.invoice_id = i.id AND l.tax_category = 'AE') AND (i.buyer->'vat_check' IS NULL OR i.buyer->'vat_check'->>'status' <> 'valid' OR i.buyer->'vat_check'->>'reason' NOT IN ('fresh','staff_override')) ORDER BY i.issued_at;`
  — documents issued before TASK-0031 have no vat_check and are expected in the result (none should be AE, since nothing
  wrote `valid` before);
* uptime monitors, webhooks and proxies pointing at private addresses (they now fail with `destination_not_allowed`);
* promo codes with `max_uses`: their `uses` start from zero now — set the real count by hand if a campaign is running;
* web and mail services that already share one name: `select hostname, family, count(*) from services where state <> 'TERMINATED' and hostname is not null group by hostname, family having count(*) > 1` — the refusal only stops new ones, and whichever vhost the node loads first is serving that name today.
* roles: `AuthorizationSeeder` runs with the deploy (`infra/aapanel/deploy.sh`); afterwards `onhost:doctor` must report the
  roles in the database equal to the catalogue — billing_admin holds `billing.wallet.spend`, only `owner` holds
  `service.panel_account.manage`, an org_admin can no longer grant billing_admin (TASK-0021);
* `php artisan onhost:audit:provider-calls` on a production copy (read-only, §19) before the first customer is told
  anything about the ISPConfig ownership hole.
