<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid work on a ticket (Brain card H29). Support covers the infrastructure; administering the customer's system,
 * fixing their application or writing something for them is work outside the plan. Such work is offered with a price,
 * the customer approves the price, and only an approved offer can be billed — for exactly that price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_work_offers', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('ticket_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('service_id', 40)->nullable();
            $table->string('scope', 24);                         // administration | application | development
            $table->string('description', 1000);
            $table->unsignedInteger('minutes')->nullable();      // the estimate the price is based on
            $table->unsignedBigInteger('price_net_minor');       // what was offered and what will be billed, without tax
            $table->string('currency', 3);
            $table->string('state', 16)->default('proposed')->index(); // proposed | approved | declined | withdrawn | expired | completed
            $table->string('proposed_by', 40)->nullable();       // staff user
            $table->string('decided_by', 40)->nullable();        // the customer's user who approved or declined
            $table->string('decision_note', 500)->nullable();
            $table->string('invoice_id', 40)->nullable();
            $table->string('payment', 16)->nullable();           // wallet | invoice — how the completed work was settled
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_work_offers');
    }
};
