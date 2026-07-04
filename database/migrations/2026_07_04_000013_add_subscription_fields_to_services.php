<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('cancel_at_period_end')->default(false)->after('suspension_reason');
            $table->string('cancellation_reason')->nullable()->after('cancel_at_period_end');
            $table->timestamp('paused_at')->nullable()->after('cancellation_reason');
            $table->date('paused_until')->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['cancel_at_period_end', 'cancellation_reason', 'paused_at', 'paused_until']);
        });
    }
};
