<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_windows', function (Blueprint $table): void {
            $table->timestamp('customers_notified_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_windows', function (Blueprint $table): void {
            $table->dropColumn('customers_notified_at');
        });
    }
};
