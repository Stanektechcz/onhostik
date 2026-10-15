<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W4-6 (docs/compliance/dpia-funnel-telemetry.md): daily count of cart quotes per plan, period and locale. No identifier of a
 * person, organization, cart or quote; platform only, no endpoint reads it. Filled by CountQuoteRequest at outbox relay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_daily', function (Blueprint $table): void {
            $table->date('date');
            $table->string('plan_key', 60);
            $table->string('period', 5);
            $table->string('locale', 2);
            $table->unsignedInteger('count')->default(0);
            $table->unique(['date', 'plan_key', 'period', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_daily');
    }
};
