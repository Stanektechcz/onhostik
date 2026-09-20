<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Everything a game panel offers around one server beyond create/power/console (Pterodactyl client + application
 * API): live status, startup variables and the container image, schedules, databases, collaborators, files,
 * network allocations, backup housekeeping and the customer's own account on the panel. Every method takes the
 * server's ResourceRef; results carry stable `remote_id`s the platform can act on later. Adapters that do not offer a
 * part throw ProviderException(VALIDATION) and the feature catalogue hides the tab.
 */
interface GameToolsProvider
{
    /** Collaborator permission presets (panel permission keys): the customer picks a preset, never a raw list. */
    public const SUBUSER_PRESETS = [
        'console' => ['control.console', 'control.start', 'control.stop', 'control.restart', 'websocket.connect'],
        'files' => ['control.console', 'control.start', 'control.stop', 'control.restart', 'websocket.connect', 'file.create', 'file.read', 'file.read-content', 'file.update', 'file.delete', 'file.archive', 'file.sftp'],
        'full' => ['control.console', 'control.start', 'control.stop', 'control.restart', 'websocket.connect', 'file.create', 'file.read', 'file.read-content', 'file.update', 'file.delete', 'file.archive', 'file.sftp', 'backup.create', 'backup.read', 'backup.restore', 'backup.download', 'schedule.create', 'schedule.read', 'schedule.update', 'schedule.delete', 'database.create', 'database.read', 'database.update', 'database.delete', 'database.view_password', 'startup.read', 'startup.update', 'settings.rename'],
    ];

    /** @return array{state:string, cpu_pct:float, mem_bytes:int, mem_limit_bytes:int, disk_bytes:int, disk_limit_bytes:int, uptime_s:int, net_in_bytes:int, net_out_bytes:int, installing:bool, suspended:bool} */
    public function status(ResourceRef $server): array;

    /** @return array{name:string, description:?string, sftp:array{host:string,port:int,username:string}, allocation:?array{ip:string,port:int,alias:?string}, egg_features:list<string>, docker_image:string, invocation:string} */
    public function serverDetail(ResourceRef $server): array;

    /** @return array{startup:string, raw_startup:string, docker_image:string, docker_images:array<string,string>, variables:list<array{name:string,key:string,value:string,default:string,description:string,editable:bool,rules:string}>} */
    public function startup(ResourceRef $server): array;

    public function setVariable(ResourceRef $server, string $key, string $value): ProviderResult;

    public function setDockerImage(ResourceRef $server, string $image): ProviderResult;

    public function rename(ResourceRef $server, string $name): ProviderResult;

    public function reinstall(ResourceRef $server): ProviderResult;

    /** @return list<array{remote_id:string,name:string,cron:string,active:bool,processing:bool,only_when_online:bool,last_run_at:?string,next_run_at:?string,tasks:list<array{action:string,payload:string,sequence:int}>}> */
    public function listSchedules(ResourceRef $server): array;

    public function setScheduleActive(ResourceRef $server, string $scheduleId, bool $active): ProviderResult;

    public function runSchedule(ResourceRef $server, string $scheduleId): ProviderResult;

    public function deleteSchedule(ResourceRef $server, string $scheduleId): ProviderResult;

    /** @return list<array{remote_id:string,name:string,username:string,host:string,port:int,connections_from:string,password:?string}> passwords only when `$reveal` */
    public function listDatabases(ResourceRef $server, bool $reveal = false): array;

    /** @return ProviderResult data: remote_id, name, username, host, port, password (shown once) */
    public function createDatabase(ResourceRef $server, string $name, string $remote = '%'): ProviderResult;

    public function rotateDatabasePassword(ResourceRef $server, string $databaseId): ProviderResult;

    public function deleteDatabase(ResourceRef $server, string $databaseId): ProviderResult;

    /** @return list<array{remote_id:string,email:string,username:?string,permissions:list<string>,created_at:?string}> */
    public function listSubusers(ResourceRef $server): array;

    /** @param list<string> $permissions panel permission keys (e.g. control.console, file.read) */
    public function createSubuser(ResourceRef $server, string $email, array $permissions): ProviderResult;

    public function deleteSubuser(ResourceRef $server, string $subuserId): ProviderResult;

    /** @return list<array{name:string,type:'dir'|'file',size:int,modified:?string,mode:string}> */
    public function listFiles(ResourceRef $server, string $directory = '/'): array;

    public function readFile(ResourceRef $server, string $path): string;

    public function writeFile(ResourceRef $server, string $path, string $content): ProviderResult;

    /** A binary file of any size the daemon accepts, through the panel's signed upload URL (audit §5r-3). @param resource|string $contents */
    public function uploadFile(ResourceRef $server, string $directory, string $filename, mixed $contents): ProviderResult;

    /** @param list<string> $files names relative to `$root` */
    public function deleteFiles(ResourceRef $server, string $root, array $files): ProviderResult;

    public function createDirectory(ResourceRef $server, string $root, string $name): ProviderResult;

    public function renameFile(ResourceRef $server, string $root, string $from, string $to): ProviderResult;

    /** @return list<array{remote_id:string,ip:string,alias:?string,port:int,notes:?string,primary:bool}> */
    public function listAllocations(ResourceRef $server): array;

    /** The panel picks a free port on the node. */
    public function addAllocation(ResourceRef $server): ProviderResult;

    public function setPrimaryAllocation(ResourceRef $server, string $allocationId): ProviderResult;

    public function removeAllocation(ResourceRef $server, string $allocationId): ProviderResult;

    public function deleteBackup(ResourceRef $server, string $backupId): ProviderResult;

    public function lockBackup(ResourceRef $server, string $backupId, bool $locked): ProviderResult;

    /** Short-lived signed download URL of a backup archive. */
    public function backupDownloadUrl(ResourceRef $server, string $backupId): string;

    /** The customer's account on the panel (never a root admin). @return array{url:string, username:?string, email:?string, remote_id:string} */
    public function panelAccount(ResourceRef $server): array;

    public function setPanelPassword(ResourceRef $server, string $password): ProviderResult;

    // ── control plane (staff) ───────────────────────────────────────────────

    /** @return list<array{id:int,identifier:string,uuid:string,external_id:?string,name:string,node:int,user:int,egg:int,suspended:bool,installed:bool,status:?string,memory:int,disk:int,cpu:int,allocation:int}> */
    public function listServers(): array;

    /** @return list<array{nest_id:int,nest:string,id:int,name:string,docker_image:string,docker_images:array<string,string>,startup:string,privileged:bool}> */
    public function listEggs(): array;

    /** @return list<array{id:int,ip:string,alias:?string,port:int,assigned:bool}> every allocation of the node */
    public function nodeAllocations(int $nodeId): array;

    /** @param list<string> $ports ports or ranges (`25565`, `25570-25580`) */
    public function createAllocations(int $nodeId, string $ip, array $ports, ?string $alias = null): ProviderResult;

    /** Startup command, image and environment of a server through the application API (the client API cannot change the command). @param array<string,string> $environment merged over the current one */
    public function setStartup(ResourceRef $server, ?string $startup, ?string $image, array $environment = []): ProviderResult;

    /** The panel's own node record (limits, over-allocation, daemon address). @return array<string,mixed> */
    public function nodeDetail(int $nodeId): array;

    /** Change node limits on the panel (memory, disk in MB, memory_overallocate, disk_overallocate in %, maintenance_mode) — the operator never opens the panel (audit §5q). @param array<string,int|bool> $fields */
    public function updateNode(int $nodeId, array $fields): ProviderResult;

    /** What the node's daemon reports about the host (`memory_mb`, `cpu_threads`, `os`, `version`); null when the daemon does not say. @return array{memory_mb:?int, cpu_threads:?int, os:?string, version:?string}|null */
    public function nodeSystem(int $nodeId): ?array;

    /** Is the client API key present and accepted? @return 'ok'|'missing'|'rejected' */
    public function clientApiStatus(): string;

    // ── migrations (audit §5g-2) ─────────────────────────────────────────────

    /**
     * Everything needed to create the same server elsewhere: template, image, startup, variables, limits, owner.
     *
     * @return array{name:string,nest:int,egg:int,user:int,node:int,identifier:string,uuid:string,external_id:?string,docker_image:string,startup:string,environment:array<string,mixed>,limits:array<string,mixed>,feature_limits:array<string,mixed>,allocation:int}
     */
    public function serverDefinition(ResourceRef $server): array;

    /**
     * Streams an archive from a signed download link into the server's root and unpacks it there — the data half
     * of a migration. Long-running: the caller runs it from a queued job, never inside a request or a workflow tick.
     *
     * @return array{bytes:int,file:string}
     */
    public function importArchive(ResourceRef $server, string $sourceUrl, string $fileName = 'onhost-import.tar.gz'): array;

    /** Streams one backup of the server to a file on the control plane (the archive kept after a cancellation). @return int bytes written */
    public function downloadBackup(ResourceRef $server, string $backupId, string $targetPath, int $timeoutSeconds = 900): int;
}
