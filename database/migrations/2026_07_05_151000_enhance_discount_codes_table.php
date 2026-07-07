<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table): void {
            $table->unsignedInteger('max_uses_per_customer')->nullable()->after('max_uses');
            $table->unsignedBigInteger('min_order_haler')->default(0)->after('max_uses_per_customer');
            $table->unsignedBigInteger('total_saved_haler')->default(0)->after('min_order_haler');
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table): void {
            $table->dropColumn(['max_uses_per_customer', 'min_order_haler', 'total_saved_haler']);
        });
    }
};
