<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServiceLabel extends Model
{
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];

    protected $fillable = ['name', 'color'];

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_note_labels');
    }
}
