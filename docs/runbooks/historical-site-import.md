# Historical site: taking it over as an import into a new site

Authority: ADR-0007, decision 22 (`docs/adr/0007-owner-decisions-2026-09-25.md`), and the owner's standing rule of 2026-09-24:
historical sites are untouchable. Vault card: H304.

## What a historical site is

A historical site is a website, mail domain or database on a live ISPConfig or aaPanel that ONhost did not create. It
was made by hand before the platform existed, and it belongs to a customer under a legacy contract. The panel shows
that a resource belongs to ONhost like this:

- aaPanel: the site's remark is `onhost:<service id>`.
- ISPConfig: the site or mail domain is owned by the organization's own client `onh_…`, and a database or login
  belongs to that very site.

Anything else is historical. Provisioning refuses it with `CONFLICT` before any write:
`providers/AaPanel/AaPanelWebProvider.php:96-99`, `providers/IspConfig/IspConfigWebProvider.php:188-197` (site) and
`:585-590` (mail domain). The error text says that adopting it is "an explicit operator decision". This runbook is that
decision's procedure, and it never binds the historical resource.

## The rule

- A takeover starts only with an **explicit request from the site's owner**, recorded in a support ticket. Nothing
  starts it automatically, and no order starts it as a side effect.
- **Ownership is checked** before anything is copied. The requester is the owner of the ONhost organization that will
  hold the new site, and the operator confirms, in the ticket, that the same person or company holds the legacy site.
  Two proofs are needed: the legacy contract or invoice record, and control of the domain (for example a TXT record the
  operator asks for, or being the registrant). A request from anybody else is refused.
- The takeover is an **import into a NEW site that the platform creates**. The customer runs the import, and the
  operator helps.
- The historical resource is **never** bound to a service, suspended, re-quota'd, re-passworded, renamed, backed up
  through a panel API or deleted by ONhost, by a person or by code. It keeps running as it is. Ending it belongs to the
  legacy contract and is done outside ONhost.

## Steps

1. **Ticket.** Record the request, the ownership proofs and the legacy site's name and node. No data is copied yet.
2. **New site.** The customer orders a web hosting in the normal flow. With no domain in the order, the site gets a
   temporary name `<short id>.web.onhost.cz` (`domains/Services/ServiceService.php:1479`,
   `onhost.provisioning.web_preview_suffix`). Use that name: it cannot collide with the historical vhost. The plan
   must include the import tool (feature `import`, enabled with `files_advanced`: `domains/Services/ServiceFeatures.php:213`).
3. **Archive.** The archive holds the files and one SQL dump per database.
   - Preferred: the site's owner exports it from their own access to the legacy site.
   - Otherwise: with the owner's written consent in the ticket, the operator makes a read-only copy outside the platform
     (SFTP read, and `mysqldump --single-transaction` with the database credentials the owner gives). The copy never
     goes through a panel write API or through a platform tool pointed at the historical site. Record the archive's
     SHA-256 in the ticket.
4. **Import.** The customer uploads the archive and runs `import.run` on the NEW service (kind `upload`, or kind `url`
   with an https link; `domains/Services/ServiceService.php:1198-1212`). Limits: archive size
   `ONHOST_UPLOAD_MAX_BYTES` (default 2 GiB), at most 20 SQL dumps (`domains/Services/Web/ImportService.php:204`), and
   the plan must have room (`domains/Services/UsageGuard.php:37`). An operator who does the clicking needs the new
   service shared with them as "Service: manage" (`svc_manage`); `import.run` falls under `service.manage`
   (`domains/Services/Commands/ServiceActionCommand.php:35-48`, `docs/runbooks/service-sharing-and-assistant.md`).
   There is no staff path that runs it on the customer's behalf.
5. **What the import changes.** Every database gets a new name, user and password on the new site. WordPress is
   re-pointed automatically: `wp-config.php` and the site URL are rewritten to the new site's name
   (`domains/Services/Web/ImportService.php:242-276`). For other applications, the owner updates the configuration.
6. **Check** the new site on its temporary name, together with the owner.
7. **Domain.** The owner pairs the real domain with the new service (`POST /v1/domains/{domain}/pair`,
   `docs/runbooks/web-tools.md`, "Pairing a domain with a hosting plan"). That moves the DNS; the certificate follows
   once the name points at the new node. For WordPress, re-point the URLs from the temporary name to the real domain
   (WP-CLI `search-replace` in the terminal).
8. **Close the ticket** with the import id, the operation id and the archive hash. The historical site stays untouched.

**Not verified** (ASSUMED from code, never run against a live panel): whether pairing the real domain works when the
new site landed on the same node as the historical vhost of that name. The guards refuse a same-named site on that
node, and whether an alias domain collides was not tested. If the new service is on the historical site's node, stop
before step 7 and escalate. Never remove the historical vhost to make room.

## Not available

- `WebMigration` moves only sites the platform owns and whose database credentials it holds
  (`domains/Services/Web/WebMigration.php:17-29`). It cannot carry a historical site.
- Import of mailboxes from a historical mail domain. No tool exists; the customer moves mail with their own client.
- A staff command that runs `import.run` for a customer. `support.customer_impersonate` is only a permission name, with
  no handler. Building one needs its own task and a security review.
