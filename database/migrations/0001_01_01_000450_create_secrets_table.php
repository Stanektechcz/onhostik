<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator-managed credentials (`db://<name>` secret references) encrypted with the application key.
 * Used when no OpenBao is deployed; production should still prefer `bao://` (blueprint §27).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table): void {
            $table->string('name', 120)->primary();      // provider_instances/<key>, payments/comgate …
            $table->text('payload');                     // encrypted JSON {key: value}
            $table->json('keys');                        // key names only (never values) for the UI
            $table->string('rotated_by', 40)->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
