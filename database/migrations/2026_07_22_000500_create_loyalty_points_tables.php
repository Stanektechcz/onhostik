<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty points + redeemable reward catalog (alongside the existing
 * milestone-based auto-rewards).
 *
 * - loyalty_point_transactions: a signed ledger (earn positive, redeem
 *   negative); balance is the sum.
 * - loyalty_rewards: admin-defined catalog items with a points cost that grant
 *   credit on redemption.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_point_transactions')) {
            Schema::create('loyalty_point_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->integer('points'); // signed: + earned, - redeemed
                $table->string('reason');
                $table->nullableMorphs('reference');
                $table->timestamps();

                $table->index(['customer_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('loyalty_rewards')) {
            Schema::create('loyalty_rewards', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('points_cost');
                $table->string('reward_type', 20)->default('credit_czk');
                $table->unsignedInteger('reward_value_halere')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_transactions');
        Schema::dropIfExists('loyalty_rewards');
    }
};
