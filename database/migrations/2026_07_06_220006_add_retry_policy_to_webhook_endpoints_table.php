<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table): void {
            $table->tinyInteger('max_retries')->default(3)->after('secret');
            $table->unsignedSmallInteger('retry_delay_seconds')->default(60)->after('max_retries');
            $table->unsignedSmallInteger('timeout_seconds')->default(10)->after('retry_delay_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table): void {
            $table->dropColumn(['max_retries', 'retry_delay_seconds', 'timeout_seconds']);
        });
    }
};
