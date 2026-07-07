<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('role')->default('general'); // billing, technical, manager, general
            $table->boolean('receives_invoices')->default(false);
            $table->boolean('receives_notifications')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
    }
};
