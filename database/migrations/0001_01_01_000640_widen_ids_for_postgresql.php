<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns SQLite accepted loosely and PostgreSQL refuses (found by the CI suite on PostgreSQL 16): a scheduler node's
 * panel id is a string for Proxmox (`prg2-n1`) and a number for Pterodactyl; the ledger reference on a rated usage row
 * (`ledger:usage:<ulid>`) is longer than 40 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->string('remote_id', 80)->nullable()->change();
        });
        Schema::table('rated_usage', function (Blueprint $table): void {
            $table->string('charged_transaction_id', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rated_usage', function (Blueprint $table): void {
            $table->string('charged_transaction_id', 40)->nullable()->change();
        });
    }
};
