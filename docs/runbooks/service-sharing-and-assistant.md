# Sharing a service and the assistant — who may see and do what

Two features added on 2026-09-20 that ask the same authorizer as every other endpoint. Nothing about a shared service or
about the assistant is a special case in the code that acts on a service.

## 1. One service handed to another person

The customer's freelancer, agency or colleague looks after **one** service. Panel → service → *Provoz a NOC* →
**Přístupy** (every family has that tab); API `GET/POST /v1/services/{service}/access`, `DELETE …/access/{grant}`,
`GET /v1/me/shared-services`.

| Capability the owner ticks | Role on the binding | What it allows |
| --- | --- | --- |
| `view` (always added) | `svc_view` | state, metrics, logs, operations, the list of backups |
| `manage` | `svc_manage` | actions and settings: restart, PHP, databases, cron, files, deploys, mailboxes |
| `console` (adds `manage`) | `svc_console` | terminal, VNC, game console — a shell is more than managing, never less (H334) |
| `backups` | `svc_backups` | download backup archives (taking data away is a decision of its own, H344) |
| `restore` | `svc_restore` | restore the service from a backup |
| `assistant` | `svc_assistant` | the AI assistant, about the shared service only |

* **Never handed out here:** cancelling the service, anything about money, members or domains.
* Sharing takes `organization.members.manage` and a step-up (it lets somebody new in); managing the service is not
  enough. Nobody hands out what they do not hold on that service themselves (`capability_above_own`). API tokens cannot
  share (`TokenRouteScope`).
* Somebody who is not in the organization is invited as **`guest`** — a member who sees nothing of the organization by
  that membership: no invoices, no team, no other service. The mail (`service-shared`) carries the ordinary accept link;
  accepting activates every share waiting for that address. A colleague who already is a member gets the access at once.
* Accepting a guest invitation never takes a role away: somebody who became a real member in the meantime keeps their
  role and the share is activated. Sharing sends mail to addresses the customer typed, so an organization sends at most
  30 guest invitations in 24 hours (`share_invitations_limit`, 429; counted on the invitations themselves); a colleague
  who already is a member needs none. Sharing again with an address whose invitation is still waiting changes what the
  person will be able to do and sends **no second mail** — the link already sent keeps working. To send the mail again,
  end the share and share anew (that one counts against the ceiling).
* The permission lives in **resource-scoped policy bindings**, one per capability, with the same `expires_at` as the
  share: the access stops at that second by itself; `onhost:access:expire` (every five minutes) closes the record.
* Ending a share (revoked, expired, or the person left the organization) takes back what they put on the panel under
  their own name — SSH keys, game collaborator accounts (`RevokeDelegatedAccess`) — unless another role still covers
  the service. A guest with nothing shared any more leaves the organization.
* Record: `service_access_grants` (who, what, since, until, by whom, note); audit `service.access.grant|revoke|expire`;
  events `service.access.granted|revoked|expired` (customer notification).

Tests: `tests/Feature/Services/ServiceAccessShareTest.php`, `tests/Feature/Provisioning/OperationSafetyTest.php`
(a run started by a guest or a project member is not stopped as "permission revoked"),
`tests/Feature/Identity/ReadOnlyRoleTest.php`.

## 2. The assistant sees what the signed-in person sees

`Support\Assistant\AssistantScope` is built from the authorizer for every conversation. Every read tool and every
proposal goes through it:

| Asked about | Needs |
| --- | --- |
| invoices, dunning, "pay" button | `billing.invoice.read` |
| credit | `billing.wallet.read` |
| orders | `organization.read` |
| domains | `domain.read` |
| tickets / hand-off to a human | `support.ticket.read` / `support.ticket.write` |
| services | organization `service.read`, or the projects and single services the person reads |
| a button for an action | the permission the API would ask for that action, at that service |

* The assistant **never executes**: a proposal is a button; the click goes through `POST /v1/services/{id}/actions`
  under the person's own permissions, step-up and audit. `terminate`, `purge`, `restore`, `resize`, `suspend`,
  `resume` are never proposed.
* `get_service_resource` reads one listing of one visible service from an allow-list per family (no file contents, no
  `reveal`), redacted and cut to size.
* A guest opens the chat only when the share includes `assistant`; asking for a human does not open a ticket in the
  owner's organization in the guest's name.
* **Staff:** `POST /v1/staff/assistant/chat {organization_id, text}` (`staff.customer.read`) — the same assistant over
  ONE customer's account (customer drawer → *AI asistent*). Proposals are marked `STAFF_WRITE` and run through the staff
  path after the operator confirms; no hand-off; conversations are kept per staff member and customer; every question
  is audited (`assistant.chat`) with the organization it was about. Model output is rendered as text (escaped).
* What goes to the LLM provider: the question, the facts and listings above. No credentials, no file contents. With
  no provider configured the deterministic answer bank and `ServiceIntent` answer — the contract is the same.

Tests: `tests/Feature/Support/AssistantScopeTest.php`, `tests/Feature/Support/SupportTest.php`.

### Reading DNS and a document

* `get_dns_records {zone}` — the records of one zone of the organization (name, type, content, TTL, who manages it; the
  first 80). Needs `domain.read`.
* `get_invoice {number}` — one document by its number: type, state, dates, totals, what is left to pay, the variable
  symbol, the lines. Needs `billing.invoice.read`.

Both are read-only, scoped to the organization of the conversation, and not offered to somebody who may not read that
in the panel. A conversation belongs to one person (`security-boundaries.md` §12).

### "Is my service all right?" (`ServiceHealthCheck`)

One pass over what the platform already knows — no panel is asked: state, whether the service can be managed right
now, the last backup (the archive of a cancellation does not count), the HTTPS certificate and its expiry, the uptime
monitor, operations that failed in the last day, how close the service is to its limits. A verdict (`ok|warn|bad`) and
a sentence per finding in both languages, the worst first. Nothing about money is in it, so a guest a service was
shared with may read it.

* API: `GET /v1/services/{id}/health` (`service.read`).
* Assistant tool `check_service` — and a rule-based answer without a model: „zkontroluj mi shop.cz", „je všechno
  v pořádku?", "is my site ok?". The service is the one the text names; with none named, the only one the person
  sees; with several it asks which. A question about an invoice or a payment is not taken for a service check.
* Support gets the same through the staff assistant over the customer's account.

## 3. Check on staging
* share a service with an address outside the organization, accept in a private window, confirm the guest sees one
  service and `403` everywhere else; revoke and confirm the panel accounts they created are gone;
* `select state, count(*) from service_access_grants group by state;` — `pending` older than seven days are
  invitations nobody accepted (the invitation has expired; share again);
* ask the assistant as a `support_contact` about invoices (nothing), as the owner (the documents).

### A reply drafted for the agent (`TicketReplyDrafter`)

`POST /v1/staff/tickets/{ticket}/draft` (`support.ticket.manage`; body: `hint?`, `locale?`) — the console's ticket detail
has the button *Navrhnout odpověď*; the draft lands in the reply box. **Nothing is sent**: the agent reads, edits and posts
it through the ordinary reply route.

* Built from what the platform knows: the last eight public messages, the customer's account facts, and — when the ticket
  names a service of that organization — its health check (state, control plane, last backup, certificate, monitoring,
  failed operations, limits). The answer carries the draft, its `source` (`llm` | `rules`), the `basis` it was written
  from and `warnings` (the check found something; the model did not answer).
* What goes to a model is **assembled by the platform, not fetched by the model**: it gets no tools, so a customer's
  message that says "ignore your instructions and …" has nothing to call. The conversation is handed over as data,
  credentials masked; **internal notes are never included** (a model must not quote them back to the customer); the
  model's own words pass the mask before the agent sees them. The prompt forbids promising refunds, compensations,
  deadlines and prices.
* Without a configured provider — or when it fails — the same findings are written as sentences by rules.
* Every draft is on record: an `AiRun` (`session_id = staff:<user>:ticket:<ticket>`, tokens, the draft) and the audit
  event `support.ticket.draft`.

### What a person typed is masked (`SecretMask`)

People paste passwords, card numbers and birth numbers into a chat or a ticket. The assistant needs none of them — it
takes no passwords, it proposes actions — and the text goes to a model, into a transcript and into a handoff ticket. The
chat masks its input before anything sees it (`heslo je …`, `password: …`, `PIN = …`, card numbers checked by Luhn, Czech
birth numbers, everything `Redactor::redactString` knows); variable symbols, invoice numbers and phone numbers stay
readable. The ticket itself keeps what the customer wrote — only the copy that goes to a model is masked.

Tests: `tests/Feature/Support/TicketReplyDraftTest.php`.

## What the model may cost (2026-09-20)

The chat route had the general API limit and nothing else: 120 questions a minute for one user or token, each a model call
with a few thousand tokens of context (several, when the model uses its tools). A script with a customer's API token could
run up the operator's bill without ever touching a service. `Support\Assistant\AssistantBudget`:

| Limit | Default | Env |
| --- | --- | --- |
| model answers a person gets in an hour | 40 | `ONHOST_AI_USER_PER_HOUR` |
| the same for a member of staff (chat over a customer's account, reply drafts) | 120 | `ONHOST_AI_STAFF_PER_HOUR` |
| model answers an organization gets in a day (staff working on its account do not spend it) | 300 | `ONHOST_AI_ORG_PER_DAY` |
| tokens the whole platform may use in a day, input + output (0 = no ceiling) | 3 000 000 | `ONHOST_AI_TOKENS_PER_DAY` |

**Nothing is refused.** Past a limit the assistant answers from the help centre and the platform's own records — the path
it takes whenever the model does not answer — says so in one sentence, and a person can still be asked for; a reply draft
is written by rules with a warning. The run is recorded with `tools_called: [{tool: llm, error: budget:<limit>}]`,
operations are told once a day per limit and subject (`assistant.budget.exhausted`; the platform's ceiling is `hot`), and
`onhost:doctor` (area `automation`) shows the day's use against the ceiling (warns from 80 %). Days are the seller's days
(`AccountingClock`).

A conversation is kept under `<who>:<the id the client sends>` — `staff:<user>:<organization>:` for staff, `<user>:` for a
customer. The column took 80 characters and the console's own id made 82: on PostgreSQL every question a support agent
asked answered 500 (SQLite did not mind). The column holds 160 now (migration `000800`).

Tests: `tests/Feature/Support/TicketReplyDraftTest.php`, `tests/Feature/Platform/DeclaredColumnWidthTest.php`.

## What the assistant may put on a button (2026-09-20)

**The hole.** The model proposed ANY action a service offers, with parameters and a button label of its own making
(`propose_service_action`), and the customer confirmed a dialog that showed the label alone. A model that followed an injected
instruction — a ticket, a file name, a page it was asked to look at — could offer "Vyčistit cache", and the click ran
`command.run` with `curl … | sh`, saved a PHP file, changed an FTP password, created a cron job, deleted a database or
redirected the site (each proven by `tests/Feature/Support/AssistantProposalsTest.php` against the old code, where the button
was classed `SAFE_WRITE`). A confirmation protects nobody who cannot see what they confirm.

**The rule** (`Support\Assistant\AssistantProposals`):

* **safe by default** — an action that is not on the list is never proposed; new actions start outside it;
* on the list are actions that can be undone or repeated and whose parameters are a *choice*, not a *payload*: `power`
  (start, reboot, shutdown — never stop, reset, kill), `backup`, `snapshot`, `deploy.run`, `wp.update`, `wp.cache`,
  `staging.refresh`, `staging.push`, `cdn.purge`, `ssl.issue`, `https.force`, `php.set`. No command, no file content, no
  credential, no destination (URL, address, host), no deletion, nothing that asks for a fresh step-up — a guard test holds
  the last point;
* a parameter that is not listed never travels; a listed one has to be one of its values, or the proposal is refused;
* the **label is the platform's**, composed from the action and its parameters (`Přepnout PHP na 8.3 · shop.cz`). The model's
  words stay in the conversation;
* for everything else the assistant tells the customer where in the panel to do it.

The customer's own clicks in the panel are untouched by this: the list limits what the *assistant* proposes, not what a
person may do.

## Access to a virtual server after it was delivered (2026-09-20)

A server is delivered with the SSH keys of its order and nothing else, and there was no way to change them: whoever lost the
key — or ordered without one — could not get into their own server. `access.reset` (feature `vm_access`, family `cloud`
only: a managed database has no root for its customer) sets new SSH keys and/or a new password for the administrator through
cloud-init. Only what is given is sent (`cipassword`, `sshkeys`): the address, the gateway and the user stay. The keys
**replace** the ones there are; the panel makes the cloud-init drive again and the server reads it at its next start — the
panel says so and offers the reboot. HIGH risk with a fresh step-up (new keys open the server); the operation forgets the
password once it has run; the assistant never proposes it. Unverified on a live Proxmox: that the guest's cloud-init applies
a changed password on reboot (images differ — the supported images have to be checked on staging).

Tests: `tests/Feature/Http/PanelApiTest.php`.
