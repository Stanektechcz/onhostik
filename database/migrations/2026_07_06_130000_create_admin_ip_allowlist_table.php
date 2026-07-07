<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_ip_allowlist', function (Blueprint $table): void {
            $table->id();
            // IPv4 or IPv6 address/range in CIDR notation, e.g. "10.0.0.0/8" or "1.2.3.4/32"
            $table->string('cidr', 50);
            $table->string('label', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_ip_allowlist');
    }
};
