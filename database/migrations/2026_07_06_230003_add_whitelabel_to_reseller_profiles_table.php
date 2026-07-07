<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_profiles', function (Blueprint $table): void {
            $table->string('support_email', 255)->nullable()->after('branding');
            $table->string('support_phone', 30)->nullable()->after('support_email');
            $table->string('panel_title', 100)->nullable()->after('support_phone');
        });
    }

    public function down(): void
    {
        Schema::table('reseller_profiles', function (Blueprint $table): void {
            $table->dropColumn(['support_email', 'support_phone', 'panel_title']);
        });
    }
};
