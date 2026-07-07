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
            $table->timestamp('billing_pause_requested_at')->nullable()->after('auto_renew');
            $table->date('billing_paused_until')->nullable()->after('billing_pause_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['billing_pause_requested_at', 'billing_paused_until']);
        });
    }
};
