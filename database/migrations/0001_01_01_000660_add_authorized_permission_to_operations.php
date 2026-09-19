<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The permission an operation was started under, so a long run can ask again before its next privileged step (H315). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->string('authorized_permission', 80)->nullable()->after('actor_id');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->dropColumn('authorized_permission');
        });
    }
};
