<?php

declare(strict_types=1);

namespace Onhost\Providers\Vies;

use Onhost\Providers\Contracts\VatCheckResult;
use Onhost\Providers\Contracts\VatNumberValidator;

/**
 * The validator while ONHOST_VIES_ENABLED is off (the default until go-live): it asks nobody and knows nothing, so every
 * number stays `unknown` and is charged exactly as before the check existed.
 */
final class DisabledVatNumberValidator implements VatNumberValidator
{
    public function check(string $countryCode, string $number, int $timeoutSeconds): VatCheckResult
    {
        return VatCheckResult::unknown('vies_disabled', false);
    }
}
