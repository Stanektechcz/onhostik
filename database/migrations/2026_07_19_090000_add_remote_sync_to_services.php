<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted result of reconciling a Service against its backend panel
 * (aaPanel / Proxmox / Pterodactyl / WEDOS).
 *
 * Until now the live status was fetched on demand and thrown away, so a
 * service that was billed but never actually created in the panel looked
 * healthy in the UI. These columns keep the last verdict so drift is
 * visible and alertable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable();
            }
            if (! Schema::hasColumn('services', 'sync_state')) {
                $table->string('sync_state', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('services', 'sync_message')) {
                $table->string('sync_message', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table): void {
            foreach (['last_synced_at', 'sync_state', 'sync_message'] as $column) {
                if (Schema::hasColumn('services', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
