<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('game_server_presets')) {
            Schema::create('game_server_presets', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('game_slug', 60)->unique()->comment('Machine identifier, e.g. minecraft, cs2, valheim');
                $table->string('description', 500)->nullable();
                $table->unsignedSmallInteger('nest_id')->default(1)->comment('Pterodactyl nest ID');
                $table->unsignedSmallInteger('egg_id')->default(1)->comment('Pterodactyl egg ID');
                $table->unsignedInteger('default_memory_mb')->default(1024);
                $table->unsignedInteger('default_disk_mb')->default(10240);
                $table->unsignedSmallInteger('default_cpu_limit')->default(100)->comment('CPU % limit');
                $table->unsignedSmallInteger('default_swap_mb')->default(0);
                $table->unsignedSmallInteger('default_io_weight')->default(500);
                $table->string('docker_image', 255)->nullable();
                $table->string('startup', 1000)->nullable()->comment('Startup command override');
                $table->json('environment')->nullable()->comment('Default env vars for the egg (key-value pairs)');
                $table->boolean('is_active')->default(true);
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('game_server_presets');
    }
};
