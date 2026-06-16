<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `purpose` distinguishes what a paid invoice triggers downstream:
     *  - order        → order transition + provisioning (default)
     *  - credit_topup → credit ledger deposit, no provisioning
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('purpose')->default('order')->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('purpose');
        });
    }
};
