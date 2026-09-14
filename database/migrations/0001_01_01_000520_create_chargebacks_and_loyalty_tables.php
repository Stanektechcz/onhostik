<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chargebacks (a customer asks to leave a service early; support decides; on the cancellation a configurable share of
 * the unused, already paid period comes back as credit) and the loyalty programme (points per action, levels with a
 * promo-credit reward, badges).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chargeback_requests', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('service_id', 40)->index();
            $table->string('requested_by', 40)->nullable();
            $table->string('state', 16)->default('requested'); // requested | approved | rejected | cancelling | refunded | withdrawn
            $table->text('reason')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('decided_by', 40)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedSmallInteger('percent')->default(70);   // the share in force when the request was approved
            $table->string('currency', 3)->default('CZK');
            $table->bigInteger('unused_minor')->default(0);         // unused value of the paid period at the cancellation
            $table->bigInteger('refund_minor')->default(0);         // what came back as credit
            $table->string('operation_id', 40)->nullable();         // the terminate operation
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'state']);
        });

        Schema::create('loyalty_points', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('rule', 40);                             // order.paid | payment.on_time | mfa.enabled | backups.enabled | ...
            $table->string('reference', 120);                       // what earned it (order id, month, ...) — one award per rule+reference
            $table->integer('points');
            $table->string('note', 200)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'rule', 'reference']);
        });

        Schema::create('loyalty_badges', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('badge', 40);
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->unique(['organization_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_badges');
        Schema::dropIfExists('loyalty_points');
        Schema::dropIfExists('chargeback_requests');
    }
};
