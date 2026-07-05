<?php

declare(strict_types=1);

namespace App\Domains\Partner\Services;

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Models\PartnerProfile;

final class PartnerTierService
{
    private const TIERS = [
        ['label' => 'Gold',   'color' => 'warning', 'min_czk' => 50000_00, 'rate' => 12.0],
        ['label' => 'Silver', 'color' => 'secondary', 'min_czk' => 10000_00, 'rate' =>  8.0],
        ['label' => 'Bronze', 'color' => 'dark',    'min_czk' =>     0,    'rate' =>  5.0],
    ];

    /**
     * Tier label, color and recommended rate for a partner based on total paid commissions.
     *
     * @return array{label: string, color: string, rate: float, total_paid_minor: int, next_tier: string|null, next_at_minor: int|null}
     */
    public function forProfile(PartnerProfile $profile): array
    {
        $totalPaidMinor = (int) $profile->commissions()
            ->where('status', CommissionStatus::Paid->value)
            ->sum('amount');

        $current   = self::TIERS[array_key_last(self::TIERS)];
        $nextTier  = null;
        $nextAt    = null;

        foreach (self::TIERS as $i => $tier) {
            if ($totalPaidMinor >= $tier['min_czk']) {
                $current  = $tier;
                $nextTier = $i > 0 ? self::TIERS[$i - 1]['label'] : null;
                $nextAt   = $i > 0 ? self::TIERS[$i - 1]['min_czk'] : null;
                break;
            }
            // Not yet at this tier — show next attainable
            $nextTier = $tier['label'];
            $nextAt   = $tier['min_czk'];
        }

        return [
            'label'            => $current['label'],
            'color'            => $current['color'],
            'rate'             => $current['rate'],
            'total_paid_minor' => $totalPaidMinor,
            'next_tier'        => $nextTier,
            'next_at_minor'    => $nextAt,
        ];
    }

    /**
     * Auto-upgrade commission_rate_percent if tier warrants it.
     * Only upgrades (never downgrades) to preserve manually set rates above tier.
     */
    public function maybeUpgrade(PartnerProfile $profile): void
    {
        $tier = $this->forProfile($profile);

        if ($profile->commission_rate_percent < $tier['rate']) {
            $profile->update(['commission_rate_percent' => $tier['rate']]);
        }
    }
}
