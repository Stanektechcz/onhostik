<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Customer\Models\Customer;

/**
 * Resolves the VAT scenario and rate for a customer.
 *
 * Rules (Czech VAT law + EU OSS, 2026):
 *  - CZ customer, no validated DIČ      → CzechB2C, 21 %
 *  - CZ customer, validated DIČ         → CzechB2B, 21 %
 *  - EU customer, no validated VAT ID   → EuB2C, OSS rate of customer's country
 *  - EU customer, validated VAT ID      → EuB2BReverseCharge, 0 %
 *  - Non-EU customer                    → NonEu, 0 %
 *
 * OSS rates are configurable in config/billing.php and must be reviewed
 * whenever a member state changes its standard rate.
 */
final class VatResolver
{
    public function resolveScenario(Customer $customer): VatScenario
    {
        if ($customer->isCzech()) {
            return $customer->isVatPayer()
                ? VatScenario::CzechB2B
                : VatScenario::CzechB2C;
        }

        if ($customer->isEu()) {
            return $customer->isVatPayer()
                ? VatScenario::EuB2BReverseCharge
                : VatScenario::EuB2C;
        }

        return VatScenario::NonEu;
    }

    /** Returns the VAT rate in percent (e.g. 21.0). */
    public function resolveRate(Customer $customer): float
    {
        $scenario = $this->resolveScenario($customer);

        return match ($scenario) {
            VatScenario::CzechB2C,
            VatScenario::CzechB2B => (float) config('billing.vat.cz_rate', 21.0),

            VatScenario::EuB2C => $this->ossRateFor($customer->country_code),

            VatScenario::EuB2BReverseCharge,
            VatScenario::NonEu => 0.0,
        };
    }

    /** OSS — standard VAT rate of the customer's member state. */
    private function ossRateFor(string $countryCode): float
    {
        $rates = config('billing.vat.oss_rates', []);

        return (float) ($rates[$countryCode]
            ?? throw new \RuntimeException("Missing OSS VAT rate for country [{$countryCode}]. Update config/billing.php."));
    }
}
