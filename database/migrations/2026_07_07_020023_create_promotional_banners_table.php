<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('promotional_banners')) {
            Schema::create('promotional_banners', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 100);
                $table->text('body');
                $table->string('cta_text', 60)->nullable();
                $table->string('cta_url')->nullable();
                $table->enum('type', ['info', 'success', 'warning', 'danger'])->default('info');
                $table->enum('placement', ['panel_top', 'panel_dashboard', 'admin_top'])->default('panel_top');
                $table->boolean('is_active')->default(false)->index();
                $table->boolean('is_dismissible')->default(true);
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('promotional_banners');
    }
};
