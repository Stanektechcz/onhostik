<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid()->unique()->after('id');
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('locale', 2)->default('cs')->after('is_active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['uuid', 'is_active', 'locale', 'last_login_at', 'last_login_ip', 'deleted_at']);
        });
    }
};
