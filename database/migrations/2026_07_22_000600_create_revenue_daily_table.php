<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialized daily-revenue rollup (audit 500 #11). Aggregating paid invoices
 * live on every dashboard load is wasteful and slows down as invoices grow; a
 * per-day/per-currency rollup is O(days) to read and refreshed by a scheduled
 * command.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('revenue_daily')) {
            return;
        }

        Schema::create('revenue_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->char('currency', 3);
            $table->bigInteger('gross_minor')->default(0); // incl. VAT (invoice total)
            $table->bigInteger('net_minor')->default(0);   // excl. VAT (invoice subtotal)
            $table->unsignedInteger('invoices_count')->default(0);
            $table->timestamps();

            $table->unique(['date', 'currency']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_daily');
    }
};
