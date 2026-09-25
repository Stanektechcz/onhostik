<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A consumer's withdrawal from a distance contract within fourteen days (odstoupení od smlouvy, TASK-0025).
 *
 * The row is the evidence of the notice — when it was sent, through which channel, what the contract was and what came
 * back — so it is never deleted. One row per order line (`subject_key`: `item:<order item>` for a delivered service,
 * `order:<order>` for a paid order nothing of which was delivered): the unique key is what makes a second request, a
 * retried event or a staff record of the same letter find the first one instead of refunding twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40);
            $table->string('order_id', 40)->index();
            $table->string('order_item_id', 40)->nullable();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('subject_key', 100)->unique();              // item:<order item id> | order:<order id>
            $table->string('channel', 16);                              // panel | staff
            $table->string('requested_by', 40)->nullable();             // the customer's user, or the staff member who recorded a letter
            $table->timestamp('sent_at');                               // when the consumer sent the notice: the deadline is kept by sending it in time
            $table->timestamp('contract_start_at');                     // orders.placed_at
            $table->timestamp('deadline_at');
            $table->string('customer_class_at_order', 8)->default('b2c');
            $table->string('refund_consent_id', 40)->nullable();        // the consumer's express agreement to a refund to the account credit
            $table->text('statement')->nullable();
            $table->string('state', 16)->default('suspending');         // suspending | refunded | terminating | completed
            $table->string('currency', 3)->default('CZK');
            $table->bigInteger('refund_minor')->default(0);
            $table->bigInteger('to_credit_minor')->default(0);
            $table->bigInteger('off_document_minor')->default(0);
            $table->json('basis')->nullable();                          // the paid lines fixed at the notice; after the refund also the credit notes
            $table->string('suspend_operation_id', 40)->nullable();
            $table->string('terminate_operation_id', 40)->nullable();
            $table->string('error', 200)->nullable();                   // the last refusal of a step, retried by onhost:withdrawals:finish
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
