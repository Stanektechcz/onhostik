<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('reseller_id')
                ->nullable()
                ->after('user_id')
                ->constrained('reseller_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Domains\Reseller\Models\ResellerProfile::class, 'reseller_id');
            $table->dropColumn('reseller_id');
        });
    }
};
