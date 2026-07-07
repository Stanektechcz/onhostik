<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificate_checks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id')->index();
            $table->string('domain');
            $table->enum('status', ['valid', 'expiring_soon', 'expired', 'invalid', 'unknown'])->default('unknown')->index();
            $table->date('expires_at')->nullable();
            $table->string('issuer')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificate_checks');
    }
};
