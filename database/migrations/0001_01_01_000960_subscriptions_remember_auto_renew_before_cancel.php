<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TASK-0094 (E10 end to end): taking back "cancel at period end" gave the customer a service that still ended.
 *
 * Cancelling at the end of the period switches `auto_renew` off, and revoking the cancellation switched `cancel_at_period_end`
 * off and left `auto_renew` as the cancellation had set it — off. The renewal pass ends a subscription that does not renew, so the
 * customer who changed their mind lost the service at the period end all the same, with nothing in the panel saying so.
 *
 * The revoke needs what the customer had chosen before: one nullable boolean on the subscription, written by the cancellation and
 * read (and cleared) by the revocation. No existing row is written; a row without it keeps today's behaviour (auto-renew stays off,
 * never switched on against the holder's choice — TASK-0025/0027).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('auto_renew_before_cancel')->nullable(); // auto_renew as it was when "cancel at period end" was asked for; null = not cancelled by that route
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('auto_renew_before_cancel');
        });
    }
};
