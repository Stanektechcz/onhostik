<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow standalone monitors (SSL checks for external domains not attached to any hosted service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_id')->nullable(false)->change();
        });
    }
};
