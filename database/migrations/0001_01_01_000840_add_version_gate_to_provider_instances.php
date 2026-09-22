<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A panel on a version nobody verified takes no new orders (Brain cards H511–H530).
 *
 * Every adapter declares the vendor versions it was verified against, and the health probe writes the version each panel
 * reports onto the instance every minute — nothing compared the two. `version_gate` keeps what the platform concluded
 * about the version the panel runs: verified, accepted by an operator (who, when, why, on which checks), the baseline it
 * found on a panel that already carried customers, or held — no new orders go there until it is verified or accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_instances', function (Blueprint $table): void {
            $table->json('version_gate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('provider_instances', function (Blueprint $table): void {
            $table->dropColumn('version_gate');
        });
    }
};
