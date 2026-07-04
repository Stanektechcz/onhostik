<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('url');
            $table->string('secret', 64)->default('');
            $table->json('events');        // ['*'] or ['service.provisioned', 'service.failed', ...]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_webhooks');
    }
};
