<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §5k: hourly usage samples per node (the 7-day trend behind predictive rebalancing and the measured power behind the
 * green footprint) and the subscription behind a monthly marketplace listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_usage_samples', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('node_id', 40);
            $table->timestamp('sampled_at');
            $table->unsignedSmallInteger('cpu_pct')->default(0);
            $table->unsignedBigInteger('ram_used_mb')->default(0);
            $table->unsignedInteger('disk_used_gb')->default(0);
            $table->unsignedInteger('power_w')->nullable();       // measured watts (PDU / IPMI) when a probe reported them
            $table->string('source', 12)->default('snapshot');   // snapshot | probe
            $table->timestamps();
            $table->index(['node_id', 'sampled_at']);
        });

        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->string('subscription_id', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn('subscription_id');
        });
        Schema::dropIfExists('node_usage_samples');
    }
};
