<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain', 253)->index();
            $table->string('status', 16)->default('pending')->index();
            $table->string('provider', 32)->default('mock');
            $table->json('nameservers')->nullable();
            $table->timestamp('ns_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'domain']);
            $table->index(['status', 'customer_id']);
        });

        Schema::create('dns_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);            // A|AAAA|CNAME|MX|TXT|NS|SRV|CAA|PTR
            $table->string('name', 253);           // relative (@ or subdomain)
            $table->text('content');
            $table->unsignedInteger('ttl')->default(3600);
            $table->unsignedSmallInteger('priority')->nullable();   // MX / SRV
            $table->timestamps();

            $table->index(['dns_zone_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('dns_zones');
    }
};
