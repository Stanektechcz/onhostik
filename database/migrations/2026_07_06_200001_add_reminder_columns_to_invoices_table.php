<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedTinyInteger('reminder_sent_count')->default(0)->after('renewal_failure_notified_at');
            $table->timestamp('last_reminder_at')->nullable()->after('reminder_sent_count');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['reminder_sent_count', 'last_reminder_at']);
        });
    }
};
