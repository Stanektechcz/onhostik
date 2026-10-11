<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * G6: a refund of an order payment to its source carries the credit note that corrects the order's document, and a bank refund
 * (a payout finance makes by hand) records who confirmed it was sent and when — until then it stays `pending` and nothing is
 * announced. The bank's payment reference goes to `provider_refund_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->string('credit_note_id', 40)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_by', 60)->nullable();
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropIndex(['state']);
            $table->dropColumn(['credit_note_id', 'confirmed_at', 'confirmed_by']);
        });
    }
};
