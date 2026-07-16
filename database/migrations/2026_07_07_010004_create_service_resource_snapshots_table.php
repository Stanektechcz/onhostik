<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_resource_snapshots')) {
            Schema::create('service_resource_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id')->index();
                $table->unsignedInteger('disk_gb')->nullable();
                $table->unsignedInteger('bandwidth_gb')->nullable();
                $table->unsignedTinyInteger('cpu_percent')->nullable();
                $table->unsignedInteger('ram_mb')->nullable();
                $table->timestamp('recorded_at')->index();

                $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_resource_snapshots');
    }
};
