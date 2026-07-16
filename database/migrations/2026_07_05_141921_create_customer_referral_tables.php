<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each customer gets one referral code
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('referral_code', 20)->nullable()->unique()->after('segment');
            $table->foreignId('referred_by_customer_id')->nullable()->constrained('customers')->nullOnDelete()->after('referral_code');
        });

        // Tracks each referral conversion
        if (!Schema::hasTable('customer_referrals')) {
            Schema::create('customer_referrals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('referrer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('referee_id')->constrained('customers')->cascadeOnDelete();
                $table->string('status', 20)->default('pending');    // pending | qualified | rewarded | expired
                $table->integer('referrer_reward_haler')->default(0);
                $table->integer('referee_reward_haler')->default(0);
                $table->string('currency', 3)->default('CZK');
                $table->timestamp('qualified_at')->nullable();
                $table->timestamp('rewarded_at')->nullable();
                $table->timestamps();

                $table->unique('referee_id');               // one referral per new customer
                $table->index(['referrer_id', 'status']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('customer_referrals');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['referral_code', 'referred_by_customer_id']);
        });
    }
};
