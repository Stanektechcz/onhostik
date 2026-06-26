<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_commissions', function (Blueprint $table): void {
            // Links commission to a specific payout — null means unpaid/unapproved.
            $table->foreignId('partner_payout_id')
                ->nullable()
                ->after('partner_referral_id')
                ->constrained()
                ->nullOnDelete();

            $table->index('partner_payout_id');
        });
    }

    public function down(): void
    {
        Schema::table('partner_commissions', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Domains\Partner\Models\PartnerPayout::class);
            $table->dropColumn('partner_payout_id');
        });
    }
};
