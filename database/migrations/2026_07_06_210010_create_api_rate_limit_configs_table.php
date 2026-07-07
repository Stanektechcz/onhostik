<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_rate_limit_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 50)->default('global');
            $table->unsignedInteger('requests_per_minute')->default(60);
            $table->unsignedInteger('requests_per_day')->default(10000);
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_rate_limit_configs');
    }
};
