<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssh_keys')) {
            Schema::create('ssh_keys', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->text('public_key');
                $table->string('fingerprint', 100)->nullable();
                $table->timestamps();

                $table->index('customer_id');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_keys');
    }
};
