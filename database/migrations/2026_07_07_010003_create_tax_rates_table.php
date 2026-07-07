<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2)->index();
            $table->string('name', 100);
            $table->decimal('rate_percent', 5, 2);
            $table->string('type', 50)->default('standard');
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'type', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
