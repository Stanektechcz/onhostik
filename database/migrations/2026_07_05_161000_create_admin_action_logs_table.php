<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_action_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 80);              // e.g. 'credit_adjustment', 'impersonate_start'
            $table->nullableMorphs('target');          // target_type / target_id
            $table->json('metadata')->nullable();       // arbitrary context (amounts, reason, old/new value)
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');           // append-only; no updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
    }
};
