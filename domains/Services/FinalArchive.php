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
 * The archive a service is never deleted without (audit §5aa). Before a termination reaches the panel this pulls
 * everything the provider can hand over — the site files and every database dump, the game server's backup archive,
 * the mail domain with its mailboxes and aliases, always the service metadata (plan, entitlements, desired spec,
 * bindings, access) — onto the platform backup disk, checksums every part and keeps the set for
 * `onhost.platform_backup.service_archive_days` (60 by default) as a protected, immutable backup row.
 *
 * A component the provider offers but that fails is a hard error: the workflow stops and nothing is deleted.
 */
final class FinalArchive
{
    public const PREFIX = 'service-archives';

    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('onhost.platform_backup.disk', 'local'));
    }

    public function retentionDays(): int
    {
        return max(1, (int) config('onhost.platform_backup.service_archive_days', 60));
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
     * @return array{backup:Backup, set:string, parts:array<string,array{bytes:int,sha256:string}>, gaps:list<string>}
     */
    public function create(Service $service, ?object $adapter, ?ResourceRef $ref, CommandContext $context, ?string $operationId = null): array
    {
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
            'meta' => ['set' => $set, 'family' => $service->family, 'reason' => 'termination'],
        ]);
        $parts = [];
        $gaps = [];
        $snapshot = [];
        try {
            $this->write($work, 'service.json', (string) json_encode($this->metadata($service, $adapter, $ref), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            match ($service->family) {
                'web', 'managed' => $this->web($service, $adapter, $ref, $work, $parts, $gaps),
                'game' => $this->game($adapter, $ref, $work, $parts, $gaps),
                'mail' => $this->mail($adapter, $ref, $work, $gaps),
                'cloud', 'data' => $snapshot = $this->snapshot($adapter, $ref, $gaps),
                default => $gaps[] = "family {$service->family}: only the service metadata is archived",
            };
            foreach (glob($work.'/*') ?: [] as $file) {
                $parts[basename($file)] = $this->store($set, basename($file), $file);
            }
            $manifest = ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'family' => $service->family, 'created_at' => now()->toIso8601String(),
                'retention_until' => $retention->toIso8601String(), 'parts' => $parts, 'gaps' => $gaps];
            $this->disk()->put($set.'/manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            $backup->forceFill(['state' => 'failed', 'finished_at' => now(), 'meta' => array_merge((array) $backup->meta, ['error' => mb_substr($e->getMessage(), 0, 400)])])->save();
            $this->cleanup($work);
            $this->audit->record($context->withScope($service->organization_id), 'service.final_archive', 'failed', ['service' => $service->id, 'set' => $set, 'error' => mb_substr($e->getMessage(), 0, 200)], 'service', $service->id);

            throw $e;
        }
        $this->cleanup($work);
        $bytes = array_sum(array_map(fn (array $p) => $p['bytes'], $parts));
        $backup->forceFill(['state' => 'completed', 'finished_at' => now(), 'size_bytes' => (int) ($snapshot['size_bytes'] ?? 0) ?: $bytes, 'verified_at' => now(), 'verify_status' => 'ok',
            'remote_id' => $snapshot['remote_id'] ?? null, 'remote_datastore' => (string) ($snapshot['datastore'] ?? config('onhost.platform_backup.disk', 'local')),
            'meta' => array_merge((array) $backup->meta, ['parts' => array_keys($parts), 'gaps' => $gaps, 'snapshot' => $snapshot ?: null])])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.final_archive', 'succeeded', ['service' => $service->id, 'set' => $set, 'bytes' => $bytes, 'parts' => array_keys($parts), 'gaps' => $gaps], 'backup', $backup->id);
        $this->outbox->publish(GenericEvent::of('service.final_archive.created', 'service', $service->id, ['backup_id' => $backup->id, 'set' => $set, 'bytes' => $bytes, 'retention_until' => $retention->toIso8601String(), 'gaps' => $gaps], $service->organization_id));

        return ['backup' => $backup->refresh(), 'set' => $set, 'parts' => $parts, 'gaps' => $gaps];
    }

    /** Deletes archive sets whose retention has passed (called by onhost:backups:run). @return int removed sets */
    public function prune(): int
    {
        $removed = 0;
        foreach (Backup::query()->where('kind', 'final')->where('state', 'completed')->whereNotNull('retention_until')->where('retention_until', '<', now())->get() as $backup) {
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
    private function metadata(Service $service, ?object $adapter, ?ResourceRef $ref): array
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
            'actual_state' => $actual, 'archived_at' => now()->toIso8601String(),
        ];
    }

    /** Site files and every database of a web or managed service. */
    private function web(Service $service, ?object $adapter, ?ResourceRef $ref, string $work, array &$parts, array &$gaps): void
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
        $remote = 'onhost-final-'.Str::random(6).'.tar.gz';
        $transport = $adapter->transport($ref);
        $transport->archive(['.'], $remote);
        $transport->download($remote, $work.'/site-files.tar.gz');
        try {
            $transport->delete($remote);
        } catch (Throwable) {
            $gaps[] = 'web: the temporary archive stayed on the server';
        }
    }

    /** The game server's own backup archive, downloaded through the panel's signed URL. */
    private function game(?object $adapter, ?ResourceRef $ref, string $work, array &$parts, array &$gaps): void
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
