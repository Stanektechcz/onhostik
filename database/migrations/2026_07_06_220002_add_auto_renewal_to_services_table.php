<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'renewal_notice_days')) {
                $table->tinyInteger('renewal_notice_days')->default(7)->after('auto_renew');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'renewal_notice_days')) {
                $table->dropColumn('renewal_notice_days');
            }
        });
    }
};
