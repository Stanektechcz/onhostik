<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('credit_transactions')) {
            Schema::create('credit_transactions', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->string('type', 16);
                $table->char('currency', 3);
                $table->bigInteger('amount');                          // SIGNED minor units
                $table->bigInteger('balance_after');                   // running balance
                $table->string('description');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();
                // NO updated_at — append-only by design.

                $table->index(['customer_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }


        // DB-level append-only enforcement (MySQL/MariaDB).
        // Model guards exist too, but raw SQL must also be blocked.
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER credit_transactions_no_update
                BEFORE UPDATE ON credit_transactions
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Credit ledger is append-only: UPDATE forbidden.';
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER credit_transactions_no_delete
                BEFORE DELETE ON credit_transactions
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Credit ledger is append-only: DELETE forbidden.';
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS credit_transactions_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS credit_transactions_no_delete');
        }

        Schema::dropIfExists('credit_transactions');
    }
};
