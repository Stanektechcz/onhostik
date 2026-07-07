<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceFieldDefinition extends Model
{
    public const TYPES = ['text' => 'Text', 'number' => 'Číslo', 'date' => 'Datum'];

    protected $fillable = [
        'key',
        'label',
        'type',
        'is_required',
        'show_on_invoice',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_required'     => 'boolean',
        'show_on_invoice' => 'boolean',
        'is_active'       => 'boolean',
    ];

    /** @return HasMany<InvoiceCustomFieldValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(InvoiceCustomFieldValue::class);
    }
}
