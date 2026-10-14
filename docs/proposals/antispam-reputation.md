# Antispam and sending reputation (SP-1, phase V)

Owner decision **V-R1** (2026-10-14, answer to SEZNAM SP-1, recorded in `ROZHODNUTI.md` *Fáze V*): antispam is to be **fully solved
and automated** — configurable across panels, staff policies per plan/service/global, customer settings inside staff bounds, outbound
protection so that no service or server lands on a blocklist, continuous monitoring of every sending address and customer domain
against the blocklists, automatic remediation first, the customer told only after a successful delisting, tech support after 72 h,
the customer asked to cooperate after another 48 h; full audit trail, metrics, alerts, doctor rows and a staff dashboard section.

This document is the research (§1–§4), the architecture (§5–§9) and the package plan (§10). What is VERIFIED was read from the code
or a primary source on 2026-10-14; ASSUMED items say what proves them. **No live mail server is written by any package here**: every
node-side change goes through the node agent (§7), which stays off (`ONHOST_ANTISPAM_AGENT=false`) until the owner's staging "ano"
(owner document `GENERALKA-V-antispam-staging-<date>.md`). Historical ISPConfig/aaPanel sites stay untouchable (owner rule
2026-09-24): every write is limited to mailboxes and domains the platform proves are its own (`ownRow()`, the `onh_` client).

## 1. Where the platform stands (VERIFIED, `development` at `02a1519d`)

| Piece | State |
| --- | --- |
| `domains/Dns/BlocklistCheck.php` + `onhost:mail:blocklist` (daily 05:25) | asks 3 lists (`zen.spamhaus.org`, `bl.spamcop.net`, `b.barracudacentral.org`) about each node's `tags.public_ipv4` through the **system resolver** (`dns_get_record`), stores `tags.blocklist`, raises `node.blocklisted` once a day; treats **any** `127.x` answer as a listing — so Spamhaus' error answers `127.255.255.254` (query through a public resolver) and `127.255.255.255` (over the volume) were counted as listings; no IPv6, no domains, no clear event, no history |
| `MailToolsProvider` (ISPConfig) | inbound spam **policies** per mailbox (`mail_spamfilter_user_*`, `mail_policy_get`), white/black lists — the customer action `spam.policy` exists, but nothing maps a plan to a policy and staff cannot bound it |
| `MailProvider::setSendingEnabled()` | receive-only mode per mail domain (dunning, `sending.set`) — the only outbound control |
| `relay_per_hour` (500 / 5 000) | **fair use**: "ISPConfig has no per-hour send limit in its remote API; the rate is held by the mail node itself" (`PlanPromises::FAIR_USE`) — nothing applies it |
| `spam_filter` (`rspamd` / `rspamd + policies`) | fair use since U4 (TASK-0220): the price list says "Antispam rspamd s pravidly serveru"; `MetricRegistry` row `gap` |
| aaPanel | **no `MailProvider`** at all (aaPanel nodes host web only); its mail plug-in is not used by the platform |
| SPF / DKIM / DMARC | the platform publishes `mail:` records in zones it hosts (record owners, `onhost-dns-record-owners`); `PublicDnsCheck` compares public DNS for customer domains; no alignment check, no PTR/HELO check |

## 2. Blocklists: the catalogue, how to ask, how to get off (research)

- **The catalogue is large and mostly dead.** `multirbl.valli.org` tracks **893** lists, of which **301 alive** and **592 dead**
  (2026-10-14). Asking dead lists is worse than useless: a dead zone may be re-registered and then answer *every* query as listed
  ("wildcard" listing). The platform therefore keeps a **curated catalogue in code** (`BlocklistCatalog`) with a weight per list
  and an operator switch per list, never a scraped list of hundreds. **SORBS was shut down by Proofpoint on 2024-06-05** — the owner's
  example list is not in the catalogue (asking it would be a dead-zone risk).
- **Weights.** `major` = lists the big receivers act on (Spamhaus ZEN/SBL/XBL/CSS, Spamhaus DBL for domains, Barracuda BRBL,
  SpamCop, Mailspike BL, PSBL, SURBL/URIBL for domains): a listing opens an incident (§6). `minor` = smaller lists (NiX Spam,
  0spam, JustSpam, Backscatterer, DroneBL, s5h, …): recorded, shown, counted in metrics, no incident on its own. `info` =
  lists that list whole networks by policy (**UCEPROTECT level 2 and 3** list an allocation or a whole ASN because of neighbours;
  Spamhaus **PBL** lists address ranges an ISP says should not send directly): shown only, never an incident, never in the doctor.
- **Query etiquette (VERIFIED from Spamhaus' announcement of the return codes, 2021, and the DQS docs):**
  - Spamhaus public mirrors **refuse queries made through public/open resolvers** (8.8.8.8, 1.1.1.1, …) and answer
    `127.255.255.254`; over the free volume they answer `127.255.255.255`; a typo in the zone gets `127.255.255.252`. These are
    **errors, not listings**. The catalogue knows each list's error answers; an error marks the list `refused` for that run and
    raises the doctor row *mail blocklist resolver is accepted*, never an incident.
  - Hence the platform asks through **its own resolver** (`ONHOST_DNSBL_RESOLVER`, an unbound/PowerDNS recursor on the platform
    host with a stable, attributable reverse name), not through the system resolver of a cloud VM. Empty = the system resolver
    (with the doctor row WARN once a list refuses).
  - **Spamhaus DQS** (Data Query Service): free for low volume after sign-up; the query is `<reversed ip>.<KEY>.zen.dq.spamhaus.net`
    (domains: `<domain>.<KEY>.dbl.dq.spamhaus.net`). The key is an **optional secret** (`ONHOST_SPAMHAUS_DQS_KEY`, never logged,
    never in an audit row or event — the platform stores the public zone name only). With a key, the Spamhaus rows use DQS and the
    public-mirror restrictions do not apply.
  - **URIBL** answers `127.0.0.1` to a blocked querier; **SURBL** similar — both are treated as errors.
  - Volume: the platform asks each (target, list) pair **at most once per `ONHOST_DNSBL_MIN_INTERVAL_MINUTES` (default 60)**, caches
    the answer, and spreads a run over the budget (`ONHOST_DNSBL_MAX_QUERIES_PER_RUN`, default 2 000). A fleet of 20 nodes × 25 lists
    + 2 000 customer domains × 4 domain lists is ~8 500 queries an hour at most — well inside the free tiers of every list in the
    catalogue; DQS is recommended above 100 000 queries a day.
- **Delisting (research, per list; `delist` in the catalogue):**

| List | How it ends | Automation |
| --- | --- | --- |
| Spamhaus SBL / CSS | self-service removal form after the cause is fixed (CSS re-lists if the problem repeats) | **prepared request** (staff submits; the form needs a human and a captcha) |
| Spamhaus XBL (CBL) | self-service lookup/removal, expires once the infection stops | **expiry**, then prepared request after 24 h |
| Spamhaus DBL | self-service ticket | prepared request |
| Barracuda BRBL | self-service removal request (12–24 h) | prepared request |
| SpamCop | expires 24–48 h after the reports stop | **expiry** |
| UCEPROTECT L1 | expires ~7 days after the last hit; paid "express delisting" exists | **expiry** (the platform never pays a list) |
| PSBL | self-service removal on its site | prepared request |
| Mailspike | self-service | prepared request |
| SURBL / URIBL (domains) | self-service request | prepared request |
| minor lists | mostly expiry | expiry |

  No list in the catalogue offers an unauthenticated **API** for removal that a provider may call without a human (the forms
  carry a captcha or need an account). The platform therefore never submits a form itself (CAPTCHA rule): "delist automation" is
  (a) removing the cause automatically, (b) re-checking on the list's own schedule until the listing expires, and (c) a **prepared
  delist request** — a support ticket with the list, the code, the removal URL, the cause found and the actions taken — for the
  lists whose `delist` is `form`. The catalogue keeps an `api` method for a list that adds one; none does today.

## 3. Postmaster feedback (research)

- **Google Postmaster Tools**: v1 (and with it IP/domain *reputation*) was retired at the end of 2025; **v2** gives spam complaint
  rate, authentication (SPF/DKIM/DMARC pass rates), delivery errors and encryption per verified domain
  (`gmailpostmastertools.googleapis.com`, OAuth 2 scope `postmaster.readonly`). Optional integration: a service-account / OAuth
  refresh token as a secret; the platform verifies our own sending domains only (customer domains need the customer's
  verification). ASSUMED until staging: the exact v2 method shapes (read from the discovery document when the integration is built).
- **Microsoft SNDS**: data per IP range the operator registered; "Automated Data Access" gives a key URL that returns headerless CSV
  (`ipStatus`: range, flag, reason). Since 2026-07 the access keys **expire every 30 days** and the old API answers `404` both for an
  expired key and for "no data" — the integration must treat `404` as *unknown*, never as *clean*, and raise a doctor row when the key
  has not answered for 30 days. Optional secret `ONHOST_SNDS_KEY`.
- Both feed the same reputation signal table as the blocklists (§5); neither is needed for the rest to work.

## 4. Engines and panels (research)

- **rspamd** (ISPConfig 3.2 installs it; any postfix can use it): `ratelimit` module with token buckets (`burst`, `rate`), predefined
  `user` (authenticated user — the outbound limit) and custom selectors (`selector = 'user.lower'`, per sender domain), action on
  excess = soft reject; needs Redis. `multimap` for per-sender maps (a held mailbox = `reject` from a map file), the controller API
  (`/history`, `/stat`; password protected, local) for per-user outbound statistics (user, client IP, action, score, time).
  **Outbound scanning** = rspamd also scans authenticated submissions (`settings` for the `authenticated` group) — an outbound
  message scoring `reject` is not relayed, `add header` is relayed and counted as a warning.
- **ISPConfig remote API** (VERIFIED from its source): `mail_policy_get/add/update/delete` — fields `policy_name`,
  `rspamd_spam_greylisting_level`, `rspamd_spam_tag_level`, `rspamd_spam_tag_method` (`add_header`/`rewrite_subject`),
  `rspamd_spam_kill_level`; `mail_spamfilter_user_*` binds a mailbox or `@domain` to a policy; `mail_user_update` fields `access`
  (login), `disablesmtp`, `disableimap`, `disablepop3`, `password`, `greylisting`. **No outbound rate limit, no queue** in the API.
- **aaPanel**: the platform has no aaPanel `MailProvider`; aaPanel nodes send only what PHP on a site sends (`mail()`, SMTP to
  localhost). Their **sending address is monitored** like any node; outbound control there is the node agent (§7) once the owner
  approves it; mailbox actions are *not supported* and the provider says so (`ExplainsWithheldFeatures`).

## 5. Architecture

```
            ┌──────────────── ReputationMonitor (onhost:mail:reputation, hourly) ───────────────┐
 targets:   │ sending addresses (node tags public_ipv4/public_ipv6 + ONHOST_ANTISPAM_SENDING_ADDRESSES) │
            │ customer mail domains (mail_domain bindings of live services)                        │
            └───► DnsblLookup (own resolver, DQS key, cache, budget) ───► BlocklistCatalog (weights, │
                   error answers, delist method)                                               ▼
                                                              mail_reputation_listings (open/cleared)
                                                                         │ major listing opened
                                                                         ▼
 OutboundGuard (onhost:mail:outbound, 5 min) ◄── node agent telemetry ── ReputationIncidents (SP1c)
   per mailbox: volume vs baseline, distinct auth IPs/countries,          │ cause → remediation via the bus
   rspamd outbound rejects → hold / tighten / lock + reset (SP1b)         │ (lock+reset, purge, tighten)
                                                                          ▼
                                       re-check → cleared → customer told what changed
                                       72 h open → support ticket + on-call alert
                                       +48 h → customer told it is being handled, cooperation asked
```

- **Provider abstraction** (`Onhost\Providers\Contracts\AntispamProvider`): `policies()`, `applyPolicy(target, policy)`,
  `outboundLimit(target, limit)`, `hold(target)` / `release(target)`, `purgeQueue(sender)`, `lockMailbox(mailbox)` (login + SMTP off
  and a random password = forced reset), `outboundStats(since)`; each method returns a `ProviderResult` or says it is not supported.
  Implementations: **ISPConfig** (inbound policies and mailbox lock through the remote API; outbound limits, holds and queue
  through the node agent), **node agent** (any postfix + rspamd: aaPanel or a plain mail server), **fake** for tests.
- **Policies** (`antispam_policies`): global defaults → per plan (by the plan's `spam_filter` tier: `rspamd` = *standard*,
  `rspamd + policies` = *enhanced*) → per service override by staff; each policy carries inbound levels (greylisting, tag, reject),
  outbound limits (per mailbox / per domain per hour, burst), and **customer bounds** (the range a customer may move the tag/reject
  levels in; whether they may switch greylisting). `spam_filter` moves from `FAIR_USE` to `MetricRegistry` `enforced_only` for
  `mail` when the policy is applied at provisioning and on drift (SP1b).
- **Every write is a bus command** (`AntispamCommand`, staff permission `mail.antispam.manage`, risk NORMAL for policy edits, HIGH
  for lock/purge; the system context for automatic remediation with the reason in the audit row). Customer settings go through
  the existing `spam.policy` action with the bounds checked in `CustomerActionParams`.
- **Audit and events**: `mail.reputation.listed` / `.cleared` / `.refused`, `mail.outbound.held` / `.released`,
  `mail.mailbox.locked`, `mail.incident.opened` / `.resolved` / `.escalated` / `.customer_asked` — every one with an audit row; the
  customer-facing ones through `Lexicon` (CZ/EN, append-only).

## 6. Incident timeline (SP1c)

| When | What happens | Who is told |
| --- | --- | --- |
| a `major` listing opens | incident `open`; cause search over the last 48 h of outbound telemetry for the listed address/domain: a mailbox past its baseline, many auth IPs, rspamd outbound rejects, a site sending via `mail()` | operators (event, staff dashboard); **not the customer** |
| cause found | automatic remediation through the bus: lock + forced reset of a compromised mailbox, purge of its queued spam, outbound limit tightened for the domain; each step audited | — |
| every re-check | listing still open? the list's `delist`: `expiry` → wait; `form` → a prepared delist ticket (once) | staff (ticket) |
| listing cleared | incident `resolved` | **customer** (only if their service was the cause): what happened and what was changed (Lexicon `mail.incident.resolved`) |
| 72 h open | incident `escalated`: support ticket (priority high) + on-call alert `mail.incident.escalated` | tech support |
| escalated + 48 h | incident `customer_asked` | **customer**: the issue occurred, it is being handled, cooperation requested (`mail.incident.cooperation`) |

Clocks are absolute timestamps on the incident (`opened_at`, `escalate_at = opened_at + 72 h`, `ask_customer_at = escalated_at +
48 h`), driven by the hourly monitor run, idempotent (one ticket, one alert, one message each).

## 7. Node agent (SP1b; off until the staging "ano")

A root-owned script on each mail-sending node (`infra/mail/onhost-antispam-agent.sh`, systemd timer every minute):
1. `GET /v1/agent/antispam/desired-state` (per-node token, HMAC, from the platform's own host list) → writes rspamd
   `local.d/ratelimit.conf` maps and a `multimap` map of held senders, `rspamc reload` only when the content changed;
2. runs the queue purges listed in the desired state (`postqueue -j` filtered by sender, `postsuper -d` by queue id);
3. `POST /v1/agent/antispam/telemetry` with the per-user counters of the last minute from the local rspamd controller (`/history`):
   authenticated user, client IP, action, score — no message content, no subjects, no recipients beyond their count.
Pull/push keeps root SSH away from the platform; the platform never runs a command on a mail node.

## 8. Outbound protection and compromised accounts (SP1b)

- **Limits**: per mailbox and per domain from the policy (default from the plan's `relay_per_hour`), applied by rspamd `ratelimit`
  (`user.lower`, sender domain selector).
- **Detection** (per mailbox, every 5 min over the telemetry): volume over `max(3 × 7-day hourly p95, 50)` in an hour; ≥ 5 distinct
  authenticating IPs or ≥ 3 countries in an hour; ≥ 20 % of outbound scored `reject`/`add header`. One signal = `suspicious`
  (staff + metric); two signals, or any signal on a mailbox whose domain is listed = **auto-hold** (sending stopped by the agent's
  multimap, the mailbox's queued mail held) and, for the strongest cases, **lock + forced reset** (login off, random password; the
  customer gets the existing mailbox password link). Release only by staff or by the customer after the reset (bus, audited).
- **DNS hygiene** (SP1d): SPF includes `ONHOST_SPF_INCLUDE`, a DKIM key published and matching the panel, DMARC present
  (`p=none` at least) — auto-set in zones the platform hosts (record owner `mail:`), checked and reported for zones held elsewhere;
  every sending address has a PTR whose name resolves back (FCrDNS) and matches the HELO name the node announces.

## 9. Doctor rows, metrics, alerts, staff dashboard

- Doctor (all OK on a fresh deploy — no data is not a warning): *sending addresses are on no major blocklist*, *mail blocklist resolver
  is accepted*, *mail reputation checked within 3 h* (only once enabled), *no mail incident past its 72 h*, *antispam agent heard from
  every mail node* (once the agent is on), *postmaster feedback answers* (once a key is set).
- `/metrics`: `onhost_mail_blocklist_listings{weight,kind}`, `onhost_mail_reputation_last_run_timestamp`,
  `onhost_mail_blocklist_refused`, `onhost_mail_incidents_open{state}`, `onhost_mail_outbound_held`; alerts
  `OnhostMailMajorBlocklisting`, `OnhostMailIncidentEscalated`, `OnhostMailReputationStale` (promtool-tested).
- Staff: *Reputace pošty* (`/sprava/nastaveni/reputace-posty`, `GET /v1/staff/mail/reputation`, permission
  `mail.antispam.read`): listings, incidents with their clocks, held mailboxes, policies; StaffReadAudit (no customer content —
  addresses of our own nodes, customer domains and mailbox addresses are customer data → audited reads).

## 10. Packages

| Package | Content | Live? |
| --- | --- | --- |
| **SP1a** (TASK-0223) | this document, V-R1; `BlocklistCatalog`, own-resolver DNSBL lookup (UDP client, DQS key, cache, budget, error answers), `ReputationMonitor` over sending addresses (IPv4 + IPv6) and customer mail domains, `mail_reputation_listings`, `onhost:mail:reputation` (replaces the 3-list daily check), doctor rows, metrics | reads public DNS only |
| **SP1b** | `AntispamProvider` + ISPConfig / node-agent / fake implementations, `antispam_policies` (global / plan / service, customer bounds), policy at provisioning + drift, outbound limits, telemetry + detection, hold / lock / reset / purge through the bus, agent endpoints and script, `spam_filter` → `enforced_only` | agent off (`ONHOST_ANTISPAM_AGENT=false`) |
| **SP1c** | incidents and the 72 h / 48 h timeline, cause search, remediation, prepared delist tickets, notifications (Lexicon CZ/EN), on-call alert, staff dashboard + API + navigation + StaffReadAudit + OpenAPI | — |
| **SP1d** | SPF/DKIM/DMARC auto-setup and checks, PTR/HELO checks, Google Postmaster v2 and SNDS integrations (optional secrets) | read only |
| **SP1e** | owner document for the staging verification (agent on one node, a test mailbox, a forced listing in a test zone) | after a separate "ano" |
