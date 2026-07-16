<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_apps')) {
            Schema::create('marketplace_apps', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('name', 100);
                $table->string('category', 32)->default('other');  // cms|ecommerce|database|email|other
                $table->text('description')->nullable();
                $table->string('icon', 50)->default('package');    // feather icon name
                $table->unsignedTinyInteger('min_disk_gb')->default(1);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }


        if (!Schema::hasTable('app_installations')) {
            Schema::create('app_installations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->foreignId('marketplace_app_id')->constrained()->cascadeOnDelete();
                $table->string('status', 20)->default('pending');  // pending|installing|installed|failed|removed
                $table->string('version', 30)->nullable();
                $table->timestamp('installed_at')->nullable();
                $table->timestamps();

                $table->unique(['service_id', 'marketplace_app_id']);
                $table->index(['service_id', 'status']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('app_installations');
        Schema::dropIfExists('marketplace_apps');
    }
};
