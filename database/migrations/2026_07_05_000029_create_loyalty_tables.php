<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_milestones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('trigger_type', ['account_age_days', 'order_count', 'total_spent_czk']);
            $table->unsignedInteger('trigger_value');   // days / order count / haléře
            $table->enum('reward_type', ['credit_czk', 'badge', 'discount_percent']);
            $table->unsignedInteger('reward_value');    // haléře / label id / percent
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_loyalty_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_milestone_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at')->useCurrent();
            $table->string('note')->nullable();
            $table->unique(['customer_id', 'loyalty_milestone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_rewards');
        Schema::dropIfExists('loyalty_milestones');
    }
};
