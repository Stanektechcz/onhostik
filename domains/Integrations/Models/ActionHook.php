<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One stored service action a customer can trigger by URL (the token in the URL is the credential).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $service_id
 * @property ?string $created_by
 * @property string $name
 * @property string $action
 * @property array<string,mixed> $params
 * @property bool $enabled
 * @property int $uses
 * @property ?Carbon $last_used_at
 * @property ?string $last_operation_id
 * @property ?string $last_result
 */
final class ActionHook extends Model
{
    protected static string $idPrefix = 'ahk';

    protected $table = 'action_hooks';

    protected function casts(): array
    {
        return ['params' => 'array', 'enabled' => 'boolean', 'uses' => 'integer', 'last_used_at' => 'datetime'];
    }
}
