<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Versioned legal documents (VOP, SLA, DPA, privacy, registry/registrar terms, complaints procedure, acceptable use).
 *
 * TASK-0142: a version is `active` (published; in force from `effective_from`) or a `draft` the owner has not published yet
 * (I-R4/4A). A draft is never current — `current()`, `effective()` and `currentVersions()` skip it, and it carries a far-future
 * `effective_from` as a second line of defence for any query that only compares dates. The text of a version lives in
 * `resources/legal/<version>/<key>.md`, the texts older than versioned folders in `resources/legal/<key>.md`
 * (Onhost\Domain\Orders\LegalDocuments::path).
 */
final class ConsentDocument extends Model
{
    public const ACTIVE = 'active';

    public const DRAFT = 'draft';

    /** `effective_from` of a draft: never reached, so a date-only query cannot mistake a draft for a version in force. */
    public const DRAFT_EFFECTIVE_FROM = '2099-12-31 00:00:00';

    protected $table = 'consent_documents';

    public $incrementing = false;

    protected $guarded = [];

    protected $primaryKey = 'key';

    protected function casts(): array
    {
        return ['title' => 'array', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'required_for_checkout' => 'boolean', 'published_at' => 'datetime'];
    }

    /** Published versions in force now (any key). @param Builder<self> $query @return Builder<self> */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('state', self::ACTIVE)->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()));
    }

    public static function current(string $key): ?self
    {
        return self::query()->effective()->where('key', $key)->orderByDesc('effective_from')->first();
    }

    /**
     * The version in force of every document, newest `effective_from` winning when an older version was not closed.
     *
     * @return array<string,string> document key => version
     */
    public static function currentVersions(): array
    {
        $out = [];
        foreach (self::query()->effective()->orderBy('effective_from')->get() as $document) {
            $out[(string) $document->key] = (string) $document->version;
        }

        return $out;
    }

    /** A published version of a document that a customer may have accepted (in force now or earlier), never a draft. */
    public static function published(string $key, string $version): ?self
    {
        return self::query()->where('key', $key)->where('version', $version)->where('state', self::ACTIVE)->where('effective_from', '<=', now())->first();
    }

    public function isDraft(): bool
    {
        return $this->state === self::DRAFT;
    }
}
