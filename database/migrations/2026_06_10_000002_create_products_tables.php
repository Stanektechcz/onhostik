<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('type', 32)->index();                  // webhosting|gamehosting|vps|domain
                $table->json('name');                                 // translatable {"cs":..,"en":..}
                $table->json('description')->nullable();
                $table->string('provisioning_driver', 32);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }


        if (!Schema::hasTable('pricing_plans')) {
            Schema::create('pricing_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->json('name');                                 // translatable
                $table->json('tagline')->nullable();                  // translatable
                $table->string('billing_cycle', 16)->default('monthly');
                // Prices in MINOR UNITS (haléře/cents). NULL = currency not offered.
                $table->unsignedBigInteger('price_czk')->nullable();
                $table->unsignedBigInteger('price_eur')->nullable();
                $table->unsignedBigInteger('price_usd')->nullable();
                $table->unsignedBigInteger('setup_fee_czk')->nullable();
                $table->unsignedBigInteger('setup_fee_eur')->nullable();
                $table->unsignedBigInteger('setup_fee_usd')->nullable();
                $table->json('resources')->nullable();                // disk_mb, ram_mb, cpu, slots...
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_featured')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['product_id', 'is_active', 'sort_order']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_plans');
        Schema::dropIfExists('products');
    }
};
