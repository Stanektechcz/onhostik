<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TASK-0110 (owner decision G-R5): a customer's own installation image, offered only where the ordered VPS plan sells
 * `custom_iso`. One row per image an organization keeps: what it is (name, size, checksum), the clean scan it passed, where the
 * platform keeps the file (`path` on the `custom_isos` disk) and on which hypervisor storages a copy sits (`node_copies`), and
 * the one server it is attached to with the boot order found before it (`previous_boot`), so a detach puts exactly that back.
 *
 * An image belongs to the project of the server it came through (`project_id`): a person whose role covers another project of the
 * organization neither sees, attaches nor deletes it. The quota is the organization's.
 *
 * A new table; no existing row is read or written. Rolling back drops it: the images on the disk and on the hypervisor stay where
 * they are and are removed by hand (docs/runbooks/custom-iso.md), and nothing else in the platform refers to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_isos', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40);
            $table->string('project_id', 40)->nullable();             // the project of the server it was uploaded through: seen and used only there
            $table->string('uploaded_via_service_id', 40)->nullable(); // the server whose plan allowed the upload
            $table->string('uploaded_by', 40)->nullable();
            $table->string('name', 120);                               // the customer's file name, sanitised; never a path
            $table->string('path', 200);                               // on the custom_isos disk: <organization>/<id>.iso
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('scan_result', 20);                         // pending while staging; clean — nothing else is ever kept
            $table->timestamp('scanned_at')->nullable();
            $table->string('state', 20)->default('ready');             // staging (an upload in flight, its bytes reserved) | ready | deleted
            $table->string('attached_service_id', 40)->nullable();
            $table->timestamp('attached_at')->nullable();
            $table->json('previous_boot')->nullable();                 // {iso, boot} as found before the attach
            $table->json('node_copies')->nullable();                   // [{instance, node, volume}]
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'project_id', 'state']);
            $table->index('attached_service_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_isos');
    }
};
