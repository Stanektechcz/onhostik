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
            $table->timestamp('dunning_paused_until')->nullable()->after('paid_at');
            $table->timestamp('reminder_1d_sent_at')->nullable()->after('dunning_paused_until');
            $table->timestamp('reminder_3d_sent_at')->nullable()->after('reminder_1d_sent_at');
            $table->timestamp('reminder_7d_sent_at')->nullable()->after('reminder_3d_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['dunning_paused_until', 'reminder_1d_sent_at', 'reminder_3d_sent_at', 'reminder_7d_sent_at']);
        });
    }
};
