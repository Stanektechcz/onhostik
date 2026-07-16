<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_uptime_checks')) {
            Schema::create('service_uptime_checks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->string('check_type', 20)->default('ping'); // ping | http | tcp
                $table->string('target', 255);                     // hostname or URL
                $table->boolean('is_up')->default(true);
                $table->unsignedSmallInteger('response_ms')->nullable();
                $table->string('error_message', 500)->nullable();
                $table->timestamp('checked_at');

                $table->index(['service_id', 'checked_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_uptime_checks');
    }
};
