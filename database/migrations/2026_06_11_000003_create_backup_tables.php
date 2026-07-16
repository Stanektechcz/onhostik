<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('backup_policies')) {
            Schema::create('backup_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->string('frequency')->default('daily'); // daily | weekly
                $table->unsignedSmallInteger('retention_days')->default(14);
                $table->string('provider')->default('local_mock');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->unique('service_id');
            });
        }


        if (!Schema::hasTable('backup_jobs')) {
            Schema::create('backup_jobs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('backup_policy_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->string('type')->default('manual');     // manual | scheduled
                $table->string('status')->default('pending');  // pending | running | success | failed
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('size_mb')->nullable();
                $table->string('error_message', 500)->nullable();
                $table->timestamps();

                $table->index(['service_id', 'status']);
            });
        }


        if (!Schema::hasTable('backup_files')) {
            Schema::create('backup_files', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('backup_job_id')->constrained()->cascadeOnDelete();
                $table->string('disk')->default('local');
                $table->string('path');
                $table->unsignedInteger('size_mb')->default(0);
                $table->string('checksum')->nullable();
                $table->date('expires_at')->nullable();
                $table->timestamps();
            });
        }


        if (!Schema::hasTable('backup_restores')) {
            Schema::create('backup_restores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->foreignId('backup_file_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status')->default('requested'); // requested | approved | done | rejected
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('notes', 500)->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restores');
        Schema::dropIfExists('backup_files');
        Schema::dropIfExists('backup_jobs');
        Schema::dropIfExists('backup_policies');
    }
};
