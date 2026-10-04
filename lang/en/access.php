<?php

declare(strict_types=1);

/*
 * TASK-0043 (permission program S1-03, D4): the words of the closed family × level matrix (CapabilityMatrix) and of the share
 * ticks of one service (ServiceAccessService::CAPABILITIES) — what the person will be able to do, what not, and what to know
 * before giving it. CapabilityMatrixTest keeps a sentence for every offered cell and a reason for every closed one.
 */

return [
    'families' => ['web' => 'Web hosting', 'mail' => 'E-mail', 'dns' => 'Domains and DNS', 'compute' => 'Virtual servers', 'game' => 'Game servers', 'database' => 'Databases', 'apps' => 'Applications'],
    'levels' => ['view' => 'View', 'operate' => 'Operate', 'manage' => 'Manage', 'console' => 'Console', 'data_delete' => 'Delete data'],

    'cells' => [
        'web' => [
            'view' => ['can' => 'See the site: state, metrics, logs and the list of backups.', 'cannot' => 'Change anything.'],
            'operate' => ['can' => 'Keep the site running: restart, switch the PHP version, clear the cache, purge the CDN, issue a certificate, force HTTPS.', 'cannot' => 'Edit files, add cron jobs, create databases or FTP logins, deploy, open a terminal or delete anything.'],
            'manage' => ['can' => 'Everything on the site: files, cron jobs, databases, FTP, deploys, WordPress, mailboxes and settings.', 'cannot' => 'Delete backups or open a terminal.', 'warning' => 'Runs code on the site and can read its passwords; includes deleting sites, databases, files and mailboxes.'],
            'console' => ['can' => 'Manage, plus the terminal and SSH accounts and keys.', 'warning' => 'Full control of the hosting account: reads every password on it and can keep a way in of their own.'],
            'data_delete' => ['can' => 'Delete data inside the site: sites, databases, files, mailboxes, staging copies.', 'cannot' => 'Delete backups, cancel the service, or change anything else.', 'warning' => 'Deleted data is gone from the site at once; only a backup brings it back.'],
        ],
        'mail' => [
            'view' => ['can' => 'See the mail domain, its mailboxes and their usage.', 'cannot' => 'Change anything or read mail.'],
            'operate' => ['can' => 'Keep mail running: restart where the plan allows it and issue certificates.', 'cannot' => 'Create, change or delete mailboxes, aliases or forwards.'],
            'manage' => ['can' => 'Create and change mailboxes, aliases, forwards, filters, spam rules and mailing lists, and delete them.', 'warning' => 'A mailbox password or a forward lets them read the mail; includes deleting mailboxes.'],
            'data_delete' => ['can' => 'Delete mailboxes.', 'cannot' => 'Create or change mailboxes.', 'warning' => 'A deleted mailbox takes its mail with it.'],
        ],
        'dns' => [
            'view' => ['can' => 'See the domains and DNS zones, with the rest of the organization read-only: services, invoices and tickets.', 'warning' => 'Domains are organization-wide: this is the organization viewer role, not a view of one domain.'],
            'operate' => ['can' => 'Edit the DNS records and DNSSEC of every domain of the organization; see the organization read-only.', 'cannot' => 'Register, transfer or renew domains.', 'warning' => 'A wrong record or DNSSEC key takes the web and mail of a domain off the internet.'],
            'manage' => ['can' => 'Register, renew and transfer domains, change contacts and holders, edit DNS records.', 'cannot' => 'Switch DNSSEC on or off.', 'warning' => 'A domain moved away or given a new holder cannot be taken back by us.'],
        ],
        'compute' => [
            'view' => ['can' => 'See the server: state, metrics and the list of backups and snapshots.', 'cannot' => 'Change anything.'],
            'operate' => ['can' => 'Start, stop and restart the server.', 'cannot' => 'Resize or reinstall it, change the firewall, open the console or delete anything.'],
            'manage' => ['can' => 'Everything on the server but its console: resize, reinstall, firewall, reverse DNS, snapshots and backups.', 'warning' => 'A reinstall wipes the server (it asks for a fresh identity check).'],
            'console' => ['can' => 'Manage, plus the VNC console, root password and SSH keys, and rescue mode.', 'warning' => 'Full control of the server: reads every password on it and can keep a way in of their own.'],
        ],
        'game' => [
            'view' => ['can' => 'See the game server: state, metrics and the list of backups.', 'cannot' => 'Change anything.'],
            'operate' => ['can' => 'Start, stop and restart the game server.', 'cannot' => 'Change files, mods, startup variables or schedules, open the console or delete anything.'],
            'manage' => ['can' => 'Files, mods, startup variables, schedules, databases and ports, deleting them too.', 'cannot' => 'Open the console or add panel sub-users.', 'warning' => 'Mods and files run code on the game server.'],
            'console' => ['can' => 'Manage, plus the game console, its sub-users and console schedules.', 'warning' => 'Full control of the game server; a sub-user keeps its own access until it is removed.'],
            'data_delete' => ['can' => 'Delete game databases and game files.', 'cannot' => 'Delete game backups or change anything else.', 'warning' => 'Deleted game data is gone at once; only a backup brings it back.'],
        ],
        'database' => [
            'view' => ['can' => 'See the database service: state, metrics and backups.', 'cannot' => 'Read or change data.'],
            'operate' => ['can' => 'Start, stop and restart the database service.', 'cannot' => 'Create databases or users, export or import data, or delete anything.'],
            'manage' => ['can' => 'Create databases and users, export and import data, change settings, delete databases.', 'warning' => 'Reads and changes every row of every database.'],
            'data_delete' => ['can' => 'Delete databases.', 'cannot' => 'Create databases or read their data.', 'warning' => 'A deleted database is gone at once; only a backup brings it back.'],
        ],
        'apps' => [
            'view' => ['can' => 'See the application: state, metrics and deployments.', 'cannot' => 'Change anything.'],
            'operate' => ['can' => 'Restart the application and clear its cache.', 'cannot' => 'Deploy, roll back or change its configuration.'],
            'manage' => ['can' => 'Deploy, roll back and configure the application.', 'warning' => 'A deploy runs new code with the secrets of the application.'],
        ],
    ],

    'reasons' => [
        'no_console' => 'This kind of service has no console to hand out.',
        'no_data_objects' => 'Nothing inside this kind of service is deleted on its own; its copies stay with the owner.',
        'domain_no_delete' => 'Domains are not deleted through a role; moving one away is part of Manage, with a warning.',
    ],

    // the ticks of sharing ONE service (panel → service → Access)
    'capabilities' => [
        'view' => ['label' => 'view', 'can' => 'See the service: state, metrics, logs and the list of backups.', 'cannot' => 'Change anything.'],
        'operate' => ['label' => 'operate (restart, PHP, cache, certificates)', 'can' => 'Keep the service running: restart, PHP version, caches, certificates, HTTPS.', 'cannot' => 'Edit files, add cron jobs, create databases or logins, open a shell or delete anything.'],
        'manage' => ['label' => 'manage and configure', 'can' => 'Change the service: files, cron jobs, databases, logins, deploys, mailboxes and settings, and delete them.', 'cannot' => 'Delete backups or open a shell.', 'warning' => 'Runs code on the service and can read its passwords.'],
        'console' => ['label' => 'console and terminal', 'can' => 'Terminal, SSH keys and root access, rescue mode, VNC, the game console and its sub-users.', 'warning' => 'Full control of the server.'],
        'data_delete' => ['label' => 'deleting data', 'can' => 'Delete sites, databases, files, mailboxes and game data inside the service.', 'cannot' => 'Delete backups or cancel the service.', 'warning' => 'Deleted data is gone at once; only a backup brings it back.'],
        'backups' => ['label' => 'download backups', 'can' => 'Download backup archives.', 'warning' => 'An archive holds the files with their passwords.'],
        'restore' => ['label' => 'restore from a backup', 'can' => 'Restore the service from a backup.', 'warning' => 'Overwrites what is on the service now.'],
        'assistant' => ['label' => 'AI assistant', 'can' => 'Use the AI assistant for this service.'],
    ],
];
