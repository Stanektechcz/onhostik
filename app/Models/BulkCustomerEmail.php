<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array{segment?: string, country_code?: string, tag_id?: int, has_overdue?: bool} $filters
 * @property string $status  draft|sending|sent|failed
 * @property Carbon|null $sent_at
 */
class BulkCustomerEmail extends Model
{
    protected $fillable = [
        'created_by',
        'subject',
        'body_html',
        'body_text',
        'filters',
        'status',
        'recipients_count',
        'sent_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'filters'  => 'array',
            'sent_at'  => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    /** @return Builder<Customer> */
    public function buildRecipientQuery(): Builder
    {
        return self::buildFilterQuery($this->filters ?? []);
    }

    /**
     * @param array{segment?: string, country_code?: string, tag_id?: int|string, has_overdue?: bool|string} $filters
     * @return Builder<Customer>
     */
    public static function buildFilterQuery(array $filters): Builder
    {
        $query = Customer::query()->whereNotNull('email');

        if (! empty($filters['segment'])) {
            $query->where('segment', $filters['segment']);
        }

        if (! empty($filters['country_code'])) {
            $query->where('country_code', $filters['country_code']);
        }

        if (! empty($filters['tag_id'])) {
            $tagId = (int) $filters['tag_id'];
            $query->whereHas('tags', fn (Builder $q) => $q->where('customer_tags.id', $tagId));
        }

        if (! empty($filters['has_overdue'])) {
            $query->whereHas('invoices', function (Builder $q): void {
                $q->where('status', 'overdue');
            });
        }

        return $query;
    }
}
