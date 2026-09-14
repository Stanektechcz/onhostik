<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization\Models;

use Illuminate\Database\Eloquent\Model;

final class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean', 'assignable' => 'boolean'];
    }

    public function permissions()
    {
        return $this->belongsToMany(PermissionDefinition::class, 'role_permissions', 'role_key', 'permission_key', 'key', 'key');
    }
}
