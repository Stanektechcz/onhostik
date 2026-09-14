<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a product/plan runs: the operator pins a plan (or a whole product, optionally per region) to a provider
 * instance and optionally to one node. The scheduler honours the most specific active placement; without one it
 * falls back to role/region scheduling over every usable instance of the product's executor family.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_placements', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('product_key', 60)->index();
            $table->string('plan_key', 60)->nullable();
            $table->string('region_code', 16)->nullable();
            $table->string('provider_instance_id', 40)->index();
            $table->string('node_id', 40)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('state', 16)->default('active');
            $table->string('note', 250)->nullable();
            $table->string('created_by', 60)->nullable();
            $table->timestamps();
            $table->unique(['product_key', 'plan_key', 'region_code'], 'plan_placements_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_placements');
    }
};
