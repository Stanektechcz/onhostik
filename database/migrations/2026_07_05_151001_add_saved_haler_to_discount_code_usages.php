<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_code_usages', function (Blueprint $table): void {
            $table->unsignedBigInteger('saved_haler')->default(0)->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('discount_code_usages', function (Blueprint $table): void {
            $table->dropColumn('saved_haler');
        });
    }
};
