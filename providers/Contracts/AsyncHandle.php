<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Handle of a long-running vendor operation: PVE UPID, ISPConfig job queue count,
 * Pterodactyl install flag, WAPI async request id. `awaitable` handles are polled by
 * the operation runner until `AsyncStatus` is terminal.
 */
final class AsyncHandle
{
    /** @param array<string,mixed> $meta */
    public function __construct(
        public readonly string $kind,      // pve_task | ispconfig_jobqueue | ptero_install | wapi_async | k8s_rollout | pbs_task
        public readonly string $handle,
        public readonly ?string $node = null,
        public readonly array $meta = [],
        public readonly int $pollIntervalSeconds = 5,
        public readonly int $timeoutSeconds = 6 * 3600,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'handle' => $this->handle, 'node' => $this->node, 'meta' => $this->meta, 'poll' => $this->pollIntervalSeconds, 'timeout' => $this->timeoutSeconds];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self((string) $data['kind'], (string) $data['handle'], $data['node'] ?? null, $data['meta'] ?? [], (int) ($data['poll'] ?? 5), (int) ($data['timeout'] ?? 21600));
    }
}
