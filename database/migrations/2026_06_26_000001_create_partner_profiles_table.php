<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('partner_profiles')) {
            Schema::create('partner_profiles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('referral_code', 16)->unique();
                $table->string('status', 20)->default('active');
                $table->decimal('commission_rate_percent', 5, 2)->default(10.00);
                $table->string('payout_method', 50)->nullable();
                $table->text('payout_details_encrypted')->nullable(); // Crypt-encrypted JSON
                $table->timestamps();

                $table->index('user_id');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
