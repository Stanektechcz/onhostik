<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Why an instance is not active: a maintenance lock, a changed panel address awaiting its probe (audit §5ab, H311/H322). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_instances', function (Blueprint $table): void {
            $table->string('state_reason', 250)->nullable()->after('maintenance_until');
        });
    }

    public function down(): void
    {
        Schema::table('provider_instances', function (Blueprint $table): void {
            $table->dropColumn('state_reason');
        });
    }
};
