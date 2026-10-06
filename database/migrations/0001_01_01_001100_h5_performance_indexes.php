<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * H5 (performance): the two lookups that had no index of their own.
 *  - loyalty_points (organization_id, created_at): the balance, the expiry candidates and the doctor's per-organization aggregates
 *    group by organization and cut by date; the unique (organization_id, rule, reference) key cannot serve the date cut.
 *  - payment_refunds.credit_note_id: "is any refund still using this credit note" runs per cancelled refund and per credit note.
 * Plain indexes, no data change; both directions are safe to repeat (checked with hasIndex), on SQLite and PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('loyalty_points', ['organization_id', 'created_at'])) {
            Schema::table('loyalty_points', function (Blueprint $table): void {
                $table->index(['organization_id', 'created_at'], 'loyalty_points_org_created_idx');
            });
        }
        if (! Schema::hasIndex('payment_refunds', ['credit_note_id'])) {
            Schema::table('payment_refunds', function (Blueprint $table): void {
                $table->index('credit_note_id', 'payment_refunds_credit_note_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('loyalty_points', 'loyalty_points_org_created_idx')) {
            Schema::table('loyalty_points', fn (Blueprint $table) => $table->dropIndex('loyalty_points_org_created_idx'));
        }
        if (Schema::hasIndex('payment_refunds', 'payment_refunds_credit_note_idx')) {
            Schema::table('payment_refunds', fn (Blueprint $table) => $table->dropIndex('payment_refunds_credit_note_idx'));
        }
    }
};
