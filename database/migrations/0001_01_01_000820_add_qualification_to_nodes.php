<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A node nobody has qualified sells nothing (H471).
 *
 * `nodes.state` defaulted to `active`, so the moment a node appeared — discovered from a panel, bootstrapped by the
 * capacity planner — it was a node the scheduler could put a paying customer on. Nobody had checked its clock, what
 * it can resolve, what it can reach, or how much room its system disk has.
 *
 * `qualified_at` says when it last passed, `qualification` keeps what was checked and what was found. Nodes that are
 * already active keep their state: this only changes what happens to nodes discovered from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->timestamp('qualified_at')->nullable()->after('last_seen_at');
            $table->json('qualification')->nullable()->after('qualified_at');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropColumn(['qualified_at', 'qualification']);
        });
    }
};
