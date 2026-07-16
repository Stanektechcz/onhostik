<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('api_usage_logs')) {
            Schema::create('api_usage_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('token_id')->nullable()->index();
                $table->string('endpoint', 200);
                $table->string('method', 10);
                $table->unsignedSmallInteger('status_code');
                $table->unsignedSmallInteger('response_time_ms')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->nullable()->index();

                // No updated_at — append-only log
                $table->index(['user_id', 'created_at']);
                $table->index(['endpoint', 'created_at']);
                $table->index(['status_code', 'created_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
