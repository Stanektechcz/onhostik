<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 2026_06_28 migration seeded pterodactyl/proxmox vault rows with a
 * plain-text '{}' in the encrypted-at-rest credentials column, which made
 * every read throw DecryptException. NULL it out — the model getter treats
 * NULL as "no credentials". Rows already re-saved through the model (and
 * therefore properly encrypted) are untouched.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('integration_settings')
            ->whereIn('provider', ['pterodactyl', 'proxmox'])
            ->where('credentials', '{}')
            ->update(['credentials' => null]);
    }

    public function down(): void
    {
        // Nothing to restore — the previous state was invalid.
    }
};
