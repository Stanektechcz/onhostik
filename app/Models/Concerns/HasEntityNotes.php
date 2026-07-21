<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\EntityNote;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model unified internal notes (audit 75).
 *
 * Any entity that wants staff-facing notes adds this trait and gets the same
 * table, component and controller as every other — no fourth bespoke copy.
 */
trait HasEntityNotes
{
    /** @return MorphMany<EntityNote, $this> */
    public function entityNotes(): MorphMany
    {
        // Pinned first, then newest — the order the panel renders them in.
        return $this->morphMany(EntityNote::class, 'notable')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');
    }
}
