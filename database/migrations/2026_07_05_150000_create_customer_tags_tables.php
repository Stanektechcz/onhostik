<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_tags')) {
            Schema::create('customer_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('color', 20)->default('#6c757d'); // hex or CSS class
                $table->text('description')->nullable();
                $table->unsignedInteger('customer_count')->default(0);
                $table->timestamps();
            });
        }


        if (!Schema::hasTable('customer_customer_tag')) {
            Schema::create('customer_customer_tag', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_tag_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamps();

                $table->unique(['customer_id', 'customer_tag_id']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('customer_customer_tag');
        Schema::dropIfExists('customer_tags');
    }
};
