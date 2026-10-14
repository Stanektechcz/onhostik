<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W2-6: a maintenance window can name the nodes (or whole service families) it touches, not only status components and service ids,
 * carries a text per language, and remembers that the reminder before it went out (so it goes out once).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table): void {
            if (! Schema::hasColumn('maintenances', 'affected_nodes')) {
                $table->json('affected_nodes')->nullable();     // node ids: the services on them are the affected ones
            }
            if (! Schema::hasColumn('maintenances', 'affected_families')) {
                $table->json('affected_families')->nullable();  // web | managed | cloud | …: every service of that family
            }
            if (! Schema::hasColumn('maintenances', 'texts')) {
                $table->json('texts')->nullable();              // {cs: "…", en: "…"} — what the customer reads
            }
            if (! Schema::hasColumn('maintenances', 'reminded_at')) {
                $table->timestamp('reminded_at')->nullable();   // the 24 h reminder was sent (or was not due: announced inside the last day)
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table): void {
            $table->dropColumn(['affected_nodes', 'affected_families', 'texts', 'reminded_at']);
        });
    }
};
