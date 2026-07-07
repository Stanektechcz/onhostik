<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('product_name', 100);
            $table->string('license_key')->unique();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->enum('status', ['available', 'assigned', 'expired', 'revoked'])->default('available')->index();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_keys');
    }
};
