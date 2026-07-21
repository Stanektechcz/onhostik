<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A recorded act of consent (audit H123).
 *
 * Append-only: a withdrawal is a NEW row with granted = false, never an edit
 * of the original. Rewriting history would defeat the whole point — the
 * record exists to prove what was agreed at a point in time.
 *
 * @property string $type
 * @property string $document_version
 * @property bool   $granted
 */
class ConsentRecord extends Model
{
    public const TYPE_TERMS     = 'terms';
    public const TYPE_PRIVACY   = 'privacy';
    public const TYPE_MARKETING = 'marketing';
    public const TYPE_COOKIES   = 'cookies';

    protected $fillable = [
        'user_id',
        'customer_id',
        'type',
        'document_version',
        'granted',
        'ip_address',
        'user_agent',
        'source',
    ];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Consent records are append-only: withdraw by recording a new entry.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Consent records are append-only: they may not be deleted.');
        });
    }

    /**
     * Record consent, capturing the evidence that makes it demonstrable.
     */
    public static function record(
        Request $request,
        string $type,
        bool $granted = true,
        ?string $source = null,
    ): self {
        $user = $request->user();

        return self::create([
            'user_id'          => $user?->id,
            'customer_id'      => $user?->customer?->id,
            'type'             => $type,
            'document_version' => self::currentVersion($type),
            'granted'          => $granted,
            'ip_address'       => $request->ip(),
            'user_agent'       => mb_substr((string) $request->userAgent(), 0, 255),
            'source'           => $source,
        ]);
    }

    /** Version currently in force for a document type. */
    public static function currentVersion(string $type): string
    {
        /** @var array<string, string> $versions */
        $versions = (array) config('legal.document_versions', []);

        return (string) ($versions[$type] ?? 'unversioned');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
