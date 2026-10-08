<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L-06 (TASK-0144): a consumer withdraws without having to agree to a refund to the credit. `refund_method` is `credit` when the
 * consumer agreed (every withdrawal before this migration did — the panel refused it otherwise) and `source` when the money goes
 * back the way it was paid. `payout_minor` is what an order payment (a card, a transfer) must get back and finance pays out;
 * `paid_out_minor` what was paid out so far; `payout_payment_id` the payment it goes back to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->string('refund_method', 8)->default('credit');
            $table->bigInteger('payout_minor')->default(0);
            $table->bigInteger('paid_out_minor')->default(0);
            $table->string('payout_payment_id', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropColumn(['refund_method', 'payout_minor', 'paid_out_minor', 'payout_payment_id']);
        });
    }
};
