<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Partner\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the local development admin account.
 *
 * Credentials come from .env (ADMIN_EMAIL / ADMIN_PASSWORD) with safe
 * local defaults. NEVER seed this in production with default values.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@onhost.local')],
            [
                'name'              => 'OnHost Admin',
                'password'          => env('ADMIN_PASSWORD', 'password'),
                'email_verified_at' => now(),
                'locale'            => 'cs',
            ],
        );

        $user->assignRole('admin');

        // Give admin a demo partner profile so partner panel is testable in dev
        if (!PartnerProfile::where('user_id', $user->id)->exists()) {
            $user->givePermissionTo('access-partner');
            PartnerProfile::create([
                'user_id'                  => $user->id,
                'referral_code'            => 'ONHOSTDEV',
                'status'                   => 'active',
                'commission_rate_percent'  => 10.0,
            ]);
        }

        // Ensure admin also has a customer profile for /panel access
        $customer = Customer::firstOrCreate(
            ['user_id' => $user->id],
            [
                'type'                => 'company',
                'email'               => $user->email,
                'company_name'        => 'Onhost.cz s.r.o.',
                'registration_number' => '08094616',
                'preferred_currency'  => 'CZK',
                'preferred_locale'    => 'cs',
                'country_code'        => 'CZ',
            ],
        );

        // Billing address — required for tax documents (IssueTaxDocumentAction)
        CustomerAddress::firstOrCreate(
            ['customer_id' => $customer->id, 'type' => 'billing'],
            [
                'street'      => 'Testovací ulice 1',
                'city'        => 'Praha',
                'zip'         => '11000',
                'country_code' => 'CZ',
                'is_primary'  => true,
            ],
        );
    }
}
