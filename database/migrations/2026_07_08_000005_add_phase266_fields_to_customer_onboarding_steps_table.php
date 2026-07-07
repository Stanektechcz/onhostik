<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_onboarding_steps', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('step');
            $table->boolean('is_required')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('customer_onboarding_steps', function (Blueprint $table): void {
            $table->dropColumn(['description', 'is_required']);
        });
    }
};
