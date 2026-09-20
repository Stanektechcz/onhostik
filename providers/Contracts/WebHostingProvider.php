<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Shared/managed PHP executor contract (ISPConfig, aaPanel). Every method maps to a customer-visible feature of the
 * service (docs/provider-adapters/*.md): the control plane never names the executor to the customer, it only shows
 * what `siteFeatures()` declares and drives it through these calls.
 */
interface WebHostingProvider extends BackupCapable, InfrastructureProvider
{
    /**
     * Feature catalogue of this executor for one site. Keys: php (versions), databases, ftp, ssl, https, cron, logs,
     * backups, restore, subdomains, redirects, ssh, mail, file_manager, usage. Values: true/false or an array of options.
     *
     * @return array<string, bool|array<string,mixed>>
     */
    public function siteFeatures(): array;

    /** @return list<string> PHP versions the executor can run (e.g. 8.1, 8.2, 8.3, 8.4) */
    public function phpVersions(): array;

    /** @param array<string,mixed> $spec name, user, password, charset, quota */
    public function createDatabase(ResourceRef $site, array $spec): ProviderResult;

    /** @return list<array{remote_id:string,name:string,user:?string,charset:?string,size_bytes:?int}> */
    public function listDatabases(ResourceRef $site): array;

    public function deleteDatabase(ResourceRef $site, string $remoteId): ProviderResult;

    public function setPhpVersion(ResourceRef $site, string $version): ProviderResult;

    public function issueCertificate(ResourceRef $site, array $domains): ProviderResult;

    /** @return array{issued:bool,letsencrypt:bool,expires_at:?string,issuer:?string,domains:list<string>,https_forced:bool} */
    public function certificate(ResourceRef $site): array;

    public function forceHttps(ResourceRef $site, bool $enabled): ProviderResult;

    /** @param array{schedule:string,command:string,label?:string} $job */
    public function createCron(ResourceRef $site, array $job): ProviderResult;

    /** @return list<array{remote_id:string,schedule:string,command:string,label:?string,active:bool}> */
    public function listCron(ResourceRef $site): array;

    public function deleteCron(ResourceRef $site, string $remoteId): ProviderResult;

    /** @param array{user:string,password:string,path?:string,quota_mb?:int} $account */
    public function createFtpAccount(ResourceRef $site, array $account): ProviderResult;

    /** @return list<array{remote_id:string,user:string,path:?string,active:bool}> */
    public function listFtpAccounts(ResourceRef $site): array;

    public function deleteFtpAccount(ResourceRef $site, string $remoteId): ProviderResult;

    public function setFtpPassword(ResourceRef $site, string $remoteId, string $password): ProviderResult;

    /** Switch one FTP account of the site off or on again (a suspended site's files are not reachable by FTP either). */
    public function setFtpAccountActive(ResourceRef $site, string $remoteId, bool $active): ProviderResult;

    /** @param array{domain:string,path?:string} $subdomain additional host name served by the site (alias or sub-folder) */
    public function addSubdomain(ResourceRef $site, array $subdomain): ProviderResult;

    /** @return list<array{remote_id:string,domain:string,path:?string}> */
    public function listSubdomains(ResourceRef $site): array;

    public function removeSubdomain(ResourceRef $site, string $remoteId): ProviderResult;

    /** @param array{target:string,type?:string} $redirect target '' clears the redirect; type 301 (default) or 302 */
    public function setRedirect(ResourceRef $site, array $redirect): ProviderResult;

    /** @return array{target:?string,type:?string} */
    public function redirect(ResourceRef $site): array;

    /** @return list<string> last lines of the requested log */
    public function tailLog(ResourceRef $site, string $log = 'access', int $lines = 200): array;

    /** @return array{cpu_pct:float|null,mem_pct:float|null,disk_pct:float|null,load:float|null,sites:int|null} */
    public function nodeLoad(?string $node = null): array;

    // ── extended site management: what the prototype's web workbench offers beyond the basics ──────────────────

    /**
     * Site-level settings for the extra tabs: `errordocs` (custom error pages on/off, null = not offered),
     * `directives` (`apache`/`nginx`/`rewrite` text as offered), `stats` (`type`, `url`, `user`), `db_admin_url`,
     * `document_root`, `site_password` (aaPanel site-wide basic auth user, null when unknown).
     *
     * @return array<string,mixed>
     */
    public function siteSettings(ResourceRef $site): array;

    public function setErrorDocs(ResourceRef $site, bool $enabled): ProviderResult;

    /** @param 'apache'|'nginx'|'rewrite' $kind */
    public function setDirectives(ResourceRef $site, string $kind, string $content): ProviderResult;

    /** @return list<array{remote_id:string, path:string, users:list<string>}> */
    public function listProtectedFolders(ResourceRef $site): array;

    /** @param array{path:string, user:string, password:string} $spec */
    public function protectFolder(ResourceRef $site, array $spec): ProviderResult;

    public function unprotectFolder(ResourceRef $site, string $remoteId): ProviderResult;

    /** @return list<array{remote_id:string, user:string, databases:list<string>}> */
    public function listDbUsers(ResourceRef $site): array;

    /** @param array{user:string, password:string} $spec */
    public function createDbUser(ResourceRef $site, array $spec): ProviderResult;

    public function setDbUserPassword(ResourceRef $site, string $remoteId, string $password): ProviderResult;

    public function deleteDbUser(ResourceRef $site, string $remoteId): ProviderResult;

    /** @return list<array{remote_id:string, user:string, has_key:bool, chroot:bool}> */
    public function listShellUsers(ResourceRef $site): array;

    /** @param array{user:string, password:string, ssh_key?:?string} $spec */
    public function createShellUser(ResourceRef $site, array $spec): ProviderResult;

    public function setShellKey(ResourceRef $site, string $remoteId, string $sshKey): ProviderResult;

    public function deleteShellUser(ResourceRef $site, string $remoteId): ProviderResult;

    /** @param array{type:string, password?:?string} $spec statistics engine (`awstats`, `goaccess`, `webalizer`, `none`) */
    public function setStats(ResourceRef $site, array $spec): ProviderResult;

    /** @param array{cert:string, key:string, chain?:?string} $cert PEM */
    public function uploadCertificate(ResourceRef $site, array $cert): ProviderResult;

    /** @return array{path:string, entries:list<array{name:string, type:string, size:?int, modified:?string}>} path relative to the site root */
    public function listFiles(ResourceRef $site, string $path): array;

    public function readFile(ResourceRef $site, string $path): string;

    public function writeFile(ResourceRef $site, string $path, string $content): ProviderResult;

    public function deleteFile(ResourceRef $site, string $path, bool $directory = false): ProviderResult;

    public function createDirectory(ResourceRef $site, string $path): ProviderResult;

    /** @return list<array{name:string, version:?string, title:?string}> one-click applications the panel can install */
    public function listApps(ResourceRef $site): array;

    /** @param array{name:string, php_version?:?string} $spec */
    public function installApp(ResourceRef $site, array $spec): ProviderResult;
}
