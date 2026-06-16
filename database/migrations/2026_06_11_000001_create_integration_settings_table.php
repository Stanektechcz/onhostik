<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();          // aapanel | wedos | comgate | ...
            $table->string('label');
            $table->text('credentials')->nullable();       // encrypted JSON — never plaintext
            $table->boolean('is_active')->default(false);
            $table->boolean('mock_mode')->default(true);
            $table->boolean('dry_run')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->json('meta')->nullable();              // non-secret extras (base url, region…)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
