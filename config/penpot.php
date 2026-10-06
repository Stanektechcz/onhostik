<?php

declare(strict_types=1);

/*
 * Penpot for web hosting customers (TASK-0123, owner decision 7 of 2026-10-06).
 *
 * Delivery model: one Penpot per customer service, run by the platform as its own Docker Compose project on a dedicated
 * Penpot node (provider `penpot`, node role `penpot`), behind the node's reverse proxy (Caddy, automatic HTTPS). Never on a
 * shared aaPanel/ISPConfig web node: Docker there is root for every tenant and one Penpot needs gigabytes of memory.
 *
 * Every value here is a default an operator may override per node in the provider instance's `options` (same key). The
 * environment variable names and flags written into a stack come from the official Penpot self-host documentation
 * (help.penpot.app/technical-guide, docker/images/docker-compose.yaml) — see docs/runbooks/penpot.md.
 */
return [
    // the image tag of every Penpot image of a NEW stack; a running stack keeps the tag it was created with (`.env` on the node)
    'version' => '2.18',
    'postgres_image' => 'postgres:15',
    'valkey_image' => 'valkey/valkey:8.1',

    // where the customer reaches the instance: <label>.<suffix>, an A/AAAA record in the platform zone (onhost.dns.platform_zones)
    'hostname_suffix' => 'penpot.onhost.cz',

    // on the node (absolute paths; the platform's SSH user must own them)
    'root' => '/srv/onhost-penpot',
    'backup_root' => '/var/backups/onhost-penpot',
    'proxy_sites' => '/etc/caddy/onhost-penpot',
    'proxy_reload' => 'systemctl reload caddy',

    // each stack's frontend listens on 127.0.0.1:<port> only; the proxy is the one way in
    'ports' => ['from' => 19001, 'to' => 19999],

    // Penpot flags of a new stack. Registration is off: the owner account is made by the platform (manage.py create-profile, which
    // needs the PREPL server — it listens inside the stack's own network only); team members are invited from inside Penpot.
    // Email verification is off unless the operator configured SMTP for the node (`smtp` option, docs/runbooks/penpot.md).
    'flags' => ['disable-registration', 'enable-login-with-password', 'enable-prepl-server', 'disable-onboarding'],
    'flags_without_smtp' => ['disable-email-verification', 'enable-log-emails'],

    // what one instance gets when the plan says nothing (entitlements ram_mb / cpus / storage_gb override)
    'defaults' => ['ram_mb' => 4096, 'cpus' => 2, 'storage_gb' => 20],

    // the valkey cache of one stack (official example: --maxmemory 128mb --maxmemory-policy volatile-lfu)
    'valkey_maxmemory' => '128mb',

    // backups kept on the node per stack (the platform's backup rows carry their own retention)
    'keep_backups' => 14,

    // HTTP upload limit of the stack (the official example's value, 350 MiB)
    'max_body_size' => 367001600,

    // the secret vault path of one service's generated secrets: db://penpot/<service id>
    'secret_ref_prefix' => 'db://penpot/',
];
