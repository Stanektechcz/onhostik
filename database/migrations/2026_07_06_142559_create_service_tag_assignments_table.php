<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tag_assignments', function (Blueprint $table): void {
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('service_tag_id')->constrained('service_tags')->cascadeOnDelete();
            $table->primary(['service_id', 'service_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tag_assignments');
    }
};
