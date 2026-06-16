<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();               // plan_recommendation | dns_explanation | ...
            $table->string('name');
            $table->string('audience')->default('customer'); // customer | admin
            $table->text('template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature');                     // plan_recommendation | dns_explanation | ...
            $table->string('provider')->default('mock');
            $table->string('status')->default('success');  // success | failed | needs_approval
            $table->json('input')->nullable();             // sanitized
            $table->json('tool_calls')->nullable();        // requested tool actions (sanitized)
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();

            $table->index(['customer_id', 'feature']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_run_id')->constrained()->cascadeOnDelete();
            $table->string('role');                        // user | assistant | system
            $table->text('content');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_action_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');                 // restart_service | change_dns | ...
            $table->json('payload')->nullable();           // sanitized action params
            $table->string('status')->default('pending');  // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('feature');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->string('currency', 3)->default('CZK');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_action_approvals');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('ai_prompt_templates');
    }
};
