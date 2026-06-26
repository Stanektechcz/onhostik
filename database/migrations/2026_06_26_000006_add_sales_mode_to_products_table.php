<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // self_service  — fully orderable via checkout
            // contact_only  — quote/contact form only
            // coming_soon   — not yet available, no order flow
            // inactive      — disabled, hide from catalog
            $table->string('sales_mode', 20)->default('self_service')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('sales_mode');
        });
    }
};
