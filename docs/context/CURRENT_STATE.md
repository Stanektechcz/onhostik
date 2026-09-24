# Current state

**Updated:** 2026-09-19
**Branch:** `development`

## Now

- Finish the production version of the control plane; every change is tried on `staging.onhost.cz` against the real
  panels (ISPConfig, aaPanel, Pterodactyl, Proxmox, WEDOS) before it is called done.
- The whole cancellation path is in place (audit §5aa, §5ab): identity check → archive → deactivation → revocation
  of delegated access → restore window → removal → archive retention, with the numbers set in *Nastavení systému →
  Životní cyklus služeb*.
- The Brain's requirement cards drive the hardening now: a changed panel address locks the instance until a probe
  confirms it (H311), a maintenance lock never lifts itself (H322), a panel answer larger than the ceiling is refused
  before it can exhaust a worker (H318). A long run never binds a service to a resource the panel did not identify
  (H319), keeps a timed-out provider task on record and refuses a blind retry (H327), and a customer-started run asks
  for its permission again before each step, customer and staff runs alike, at the scope the bus checked (H315).
  What the panel shows about a service carries its age: a reading the reconciler has not refreshed is named stale
  (H325), and the panel API is reported apart from the service: a panel under maintenance refuses a customer's change
  with the reason and the planned end (`control_plane_maintenance`, 503 with `Retry-After`) while the service keeps its
  own state, and staff and system runs are not locked out (H324). Servicing a customer and moving their money are
  two permissions: the refunded share and an assisted order paid from the customer's credit or on invoice need the
  finance permission and a step-up, and no servicing role holds a billing, member or ownership right (H348). Every
  panel quota keeps a slice for health reads, so our own flood cannot make a working panel look down (H323). A new
  panel access is tried against the panel before it replaces the working one, and one instance can never be pointed at
  the stored access of another (H314). SSH keys on shell accounts are recorded by fingerprint with the member they
  belong to; a removed member loses them on every site, and a revocation a panel has not confirmed stays visibly open
  and is repeated (H185). A membership or a project role can end on a date: the permission stops by itself at that
  second and a scheduled pass then removes the role with the panel accounts and keys that came with it (H343). A member
  moved to a smaller role loses the keys and collaborator accounts the old role had put on the panels (H332). A game
  server takes its collaborators along when it migrates, with exactly the permissions they had, or does not switch (H341).
  A read-only role sees state and logs but no file contents, revealed passwords, build secrets or consoles, and taking
  data out is the separate `backup.download`; deploys seed the role catalog and the doctor fails on a drift (H334, H344).
  Under overload reports and overviews are refused so that actions, restores and payments keep their share (H139).
  A suspension belongs to whoever imposed it: a customer cannot lift a quarantine, a non-payment stop or an ended
  subscription, holds stack, and a refused suspend or resume no longer strands a service in a transient state (H17).
  Tenant isolation is swept: the owner of another organization walks every customer route addressed by this
  organization's identifiers and is refused everywhere, also with a body that would pass validation (H03). A legal
  hold now suspends every deletion — archives past retention, backup generations, manual deletes — not only the
  termination of the service (H18). A change asked for while a panel is away is queued and carried out exactly once,
  plan limits included (H02). No panel credential reaches a log: answers that are themselves a credential are withheld
  whole (H12). A domain renewal the registry refuses frees the money, reaches the customer and operations the same
  day and is tried again on every pass until it goes through, paid once (H23). A VPS or a game server that stops on its
  own raises one alarm that names the service — to the customer, and to operations where an SLA class is at stake —
  and none when the stop has an explanation (H14); an incident reaches only the affected customers and shows them
  nothing internal (H16). A cart the catalogue does not offer is refused before an order, a document or a movement of
  money exists, and an order keeps the plan version, limits and price it was sold with after a new version of the plan
  is published (H01). Work outside the plan is offered on the ticket with a price and billed only after the customer
  approved it, for exactly that price (H29). A plan is never edited: staff publish a new version (limits, prices) with a
  step-up and a reason, new orders get it, existing customers keep theirs, and an earlier version can be put back on
  sale (H01, `/sprava/nastaveni/tarify`). A placement holds the space it took until the node is measured again and
  placements are serialized, so a burst of orders cannot share one reserve; a server no node can take is refused in the
  cart (H04). A maintenance window is taken out of the SLA only when a second person approved it at least 48 h before
  it starts; an emergency, late-approved or back-dated window counts as downtime, and the status page says which (H15). A downloaded archive verifies and restores with stock tools only and says what it lacks (H28). The
  customer's monthly budget can be set, starts again with the month, and holds for renewals and metered usage too (H30). When the platform's own mail stops leaving, staff and the pager hear it once, dunning
  holds its suspensions meanwhile, and what gave up is sent again after the first real delivery (H24). The customer API keeps only the parameters a customer may choose for a core action: no
  free resize, no skipped archive before a cancellation, no restore into another VM — through the API, stored action
  hooks and Discord alike (H21). An order delivers exactly the options that were priced: only what the product sells,
  within each option's range. A security review of the order, payment, egress and write paths (2026-09-20)
  closed: a guest-checkout replay that signed the caller in as another account, orders readable by a foreign
  idempotency key, VAT treatment and price region taken from the request, a captured payment rolled back when the
  order could not be marked paid, server-side requests into the management network (uptime checks, webhooks, import
  URLs — `EgressGuard`), bearer tokens reaching the account and the step-up (`token.scope`), and roles granted above
  the granter's own. Second batch: promo codes are counted, custom vhost directives are judged statement by
  statement, reverse-proxy upstreams stay off the node's panels and the network behind it, a forged payment callback
  cannot swallow the real one. The rules are written down in `docs/runbooks/security-boundaries.md`. Event payloads keep who wrote a message (`author_*`): the outbox masked
  `author_type`, so a reply of support never reached the customer — fixed, with a guard test over every published key. A member removed from an organization loses their collaborator accounts on its game servers at once, and a
  weekly review reports panel accounts of people who are not members (H333, H332). Assessments live in the
  vault (`Hosting/ASSESSMENT-2026-09-17`, `Hosting/ASSESSMENT-2026-09-19`).
- What remains is operational: the staging run of the new lifecycle, the payment gateway and bank tokens, staff MFA,
  the virus scanner, the console relay and the production deployment itself (`docs/runbooks/go-live-checklist.md`).

- A second review (2026-09-20: registrars, the four panel adapters, the staff side, the order-to-cash path) closed what
  could be proven from the repository. **Money:** an order is *settled* — delivered lines are captured from the
  reservation, undelivered ones go back with a credit note and a message (`OrderSettlement`; the reservation used to
  run out after a day and hand everything back); a postpaid invoice paid from credit settles the receivable instead of
  booking revenue and VAT twice; unpaid orders expire; a renewal closes only the dunning case it caused; periods do not
  overflow months (`BillingPeriod`); a quantity that cannot be delivered is refused. **Secrets:** a domain's transfer
  code is never kept or returned, SOAP bodies / private keys / command-line passwords are masked in `provider_calls`,
  the replay store and a sent mail keep no one-time secret. **Accounts:** the lock is asked before the password, wrong
  authenticator codes count, a reset link does not sign in an account with a second factor, enrolling TOTP takes a
  step-up, the GDPR export needs `organization.manage` and is audited. **Panels:** cancelling a mail service no longer
  touches a stranger's FTP/shell accounts, a retried Proxmox clone adopts the guest it made (H38), long TXT records
  publish, the reconciler's auto-repair no longer dies on a missing import. Rules: `docs/runbooks/security-boundaries.md`,
  `docs/runbooks/billing-dunning.md`. What the review found and did NOT fix is listed in
  `docs/runbooks/production-readiness-audit.md` §"Review 2026-09-20".

- One service can be **shared** with another person with named capabilities and an end date (guest membership,
  resource-scoped bindings, revocation takes panel-side access with it); the **AI assistant** answers and proposes
  with the signed-in person's permissions and works for support over one customer's account
  (`docs/runbooks/service-sharing-and-assistant.md`). Services stranded in a transient state are released by the
  scheduler.

- **The mailboxes a web plan sells can be used:** the web service offered no mailbox action at all (the actions hang
  off `mailboxes`, the web branch only set `mail`), and there was nowhere to put one — the mail domain is now made at
  the first address, with DKIM and the mail records, and removed with the service.

- **A migrated site stops serving before it is copied** and starts again if anything fails before the switch, so
  nothing written between the copy and the switch is lost; the panel says so in the customer's own words.

- **A web hosting can be moved to another node** (`WebMigrationWorkflow`, so a shared node can be drained): target →
  readiness → fresh archive → site on the target → data → switch → certificate → remove the source. A database is
  carried only when the platform holds its password, and arrives with the same name, user and password.

- **A certificate is asked for only the names that already point at the node** (`DomainPointing`): the rest are
  recorded as waiting, and when nothing points here the authority is never asked (its five checks an hour per
  hostname are not spent on a domain that has not moved yet). With no node address known, the panel decides as before.

- **The panel lists services by state too** — „Vyžadují pozornost“, „Zřizují se“, „Rušené“ — through the category
  seam the sidebar already had; a view appears only when it holds something and never counts a service twice.

- **The address a node sends mail from is asked of the blocklists** every day (`BlocklistCheck`): one spammer on a
  shared node makes every other customer on it bounce. Operators hear it once a day per node with the list and the
  code; the customer never does, because the address and the delisting are both ours.

- **What public DNS answers is compared with what the platform published** (`PublicDnsCheck`, daily): the site's
  address, MX, SPF, DKIM and DMARC. A record missing from a zone the platform runs is repaired silently; what only
  the customer can change is told once a day, in the panel and as a notification naming the records to set.

- **Every publisher of system DNS records names what it owns** (`web:<service>`, `mail:<domain>`): it removes only
  its own records, records of the kind it publishes, and what cannot stand beside them (CNAME). Before this the mail
  saga deleted the site's A records and the website saga deleted the domain's MX. `MailSettings::records()` is the one
  builder for a domain's mail records, and a guard test validates everything the platform generates.

- **Mail follows the domain of the address:** a web service can have mail in every domain it hosts, each with its
  own mail domain, DKIM and DNS (`MailDomains`). Listings, suspension, removal and the final archive walk all of
  them, and the chat can read a web hosting's mail settings.

- **A number of the plan is the plan's, not each site's:** the databases, mailboxes, FTP accounts, cron jobs and
  subdomains of a web hosting plan are counted across every site the plan carries (`PlanAllowance`), and a plan change
  or an add-on reaches those sites (`ServiceSites::spread`). A site keeps its own share of the space and its shell user.

- **A suspension reaches the mail too:** a suspended site stops sending and keeps receiving, and exactly the
  mailboxes the platform stopped come back. A mailbox update now reads the record and merges (ISPConfig takes an
  update as the whole record) and never sends the stored password hash back.

- **A site is looked at for the marks of a compromise:** `SiteIntegrityCheck` (`onhost:sites:integrity`, daily) finds
  code in upload folders and the fingerprints of ready-made web shells with one `find` and one `grep`, and tells the
  operators once a day per site. It never acts: that stays with an abuse case, which has a way back.

- **A quarantine ends:** an abuse case that suspended a service cannot be closed without saying whether the service
  comes back; `liftHold(ABUSE)` had no caller at all, so a customer who won the appeal stayed suspended for ever. A
  service another open case still holds is never released by closing this one.

- **Traffic is measured where the panel cannot:** aaPanel has no traffic counter, so a managed site's sold traffic
  was measured against nothing; it is now summed from the site's own access log for the current month, and a log
  format the platform cannot read reports "not measured" instead of zero.

- **A rate limit from the certificate authority is its own answer:** 429 becomes `RATE_LIMIT` with the authority's own
  `Retry-After`, the certificate carries `rate_limited_until` so the nightly renewal steps over it, and renewals are
  spread by up to six days so a batch issued together does not come due together. (Two latent bugs fixed on the way:
  the ACME account call passed null where a string was declared, and its key was generated without an OpenSSL config.)

- **A web node says how full it is:** `NodeUsageSync` (`onhost:nodes:usage`, every 15 minutes) writes the disk of
  every ISPConfig and aaPanel node onto the node, so the scheduler's 85 % rule and the acceptance headroom finally
  have a number instead of a zero; a node below its own headroom is reported once a day (`node.disk.low`).

- **A used-up plan stops growing:** at 100 % the customer is told (level `full`) and `UsageGuard` refuses the
  actions that would store more — while deleting, backups, restores and a plan change stay open. It reads the last
  measurement (no panel call) and only while it is fresh, so a stale number never blocks a site for ever.

- **A plan change fits what the service holds, and reaches the panel:** `PlanFit` refuses a plan that sells fewer
  sites than the service has (or less space than its sites hold, or no staging, or not their PHP version) before any
  money moves, naming every reason at once; the ISPConfig client's limits now follow the plan, so a paid upgrade is
  not refused by the panel it was bought for.

- **The plan's sites are sites:** „10 webů“ used to buy nine extra NAMES on one site. `site.create` / `site.delete`
  (`ServiceSites`, `SiteWorkflow`) give each one its own document root, PHP version, certificate and backups, capped by
  the plan and by the space of the plan, which is divided between the sites and never doubled.

- **A service ends the sites it carries:** a web hosting service holds further sites of the same customer as
  services of their own (`tags.billing = included`) — the test copy today. `IncludedServices` names them and the
  cancellation, the removal, the suspension and the resume all reach them; each site is archived by its own
  cancellation before anything is removed, and only the hold the platform imposed is lifted again.

- **The assistant proposes what the platform learned since:** restore test, staging, deployment rollback, rescue end,
  Node.js apps, HTTP/3, game schedules, database export, mailbox backup, wildcard certificate — same allow-list rule; the
  tool description is generated from the list; refusals say why; no button that would end in an error.

- **The automatic drain judges the node, not its panel:** `OperationsBoard` counts the operations of the services placed
  on each node, and a quiet drained node comes back when the node itself answers (cluster `online`, its Wings daemon).

- **A panel on a version nobody verified takes no new orders:** `PanelVersionGate` compares the version the health
  probe reads with what the adapter declares and verifies a change on passing checks; a held instance is not usable for
  new work, services on it are still managed; `onhost:integrations:versions --accept` (runbook panel-upgrade.md).
  The evidence includes an identity sample of the instance's services (H517); maintenance is refused while the panel
  still carries out tasks for the platform unless `acknowledge_running` (H519).

- **A VM moved by HA is followed:** the reconciler moves the binding, the VM record and the service's node after a VM
  that answers from another node — once it proves to be the service's (service tag, else name); otherwise a drift.

- **A panel's breaker trips for the panel, not for one guest or one node:** requests marked `judgedByCaller` leave the
  verdict to the adapter — Proxmox refusals and unreachable nodes, a Pterodactyl node's daemon, are answers; failures
  ISPConfig, WEDOS and Subreg report inside an HTTP 200 now count; Wings is called under a breaker per node.

- **The number of a new VM is never one the platform gave before:** `VmidReservations` holds it per cluster
  (`vmid_reservations`), above every number ever held or bound, skipping live guests and numbers with backups; a lost
  clone answer waits for its own clone. Proxmox refusals are read from the HTTP status line; a guest is missing only
  when the whole cluster says so.

- **An expired VPS archive is gone from the backup server too:** `FinalArchive::prune()` removes the protected backup at
  the provider (`ExpiringBackups`) before it marks the archive expired; until then the archive stays `completed`.

- **A game server is deleted with everything of it, or not at all:** the plain panel delete removes the files on Wings
  and the databases on the database host or refuses; `/force` only behind the instance switch `terminate_force`.

- **A VPS firewall change is made before it is broken, keeps the customer's order, and warns before a lockout:**
  new rules go in on top before the old ones are removed, a refused change puts the whole previous policy back, and a
  rule set that closes SSH/RDP needs `accept_lockout` (the way back is the console).

- **A site's Node.js app ends with the site, stops with it, and is nobody else's:** aaPanel apps are owned by the
  site root or below its slash (a bare prefix gave `shop.cz` the apps of `shop.cz.eu`), removed before `DeleteSite`
  and paused by `SuspensionDepth`.

- **A node must make and remove one thing before it carries anybody:** `SyntheticService` creates a throw-away
  resource from the owner's per-role template, confirms it, removes it and confirms it is gone; a leftover fails the
  node. No template configured → nothing is created (the default).

- **A node nobody has qualified sells nothing:** a discovered or freshly installed node is born `qualifying`; the
  scheduler cannot see it until `NodeQualification::accept()` passes every required point (`onhost:nodes:qualify`).

- **A promised restore test is really made:** `restore.test` restores the newest set into databases of its own,
  compares the tables that come back, removes them again, and reports what it found (`RestoreTest`).

- **A SQL import keeps a copy of the database it overwrites:** room is checked, that one database is exported and
  kept protected, and a dump the panel refuses is rolled back (`service.database.import.failed`).

- **A schedule that keeps failing stops itself:** five failed scheduled backups in a row pause the schedule
  (`service.backup.schedule.paused`); only a person setting the schedule again starts it.

- **A suspended game server's schedules are disarmed too:** `SuspensionDepth` is driven by a table of kinds
  (`cron`, `ftp`, `schedule`) and switches back on exactly what it switched off.

- **A scheduled command runs as the site's own user, and ends with the service:** aaPanel's scheduler is the node's
  root crontab, so a cron body is now a quoted here-document handed to the site's user, judged statement by statement
  (`CronCommand`), and removed from the node when the service is terminated.

- **A missed backup slot is not silent:** the scheduler writes down every slot it could not run and reports the third in a
  row (`service.backup.schedule.stalled`); a slot that runs clears the count.

- **A destructive action is previewed, and a stale confirmation is refused:** `DestructivePreview` names what goes, what
  hangs on it and how far back one could come, and its fingerprint makes a confirmation that no longer fits fail 409.

- **A button a person cannot press is not offered:** `features($service, $actor)` gates every feature on the permission the
  command bus will ask for and says why it is gone (`permission`, `state`) — a read-only collaborator sees no dead buttons.

- **ISPConfig is followed by its own change log:** a named write carries its `sys_datalog` row; an error there fails the
  operation and an applied change finishes it at once, however busy the server is (opt-in on the nightly probe).

- **What a destructive action replaces is kept first:** a restore, a rollback and a game reinstall each take a protected copy
  (`safetyCopyStep`) before they overwrite anything; when the copy fails, nothing is overwritten.

- **A broken server can boot something else:** `RescueMode` (H233) — an image from the node's ISO storage, a window that
  ends by itself (`onhost:services:rescue-expire`), and exactly the boot order it found going back.

- **A server's reverse record reaches the resolver:** `DnsService::reverseZoneFor`/`syncPtr` publish the PTR into the reverse
  zone the platform holds; the customer sets it with `rdns.set`, releasing the address removes it (migration `000810`).

- **A plan promises only what the platform holds:** `PlanPromises` — every number in `entitlements`/`limits` is read by code or
  declared fair use; the transfer and the file count are measured against what the panel reports; a key can leave a plan.

- **A paid add-on changes the service it was bought for:** `Addons` applies it, writes down what it replaced and gives that
  back on cancellation; an add-on the platform cannot deliver is not on sale, and an add-on can now be cancelled at all.

- **The assistant's buttons are the platform's:** an allow-list of proposable actions with their own parameters and platform-made
  labels (`AssistantProposals`) — the model could put `command.run` under "Vyčistit cache" on a confirm button.
- **Access to a VPS after delivery:** `access.reset` sets new SSH keys / a password through cloud-init (step-up; never proposed
  by the assistant).

- **The password mail tells the truth:** after a password CHANGE the API tokens stay valid — the mail said they were signed out; it
  now says how many stay valid and where to revoke them (a reset still revokes them).

- **The model has a budget:** `AssistantBudget` — per person per hour, per organization per day, a daily ceiling of tokens; past it
  the assistant answers from the help centre (nothing is refused). The staff assistant's conversation id did not fit its column
  (500 on PostgreSQL) — fixed; git deploy says its limits (422) instead of failing at the database.

- **The production database's only test is CI:** `pest-postgres` was red for 92 runs (a staff credit would have answered 500 on
  PostgreSQL: an 81-character key in a 60-character ledger column). Staff money actions (credit, assisted order, service,
  points) carried the second in their command key: when the HTTP layer had no stored answer to replay, a retry was a new command —
  now `onceKey()`. Fixed, guarded by
  `DeclaredColumnWidthTest`, and the release runbook now says: look at the workflow after every push.

- **A domain has an end:** one that left the registrar's account (asked a second way, away for 36 h) is closed —
  `TRANSFERRED_OUT` / `DELETED`, renewals stopped, told once, reopened if a registrar lists it again; it used to stay ACTIVE for
  ever with a nightly notice.

- **A quantity is that many lines:** `qty: 3` becomes `l1`, `l1#2`, `l1#3` — own price, discount share, service, subscription and
  document line each; add-on lines are copied with their parent. The storefront cart offered a quantity the server refused.

- **VAT in CZK on documents in another currency:** an EUR tax document carries the ČNB rate of the supply day and its VAT in CZK
  (PDF, e-invoice BT-6/BT-111, API); a credit note uses the original's rate; `onhost:fx:sync`; a bank that does not answer never
  stops a document (`czk_pending`, completed later; doctor area `money`).

- **DNS is compared every night and bounded in size:** `onhost:dns:drift` (what the provider serves vs what we hold; doctor area
  `dns`; a zone that is gone at the provider is `hot`, a comparison that could not be made is kept apart), at most 500 records
  and 200 waiting changes per zone. The repair is `POST /v1/dns/zones/{zone}/republish` (the provider gets what the platform
  holds; a lost zone is created again; verified by a second read). PowerDNS read TXT values in wire form — a TXT record could be
  neither replaced nor removed; fixed. The WEDOS zone adapter repeats a half-failed batch without doubling rows, always commits,
  and renames a record as delete + add.

- **A plan change never resizes the node:** aaPanel's `SetPHPMaxChildren` is per PHP version for the whole node — one customer's
  plan change set it for everybody; `resize` on aaPanel now touches nothing on the node.

- **Staff reads leave a trail, and roles read what they may change:** `StaffReadAudit` (`staff.read.*`, visible in the customer's own
  audit); `support.ticket.read` was the platform owner's alone — every support role got 403 on the queue and the ticket detail.

- **Promo codes do what their form says:** a fixed-amount code is spent once per order (it was applied to every line), and a code
  that is not "first period only" discounts the renewals too (the box was stored and never read).

- **The game panel's links are followed only to its own daemons:** signed backup downloads and file uploads must point at a node
  of that panel (no redirects); the adapter reads the instance's TLS settings like every other one (`tls_ca`, `wings_tls_ca`).

- **A failed operation takes back only what it made:** compensations delete through `CompensationGuard` — this operation's
  own binding, confirmed by the panel (`ServiceIdentityCheck`); a failed VPS clone used to stop and destroy the stranger's VM
  that had taken the reserved vmid. What cannot be confirmed is kept, audited and reported.

- **Premium names are not sold at list price:** the search answers `premium`, the create asks about the name once more and
  refuses a premium (or since-taken) one, renewals and transfers of premium names stop before anything is sent or held.

- **A suspended site is more than a stopped vhost:** suspension (and the deactivation of a cancelled service) switches the
  site's cron jobs and FTP accounts off and remembers which; resume switches exactly those on. A VM its owner had switched
  off is not started when a suspension is lifted. aaPanel's cron switch toggles — an unchanged `active` used to turn a job off.

- **Domains are not lost to a calendar:** a registrar listing without a status is not read as "active" (a domain in redemption
  came back as ACTIVE); an unpaid renewal is retried daily through the protective period after the expiry and at once after
  a top-up (it used to be abandoned the day before the expiry).

- **AI for support:** a reply to a ticket is drafted for the agent from the conversation, the account facts and the health
  check of the ticket's service (`POST /v1/staff/tickets/{id}/draft`; a model gets no tools, masked data and no internal
  notes; rules without a model; nothing is sent). What a person types into the chat is masked (`SecretMask`) before a
  model or a transcript sees it. **Dunning acts on the service:** a suspension that did not happen is asked for again
  daily, a case closes only once the service is down.

- **A name on a shared node belongs to one service:** the 30-bit name prefix of a service is a unique column (`000760`);
  a colliding id is replaced before anything exists, cancelled services keep theirs, the doctor names old collisions.

- **Corrections that hold (2026-09-20, night):** the state of an order is not written by hand — the transition endpoint
  took any state from `staff.order.manage` outside the command bus (a support agent declared their own order paid and got
  the services for nothing); a person now only cancels, through the bus. A cancelled paid order credits its documents and
  gives the reservation back. A credit note knows the line it corrects (`000750`): nothing is credited twice, a partly
  credited document is paid for what it has left, a booked document gives its VAT back too. Money that comes back is a
  `returnToCredit` against revenue and VAT — not a top-up from a bank called "chargeback", not purchased credit that turns
  bonus credit into cash. The return of an unused period is computed from the paid document lines, not from one period's
  list price without VAT. Doctor area `money`. Runbooks: `billing-dunning.md`, `security-boundaries.md` §13.

- **Money and privacy fixes of 2026-09-20 (evening):** a date on a document is the day at the seller's seat
  (`AccountingClock`) — the tax date and the number series came from UTC, so 1 January 00:30 in Prague was invoiced into
  last year; automatic top-ups keep the daily cap and the monthly limit the customer set (neither ever applied); a
  conversation with the assistant belongs to one person — without a session id the transcript was keyed by the IP
  address and continued by whoever came next from it. The assistant reads DNS records and one document for whoever may
  read them in the panel; `onhost:staging:report --check` writes one redacted file of how the installation stands.

- **Control points are numbers** — `onhost:doctor` and the operations board show p50/p95 of every action by panel
  (`OperationLatency`, target `ONHOST_LATENCY_TARGET_SECONDS`), name web services without a recent backup and finished
  operations that still hold secrets; the nightly prerequisites pass asks every panel which of the calls we rely on
  it really answers (`SelfProbing`). The assistant answers "is my service all right?" from the platform's records
  (`ServiceHealthCheck`, also `GET /v1/services/{id}/health`), with a model or without one. Loyalty operations that
  move money (manual points, level rewards, held referrals, streak discounts) take a step-up. The service overview
  read backups in a state no backup ever has (`available`), so it never saw one — fixed.

- **Operations forget their secrets** (`security-boundaries.md` §11): passwords and keys an action carried leave the row
  when it is done; a generated WordPress administrator password is shown for half an hour to whoever manages the
  service — it used to be readable by every viewer of the service, for good. A sweep cleans the rows of the past.

- **Four eyes work** (`docs/runbooks/approvals.md`): a critical staff action (legal hold, roles, mass credit, tax rules,
  secrets) opens a request when one person tries it alone; somebody else who could do it themselves approves it behind
  their own step-up; the approval is spent once by exactly that command. A command can no longer talk a critical
  permission down. One operator alone runs with `ONHOST_FOUR_EYES=false` set on the server — never from the application.

- **Backups are fresh and whole, or they fail** (`docs/runbooks/backups.md`). A backup of a web service is the
  platform's own set — site files + every database, off the node, checksummed — for manual, scheduled and pre-push
  backups on both panels; panel backups (games, VMs) are recognised by the name the panel gave them or as the entry
  that was not there before, and the final archive follows the same rule. Found on the way: both file transports
  refused the path `.` (packing a whole site never worked on a real node), core actions were not gated by family
  (a `backup` on a mail service reached a stranger's web site on ISPConfig), the ISPConfig backup calls sent the
  site's id where the panel expects the backup's. The ISPConfig calls are written from the API's documentation, not
  checked against a live panel — staging checks are in the runbook.
- **A web name belongs to one service** (`docs/runbooks/security-boundaries.md` §18, audit row 89). On a shared node
  the web server picks the site by the host name alone, and only one place checked who holds it. `ssl.issue` and
  `ssl.wildcard` took any name — and because a neighbour's domain answers at the same node, the pointing check from
  audit row 82 could not tell them apart, so the platform asked a certificate authority for certificates on other
  customers' domains (five failed validations an hour per hostname is enough to keep the owner from getting one);
  `subdomain.add` wrote any name into the customer's own `server_name`; and the order copied `config.domain` and
  `config.aliases` out of the cart unread. `SiteNames` answers it in one place now — a live service's name is its
  own, a name under another organization's name is theirs, a name under your own is yours, the rest is free — with
  web vhosts and mail domains as separate namespaces. The cart refuses before payment (`site_name_taken`), and
  `desiredSpec` refuses again inside the creating transaction so two carts racing for one name end there.
- **A claim has to be proved, or it is a squat** (audit row 90, the counterweight to row 89). Once a name belongs to
  one service, ordering the cheapest hosting for somebody else's domain and never pointing it anywhere would keep
  its real owner from ever being hosted here. Nothing new is asked of an honest customer: the platform already knows
  three proofs (the domain is registered here, its DNS zone is here, or the name answers with the node), and the
  daily DNS check computes the third anyway — so `SiteClaim` rides along with it, writes `tags.name_claim`, and
  raises `service.name_unproved` to the operators when a claim stays unproved past 30 days
  (`ONHOST_SITE_CLAIM_GRACE_DAYS`). Nothing is taken away automatically. The refusal now says to write to support,
  and when the refused party can prove the name while the holder cannot, `service.name_disputed` records it.

## Verified baseline
- Remote: `github.com/Stanektechcz/onhostik`, default branch `development`.
- Pest: 889 tests, 14 766 assertions green; Pint clean; Larastan level 5 clean (the baseline holds the older typing
  debt, new code passes without it).
- CI: `tests.yml` (Pint, Pest, Larastan, Composer audit, the same suite on PostgreSQL 16), `security.yml` (gitleaks
  over the history, Composer and npm advisories, frontend build), `e2e.yml`, `edge-role.yml`; Dependabot weekly.
- Live orders were placed and verified through the panels for web hosting (ISPConfig and aaPanel), WordPress, e-shop,
  a custom web, mail and a Minecraft Vanilla 1.21.8 game server.
- `onhost:doctor` is the readiness gate: environment, storage, automation, secrets, TLS, providers, payments,
  documents, identity, mail, observability and the deletion lifecycle.

## Known risk

- A local `.env` holds provider credentials. It never enters prompts, logs, commits or generated context; the
  pre-commit hook (`git config core.hooksPath .githooks`) and the security workflow enforce that.
- One staging web service is still stuck mid-termination from before the archive fallback existed; it is unblocked
  with `onhost:services:purge --service=… --force --reason=…`.
- `s4s.electree.cz` was deleted on the live ISPConfig node by the resource-type confusion fixed in §5z; the site is
  recreated from the panel's data log with `onhost:ispconfig:restore-site` and its node backup.
- The production-readiness audit still lists open P0/P1 items: `docs/runbooks/production-readiness-audit.md`.
- Playwright: five panel navigation/session scenarios fail and keep the E2E gate advisory rather than blocking.

## Next decision

Run the lifecycle verification on staging (archive on each panel with `onhost:services:archive --create`, then the
purge), restore `s4s.electree.cz`, and only then move the go-live checklist to the production host.
