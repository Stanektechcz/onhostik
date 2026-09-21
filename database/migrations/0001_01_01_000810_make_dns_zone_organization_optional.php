<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reverse zone is the operator's, not a customer's (audit §5ae): `2.0.192.in-addr.arpa` holds the PTR records of
 * every address in that range, whoever rents them this month. Until now every zone had to belong to an organization,
 * so there was nowhere to put one — and no PTR the platform published could exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dns_zones', function (Blueprint $table): void {
            $table->string('organization_id', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dns_zones', function (Blueprint $table): void {
            $table->string('organization_id', 40)->nullable(false)->change();
        });
    }
};
