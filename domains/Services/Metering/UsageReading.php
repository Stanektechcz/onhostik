<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

use LogicException;

/**
 * One reading of one metric. A number that was not measured is null — the constructor refuses a measured reading
 * without a number and an unavailable reading with one, so "we could not read it" can never turn into a 0 on the way.
 * A limit of 0 or less means "no limit known" and is kept as null.
 */
final readonly class UsageReading
{
    public const MEASURED = 'measured';

    public const ESTIMATED = 'estimated';

    public const UNAVAILABLE = 'unavailable';

    public ?int $limit;

    public function __construct(
        public string $metric,
        public ?int $value,
        ?int $limit,
        public string $unit,
        public string $limitKind,
        public string $quality,
        public ?string $reason = null,
        public ?string $source = null,
        public string $scope = 'service',
    ) {
        if (! in_array($quality, [self::MEASURED, self::ESTIMATED, self::UNAVAILABLE], true)) {
            throw new LogicException("Unknown reading quality {$quality}.");
        }
        if ($quality === self::UNAVAILABLE && $value !== null) {
            throw new LogicException("An unavailable reading of {$metric} carries no number.");
        }
        if ($quality !== self::UNAVAILABLE && $value === null) {
            throw new LogicException("A {$quality} reading of {$metric} needs a number; use unavailable() instead of 0.");
        }
        if ($value !== null && $value < 0) {
            throw new LogicException("A reading of {$metric} cannot be negative.");
        }
        $this->limit = $limit !== null && $limit > 0 ? $limit : null;
    }

    public static function measured(string $metric, int $value, ?int $limit, ?string $source = null): self
    {
        return new self($metric, $value, $limit, UsageMetrics::unit($metric), UsageMetrics::limitKind($metric), self::MEASURED, null, $source);
    }

    /** Part of the answer was missing (e.g. some mailboxes did not report): the known part, marked as such. */
    public static function estimated(string $metric, int $value, ?int $limit, string $reason, ?string $source = null): self
    {
        return new self($metric, $value, $limit, UsageMetrics::unit($metric), UsageMetrics::limitKind($metric), self::ESTIMATED, self::code($reason), $source);
    }

    public static function unavailable(string $metric, ?int $limit, string $reason, ?string $source = null): self
    {
        return new self($metric, null, $limit, UsageMetrics::unit($metric), UsageMetrics::limitKind($metric), self::UNAVAILABLE, self::code($reason), $source);
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    /** Share of the limit used, or null when either number is unknown. */
    public function pct(): ?int
    {
        if ($this->value === null || $this->limit === null) {
            return null;
        }

        return (int) min(999, round($this->value / $this->limit * 100));
    }

    /** A reason is an error code, never a sentence from a vendor. */
    private static function code(string $reason): string
    {
        $code = (string) preg_replace('/[^a-z0-9_.:-]+/', '_', strtolower($reason));

        return substr($code !== '' ? $code : 'error', 0, 60);
    }
}
