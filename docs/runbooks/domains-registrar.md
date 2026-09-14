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

## Reconciliation

`onhost:registrar:reconcile` (daily 04:00) lists domains at WEDOS and compares with `domains`:
`domain.reconcile.missing_remote` (we think we own it, registrar does not) and `unknown_remote` (registrar has a
domain we do not) are internal notifications; both need a human decision — import, transfer, or close.

## Transfer out / AUTH-ID

AUTH-ID is revealed only through the `domain.transfer_out.execute` command (HIGH risk → step-up) and mailed to
the registrant (`domain-auth-info` mandatory template); it is never logged or stored.

## DNS

Zones are canonical in PowerDNS; WEDOS Zone is a fallback selectable per zone. Two-phase editing:
`POST /v1/domains/{zone}/zone/changes` (stage) → `…/commit` (atomic, versioned) → `…/rollback` (previous
version). Enabling DNSSEC publishes DS records to the registrar through the same command bus.
