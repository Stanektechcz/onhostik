<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

/** Wholesale price of one TLD at one registrar (per operation), in the registrar's currency. */
final class RegistrarTldCost extends Model
{
    protected static string $idPrefix = 'rtc';

    protected $table = 'registrar_tld_costs';

    protected function casts(): array
    {
        return ['register_minor' => 'integer', 'renew_minor' => 'integer', 'transfer_minor' => 'integer', 'restore_minor' => 'integer', 'fetched_at' => 'datetime', 'meta' => 'array'];
    }

    public function amount(string $operation): ?Money
    {
        $minor = $this->{$operation.'_minor'} ?? null;

        return $minor === null ? null : Money::minor((int) $minor, $this->currency);
    }

    public function isStale(int $ttlHours): bool
    {
        return $this->source === 'api' && ($this->fetched_at === null || $this->fetched_at->lt(now()->subHours($ttlHours)));
    }
}
