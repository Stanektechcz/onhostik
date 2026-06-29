<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Domains\Customer\Models\Customer;
use App\Domains\Partner\Services\ReferralTracker;
use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

/**
 * Registers a new customer account: User (auth identity) + Customer
 * (billing identity) are created together, atomically.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = DB::transaction(function () use ($input): User {
            $user = User::create([
                'name'     => $input['name'],
                'email'    => $input['email'],
                'password' => $input['password'],
                'locale'   => app()->getLocale(),
            ]);

            $user->assignRole(Role::findOrCreate('customer', 'web'));

            Customer::create([
                'user_id'            => $user->id,
                'type'               => 'individual',
                'email'              => $user->email,
                'preferred_currency' => config('billing.default_currency', 'CZK'),
                'preferred_locale'   => app()->getLocale(),
                'country_code'       => 'CZ',
            ]);

            return $user;
        });

        // Link referral code from session — outside transaction so failures
        // never break registration. ReferralTracker handles all edge cases.
        $referralCode = session(config('partner.session_key'));
        if (is_string($referralCode) && $referralCode !== '') {
            try {
                app(ReferralTracker::class)->linkRegistration($user->fresh('customer'), $referralCode);
            } catch (\Throwable) {
                // Never break registration over referral errors
            }
        }

        /* Welcome email — non-fatal, never breaks registration. */
        try {
            $user->notify(new WelcomeUserNotification($user));
        } catch (\Throwable) {}

        return $user;
    }
}
