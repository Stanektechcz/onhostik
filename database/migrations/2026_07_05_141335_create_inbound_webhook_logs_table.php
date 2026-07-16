<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inbound_webhook_logs')) {
            Schema::create('inbound_webhook_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 50);                     // comgate | stripe | github | generic
                $table->string('event_type', 100)->nullable();
                $table->string('status', 20)->default('received'); // received | processed | ignored | failed
                $table->json('headers')->nullable();
                $table->json('payload');
                $table->string('signature_header')->nullable();
                $table->boolean('signature_valid')->default(false);
                $table->string('error_message')->nullable();
                $table->string('idempotency_key', 128)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['source', 'status']);
                $table->index('created_at');
                $table->index('idempotency_key');
            });
        }


        if (!Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('source', 50)->unique();           // slug: comgate, stripe, etc.
                $table->string('secret', 255)->nullable();        // HMAC secret (never logged)
                $table->string('signature_algo', 20)->default('sha256');
                $table->string('signature_header', 100)->default('X-Signature');
                $table->boolean('is_active')->default(true);
                $table->json('allowed_events')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhook_logs');
        Schema::dropIfExists('webhook_endpoints');
    }
};
