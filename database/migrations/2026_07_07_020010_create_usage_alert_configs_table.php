<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_alert_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('metric', ['disk', 'bandwidth', 'cpu', 'ram'])->index();
            $table->unsignedTinyInteger('threshold_percent');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'user_id', 'metric']);
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_alert_configs');
    }
};
