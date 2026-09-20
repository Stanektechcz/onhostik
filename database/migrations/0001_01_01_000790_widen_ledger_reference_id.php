<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A top-up, an adjustment and a refund are entered into the ledger with their idempotency key as the reference
 * (`WalletService`), and a key is up to 200 characters — the column took 60. SQLite does not look at the length, PostgreSQL
 * refuses the statement: a manual credit by staff (`staff-credit:wallet.credit:<org>:…`) answered 500 on the production
 * database. The reference holds what the code gives it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table): void {
            $table->string('reference_id', 200)->nullable()->change();
        });
    }

    public function down(): void
    {
        // narrowing would cut references that are already stored: the column stays wide
    }
};
