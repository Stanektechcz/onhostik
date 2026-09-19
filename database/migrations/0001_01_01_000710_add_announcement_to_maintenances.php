<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What makes a maintenance window "planned" in the sense of the SLA (Brain card H15): it was announced — approved by
 * a second person, which is what notifies the customers — at least the lead time before it started. The moment of the
 * announcement and the emergency flag are kept so the rule can be checked, by us and by whoever audits an SLA report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table): void {
            $table->timestamp('announced_at')->nullable(); // the approval = the moment customers were told
            $table->boolean('emergency')->default(false);  // scheduled at short notice: never excluded from the SLA
        });
        // windows approved before this column existed: the best knowledge of their announcement is when they were created
        DB::table('maintenances')->whereNotNull('approved_by')->whereNull('announced_at')->update(['announced_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table): void {
            $table->dropColumn(['announced_at', 'emergency']);
        });
    }
};
