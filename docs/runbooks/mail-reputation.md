# Mail reputation alerts

`onhost:mail:blocklist` runs hourly (minute 25) and asks the blocklists about every sending address and customer mail
domain. `/metrics` exports `onhost_mail_blocklist_listings{weight,kind}`, `onhost_mail_blocklist_refused` and
`onhost_mail_reputation_last_check_timestamp`; the alerts live in `infra/monitoring/slo-alerts.yml` (A5).

## OnhostMailBlocklisted (ticket)

A `major` or `minor` listing of a sending address or domain has been open for 30 minutes (`info` lists do not alert).

1. Open the mail reputation view in the staff admin and find the listed address or domain and the list that names it.
2. Find the cause before asking for delisting: a compromised mailbox or form, a customer bulk send, a missing SPF/DKIM/DMARC
   record. Hold the offending outbound mail and contact the customer through support.
3. Request delisting on the blocklist's own site only after the cause is fixed; the next hourly check clears the alert.

## OnhostMailReputationStale (ticket)

No blocklist answered for 6 hours although one did before (0 = never asked is the doctor's row, not an alert).

1. Check the scheduler is running (`php artisan schedule:list`, the doctor's scheduler row) and the last
   `onhost:mail:blocklist` run in the logs.
2. Check `onhost_mail_blocklist_refused`: if every list refuses our resolver, the resolver needs its own (non-public)
   DNS upstream.
3. Run `php artisan onhost:mail:blocklist` once by hand and confirm the timestamp moves.
