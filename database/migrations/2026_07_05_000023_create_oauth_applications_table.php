<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('client_id', 64)->unique();
            $table->string('client_secret_hash', 255);
            $table->json('redirect_uris')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_applications');
    }
};
