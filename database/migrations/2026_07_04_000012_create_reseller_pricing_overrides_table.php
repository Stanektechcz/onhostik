<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reseller_pricing_overrides')) {
            Schema::create('reseller_pricing_overrides', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reseller_id')->constrained('reseller_profiles')->cascadeOnDelete();
                $table->foreignId('pricing_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->unsignedInteger('price_czk')->nullable()->comment('Minor units (haléře)');
                $table->unsignedInteger('price_eur')->nullable()->comment('Minor units (cents)');
                $table->unsignedInteger('price_usd')->nullable()->comment('Minor units (cents)');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['reseller_id', 'pricing_plan_id']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_pricing_overrides');
    }
};
