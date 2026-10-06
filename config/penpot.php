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
    // the Penpot release of a NEW stack (shown on the card); a running stack keeps the images it was created with (`.env` on the node)
    'version' => '2.18',
    // pinned by digest (security review of PR #119): the multi-arch index digest of each tag on Docker Hub, read 2026-10-06 from
    // hub.docker.com/v2/repositories/<repo>/tags/<tag>. An upgrade changes the tag AND the digest here (docs/runbooks/penpot.md).
    'images' => [
        'frontend' => 'penpotapp/frontend:2.18@sha256:bb8abe27d53de84c95597f2c02c0e702b2779971fb0703e543f9ecf183e999f6',
        'backend' => 'penpotapp/backend:2.18@sha256:2df1b3440d2a82cc3571db211b4ffdfa2b89ccc910759e8d5e9387fb62971b5c',
        'exporter' => 'penpotapp/exporter:2.18@sha256:418232d6ca3120b1c2bfde298a56a05a1f41f567cd8494deac3fe7fbc186cfbd',
        'postgres' => 'postgres:15@sha256:7e2070cf6ad06fb3cbbd141b1bafbb7fd5bb63e2b6001e6daf34448eb555e4b3',
        'valkey' => 'valkey/valkey:8.1@sha256:640c5e62cea04b6d6f2084232651d0cc70362d31f4f805e7be94dbed6855e8f2',
    ],

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

    // a new stack is refused when the stacks' disk has less free than the plan's storage plus this headroom (GB)
    'min_free_gb' => 10,

    // the operator's storage quota helper (XFS project quota, docs/runbooks/penpot.md): run as `<command> '<stack>' '<GB>'` after a
    // stack is made or resized; empty = no quota on this node (the doctor warns)
    'quota_command' => '',

    // backups kept on the node per stack (the platform's backup rows carry their own retention)
    'keep_backups' => 14,

    // HTTP upload limit of the stack (the official example's value, 350 MiB)
    'max_body_size' => 367001600,

    // the secret vault path of one service's generated secrets: db://penpot/<service id>
    'secret_ref_prefix' => 'db://penpot/',
];
