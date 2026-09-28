<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TASK-0042 (S1-07 red team, restore × I3): an access snapshot remembers the role of whoever made the change it precedes.
 *
 * GrantPolicy::assertMayRestore compared the restorer only with the person's role NOW — and a removed person has none, so an
 * owner's removal of an admin was undone by any other admin, two admins restoring each other for ever. The restorer must now
 * cover `taken_by_role` as well: a removal made by the owner is undone by the owner.
 *
 * One nullable column on a table this same chain created (0001_01_01_000900); no existing row is written. A row with a person
 * in `taken_by` and no role here (made before this column, or by support, or by somebody who was no member) is read as the
 * owner's — fail closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_snapshots', function (Blueprint $table): void {
            $table->string('taken_by_role', 40)->nullable(); // the organization role of `taken_by` at that moment; null for the system
        });
    }

    public function down(): void
    {
        Schema::table('access_snapshots', function (Blueprint $table): void {
            $table->dropColumn('taken_by_role');
        });
    }
};
