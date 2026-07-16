<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('api_token_rate_limits')) {
            Schema::create('api_token_rate_limits', static function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('token_id')->unique(); // personal_access_tokens.id
                $table->unsignedInteger('requests_per_minute')->default(30);
                $table->unsignedInteger('requests_per_hour')->default(500);
                $table->unsignedInteger('requests_per_day')->default(5000);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('api_token_rate_limits');
    }
};
