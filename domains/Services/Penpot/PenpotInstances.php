<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * Names and the customer's view of one Penpot instance (TASK-0123).
 *
 * The service is the product family `penpot`, executor `penpot`: its stack is `penpot-<10 characters of the service id>` on the
 * node, its address `https://<8 characters>.<penpot.hostname_suffix>`, its owner the organization owner's e-mail (Penpot
 * registration is off; the owner invites the team inside Penpot).
 */
final class PenpotInstances
{
    public const FAMILY = 'penpot';

    public const PRODUCT = 'penpot';

    public static function stackName(Service|string $service): string
    {
        $id = $service instanceof Service ? $service->id : $service;

        return 'penpot-'.substr((string) preg_replace('/[^a-z0-9]/', '', strtolower($id)), -10);
    }

    /** The host name a new service gets (ServiceService::desiredSpec). */
    public static function hostnameFor(string $serviceId): string
    {
        return strtolower(substr($serviceId, -8)).'.'.ltrim((string) config('penpot.hostname_suffix', 'penpot.onhost.cz'), '.');
    }

    public static function ownerEmail(Organization $organization): ?string
    {
        $owner = $organization->owner_user_id === null ? null : User::query()->find($organization->owner_user_id);
        $email = Str::lower(trim((string) ($owner?->email ?: $organization->billing_email ?: '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }

    public static function isPenpot(Service $service): bool
    {
        return $service->family === self::FAMILY;
    }

    /**
     * What the panel card shows: the address (the "open Penpot" link), the login e-mail, whether the owner set a password, the
     * limits of the plan, the last backups. No secret: the vault keeps the stack's keys and nobody keeps the owner's password.
     *
     * @return array<string,mixed>
     */
    public static function summary(Service $service): array
    {
        $tags = (array) (($service->tags ?? [])['penpot'] ?? []);
        $ent = (array) $service->entitlements;
        $defaults = (array) config('penpot.defaults', []);
        $open = in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true);
        $backups = Backup::query()->where('service_id', $service->id)->orderByDesc('created_at')->limit(5)->get(['id', 'state', 'kind', 'size_bytes', 'created_at', 'finished_at']);

        return [
            'service_id' => $service->id, 'state' => $service->state,
            'url' => $tags['url'] ?? ($service->hostname ? 'https://'.$service->hostname : null),
            // opening Penpot is a plain link: the instance has its own login (Penpot accounts), the platform hands over no session
            'open_url' => $open ? ($tags['url'] ?? ($service->hostname ? 'https://'.$service->hostname : null)) : null,
            'owner_email' => $tags['owner_email'] ?? null,
            'owner_password_set' => (bool) ($tags['owner_password_set'] ?? false),
            'version' => $tags['version'] ?? null,
            'limits' => [
                'ram_mb' => (int) ($ent['ram_mb'] ?? $defaults['ram_mb'] ?? 4096), 'cpus' => (float) ($ent['cpus'] ?? $defaults['cpus'] ?? 2),
                'storage_gb' => (int) ($ent['storage_gb'] ?? $defaults['storage_gb'] ?? 20), 'editors' => isset($ent['editors']) ? (int) $ent['editors'] : null,
            ],
            'usage' => (array) (($service->tags ?? [])['usage'] ?? []),
            'health' => (array) ($service->health ?? []),
            'backups' => $backups->map(fn (Backup $b) => ['id' => $b->id, 'state' => $b->state, 'kind' => $b->kind, 'size_bytes' => $b->size_bytes, 'created_at' => $b->created_at?->toIso8601String()])->all(),
        ];
    }
}
