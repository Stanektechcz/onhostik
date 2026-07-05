<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('trigger', 80)->index();   // e.g. ticket.created, invoice.overdue, service.expiring_soon
            $table->json('conditions')->nullable();    // [{field,operator,value}]
            $table->string('action', 80);             // e.g. assign_ticket, send_email, suspend_service
            $table->json('action_params')->nullable(); // {to, subject, template_id, ...}
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_rule_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 80)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('outcome', 30)->default('ok');  // ok | skipped | error
            $table->text('message')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            // No updated_at — append-only
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rule_logs');
        Schema::dropIfExists('automation_rules');
    }
};
