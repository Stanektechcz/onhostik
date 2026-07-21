<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A recorded API write, keyed by the client's Idempotency-Key (audit J136).
 *
 * @property string $idempotency_key
 * @property string $request_hash
 * @property int|null $response_status
 * @property string|null $response_body
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'token_id',
        'idempotency_key',
        'method',
        'path',
        'request_hash',
        'response_status',
        'response_body',
        'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    /** A finished request whose response can be replayed. */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null && $this->response_status !== null;
    }
}
