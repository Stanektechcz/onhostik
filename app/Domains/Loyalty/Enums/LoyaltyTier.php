<?php

declare(strict_types=1);

namespace App\Domains\Loyalty\Enums;

/**
 * Loyalty tiers (audit 500 #514) — derived from lifetime earned points, not the
 * spendable balance, so redeeming rewards never demotes a customer.
 */
enum LoyaltyTier: string
{
    case Bronze   = 'bronze';
    case Silver   = 'silver';
    case Gold     = 'gold';
    case Platinum = 'platinum';

    public function label(): string
    {
        return match ($this) {
            self::Bronze   => 'Bronzový',
            self::Silver   => 'Stříbrný',
            self::Gold     => 'Zlatý',
            self::Platinum => 'Platinový',
        };
    }

    /** Lifetime earned points required to reach this tier. */
    public function threshold(): int
    {
        return match ($this) {
            self::Bronze   => 0,
            self::Silver   => 1_000,
            self::Gold     => 5_000,
            self::Platinum => 20_000,
        };
    }

    /** Percentage discount granted by the tier. */
    public function discountPercent(): int
    {
        return match ($this) {
            self::Bronze   => 0,
            self::Silver   => 2,
            self::Gold     => 5,
            self::Platinum => 10,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Bronze   => 'badge-light-secondary',
            self::Silver   => 'badge-light-info',
            self::Gold     => 'badge-light-warning',
            self::Platinum => 'badge-light-primary',
        };
    }

    /** Highest tier whose threshold the lifetime points meet. */
    public static function forPoints(int $lifetimePoints): self
    {
        $tier = self::Bronze;

        foreach (self::cases() as $case) {
            if ($lifetimePoints >= $case->threshold()) {
                $tier = $case;
            }
        }

        return $tier;
    }

    /** The next tier up, or null at the top. */
    public function next(): ?self
    {
        $cases = self::cases();

        foreach ($cases as $i => $case) {
            if ($case === $this) {
                return $cases[$i + 1] ?? null;
            }
        }

        return null;
    }
}
