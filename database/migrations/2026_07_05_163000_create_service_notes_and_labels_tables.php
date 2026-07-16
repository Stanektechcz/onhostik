<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_labels')) {
            Schema::create('service_labels', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 60);
                $table->string('color', 20)->default('secondary'); // Bootstrap color class suffix
                $table->timestamps();
            });
        }


        if (!Schema::hasTable('service_note_labels')) {
            Schema::create('service_note_labels', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_label_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['service_id', 'service_label_id']);
            });
        }


        Schema::table('services', function (Blueprint $table): void {
            // services has no "notes" column — position hint dropped, it's
            // purely cosmetic (column order never affects Eloquent access).
            $table->text('admin_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('admin_note');
        });
        Schema::dropIfExists('service_note_labels');
        Schema::dropIfExists('service_labels');
    }
};
