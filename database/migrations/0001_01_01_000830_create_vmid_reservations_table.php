<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The number of a new VM is never one this platform gave before (H488, H505).
 *
 * Proxmox hands out the lowest free number, so the number of a VPS that was just purged went to the next order — whose
 * binding then collided with the cancelled service's, and whose backups would have joined the predecessor's group on the
 * backup server. A number is held here, per cluster, from the moment an operation picks it: the unique key is what stops two
 * orders running at once from cloning into the same number, and the highest number held is the floor for the next one.
 * `burned_at` marks a number somebody else took at the panel before the clone arrived: it is never tried again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vmid_reservations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('provider_instance_id', 40);
            $table->unsignedInteger('vmid');
            $table->string('service_id', 40)->nullable()->index();
            $table->string('operation_id', 40)->nullable()->index();
            $table->timestamp('burned_at')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamps();
            $table->unique(['provider_instance_id', 'vmid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vmid_reservations');
    }
};
