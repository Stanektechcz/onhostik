<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit §5p: files behind checklist items of a monthly deliverable; a bootstrapped node activating itself. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->json('period_uploads')->nullable()->after('period_evidence'); // files uploaded for the running period, consumed by the report (§5p-3)
        });
        Schema::table('capacity_requests', function (Blueprint $table): void {
            $table->timestamp('activated_at')->nullable()->after('ready_at'); // the playbook reported the hypervisor installed (§5p-7)
        });
    }

    public function down(): void
    {
        Schema::table('capacity_requests', function (Blueprint $table): void {
            $table->dropColumn('activated_at');
        });
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn('period_uploads');
        });
    }
};
