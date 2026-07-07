<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceCustomFieldValue extends Model
{
    protected $fillable = [
        'invoice_id',
        'invoice_field_definition_id',
        'value',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<InvoiceFieldDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(InvoiceFieldDefinition::class, 'invoice_field_definition_id');
    }
}
