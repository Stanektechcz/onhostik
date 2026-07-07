<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', static function (Blueprint $table): void {
            $table->timestamp('renewal_reminder_30d_sent_at')->nullable()->after('next_due_date');
            $table->timestamp('renewal_reminder_14d_sent_at')->nullable()->after('renewal_reminder_30d_sent_at');
            $table->timestamp('renewal_reminder_7d_sent_at')->nullable()->after('renewal_reminder_14d_sent_at');
            $table->timestamp('renewal_reminder_1d_sent_at')->nullable()->after('renewal_reminder_7d_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', static function (Blueprint $table): void {
            $table->dropColumn([
                'renewal_reminder_30d_sent_at',
                'renewal_reminder_14d_sent_at',
                'renewal_reminder_7d_sent_at',
                'renewal_reminder_1d_sent_at',
            ]);
        });
    }
};
