<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('driver', 32)->index();                 // aapanel|proxmox|pterodactyl|wedos
            $table->string('api_url');
            $table->text('api_credentials')->nullable();           // encrypted JSON
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('max_services')->nullable();
            $table->unsignedInteger('current_services')->default(0);
            $table->json('capacity_meta')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('mock_mode')->default(true);
            $table->timestamp('last_health_check_at')->nullable();
            $table->boolean('last_health_ok')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provisioning_driver', 32);
            $table->string('external_id')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->string('label');                                // domain / hostname
            $table->json('resources')->nullable();
            $table->date('next_due_date')->nullable()->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index(['provisioning_driver', 'external_id']);
            $table->index(['status', 'next_due_date']);
        });

        Schema::create('provisioning_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 24);
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('external_request_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'status']);
        });

        Schema::create('domain_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('tld', 16);
            $table->string('registrar', 24)->default('wedos');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('auto_renew')->default(true);
            $table->json('nameservers')->nullable();
            $table->text('auth_code')->nullable();                  // encrypted
            $table->string('wedos_domain_id')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'tld']);
        });

        Schema::create('provisioning_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('driver', 32);
            $table->string('endpoint');
            $table->json('request_sanitized')->nullable();
            $table->json('response_sanitized')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('success');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['driver', 'created_at']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_logs');
        Schema::dropIfExists('domain_registrations');
        Schema::dropIfExists('provisioning_tasks');
        Schema::dropIfExists('services');
        Schema::dropIfExists('servers');
    }
};
