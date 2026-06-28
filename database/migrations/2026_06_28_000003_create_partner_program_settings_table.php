<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_program_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string|boolean|integer|json
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Insert default settings
        $defaults = [
            ['key' => 'affiliate_enabled',           'value' => '1',    'type' => 'boolean', 'group' => 'affiliate'],
            ['key' => 'affiliate_commission_rate',    'value' => '10',   'type' => 'integer', 'group' => 'affiliate'],
            ['key' => 'affiliate_cookie_days',        'value' => '30',   'type' => 'integer', 'group' => 'affiliate'],
            ['key' => 'affiliate_min_payout',         'value' => '500',  'type' => 'integer', 'group' => 'affiliate'],
            ['key' => 'referral_enabled',             'value' => '1',    'type' => 'boolean', 'group' => 'referral'],
            ['key' => 'referral_reward_amount',       'value' => '100',  'type' => 'integer', 'group' => 'referral'],
            ['key' => 'referral_reward_currency',     'value' => 'CZK',  'type' => 'string',  'group' => 'referral'],
            ['key' => 'referral_code_length',         'value' => '8',    'type' => 'integer', 'group' => 'referral'],
            ['key' => 'codes_enabled',                'value' => '1',    'type' => 'boolean', 'group' => 'codes'],
            ['key' => 'codes_discount_type',          'value' => 'percent', 'type' => 'string', 'group' => 'codes'],
            ['key' => 'codes_max_uses_per_code',      'value' => '100',  'type' => 'integer', 'group' => 'codes'],
            ['key' => 'banners_enabled',              'value' => '1',    'type' => 'boolean', 'group' => 'banners'],
            ['key' => 'payout_schedule',              'value' => 'monthly', 'type' => 'string', 'group' => 'payout'],
            ['key' => 'payout_min_amount',            'value' => '500',  'type' => 'integer', 'group' => 'payout'],
            ['key' => 'payout_currency',              'value' => 'CZK',  'type' => 'string',  'group' => 'payout'],
            ['key' => 'auto_approve_threshold',       'value' => '30',   'type' => 'integer', 'group' => 'general'],
            ['key' => 'eligible_days_after_payment',  'value' => '30',   'type' => 'integer', 'group' => 'general'],
        ];

        foreach ($defaults as $setting) {
            \DB::table('partner_program_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_program_settings');
    }
};
