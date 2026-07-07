<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_migration_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('source_server_id')->index();
            $table->unsignedBigInteger('target_server_id')->index();
            $table->json('service_ids');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_migration_batches');
    }
};
