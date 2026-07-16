<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('provisioning_audit_logs')) {
            Schema::create('provisioning_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 100);
                $table->json('payload')->nullable();
                $table->string('driver', 50)->nullable();
                $table->boolean('success')->default(true);
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_audit_logs');
    }
};
