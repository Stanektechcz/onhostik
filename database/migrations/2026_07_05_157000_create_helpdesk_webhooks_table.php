<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_webhooks', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('url');
            $table->string('secret', 100)->nullable(); // HMAC signing secret
            $table->json('events'); // ['ticket.created','ticket.replied','ticket.closed','ticket.status_changed']
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->timestamp('last_fired_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();
        });

        Schema::create('helpdesk_webhook_deliveries', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('helpdesk_webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->json('payload');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('fired_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_webhook_deliveries');
        Schema::dropIfExists('helpdesk_webhooks');
    }
};
