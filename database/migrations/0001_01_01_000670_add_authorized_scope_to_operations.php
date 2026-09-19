<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scope the authorizing permission was checked at (H315). Staff hold their roles globally, customers on the
 * organization or the resource: a run asks again at the same scope the command bus asked, never a narrower one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->string('authorized_scope', 16)->nullable()->after('authorized_permission'); // global | null = the run's own service or organization
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->dropColumn('authorized_scope');
        });
    }
};
