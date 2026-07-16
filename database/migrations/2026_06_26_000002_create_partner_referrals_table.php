<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('partner_referrals')) {
            Schema::create('partner_referrals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('referred_customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('referral_code', 16);
                $table->string('source_url', 2048)->nullable();
                $table->string('landing_url', 2048)->nullable();
                $table->string('ip_hash', 64)->nullable();         // sha256 of IP — no raw IP stored
                $table->string('user_agent_hash', 64)->nullable(); // sha256 of UA
                $table->timestamp('first_seen_at');
                $table->timestamp('registered_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->string('status', 20)->default('visitor');
                $table->timestamps();

                // One user can only be referred once (GDPR: no double-counting)
                $table->unique('referred_user_id');
                $table->index('partner_profile_id');
                $table->index('referral_code');
                $table->index('ip_hash');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('partner_referrals');
    }
};
