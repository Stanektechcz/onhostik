<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['individual', 'company'])->default('individual');
                $table->string('email')->index();
                $table->string('phone', 32)->nullable();
                $table->string('company_name')->nullable();
                $table->string('vat_number', 20)->nullable();          // DIČ
                $table->string('registration_number', 20)->nullable(); // IČ
                $table->char('preferred_currency', 3)->default('CZK');
                $table->char('preferred_locale', 2)->default('cs');
                $table->char('country_code', 2)->default('CZ')->index();
                $table->timestamp('vat_validated_at')->nullable();     // VIES validation
                $table->timestamps();
                $table->softDeletes();
            });
        }


        if (!Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['billing', 'technical'])->default('billing');
                $table->string('street');
                $table->string('city');
                $table->string('zip', 16);
                $table->char('country_code', 2);
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->index(['customer_id', 'type']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
