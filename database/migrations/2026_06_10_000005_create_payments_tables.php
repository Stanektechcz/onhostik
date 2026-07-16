<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->string('method', 24);                          // comgate|bank_transfer|credit
                $table->string('status', 16)->default('pending')->index();
                $table->char('currency', 3);
                $table->bigInteger('amount');                          // minor units
                // IDEMPOTENCY: a gateway transaction can exist exactly once.
                $table->string('gateway_transaction_id')->nullable()->unique();
                $table->json('gateway_response')->nullable();          // sanitized
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
                $table->index('created_at');
            });
        }


        if (!Schema::hasTable('payment_webhook_logs')) {
            Schema::create('payment_webhook_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 24);
                $table->string('event_id')->nullable();
                $table->json('payload')->nullable();                   // sanitized
                $table->json('headers')->nullable();                   // sanitized
                $table->string('ip_address', 45)->nullable();
                $table->boolean('signature_valid')->nullable();
                $table->boolean('processed')->default(false);
                $table->timestamp('processed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['provider', 'event_id']);
                $table->index('created_at');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('payments');
    }
};
