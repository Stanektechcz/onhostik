<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('type')->default('percent'); // percent | fixed
            $table->unsignedDecimal('value', 8, 2); // % or minor units
            $table->string('currency', 3)->nullable(); // for fixed type
            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            // Who created it (partner code vs admin code)
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('admin'); // admin | partner
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });

        Schema::create('discount_code_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->unique(['discount_code_id', 'customer_id']);
            $table->index('discount_code_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('discount_code_id')->nullable()->after('customer_id')->constrained('discount_codes')->nullOnDelete();
            $table->unsignedDecimal('discount_amount', 10, 2)->default(0)->after('discount_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Domains\Billing\Models\Order::class, 'discount_code_id');
            $table->dropColumn(['discount_code_id', 'discount_amount']);
        });
        Schema::dropIfExists('discount_code_usages');
        Schema::dropIfExists('discount_codes');
    }
};
