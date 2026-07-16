<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_backup_logs')) {
            Schema::create('service_backup_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id')->index();
                $table->enum('status', ['running', 'success', 'failed', 'cancelled'])->default('running')->index();
                $table->bigInteger('size_bytes')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->text('error_message')->nullable();
                $table->datetime('started_at');
                $table->datetime('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_backup_logs');
    }
};
