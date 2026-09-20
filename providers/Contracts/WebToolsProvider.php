<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Everything a full hosting panel offers beyond the site CRUD of WebHostingProvider: a shell and a file transport
 * bound to the site, per-site PHP settings, managed security rules, HTTP/3, cron editing, database access and
 * transfers, backup management, quotas and (aaPanel) Node projects. Adapters implement it next to WebHostingProvider;
 * the domain gates every method behind ServiceFeatures and the plan's entitlements.
 */
interface WebToolsProvider
{
    public function shell(ResourceRef $site): NodeShell;

    public function transport(ResourceRef $site): FileTransport;

    /** Are shell-backed tools usable for this site right now (agent user present, credentials configured)? */
    public function shellAvailable(ResourceRef $site): bool;

    /** Prepare the site's agent identity (ISPConfig: the jailed shell user with the instance key); a no-op where the panel API is the shell. */
    public function ensureAgent(ResourceRef $site): ProviderResult;

    public function siteUser(ResourceRef $site): string;

    /** Absolute document root currently served (site root plus run path). */
    public function documentRoot(ResourceRef $site): string;

    /** Serve the site from a sub-folder of the site root (deploy releases); `/` resets. */
    public function setDocumentRoot(ResourceRef $site, string $relative): ProviderResult;

    /** @return array<string, ?string> php, wp, composer, git, node, npm, mysql, mysqldump, redis-cli → absolute paths or null */
    public function toolPaths(ResourceRef $site): array;

    /** Command prefix that runs WP-CLI with the site's PHP (installs the phar on first use). */
    public function wpCommand(ResourceRef $site): string;

    /** @return array{version:?string, settings:array<string,string>, editable:list<string>, extensions:list<string>, open_basedir:?bool} */
    public function phpSettings(ResourceRef $site): array;

    /** @param array<string,string> $settings php.ini keys the plan allows (memory_limit, upload_max_filesize, …) */
    public function setPhpSettings(ResourceRef $site, array $settings): ProviderResult;

    /** @return array{deny:list<string>, allow:list<string>, bots:bool, hotlink:bool, hotlink_allow:list<string>, hsts:bool, headers:bool, rate:array{perip:int,perserver:int,limit_rate:int}|null, supports:list<string>} */
    public function securityRules(ResourceRef $site): array;

    /** @param array<string,mixed> $rules the same shape as securityRules() */
    public function setSecurityRules(ResourceRef $site, array $rules): ProviderResult;

    /** @return array{http2:bool, http3:bool, http3_available:bool, server:?string} */
    public function httpVersions(ResourceRef $site): array;

    public function setHttp3(ResourceRef $site, bool $enabled): ProviderResult;

    /** @param array{schedule?:string, command?:string, label?:string, active?:bool} $job */
    public function updateCron(ResourceRef $site, string $remoteId, array $job): ProviderResult;

    public function runCron(ResourceRef $site, string $remoteId): ProviderResult;

    /** Switch one cron job of the site off or on WITHOUT re-saving it (`updateCron` rewrites the schedule from what the listing could express). */
    public function setCronActive(ResourceRef $site, string $remoteId, bool $active): ProviderResult;

    /** @return list<string> */
    public function cronLogs(ResourceRef $site, string $remoteId, int $lines = 100): array;

    /** @return array{remote:bool, hosts:list<string>} */
    public function databaseAccess(ResourceRef $site, string $remoteId): array;

    /** @param list<string> $hosts IP addresses allowed remotely; empty with `$remote` = anywhere */
    public function setDatabaseAccess(ResourceRef $site, string $remoteId, bool $remote, array $hosts = []): ProviderResult;

    /** Dump one database into a local file (SQL, gzip by `.gz` extension). @param array{name?:string,user?:string,password?:string,host?:string} $credentials */
    public function exportDatabase(ResourceRef $site, string $remoteId, string $localFile, array $credentials = []): ProviderResult;

    /** Import a local SQL file (plain or gzip) into one database. @param array{name?:string,user?:string,password?:string,host?:string} $credentials */
    public function importDatabase(ResourceRef $site, string $remoteId, string $localFile, array $credentials = []): ProviderResult;

    public function deleteBackup(ResourceRef $site, string $backupRemoteId): ProviderResult;

    /** Stream one backup archive into a local file. */
    public function downloadBackup(ResourceRef $site, string $backupRemoteId, string $localFile): void;

    /** @return array{disk_used_bytes:?int, disk_limit_bytes:?int, traffic_used_bytes:?int, traffic_limit_bytes:?int, inodes_used:?int, measured_at:string} */
    public function quotas(ResourceRef $site): array;

    /** @return list<array{remote_id:string, name:string, path:string, port:?int, state:string, version:?string, domains:list<string>}> */
    public function nodeProjects(ResourceRef $site): array;

    /** @param array{name:string, path:string, script:string, port:int, version?:string, domains?:list<string>, env?:array<string,string>} $spec */
    public function createNodeProject(ResourceRef $site, array $spec): ProviderResult;

    /** start | stop | restart | delete */
    public function nodeProjectAction(ResourceRef $site, string $remoteId, string $action): ProviderResult;

    /** One-time login URL into the vendor panel for the site's client (support use), null when the panel offers none. */
    public function panelLoginUrl(ResourceRef $site): ?string;

    /** Reverse proxies of the site: a path (or the whole site) forwarded to an upstream. @return list<array{remote_id:string, name:string, path:string, target:string, enabled:bool, cache:bool}> */
    public function listProxies(ResourceRef $site): array;

    /** @param array{name:string, target:string, path?:string, cache?:bool, host?:?string} $spec */
    public function createProxy(ResourceRef $site, array $spec): ProviderResult;

    public function deleteProxy(ResourceRef $site, string $remoteId): ProviderResult;

    /**
     * Replaces the whole proxy list in one go (automation with many proxies): proxies missing from `$items` are removed,
     * new or changed ones created — panels that keep proxies in the vhost write it once.
     *
     * @param  list<array{name:string, target:string, path?:string, cache?:bool, host?:?string}>  $items
     */
    public function setProxies(ResourceRef $site, array $items): ProviderResult;

    /** The default documents (index order) the web server tries for a directory. @return list<string> */
    public function defaultDocuments(ResourceRef $site): array;

    /** @param list<string> $names */
    public function setDefaultDocuments(ResourceRef $site, array $names): ProviderResult;
}
