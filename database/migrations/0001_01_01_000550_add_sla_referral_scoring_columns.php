<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §5l: marketplace delivery SLA (overdue warning, refund offer) and referral fraud scoring (score, signals, review). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('refund_offered_at')->nullable();
        });
        Schema::table('referrals', function (Blueprint $table): void {
            $table->unsignedSmallInteger('score')->default(0);
            $table->json('signals')->nullable();
            $table->string('decided_by', 40)->nullable();
            $table->timestamp('decided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropColumn(['score', 'signals', 'decided_by', 'decided_at']);
        });
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn(['overdue_notified_at', 'refund_offered_at']);
        });
    }
};
