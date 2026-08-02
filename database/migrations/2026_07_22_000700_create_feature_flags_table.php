<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature flags (audit 500 #110/#383): turn functionality on/off without a
 * deploy, optionally as a percentage rollout or for named accounts only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feature_flags')) {
            return;
        }

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            // 0–100; applies only when is_enabled. 100 = everyone.
            $table->unsignedTinyInteger('rollout_percent')->default(100);
            // Explicit allow-list of customer ids that always get the feature.
            $table->json('customer_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
