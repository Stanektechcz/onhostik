<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Traits\HasUuid;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpsResponse extends Model
{
    use HasUuid;

    protected $fillable = [
        'customer_id',
        'ticket_id',
        'score',
        'comment',
        'survey_token',
        'notified_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score'        => 'integer',
            'notified_at'  => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function isPromoter(): bool
    {
        return $this->score !== null && $this->score >= 9;
    }

    public function isPassive(): bool
    {
        return $this->score !== null && $this->score >= 7 && $this->score <= 8;
    }

    public function isDetractor(): bool
    {
        return $this->score !== null && $this->score <= 6;
    }

    public function categoryLabel(): string
    {
        if ($this->score === null) return 'Bez odpovědi';
        if ($this->isPromoter())  return 'Promotér';
        if ($this->isPassive())   return 'Pasivní';
        return 'Kritik';
    }

    public function categoryColor(): string
    {
        if ($this->score === null) return 'secondary';
        if ($this->isPromoter())  return 'success';
        if ($this->isPassive())   return 'warning';
        return 'danger';
    }
}
