<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit §5o: evidence behind monthly marketplace deliverables; readiness of vendor-ordered nodes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->json('period_evidence')->nullable()->after('missed_periods'); // the checklist the partner ticked per period (§5o-2)
        });
        Schema::table('capacity_requests', function (Blueprint $table): void {
            $table->timestamp('ready_at')->nullable()->after('delivered_at'); // the ordered node's bootstrap reported back (§5o-7)
        });
    }

    public function down(): void
    {
        Schema::table('capacity_requests', function (Blueprint $table): void {
            $table->dropColumn('ready_at');
        });
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn('period_evidence');
        });
    }
};
