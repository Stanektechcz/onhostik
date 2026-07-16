<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('emergency_contacts')) {
            Schema::create('emergency_contacts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('relationship', 60)->nullable();
                $table->boolean('notify_on_suspension')->default(true);
                $table->boolean('notify_on_expiry')->default(true);
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
    }
};
