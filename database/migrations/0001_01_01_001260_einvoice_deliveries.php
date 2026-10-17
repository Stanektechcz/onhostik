<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W7-1 (Peppol layer 1): one e-invoice delivery per invoice. The base finance migration created an `einvoice_deliveries`
 * placeholder that nothing ever wrote (no adapter existed); it is replaced by the layer-1 shape. `state` queued | skipped |
 * sent | delivered | rejected | failed; `error_code` is our own short code only, never a provider's text, and nothing of the
 * document or the recipient is stored. down() puts the placeholder back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('einvoice_deliveries');
        Schema::create('einvoice_deliveries', function (Blueprint $table): void {
            $table->string('id', 40)->primary(); // a UUID (HasUuids); varchar like every other key, so the width guard can measure it
            $table->string('organization_id', 40)->index();
            $table->string('invoice_id', 40)->unique();
            $table->string('invoice_number', 64);
            $table->string('driver', 32);
            $table->string('delivery_id', 190)->nullable();
            $table->string('state', 16)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['state', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einvoice_deliveries');
        Schema::create('einvoice_deliveries', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('invoice_id', 40)->index();
            $table->string('provider', 20);
            $table->string('delivery_id', 120)->nullable();
            $table->string('state', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }
};
