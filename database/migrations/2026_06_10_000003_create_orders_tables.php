<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->string('status', 16)->default('pending')->index();
                $table->char('currency', 3);
                $table->bigInteger('subtotal');                       // minor units
                $table->bigInteger('tax_amount');
                $table->bigInteger('total');
                $table->string('vat_scenario', 16);
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
                $table->index('created_at');
            });
        }


        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pricing_plan_id')->nullable()->constrained()->nullOnDelete();
                $table->string('description');
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->char('currency', 3);
                $table->bigInteger('unit_price');                     // minor units
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->bigInteger('total');
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->string('provisioning_status', 16)->default('pending');
                $table->json('config')->nullable();                   // domain, hostname, game...
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
