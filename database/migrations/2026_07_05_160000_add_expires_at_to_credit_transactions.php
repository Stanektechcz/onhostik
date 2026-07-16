<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_transactions', static function (Blueprint $table): void {
            // credit_transactions has no "amount_haler" column — that name
            // belongs to invoice_partial_payments. Real column is "amount".
            $table->timestamp('expires_at')->nullable()->after('amount')->index();
        });

        // Tracks which deposit transactions already got a reminder, to prevent duplicates
        if (!Schema::hasTable('credit_expiry_reminders')) {
            Schema::create('credit_expiry_reminders', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('credit_transaction_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('days_before'); // 30 | 7
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->unique(['credit_transaction_id', 'days_before']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('credit_expiry_reminders');
        Schema::table('credit_transactions', static function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
    }
};
