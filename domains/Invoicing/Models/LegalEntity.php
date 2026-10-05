<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Onhost\Domain\Tax\VatPayerMode;

final class LegalEntity extends Model
{
    protected $table = 'legal_entities';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /** The VAT mode is read once per request (VatPayerMode): any change of the legal entity drops what was read. */
    protected static function booted(): void
    {
        self::saved(fn () => VatPayerMode::forget());
        self::deleted(fn () => VatPayerMode::forget());
    }

    protected function casts(): array
    {
        return ['address' => 'array', 'series' => 'array', 'meta' => 'array', 'vat_payer' => 'boolean'];
    }

    public function seriesFor(string $type): string
    {
        return (string) ($this->series[$type] ?? strtoupper(substr($type, 0, 2)));
    }
}
