<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('billing_addresses')) {
            Schema::create('billing_addresses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('label', 100)->nullable();
                $table->string('company_name')->nullable();
                $table->string('street');
                $table->string('city');
                $table->string('postal_code', 20);
                $table->char('country_code', 2);
                $table->string('vat_number', 50)->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('billing_addresses');
    }
};
