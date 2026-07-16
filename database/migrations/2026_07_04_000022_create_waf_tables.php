<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('waf_rules')) {
            Schema::create('waf_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 20);          // ip_block|ip_allow|country_block|rate_limit
                $table->string('value', 100);        // IP, CIDR, ISO country code, or req/min limit
                $table->string('action', 16)->default('block');  // block|allow|throttle
                $table->string('notes', 500)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index(['service_id', 'type', 'is_active']);
                $table->index(['type', 'is_active']);
            });
        }


        if (!Schema::hasTable('waf_events')) {
            Schema::create('waf_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('waf_rule_id')->nullable()->constrained('waf_rules')->nullOnDelete();
                $table->string('ip_address', 45);
                $table->string('country_code', 2)->nullable();
                $table->string('request_uri', 500)->nullable();
                $table->string('method', 10)->nullable();
                $table->string('action_taken', 16)->default('blocked');
                $table->timestamp('blocked_at');
                $table->timestamp('created_at')->nullable();

                $table->index(['service_id', 'blocked_at']);
                $table->index(['ip_address', 'blocked_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('waf_events');
        Schema::dropIfExists('waf_rules');
    }
};
