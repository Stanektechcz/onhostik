<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_maintenance_windows')) {
            Schema::create('service_maintenance_windows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
                $table->dateTime('scheduled_start');
                $table->dateTime('scheduled_end');
                $table->dateTime('actual_start')->nullable();
                $table->dateTime('actual_end')->nullable();
                $table->boolean('notify_customers')->default(true);
                $table->dateTime('notified_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['service_id', 'scheduled_start']);
                $table->index(['status', 'scheduled_start']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_maintenance_windows');
    }
};
