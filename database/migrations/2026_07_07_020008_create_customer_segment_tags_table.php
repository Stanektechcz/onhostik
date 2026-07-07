<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_segment_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('color', 7)->default('#6c757d');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_segment_tag_pivot', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('segment_tag_id');
            $table->timestamps();

            $table->primary(['customer_id', 'segment_tag_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('segment_tag_id')->references('id')->on('customer_segment_tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segment_tag_pivot');
        Schema::dropIfExists('customer_segment_tags');
    }
};
