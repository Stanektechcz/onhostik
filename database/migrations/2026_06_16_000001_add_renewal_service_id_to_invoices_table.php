<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identifies a renewal invoice's (service, due_date) cycle so
     * IssueRenewalInvoiceAction can stay idempotent — one renewal invoice
     * per service per due-date cycle, never a duplicate.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('renewal_service_id')->nullable()->after('order_id')
                ->constrained('services')->nullOnDelete();

            $table->index(['renewal_service_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('renewal_service_id');
        });
    }
};
