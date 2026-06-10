<?php

declare(strict_types=1);

namespace App\Domains\Shared\Casts;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a BIGINT minor-units column + sibling currency column to Brick\Money.
 *
 * Storage convention: amounts are ALWAYS stored as integer minor units
 * (haléře / cents) — never floats. The currency lives in a sibling column
 * whose name is passed as the first cast argument (default: "currency").
 *
 * Usage in model:
 *   protected function casts(): array
 *   {
 *       return ['total' => MoneyCast::class . ':currency'];
 *   }
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(
        private readonly string $currencyColumn = 'currency',
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $attributes[$this->currencyColumn]
            ?? throw new InvalidArgumentException(
                "MoneyCast: currency column [{$this->currencyColumn}] missing on " . $model::class
            );

        return Money::ofMinor((int) $value, $currency);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (!$value instanceof Money) {
            throw new InvalidArgumentException(
                "MoneyCast: attribute [{$key}] on " . $model::class . ' must be a Brick\Money\Money instance.'
            );
        }

        return [
            $key                   => $value->getMinorAmount()->toInt(),
            $this->currencyColumn  => $value->getCurrency()->getCurrencyCode(),
        ];
    }
}
