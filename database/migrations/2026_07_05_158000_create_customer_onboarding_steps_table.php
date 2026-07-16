<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_onboarding_steps')) {
            Schema::create('customer_onboarding_steps', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('step', 60); // email_verified|billing_details|first_order|profile_complete
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['customer_id', 'step']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('customer_onboarding_steps');
    }
};
