<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified, polymorphic internal notes (audit 75).
 *
 * Customers and services grew their own bespoke note tables; orders and
 * invoices had none. This is the single reusable mechanism any entity can adopt
 * via the HasEntityNotes trait — one table, one component, one controller —
 * instead of a fourth bespoke copy. The pre-existing customer/service notes are
 * left in place; new adopters use this.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entity_notes')) {
            return;
        }

        Schema::create('entity_notes', function (Blueprint $table): void {
            $table->id();
            // Polymorphic owner — order, invoice, or anything that adopts the trait.
            $table->morphs('notable');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            // The hot query is "notes for this entity, pinned first"; morphs()
            // already indexes (notable_type, notable_id).
            $table->index('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_notes');
    }
};
