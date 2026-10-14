<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * G3 (owner decision G-R2): loyalty points can be redeemed for a discount.
 *
 * - `carts.loyalty_points` + `carts.loyalty_organization_id`: what the customer asked to redeem on their cart, and from which
 *   organization (`loyalty.redeem`, an explicit action through the command bus). Null on every existing cart: nothing is redeemed
 *   that nobody asked for, and a choice made for one organization is never applied to another one's order.
 * - `loyalty_redemptions`: one row per order that redeems points. Reserved when the order is placed, consumed when it is paid,
 *   released when it is cancelled unpaid; `returned_points` counts what credit notes gave back. New, empty table.
 * - `loyalty_expiry_notices`: the one warning per organization and month in which its points expire (the expiry job's
 *   idempotency). New, empty table.
 *
 * Nothing existing is rewritten; old code ignores the new column and tables. PostgreSQL: widths are the platform's id width (40).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->integer('loyalty_points')->nullable(); // points the customer asked to redeem on this cart; null = none
            $table->string('loyalty_organization_id', 40)->nullable(); // whose points: a person in two organizations chose for one of them
        });

        Schema::create('loyalty_redemptions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('order_id', 40)->unique();
            $table->string('quote_id', 40)->nullable()->unique();
            $table->string('user_id', 40)->nullable();
            $table->string('state', 16);                           // reserved | consumed | released
            $table->integer('points');                             // points the order redeems
            $table->bigInteger('value_minor');                     // the discount (net) in minor units of the order currency
            $table->string('currency', 3);
            $table->bigInteger('rate_micro')->nullable();          // the bank rate a foreign-currency value was converted at (CZK × 1 000 000)
            $table->integer('rate_amount')->nullable();
            $table->date('rate_valid_on')->nullable();
            $table->integer('returned_points')->default(0);        // what credit notes gave back so far
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'state']);
        });

        Schema::create('loyalty_expiry_notices', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40);
            $table->string('window', 7);                           // YYYY-MM: the month the warned points expire in
            $table->integer('points');
            $table->timestamp('notified_at');
            $table->timestamps();
            $table->unique(['organization_id', 'window']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_expiry_notices');
        Schema::dropIfExists('loyalty_redemptions');
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropColumn(['loyalty_points', 'loyalty_organization_id']);
        });
    }
};
