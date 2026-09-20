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
