<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('currency', 3)->comment('ISO-4217 non-CZK currency code');
            $table->decimal('rate', 10, 4)->comment('Units of CZK per 1 unit of this currency');
            $table->enum('source', ['manual', 'cnb'])->default('manual');
            $table->date('valid_from');
            $table->timestamps();

            $table->unique(['currency', 'valid_from']);
            $table->index(['currency', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
