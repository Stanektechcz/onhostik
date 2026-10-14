<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One Penpot per service (owner decision H-R7, TASK-0130). The cart, the checkout and the delivery each refuse a second Penpot
 * for a service, but two workers delivering two paid orders at the same instant read "none yet" both. The database is the
 * last word: `penpot_parent_id` names the service a Penpot belongs to, and a partial unique index allows one row per parent
 * among the Penpots that are not gone (TERMINATED, FAILED). The same SQL works on PostgreSQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('penpot_parent_id', 40)->nullable();
        });
        foreach (DB::table('services')->where('family', 'penpot')->get(['id', 'tags']) as $row) {
            $parent = (string) (json_decode((string) $row->tags, true)['parent_service_id'] ?? '');
            if ($parent !== '') {
                DB::table('services')->where('id', $row->id)->update(['penpot_parent_id' => $parent]);
            }
        }
        DB::statement("CREATE UNIQUE INDEX services_one_penpot_per_parent ON services (penpot_parent_id) WHERE penpot_parent_id IS NOT NULL AND state NOT IN ('TERMINATED', 'FAILED')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS services_one_penpot_per_parent');
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('penpot_parent_id');
        });
    }
};
