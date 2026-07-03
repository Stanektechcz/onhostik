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
        return ['required', 'string', Password::min(8), new StrongPassword(), 'confirmed'];
    }
}
