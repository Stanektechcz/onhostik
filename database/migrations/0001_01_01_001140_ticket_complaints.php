<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L-15 (TASK-0145): a support ticket can be a complaint (reklamace) about a digital service. The law counts from the day the
 * customer made the claim: `complaint_received_at`, decided within 30 days (`complaint_due_at`, § 19 (3) of the Consumer Protection
 * Act), and the decision is confirmed (`complaint_resolved_at`, `complaint_outcome` accepted | partially_accepted | rejected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->timestamp('complaint_received_at')->nullable();
            $table->timestamp('complaint_due_at')->nullable()->index();
            $table->timestamp('complaint_resolved_at')->nullable();
            $table->string('complaint_outcome', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex(['complaint_due_at']);
            $table->dropColumn(['complaint_received_at', 'complaint_due_at', 'complaint_resolved_at', 'complaint_outcome']);
        });
    }
};
