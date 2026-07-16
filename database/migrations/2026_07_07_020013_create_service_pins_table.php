<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_pins')) {
            Schema::create('service_pins', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id')->unique();
                $table->string('pin_hash');
                $table->text('hint')->nullable();
                $table->timestamp('set_at');
                $table->timestamps();

                $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_pins');
    }
};
