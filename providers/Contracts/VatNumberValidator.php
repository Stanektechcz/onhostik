<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Asks the EU register (VIES) whether a VAT number is registered (TASK-0031, D31.1). Implementations never throw: every
 * failure — no connection, an open breaker, a refusal, an answer nobody understands — is `unknown` with a code, and the
 * caller writes nothing on `unknown`. The trader's name and address in the answer are evidence for the tax decision only;
 * implementations never log them.
 */
interface VatNumberValidator
{
    /**
     * @param  string  $countryCode  the VIES member-state code of the number (EL for Greece)
     * @param  string  $number  the number without its country prefix
     */
    public function check(string $countryCode, string $number, int $timeoutSeconds): VatCheckResult;
}
