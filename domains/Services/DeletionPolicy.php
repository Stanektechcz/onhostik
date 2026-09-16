<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Platform\Settings\SettingsStore;

/**
 * How a cancelled service dies (audit §5ab). The order is fixed and every number here is editable by staff in
 * "Nastavení systému → Životní cyklus služeb":
 *
 *  1. the service is identified by at least `identityChecks()` independent identifiers (ServiceIdentityCheck),
 *  2. everything the provider can hand over is archived (FinalArchive) — nothing is touched before that finishes,
 *  3. the service is only *deactivated*; the customer has `graceDays()` days to bring it back,
 *  4. after the grace window it is really removed (onhost:services:purge) and the archive is kept
 *     `retentionDays()` days counted from that removal,
 *  5. restoring the archive onto a new paid service is free; downloading it as one compressed file without a new
 *     service costs `downloadFeeMinor()` (500 Kč by default).
 */
final class DeletionPolicy
{
    public const SETTING = 'services.deletion';

    /** @var array<string,int> */
    private const FEE_FALLBACK = ['CZK' => 50000, 'EUR' => 2000];

    public function __construct(private readonly SettingsStore $settings) {}

    /** Days the customer may restore a deactivated service before it is removed for good. */
    public function graceDays(): int
    {
        return $this->int('grace_days', (int) config('onhost.services.deletion.grace_days', 30), 1, 365);
    }

    /** Days the final archive is kept — counted from the removal of the service. */
    public function retentionDays(): int
    {
        return $this->int('retention_days', (int) config('onhost.platform_backup.service_archive_days', 60), 30, 3650);
    }

    /** How many identifiers must match before anything is deleted (never fewer than five). */
    public function identityChecks(): int
    {
        return $this->int('identity_checks', (int) config('onhost.services.deletion.identity_checks', 5), 5, 12);
    }

    /** The fee for downloading an archive without ordering a new service, in minor units. */
    public function downloadFeeMinor(string $currency = 'CZK'): int
    {
        $currency = strtoupper($currency);
        $fees = (array) $this->value('download_fee_minor', []);
        $configured = $fees[$currency] ?? config('onhost.services.deletion.download_fee_minor.'.$currency);

        return max(0, (int) ($configured ?? self::FEE_FALLBACK[$currency] ?? 0));
    }

    /** @return array{grace_days:int, retention_days:int, identity_checks:int, download_fee_minor:array<string,int>} */
    public function all(): array
    {
        $fees = [];
        foreach (array_keys(self::FEE_FALLBACK) as $currency) {
            $fees[$currency] = $this->downloadFeeMinor($currency);
        }

        return ['grace_days' => $this->graceDays(), 'retention_days' => $this->retentionDays(), 'identity_checks' => $this->identityChecks(), 'download_fee_minor' => $fees];
    }

    /**
     * Staff edit: only the known keys are stored and every one of them is clamped, so a typo in the console can
     * never shorten the retention below a month or drop the identity verification below five points.
     *
     * @param  array<string,mixed>  $values
     * @return array{grace_days:int, retention_days:int, identity_checks:int, download_fee_minor:array<string,int>}
     */
    public function set(array $values, ?string $updatedBy = null): array
    {
        $current = (array) $this->settings->get(self::SETTING, []);
        $next = [
            'grace_days' => isset($values['grace_days']) ? max(1, min(365, (int) $values['grace_days'])) : $this->graceDays(),
            'retention_days' => isset($values['retention_days']) ? max(30, min(3650, (int) $values['retention_days'])) : $this->retentionDays(),
            'identity_checks' => isset($values['identity_checks']) ? max(5, min(12, (int) $values['identity_checks'])) : $this->identityChecks(),
            'download_fee_minor' => (array) ($current['download_fee_minor'] ?? []),
        ];
        foreach ((array) ($values['download_fee_minor'] ?? []) as $currency => $amount) {
            $currency = strtoupper((string) $currency);
            if (isset(self::FEE_FALLBACK[$currency])) {
                $next['download_fee_minor'][$currency] = max(0, min(100_000_00, (int) $amount));
            }
        }
        $this->settings->set(self::SETTING, $next, $updatedBy);

        return $this->all();
    }

    private function value(string $key, mixed $default = null): mixed
    {
        $all = (array) $this->settings->get(self::SETTING, []);

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    private function int(string $key, int $default, int $min, int $max): int
    {
        $value = $this->value($key);

        return max($min, min($max, is_numeric($value) ? (int) $value : $default));
    }
}
