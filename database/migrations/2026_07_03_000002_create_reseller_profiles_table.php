<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reseller_profiles')) {
            Schema::create('reseller_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('business_name', 150);
                $table->string('custom_domain', 150)->nullable()->unique();
                $table->decimal('markup_percent', 5, 2)->default(0);
                $table->string('status', 20)->default('pending');
                $table->json('branding')->nullable();
                $table->json('allowed_products')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_profiles');
    }
};
