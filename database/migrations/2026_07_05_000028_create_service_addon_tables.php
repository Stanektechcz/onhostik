<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_addons')) {
            Schema::create('service_addons', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 80)->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('price_czk')->default(0);  // monthly price in minor units (hellers)
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }


        if (!Schema::hasTable('service_addon_subscriptions')) {
            Schema::create('service_addon_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_addon_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->timestamp('activated_at');
                $table->timestamp('cancelled_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['service_id', 'service_addon_id']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_addon_subscriptions');
        Schema::dropIfExists('service_addons');
    }
};
