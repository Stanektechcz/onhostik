<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Onhost\Providers\Contracts\Naming;

/**
 * What a service owns on a shared node is recognised by its name prefix (`oh` + the last six characters of the service
 * id): databases, FTP accounts, the unix agent. Thirty bits — with thousands of sites on one panel two services end up
 * with the same prefix sooner or later, and each of them then lists, changes and drops the other's databases. The prefix
 * becomes a column with a unique index: a service whose id would repeat a prefix gets another id before anything exists
 * (`Service::creating`), and the database refuses what the code would miss. Cancelled services keep theirs — their
 * databases stay on the node through the restore window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('name_prefix', 8)->nullable()->unique();
        });
        $taken = [];
        foreach (DB::table('services')->orderBy('created_at')->orderBy('id')->pluck('id') as $id) {
            $prefix = Naming::prefix((string) $id);
            if (isset($taken[$prefix])) {
                continue; // an existing collision stays without a prefix of its own: `onhost:doctor` names it (area security)
            }
            $taken[$prefix] = true;
            DB::table('services')->where('id', $id)->update(['name_prefix' => $prefix]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique(['name_prefix']);
            $table->dropColumn('name_prefix');
        });
    }
};
