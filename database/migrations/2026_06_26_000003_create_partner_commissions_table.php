<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('partner_commissions')) {
            Schema::create('partner_commissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('partner_referral_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('amount');  // minor units (haléře/cents)
                $table->string('currency', 3)->default('CZK');
                $table->decimal('rate_percent', 5, 2);
                $table->string('status', 20)->default('pending');
                $table->timestamp('eligible_at')->nullable();  // hold period expiry
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                // Idempotency: one commission per invoice (prevents InvoicePaid replay duplicates)
                $table->unique('invoice_id');
                $table->index('partner_profile_id');
                $table->index('status');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('partner_commissions');
    }
};
