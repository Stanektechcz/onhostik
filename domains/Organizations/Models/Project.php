<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Onhost\Platform\Eloquent\Model;

final class Project extends Model
{
    use SoftDeletes;

    protected static string $idPrefix = 'prj';

    protected $table = 'projects';

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
