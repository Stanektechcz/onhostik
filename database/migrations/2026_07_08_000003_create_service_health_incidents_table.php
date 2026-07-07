<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_health_incidents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id')->index();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved'])->default('open')->index();
            $table->datetime('resolved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_health_incidents');
    }
};
