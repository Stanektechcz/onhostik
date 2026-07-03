<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enforces a stronger password policy:
 *  - min 8 characters
 *  - at least 1 uppercase letter
 *  - at least 1 digit
 *  - at least 1 special character
 *
 * Used on registration, password reset, and the panel password-change form.
 */
final class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Heslo musí být textový řetězec.');
            return;
        }

        if (mb_strlen($value) < 8) {
            $fail('Heslo musí mít alespoň 8 znaků.');
            return;
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $fail('Heslo musí obsahovat alespoň jedno velké písmeno.');
            return;
        }

        if (! preg_match('/[0-9]/', $value)) {
            $fail('Heslo musí obsahovat alespoň jednu číslici.');
            return;
        }

        if (! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('Heslo musí obsahovat alespoň jeden speciální znak (např. !, @, #, $).');
            return;
        }
    }
}
