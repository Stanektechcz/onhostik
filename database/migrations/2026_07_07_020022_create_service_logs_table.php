<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id')->index();
            $table->enum('level', ['debug', 'info', 'warning', 'error'])->default('info')->index();
            $table->string('source', 60)->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('logged_at')->index();

            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_logs');
    }
};
