<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\ResourceRef;
use RuntimeException;
use Throwable;

/**
 * The data half of a game migration (audit §5g-2, §5h-2): streams the migration backup of the source server into
 * the target server — on the same panel or on another one — and unpacks it. Runs for as long as the archive needs;
 * the migration saga polls its record in the cache (`state` done|failed) instead of holding a workflow tick open.
 */
final class TransferGameArchive implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 6 * 3600;

    public function __construct(public readonly string $operationId) {}

    public static function cacheKey(string $operationId): string
    {
        return "onhost:gmig:{$operationId}";
    }

    public function handle(ProviderRegistry $providers, CacheRepository $cache): void
    {
        $operation = Operation::query()->find($this->operationId);
        if ($operation === null) {
            return;
        }
        try {
            $ctx = (array) $operation->context;
            $sourceInstance = ProviderInstance::query()->findOrFail((string) ($ctx['source_instance_id'] ?? $operation->provider_instance_id));
            $targetInstance = ! empty($ctx['target_instance_id']) ? ProviderInstance::query()->findOrFail((string) $ctx['target_instance_id']) : $sourceInstance;
            $source = $providers->forInstance($sourceInstance);
            $destination = $providers->forInstance($targetInstance);
            if (! $source instanceof GameToolsProvider || ! $destination instanceof GameToolsProvider) {
                throw new RuntimeException('The provider instance is not a game panel');
            }
            $sourceRef = new ResourceRef('server', (string) ($ctx['source_server_id'] ?? ''), (string) ($ctx['source_node_remote_id'] ?? ''), (array) ($ctx['source_meta'] ?? []), (string) $operation->service_id);
            $target = GameMigrationWorkflow::targetBinding((string) $operation->service_id);
            if ($target === null) {
                throw new RuntimeException('The target server binding is missing');
            }
            $url = $source->backupDownloadUrl($sourceRef, (string) ($ctx['backup_uuid'] ?? ''));
            if ($url === '') {
                throw new RuntimeException('The panel returned no download link for the migration backup');
            }
            $result = $destination->importArchive($target->ref(), $url, 'onhost-migration.tar.gz');
            $cache->put(self::cacheKey($operation->id), ['state' => 'done', 'bytes' => $result['bytes'], 'at' => now()->toIso8601String()], 86400);
        } catch (Throwable $e) {
            $cache->put(self::cacheKey($operation->id), ['state' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300), 'at' => now()->toIso8601String()], 86400);
        }
    }
}
