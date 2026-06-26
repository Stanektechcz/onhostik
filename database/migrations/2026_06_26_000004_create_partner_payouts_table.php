<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');  // minor units
            $table->string('currency', 3)->default('CZK');
            $table->string('status', 20)->default('requested');
            $table->string('method', 50)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index('partner_profile_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payouts');
    }
};
