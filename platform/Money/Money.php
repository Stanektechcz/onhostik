<?php

declare(strict_types=1);

namespace Onhost\Platform\Money;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable money value in integer minor units (haléře / cents). No floats,
 * deterministic half-up rounding, no implicit FX (blueprint §62.4).
 */
final class Money implements JsonSerializable
{
    private function __construct(
        public readonly int $minor,
        public readonly Currency $currency,
    ) {}

    public static function minor(int $minor, Currency|string $currency): self
    {
        return new self($minor, $currency instanceof Currency ? $currency : Currency::fromString($currency));
    }

    public static function zero(Currency|string $currency): self
    {
        return self::minor(0, $currency);
    }

    /** From a decimal string such as "1290.50" (never from floats in business code). */
    public static function decimal(string|int|float $amount, Currency|string $currency): self
    {
        $cur = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $normalized = str_replace([' ', ','], ['', '.'], (string) $amount);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("Invalid decimal amount: {$amount}");
        }
        $scaled = bcmul($normalized, (string) (10 ** $cur->minorUnits()), 6);

        return new self((int) self::roundHalfUp($scaled), $cur);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(int|string $factor): self
    {
        $result = bcmul((string) $this->minor, (string) $factor, 6);

        return new self((int) self::roundHalfUp($result), $this->currency);
    }

    /** Percentage with deterministic rounding, e.g. 21 => VAT 21 %. */
    public function percent(string|int|float $percent): self
    {
        $result = bcdiv(bcmul((string) $this->minor, (string) $percent, 6), '100', 6);

        return new self((int) self::roundHalfUp($result), $this->currency);
    }

    /** Proportional share (used for proration): amount * numerator / denominator. */
    public function share(int $numerator, int $denominator): self
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Denominator must be positive');
        }
        $result = bcdiv(bcmul((string) $this->minor, (string) $numerator, 6), (string) $denominator, 6);

        return new self((int) self::roundHalfUp($result), $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function greaterThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor >= $other->minor;
    }

    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor < $other->minor;
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function toDecimal(): string
    {
        $units = 10 ** $this->currency->minorUnits();
        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);

        return sprintf('%s%d.%0'.$this->currency->minorUnits().'d', $sign, intdiv($abs, $units), $abs % $units);
    }

    public function format(string $locale = 'cs'): string
    {
        $decimal = $this->toDecimal();
        [$wholePart, $frac] = explode('.', ltrim($decimal, '-'));
        $sign = $this->minor < 0 ? '-' : '';
        $grouped = number_format((int) $wholePart, 0, ',', ' ');
        $symbol = $this->currency === Currency::CZK ? 'Kč' : '€';
        $body = $frac === '00' ? $grouped : $grouped.','.$frac;

        return $locale === 'cs' ? "{$sign}{$body} {$symbol}" : "{$sign}{$body} {$this->currency->value}";
    }

    /** @return array{minor:int,currency:string,decimal:string} */
    public function jsonSerialize(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency->value, 'decimal' => $this->toDecimal()];
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException("Currency mismatch {$this->currency->value} vs {$other->currency->value}");
        }
    }

    private static function roundHalfUp(string $scaled): string
    {
        $negative = str_starts_with($scaled, '-');
        $abs = ltrim($scaled, '-');
        $rounded = bcadd($abs, '0.5', 0);

        return $negative ? '-'.$rounded : $rounded;
    }
}
