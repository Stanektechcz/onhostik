<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maintenance_windows')) {
            Schema::create('maintenance_windows', static function (Blueprint $table): void {
                $table->id();
                $table->string('title', 200);
                $table->text('message');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->boolean('show_on_frontend')->default(true);
                $table->boolean('show_on_admin')->default(true);
                $table->string('color', 20)->default('warning'); // warning|danger|info|primary
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['starts_at', 'ends_at', 'is_active']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
    }
};
