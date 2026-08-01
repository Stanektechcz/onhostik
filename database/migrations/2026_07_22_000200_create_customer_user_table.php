<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer sub-accounts: a membership pivot so several login users can access
 * one customer account with a role (owner / member).
 *
 * Ownership (customers.user_id) is preserved and stays canonical — every
 * existing owner is backfilled here as an 'owner' membership, so nothing about
 * the current one-user-per-customer flow changes. Members are added on top.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_user')) {
            Schema::create('customer_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 20)->default('member'); // owner | member
                $table->timestamps();

                $table->unique(['customer_id', 'user_id']);
            });
        }

        // Backfill: every current owner becomes an 'owner' membership.
        DB::table('customers')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(500, function ($customers): void {
                $now  = now();
                $rows = [];

                foreach ($customers as $customer) {
                    $rows[] = [
                        'customer_id' => $customer->id,
                        'user_id'     => $customer->user_id,
                        'role'        => 'owner',
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }

                if ($rows !== []) {
                    // Idempotent: skip rows that already exist.
                    DB::table('customer_user')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user');
    }
};
