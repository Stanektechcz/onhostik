<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('outgoing_webhook_id')->constrained()->cascadeOnDelete();
                $table->string('event', 64);
                $table->json('payload');
                $table->string('status', 16)->default('pending'); // pending | delivered | failed
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->text('response_body')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->index(['outgoing_webhook_id', 'status']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
