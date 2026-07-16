<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_plan_changes')) {
            Schema::create('service_plan_changes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->foreignId('from_plan_id')->nullable()->constrained('pricing_plans')->nullOnDelete();
                $table->foreignId('to_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason')->nullable();    // 'customer_request' | 'admin_override' | 'auto'
                $table->timestamp('changed_at');
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_plan_changes');
    }
};
