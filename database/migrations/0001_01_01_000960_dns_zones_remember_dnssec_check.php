<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the last nightly look at the parent's DS record found for a signed zone (state, the DS the parent holds, when). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dns_zones', function (Blueprint $table): void {
            $table->json('dnssec_check')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dns_zones', function (Blueprint $table): void {
            $table->dropColumn('dnssec_check');
        });
    }
};
