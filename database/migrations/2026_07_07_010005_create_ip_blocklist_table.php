<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_blocklist', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('blocked_by')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_blocklist');
    }
};
