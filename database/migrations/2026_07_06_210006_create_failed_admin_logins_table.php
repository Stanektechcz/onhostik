<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_admin_logins', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255);
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('attempted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_admin_logins');
    }
};
