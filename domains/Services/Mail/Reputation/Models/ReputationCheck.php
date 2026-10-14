<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail\Reputation\Models;

use Onhost\Platform\Eloquent\Model;

/** The last answer of one blocklist about one target (SP1a). */
final class ReputationCheck extends Model
{
    protected static string $idPrefix = 'mrc';

    protected $table = 'mail_reputation_checks';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }
}
