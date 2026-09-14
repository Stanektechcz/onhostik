<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/** Versioned legal documents (VOP, SLA, DPA, privacy, registry/registrar terms). */
final class ConsentDocument extends Model
{
    protected $table = 'consent_documents';

    public $incrementing = false;

    protected $guarded = [];

    protected $primaryKey = 'key';

    protected function casts(): array
    {
        return ['title' => 'array', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'required_for_checkout' => 'boolean'];
    }

    public static function current(string $key): ?self
    {
        return self::query()->where('key', $key)->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->orderByDesc('effective_from')->first();
    }
}
