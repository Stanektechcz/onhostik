<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_tags')) {
            Schema::create('service_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('color', 7)->default('#6c757d');
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_tags');
    }
};
