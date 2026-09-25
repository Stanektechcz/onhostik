# Current state

**Updated:** 2026-09-25
**Branch:** `development` (integrated through TASK-0016, PR #19) + the stack TASK-0017 … TASK-0027 on
`fix/TASK-0027-stack-coherence-and-the-docs-that-descri`, waiting for one pull request into `development`

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

- **A password change ends the old API tokens** (owner decision 14, TASK-0021, audit row 28): changing the password
  revokes every personal API token of the user in every organization (a reset always did); the changing browser stays
  signed in; service-account tokens and integration secrets are untouched. `ONHOST_PASSWORD_CHANGE_REVOKES_API_ACCESS=false`
  restores the old "tokens kept" path with its truthful mail. **The game panel password is the owner's** (decision 15,
  audit row 106): `panel.password` needs `service.panel_account.manage`, held only by the `owner` role, and
  `OwnerOnlyActions` refuses anyone but the organization's `owner_user_id` in person.

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
- **A certificate that quietly expired** (audit row 91, `docs/runbooks/web-tools.md`). A site certificate lasts
  ninety days and the panel renews it — until it does not, and then nothing said so: the health check answered from
  `tags.access.certificate`, a flag written once at the first issue, so the panel, the assistant and support all kept
  saying "the certificate is issued" on the day after it expired. `CertificateWatch` (`onhost:certificates:watch`,
  daily 04:40) believes nobody and looks: one TLS handshake to the node's **own** address with the site's name for
  SNI, peer deliberately unverified, nothing sent afterwards. Below ten days left — the panel has demonstrably not
  renewed — the platform asks for the certificate itself through the ordinary `ssl.issue`; an expired certificate, or
  one that does not cover the site's name at all, goes to the operators once a day.
- **An id from a customer is not proof of ownership** (audit row 92, `security-boundaries.md` §19, TASK-0005).
  ISPConfig's remote API runs on one administrator session per instance and never asks whose `primary_id` it was
  handed; the platform checks only the shape of the id. Nine of eleven adapter methods always resolved it against the
  site's own listing — `setShellKey`, `setDbUserPassword` and `deleteDbUser` did not, so on a shared node a customer
  could put their SSH key on a neighbour's shell user, reset a neighbour's database password, or delete a neighbour's
  database user. Mail had the same hole: `mailbox.update`, `mailbox.delete` and `alias.delete` built the ref straight
  from the parameter. The rule is one place now (`IspConfigWebProvider::ownRow`), mail resolves through
  `MailDomains::across` before the panel is touched, and aaPanel was already clean.
- **Undoing a cancellation brings back the sites the service carried** (audit row 93, TASK-0006). A cancellation can
  be taken back for the whole grace window, but `cancellationCleared` nulled `terminate_at` only on the service it was
  called on. The carried sites had been ended with their own `terminate_at` and no `included.held_by`, so the resume
  step skipped every one — the hosting ran on, paid and live, while the purge took the sites when their thirty days
  were up, with the customer's files and databases. The end step now marks what it ended (`included.ended_by`), the
  resume takes it back, and **the scheduled deletion is undone first and on its own**
  (`ServiceService::undoScheduledDeletion`), so a site held down by an abuse case stays suspended but is no longer
  waiting to be deleted. A site the customer had cancelled themselves is not marked and stays on its way out.

- **Managing a shared service is not a shell on it** (audit row 94, `security-boundaries.md` §20, TASK-0007).
  `svc_manage` promises *actions and settings*; `svc_console` promises *terminal, VNC and game console*. But
  `permissionFor()` sent the web terminal, the per-site SSH accounts and the game console to `service.manage`, so the
  one role that exists to hand over the day-to-day work **without** a shell handed over a shell. Those five actions
  ask for `service.console` now; the permission catalogue is untouched and no role loses anything it had.
- **An ISPConfig client's limits are the organization's** (audit row 95, TASK-0008). One client account per customer
  carries the limits the panel enforces, and they were written from whichever service provisioned first — so a second
  ordinary hosting was **refused by the panel after the customer paid**, and a plan change on a small service took the
  space and the sites away from every other site of the same customer. `ClientAllowance` sums what the organization
  holds on that panel (carried sites counted once, everything but TERMINATED counted) and the provisioning and resize
  specs carry it as `client_entitlements`. Clients already on the live panel keep their old limits until that
  customer's next provisioning — no sweep exists yet.

- **A resume that could not switch everything back on says so** (audit row 96, TASK-0009). A suspension switches off
  the cron jobs, FTP accounts, schedules, apps and outgoing mail around the service and remembers what it switched
  off. `resume()` kept what it could not switch on only for a timeout — any other refusal (aaPanel's `status:false`
  is `VALIDATION`) wiped the memory, so the cron stayed off at the panel, nothing knew it ever had been on, and no
  later resume could put it back: the customer paid and their scheduled jobs never ran again. The memory now keeps
  whatever is still off, `CIRCUIT_OPEN` and `RATE_LIMIT` count as "not now" rather than "never",
  `service.resume.incomplete` goes to the operators, and the health check stops calling the service whole
  (`suspension_left`, named the way a customer reads it). The service still returns to ACTIVE — the site serves and
  the customer paid; the rest is ours to finish.

- **A suspension the panel partly refused is not a finished suspension** (audit row 97, TASK-0010). The same hole on
  the way in: when the panel refused to switch a cron job off (`status:false` is `VALIDATION`, not a timeout), the
  step still answered `done`, the saga wrote SUSPENDED and nobody knew — the site was stopped, the customer cut off,
  and the cron the quarantine exists to stop kept running. `pause()` now returns what it could not switch off, the
  step records it in a tag of its own (`tags.suspension_still_running`; `tags.suspension` belongs to the holds and is
  rewritten when the state is written) and publishes `service.suspend.incomplete` to the operators. The suspension
  stands — failing the step would put the service back to ACTIVE, serving *and* running its cron — the refused items
  are deliberately not remembered as paused, and the record is dropped as soon as the service runs again. The health
  check stays silent here on purpose: a suspended customer is not told what still runs for them.

- **A deleted ISPConfig site leaves nothing of the customer behind** (audit row 98, TASK-0011). `sites_web_domain_delete`
  deletes one row — the vhost. The databases, their logins, the FTP and SSH accounts, the cron jobs and every further
  host name are rows of their own, hanging off the site by `parent_domain_id` and named in no part of that call. What
  stayed was a terminated customer's data on a live node past every retention promise, their FTP, SSH and database
  passwords still working on a shared machine, the disk never freed, and an alias vhost still answering for a name the
  platform believed nobody held. `dropSiteChildren()` now removes them first, in an order the panel accepts and scoped
  by `parent_domain_id` so no neighbour is in reach; a refusal does not stop the termination (the customer is entitled
  to be rid of the service, and the final archive is already taken) but comes back as `leftover` and reaches the
  operators as `service.purge.leftover`, ids and all. The same holds for the old node after a web migration. aaPanel
  always did this; only ISPConfig did not.

- **The primary binding of a service is no longer a coin flip** (audit row 99, TASK-0012). A web hosting binds more
  than its site: the mail domain it was given for its mailboxes is a resource of its own, written by the same
  operation within the same second. `primaryBinding()` ordered by `created_at` alone, so on a tie the database chose
  — PostgreSQL returned the mail domain twice in the nightly suite, and `ServiceIdentityCheck` then refused to
  archive a service it could not recognise (`binding_type`, 9/5 points). Everything that asks which resource a
  service *is* hangs on that answer: the proof before a deletion, the reference an action is sent to the panel with,
  the reconciler, the features on offer. The service's own family now decides (`ServiceIdentityCheck::TYPES` is the
  one answer to both questions); `created_at` and the id only break a tie inside that family.

- **A hosting that ends stops pointing its DNS at the node** (audit row 100, TASK-0013). A hosting is written into
  DNS by three publishers — the site's A/AAAA in the customer's own zone (`web:<service>`), what the pairing of a
  further domain added (`service:<service>`), and the mail records of every mail domain it was given
  (`mail:<domain>`) — and the removal took none of them. The customer's domain went on resolving to a node that no
  longer served their site (on a shared node a visitor meets whatever it answers for an unknown name — another
  customer's website), and their mail kept being delivered to a node that no longer accepts it, with SPF and DKIM
  still authorising it. `ServiceDnsCleanup` now removes exactly what the service published, leaves a record the
  customer made themselves alone, and leaves the domain pointing nowhere rather than at a parking page (parking is
  an action of its own). Found on the way: the DNS cleanup step swallowed a failure with a comment claiming the
  reconciler would finish it — it does not, because a failed commit leaves the platform and the provider holding
  the same record and the drift check sees no difference. The termination still completes; what is left is named
  to the operators (`service.purge.leftover`, kind `dns`).

- **A resource the platform did not create is not ours to touch** (audit row 101, TASK-0014) — the owner's rule of
  2026-09-24: historical sites on the live ISPConfig and aaPanel instances must never be touched; everything the
  platform creates stays fully manageable. Provisioning used to be idempotent by name: a site (or an ISPConfig mail
  domain) already on the node was answered "already exists" and bound as `managed_by: onhost`, after which the
  platform could suspend it, change its limits and, at the end of the service, delete it with its databases. Now a
  pre-existing resource is ours only when the panel says so — the `onhost:<service>` remark on aaPanel, the
  organization's `onh_…` client as owner on ISPConfig — and anything else is refused (`CONFLICT`) before a single
  write, no client created, no binding written. A historical site is taken over only at its
  owner's explicit request, as a customer-run import into a NEW platform-created site with operator assistance
  (ADR-0007, decision 22; `docs/runbooks/historical-site-import.md`); the historical resource itself is never bound
  or modified, and never as a side effect of an order. Found on the way, and worse: a mail service was bound without
  its domain name, so every mailbox query (`LIKE '%@<domain>'`) read `'%@'` — every mailbox on the shared mail
  server. A suspended mail customer switched off sending for all of them, and a web hosting with no mail domain was
  shown every mailbox on the server and passed the ownership check for any of them. The mail domain now carries its
  name, a web hosting without one has no mailboxes, and an empty name is refused before any query.

- **Erasing a customer account is the owner's deliberate act** (audit row 102, TASK-0015) — the owner's rule of
  2026-09-24: the client account stays in the ONhost panel after its services end. Nothing closed it automatically;
  the one path that does — the customer's GDPR erasure request — was open to any administrator (`organization.manage`),
  bypassed the CommandBus and ran within half an hour, anonymising every member including the owner, closing the
  organization and erasing the archives of its cancelled services. It now goes through the bus
  (`DataRequestCommand`) and takes the owner's `organization.close` plus a fresh step-up — deliberately not four-eyes,
  an organization of one has nobody to ask. What stands in for the second person is time: the erasure waits
  `onhost.compliance.deletion_grace_days` (14) and anybody who manages the organization can cancel it
  (`POST /v1/data-requests/{id}/cancel`). An account still holding a registered domain is not erased
  (`active_domains`).

- **A mailbox tool acts only on the service's own mailboxes** (audit row 103, TASK-0016) — the owner's rule of
  2026-09-24 again: the shared ISPConfig mail server holds historical customers' mailboxes. The ownership check on
  `mailbox.update`/`mailbox.delete`/`alias.delete` did not cover the tools that act on ONE mailbox:
  `autoresponder.set`, `spam.policy`, `filter.create`, `filter.delete` (`mailbox_id`), `mailbox.backup` and
  `mailbox.restore` took the mailbox id as the customer sent it, so a neighbour's mailbox could get a `delete` filter,
  an autoresponder, another spam policy or an old backup restored over it; the `mail_autoresponder`, `mail_filters`
  and `mail_spam` listings read its settings; `fetchmail.create` delivered into any address. Now the saga looks the
  mailbox up in the service's own mail domains before any panel write (`ServiceActionWorkflow::OWN_MAIL_TARGETS`,
  `MailDomains::ownRow`, non-retryable "nepatří"), a listing of a mailbox that is not the service's answers 404
  `mailbox_not_found`, and a fetchmail destination must be an address in the service's domain (422) and one of its
  mailboxes.

- **A plan promises only what is measured or enforced** (audit row 104, TASK-0017, brain H278) — `PlanPromises`
  counted the price list (`CatalogPresentation`) as "code that reads this key", so `products`, `connections` and
  `dedicated_outbound_ip` passed a guard that only the price list ever named. Presentation files are now excluded
  from the scan, and every numeric promise is checked against `domains/Services/Metering/MetricRegistry.php` — a
  hand-verified table of what actually measures or enforces each key, scoped to the plan's own product family (a
  row verified only for mail must not pass a web plan selling the same key name) and to numeric strings as well as
  ints/floats. Family-scoping itself surfaced one more honest gap (`backup_days`: a managed database is never backed
  up on a schedule at all, and mail plans are selected by `BackupScheduler` but never scheduled) and two rows that were incomplete rather than wrong
  (`nvme_gb`, `mailboxes`, extended once their real enforcement paths were confirmed). 14 tracked gaps remained in
  `PlanPromises::KNOWN_GAPS` after this task (8 after the stack below) — a ratchet that may only shrink — shown as a standing WARN by `onhost:doctor`; any new,
  untracked gap is a production FAIL.

### The stack TASK-0017 … TASK-0027 (branch `fix/TASK-0027-stack-coherence-and-the-docs-that-descri`, one pull request into `development`, not merged yet)

- **Owner decisions of 2026-09-25 are recorded in ADR-0007** (TASK-0026, audit row 117): 25 decisions, each naming its
  implementing task TASK-0019 … TASK-0025; the 16 P0 vault cards are `assessed`. The standing rules of 2026-09-24 frame
  them: historical sites are untouchable, the client account survives its services, measure everything; a price list
  changes only as a new plan version, and new behaviour that reaches existing services ships behind a `default_off` rule
  or a dry-run operator command.
- **A test's credentials do not outlive the test** (TASK-0018, audit row 119): `CheckoutTest` left the Comgate
  credentials in the process environment and `RenewalGuardTest` failed after it. A global hook in `tests/Pest.php`
  restores `$_ENV` and `putenv()` after every test (removed keys too); the baseline lists no known failure any more.
- **Servers and managed databases are backed up as sold, behind a switch** (TASK-0019, owner decision 1, audit row 105):
  `db-s`/`db-m` and a VPS with an active backup add-on get scheduled vzdump backups on the Proxmox instance's
  `backup_storage` under the rule `backups.compute`; an expired backup is deleted only when its volume carries the row's
  marker, is the service's own guest, on its own storage and unprotected. `onhost:backups:compute-plan` shows who would be
  touched. The backup tick now walks every web/managed/mail service instead of the first 100 by id — no switch; that
  owner decision is still open (`docs/runbooks/backups.md`).
- **Was the ownership hole used before TASK-0005 closed it?** (TASK-0020, owner decision 16, audit row 92)
  `php artisan onhost:audit:provider-calls` reads the logged ISPConfig calls and the service actions that named a record by
  id and reports whose each record was (CRITICAL/HIGH/MEDIUM/REVIEW), refusals after the fix (probing), writes no operation
  explains and how far back the log reaches. Read-only (no DB write, no panel call), masked output, file under
  `storage/app/private/reports` or stdout. Runbook `docs/runbooks/provider-calls-audit.md`; to be run by the operator on
  staging with a production copy.
- **Credit is the owner's and the billing admin's** (owner decision 20, TASK-0021, audit row 107): `billing.wallet.spend`
  (owner, billing_admin; withheld from org_admin in `RoleCatalog::orgAdminWithheld()`, and an org_admin can no longer
  grant billing_admin). With `ONHOST_ORDER_CREDIT_APPROVAL=true` (default off) a credit order of anybody else waits for
  their approval (`POST /v1/orders/{id}/approval`, expiry 7 days in `onhost:commerce:prune`) and immediate credit payments
  (invoice, domain renewal, marketplace, work offer, archive download, pay-and-restore) are refused to them; card and bank
  are unaffected. Read-only `onhost:orders:credit-approval-report` before switching on.
- **Honest plan versions and four eyes on prices** (TASK-0022, owner decisions 2, 4, 5, 6, 11, 13, 18, 21; audit rows 6,
  108): every price or plan change in the admin configuration (`CatalogCommand`) takes a step-up and a second person
  (billing_finance_admin or platform_owner approve; product_manager asks), withdrawals are one person with a step-up,
  unknown catalogue ops are four-eyes, requests are pre-flight checked and bound to the plan version/setting they were
  asked against (409 `catalog_changed_since_request`), automation switches need a step-up. Code-defined catalogue revisions
  (`CatalogRevisions`, `onhost:catalog:revise`, dry run by default) publish new plan versions through `plan.publish`;
  `2026-09-honest-promises` drops `pitr_days`/`connections` (db-s/db-m), `dedicated_outbound_ip` (mail-enterprise),
  `dedicated_db` (managed-woo/shop-peak) and rewrites `backup_frequency` `1h` → `hourly`. `products` is fair use,
  `CatalogSeeder` no longer rewrites a held version, the public `sol-edu` page is withdrawn.
- **Paid limit raises** (TASK-0022, owner decision 8, audit row 109): product `limit-raise` (revision
  `2026-09-limit-raise`) raises one enforced number of one running service at the parent product's option price × units ×
  months, renewed by its own subscription; staff order it as an assisted order, customers only with
  `ONHOST_LIMIT_RAISE_CUSTOMER_ORDERS=true`; free = four eyes (`POST /v1/staff/customers/{org}/limit-raises/free`,
  `billing.limit_raise.waive` CRITICAL, one period). Raw staff resize above the service and `service.create` above the
  plan are refused (`limit_raise_required`, `plan_required`). The CommandBus hands handlers the approvals it consumed
  (`CommandContext::verifiedApprovalIds`), spent by one conditional UPDATE. Other add-ons renew only with
  `ONHOST_ADDON_RENEWALS=true`.
- **Placement by what a plan sells, capacity per dimension** (TASK-0023, owner decisions 7 and 19, audit row 110): a web
  plan that sells dedicated PHP workers (web-hosting/profi) runs only on ISPConfig — placements, staff pins, the scheduler,
  the cart and plan changes enforce it; other plans say "Sdílené PHP workery". Node capacity is judged per dimension
  (`CapacityBasis`: disk sold once `ONHOST_CAPACITY_DISK_BASIS=sold`, RAM/CPU measured; read `onhost:capacity:basis`
  first). Web/managed cart lines are refused with `capacity_sold_out` when no node can take them.
- **Usage samples, null never 0** (TASK-0023, owner decisions 9 and 12, audit row 111): every reading is kept in
  `service_usage_samples` (migration `000860`); a number a panel does not report is not measured (null), never 0, and
  `summary.usage.level` may be `unknown`. Daily/monthly rollups (`onhost:metering:rollup` 00:20) kept 45 days / 400 days /
  for ever (`onhost:metering:prune` 04:35). The rule `usage.rotation` (default off, preview `onhost:metering:preview`)
  lets the watch visit every service instead of the first 200; a first reading is a baseline; metrics new to a family are
  observed until `ONHOST_METERING_ENFORCE_NEW_METRICS`; soft limits only notify; samples are never billed.
- **Plan space in total** (TASK-0023, owner decision 10, audit row 112): files + databases + mail of the paying service and
  its included sites are shown at once (`tags.usage.disk_total`, `partial` when a panel could not say) and count against
  the plan only from `ONHOST_WEB_DISK_TOTAL_ENFORCE_FROM`, for services told at least 30 days before
  (`onhost:usage:disk-total-notice --send`) or ordered after it; ISPConfig database sizes only with
  `ONHOST_WEB_DISK_TOTAL_DATABASE_SIZES`, the date only with `ONHOST_WEB_DISK_TOTAL_PARTS_VERIFIED`. The usage API no
  longer shows an aaPanel node's whole-node figures as the customer's usage (H286).
- **Backup operations and mailbox backups** (TASK-0024, owner decisions 1, 3, 18; audit rows 113, 114): the doctor reports
  server/database backup readiness (rule vs sold, missing `backup_storage`, delete-blocked and orphaned volumes, stalled or
  paused schedules of every family, tick age/budget/errors); a retried Proxmox backup adopts its first marked volume
  instead of dumping twice. `backups.as_sold` (default off, review `onhost:backups:frequency-plan`) backs plans up as often
  and as long as sold; switching it off deletes nothing en masse. `mail.backup_retention` (default off, existing mailboxes
  only via `onhost:mail:backup-retention`, dry run by default) sets ISPConfig mailbox backups from `backup_days` on
  mailboxes proven to be the platform's; an autoresponder change no longer resets them.
- **Pay and restore** (TASK-0025, owner decision 23, audit row 115): cancelled services come back inside their restore
  window after payment (a paid dunning invoice, one new period from the credit, or a top-up covering a recorded request)
  through the ordinary resume; undone cancellations are billed again; chargeback-cancelled services cannot be resumed by
  the customer; the purge spares a paid service and its carried sites. Behind `services.reinstate` (default off);
  read-only `onhost:billing:reinstatement-audit` before switching it on.
- **Consumer withdrawal** (TASK-0025, owner decision 17, audit rows 16, 116): a consumer (class at order time) withdraws
  within 14 days of the order in the panel, or finance records a letter by its sent date behind four eyes; the service is
  suspended first, the unused part of the paid lines returns to the credit as credit notes, then the service is cancelled
  and cannot be resumed by the customer. Registered domains and business orders are excluded. Behind `billing.withdrawal`
  (default off); `onhost:withdrawals:finish` (hourly) completes refused steps; a lawyer's review is pending (doctor row,
  `ONHOST_WITHDRAWAL_LEGAL_REVIEWED`).
- **The stack agrees with itself** (TASK-0027, audit row 118): one credit gate — paying for a restore, taking back a
  cancellation that bills again and a recorded restore request all ask `CreditOrderPolicy` (`credit_spend_not_allowed`);
  the reinstate command needs `billing.wallet.topup` on the bus (C1). A restore never switches auto-renew on: it keeps the
  subscription's previous value, nothing recorded = off (C2). Staff hear the four service troubles whose staff branch never
  ran — restore test failed, database import failed, backup schedule paused/stalled (`NotificationRouter`, one arm per
  event, C3). `eshop/shop-peak` stops promising dedicated PHP workers on aaPanel in a new plan version (revision
  `2026-09-shared-php-workers`, only through `onhost:catalog:revise`; each revision reads what is pending just before it
  runs) (C4). `PlanPromises::KNOWN_GAPS` holds 8 entries.

**Operator switches introduced by the stack** (all default off unless stated; the steps are in
`docs/runbooks/go-live-checklist.md` §6):

- `backups.compute` (rule) — scheduled backups of managed databases and VPS with a backup add-on; `onhost:backups:compute-plan` first.
- `backups.as_sold` (rule) — backup frequency and history as sold; `onhost:backups:frequency-plan` first.
- `mail.backup_retention` (rule) — ISPConfig mailbox backups from `backup_days`; `onhost:mail:backup-retention` (dry run) first.
- `usage.rotation` (rule) — the usage watch visits every service; `onhost:metering:preview` first.
- `services.reinstate` (rule) — pay and restore; `onhost:billing:reinstatement-audit` first.
- `billing.withdrawal` (rule) — consumer withdrawal; after the legal review and `ONHOST_WITHDRAWAL_LEGAL_REVIEWED=true`.
- `ONHOST_ORDER_CREDIT_APPROVAL` — customer approval of credit orders; `onhost:orders:credit-approval-report` first.
- `ONHOST_LIMIT_RAISE_CUSTOMER_ORDERS` — customers may order limit raises themselves.
- `ONHOST_ADDON_RENEWALS` — add-ons other than limit raises renew (new orders only).
- `ONHOST_CAPACITY_DISK_BASIS` (`measured` → `sold`) — node disk judged by what is sold; `onhost:capacity:basis` first.
- `ONHOST_METERING_ENFORCE_NEW_METRICS` — metrics new to a family count against the plan.
- `ONHOST_WEB_DISK_TOTAL_DATABASE_SIZES` — ISPConfig database sizes are read for the plan total.
- `ONHOST_WEB_DISK_TOTAL_PARTS_VERIFIED` — the operator confirmed the plan total counts nothing twice.
- `ONHOST_WEB_DISK_TOTAL_ENFORCE_FROM` (a date, unset by default) — from when the plan total counts, for noticed services.
- `ONHOST_PASSWORD_CHANGE_REVOKES_API_ACCESS` — **default on** (owner decision 14); `false` keeps personal tokens after a password change.
- `onhost:catalog:revise --apply` (operator command, dry run by default) — publishes the three catalogue revisions.

## Verified baseline

Measured on the stack tip `edb635b` (TASK-0027 C1–C4, before its docs commits) on 2026-09-25 with `.\brain.ps1 gate`
(full): **PASS** — Pint clean, Larastan level 5 0 errors (baseline file unchanged: 641 entries / 1 013 suppressed
occurrences), Pest **1 364 tests / 18 258 assertions**, no failures, frontend build green. 543 routes (493 under `/v1`),
61 migrations apply on an empty SQLite file, `composer validate`, `composer audit` and `npm audit --audit-level=high`
clean (`.ai/baseline/baseline.json`). `development` itself (at `2426c17`, TASK-0016) is covered by its own CI runs.

- Remote: `github.com/Stanektechcz/onhostik`, default branch `development`.
- CI: `tests.yml` (Pint, Pest, Larastan, Composer audit, the same suite on PostgreSQL 16 — `pest-postgres` is the only
  PostgreSQL run; it ran green on the separately pushed TASK-0017, TASK-0018, TASK-0019 and TASK-0003
  branches — open PRs #20–#23 — but not on the whole stack), `security.yml` (gitleaks over the history, Composer and npm
  advisories, frontend build), `e2e.yml`, `edge-role.yml`; Dependabot weekly.
- Live orders were placed and verified through the panels for web hosting (ISPConfig and aaPanel), WordPress, e-shop,
  a custom web, mail and a Minecraft Vanilla 1.21.8 game server. Nothing of the stack ran against a live panel.
- `onhost:doctor` is the readiness gate: environment, storage, automation, secrets, TLS, providers, payments,
  documents, identity, mail, observability, the deletion lifecycle, and since the stack the catalogue revisions, the
  metering-gap ratchet, limit raises, server/database/mailbox backups and consumer withdrawals.

## Known risk

- A local `.env` holds provider credentials. It never enters prompts, logs, commits or generated context; the
  pre-commit hook (`git config core.hooksPath .githooks`) and the security workflow enforce that.
- The stack is one large pull request (TASK-0017 … TASK-0027 plus TASK-0018 and TASK-0003): PostgreSQL behaviour of the
  new JSON-path and roll-up queries of TASK-0020 … TASK-0027 is proven only by CI `pest-postgres` after the push; several provider calls are
  unverified live (`databasequota_get_by_user`, mailbox `backup_interval`/`backup_copies`, Proxmox volume `notes` and
  `protected`).
- Two behaviour changes reach production with the merge without a switch: the backup tick visits every web/managed/mail
  service (owner decision open, `docs/runbooks/backups.md`), and a password change revokes personal API tokens (owner
  decision 14; `ONHOST_PASSWORD_CHANGE_REVOKES_API_ACCESS=false` undoes it). The deploy's `AuthorizationSeeder` changes
  roles in every organization (billing_admin gains `billing.wallet.spend`, the panel password becomes owner-only).
- A solo owner must set `ONHOST_FOUR_EYES=false` before deploying, or every price change waits for a second person.
- One staging web service is still stuck mid-termination from before the archive fallback existed; it is unblocked
  with `onhost:services:purge --service=… --force --reason=…`.
- `s4s.electree.cz` was deleted on the live ISPConfig node by the resource-type confusion fixed in §5z; the site is
  recreated from the panel's data log with `onhost:ispconfig:restore-site` and its node backup.
- Consumer withdrawal waits for a lawyer; the document version of the edited legal texts (TASK-0025) and the date and
  terms wording of the plan total (TASK-0023) are the owner's decisions.
- The production-readiness audit still lists open P0/P1 items: `docs/runbooks/production-readiness-audit.md`.
- Playwright: five panel navigation/session scenarios fail and keep the E2E gate advisory rather than blocking.

## Next decision

1. The human reviews the stack and decides the one pull request into `development` (push and merge only with their
   go-ahead); then the post-integration gate and CI `pest-postgres` on the merge.
2. Run the lifecycle verification on staging (archive on each panel with `onhost:services:archive --create`, then the
   purge) and restore `s4s.electree.cz` with `onhost:ispconfig:restore-site`.
3. Then the go-live checklist on the production host, including the stack's operator steps
   (`docs/runbooks/go-live-checklist.md` §6): `AuthorizationSeeder` and the roles row after deploy,
   `ONHOST_FOUR_EYES=false` for a solo owner, `onhost:catalog:revise --apply`, `onhost:audit:provider-calls` on a
   production copy, and every default-off switch only after its read-only command.
