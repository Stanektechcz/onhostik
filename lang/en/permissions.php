<?php

declare(strict_types=1);

/*
 * TASK-0043 (permission program S1-03, ruling #20 "permission diffs show unreadable raw keys"): what a person holding each
 * permission of PermissionCatalog will be able to do, in plain words — `can`, and `cannot` / `warning` where it matters. Read
 * through CapabilityMatrix::sentence(); CapabilityMatrixTest keeps one line per catalogue key and a warning on every HIGH or
 * CRITICAL permission and on everything that runs code or deletes data. Czech: lang/cs/permissions.php.
 */

return [
    // ── the organization and its people ──
    'organization.read' => ['can' => 'See the organization, its members and projects.'],
    'organization.manage' => ['can' => 'Change the organization profile and settings.', 'cannot' => 'Add or remove people.'],
    'organization.members.manage' => ['can' => 'Invite people, change what they may do and remove them.', 'cannot' => 'Give anybody more than they hold themselves.', 'warning' => 'Decides who gets into the organization; every change asks for a fresh identity check.'],
    'organization.close' => ['can' => 'Close the organization and schedule its data for deletion.', 'warning' => 'Ends every service and deletes the data after the grace period; the owner alone holds it.'],
    'project.manage' => ['can' => 'Create and rename projects.'],
    'api_token.manage' => ['can' => 'Create and revoke API tokens and service accounts.', 'warning' => 'A token works without the person at the keyboard; it acts with the scopes it was given until it is revoked or expires.'],
    'security.settings.manage' => ['can' => 'Set two-factor rules, IP allow-lists and sign-in sessions.', 'warning' => 'Can lock people out of the organization or open it to more places.'],
    'audit.read' => ['can' => 'Read the audit log of the organization: who did what and when.'],
    'data_export.request' => ['can' => 'Request a portable export of the organization data.'],

    // ── billing ──
    'billing.wallet.read' => ['can' => 'See the account credit, holds and usage.'],
    'billing.wallet.topup' => ['can' => 'Top up the credit and set automatic top-up.'],
    'billing.invoice.read' => ['can' => 'See and download invoices, credit notes and receipts, and read billing tickets.'],
    'billing.payment_method.manage' => ['can' => 'Add and remove saved payment cards and methods.', 'warning' => 'Decides which card the organization is charged to.'],
    'billing.budget.manage' => ['can' => 'Set budgets, spending limits and alerts.'],
    'catalog.order.create' => ['can' => 'Order services and change plans.', 'cannot' => 'Pay from the account credit without an owner or billing admin.'],
    'billing.wallet.spend' => ['can' => 'Pay from the account credit and approve credit orders of others.', 'warning' => 'Spends the organization money.'],

    // ── services ──
    'service.read' => ['can' => 'See the service, its state, metrics, logs and activity.', 'cannot' => 'Change anything on it.'],
    'service.operate' => ['can' => 'Keep the service running: start, stop and restart it, switch the PHP version, clear caches, issue a certificate and force HTTPS.', 'cannot' => 'Edit files, add cron jobs, create databases or logins, open a shell or delete anything.'],
    'service.manage' => ['can' => 'Change the service: files, cron jobs, databases, FTP and database logins, deploys, applications, mailboxes and settings.', 'cannot' => 'Delete backups or open a shell.', 'warning' => 'Runs code on the service: a file or a cron job can read the passwords stored there. Includes deleting sites, databases, files and mailboxes.'],
    'service.data.delete' => ['can' => 'Delete data inside the service: sites, databases, files, mailboxes, game databases and files, staging copies.', 'cannot' => 'Delete backups or cancel the service.', 'warning' => 'What is deleted is gone from the service at once; only a backup brings it back.'],
    'service.delete' => ['can' => 'Cancel a service (it stays restorable for the grace period).', 'warning' => 'The service stops for everybody; after the grace period its data is deleted.'],
    'service.console' => ['can' => 'Open the terminal, SSH, the VNC or game console, set root access and SSH keys, rescue mode and game sub-users.', 'warning' => 'Full control of the server: whoever holds it can read every password on it and keep a way in of their own.'],
    'service.credentials.rotate' => ['can' => 'Replace the service passwords and keys.', 'warning' => 'Everything that used the old credentials stops until it gets the new ones.'],
    'service.panel_account.manage' => ['can' => 'Set the password of the game panel account (the owner alone).', 'warning' => 'The panel account opens every game server of the account, not only this one.'],
    'compute.vm.manage' => ['can' => 'Operate virtual servers: power, resize, snapshots, firewall.'],
    'compute.vm.delete' => ['can' => 'Delete virtual servers and their snapshots.', 'warning' => 'A deleted snapshot cannot be brought back.'],
    'apps.deploy' => ['can' => 'Deploy, roll back and configure applications.'],
    'game.manage' => ['can' => 'Manage game servers, mods, schedules and game backups.'],
    'mail.manage' => ['can' => 'Manage mail domains, mailboxes, aliases and relays.'],
    'database.manage' => ['can' => 'Manage managed databases and their users.'],
    'backup.read' => ['can' => 'See which backups and restore points exist.', 'cannot' => 'Download or restore them.'],
    'backup.download' => ['can' => 'Download backup archives and data exports.', 'warning' => 'An archive holds the site files with their passwords (wp-config.php, .env).'],
    'backup.restore' => ['can' => 'Restore a service from a backup.', 'warning' => 'Overwrites what is on the service now with the older state.'],
    'backup.delete' => ['can' => 'Delete backup generations.', 'warning' => 'A deleted backup is gone for good; it may be the only way back after a mistake.'],

    // ── domains and DNS (organization-wide, owner default O7) ──
    'domain.read' => ['can' => 'See the domains, their expiry and registrar status.'],
    'domain.manage' => ['can' => 'Register and renew domains, set auto-renew and contacts.'],
    'domain.transfer_out.execute' => ['can' => 'Reveal the transfer code (AUTH-ID) and move a domain to another registrar.', 'warning' => 'A domain moved away is no longer ours to bring back.'],
    'domain.registrant.change' => ['can' => 'Change who owns a domain (the registrant).', 'warning' => 'The new registrant becomes the legal holder of the domain.'],
    'dns.zone.read' => ['can' => 'See DNS zones and their history.'],
    'dns.zone.write' => ['can' => 'Edit DNS records and publish the changes.', 'cannot' => 'Switch DNSSEC on or off.'],
    'dns.dnssec.manage' => ['can' => 'Switch DNSSEC on or off and replace its keys.', 'warning' => 'A mistake makes the domain unreachable until the registry forgets the old key.'],

    // ── support ──
    'support.ticket.read' => ['can' => 'Read the support tickets about the services and projects the person looks after.', 'cannot' => 'Read billing tickets without the right to see invoices.'],
    'support.ticket.write' => ['can' => 'Open support tickets and reply to them.'],
    'support.chat.use' => ['can' => 'Use the AI assistant and the live chat.'],

    // ── partner portal ──
    'partner.portal.read' => ['can' => 'See the partner portal: clients, commissions, payouts and the payout account.'],
    'partner.payout_account.manage' => ['can' => 'Set the bank account commissions are paid to (the owner alone).', 'warning' => 'Decides where the money goes; a new account is used only after a cooling-off period.'],

    // ── staff: customers and operations (ONhost staff only; no customer role holds these) ──
    'staff.customer.read' => ['can' => 'Staff: see the customer overview — organizations, services, billing and tickets.'],
    'staff.customer.manage' => ['can' => 'Staff: edit customer organizations and memberships.', 'warning' => 'Changes who can reach a customer account.'],
    'staff.order.manage' => ['can' => 'Staff: move orders on and start provisioning.'],
    'staff.service.manage' => ['can' => 'Staff: suspend, resume and resize any customer service.'],
    'staff.service.delete' => ['can' => 'Staff: cancel any customer service, early or without its final archive.', 'warning' => 'Removes a customer service and its data; needs a second person.'],
    'staff.console' => ['can' => 'Staff: open a console on a customer server (recorded, bound to a ticket).', 'warning' => 'Full control of a customer server.'],
    'support.customer_impersonate' => ['can' => 'Staff: see the portal as a customer sees it.', 'warning' => 'Acts in a customer account; every step is audited.'],
    'staff.chargeback.decide' => ['can' => 'Staff: decide a customer request for credit back for a service cancelled early.', 'cannot' => 'Does not change the share returned; finance sets it.'],

    // ── staff: provisioning and providers ──
    'provisioning.operation.read' => ['can' => 'Staff: see operations, jobs and provider calls.'],
    'provisioning.operation.retry' => ['can' => 'Staff: retry failed operations.'],
    'provisioning.operation.cancel' => ['can' => 'Staff: cancel running operations.', 'warning' => 'A cancelled operation may leave a half-made resource to clean up.'],
    'provisioning.drift.resolve' => ['can' => 'Staff: approve or repair configuration drift.', 'warning' => 'Overwrites what the panel holds with what the platform expects.'],
    'provisioning.freeze' => ['can' => 'Staff: freeze all automated provisioning (incident switch).', 'warning' => 'Stops every order and change for every customer until lifted.'],
    'provider.instance.read' => ['can' => 'Staff: see provider instances, capabilities and health.'],
    'provider.instance.manage' => ['can' => 'Staff: register and edit provider instances.', 'warning' => 'Points the platform at a panel; a wrong one reaches the wrong servers.'],
    'provider.secret.view' => ['can' => 'Staff: reveal or rotate provider credentials.', 'warning' => 'The keys of the platform to the panels; needs a second person.'],
    'capacity.read' => ['can' => 'Staff: see capacity, headroom and inventory.'],
    'capacity.manage' => ['can' => 'Staff: approve, order and close capacity requests.', 'warning' => 'Spends money on hardware.'],
    'node.manage' => ['can' => 'Staff: put nodes into maintenance, drain and cordon them.', 'warning' => 'Moves or stops customer services on the node.'],
    'ipam.manage' => ['can' => 'Staff: manage IP pools, allocations and reverse DNS.', 'warning' => 'A wrong address takes a customer server off the network.'],
    'backup.policy.manage' => ['can' => 'Staff: edit backup policies and retention.', 'warning' => 'A shorter retention deletes older backups.'],

    // ── staff: domains and DNS ──
    'domain.registrar.manage' => ['can' => 'Staff: operate the registrar queue, contacts, NSSETs and credit.', 'warning' => 'Acts at the registry for customer domains.'],
    'dns.global.write' => ['can' => 'Staff: change ONhost infrastructure DNS and nameservers.', 'warning' => 'Every customer zone depends on it; needs a second person.'],
    'domain.critical.manage' => ['can' => 'Staff: change the critical ONhost-owned domains.', 'warning' => 'The addresses of the platform itself; needs a second person.'],

    // ── staff: finance ──
    'billing.invoice.manage' => ['can' => 'Staff: issue, correct and resend invoices.'],
    'billing.refund.execute' => ['can' => 'Staff: send refunds.', 'warning' => 'Money leaves ONhost.'],
    'billing.refund.execute_large' => ['can' => 'Staff: send refunds above the approval threshold.', 'warning' => 'Money leaves ONhost; needs a second person.'],
    'billing.credit.adjust' => ['can' => 'Staff: adjust a customer credit by hand.', 'warning' => 'Creates or takes away money on an account.'],
    'billing.credit.adjust_mass' => ['can' => 'Staff: adjust the credit of many customers at once.', 'warning' => 'Moves money on many accounts; needs a second person.'],
    'billing.tax_rule.manage' => ['can' => 'Staff: edit tax rules and registrations.', 'warning' => 'Changes the tax on every invoice issued afterwards; needs a second person.'],
    'billing.reconcile' => ['can' => 'Staff: run and resolve reconciliations.'],
    'billing.dunning.manage' => ['can' => 'Staff: run dunning, suspend and unsuspend for non-payment.', 'warning' => 'Stops or restarts a customer service.'],
    'billing.credit_line.manage' => ['can' => 'Staff: approve postpaid credit lines.', 'warning' => 'Lets a customer spend before paying.'],
    'report.read' => ['can' => 'Staff: see financial and operational reports.'],
    'billing.limit_raise.waive' => ['can' => 'Staff: grant a limit raise at no charge for one period.', 'warning' => 'Gives away what would be billed; needs a second person.'],
    'staff.billing.read' => ['can' => 'Staff: see invoices, withdrawals and billing records of every customer.'],

    // ── staff: support and incidents ──
    'staff.support.ticket.read' => ['can' => 'Staff: read the support queue and the tickets of every customer.'],
    'staff.backup.read' => ['can' => 'Staff: see the backups and restore points of customer services.'],
    'support.ticket.assign' => ['can' => 'Staff: assign and route tickets.'],
    'support.ticket.manage' => ['can' => 'Staff: reply to, escalate and resolve tickets.'],
    'support.queue.manage' => ['can' => 'Staff: manage queues, SLA policies and macros.'],
    'support.kb.manage' => ['can' => 'Staff: edit knowledge base articles.'],
    'incident.manage' => ['can' => 'Staff: open, update and resolve incidents.'],
    'incident.publish' => ['can' => 'Staff: publish incidents on the public status page.', 'warning' => 'Everybody can read it.'],
    'maintenance.manage' => ['can' => 'Staff: schedule maintenance windows.', 'warning' => 'Customers are told and services may stop in the window.'],
    'sla.credit.manage' => ['can' => 'Staff: approve SLA credits.', 'warning' => 'Gives customers money back as credit.'],
    'notification.template.manage' => ['can' => 'Staff: edit notification templates.'],
    'notification.mass.send' => ['can' => 'Staff: send mass notifications.', 'warning' => 'Reaches every customer at once; cannot be recalled.'],

    // ── staff: security, compliance, access ──
    'security.incident.manage' => ['can' => 'Staff: handle security incidents, quarantine and forensics.', 'warning' => 'Can stop a customer service.'],
    'security.event.read' => ['can' => 'Staff: read security events and anomalies.'],
    'abuse.case.manage' => ['can' => 'Staff: handle abuse and DSA cases.', 'warning' => 'Can take customer content down.'],
    'compliance.case.manage' => ['can' => 'Staff: run regulatory cases (GDPR, NIS2, Data Act).', 'warning' => 'Legal deadlines run on these cases.'],
    'compliance.legal_hold.manage' => ['can' => 'Staff: apply or lift legal holds.', 'warning' => 'A lifted hold lets data be deleted; needs a second person.'],
    'iam.user.manage' => ['can' => 'Staff: manage staff accounts.', 'warning' => 'Decides who works as ONhost staff.'],
    'iam.role.manage' => ['can' => 'Staff: create staff roles and change what roles may do.', 'warning' => 'Changes what everybody holding a role may do; needs a second person.'],
    'iam.mfa.reset' => ['can' => 'Staff: reset the second factor of another person.', 'warning' => 'The classic way to take over an account; customer owners only through a week-long recovery.'],
    'iam.jit.request' => ['can' => 'Staff: ask for a temporary raise of their own rights.'],
    'iam.jit.approve' => ['can' => 'Staff: approve temporary raises of rights.', 'warning' => 'Hands another person more power for a while.'],
    'iam.approval.decide' => ['can' => 'Staff: approve or reject what needs a second person.', 'warning' => 'The second lock on every critical action.'],
    'iam.access_review.manage' => ['can' => 'Staff: run the quarterly access reviews.'],
    'iam.break_glass' => ['can' => 'Staff: emergency access to everything.', 'warning' => 'Everything at once; every use alerts every administrator.'],
    'secret.rotate' => ['can' => 'Staff: rotate high-impact secrets.', 'warning' => 'Everything using the old secret stops; needs a second person.'],
    'audit.read.global' => ['can' => 'Staff: read the audit log of the whole platform.'],
    'ai.policy.manage' => ['can' => 'Staff: manage what the AI tools may do, their prompts and evaluations.', 'warning' => 'Changes what the assistant may do in customer accounts.'],
    'ai.ops.read' => ['can' => 'Staff: read AI runs and evaluations.'],
    'content.manage' => ['can' => 'Staff: edit public content, the changelog and documentation.'],
    'catalog.manage' => ['can' => 'Staff: edit products, plans and prices.', 'warning' => 'Changes what customers are offered and charged; price changes need a second person.'],
    'partner.manage' => ['can' => 'Staff: manage partners, commissions and payouts.', 'warning' => 'Decides what partners are paid.'],
    'feature_flag.manage' => ['can' => 'Staff: switch features on and off.', 'warning' => 'Changes the platform for every customer at once.'],
];
