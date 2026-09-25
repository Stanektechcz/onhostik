# Pre-production audit (closed 2026-09-07, extended through block §5u on 2026-09-14)

> **Historický dokument — stav k 2026-09-14; aktuální stav: `docs/context/CURRENT_STATE.md`.** The numbers below are those of
> their day: the suite then had 155 tests / 2 819 assertions (on 2026-09-25 the stack tip of TASK-0027 runs
> 1 364 tests / 18 258 assertions, `.ai/baseline/baseline.json`). The operator checklist of §6 is superseded by
> `docs/runbooks/go-live-checklist.md`; open findings after 2026-09-14 live in `docs/runbooks/production-readiness-audit.md`.

What the control plane does end to end, how each part is verified, what was found and fixed during the audit, and the
short list an operator still has to do before the first paying customer. "Verified" means a test in the suite
(`php artisan test`: 155 tests / 2 819 assertions green, `tests/Feature/Http/EndpointSweepTest.php` calls every
`/v1` route as visitor, customer and staff without a 5xx) or a live check against a real vendor system.

## 1. Customer journey

| Step | State | Verified by |
| --- | --- | --- |
| Public site, catalogue, plans, domain check, cart, checkout (card / bank / postpaid), consents | complete | CheckoutTest, DuplicateOrderTest, browser walkthrough (order OH-2026-1002) |
| Legal documents behind every consent (VOP, privacy, DPA, SLA, withdrawal notice, auto-renewal, domain terms) | complete: versioned pages at `/dokumenty/<slug>` rendered from `resources/legal/*.md` with the legal entity's identity | LegalDocumentsTest |
| Registration, sign-in, password reset, TOTP, step-up | complete | AuthApiTest |
| Order → proforma / gateway intent → payment settlement → fulfilment → service ACTIVE | complete | OrderFulfillmentTest, VpsProvisioningTest, BillingTest |
| Plan change of a running service (upgrade paid pro rata, downgrade free, node resized, subscription re-priced, notification + mail) | complete | PlanChangeTest |
| Live journey 2026-09-13 (guest order → proforma → bank line → provisioning on cz1 → ACTIVE → statement/proforma/receipt → ticket) | complete: order OH-2026-1016, ACTIVE 108 s after the bank line | journey.php (scratch script) |
| Scripted browser walkthrough 2026-09-13 (public `/webhosting` → plan CTA → cart drawer → `/kosik` guest details → bank transfer → confirmation → bank line → provisioning on s2 → panel *Fakturace*) | complete: order OH-2026-1017 (2 sites), proforma PF-2026-0017 → receipt PP-2026-0009 + statement VY-2026-0007, both services ACTIVE, mails welcome/order-received/invoice/payment-received; **found and fixed**: the SPA summed monthly prices for a 12-month default term while the API priced the year (336 Kč shown, 3 364 Kč invoiced) — the cart now shows the server quote and starts monthly (seam #24); s2 reset two API connections during provisioning (operations WAITING → retried on their own) | in-app browser + pay-1017.php |
| Cancelling an unpaid order voids the proforma and the bank intent | complete | OrderCancellationTest |
| Panel: overview, services, servers, tickets, invoices, notifications, credit top-up, new-service wizard | complete (real data or hidden) | PanelBillingSeamTest, SurfaceTest, browser walkthrough |
| Panel: per-service workbench driven by the service feature catalogue (tabs = what the executor offers) | complete for web (aaPanel, ISPConfig), VPS (Proxmox), game (Pterodactyl), mail (ISPConfig) | WebFeatureActionsTest, ServiceActionTest, browser walkthrough |
| Panel: domains — registration and expiry (renew, auto-renew, transfer lock, AUTH-ID), nameservers / switch to ONhost DNS, the ONhost zone with staged DNS changes and publish, DNSSEC (sign + publish DS), registry operations; "Nová služba → Doména" orders a domain with a live availability check | complete | PanelDomainSeamTest, DomainLifecycleTest, DnsServiceTest, browser walkthrough |
| Domains: registration, transfer, renewals, DNS zones (two-phase commit), DNSSEC, multi-registrar with cheapest-registrar selection | complete | DomainLifecycleTest, RegistrarSelectionTest, WedosContractTest, SubregContractTest, PowerDnsContractTest |
| Support tickets, SLA, incidents, status page, maintenance | complete | IncidentsTest, SlaTest, support tests |
| Partner programme (referrals, commissions, payouts, white-label) | complete | PartnersTest |
| Compliance (GDPR/Data Act requests, DSA abuse, NIS2 timers, legal hold) | complete | ComplianceTest |
| Vendor neutrality: no panel or registrar name on any customer surface, script, catalogue, consent or API response | complete | VendorNeutralityTest, PanelBillingSeamTest |

## 2. Executors and the customer feature matrix

Customers never see a vendor name (Presenters strip `[vendor:CODE]` prefixes and vendor names from operation errors,
domain DNS is "ONhost DNS" / "external", the panel prototype's vendor strings are replaced by seams). Each service
exposes `GET /v1/services/{id}/features` and the panel shows only enabled tabs.

| Feature | aaPanel | ISPConfig | Proxmox (VPS) | Pterodactyl (game) | ISPConfig mail |
| --- | --- | --- | --- | --- | --- |
| Domain + extra domains / sub-folders | ✓ | ✓ (alias / subdomain) | – | – | – |
| PHP version | ✓ (versions from the panel) | ✓ (`server_get_php_versions`) | – | – | – |
| Let's Encrypt + forced HTTPS | ✓ | ✓ | – | – | – |
| Databases (create / delete, scoped names) | ✓ | ✓ | – | – | – |
| FTP accounts (create / delete / password) | ✓ | ✓ | – | – | – |
| Cron jobs | ✓ (host scheduler, labelled per service) | ✓ | – | – | – |
| Redirects | ✓ | ✓ | – | – | – |
| Logs (access / error) | ✓ | – (no API; log pipeline) | – | – | – |
| Backups / restore | ✓ / node agent | ✓ / ✓ | ✓ / ✓ (PBS) | ✓ / ✓ | – |
| Shell users + SSH keys | – (SFTP with the FTP accounts) | ✓ (chrooted shell users, ed25519/RSA keys) | root | – | – |
| Error pages / web-server directives | – / rewrite rules | ✓ / ✓ (Apache + nginx; includes, modules and handlers are refused) | – | – | – |
| Protected folders | site-wide password | ✓ (HTTP Basic per folder) | – | – | – |
| Database users (create / password / delete) | password reset per database | ✓ | – | – | – |
| Traffic statistics | – | ✓ (AWStats / GoAccess / Webalizer, password) | – | – | – |
| Custom certificate upload | ✓ | ✓ | – | – | – |
| File manager (browse, edit ≤ 512 kB, folders, delete, download through the API) | ✓ | – (SFTP) | – | – | – |
| One-click applications | ✓ | – | – | – | – |
| Database admin tool link | when `phpmyadmin_url` is set on the instance | same | – | – | – |
| Power, console, snapshots, firewall | – | – | ✓ / VNC relay / ✓ / ✓ | ✓ / websocket / – / – | – |
| Console command, schedules | – | – | – | ✓ / ✓ | – |
| Mailboxes, aliases, DKIM, sending switch | – | – | – | – | ✓ |
| Usage / quota | ✓ | ✓ | ✓ | ✓ | – |
| Reverse proxy (path → upstream), default documents | ✓ (`GetProxyList`/`CreateProxy`/`RemoveProxy`, `GetIndex`/`SetIndex`) | ✓ (managed block in the site directives: `ProxyPass`/`location`, `DirectoryIndex`/`index`; Apache needs mod_proxy) | – | – | – |
| Plan change (upgrade / downgrade) | ✓ via the order pipeline (`resize`: PHP workers) | ✓ (`resize`: quota, PHP workers) | ✓ (`resize`) | ✓ (`resize`) | – |
| Billing period change (month ↔ year), service summary (plan, renewal, project, certificate, backup, monitor) | ✓ | ✓ | ✓ | ✓ | ✓ |

The prototype's web workbench has 24 tabs; all 24 are now API-backed (`tests/Feature/Provisioning/WebFeature*Test.php`,
`WebToolsFeatureTest.php`, `tests/Contract/*ToolsContractTest.php`). What the panels do not offer through their own APIs
runs through the platform's node access instead (docs/runbooks/web-tools.md): terminal, PHP settings, security rules,
HTTP/3, staging, git deploy, WordPress toolkit, wildcard certificates, CDN, imports, monitoring, backup schedules, and the
mail extras on ISPConfig (forwards, catch-all, autoresponder, spam policies and lists, filters, mailing lists, fetchmail,
mailbox backups). The "add-ons" tab is the toolkit hub, not a shop. Next candidate after go-live: a noVNC
page in front of the VNC relay (today the customer gets a one-time token and connects with a VNC client).

## 3. Placement, capacity and registrars

* Products carry a default executor; **plan placements** (Nastavení systému → Umístění tarifů) pin a product or a
  single plan, optionally per region, to a provider instance and optionally to one node. The scheduler honours the
  most specific active placement; `PlanPlacementTest` covers resolution, compatibility and scheduling.
* Node discovery: Proxmox (cluster), ISPConfig (servers); aaPanel / Pterodactyl / PowerDNS / PBS nodes are registered
  by hand. IPAM pools per region are required for VPS; the doctor reports products without any usable executor
  (the dev seeder now includes a PBS lab instance so backup products have an executor outside production too).
* Registrars: ONhost sets its own selling prices; the wholesale price book (Nastavení systému → Registrátoři domén,
  `registrar_tld_costs`) is refreshed from registrar price APIs (Subreg) or entered by staff (WEDOS has no price API)
  and `RegistrarSelector` registers each domain with the cheapest registrar for its TLD; renewals stay with the holding
  registrar; TLDs can be pinned. `RegistrarSelectionTest`, doctor checks `registrar available` and
  `registrar cost prices known for every TLD`.

## 4. Live verification (2026-09-07)

| System | Result |
| --- | --- |
| ISPConfig 3 · https://s2.onhost.cz:8080 | remote login OK, sites/server/monitor functions OK, `server_id` 1, discovery imported `s2.onhost.cz` (web) |
| aaPanel 8.0.6 · https://45.67.217.22:28133 | system totals and site list OK from the control plane IP (allow-listed) |
| WEDOS WAPI (`wedos-main`, JSON, poll queue) | UP over IPv4: `ping`, `credit-info` (100 CZK), `domain-check` (registered names answer 3201, free names 1000) verified through the adapter and `DomainService::search`. Over IPv6 WAPI redirects to a 404 page or refuses the address, so the gateway forces IPv4 (`WEDOS_FORCE_IP_RESOLVE=v4`); the production IPv4 egress must be in the WAPI allow-list. Reads are sent without the `test` flag (WAPI returns empty data for test-mode reads); the clock gate tolerates 5 s. |
| Subreg (`subreg-main`, SOAP document/literal) | credentials stored; `Login` with the supplied API user is refused with `500/104 Incorrect username or password` on both SOAP endpoints. The wire format was verified against the live endpoint (document/literal envelope, `*_Container/response`, `major/minor` error codes); the API user has to be fixed on the Subreg side before the price book can be pulled |

Neither live hosting system was modified beyond the read-only probes and node discovery; the registrar checks were
read-only (`ping`, `credit-info`, `domain-check`, `Login`).

### 4b. Live toolkit verification (2026-09-12)

Two test services of the audit organization (`688mr1zw.web.onhost.cz` on aaPanel cz1, `275w3cg8.web.onhost.cz` on
ISPConfig s2; entitlements `ssh`, `staging`, `deploy`, `monitors:3` granted for the test) were driven from the
panel in the browser (every tab and hub chip rendered, no vendor names, no error strings) and through the action
API on the live nodes:

| Node | Verified live |
| --- | --- |
| aaPanel 8.0.6 | terminal as the site's agent user (git, PHP 8.5), php.settings, security.set, cron create/update/run/logs/delete, files mkdir/save/copy/rename/chmod, zip/unzip, missing folder → not found (no parent listing), backup, monitoring check (200 / 195 ms), database.delete, **wp.install** (own database, WP-CLI, 47 s), wp.update, git deploy of a public https repository (release folder, run-path switch) and disconnect (run path back to the root), staging resources |
| ISPConfig 3.2 | terminal (jailed agent user over SSH), php.settings, security.set, cron.create (job queue 40–70 s), files over SFTP incl. tar.gz archive/extract, backup, quotas; `sites_cron_get` answers an empty list and `sites_cron_delete` an SQL error on this server (two probe cron rows `echo isp-cron-test` / `echo probe-cron`, ids 7 and 8, remain on s2) |
| Discord | signed interactions against the running platform: ping, wrong signature → 401, `/onhost link` with a panel code, `services`, `status` (operations, uptime), `backup` refused while another operation runs, `restart` explained for web hosting, `ask`, `unlink` |
| Staging (2026-09-13) | aaPanel: create (site + DNS, certificate pending until the hostname resolves), refresh (rsync on the node), push refused before the first refresh, delete + terminate; ISPConfig: create (jailed agent user through the job queue, operation retries until SSH accepts), refresh over the control-plane relay (tar warning tolerated), push, delete + terminate |
| cz1 "web server" (resolved: DNS) | the node itself serves the WordPress site correctly (`curl --resolve … 45.67.217.22` → the installed site, `/wp-login.php` 302); the public name `688mr1zw.web.onhost.cz` resolves through the WEDOS wildcard `*.web.onhost.cz` to s2 (161.97.119.173), whose default vhost answered. Root cause: the platform never wrote DNS rows for hosting subdomains because `onhost.cz` lives at WEDOS, not in the platform DNS. Fix: platform zones (`onhost:dns:adopt`, `onhost:dns:platform-sync`, provisioning/termination write and remove `<label>.web` rows through the WEDOS zone adapter). **Open for the operator:** the WAPI refused the control plane IP (`2051 Access not allowed from this IP address (193.179.119.167)`) — allow-list it, then run the two commands and `ssl.issue` on the site |
| Events and mails waited for the minute scheduler (order paid → provisioning, payment → invoice closed, ticket replies, top-ups: up to 60 s each hop; mails another minute) | `OutboxPublisher::publish` dispatches a debounced `RelayOutboxJob` after commit, `NotificationService::queueMail` dispatches `SendMailOutboxJob` on the `mails` queue (`ONHOST_OUTBOX_EAGER`); the scheduler stays as the safety net (`EagerDeliveryTest`) |
| Two relays running at once (eager job + request cycle) delivered the same outbox message twice — one payment produced doubled mails | `OutboxPublisher::relayPending` serialises relays with a cache lock; `RelayOutboxJob` just loops it |
| A locked SQLite database (or a deadlock) during fulfilment marked the paid order FAILED | `FulfillPaidOrder` rethrows transient database errors so the outbox retries the message with backoff; the order stays PROVISIONING |
| Payments and top-ups only produced in-app notifications | mail templates `payment-received` (on `invoice.paid`, also to the customer) and `wallet-topup` (`wallet.topup.completed` with the new balance) |
| The panel's ⌘K search only filtered the prototype's lists and offered fake guides ("Migrace z Wedosu") | quick select (seam #34): basic actions (new service, domain, top-up, ticket, invoices, services, domains, account, security, status, knowledge base, AI assistant, team, API keys) plus the organization's services, domains and tickets; Enter takes the first hit; the overview's quick cards grew to eight |
| Staff console | outside demo mode only API-backed areas remain (overview, ticket queue and thread with reply and state changes, customers, incidents with resolve/update, maintenance calendar); every narrated element (fake P1 strip, roster, capacity, per-role dashboards, seeded log, access grants, role switcher) is gone; verified in the browser as `admin@onhost.cz` |

## 5. Findings fixed during the audit

| Finding | Fix |
| --- | --- |
| The hosting panels' own APIs left a third of a modern hosting panel uncovered (terminal, php.ini, WAF-lite, HTTP/3, staging, git deploy, WordPress updates/cache, wildcard SSL, CDN, imports, uptime monitoring, backup schedules/download, mail forwards/catch-all/autoresponder/spam/filters/lists/fetchmail/mailbox backups) | the web toolkit (`WebToolsProvider`, `MailToolsProvider`, node shell + file transport per executor, platform services and workflows, `WebToolsController`, seam #31) implements them once for aaPanel and ISPConfig; scheduler commands `onhost:monitoring:check`, `onhost:backups:run`, `onhost:certificates:renew`, `onhost:cdn:refresh`, `onhost:web-tools:prune` |
| Customers learned about an empty wallet only when a renewal failed | `WalletForecast` (gross renewal walk, `GET /v1/wallet` → `forecast`), `onhost:billing:runway` + `wallet.runway.low` → notification and `wallet-runway` mail 14 days ahead, once a day |
| Sites provisioned before their DNS pointed at the node never got a certificate | `CertificateAutoIssuer` (`onhost:certificates:issue-pending`, every 15 min) requests `ssl.issue` once the name resolves to the node; `ssl.issue` keeps `tags.access.certificate` in step |
| Slack and Teams webhooks received the raw signed envelope | `ChatMessage` renders Block Kit / MessageCard by URL (Discord embeds unchanged), signature header kept |
| Customers with domains at their own WEDOS account had no automation for them | connected registrar accounts (`RegistrarConnectionService`, `/v1/registrar-connections`, panel page *Připojené registrátory*): mirrored domains and zones, expiry notices at the customer's registrar, credit watch, hosting pairing (`DomainPairingService`, aliases + DNS rows + certificate), staff list/pause; customer instances excluded from every platform lookup |
| `WapiGateway` rejected zone rows for the apex (`name` empty) and relative labels such as `www` as "invalid domain name" | row commands validate the label relative to the zone (empty allowed) separately from domain names |
| No way to move a running service to another plan; the workbench said "write to support" | plan changes through the order pipeline (`config.upgrade_of`, `PlanChangeService`, `GET /v1/services/{id}/plans`, panel *Kvóty* / *Výkon a tarif*); zero-total orders settle at once |
| aaPanel reverse proxies and default documents were not reachable from the panel | `proxy.create/delete` + `resources/proxies`, `index.set` + `resources/default_docs` (aaPanel), refused cleanly on ISPConfig; add-ons hub chip *Reverzní proxy a index* |
| A top-up "by transfer" without an explicit provider went to the card gateway and failed in dev | `method: bank|transfer` selects the bank provider when no provider is named |
| Six panel areas (audit, maintenance windows, costs, personal data, monitoring, backups) stayed prototype-only and hidden | seam #37 (`onhost-panel-pages.api.js`) + `GET /v1/monitors`, `GET /v1/backups`; sidebar and quick-select entries, staff-switchable |
| Mirrored domains could be sent to renew/re-delegate through the platform's own registrar account | `DomainService::assertNotMirrored` (`domain_external`) on renew, auto-renew, nameservers, DNS delegation and every critical-domain check |
| Projects had no page in the panel | seam #35 (`onhost-panel-projects.api.js`): list with spend and share, detail with members, service assignment, archive/restore |
| Projects existed as a table only | `ProjectService` + `ProjectController`: list with spend per project, detail, update, archive/restore, members with project-scoped roles, service assignment; `CommandScope::resource()` carries the project so a project developer manages that project's services only |
| No calendar of renewals, expiries, due dates and maintenance | `CalendarFeed`: `GET /v1/calendar`, signed ICS feed `/calendar/{org}.ics` (rotatable), quick-select action |
| WEDOS WAPI still answers `2051 Access not allowed from this IP address (193.179.119.167)` after the allow-list change (2026-09-13, three probes, breakers reset) | platform side complete (`onhost:dns:adopt onhost.cz`, `onhost:dns:platform-sync`, hourly sync); operator: allow exactly `193.179.119.167` (IPv4) for the WAPI login in `WEDOS_MAIN_LOGIN`, then run both commands with `WEDOS_TEST_MODE=false` |
| The panel showed the prototype's narrated domain panels (fake expiry, fake registrant) for real domains | domain rows carry `apiState` and the domain state; an API-backed domain workbench (seam #21) replaces the narrated panels; domains can be ordered from the panel |
| Consent links (`/dokumenty/vop`, `/dokumenty/podminky-registrace-domen`, `/sla`) had no page behind them | `LegalDocumentController` + Markdown documents + versioned pages; consents link to ONhost's own domain terms instead of a registrar's |
| WEDOS `domain-check` misread: registered names reported as "unknown", free names as "taken" | adapter maps `3201` to `available: false`, `1000` without status to free (verified live) |
| WEDOS `credit-info` read the wrong field and test-mode reads returned empty data | field `amount`, reads without the `test` flag |
| WAPI unreachable over IPv6 (302 to a 404 page), clock gate too strict for Date-header measurement | forced IPv4, 5 s tolerance |
| Registrar name (WEDOS) in public copy, panel copy, shared scripts, catalogue terms link and consent title | renderer block 7, asset rewrite, seeders, VendorNeutralityTest |
| Only one registrar; no cost comparison | Subreg adapter, price book, cheapest-registrar selection, per-registrar contacts, multi-registrar poll/credit/reconcile |
| Backup products had no executor in the dev environment (doctor warning) | PBS lab instance in `DevInfrastructureSeeder` |
| API keys, team invitations and every other step-up action failed from the panel ("Step-up authentication required"), and the denied answer was replayed for the same `Idempotency-Key` | session bridge step-up dialog (`OnhostApi.stepUp`: password, or authenticator/recovery code once 2FA is on) repeats the request; `IdempotencyKey` no longer stores 401/403/429 answers (`PanelApiTest`) |
| One-time secrets (API token, webhook signing secret, authenticator secret) never appeared — the `wide` block is hidden outside demo mode unless flagged real | account views flag their blocks `real: true` |
| Revoked API keys and disabled webhooks stayed in the lists; side-panel rows had no click handler, so a webhook could not be removed | lists filter them out; renderer side-row seam (`on`, pointer cursor) |
| Password fields were plain text inputs; "other devices signed out" was claimed but only step-ups were revoked | form field kinds `password`/`email` with `autocomplete`; `MeController::changePassword` rotates the remember token and deletes the user's other database sessions (`PanelApiTest`) |
| Pending team invitations were invisible and could not be withdrawn | `GET /v1/organizations/{id}` lists `invitations`; `DELETE /v1/organizations/{id}/invitations/{id}` cancels (op `cancel_invitation`, event `organization.invitation.cancelled`); team page rows with *Zrušit pozvánku* |
| The wizard opened from the header showed an OS choice for web hosting, location/system rows for a domain, "(měsíc)" for a yearly domain and a two-year default while the order used one year | order module `current()`, `summary()`, `period()`; renderer defaults (`orderSizes[0]` for domains, the shown size is passed to `place()`) |
| A failed card payment surfaced the API's English message | billing module explains in the panel language that the gateway is unavailable and offers transfer or credit |
| The panel sidebar listed the prototype's narrated areas and "Panely a technologie" (nothing behind them) and could not follow the offer | sidebar built from `PanelNavigation` (seam #29): categories from the catalogue and the organization's services, staff switches/order/labels in the admin, wizard follows the same switches; add-ons no longer appear as wizard tiles; domains register for 1–10 years; `apps` (Kubernetes) is a draft product — its public page (`/sluzba/devhosting`) shows the plans as "připravujeme" with a contact request instead of prices and order buttons |
| Two assistants (the command script's drawer and the chat) and the surface switcher "Plochy Onhost" were visible to customers | only the chat remains (seam #30) and talks to the real assistant; the switcher and the drawer are not mounted outside demo mode |
| Keyword triage missed accented words on Windows/libiconv ("stojí" → "stoj'i") and word forms like "služeb"/"objednat" | `Triage::normalize` drops transliteration marks; topics `api` and `cenik` added, `objednavka` keywords widened |
| Three TLDs were sold below the scraped wholesale price (.sk, .com, .io) and two sat within a few crowns of it (.dev, .gg) | selling prices raised in `CatalogSeeder`; doctor warns `no TLD sold below its cheapest cost price` whenever the CZK price drops under the cheapest registrar cost |
| The cart implied discounts nobody approved (−10 % / −18 % for 12 / 24 months, ONHOST10 hard-coded), sold four fake upsells and a fake domain search; guests were sent to registration; the registration hint said 8 characters while the API wanted 12 and `terms` was never sent ("The terms field must be accepted"); the sign-in pages swallowed header clicks (Produkty menu) | pricing rules live in *Nastavení → Slevy a doplňky* (`docs/runbooks/pricing.md`): no commitment discount unless approved per family, domains at list price for whole years with optional TLD discounts, add-ons per cart line from the catalogue, promo codes validated by the API, real domain availability, guest checkout with automatic account (`POST /v1/checkout/guest`), Czech validation messages (`lang/cs`, `SetLocale`), auth interception limited to the auth card |
| Web hosting pages showed three plans and a short comparison only | landing and product pages carry the comparison, the complete parameter listing, the add-on catalogue and the configurator *Tarif na míru* (product `web-custom`, option prices set by staff) |
| The links the platform mails ("set your password", "confirm your e-mail") had no page — the prototype's reset view only requests a new link | `/obnova-hesla?token=` and `/overeni-emailu?token=` are server-rendered pages (`Web\AuthPagesController`) calling the API; setting the password signs the browser in (`AuthPagesTest`) |
| The web workbench offered 12 of the prototype's 24 tabs | error pages, directives, protected folders / site password, database users, shell users + keys, statistics, custom certificate, file manager, applications and the database admin link are API-backed (`WebFeatureExtrasTest`); the file manager keeps every path inside the site root and streams downloads through the API |
| Registration failed with `database is locked` and left an orphan user (the e-mail could never register again) | SQLite runs in WAL mode with `busy_timeout` and `BEGIN IMMEDIATE` (`config/database.php`, `DB_BUSY_TIMEOUT`); registration creates user, organization, attribution and token in one transaction |
| Registering or signing in while the browser still cached another account's organization answered 403 "not a member" | `startSession()` drops the stale `X-Organization` header; the bridge forgets a hand-off order that belongs to another account |
| Every "Odhlásit" button (public account box, panel user menu, admin user menu) only touched localStorage — the server session stayed alive | renderer block 4d routes them through the bridge's `OnhostSession.signOut()` → `POST /v1/auth/logout` (`SignOutSeamTest`) |
| The registration form ignored the "Jméno a firma" field (accounts were named after the e-mail) | the bridge reads the prototype's untyped input |
| Staff step-up by password was refused by validation although the service allows it before TOTP enrolment | `/v1/auth/step-up` accepts `method: password`; the settings page's step-up dialog resolves the caller's promise after the verified retry |
| Payment instructions showed `iban: [redacted]` | our bank account is not a secret: `iban` left the redaction list |
| Checkout offered PayPal, crypto, SEPA and "Stripe"; the ETA promised a server in 90 seconds even for transfers; signed-in customers retyped their identity | tiles limited to card / Apple Pay / Google Pay (gateway) and bank transfer; ETA per method (`OnhostCart.eta`); form pre-filled from the account (`OnhostCart.prefill`, organization identity in the boot object) |
| Bank transfers could never be confirmed (no statement import) | `BankStatementImporter`: manual lines in Nastavení → Bankovní platby (`POST /v1/staff/payments/bank/lines`), Fio API sync (`onhost:bank:sync`, `ONHOST_BANK_FIO_TOKEN`), doctor check, `BankImportTest` |
| Transactional mails (verify, reset, guest account) sat on a `mails` queue no worker consumed | worker queue lists include `mails` (README, docker-compose, systemd) |
| ISPConfig provisioning failed on a live 3.2 panel: unknown username answered as a fault, client password policy, mandatory `http_port`/`https_port`, partial `sites_web_domain_update`, `sys_groupid` used as client id | adapter: fault → create client, "Very Strong" panel password, ports, get → merge → update, client resolved by username (`IspConfigContractTest`) |
| aaPanel: site lookup searched the id in the name column ("site missing after creation"), new files could not be saved | lookup by remembered name / paginated scan, `CreateFile` before `SaveFileBody` |
| A retried operation resumed after the failed step although compensation had removed what earlier steps created; the async wait timeout counted from the first run ever | retry restarts the workflow (step 0, context, attempts, clock); the timeout counts waits of the current run only (`StaffApiTest`) |
| Customers had no ticket conversation in the panel: "Odpovědět" sent them to the staff queue, replies/closing/rating were unreachable, the form listed no real services | seam #26 `onhost-panel-support.api.js` (thread from `GET /v1/tickets/{id}`, reply, close, CSAT, real services in the form) |

## 5b. Audit 2026-09-13 (after the commerce, panel and registrar-connection blocks)

What was re-verified: the full Pest suite (221 tests green at the time; 225 after the 2026-09-13 walkthrough block), the live customer journey on cz1 (order → proforma → bank
line → provisioning → ACTIVE in 108 s → statement, proforma and receipt → ticket), the panel pages on real data
(projects, connected registrars, audit, maintenance windows, costs, personal data, monitoring, backups), plan changes
and the aaPanel proxy/index tools against the provider doubles, the public landing (no dead server links; the SPA
routes by hash).

Findings that remain open, in the order they should be taken:

1. ~~Public landing → checkout in a real browser session~~ — done 2026-09-13 (§1, walkthrough row): the run found the
   monthly-vs-yearly summary mismatch, fixed in seam #24 (`GuestCartQuoteTest`, `PublicPagesSeamTest`). Still worth
   automating as a Playwright smoke in CI; the in-app browser lands every `/panel/<slug>` deep link on the overview
   (hash routing is fine) — reproduce in Chrome before treating it as a bug.
2. **Registrar connections** are complete on the platform side but not exercised against a live WEDOS account (the
   operator's credentials were never run from this environment); the first live connect should be watched in
   *Nastavení systému → Integrace → Zákaznická připojení* (probe result, domain count, credit).
3. ~~Plan changes: billing period~~ — done 2026-09-13: month ↔ year is its own flow (`periods` in
   `GET /services/{id}/plans`, a period-change line starts a new period today minus the unused rest, mail
   `service-period-changed`; runbook *Plan changes*). A product change (web → managed) stays a new order + termination.
4. ~~ISPConfig reverse proxy / default documents~~ — done 2026-09-13 as a managed block in the site directives
   (`ManagedDirectives`, runbook *Reverse proxy and default documents*). Operator prerequisite: `mod_proxy` on Apache
   nodes (check `apache2ctl -M | grep proxy` on s2 before the first customer proxy). HTTP/3 stays refused on ISPConfig.
5. **Card payments** depend on the Comgate merchant (dev has none): the gateway path is contract-tested, not live —
   deliberately left out of this round.
6. **Operator items** in §6 are unchanged: production `.env`, provider instances, WEDOS IP allow-list, Subreg access,
   legal review, price books.

### 5c. Next steps proposed after the 2026-09-13 walkthrough — all seven done the same day

1. ~~Playwright smoke in CI~~ — `tests/e2e/public-checkout.spec.js` + `playwright.config.js` + `.github/workflows/e2e.yml`
   (and `tests.yml` for Pint + Pest): catalogue → drawer (total = `POST /v1/cart/quote`, monthly by default, the
   12-month chip re-quotes yearly) → guest checkout → confirmation (number, amount, VS) → panel overview / Fakturace /
   Služby → the unpaid order is cancelled through the panel's API client. Verified locally against the dev server
   (runbook *Browser smoke*). Lesson: `page.request` has no session (sanctum needs the referer) — cancel through
   `window.OnhostApi` inside the page; interrupted runs leave an unpaid order (`e2e-<stamp>@onhost-test.cz`).
2. ~~Quote hygiene~~ — `onhost:commerce:prune` (daily 04:20, `CommerceHousekeeping`), `CommercePruneTest`.
3. ~~Money display~~ — `mny()`/`czk()` (public) and `money()` (panel, admin) keep haléře when the amount has them
   (2 286,90 Kč; whole crowns stay whole); the overview now says *2 286,90 Kč k úhradě*.
4. ~~Renewal transparency~~ — service rows: *Standard · ročně · obnova 6. 9. 2027 · 1 890 Kč / rok* (`renewal` field);
   the workbench's *Platba a obnova* row carries *Platit ročně* / *Platit měsíčně* (`OnhostPanelTools.switchPeriod`).
5. ~~ISPConfig node check~~ — live on s2 (walkthrough site 4xe0jag1.web.onhost.cz): `DirectoryIndex` took effect
   (`/` 200 → 403 with a non-existent index), `ProxyPass` took effect (`/onhost-proxy-check/` 404 → 503 from a closed
   upstream, so `mod_proxy` is on), clean-up restored 200. Two lessons fixed the same day: (a) the managed
   `DirectoryIndex` replaces ISPConfig's template line, which ends with `standard_index.html` (the welcome page of an
   empty site) — the block now keeps that fallback last; (b) `index.set` with an empty list means "the web server's
   default" and removes the block (aaPanel gets its default order), a list of only bad names is still refused.
6. ~~Provisioning resilience~~ — `GET /v1/orders/{id}` → `provisioning.stalled`; the banner says *nasazení trvá déle než
   obvykle, zkoušíme znovu*, the operations tab *trvá déle než obvykle · zkoušíme znovu* (`ProvisioningStallTest`).
7. ~~Cleanup~~ — Webhosting Start (srv_…40tv4kyp) terminated through the bus (final backup, deprovision on s2, 60 s);
   Webhosting Standard (srv_…4xe0jag1, org of `walkthrough-mtznt39m@onhost-test.cz`) stays as the ISPConfig smoke
   fixture for live directive checks (scratch scripts `proxy-live.php`, `restore-index.php`).

### 5d. What to take next — done 2026-09-13 (except the push, which needs the repository under git)

1. ~~Second e2e spec~~ — `tests/e2e/panel-customer.spec.js`: signs in with the local dev login link (`E2E_PHP`,
   `E2E_CUSTOMER_EMAIL`, default `demo@onhost.cz` from `DevAccountSeeder`, which now attaches the catalogue's plan
   version to the demo web service and backfills existing databases), opens `/panel/fakturace` from a link and
   `/panel#/sluzba/web` on a fresh load, reads *demo-web.cz · CZ1 · Profi · měsíčně · obnova … · 449 Kč / měs.*,
   opens the service and switches the billing period through *Platit ročně* (confirm dialog, order placed), then
   undoes it (cancels an unpaid order or switches back for free). The workflow seeds `DevAccountSeeder` for it.
   **Still open**: pushing the repository to a git remote so `.github/workflows/*.yml` actually run — an operator step.
2. ~~Panel deep links on boot~~ — root cause: the shell's first render wrote the default hash (`#/prehled`) before
   `componentDidMount` read the one the link carried, so `/panel/fakturace` (boot `#/fakturace`) and a fresh
   `/panel#/…` both landed on the overview. `SurfaceRenderer::panelSeams` now lets `syncHash()` write the URL only once
   mounted (`this.__onhostMounted`); verified in a fresh browser and in the e2e (`ServiceSummaryTest` guards the seam).
3. ~~Quote/cart volume~~ — the drawer quotes after 600 ms of idle (one request per burst of clicks), the local
   estimate bridges the gap; `onhost:commerce:prune` prints the counts to watch.
4. ~~ISPConfig proxies at scale~~ — `proxies.set` replaces the whole list in one action (`WebToolsProvider::setProxies`):
   ISPConfig writes the vhost once, aaPanel applies the difference; validated (≤ 20, unique names), tested on both.

### 5e. Proposed next features for full automation of the client area, the staff console and service management — all eight built 2026-09-13

Ordered by how much manual work they remove; each is buildable on what exists (command bus, outbox events,
notification router, service summary, plan changes, action hooks). Implementation notes per item are in
`docs/runbooks/web-tools.md` → *Automation block*; tests: `UsageWatchTest`, `RenewalGuardTest`, `OperationsBoardTest`,
`ServiceSummaryTest` (checklist), `DigestTest`, `ServiceSpecTest`, `BulkActionTest`, `NodePrerequisitesTest`.

1. **Usage watch → one-click upgrade** (`onhost:services:usage-watch`, hourly): read the `quotas` resource of active
   web/VPS services, raise `service.usage.high` at 85 % / 95 % of disk, traffic or workers with the plan options
   attached (`GET /services/{id}/plans` already prices the upgrade), a panel nudge on the service row and, per
   service, an opt-in *automatické navýšení tarifu* policy that places the upgrade order from credit without a click.
2. **Renewal guardrails**: seven days before a renewal compare credit with the sum due (`WalletForecast` has the
   data) and mail a top-up link; with a card token on file (Comgate, once live) an opt-in *auto top-up* charges the
   difference so no service ever lapses for an empty wallet.
3. **Staff operations board**: stalled and failed operations across tenants with *retry now* / *cancel* / *drain node*
   (pause placements onto a node after N transient failures, resume on green), plus the node reachability probe that
   the walkthrough showed s2 needs (`orders → provisioning.stalled` is the customer half of it).
4. **Onboarding checklist per service**: computed from the summary (domain paired, certificate issued, backup
   verified, monitor on, mail DNS ok) and shown on the first tab and in the chat agent's suggestions; each item is a
   one-click action that already exists.
5. **Digests**: a weekly customer digest (renewals due, spend, backups, uptime, expiring domains) and a daily staff
   digest (stalled ops, failed backups, dunning, capacity) from the calendar and insight endpoints.
6. **Declarative service spec for automation**: `PUT /v1/services/{id}/spec` accepting the whole desired state
   (php, redirects, cron, proxies, index, security rules, monitors) applied idempotently through the existing
   workflows — the Terraform-style counterpart of `proxies.set`, driven by the same reconciler.
7. **Bulk staff actions**: PHP-version rollout, security-baseline apply, certificate re-issue across a node or a
   customer as one job with progress and a per-service report.
8. **Live-node hygiene from the platform**: an operator command that checks each ISPConfig/aaPanel node's
   prerequisites (`mod_proxy`, PHP versions, jail tools, cron API) and records them on the instance, so features are
   offered only where the node can deliver them.

### 5f. What remains for hands-off operation (proposed 2026-09-13, after §5e) — all eight built 2026-09-13

Done the same day (runbook `web-tools.md` → *Hands-off block*, *Game servers*): Comgate recurring cards behind
`StoredMethodCharging` (§5f-1), the staff console table views on the staff API (§5f-2), usage watch for game and
mail (§5f-3), spec sections for game servers and VPS (§5f-4), digest frequency/preview/EN (§5f-5), the shell
probe (§5f-6), the auto-drain feedback loop with failed operations, probes and *keep drained* (§5f-7), the order
intake pre-check with a staff review queue (§5f-8) — plus the complete game panel integration (`GameToolsProvider`,
node discovery, prerequisites, `onhost:integrations:secret`).

1. **Stored payment methods**: a provider implementing `StoredMethodCharging` (Comgate recurring / card tokens once
   the merchant is live) turns the automatic top-up from "notice only" into a charge; the setting, limits, audit and
   mail are ready.
2. **Panel surfaces for the new staff pages**: the operations board and bulk actions live on Blade pages under
   *Nastavení systému*; a seam in the staff console prototype (`#/provoz`) would put them next to the ticket queue.
3. **Usage watch for game and mail**: disk of game servers (Pterodactyl) and mailbox quotas (ISPConfig) measured the
   same way; VPS memory is measured but has no automatic upgrade (plan changes on VPS resize disks only).
4. **Spec for VPS and game servers**: the declarative document covers web services; firewall, snapshots schedule and
   server settings could join it for cloud/game families.
5. **Digest tuning**: per-user digest opt-in in *Nastavení → Oznámení* (today the `digest` kind is organization-wide),
   English wording for EN accounts, and a staff digest per region.
6. **Node prerequisite probes that need a shell**: jail tools on ISPConfig and shell execution on aaPanel are known
   from live checks (memory), not from the command; a per-site agent probe (`php`, `git`, `rsync` present) would
   complete the record.
7. **Auto-drain feedback loop**: today a node drained automatically is resumed on the first successes; add a manual
   *keep drained* flag and a staff notification with the operations that failed.
8. **Order intake automation**: a fraud/abuse pre-check on guest orders (disposable e-mail domains, repeated failed
   payments) before provisioning starts, and automatic KYC prompts for B2B invoices above a threshold.

### 5g. What remains after §5f (proposed 2026-09-13) — all eight built 2026-09-13

1. ~~Game panel go-live~~ — `GamePanelBootstrap` (`php artisan onhost:game:bootstrap pterodactyl-gamepanel`, console
   *Herní uzly → Zprovoznit panel*, `POST /v1/staff/integrations/{instance}/game/bootstrap`): probe → nodes into the
   scheduler → catalogue templates (`config/onhost.php` → `game.eggs`, regex on nest/egg names) mapped onto the panel's
   eggs (*Šablony her → Namapovat automaticky*, `POST …/game/eggs/sync`; community eggs the panel lacks are named for
   import) → prerequisites → the default port range where a node has no free allocation → plan placement. The public
   `gamehosting` page sells the `game` product, the panel wizard offers the templates (`onhost-panel-order.api.js`
   `images()`), the order carries `config.egg`. Still needs the keys stored by the operator (never in chat, never in
   code): `onhost:integrations:secret pterodactyl-gamepanel application_key --check` and `… client_key --check`.
   e2e: `tests/e2e/panel-game.spec.js` (workbench + API shapes) and `tests/e2e/staff-console.spec.js`.
2. ~~Game migrations~~ — `GameMigrationWorkflow` (`game.migrate`): target node (given or scheduler, same panel) →
   stop the source → backup → allocation on the target → the same server created there (`serverDefinition`) → the
   archive streamed daemon to daemon by `TransferGameArchive` (`importArchive`: signed download → signed upload →
   decompress) → the platform switched (binding, `game_servers`, node, access address; `service.migrated` mail with
   the new address) → source deleted. A failure before the switch deletes the target and starts the source again
   (`service.migration.failed`). Staff: `POST /v1/staff/services/{service}/migrate {target_node_id?, reason}` and
   *Herní uzly → Vystěhovat servery* (`POST …/game/nodes/{node}/evacuate`, drains the node and keeps it drained).
3. ~~Mail spec~~ — `ServiceSpecService` family `mail`: `forwards`, `catchall`, `aliases` (GET/PUT
   `/v1/services/{id}/spec`; one action per row to add or remove). A spec apply may queue several operations on one
   service (`requestAction(..., chained: true)`; the queue serialises them per service).
4. ~~Fraud signals with data~~ — `IpGeoProvider` (`ONHOST_ORDER_RISK_GEO_ENDPOINT`, any JSON endpoint with `{ip}`,
   cached, never blocking) adds `ip_country_mismatch`; staff decisions teach the check (`OrderRiskService::learn`:
   release −5 / reject +5 per signal, bounds 5–100, `system_settings` `orders.risk.weights`; the console shows the
   weights under *Kontrola objednávek*).
5. ~~Stored methods beyond Comgate~~ — Stripe (`setup_future_usage=off_session`, token `customer:payment_method`,
   off-session PaymentIntents known by `pi_` ids) and GoPay (ON_DEMAND recurrence, `create-recurrence`) implement
   `StoredMethodCharging`; `storedMethodFrom()` reads the token from the settled payment's status payload, so the
   domain never parses vendor payloads; the automatic top-up charges through the card's own gateway.
6. ~~Scheduler heartbeat~~ — `QueueHeartbeat` job every minute; `AutomationLedger::liveness()`; `onhost:doctor`
   rows *scheduler running* / *queue worker alive* (FAIL in production after five minutes); `onhost:integrations:health`
   raises `platform.queue.stalled` once per half hour; the console's jobs view shows both machines.
7. ~~Automation switches~~ — `PUT /v1/staff/automation/{key} {enabled, reason}` (op `automation.toggle`, permission
   `provisioning.freeze`); a switched-off rule records skips (`stats.skipped`) instead of going quiet; provisioning
   and integration health have no switch (the freeze is for incidents); the order check honours its switch.
8. ~~Bulk actions in the console~~ — *Běhové úlohy → Hromadná akce* (action, filter `family:web` / `product:` /
   `instance:<key>` / `node:` / `org:` / `services:`, JSON params, reason) → `POST /v1/staff/bulk-jobs`; rows show
   the job's items.

### 5h. What remains after §5g (proposed 2026-09-13) — built 2026-09-13, item 1 waits for the operator's keys

1. **Live panel run** — everything is prepared and tested against a panel double; the live run needs the two keys
   stored by the operator (`onhost:integrations:secret pterodactyl-gamepanel application_key --check`, then
   `client_key`) — keys pasted into a chat are never stored by the assistant and should be regenerated. Then
   `onhost:game:bootstrap pterodactyl-gamepanel`, import the community eggs the report names, one paid game order,
   `npm run e2e` (`tests/e2e/panel-game.spec.js`). Live audit 2026-09-13: aaPanel cz1 (8.0.6), ISPConfig s2,
   WEDOS API + zones and Subreg all answer; the game panel waits for its keys.
2. ~~Cross-panel migrations~~ — the target node may belong to another panel: `GameMigrationWorkflow` ensures the
   customer's account there (`ensureUser`), takes the catalogue template mapped on the target (`options.eggs`; an
   unmapped template refuses before anything is touched), creates through the target adapter, polls the install
   there, streams the archive from the source daemon to the target daemon, and switches the binding, the service
   and the game server row to the target panel. Test: `GameMigrationTest` cross-panel case.
3. ~~Customer-chosen migration windows~~ — `POST /v1/staff/services/{id}/migrate {window_from, window_to}` (and
   evacuation) creates the saga undispatched with `next_run_at` = window start; the customer gets
   `service-migration-scheduled` and moves the start with `PUT /v1/services/{id}/migration {starts_at}` inside the
   window (workbench *Provoz* tab row *Plánované stěhování serveru → Změnit termín*); `provisioning.tick` starts it
   on time; the schedule lives in `tags.migration` until the saga ran.
4. ~~Risk weights in the console~~ — `PUT /v1/staff/automation/order.risk/tuning {weights, hold_score, reset}`
   (*Automatizace → Kontrola objednávek → Upravit váhy*); the row shows the weights, the hold score and the
   feedback counts per signal.
5. ~~Worker autoscaling signal~~ — `AutomationLedger::backlog()` (operations due for longer than
   `ONHOST_QUEUE_BACKLOG_AGE_MINUTES`, per queue); `platform.queue.backlog` once per half hour above
   `ONHOST_QUEUE_BACKLOG_THRESHOLD`; gauges `onhost_operations_backlog{queue}` and
   `onhost_operations_backlog_threshold` on `/metrics`; doctor row *operation backlog*; the jobs view carries it.
6. ~~Mail spec completeness~~ — sections `mailboxes` (create with password, quota/name/password rotation; never
   deleted by a document — extras are reported), `autoresponders` (per address), `spam` (policy id or name per
   address); `tests/e2e/panel-mail.spec.js` on the demo mail service (`DevAccountSeeder` now seeds one).
7. ~~GoPay/Stripe recorded contract tests~~ — `tests/Contract/GatewayRecurringContractTest.php` on
   `tests/Contract/fixtures/{stripe,gopay}` (sandbox-shaped payloads, secrets redacted). Found and fixed on the
   way: a stored-card charge the gateway completes at once (Stripe `succeeded`, GoPay recurrence `PAID`) was recorded
   as paid without booking the credit; `PaymentService::createIntent` now settles it immediately.
8. ~~Console deep links~~ — every backed view answers `#/<view>` on a fresh load (e2e covers renewals, galloc,
   gprov, customers, incidents, maintenance, prehled); narrated prototype views stay hidden outside demo mode.

### 5i. What remains after §5h (proposed 2026-09-13) — built 2026-09-13, item 1 still waits for the operator's keys

1. **Live run of the game panel** (item 1 above) and a production `.env` pass with `onhost:doctor` at 0 WARN on the
   blocking rows (HTTPS origin, Redis, PostgreSQL, secrets driver, staff MFA, Comgate merchant, bank account,
   `SANCTUM_STATEFUL_DOMAINS`, metrics token). *Open: the panel keys are stored by the operator
   (`onhost:integrations:secret pterodactyl-gamepanel application_key|client_key --check`); everything else is scripted.*
2. **Migration windows for other families** — done: `ServiceMigrationService` (`domains/Provisioning`) owns
   migrations for game *and* cloud services (`VpsMigrationWorkflow`, kind `vps.migrate`: Proxmox live migration
   `POST /nodes/{node}/qemu/{vmid}/migrate` with local disks, then the binding, the node and `tags.migration`
   move); the windows/reschedule machinery is shared; other families answer `migration_unsupported`.
3. **Second worker on the backlog gauge** — done: `QueueScaler` (`onhost:queue:scale [--apply] [--max=]`) derives
   the desired helper count from the stale backlog and starts short-lived `queue:work --stop-when-empty` helpers
   (cool-down between rounds); scheduled every five minutes when `ONHOST_QUEUE_AUTOSCALE=true`.
4. **Risk model review page** — done: `GET /v1/staff/orders/risk-review?days=&format=csv` (held orders with
   signals next to the outcome, per-signal precision), console action *Přehled rozhodnutí* on the `order.risk` row.
5. **Mailbox password rotation without a plaintext password** — done: `POST /v1/services/{id}/mailbox-password-link`
   issues a signed one-time link (24 h) to a plain page where the mailbox user sets the password; the ordinary
   `mailbox.update` action carries it to the node, the link is burned. DKIM/sending policy sections stay in the
   mail spec (§5h-5).
6. **Recorded fixtures** — done: `onhost:fixtures:record {comgate|stripe|gopay} [--out=]` creates one sandbox
   payment and stores the create/status payloads redacted under `tests/Contract/fixtures/<gateway>/recorded_*.json`.
7. **Customer-facing migration status** — done: the workbench operations row shows the step label while the saga
   runs, the new address and node when it finished, the failure when it failed (`tags.migration.state`).
8. **Console deep links with selection** — done: `tests/e2e/staff-console.spec.js` opens `#/customers/<id>`, the
   money view and the risk review.

**Service management and redistribution (added with §5i, 2026-09-13)**

* **Chargeback (refund in credits)**: the customer asks (`POST /v1/services/{id}/chargeback {reason}`), support
  decides in *Finance → Vrácení kreditu* (`POST /v1/staff/chargebacks/{id}/decide`), the customer then cancels the
  service from the workbench (step-up, `POST …/chargeback/cancel`); the unused part of the paid period × the
  percentage is credited to the organization's wallet (source `chargeback`) once the termination succeeded. The
  percentage (default 70 %) is set in the console (`PUT /v1/staff/chargebacks/settings {percent}`) and is
  snapshotted on approval so a later change never alters an approved case. Money only moves as wallet credit.
* **Rebalancing**: `NodeRebalancer::plan()` (`GET /v1/staff/provisioning/rebalance?role=`) reads sold RAM against
  capacity per node, hands the smallest services of a hot node (> 85 %) to the coldest node of the same role and
  region until it sits at the target (75 %); `POST …/rebalance {service_ids, window_from/to, reason}` turns the
  moves into migrations (the console *Uzly a operace* view has *Plán přerozdělení* and *Spustit přerozdělení*).
* **Loyalty (gamification)**: points for paid orders (1 per 100 Kč), on-time payments, MFA, backups, monitoring,
  the first service and referrals (`config/onhost.php` → `loyalty.points`, `LoyaltyRouter`); levels bronze → silver
  → gold → platinum with a promo-credit reward on level-up (thresholds and rewards editable in
  `PUT /v1/staff/loyalty/levels`), badges (`guardian`, `archivist`, `watchman`, `ambassador`, `first-service`);
  `GET /v1/account/rewards` feeds the account page row; manual awards through `POST /v1/staff/loyalty/award`.

### 5j. What remains after §5i (proposed 2026-09-13) — built 2026-09-13

1. **Marketplace of partner services** — done: `MarketplaceService` (`domains/Marketplace`), listings by approved
   partners (`/v1/partner/marketplace/*`), staff publish/pause/retire (`/v1/staff/marketplace/listings/{id}/state`),
   customers order from credit with a step-up (`POST /v1/marketplace/{key}/order`; a paid tax document is issued, the
   platform keeps `ONHOST_MARKETPLACE_COMMISSION` %), the partner delivers, the customer accepts or disputes
   (`/v1/account/marketplace/orders/{id}/accept|dispute|cancel`); acceptance books the partner's share as a payable
   `PartnerCommission` (kind `marketplace`), a refund returns the credit with a credit note; deliveries nobody answered
   count as accepted after `ONHOST_MARKETPLACE_AUTO_ACCEPT_DAYS` (`onhost:marketplace:auto-accept`). Disputes reach
   support in *Finance* (*Spory marketplace*).
2. **Referral programme** — done: `ReferralService`; the invite code (`POST /v1/account/referral/code`) binds a new
   organization at registration (`ref` on `POST /v1/auth/register`, link `/registrace?ref=`), the first paid document
   rewards both sides (points + promo credit, once); abuse is refused with a reason: same non-public e-mail domain,
   an order held or rejected by the risk check, the same registration address as an earlier invite, the monthly cap,
   sandbox tenants. Account row *Doporučte nás*.
3. **Missions and streaks** — done: `MissionService`; five monthly missions (2FA on every member, a monitor, a tested
   restore, every document paid on time, a complete billing profile) award points once per month, a full month the
   badge `mission:month`; the on-time streak is counted from the documents, the target (`ONHOST_STREAK_MONTHS`) fires
   `loyalty.streak.reached` once, finance grants the permanent discount (`POST /v1/staff/loyalty/streak/{org}
   {percent}`), `QuoteService` applies it after the other discounts (never on plan changes). Daily
   `onhost:loyalty:missions`.
4. **Predictive rebalancing** — done: `NodeRebalancer::plan($role, 'usage')` reads the node's measured RAM instead of
   the sold RAM (`?basis=usage`, fleet action *Návrh podle využití*); `onhost:rebalance:plan --basis=usage --mail`
   runs nightly (03:35) and reaches the operations inbox as `rebalance.plan` with the loads before and after.
5. **Status page per organization** — done: `OrganizationStatusService`; off by default, switched on with
   `PATCH /v1/organizations/{id} {status_page: {enabled, title, show_monitors, show_incidents}}` (account row *Vlastní
   stránka stavu*); `/stav/<slug>` (+ `badge.svg`, `GET /v1/status/org/<slug>`) shows the customer's monitors by host
   name, the platform components behind their services, incidents and maintenance touching them. A CNAME
   `status.<their-domain>` may point at the portal host; the page answers under `/stav/<slug>` (vhost alias is an
   operator task).
6. **Chargeback analytics** — done: `ChargebackAnalyst` clusters reasons per product, node and theme (performance,
   reliability, price, support, features, moving); a cluster of `ONHOST_CHARGEBACK_CLUSTER_THRESHOLD` requests in
   `ONHOST_CHARGEBACK_CLUSTER_DAYS` opens one internal p3 incident on the matching status component (dedupe per
   cluster while open); `GET /v1/staff/chargebacks/analytics`, *Finance → Proč odcházejí*, daily
   `onhost:chargebacks:analyse`.
7. **Signed data export** — done: the export carries the audit trail (last 5 000 rows); `POST
   /v1/data-requests/{id}/link` issues a signed link (`/export/{id}/{token}`, 7 days at most, never past the export's
   expiry, a new link revokes the old, the token is stored hashed).
8. **Regional pricing and the customer's currency** — done: `PricingRules::regions()` (config
   `onhost.pricing.regions`, staff table `PUT /v1/staff/pricing/regions`), `QuoteService` applies the group's
   percentage to the list and renewal price and records `price_region` on the line (`region` on a line stays the
   placement region the provisioning reads); `GET /v1/catalog/regions?country=`;
   customers pick CZK or EUR with `PATCH /v1/organizations/{id} {currency}`.
9. **Sandbox tenants** — done: `POST /v1/staff/customers/{id}/sandbox {enabled}` sets `feature_flags.sandbox`, books
   the promo credit (`ONHOST_SANDBOX_CREDIT`, once) and routes every placement (provisioning and migrations) to
   instances flagged `options.sandbox`; production tenants never land there; sandbox tenants earn no loyalty and no
   referral rewards.
10. **Green hosting** — done: `GreenService`; the energy profile per region (`onhost.green.regions`, a node may
    override it in `tags.energy`), the footprint estimate of an organization (RAM sold × W/GB × hours × PUE ×
    gCO₂/kWh, stated as an estimate), stamped on every invoice and receipt (`meta.green`, PDF footnote), the public
    profile `GET /v1/green`, the badge `/green/badge.svg`, account row *Uhlíková stopa*.

### 5k. What remains after §5j (proposed 2026-09-13) — built 2026-09-13

1. **Partner portal surface for the marketplace** — done: seam #46 (`SurfaceRenderer::partnerSeams` +
   `api/onhost-partner.api.js`) adds the *Marketplace* tab to `Onhost-partner.dc.html` outside demo mode — listings
   (new, pause/resume, price edit → back to draft), jobs from customers (start, mark delivered), the open-jobs badge on
   the tab; every write goes through `/v1/partner/marketplace/*`.
2. **Marketplace subscriptions** — done: a monthly listing opens a `Subscription` (no service, period month) with the
   order; `onhost:marketplace:renew` (daily 05:00) charges the credit, issues a paid statement and books the partner's
   share of the period as a payable commission; the customer ends it with the paid period (`…/cancel` on an accepted
   monthly order); without credit the subscription goes past due, is retried daily and the listing ends after the
   grace period (`billing.dunning.grace_days`).
3. **Status page on the customer's own domain** — done: `status_page.domain` in the settings, CNAME verification to
   the portal host (`POST /v1/account/status-page/verify`, hourly `onhost:status:verify-domains`), the `StatusHost`
   middleware serves `/` and `/badge.svg` on a verified host, and `GET /v1/status/host-check?host=` is the on-demand
   TLS "ask" for the edge (Caddy `on_demand_tls { ask }`), so the certificate is automatic. A host verified by one
   organization cannot be claimed by another.
4. **Referral landing and attribution cookies** — done: the `RememberReferral` middleware stores `?ref=` from any
   public page in an unencrypted, HTTP-only cookie for 30 days; `POST /v1/auth/register` falls back to the cookie
   when the form sends no code (the form's code wins).
5. **Missions catalogue in settings** — done: `loyalty.missions.catalogue` in system settings
   (`GET|PUT /v1/staff/loyalty/missions`): key, titles, points, the check (`mfa_all`, `monitor`, `restore_test`,
   `on_time`, `profile`, `backup_done`, `services_min {min}`, `ticket_free`), a season (`active_from`/`active_to`)
   and an optional badge; an empty table restores the five built-in missions; seasonal missions count only inside
   their season.
6. **Measured energy** — done: `POST /v1/probes/power {readings: [{node, watts, at?}]}` (probe token) stores the
   node's wall watts (`usage.power_w`) and an hourly sample; while the reading is fresh
   (`ONHOST_GREEN_MEASURED_MAX_AGE_HOURS`) the footprint takes the service's RAM share of the measured node instead of
   the model (`basis: measured` on the row), the public profile reports how many nodes are measured.
7. **Predictive rebalancing from history** — done: `onhost:provisioning:sample-nodes` (hourly) writes
   `node_usage_samples`; `NodeRebalancer::trend()` = the 7-day p95 of RAM plus the slope of the daily averages
   projected a week ahead; `plan($role, 'trend')` (`?basis=trend`) rebalances before the peak; the nightly dry run uses
   it; fewer than twelve samples fall back to the last measurement.

### 5l. What remains after §5k (proposed 2026-09-13) — built 2026-09-13

1. **Partner portal on the API** — done: seam #47 (`SurfaceRenderer::partnerSeams` + `api/onhost-partner.api.js`):
   the overview (KPIs, tier table from `partners.tiers`, feed), the clients table and detail, the monthly commissions,
   the balance and payouts (a real `POST /v1/partner/payouts`), the white-label settings (saved with
   `PUT /v1/partner/whitelabel`, verified by the hourly scheduler) and the assets with the partner's own referral link
   read the partner API when it answers; every prototype literal stays as the fallback.
2. **Marketplace SLA** — done: `MarketplaceService::sweepOverdue()` (`onhost:marketplace:sla`, daily 05:10) warns the
   partner (`marketplace.overdue`) and the customer (`marketplace.delayed`) once past the due date; after
   `ONHOST_MARKETPLACE_OVERDUE_GRACE_DAYS` the customer is offered a refund (`marketplace.refund_offered`) and may cancel
   a started job without a dispute; orders carry `sla {overdue, days_overdue, refund_available}`.
3. **Custom-domain edge, packaged** — done: `infra/edge/Caddyfile.status-hosts` (on-demand TLS with the platform's
   ask) and `infra/edge/nginx-status-hosts.conf` (default server + certbot); `php artisan onhost:edge:config
   --format=caddy|nginx [--upstream=] [--write]` renders them with the portal host.
4. **Referral fraud scoring** — done: `ReferralService::assess()` scores signals (`same_email_domain`, `risk_hold`,
   `chargeback`, `same_address`, `refused_history`, `rapid_signup`, `many_pending`) with weights in
   `loyalty.referral.weights`; ≥ 100 refuses with the strongest signal, ≥ 60 holds for finance
   (`GET /v1/staff/referrals?state=held`, `POST /v1/staff/referrals/{id}/review {decision}`); a release lightens the
   signals that held it, a reject makes them heavier; `chargeback.approved` of a rewarded referral inside
   `ONHOST_REFERRAL_CLAWBACK_DAYS` claws it back (`referral.clawback`) and raises the weights.
5. **Mission campaigns** — done: `loyalty.missions.campaigns` in settings (`GET|PUT /v1/staff/loyalty/campaigns`): a
   bundle of catalogue missions with a badge, a window and a start mail; `onhost:loyalty:campaigns` (daily 05:20)
   announces an opened window once to every active organization (`loyalty.campaign.started`, mail
   `loyalty-campaign`); completing every mission inside the window grants `campaign:<badge>` once
   (`loyalty.campaign.completed`); the account page lists progress per campaign.
6. **Power per service** — done: on a measured node the watts split by the memory the hypervisor reports per service
   (`tags.usage.memory.used` from the usage watch); services without telemetry weigh their sold RAM (`split:
   telemetry|ram` on the footprint row).
7. **Trend on CPU and disk** — done: `NodeRebalancer::trend()` also returns `cpu_p95` and `disk_p95_gb`; a node hot
   on CPU (p95 ≥ high mark) or disk (p95 ≥ 85 % of capacity) is rebalanced although its RAM looks fine, the estimate
   of the other axes shrinking with the RAM share moved away; node rows carry `reasons`, `cpu_p95`, `disk_p95_gb`.

### 5m. What remains after §5l (proposed 2026-09-13) — built 2026-09-13

1. **Commission model as a contract term** — done: the portal's model buttons ask finance instead of switching
   (`POST /v1/partner/model {model, note}` → `partner_change_requests`, one open request at a time,
   `partner.model.requested` in the finance inbox); `GET|POST /v1/staff/partners/requests[/{id}/decide {decision, note}]`
   approves or rejects; an approval sets `partners.pending_model` + `model_effective_from` (the first of next month) and
   `onhost:partners:apply-models` (daily 02:35) flips the model then (`partner.model.approved/rejected/changed` to the
   partner); `GET /v1/partner/model` and the portal note show the state.
2. **Marketplace SLA credits** — done: a delivery accepted after its due date credits the customer
   `ONHOST_MARKETPLACE_LATE_CREDIT_PCT` (5 %) of the net price per day late, capped at `ONHOST_MARKETPLACE_LATE_CREDIT_CAP`
   (50 %), the moment the order is accepted (wallet credit, `marketplace.late_credit`); the partner share carries the
   credit (`late_credit_minor`, the commission row follows); `sla` carries `days_late` and `late_credit`.
3. **Edge as code** — done: `infra/ansible/roles/onhost_edge` (playbook `infra/ansible/edge.yml`) renders the flavour
   with `onhost:edge:config --out=` on the app host, ships `status-hosts.caddy|.conf` to the edge, validates
   (`caddy validate` / `nginx -t`) and reloads through handlers.
4. **Shared risk signals** — done: `OrderRiskService` scores `referral_flagged` (40) when the ordering organization's
   referral was held, refused or clawed back; `ReferralService` scores `referrer_risk` (40) when the referrer had an
   order rejected by staff inside 180 days; each loop's reject teaches the other's cross signal (+5), without cascading.
5. **Campaign analytics** — done: `GET /v1/staff/loyalty/campaigns/{key}/analytics` — organizations announced and
   completed, per-mission organizations and points inside the window, points in total, level-ups reached and their
   promo credit, completion rate.
6. **Per-VM power** — done: `POST /v1/probes/power` accepts `readings[].vms[] {id, watts}`; a VM is matched to its
   service through the provider binding (`remote_id` on the node's instance) and charged its own watts
   (`tags.power`, `split: vm`); the rest of the node splits what is left; unknown ids come back in `unknown`.
7. **Trend-driven pre-provisioning** — done: `CapacityForecast` — per role and region the sellable RAM (N+1 view)
   against what is sold and the measured p95 plus the summed daily slope of the 7-day trend → `days_left`;
   `onhost:provisioning:capacity-forecast` (daily 03:45) warns operations once a day per pool under
   `ONHOST_CAPACITY_WARN_DAYS` (30) or without headroom (`capacity.forecast.low`); `GET /v1/staff/capacity` carries
   `forecast`.

### 5n. What remains after §5m (proposed 2026-09-13) — built 2026-09-14

1. **Contract changes beyond the model** — done: every term goes through the request table — `model`, `rate_lock`
   (3/6/12 months), `payout_terms` (on_request/monthly/quarterly), `whitelabel_scope` (basic/full);
   `GET|POST /v1/partner/changes {kind, value, note}` from the portal's contract block under the model buttons (seam
   #48), one open request per term, finance decides with the same `partners/requests/{id}/decide`; money terms apply
   on the first of next month (`onhost:partners:apply-models`, daily), the scope at once; the basic scope keeps own
   mail / prices / support off (`whitelabel.limited`); monthly / quarterly terms request the payable balance for the
   partner (`onhost:partners:auto-payouts`, the 1st at 06:00, `partner.payout.auto`).
2. **Late credit on subscriptions** — done: a running monthly listing owes a deliverable per period; the partner's
   *deliver* on an accepted subscription reports it (`period_delivered_at`, `marketplace.period_delivered`); 80 % into
   an unserved period the partner is reminded once (`marketplace.period_due`, in the daily SLA command); a period that
   ended unserved credits the customer `ONHOST_MARKETPLACE_MISSED_PERIOD_CREDIT` (50 %) of the period price at the
   renewal, taken from the partner's next share (`marketplace.period_missed` + `_partner`); `sla.period` on the order
   and `sla.late_credit_preview` before a late delivery is accepted.
3. **Edge role in CI** — done: `.github/workflows/edge-role.yml` lints `infra/ansible` (`.ansible-lint`, profile
   moderate) and runs the molecule scenario `roles/onhost_edge/molecule/default` (Debian container with Caddy, a stub
   `php` that renders `--out`, converge, `caddy validate` in verify); `EdgeRoleCiTest` parses every file.
4. **One risk model** — done: `RiskWeights` (`risk.weights`) is the single table both loops read and teach; the legacy
   per-loop tables are merged on first read; a reject or release in either loop moves every signal that fired, the
   referral check now scores `disposable_email` with the shared weight; the console's risk tuning accepts any signal
   of either loop (`tuning.shared`, `tuning.referral` thresholds) and `reset` clears the table.
5. **Campaign cost forecast** — done: `POST /v1/staff/loyalty/campaigns/forecast {missions, …}` prices a draft —
   organizations reached, points at most and expected (the missions' 90-day completion history, default
   `ONHOST_LOYALTY_FORECAST_DEFAULT_PCT` 25 %), level-ups the points would trigger and their promo credit (ceiling and
   expected).
6. **Per-VM power without a probe** — done: `HostPowerReader` reads a node's `tags.bmc` (Redfish `EnvironmentMetrics`
   or `Power`, credentials from the secret store) inside the hourly usage watch before the snapshot; a hypervisor's
   per-VM `tags.usage.power_w` is charged directly (`split: vm`) and the node's remainder splits among the rest.
7. **Automatic pre-provisioning orders** — done: `CapacityPlanner` proposes one `capacity_requests` row per short pool
   sized like its largest node (`capacity.request.proposed`); operations approve, cancel, close a manual purchase
   (`delivered {node_name}`) or retry (`POST /v1/staff/capacity/requests/{id}/decide`, permission `capacity.manage`,
   step-up on approve); an instance with `options.node_order` (driver `hetzner`) orders the node from the vendor
   (`NodeOrderProvider`), the node row waits `pending` (never sellable) until operations put it active; the rule
   `capacity.auto_order` (off by default) orders without a human.

### 5o. What remains after §5n (proposed 2026-09-14) — built 2026-09-14

1. **Term-specific finance rules** — done: the rule `partners.auto_approve` (console, on by default) approves a rate
   lock up to `ONHOST_PARTNER_AUTO_RATE_LOCK_MONTHS` (6) and a payout-terms change for a partner approved
   `ONHOST_PARTNER_AUTO_CLEAN_MONTHS` (12) ago without a rejected payout; finance is informed
   (`partner.change.auto_approved`); the model and the white-label scope always wait.
2. **Period deliverables with evidence** — done: a listing carries `checklist[] {key, cs, en, kind: check|text}`
   (`meta.checklist`, set with the listing); the period report (`POST …/deliver {note, evidence}`) must tick or fill
   every item (`marketplace_checklist_incomplete` names the missing ones); the last periods' evidence sits on the order
   (`period_evidence`) for the customer.
3. **Molecule for the other plays** — done: the four roles `site.yml` names now exist (`onhost_proxmox_api`,
   `onhost_ispconfig_remote`, `onhost_powerdns`, `onhost_probe`) with molecule scenarios (PowerDNS and the probe for
   real in a Debian container, Proxmox and ISPConfig against stubbed `pveum` / `mysql` that record the calls and check
   idempotence); the workflow runs all five in a matrix.
4. **Risk model review across loops** — done: `GET /v1/staff/orders/risk-review` lists `referrals` (held, refused,
   released, clawed back) next to `orders`, counts both loops into one `signals` precision table, carries the shared
   `weights`; the CSV has a `loop` column.
5. **Campaign forecast in the console** — done: the prototype's `coupons` table view is *Věrnost a kampaně* outside
   demo mode — campaigns with *Odhad nákladů* per row and *Nová kampaň* that shows the forecast before the save.
6. **Redfish inventory** — done: the reader also takes `Thermal` (hottest sensor, failed fans) and `Power`
   (PSU health) into `usage.bmc`; a host at or above `ONHOST_BMC_TEMP_WARN_C` (75) or with a failed PSU/fan raises
   `node.bmc.alert` once a day per node.
7. **Vendor node bootstrap** — done: a vendor order carries cloud-init user-data (packages, the operator's key,
   `ONHOST_NODE_BOOTSTRAP_USER_DATA` override) with a one-time token; `POST /v1/probes/capacity/{id}/ready` marks the
   request ready (`ready_at`, the host's facts on the node's `tags.bootstrap`) and tells operations the playbook line
   (`capacity.request.ready`); the prototype's `nodecost` view is *Kapacita a nákup uzlů* with the pools, the
   requests and their actions, and *Spustit forecast*.

Also built with §5o (customer and staff convenience): the Spigot template (`minecraft-spigot`, the Paper egg through
`DL_PATH` when the panel has no Spigot egg, `{version}` from the order's `version` → `MINECRAFT_VERSION`), the staff
quick action *Založit herní server* (`POST /v1/staff/customers/{org}/services`, `onhost:game:create`), and full
control of every matched game server from the console's provisioning view (start / restart / stop, a console command,
the log, the live console token).

### 5p. What remains after §5o (proposed 2026-09-14) — built 2026-09-14

1. **Live game panel keys** — waits for the operator: the keys must be stored by a person
   (`php artisan onhost:integrations:secret pterodactyl-gamepanel application_key --check`, then `client_key`);
   the console's *Provisioning fronta* names the missing secret next to the panel. Everything after the keys is one
   command each: `onhost:game:bootstrap pterodactyl-gamepanel`, `onhost:game:create <org> --egg=minecraft-spigot
   --game-version=1.21.8` (or *Založit herní server* in the console).
2. **Console terminal in the admin** — done: `/sprava/konzole/{service}` (staff with `staff.service.manage`) — the
   log tail refreshed every five seconds, a command line (`command.send`), Start / Restart / Stop / Kill, the live
   console token; the provisioning view's *Konzole* opens it.
3. **Evidence with attachments** — done: checklist items of kind `file`; `POST /v1/partner/marketplace/orders/{id}/evidence`
   (multipart `key`, `file`, 10 MB, pdf/png/jpg/txt/csv/zip/log) stores the file next to the order, the period report
   consumes it, the customer downloads it from `GET /v1/account/marketplace/orders/{id}/evidence/{entry}/{key}`; the
   portal's *Odevzdat měsíční plnění* asks for the upload first.
4. **Molecule on the real vendors** — done as a nightly regression check: `onhost:nodes:check` (05:20) compares every
   instance's prerequisites with yesterday's — a new warning or a panel that stopped answering is
   `integration.prereqs.regressed`, a clean instance again `integration.prereqs.recovered`.
5. **Auto-approve for the model** — done: a switch back to `ONHOST_PARTNER_DEFAULT_MODEL` (share) after a clean year
   goes through `partners.auto_approve`; the other direction still waits for finance.
6. **BMC inventory in the fleet view** — done: the board's node rows carry `bmc {temp_max_c, fans_failed, psu_failed,
   psus, at}` and `power_w`; the console's fleet row shows *BMC 61 °C · zdroj mimo OK · 410 W* and turns hot on a
   failed PSU or fan.
7. **Bootstrap to active** — done: the readiness answer hands out a one-time `activate_token` + `activate_url`;
   the role `onhost_node_activate` (play `bootstrapped` in `site.yml`) posts it when the hypervisor is installed;
   `POST /v1/probes/capacity/{id}/activate` puts the node `active` with the reported size, delivers the request
   (`capacity.request.activated`) and the pool sells the new node at once.

Also built with §5p (customer convenience): the order wizard offers game templates per version (`key@version`,
newest first: Spigot 1.21.8 / 1.21.7 / 1.21.4 / 1.20.6) and carries the choice as `config.version` into
`MINECRAFT_VERSION`. The production-readiness audit is `docs/runbooks/production-readiness-audit.md`.

### 5q. What remains after §5p (proposed 2026-09-14) — built 2026-09-14

1. **On-call escalation** — done: `OnCallService` listens to the outbox; the paging events (`onhost.oncall.events`:
   queue stalled, integration down, BMC alert, SLA burn rate, capacity low, incident opened, prerequisites regressed,
   queue backlog) open one `oncall_alerts` row per subject and page the provider behind `ONHOST_ONCALL_PROVIDER`
   (PagerDuty Events v2, Opsgenie Alerts, or a signed webhook; the key in the secret store). Nobody acknowledging
   within `ONHOST_ONCALL_ESCALATE_MINUTES` re-pages with a higher severity (rule `oncall.escalate`, every minute, at
   most `ONHOST_ONCALL_MAX_ESCALATIONS` times); the console (`POST /v1/staff/oncall/alerts/{id}/ack|resolve`) or the
   pager's own webhook (`POST /v1/webhooks/oncall/{provider}`, PagerDuty v3 signature or `X-ONhost-Oncall-Token`)
   acknowledges; the recovery event (`onhost.oncall.resolves`) resolves on both sides. `POST /v1/staff/oncall/test`
   pages a synthetic alert. Without a provider the alerts still live in the console.
2. **Error tracking and tracing** — done without SDKs: `ErrorReporter` ships unexpected exceptions as redacted Sentry
   envelopes (`SENTRY_DSN`, tags correlation/request id, actor, command, operation); `Tracer` exports OTLP/HTTP spans
   (`OTEL_EXPORTER_OTLP_ENDPOINT`, `OTEL_EXPORTER_OTLP_HEADERS`) for every command (`CommandBus`), every operation
   step (`OperationRunner`) and every provider call (`ProviderCallLogger`), one trace per correlation id across web
   and worker. Both silent without an endpoint.
3. **Websocket console in the staff page** — done: `/sprava/konzole/{service}` connects to the relay
   (`ONHOST_CONSOLE_RELAY_URL/ws/<token>`) with the one-time console token, replays the last lines (`send logs`),
   streams `console output`, sends commands straight into the socket while connected (the API `command.send`
   otherwise), reconnects on an expired token, shows CPU/RAM from `stats`; a text file up to 512 kB goes to the
   server through the audited `gfile.save`. VNC consoles keep the token hand-off.
4. **S3 store for evidence and exports** — done: `FileStore` (`ONHOST_FILES_DISK` local | s3) behind marketplace
   evidence and data exports; a download from a signing disk is a 302 to a temporary URL
   (`ONHOST_FILES_SIGNED_TTL` minutes), from the local disk a stream; `onhost:files:prune` (rule `files.prune`,
   04:25) deletes evidence older than `ONHOST_EVIDENCE_RETENTION_MONTHS` and orphaned exports.
5. **Capacity budget cap** — done: `CapacityBudget` (`ONHOST_CAPACITY_BUDGET_MONTHLY_MINOR` or the console's
   `PUT /v1/staff/capacity/budget`) against the vendor's monthly price of every node ordered this month
   (Hetzner catalogue prices, `cost_minor` on the request); an automatic order that would cross it stays approved
   with `budget_hold` and `capacity.budget.exceeded` reaches finance; a person crossing it sends
   `override_budget: true` with a note naming who approved the spend (the audit row keeps it).
6. **Turnstile on registration and checkout** — done: `Turnstile` verifies the widget token
   (`TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`) once per request; registration refuses a missing or failed check
   while `ONHOST_TURNSTILE_ENFORCE_REGISTER` is on, checkout scores it as the risk signal `turnstile_failed` (35,
   tunable like every other); the boot object carries the site key, the session bridge renders the widget and sends
   the token, the CSP allows `challenges.cloudflare.com`. Off without keys.
7. **EN notifications** — done: `Lexicon` swaps the router's fixed Czech phrases for English before a customer or
   user row is written (`notifications.locale`), by the organization's (or the user's) locale; values stay; mails
   already had `en` templates.

Also built with §5q (operator rule: nothing is changed in the game panel's own UI, everything goes through the
Application API from the console): node limits — `PUT /v1/staff/integrations/{instance}/game/nodes/{node}` with
memory / disk / over-allocation / maintenance or `detect` (the daemon's RAM minus `ONHOST_GAME_NODE_RESERVE_MB`);
node discovery now refreshes stored capacity from the panel. The first live Spigot 1.21.8 (`srv_01m2era5hx2abepxmy79v1rfp5`,
gamepanel.onhost.cz, node ONHOST-GAME-TEST-01, 45.67.217.22:6665) was created, built (BuildTools through the
platform-written `onhost-start.sh`), started and commanded from the console — fixes on the way: `withVersion()` was
called on the anonymous step class, the allocation step reads the node's panel id from the row, power verification
accepts `starting`, `getActualState()` carries the live daemon state, game logs come from `logs/latest.log`.

### 5r. What remains after §5q (proposed 2026-09-14) — built 2026-09-14

1. **On-call rota** — done: `oncall_shifts` (staff account, start, end, note; shifts may not overlap) through
   `GET/POST /v1/staff/oncall/shifts`, `DELETE /v1/staff/oncall/shifts/{shift}` (`incident.manage`, audited). An alert
   stores its `assignee` and the pager payload carries the name; `status` of the alert list shows who is on call and
   the next shift; the daily staff digest ends with the hand-over line. Console: *Automatizace → Eskalace on-call →
   Rota on-call / Přidat směnu / Odebrat směnu*.
2. **Trace links** — done: `ONHOST_TRACE_URL` with `{trace_id}` / `{correlation_id}`; staff operation rows (board,
   jobs) and on-call alerts carry `trace_url`, the console's failed/stalled operation rows get *Trasa*.
3. **Binary file transfer** — done: `POST /v1/services/{service}/game-files/upload` (multipart, up to
   `ONHOST_GAME_UPLOAD_MAX_MB`, default 100) stages the file on the file store, scans it, then the audited action
   `gfile.upload` asks the panel for a signed upload URL (`GET /files/upload`) and posts the file to the daemon; the
   staging copy is deleted. The staff console's upload form uses it for every file.
4. **Virus scan** — done: `VirusScanner` speaks clamd INSTREAM (`ONHOST_CLAMAV_HOST/PORT`); an infected upload
   (evidence or game file) is deleted, audited and reported (`files.infected`); with `ONHOST_CLAMAV_ENFORCE` an
   unscanned evidence file cannot be downloaded (409) until `onhost:files:scan` (every 10 minutes) finds it clean,
   and a game upload while clamd is down is a retryable 503.
5. **Budget forecast** — done: `CapacityForecast::budget()` turns the 30-day growth beyond headroom into whole nodes
   and prices them (`provisioning.capacity_forecast.node_monthly_minor.{role}`, else the average cost of the role's
   past vendor orders) against the monthly cap; `/v1/staff/capacity` returns `budget_forecast`, the console shows the
   row, the daily pass tells finance once a month when the purchases would cross the cap (`capacity.budget.forecast_over`).
6. **Turnstile on public forms** — done: contact/support requests (`/v1/leads`), tender requests and the partner
   application (`/v1/reseller/apply`) refuse a guest without a passing check while `ONHOST_TURNSTILE_ENFORCE_FORMS` is
   on; the session bridge attaches the token to those posts; signed-in users are not asked.
7. **Lexicon coverage in CI** — done: `LexiconCoverageTest` renders every routed event for an English organization
   and fails on a Czech word left in a title or body; the phrases it found are translated.

### 5s. Game templates beyond the first server (proposed and built 2026-09-14)

The catalogue now sells 22 templates on gamepanel.onhost.cz (nest *Minecraft* and nest *Onhost Gamehosting*). Reading
the eggs through the API showed that several cannot be created with defaults: CS2 needs the customer's Game Server
Login Token (`STEAM_GSLT`) and an RCON password, DayZ downloads with a Steam account the customer may not change
(`STEAM_USER`, `STEAM_PASS`), Palworld / Project Zomboid / Rust want an admin or RCON password.

1. **Requirements from the egg** — done: the egg sync (and the daily `onhost:game:templates:verify`) stores for every
   mapping the required variables without a default (`required: [{env, rules, editable}]`).
2. **Generated passwords** — done: editable `*PASS`, `*PASSWORD`, `*SECRET` variables get a random value that meets
   the rule (`size`, `max`, `between`, `min`) when the server is created; the customer sees and changes them in Startup.
3. **Customer inputs at the order** — done: the offer lists a template's `inputs`, the panel's order wizard asks for
   them, the quote refuses a game line without a value that passes the rule (`game_template_input_required`).
4. **Operator-held variables** — done: read-only variables come from `db://game/operator-variables`
   (`php artisan onhost:game:operator-variable STEAM_USER`, hidden prompt); until they are stored the template is not
   offered and the quote refuses it (`game_template_unavailable`, reason `operator_variables_missing`).
5. **Offer follows the panel, RAM floor at the quote** — done: a template no active panel has mapped is not offered
   (`not_on_panel`), a plan below the template's `min_ram_mb` is refused (`game_template_ram_too_low`).
6. **Template drift** — done: `onhost:game:templates:verify` (daily 05:10, rule `game.templates.verify`) drops the
   mapping of an egg the panel lost and tells operations (`game.template.missing`).

### 5t. What remains after §5s (proposed 2026-09-14) — built 2026-09-14

1. **Operator variables from the console** — done: *Šablony her → Proměnné provozovatele* lists which read-only
   variables each template needs and which are stored, and stores one (`PUT /v1/staff/game/operator-variables/{env}`,
   `provider.instance.manage`, HIGH risk with a fresh step-up; the value travels as the stripped `secret` field, is
   written to `db://game/operator-variables` and never returned). The artisan command uses the same service. The DayZ
   Steam account itself is the operator's to enter.
2. **RAM floor in the wizard** — done: the panel catalogue carries each plan's `ram_mb`; the size step of a game server
   lists only plans that fit the smallest template and names the RAM, and the order refuses a plan below the chosen
   template's floor with the smallest fitting plan before any quote.
3. **Customer inputs after the order** — done: the Startup resource returns `attention` (the template's customer
   inputs whose value is missing or fails the rule) and the workbench marks them; the daily
   `onhost:game:templates:verify` reads running servers and tells the customer once a day (`game.setup.attention`).
4. **Rota as iCalendar and reminders** — done: `GET /v1/staff/oncall/shifts.ics` (stable UIDs), `POST
   /v1/staff/oncall/shifts/import` (ATTENDEE/ORGANIZER e-mail of a staff account, UTC or TZID times; a known UID updates
   its shift, overlaps and unknown people are reported); `onhost:oncall:remind` every 5 minutes (rule `oncall.remind`)
   sends `oncall.shift.starting` — feed and mail `oncall-shift` — an hour before a shift, once.
5. **Scan verdict in the partner portal** — done: uploads and report items carry `scan` (clean / unavailable /
   infected / none) and the upload message says *antivir: čistý / prověřuje se / zablokován*.
6. **ClamAV deployment** — done: Ansible role `onhost_clamav` (clamd on a private address over TCP, freshclam, ufw for
   the control plane) in `site.yml` (group `clamav`); `onhost:doctor` reads clamd's `VERSION` and warns when the
   signatures are older than two days or the scanner is not configured.

### 5u. What remains after §5t (proposed 2026-09-14) — built 2026-09-14

1. **clamd in the stack** — done: `infra/docker-compose.yml` runs `clamav/clamav:1.4` (freshclam inside, signatures on a
   volume) and points web and workers at it (`ONHOST_CLAMAV_HOST=clamav`); production hosts use the Ansible role
   `onhost_clamav` from §5t. A live clamd on the production network still has to be started by the operator.
2. **Trace backend** — done: OpenTelemetry collector (OTLP/HTTP 4318) → Tempo → Grafana with a provisioned `tempo`
   datasource; the compose stack sets `OTEL_EXPORTER_OTLP_ENDPOINT` and an `ONHOST_TRACE_URL` that opens the trace in
   Grafana Explore. `onhost:doctor` warns when spans are exported but the console has no trace link template.
3. **Template input form** — done: the offer describes each customer input as a field (`label`, `hint` from the rule
   — exact length, allowed characters —, `min`/`max`, `pattern`, `help_url`; the Steam GSLT links to
   steamcommunity.com/dev/managegameservers); the order wizard opens a form instead of prompts, the browser validates
   the pattern and length, the quote still decides.
4. **Personal rota subscription** — done: `POST /v1/staff/oncall/feed-token` issues a random token (only its SHA-256 is
   stored, one per user, a new one revokes the old) and returns `…/v1/oncall/feed/{token}.ics` once; the feed needs no
   session, answers 404 for an unknown token or a user who is no longer staff. Console: *Eskalace on-call → Odkaz pro
   kalendář*.
5. **Operator variable rotation** — done: the secret's `rotated_at` is compared with `ONHOST_GAME_OPERATOR_ROTATION_DAYS`
   (180); the daily template check reminds security once a month (`game.operator_variables.stale`, names only) and the
   console status carries `rotation`.

### 5v. What remains after §5u (proposed 2026-09-14)

1. **Production observability host**: collector, Tempo on object storage and Grafana behind SSO as an Ansible role
   next to Prometheus, with retention and alerting on span error rate.
2. **Customer-side input fixes from the notice**: the `game.setup.attention` notice links straight to the Startup field
   and the fix restarts the server.
3. **Feed token hygiene**: tokens unused for 90 days expire; the console lists who holds a subscription.
4. **GSLT validation against Steam**: check a token with the Steam Web API (`IGameServersService/GetAccountList`) when
   the order is placed, with the operator's Steam Web API key.
5. **Rotation from the console with a second person**: storing an operator variable asks a second staff approval.
## 6. Operator checklist before go-live

1. Production `.env` (`php artisan onhost:doctor` lists every blocking item): HTTPS origin, Redis, PostgreSQL, secrets
   driver (`openbao` or `db`), `ONHOST_STAFF_MFA_REQUIRED=true`, `WEDOS_TEST_MODE=false`, Comgate merchant, bank
   account, legal entity (`LegalEntitySeeder` with real IČO/DIČ/IBAN), mail sender and SPF/DKIM/DMARC, metrics and
   console-relay tokens. Remove the development accounts (`DevAccountSeeder`) and the lab instances.
2. Provider instances for production in *Nastavení systému → Integrace providerů*: Proxmox + PBS, ISPConfig, aaPanel,
   Pterodactyl, PowerDNS, RKE2 — credentials in the console, probes green, nodes discovered/registered, placements set
   per plan, IPAM pools per region.
3. Registrars: allow-list the production IPv4 egress at WEDOS, fix the Subreg API access (2026-09-07: both the account
   login `Niasee` and the API user `onhost_api` answer `500.104 Incorrect username or password` on every endpoint variant —
   enable the API for the user in the Subreg administration and check the allowed IP addresses / two-factor settings;
   until then the scraped public price list covers Subreg costs and availability of Subreg-won TLDs shows "nelze ověřit"),
   run *Registrátoři domén → Aktualizovat ceníky z API*, enter WEDOS wholesale prices, pin any TLD that must stay at one
   registrar. Queue workers must consume `provider-registrar`.
4. Legal review of `resources/legal/*.md` (drafts prepared by engineering; bump the versions in `LegalEntitySeeder`
   when the final wording is approved — customers keep the version they accepted).
5. Business decisions: commitment discounts are approved per product family in *Nastavení → Slevy, závazky a promo kódy*
   (none by default — the prototype's −10 %/−18 % is gone), domain discounts per TLD, promo codes and add-on prices are
   set there too (`docs/runbooks/pricing.md`); still open: top-up bonus (not implemented on purpose), dunning ladder
   wording (generated from config).
6. Prototype-only areas that remain narrated only in demo mode are listed in `docs/ui/data-seams.md` (seam #17).
