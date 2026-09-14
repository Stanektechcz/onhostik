<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;

/** Registry objects owned by ONhost or a customer: NSSET / KEYSET (.cz). */
final class RegistrarObject extends Model
{
    protected static string $idPrefix = 'rob';

    protected $table = 'registrar_objects';

    protected function casts(): array
    {
        return ['nameservers' => 'array', 'keys' => 'array', 'shared' => 'boolean'];
    }

    public static function sharedNsset(string $provider): ?self
    {
        return self::query()->where('kind', 'nsset')->where('registrar_provider', $provider)->where('shared', true)->where('state', 'synced')->first();
    }
}
