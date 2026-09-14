<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

final class DomainPrice extends Model
{
    protected static string $idPrefix = 'dprc';

    protected $table = 'domain_prices';

    protected function casts(): array
    {
        return [
            'register_minor' => 'integer', 'renew_minor' => 'integer', 'transfer_minor' => 'integer', 'restore_minor' => 'integer',
            'cost_minor' => 'integer', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'version' => 'integer',
        ];
    }

    public function register(): Money
    {
        return Money::minor($this->register_minor, $this->currency);
    }

    public function renew(): Money
    {
        return Money::minor($this->renew_minor, $this->currency);
    }

    public function transfer(): Money
    {
        return Money::minor($this->transfer_minor, $this->currency);
    }
}
