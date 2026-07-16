<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reseller_payout_requests')) {
            Schema::create('reseller_payout_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('reseller_profile_id')->index();
                $table->bigInteger('amount');
                $table->string('currency', 3)->default('CZK');
                $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending')->index();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->datetime('processed_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->foreign('reseller_profile_id')->references('id')->on('reseller_profiles')->cascadeOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_payout_requests');
    }
};
