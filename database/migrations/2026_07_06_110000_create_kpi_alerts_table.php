<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_alerts', function (Blueprint $table): void {
            $table->id();
            // Metric key: overdue_invoices_count | failed_backups_24h |
            //             suspended_services_count | open_tickets_count | monthly_revenue_czk
            $table->string('metric', 64);
            // gte = alert when value >= threshold; lte = alert when value <= threshold
            $table->string('operator', 4)->default('gte');
            $table->decimal('threshold', 12, 2);
            $table->boolean('is_active')->default(true);
            // Set when the alert transitions to triggered; cleared when resolved
            $table->timestamp('triggered_at')->nullable();
            // Last evaluated metric value (for display)
            $table->decimal('last_value', 12, 2)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_alerts');
    }
};
