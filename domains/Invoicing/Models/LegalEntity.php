<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;

final class LegalEntity extends Model
{
    protected $table = 'legal_entities';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['address' => 'array', 'series' => 'array', 'meta' => 'array', 'vat_payer' => 'boolean'];
    }

    public function seriesFor(string $type): string
    {
        return (string) ($this->series[$type] ?? strtoupper(substr($type, 0, 2)));
    }
}
