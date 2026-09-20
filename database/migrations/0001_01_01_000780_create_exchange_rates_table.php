<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The exchange rate lists of the Czech National Bank. A tax document issued in another currency states its VAT in CZK (§ 29
 * of the Czech VAT act), converted at the rate the bank announced for the day the tax is due (§ 4) — a document in EUR carried
 * neither the rate nor the amount. A list is kept under the day it is valid from (the bank publishes on working days at
 * 14:30; a weekend uses Friday's list); the rate is kept as an integer (CZK × 1 000 000 for `amount` units), never a float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('source', 12)->default('cnb');
            $table->string('currency', 3);
            $table->date('valid_on');
            $table->unsignedInteger('amount')->default(1); // the rate is for this many units: 1 EUR, 100 HUF
            $table->unsignedBigInteger('rate_micro'); // CZK × 1 000 000
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['source', 'currency', 'valid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
