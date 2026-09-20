# Domains and the registrar (WEDOS WAPI)

## Daily signals

* `registrar.credit.low` — WAPI credit below the threshold (`onhost.domains.credit_warning`). Top up at WEDOS;
  renewals are prioritised by the `wapi:domain` bucket reserve, registrations wait.
* `domain.renewal_notice` / `domain.renewal_payment_failed` — customers are told at the configured lead days;
  critical domains (`critical: true`) are never allowed to lapse: the scheduler escalates to support instead of
  abandoning the renewal.
* `registrar.notification.dead` — a WAPI notification could not be matched to a domain after retries; check
  `registrar_operations` for the raw (redacted) row and match manually via `onhost:registrar:reconcile`.

## Async registrations

`domain-create` may return 1000 (done) or 1001 (accepted). The Register workflow polls `domain-info` until the
domain is active; the customer sees PENDING_REGISTRATION. If the registry rejects later (`registration_failed`),
the wallet hold is released and the order item is marked failed with the registry reason.

## Renewals

`onhost:domains:renewals` (hourly) places a wallet hold, submits `domain-renew`, confirms the new expiry and
issues the VY statement. Failure paths:

| State | Cause | Action |
| --- | --- | --- |
| HOLD_FAILED | insufficient wallet | dunning notice sent; for critical domains support calls the customer |
| SENT, no confirmation | WAPI async pending | wait; poll worker retries `domain-info`; escalate after 24 h |
| FAILED 3205/3206 | domain not renewable (locked/expired at registry) | open ticket, manual renewal at WEDOS |

**After the expiry the renewal goes on.** An unpaid renewal is retried every day up to the expiry **and through the
protective period after it** (`ONHOST_DOMAIN_GRACE_RETRY_DAYS`, default 20 — the registry still renews at the ordinary
price; afterwards only a paid restore helps), and on the next hourly pass after the customer tops up
(`DomainRenewalScheduler::wake`). It used to be abandoned the day before the expiry: a customer who topped up the next
morning lost the domain anyway. An expired domain with auto-renew on and no open job gets one at once. The customer's
notice says how many days of the protective period are left. Check on staging how long each registry really renews at
the ordinary price (`.cz` 30 days, most gTLDs 30–45) and set the number below the shortest.

## Reconciliation

`onhost:registrar:reconcile` (daily 04:00) lists domains at WEDOS and compares with `domains`:
`domain.reconcile.missing_remote` (we think we own it, registrar does not) and `unknown_remote` (registrar has a
domain we do not) are internal notifications; both need a human decision — import, transfer, or close.

A listing **without a status** (Subreg's has none) says nothing about the state: dates are taken from it, the state is
not. It used to read as "active", so a domain in redemption or on its way out came back as `ACTIVE` every night. Domains
such a listing cannot explain — not `ACTIVE` locally, or listed with a date in the past — are asked about one by one
(`domain-info` / `Info_Domain`, at most 100 per run).

Tests: `tests/Feature/Domains/DomainGraceAndReconcileTest.php`.

## Transfer out / AUTH-ID

AUTH-ID is revealed only through the `domain.transfer_out.execute` command (HIGH risk → step-up) and mailed to
the registrant (`domain-auth-info` mandatory template); it is never logged or stored.

## DNS

Zones are canonical in PowerDNS; WEDOS Zone is a fallback selectable per zone. Two-phase editing:
`POST /v1/domains/{zone}/zone/changes` (stage) → `…/commit` (atomic, versioned) → `…/rollback` (previous
version). Enabling DNSSEC publishes DS records to the registrar through the same command bus.

## Premium names

A premium name costs at the registry what the registry says — hundreds or thousands of euros — and the registrar takes it
from **our** credit the moment the order is accepted. The shop priced every name from the list of its TLD:

* the search offered a premium name as free at the ordinary price (Subreg's `Check_Domain` says `price.premium = 1`; the
  flag was read by the adapter and ignored by everything after it);
* the registration sent the create without looking — whoever placed the order, the API quotes a domain without searching
  for it; the renewal and the transfer did the same at the list price.

Now: the search answers `available: false, reason: premium` (the shop says "premium name — write to us"); right before
`domain-create` the name is asked about once more and a premium one — or one taken in the meantime — fails the line for
good (the order settlement gives the money back), while **no answer** makes the create wait (it is not "ordinary" by
default); a renewal or a transfer of a premium name fails before anything is sent or held, the customer and finance hear
about it (`domain.renewal_failed`), and finance renew it by hand at the registry's price.

Selling premium names is a business decision (the registry's price + a margin, quoted individually); until then they
are not sold. Tests: `tests/Feature/Domains/PremiumDomainTest.php`.

## DNS: compared every night, bounded in size

* `onhost:dns:drift` (daily 03:40; `ONHOST_DNS_DRIFT_BATCH` zones a night, oldest comparison first) compares what each
  DNS provider **serves** with what the platform holds. `DnsService::drift()` existed and nobody called it: a record
  changed at the provider by hand, a commit that arrived only in part (the WEDOS zone API takes rows one by one), a zone
  deleted there — none of it was ever noticed. Zones of the platform's own DNS only; a zone mirrored from a customer's
  registrar account is edited there too and has its own import.
* A difference is said **once** (`dns.drift.detected`, internal) and stays in `onhost:doctor` (area `dns`) until it is
  gone: the zone keeps `drift_checked_at` and a summary (`missing_at_provider`, `unknown_at_provider`, a sample). Nothing
  is repaired by itself — somebody looks first (a record added at the provider by hand may be one that belongs into the
  zone: add it here, then publish).
* **The repair is `POST /v1/dns/zones/{zone}/republish`** (`dns.zone.write`; the button "Publikovat zónu znovu" on the DNS
  tab, shown when the zone differs): the provider is made to serve what the platform holds — missing records are added,
  unknown ones removed, a record whose TTL differs is **one update** (sent as a row added and a row removed, PowerDNS
  would drop the record from its set: it tells records apart by their data, not by their TTL), a zone that is gone is
  created again. The provider is read once more afterwards; the zone is clean only when nothing is left (`verified`). A
  signed zone that had to be created again has lost its keys: the answer says `dnssec_attention` and operations are told
  (`dns.zone.republished`, hot) — enable DNSSEC again and publish the new DS at the registry.
* **No false alarms** — both proven against the old code: PowerDNS answers a TXT value in wire form (quoted, a long one in
  parts) and `listRecords` handed it on like that, so every zone with an SPF record differed for ever — and, worse, a TXT
  record could be **neither replaced nor removed** at the provider (the old SPF stayed next to the new one: a permerror;
  a removed DKIM key stayed published). And a set of records has ONE TTL on the wire: two A records the customer gave two
  TTLs are ours, not a difference (a TTL changed at the provider still is one).
* A zone that is **not at the provider at all** is the largest difference there is (`zone_missing`, severity `hot`) — the
  provider is asked a second way (`zoneExists`) before anybody is told. A comparison that **could not be made** (the key is
  refused, the provider is down) is neither "equal" nor "gone": the zone keeps `drift_error` (the normalised error class,
  never a vendor payload), what differed the last time stays as it was, and the doctor has a line of its own for it
  (`every DNS zone could be compared with its provider`).
* A zone holds at most `ONHOST_DNS_MAX_RECORDS` (500) records and `ONHOST_DNS_MAX_PENDING` (200) changes waiting to be
  published (`dns_record_limit`, `dns_pending_limit`, 409). Nothing bounded either: one API token could stage records
  without end, and a commit pushes the whole zone to the provider.

### WEDOS hosted zones: a batch that is repeated arrives at the same zone

The WEDOS zone API takes rows one by one (`dns-row-add|update|delete`) and publishes them with one `dns-domain-commit`. A
batch can therefore fail half-way; the platform keeps the changes pending and repeats the batch as a whole. Four faults of
the adapter, all proven against the old code (`tests/Contract/WedosZoneDnsContractTest.php`):

* every repeat **added again** the rows the first attempt had already staged — a row that is there is not added now (a TTL
  that differs is brought in line);
* the commit is sent for every batch that is not empty, even when nothing was left to stage — the first attempt may have
  staged everything and failed at the commit;
* a record that was **renamed** (or whose type changed) went out as `dns-row-update`, which takes a TTL and the data and
  never a name: WEDOS kept serving the old name while the platform held the new one. It is a row removed and a row added;
* two MX rows of the same host were told apart by nothing — a delete removed the first one it met. The priority decides.

Unverified on a live account: that `dns-rows-list` shows rows that are staged and not yet committed (the repeat relies on
it; if it does not, a repeat adds the row again exactly as before — no worse than it was).

Tests: `tests/Feature/Dns/DnsServiceTest.php`, `tests/Contract/WedosZoneDnsContractTest.php`.
