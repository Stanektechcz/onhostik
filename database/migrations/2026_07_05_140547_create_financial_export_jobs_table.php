<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_export_jobs')) {
            Schema::create('financial_export_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('format', 20);
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
                $table->string('status', 20)->default('pending');
                $table->string('file_path')->nullable();
                $table->string('error_message')->nullable();
                $table->json('filters')->nullable();
                $table->integer('row_count')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('created_by');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('financial_export_jobs');
    }
};
