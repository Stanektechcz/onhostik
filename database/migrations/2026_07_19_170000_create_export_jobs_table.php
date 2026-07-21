<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queued exports (audit L120).
 *
 * The invoice batch export rendered up to 50 PDFs synchronously inside a web
 * request — minutes of CPU behind a request timeout, and if it died the admin
 * got a blank error page with nothing to retry.
 *
 * Exports now become tracked jobs: dispatch, generate on the queue, notify,
 * download. `expires_at` matters because these files contain complete invoice
 * data — they must not accumulate on disk indefinitely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('export_jobs')) {
            return;
        }

        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->id();

            // Who asked — also who is allowed to download the result.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 40);              // e.g. invoice_batch
            $table->string('status', 20)->default('pending'); // pending|running|ready|failed
            $table->string('format', 10)->default('zip');

            // Parameters the job needs (ids to export, filters…).
            $table->json('parameters')->nullable();

            $table->string('file_path', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('row_count')->nullable();

            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // Exports carry full invoice data — they get cleaned up.
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
