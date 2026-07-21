<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records HOW a refunded customer was made whole (audit D56).
 *
 * Marking a payment ManualRefund said the refund happened but not where the
 * money went — back to the card via the gateway dashboard, or onto the
 * customer's credit balance. Accounting needs that distinction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'refund_destination')) {
                $table->string('refund_destination', 16)->nullable();
            }
            if (! Schema::hasColumn('payments', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable();
            }
            if (! Schema::hasColumn('payments', 'refund_reason')) {
                $table->string('refund_reason', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            foreach (['refund_destination', 'refunded_at', 'refund_reason'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
