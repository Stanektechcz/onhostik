<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_chat_conversations')) {
            Schema::create('support_chat_conversations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status', ['bot', 'waiting_agent', 'agent_active', 'closed'])->default('bot')->index();
                $table->string('subject')->nullable();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
            });
        }

        if (! Schema::hasTable('support_chat_messages')) {
            Schema::create('support_chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('support_chat_conversations')->cascadeOnDelete();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('role', ['user', 'bot', 'agent', 'system'])->index();
                $table->text('body');
                $table->json('meta')->nullable(); // bot suggestions + links, agent flags
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_chat_messages');
        Schema::dropIfExists('support_chat_conversations');
    }
};
