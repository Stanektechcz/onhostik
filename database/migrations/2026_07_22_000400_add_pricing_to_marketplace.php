<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid marketplace add-ons: a one-time price on the app, and a record of what
 * was actually charged on each installation. Price 0 = free (unchanged
 * behaviour). Charged from the customer's credit at install time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_apps', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_apps', 'price_halere')) {
                $table->unsignedInteger('price_halere')->default(0)->after('min_disk_gb');
            }
        });

        Schema::table('app_installations', function (Blueprint $table): void {
            if (! Schema::hasColumn('app_installations', 'price_halere_paid')) {
                $table->unsignedInteger('price_halere_paid')->nullable()->after('version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_apps', function (Blueprint $table): void {
            if (Schema::hasColumn('marketplace_apps', 'price_halere')) {
                $table->dropColumn('price_halere');
            }
        });

        Schema::table('app_installations', function (Blueprint $table): void {
            if (Schema::hasColumn('app_installations', 'price_halere_paid')) {
                $table->dropColumn('price_halere_paid');
            }
        });
    }
};
