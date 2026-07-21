<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Rules\StrongPassword;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * @return array<int, Rule|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        $strength = Password::min(8);

        /*
         | Reject passwords known from public breaches (audit H116).
         |
         | Complexity rules alone are not enough — "Password1!" satisfies every
         | one of them and appears in essentially every breach corpus.
         |
         | Laravel checks this against Have I Been Pwned using k-anonymity:
         | only the first 5 characters of the password's SHA-1 hash leave the
         | server, never the password. Configurable so an offline install (or
         | the test suite) is not blocked on an outbound HTTP call.
         */
        if ((bool) config('auth.password_breach_check', false)) {
            $strength = $strength->uncompromised();
        }

        return ['required', 'string', $strength, new StrongPassword(), 'confirmed'];
    }
}
