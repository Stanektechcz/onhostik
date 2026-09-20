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

## 3. Check on staging

* share a service with an address outside the organization, accept in a private window, confirm the guest sees one
  service and `403` everywhere else; revoke and confirm the panel accounts they created are gone;
* `select state, count(*) from service_access_grants group by state;` — `pending` older than seven days are
  invitations nobody accepted (the invitation has expired; share again);
* ask the assistant as a `support_contact` about invoices (nothing), as the owner (the documents).
