<?php

declare(strict_types=1);

namespace Onhost\Providers\Hetzner;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\NodeOrderProvider;
use Throwable;

/**
 * Hetzner Cloud as a burst-capacity vendor (audit §5n-7): `POST /v1/servers` with the type chosen from the wanted RAM
 * (the smallest type that fits, from the vendor's own `server_types` list), the image, location and SSH keys the instance
 * options name. The token comes from the instance's secret store entry (`token`); nothing of the vendor's answer beyond
 * the id, name and address is stored or logged.
 */
final class HetznerCloudNodeOrderProvider implements NodeOrderProvider
{
    public const BASE = 'https://api.hetzner.cloud/v1';

    public function __construct(private readonly string $token, private readonly int $timeoutSeconds = 15) {}

    public function order(array $spec): array
    {
        $options = (array) ($spec['options'] ?? []);
        $type = (string) ($options['server_type'] ?? '');
        if ($type === '') {
            $type = $this->pick((int) $spec['ram_mb'], (int) $spec['cpu_cores'], (int) $spec['disk_gb'], $options);
        }
        $body = [
            'name' => $spec['name'], 'server_type' => $type, 'image' => (string) ($options['image'] ?? 'debian-12'),
            'location' => (string) ($options['location'] ?? ($spec['region'] ?? 'fsn1')), 'ssh_keys' => array_values((array) ($options['ssh_keys'] ?? [])),
            'labels' => ['onhost' => 'capacity', 'role' => (string) ($options['role'] ?? '')], 'start_after_create' => true,
        ];
        if (! empty($options['user_data'])) {
            $body['user_data'] = (string) $options['user_data'];
        }
        try {
            $response = $this->client()->post(self::BASE.'/servers', $body);
        } catch (Throwable $e) {
            throw new DomainError('node_order_failed', 'The vendor did not answer the node order.', 502, ['vendor' => 'hetzner']);
        }
        if (! $response->successful()) {
            throw new DomainError('node_order_refused', 'The vendor refused the node order ('.$response->status().').', 502, ['vendor' => 'hetzner', 'code' => (string) data_get($response->json(), 'error.code', '')]);
        }
        $server = (array) data_get($response->json(), 'server', []);

        return ['remote_id' => (string) ($server['id'] ?? ''), 'name' => (string) ($server['name'] ?? $spec['name']), 'ip' => data_get($server, 'public_net.ipv4.ip'), 'type' => $type];
    }

    public function catalogue(array $options): array
    {
        try {
            $response = $this->client()->get(self::BASE.'/server_types', ['per_page' => 50]);
        } catch (Throwable) {
            return [];
        }
        if (! $response->successful()) {
            return [];
        }
        $rows = [];
        foreach ((array) data_get($response->json(), 'server_types', []) as $type) {
            if (! empty($type['deprecated'])) {
                continue;
            }
            $rows[] = ['type' => (string) ($type['name'] ?? ''), 'ram_mb' => (int) round(((float) ($type['memory'] ?? 0)) * 1024), 'cpu_cores' => (int) ($type['cores'] ?? 0), 'disk_gb' => (int) ($type['disk'] ?? 0)];
        }
        usort($rows, fn ($a, $b) => [$a['ram_mb'], $a['cpu_cores']] <=> [$b['ram_mb'], $b['cpu_cores']]);

        return $rows;
    }

    /** The smallest type that fits the wanted size (the largest one when nothing fits). */
    private function pick(int $ramMb, int $cores, int $diskGb, array $options): string
    {
        $catalogue = $this->catalogue($options);
        if ($catalogue === []) {
            throw new DomainError('node_order_no_types', 'Set options.node_order.server_type on the instance: the vendor catalogue is not readable.', 422, ['field' => 'server_type']);
        }
        foreach ($catalogue as $row) {
            if ($row['ram_mb'] >= $ramMb && $row['cpu_cores'] >= $cores && $row['disk_gb'] >= $diskGb) {
                return $row['type'];
            }
        }

        return $catalogue[array_key_last($catalogue)]['type'];
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)->connectTimeout(5)->acceptJson()->withToken($this->token);
    }
}
