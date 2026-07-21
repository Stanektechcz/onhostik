<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerSegmentTag extends Model
{
    protected $fillable = [
        'name',
        'color',
        'description',
    ];

    protected function casts(): array
    {
        return [];
    }

    /**
     * The pivot table existed since the tags were introduced, but neither
     * side declared the relation — so tags could only ever be attached with
     * raw queries.
     *
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'customer_segment_tag_pivot',
            'segment_tag_id',
            'customer_id',
        )->withTimestamps();
    }
}
