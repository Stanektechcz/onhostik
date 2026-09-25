# Audit of past provider calls (ISPConfig ownership)

Did anybody use the hole TASK-0005 closed before it was closed? Until then a customer could name **any** ISPConfig
remote id — a shell user (`shell.key`), a database user (`dbuser.password`, `dbuser.delete`), a mailbox or alias
(`mailbox.update`, `mailbox.delete`, `alias.delete`, `autoresponder.set`, `spam.policy`, `filter.*`,
`mailbox.backup`/`restore`, `fetchmail.create`) — and the platform acted on it without checking that the service owned
it. ISPConfig answers one administrator session and never asks whose record a `primary_id` is, and ids on a shared node
are consecutive. The fix is described in `security-boundaries.md` §19; this runbook is about the time **before** it.

```
php artisan onhost:audit:provider-calls [--days=90 | --since=2026-06-01 [--until=2026-09-25]]
                                        [--instance=ispconfig-shared01 ...] [--format=md|json]
                                        [--output=reports/<name>] [--stdout] [--include-clean]
```

Exit code 0 = nothing CRITICAL or HIGH, 1 = at least one such row, 2 = bad options.

## What it does — and what it never does

* **Read-only.** It reads `provider_calls`, `operations`, `operation_attempts`, `services` (removed ones included),
  `provider_bindings`, `mail_domains` and `provider_instances`. It writes no row, goes through no CommandBus, resolves
  no provider adapter and **calls no panel** (test: `tests/Feature/Provisioning/ProviderCallsAuditTest.php`, "changes
  nothing"). It has no schedule entry, no route and no event.
* Its only output is the report: one file on the private local disk (`storage/app/private/reports/…`, never a link)
  or stdout with `--stdout`.
* **ISPConfig only.** aaPanel resolved every id against the site's own listing and was clean.

### How it decides

1. **The actions.** Every `service.action` operation in the window whose action is one of the list above
   (`Onhost\Domain\Provisioning\Audit\OwnershipAuditTargets`; the mail half is derived from the guard
   `ServiceActionWorkflow::OWN_MAIL_TARGETS`, so the two cannot drift).
2. **Was the write sent?** ISPConfig calls are logged with `operation_id` NULL (no call site passes it), so the write
   is tied to the operation by **time** (its attempts' window ± 2 s), the **same function** and the **same id**.
   `sent` = `accepted` (the panel answered body code `ok` — HTTP 200 alone proves nothing, ISPConfig refuses inside a
   200), `uncertain` (no answer came back — a 5xx, a timeout — so the write may have been applied; graded like
   `accepted`), `refused`, `not_sent`, or `none` (the action writes nothing). `spam.policy` writes the spam-filter row, whose id
   is not the mailbox's, and is tied by time only (note `tied_by_time_only`).
3. **Whose was the record?** From evidence the platform already logged, **of any age** (a listing older than the window
   still proves ownership; ISPConfig does not reuse ids — ASSUMED): site listings (`sites_shell_user_get`,
   `sites_database_get` by `parent_domain_id`), reads by id (`sites_database_user_get`, `mail_user_get`), and records
   the platform created (`*_add`). A site is matched to its service through `provider_bindings`; a database or shell
   user by its name prefix (`services.name_prefix`, e.g. `oh1yz8n6_`); a mailbox or alias by the domain of its address
   (`provider_bindings.meta.domain` and `mail_domains`, which keeps a removed domain).
4. **Verdict and severity**

   | verdict | meaning | severity |
   | --- | --- | --- |
   | OWN | the acting service's own record | CLEAN (listed only with `--include-clean`) |
   | FOREIGN_PLATFORM, other organization | another customer's record | **CRITICAL** when accepted, **HIGH** otherwise (an attempt) |
   | FOREIGN_UNMANAGED | nothing on the platform owns it — a historical resource the owner rules make untouchable | **CRITICAL** when accepted, **HIGH** otherwise |
   | FOREIGN_PLATFORM, same organization | another service of the same customer | MEDIUM |
   | FOREIGN_PLATFORM, owner unknown | the platform made it but no longer knows for whom (note `owner_service_unknown`) | REVIEW |
   | UNKNOWN | no evidence in the logs | REVIEW when the panel was asked, INFO when nothing was sent |

   Evidence that disagrees takes the worst reading (note `conflicting_evidence`).
5. **Probing.** Operations that FAILED with the guard's `error.detail.not_ours` or the adapter's "not found on this
   site" — someone still trying after the fix. Grouped per actor; HIGH at three refusals or consecutive ids.
6. **Writes no operation explains.** `sites_shell_user_update/delete`, `sites_database_user_update/delete`,
   `mail_user_update/delete`, `mail_alias_delete` in the window that no action above accounts for. HIGH when the
   record is FOREIGN_UNMANAGED, REVIEW when there is no evidence and the panel accepted, INFO for platform records
   (a site's leftovers removed, sending switched for a whole domain). Each listed row names the service action that
   ran nearest in time on the instance (± 60 s) as a hint.
7. **Coverage.** Per instance: the oldest and newest logged call, the number of rows, rows that could not be read
   (cut at 16 KB or not JSON — never guessed at) and a warning when the log starts after the window start.

## Running it

1. **Where.** Preferably on **staging with a restored copy of the production database** (the tables above must be in
   the copy). Running it read-only directly on production is the owner's call. Development never runs it against
   production data.
2. **Coverage first.** Nothing in the code prunes `provider_calls` (the logger's docblock promises 90 days and a prune
   command that does not exist), so the real depth of history is only known from this report. If the oldest row is
   newer than the window start, write down that the check could not cover the full window.
3. **CRITICAL / HIGH rows** are incident work for the owner (see below). The exit code is 1.
4. **REVIEW rows**: look the record up **read-only** on the ISPConfig master — the row prints the `sys_datalog` key
   (`dbtable` and `dbidx`, e.g. `web_database_user database_user_id:99`): `select * from sys_datalog where dbtable =
   'web_database_user' and dbidx = 'database_user_id:99' order by datalog_id`, or open the record in the panel UI and
   read its client/site. This is an operator-only action against a live panel; no agent does it.
5. `--format=json` gives the same content for further processing; `--include-clean` also lists the OWN actions and the
   INFO writes (a longer report, useful to see that the tie by time found the writes at all).

## What the report shows (and what it never shows)

Whitelisted fields only: ids (operation, service, organization, provider call, actor), instance, function, id, body
code, time, verdict, severity, evidence call ids and the `sys_datalog` key. **Never** a logged request or answer —
rows written before the H12 masks may still hold passwords and keys. Names keep their service prefix and lose the
rest (`oh1yz8n6_s***`), addresses keep their domain (`i***@other.cz`), a planted SSH key appears only as its
fingerprint (`Redactor::fingerprint` of the key string as it was sent: the first 16 hex characters of the SHA-256 of
its JSON form — not the `ssh-keygen -l` fingerprint; compute it the same way on a key found on the node to compare),
and every string passes `Redactor` and `SecretMask`.

## A CRITICAL or HIGH row — incident steps (owner / legal)

1. Treat it as a possible personal-data breach: start the incident record (`incident-response.md`) and assess the
   notice to the supervisory authority within **72 hours** of becoming aware (GDPR art. 33) and to the affected
   customer (art. 34).
2. Contain through the normal audited paths, never by hand on the panel: reset the affected database user's or
   mailbox's password, remove a foreign SSH key from the shell user (match it by the printed fingerprint), check the
   mailbox for forwarding rules and filters that were not the owner's.
3. For a FOREIGN_UNMANAGED record (a historical site the platform did not create): the owner rules make it untouchable
   for the platform — contact its owner through the operator, do not change it from the platform.
4. Apply the abuse process to the acting account (the row's organization and actor).
5. Probing rows (HIGH): the same abuse process; the attempts were refused, nothing to contain.

## The report file

It holds customer and service ids and masked addresses: **confidential**. Keep it on the private disk, do not attach
it to tickets or chats, and delete it (`storage/app/private/reports/provider-calls-audit-*`) when the incident review
is closed.

## Known limits

* The tie by time can in principle attach a write to the wrong operation on a busy instance; requiring the same
  function **and** the same id keeps this unlikely, not impossible.
* Before TASK-0005 `setShellKey` and `setDbUserPassword` read no listing, and a successful pre-fix `dbuser.delete`
  always named an id that was not in the site's own listing: expect REVIEW rows where the evidence has to come from
  other listings.
* ISPConfig's remote API not enforcing client ownership, and ids never being reused, are ASSUMED (TASK-0005 handoff).
