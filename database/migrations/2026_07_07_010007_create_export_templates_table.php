<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('entity_type', 50);
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->enum('format', ['csv', 'xlsx', 'json'])->default('csv');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_shared')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_templates');
    }
};
