<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\MailProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Throwable;

/**
 * The archive a service is never deleted without (audit §5aa). Before a cancellation touches the panel this pulls
 * everything the provider can hand over — the site files and every database dump, the game server's backup archive,
 * the mail domain with its mailboxes and aliases, always the service metadata (plan, entitlements, desired spec,
 * bindings, the identity report) — onto the platform backup disk, checksums every part and keeps the set for
 * `DeletionPolicy::retentionDays()` (60 by default) as a protected, immutable backup row.
 *
 * Rules that hold without exception:
 *  • the identity of the service is verified first (ServiceIdentityCheck) — a mismatch stops everything,
 *  • the service metadata reaches the disk before any provider call that could disturb the service,
 *  • a component the provider offers but that fails is a hard error: the workflow stops and nothing is deleted,
 *  • the files of a web service have two independent paths (file transport, the panel's own backup); only when both
 *    fail does the archive fail.
 */
final class FinalArchive
{
    public const PREFIX = 'service-archives';

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly DeletionPolicy $policy,
        private readonly ServiceIdentityCheck $identity,
    ) {}

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('onhost.platform_backup.disk', 'local'));
    }

    public function retentionDays(): int
    {
        return $this->policy->retentionDays();
    }

    /** An existing, completed archive of this service (a retry of the same termination must not archive twice). */
    public function existing(Service $service, ?string $operationId = null): ?Backup
    {
        return Backup::query()->where('service_id', $service->id)->where('kind', 'final')->where('state', 'completed')
            ->when($operationId !== null, fn ($q) => $q->where('operation_id', $operationId))
            ->orderByDesc('finished_at')->first();
    }

    /**
     * Writes the archive set and returns its backup row; throws when a part the provider offers cannot be stored.
     *
     * @param  array<string,mixed>|null  $identity  the identity report of the caller; verified here when absent
     * @return array{backup:Backup, set:string, parts:array<string,array{bytes:int,sha256:string}>, gaps:list<string>, identity:array<string,mixed>}
     */
    public function create(Service $service, ?object $adapter, ?ResourceRef $ref, CommandContext $context, ?string $operationId = null, ?array $identity = null): array
    {
        $identity ??= $this->identity->assert($service, $adapter, $ref); // §5ab: never archive (and therefore never delete) a resource we cannot identify
        $set = self::PREFIX.'/'.$service->organization_id.'/'.$service->id.'-'.now()->format('Ymd-His');
        $work = storage_path('app/onhost-final-'.Str::random(10));
        if (! is_dir($work) && ! mkdir($work, 0700, true) && ! is_dir($work)) {
            throw new DomainError('archive_workdir', 'Cannot create the working directory for the final archive.', 500);
        }
        $retention = now()->addDays($this->retentionDays());
        $backup = Backup::query()->create([
            'service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id,
            'kind' => 'final', 'state' => 'running', 'started_at' => now(), 'protected' => true, 'operation_id' => $operationId,
            'retention_until' => $retention, 'immutable_until' => $retention,
            'meta' => ['set' => $set, 'family' => $service->family, 'reason' => 'termination', 'identity' => $identity],
        ]);
        $parts = [];
        $gaps = [];
        $snapshot = [];
        $attempts = [];
        try {
            $this->write($work, 'service.json', (string) json_encode($this->metadata($service, $adapter, $ref, $identity), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->flush($set, $work, $parts); // the metadata is on the disk before the first provider call
            if (($identity['missing'] ?? false) === true) { // the panel says the resource is already gone: there is nothing left to pull
                $gaps[] = 'the resource no longer exists at the provider; only the service metadata is archived';
            } else {
                match ($service->family) {
                    'web', 'managed' => $this->web($adapter, $ref, $work, $gaps, $attempts),
                    'game' => $this->game($adapter, $ref, $work, $gaps),
                    'mail' => $this->mail($adapter, $ref, $work, $gaps),
                    'cloud', 'data' => $snapshot = $this->snapshot($adapter, $ref, $gaps),
                    default => $gaps[] = "family {$service->family}: only the service metadata is archived",
                };
            }
            $this->flush($set, $work, $parts);
            $manifest = ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'family' => $service->family, 'created_at' => now()->toIso8601String(),
                'retention_until' => $retention->toIso8601String(), 'parts' => $parts, 'gaps' => $gaps, 'identity' => $identity, 'attempts' => $attempts];
            $this->disk()->put($set.'/manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            try {
                $this->flush($set, $work, $parts); // whatever did reach the working directory is kept for the operator
            } catch (Throwable) {
                // the disk is the problem itself; the error below is the one that matters
            }
            $backup->forceFill(['state' => 'failed', 'finished_at' => now(), 'size_bytes' => array_sum(array_map(fn (array $p) => $p['bytes'], $parts)),
                'meta' => array_merge((array) $backup->meta, ['error' => mb_substr($e->getMessage(), 0, 400), 'parts' => array_keys($parts), 'gaps' => $gaps, 'attempts' => $attempts])])->save();
            $this->cleanup($work);
            $this->audit->record($context->withScope($service->organization_id), 'service.final_archive', 'failed', ['service' => $service->id, 'set' => $set, 'error' => mb_substr($e->getMessage(), 0, 200), 'attempts' => $attempts], 'service', $service->id);

            throw $e;
        }
        $this->cleanup($work);
        $bytes = array_sum(array_map(fn (array $p) => $p['bytes'], $parts));
        $backup->forceFill(['state' => 'completed', 'finished_at' => now(), 'size_bytes' => (int) ($snapshot['size_bytes'] ?? 0) ?: $bytes, 'verified_at' => now(), 'verify_status' => 'ok',
            'remote_id' => $snapshot['remote_id'] ?? null, 'remote_datastore' => (string) ($snapshot['datastore'] ?? config('onhost.platform_backup.disk', 'local')),
            'meta' => array_merge((array) $backup->meta, ['parts' => array_keys($parts), 'gaps' => $gaps, 'snapshot' => $snapshot ?: null, 'attempts' => $attempts])])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.final_archive', 'succeeded', ['service' => $service->id, 'set' => $set, 'bytes' => $bytes, 'parts' => array_keys($parts), 'gaps' => $gaps], 'backup', $backup->id);
        $this->outbox->publish(GenericEvent::of('service.final_archive.created', 'service', $service->id, ['backup_id' => $backup->id, 'set' => $set, 'bytes' => $bytes, 'retention_until' => $retention->toIso8601String(), 'gaps' => $gaps], $service->organization_id));

        return ['backup' => $backup->refresh(), 'set' => $set, 'parts' => $parts, 'gaps' => $gaps, 'identity' => $identity];
    }

    /**
     * The retention clock starts when the service is really removed, not when it was deactivated (audit §5ab):
     * the purge calls this so the customer's 30 days of grace do not eat into the 60 days of data retention.
     */
    public function startRetention(Backup $backup): Backup
    {
        $until = now()->addDays($this->retentionDays());
        $backup->forceFill([
            'retention_until' => $until, 'immutable_until' => $until, 'protected' => true,
            'meta' => array_merge((array) $backup->meta, ['retention_started_at' => now()->toIso8601String(), 'retention_days' => $this->retentionDays()]),
        ])->save();

        return $backup->refresh();
    }

    /**
     * One compressed file with the whole set, built on demand (the paid download and the free restore both use it).
     *
     * @return array{path:string, filename:string, bytes:int, sha256:string}
     */
    public function package(Backup $backup): array
    {
        $set = (string) data_get($backup->meta, 'set', '');
        if ($set === '' || ! str_starts_with($set, self::PREFIX.'/')) {
            throw new DomainError('archive_missing', 'Tato záloha nemá archivní sadu ke stažení.', 404);
        }
        $filename = ($backup->service_id ?? 'service').'-archive-'.$backup->created_at?->format('Ymd').'.zip';
        $target = $set.'/download/'.$filename;
        if ($this->disk()->exists($target)) {
            return ['path' => $target, 'filename' => $filename, 'bytes' => (int) $this->disk()->size($target), 'sha256' => (string) data_get($backup->meta, 'download.sha256', '')];
        }
        $local = storage_path('app/onhost-archive-'.Str::random(8).'.zip');
        $zip = new \ZipArchive;
        if ($zip->open($local, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new DomainError('archive_package', 'Archiv se nepodařilo zabalit.', 500);
        }
        foreach ($this->disk()->files($set) as $file) {
            $stream = $this->disk()->readStream($file);
            if (! is_resource($stream)) {
                continue;
            }
            $temp = storage_path('app/onhost-part-'.Str::random(8));
            $out = fopen($temp, 'wb');
            if (is_resource($out)) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
                $zip->addFile($temp, basename($file));
            }
            fclose($stream);
        }
        $zip->close();
        foreach (glob(storage_path('app/onhost-part-*')) ?: [] as $temp) {
            @unlink($temp);
        }
        $stream = fopen($local, 'rb');
        if (! is_resource($stream)) {
            throw new DomainError('archive_package', 'Zabalený archiv nelze číst.', 500);
        }
        $this->disk()->writeStream($target, $stream);
        fclose($stream);
        $result = ['path' => $target, 'filename' => $filename, 'bytes' => (int) filesize($local), 'sha256' => (string) hash_file('sha256', $local)];
        @unlink($local);
        // whatever the download meta already carries (paid, fee, waiver) stays — packaging only adds where the file is
        $backup->forceFill(['meta' => array_merge((array) $backup->meta, ['download' => array_merge((array) data_get($backup->meta, 'download', []), $result, ['built_at' => now()->toIso8601String()])])])->save();

        return $result;
    }

    /**
     * A backup nobody ever read back is not a backup: every archive is re-hashed against its own manifest on a
     * schedule (onhost:backups:run), so bit rot or a half-written upload is found while the data still matters —
     * not on the day a customer asks for it. A mismatch marks the archive failed and is reported by onhost:doctor.
     *
     * @return array{checked:int, ok:int, failed:int, problems:list<string>}
     */
    public function verifyStored(int $limit = 3): array
    {
        $stats = ['checked' => 0, 'ok' => 0, 'failed' => 0, 'problems' => []];
        $due = Backup::query()->where('kind', 'final')->where('state', 'completed')
            ->where(fn ($q) => $q->whereNull('verified_at')->orWhere('verified_at', '<', now()->subDays(max(1, (int) config('onhost.platform_backup.archive_verify_days', 14)))))
            ->orderBy('verified_at')->limit(max(1, $limit))->get();
        foreach ($due as $backup) {
            $stats['checked']++;
            $problem = $this->verifyOne($backup);
            if ($problem === null) {
                $backup->forceFill(['verified_at' => now(), 'verify_status' => 'ok'])->save();
                $stats['ok']++;

                continue;
            }
            $stats['failed']++;
            $stats['problems'][] = $backup->id.': '.$problem;
            $backup->forceFill(['verified_at' => now(), 'verify_status' => 'failed', 'meta' => array_merge((array) $backup->meta, ['verify_problem' => $problem])])->save();
            $this->outbox->publish(GenericEvent::of('service.final_archive.corrupt', 'backup', $backup->id, [
                'service_id' => $backup->service_id, 'set' => data_get($backup->meta, 'set'), 'problem' => $problem,
            ], $backup->organization_id));
        }

        return $stats;
    }

    /** @return string|null the first problem found, or null when the set matches its manifest */
    private function verifyOne(Backup $backup): ?string
    {
        $set = (string) data_get($backup->meta, 'set', '');
        if ($set === '' || ! str_starts_with($set, self::PREFIX.'/')) {
            return 'the backup row carries no archive set';
        }
        $disk = $this->disk();
        if (! $disk->exists($set.'/manifest.json')) {
            return 'the manifest is missing from '.$set;
        }
        $manifest = json_decode((string) $disk->get($set.'/manifest.json'), true);
        $parts = is_array($manifest) ? (array) ($manifest['parts'] ?? []) : [];
        if ($parts === []) {
            return 'the manifest lists no parts';
        }
        foreach ($parts as $name => $part) {
            $path = $set.'/'.$name;
            if (! $disk->exists($path)) {
                return "part {$name} is missing";
            }
            $expected = (string) ($part['sha256'] ?? '');
            if ($expected === '') {
                continue; // an older set without checksums: existence and size are all that can be checked
            }
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return "part {$name} cannot be read";
            }
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            fclose($stream);
            if (! hash_equals($expected, hash_final($hash))) {
                return "part {$name} does not match its checksum";
            }
        }

        return null;
    }

    /** Deletes archive sets whose retention has passed (called by onhost:backups:run). @return int removed sets */
    public function prune(): int
    {
        $removed = 0;
        foreach (Backup::query()->where('kind', 'final')->where('state', 'completed')->whereNotNull('retention_until')->where('retention_until', '<', now())->get() as $backup) {
            if (LegalHold::coversBackup($backup)) {
                continue; // a legal hold suspends deletion (H18): the archive — often the only copy left — waits for the hold to be lifted
            }
            $set = (string) data_get($backup->meta, 'set', '');
            if ($set !== '' && str_starts_with($set, self::PREFIX.'/')) {
                $this->disk()->deleteDirectory($set);
            }
            $backup->forceFill(['state' => 'expired', 'protected' => false])->save();
            $removed++;
        }

        return $removed;
    }

    /** @return array<string,mixed> */
    private function metadata(Service $service, ?object $adapter, ?ResourceRef $ref, array $identity): array
    {
        $actual = null;
        if ($adapter instanceof InfrastructureProvider && $ref !== null) {
            try {
                $state = $adapter->getActualState($ref);
                $actual = ['exists' => $state->exists, 'status' => $state->status, 'attributes' => $state->attributes];
            } catch (Throwable $e) {
                $actual = ['error' => mb_substr($e->getMessage(), 0, 200)];
            }
        }

        return [
            'service' => $service->only(['id', 'organization_id', 'product_key', 'family', 'name', 'label', 'state', 'region_code', 'hostname', 'sla_class', 'created_at', 'activated_at']),
            'entitlements' => $service->entitlements, 'desired_spec' => $service->desired_spec, 'actual_spec' => $service->actual_spec, 'health' => $service->health,
            'bindings' => ProviderBinding::query()->where('service_id', $service->id)->get(['remote_type', 'remote_id', 'remote_node', 'meta'])->all(),
            'actual_state' => $actual, 'access' => $this->access($adapter, $ref), 'identity' => $identity, 'archived_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Who could reach the service through the panel (H346): FTP and shell accounts, database users, collaborators —
     * names and ids only, never a password or key. The cancellation removes the delegated ones right after this
     * archive is written, so this is the list the customer needs when a restore asks "who had access".
     *
     * @return array<string,mixed>
     */
    private function access(?object $adapter, ?ResourceRef $ref): array
    {
        if ($ref === null) {
            return [];
        }
        $keep = array_flip(['remote_id', 'user', 'email', 'username', 'path', 'permissions', 'databases', 'has_key', 'active']);
        $inventory = [];
        $read = function (string $key, callable $call) use (&$inventory, $keep): void {
            try {
                $inventory[$key] = array_values(array_map(fn ($row) => is_array($row) ? array_intersect_key($row, $keep) : $row, (array) $call()));
            } catch (Throwable $e) {
                $inventory[$key] = ['error' => mb_substr($e->getMessage(), 0, 160)];
            }
        };
        if ($adapter instanceof WebHostingProvider) {
            $read('ftp_accounts', fn () => $adapter->listFtpAccounts($ref));
            $read('shell_users', fn () => $adapter->listShellUsers($ref));
            $read('db_users', fn () => $adapter->listDbUsers($ref));
        }
        if ($adapter instanceof GameToolsProvider) {
            $read('subusers', fn () => $adapter->listSubusers($ref));
        }

        return $inventory;
    }

    /**
     * Site files and every database of a web or managed service. The files have two independent paths because a
     * shared node does not always offer both: the file transport (SFTP or the panel's file API) and the panel's own
     * site backup. Each attempt is recorded; only when both fail does the archive — and with it the deletion — stop.
     */
    private function web(?object $adapter, ?ResourceRef $ref, string $work, array &$gaps, array &$attempts): void
    {
        if (! $adapter instanceof WebToolsProvider || $ref === null) {
            $gaps[] = 'web: the panel offers no file or database export';

            return;
        }
        if ($adapter instanceof WebHostingProvider) {
            foreach ($adapter->listDatabases($ref) as $database) {
                $name = (string) ($database['name'] ?? $database['remote_id'] ?? 'db');
                $adapter->exportDatabase($ref, (string) $database['remote_id'], $work.'/database-'.Str::slug($name).'.sql');
            }
        }
        try {
            $this->filesByTransport($adapter, $ref, $work, $gaps);
            $attempts['transport'] = 'ok';

            return;
        } catch (Throwable $e) {
            $attempts['transport'] = mb_substr($e->getMessage(), 0, 300);
        }
        try {
            $attempts['panel_backup'] = $this->filesByPanelBackup($adapter, $ref, $work, $gaps);

            return;
        } catch (Throwable $e) {
            $attempts['panel_backup'] = mb_substr($e->getMessage(), 0, 300);
        }

        throw new DomainError('final_archive_files', 'Soubory webu se nepodařilo zazálohovat žádnou cestou (přenos: '.$attempts['transport'].'; záloha panelu: '.$attempts['panel_backup'].'); nic nebylo smazáno.', 503);
    }

    /** Path 1: pack the site root on the node and pull the archive through the provider's file transport. */
    private function filesByTransport(WebToolsProvider $adapter, ResourceRef $ref, string $work, array &$gaps): void
    {
        $remote = 'onhost-final-'.Str::random(6).'.tar.gz';
        $transport = $adapter->transport($ref);
        $transport->archive(['.'], $remote);
        $transport->download($remote, $work.'/site-files.tar.gz');
        if (! is_file($work.'/site-files.tar.gz') || filesize($work.'/site-files.tar.gz') < 32) {
            throw new DomainError('final_archive_files', 'the file transport produced an empty archive', 503);
        }
        try {
            $transport->delete($remote);
        } catch (Throwable) {
            $gaps[] = 'web: the temporary archive stayed on the server';
        }
    }

    /**
     * Path 2: ask the panel for its own site backup and download that. aaPanel packs the site itself (`ToBackup`),
     * ISPConfig keeps the nightly archives — when no fresh one appears in time, a recent existing backup is taken
     * and the age is recorded as a gap, because an archive from last night beats no archive at all.
     */
    private function filesByPanelBackup(WebToolsProvider $adapter, ResourceRef $ref, string $work, array &$gaps): string
    {
        if (! $adapter instanceof BackupCapable) {
            throw new DomainError('final_archive_files', 'the panel offers no site backup', 503);
        }
        $before = array_map(fn (array $b) => (string) $b['remote_id'], $adapter->listBackups($ref));
        $adapter->backup($ref, ['name' => 'onhost-final-'.now()->format('Ymd-His'), 'protected' => true, 'notes' => 'final archive before termination']);
        $deadline = time() + (int) config('onhost.platform_backup.game_archive_timeout', 1800);
        $fresh = null;
        while (true) { // aaPanel packs the site during the call, ISPConfig hands the job to its queue — so look first, wait afterwards
            $fresh = collect($adapter->listBackups($ref))->reject(fn (array $b) => in_array((string) $b['remote_id'], $before, true))
                ->sortByDesc('created_at')->first();
            if (is_array($fresh) && ($fresh['size_bytes'] ?? 1) > 0) {
                break;
            }
            $fresh = null;
            if (time() >= $deadline) {
                break;
            }
            sleep(10);
        }
        $note = 'fresh';
        if ($fresh === null) { // no new archive in time: the newest recent one, clearly marked
            $stale = (int) config('onhost.platform_backup.stale_backup_hours', 48);
            $fresh = collect($adapter->listBackups($ref))->sortByDesc('created_at')
                ->first(fn (array $b) => ($b['created_at'] ?? '') !== '' && strtotime((string) $b['created_at']) > time() - $stale * 3600);
            if (! is_array($fresh)) {
                throw new DomainError('final_archive_files', 'the panel produced no site backup in time and has none from the last '.$stale.' h', 503);
            }
            $gaps[] = 'web: the files come from the panel backup of '.$fresh['created_at'].', not from a fresh archive';
            $note = 'existing backup from '.$fresh['created_at'];
        }
        $name = (string) (data_get($fresh, 'meta.filename') ?? '');
        $target = $work.'/site-files'.(str_ends_with(strtolower($name), '.zip') ? '.zip' : '.tar.gz');
        $adapter->downloadBackup($ref, (string) $fresh['remote_id'], $target);
        if (! is_file($target) || filesize($target) < 32) {
            throw new DomainError('final_archive_files', 'the panel backup could not be downloaded', 503);
        }
        $gaps[] = 'web: the archive is the panel\'s own site backup (the node offers no file transport)';

        return $note;
    }

    /** The game server's own backup archive, downloaded through the panel's signed URL. */
    private function game(?object $adapter, ?ResourceRef $ref, string $work, array &$gaps): void
    {
        if (! $adapter instanceof BackupCapable || $ref === null) {
            $gaps[] = 'game: the panel offers no backups';

            return;
        }
        $adapter->backup($ref, ['name' => 'onhost-final-'.now()->format('Ymd-His'), 'protected' => true, 'notes' => 'final archive before termination']);
        $deadline = time() + (int) config('onhost.platform_backup.game_archive_timeout', 1800);
        $latest = null;
        while (time() < $deadline) {
            $latest = collect($adapter->listBackups($ref))->sortByDesc('created_at')->first();
            if (is_array($latest) && ($latest['verified'] ?? null) !== null && $latest['verified'] !== false) {
                break;
            }
            sleep(10);
        }
        if (! is_array($latest) || ($latest['verified'] ?? null) === false || empty($latest['remote_id'])) {
            throw new DomainError('final_archive_backup', 'The game panel did not finish the final backup; nothing was deleted.', 503);
        }
        if (! $adapter instanceof GameToolsProvider) {
            $gaps[] = 'game: the archive stays on the panel (no download URL)';

            return;
        }
        $url = $adapter->backupDownloadUrl($ref, (string) $latest['remote_id']);
        $target = $work.'/game-backup.tar.gz';
        $response = Http::timeout((int) config('onhost.platform_backup.download_timeout', 900))->withOptions(['sink' => $target])->get($url);
        if ($response->successful() && (! is_file($target) || filesize($target) === 0)) {
            file_put_contents($target, $response->body()); // a client that does not stream into the sink
        }
        if (! $response->successful() || ! is_file($target) || filesize($target) < 64) {
            throw new DomainError('final_archive_download', 'The game server backup could not be downloaded; nothing was deleted.', 503);
        }
    }

    /** Mail domain, mailboxes, aliases and the DKIM public key; message content has no export in the panel API. */
    private function mail(?object $adapter, ?ResourceRef $ref, string $work, array &$gaps): void
    {
        if (! $adapter instanceof MailProvider || $ref === null) {
            $gaps[] = 'mail: the panel offers no mail export';

            return;
        }
        $data = ['mailboxes' => $adapter->listMailboxes($ref), 'aliases' => $adapter->listAliases($ref), 'dkim' => $adapter->dkim($ref)];
        $this->write($work, 'mail-domain.json', (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $gaps[] = 'mail: mailbox contents are not exportable through the panel API (IMAP copy is a separate migration)';
    }

    /**
     * Compute and database services: the disk image is far too large to pull through the control plane, so the archive
     * is the provider's own protected snapshot — taken now, waited for, and recorded with its id, size and datastore.
     *
     * @return array{remote_id?:string, size_bytes?:int, datastore?:string}
     */
    private function snapshot(?object $adapter, ?ResourceRef $ref, array &$gaps): array
    {
        if (! $adapter instanceof BackupCapable || $ref === null) {
            $gaps[] = 'compute: the provider offers no snapshot';

            return [];
        }
        $result = $adapter->backup($ref, ['mode' => 'stop', 'protected' => true, 'notes' => 'final backup before termination']);
        if ($result->isAsync() && $adapter instanceof InfrastructureProvider) {
            $deadline = time() + (int) config('onhost.platform_backup.game_archive_timeout', 1800);
            while (time() < $deadline) {
                $status = $adapter->awaitStatus($result->async);
                if ($status->state === AsyncStatus::SUCCEEDED) {
                    break;
                }
                if ($status->state === AsyncStatus::FAILED) {
                    throw new DomainError('final_archive_snapshot', 'The final snapshot failed at the provider; nothing was deleted.', 503);
                }
                sleep(5);
            }
        }
        $latest = collect($adapter->listBackups($ref))->sortByDesc('created_at')->first();
        if (! is_array($latest) || empty($latest['remote_id'])) {
            throw new DomainError('final_archive_snapshot', 'The provider reports no final snapshot; nothing was deleted.', 503);
        }
        $gaps[] = 'compute: the disk image stays on the backup server as a protected snapshot';

        return ['remote_id' => (string) $latest['remote_id'], 'size_bytes' => (int) ($latest['size_bytes'] ?? 0), 'datastore' => (string) ($latest['datastore'] ?? data_get($latest, 'meta.datastore', ''))];
    }

    private function write(string $work, string $name, string $content): void
    {
        if (file_put_contents($work.'/'.$name, $content) === false) {
            throw new DomainError('archive_write', "Cannot write {$name} of the final archive.", 500);
        }
    }

    /** Moves everything produced so far onto the backup disk (checksummed) and empties the working directory. */
    private function flush(string $set, string $work, array &$parts): void
    {
        foreach (glob($work.'/*') ?: [] as $file) {
            $parts[basename($file)] = $this->store($set, basename($file), $file);
            @unlink($file);
        }
    }

    /** @return array{bytes:int, sha256:string} */
    private function store(string $set, string $name, string $local): array
    {
        $stream = fopen($local, 'rb');
        if (! is_resource($stream)) {
            throw new DomainError('archive_read', "Cannot read {$name} of the final archive.", 500);
        }
        $this->disk()->writeStream($set.'/'.$name, $stream);
        fclose($stream);
        if (! $this->disk()->exists($set.'/'.$name)) {
            throw new DomainError('archive_store', "{$name} did not reach the backup disk.", 500);
        }

        return ['bytes' => (int) filesize($local), 'sha256' => (string) hash_file('sha256', $local)];
    }

    private function cleanup(string $work): void
    {
        foreach (glob($work.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($work);
    }
}
