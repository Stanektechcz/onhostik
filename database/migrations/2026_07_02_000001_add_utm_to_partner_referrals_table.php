<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_referrals', function (Blueprint $table): void {
            $table->string('utm_source',   100)->nullable()->after('landing_url');
            $table->string('utm_medium',   100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 200)->nullable()->after('utm_medium');
            $table->string('utm_term',     200)->nullable()->after('utm_campaign');
            $table->string('utm_content',  200)->nullable()->after('utm_term');
        });
    }

    public function down(): void
    {
        Schema::table('partner_referrals', function (Blueprint $table): void {
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);
        });
    }
};
