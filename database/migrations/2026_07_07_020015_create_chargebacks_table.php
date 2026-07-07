<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chargebacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->bigInteger('amount');
            $table->string('currency', 3)->default('CZK');
            $table->string('reason')->nullable();
            $table->string('gateway_reference')->nullable()->unique();
            $table->enum('status', ['received', 'under_review', 'won', 'lost', 'refunded'])->default('received')->index();
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->date('received_at');
            $table->date('deadline_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chargebacks');
    }
};
