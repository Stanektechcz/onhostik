<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Models\User;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property 'pending'|'processing'|'done'|'failed' $status
 * @property 'pohoda_xml'|'csv_invoices'|'csv_payments'|'pdf_summary' $format
 * @property array<string, mixed>|null $filters
 * @property \Illuminate\Support\Carbon|null $date_from
 * @property \Illuminate\Support\Carbon|null $date_to
 */
class FinancialExportJob extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'created_by',
        'format',
        'date_from',
        'date_to',
        'status',
        'file_path',
        'error_message',
        'filters',
        'row_count',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to'   => 'date',
            'filters'   => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'    => 'Ve frontě',
            'processing' => 'Zpracovává se',
            'done'       => 'Dokončeno',
            'failed'     => 'Chyba',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending'    => 'bg-secondary',
            'processing' => 'bg-warning',
            'done'       => 'bg-success',
            'failed'     => 'bg-danger',
        };
    }

    public function formatLabel(): string
    {
        return match ($this->format) {
            'pohoda_xml'   => 'POHODA XML',
            'csv_invoices' => 'CSV – Faktury',
            'csv_payments' => 'CSV – Platby',
            'pdf_summary'  => 'PDF – Přehled',
        };
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'done' && $this->file_path !== null;
    }
}
