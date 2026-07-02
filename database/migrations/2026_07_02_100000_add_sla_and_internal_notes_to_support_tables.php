<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->timestamp('sla_deadline')->nullable()->after('closed_at');
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            // Internal notes visible only to staff — not shown to customers
            $table->boolean('is_internal')->default(false)->after('is_staff');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn('sla_deadline');
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->dropColumn('is_internal');
        });
    }
};
