<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency guard for HandleInvoicePaid's renewal branch — set exactly
     * once, under row lock, the moment a renewal invoice's payment has
     * extended its service's next_due_date. A replayed InvoicePaid event for
     * the same invoice must never extend the due date twice.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('renewal_applied_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('renewal_applied_at');
        });
    }
};
