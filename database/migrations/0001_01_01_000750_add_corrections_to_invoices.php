<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A credit note says WHICH line it corrects, and a document knows how much of it was credited. Without the two, the same
 * line could be credited again and again (each time taking the customer's debt down once more), a document with a
 * partial credit note was still asked to be paid in full, and money returned for a part of a paid period had nothing to
 * be computed from. `chargeback_requests.basis` keeps the lines a return was computed from, fixed when the cancellation
 * starts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->string('corrects_line_id', 40)->nullable()->index();
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->bigInteger('credited_minor')->default(0);
        });
        Schema::table('chargeback_requests', function (Blueprint $table): void {
            $table->json('basis')->nullable();
        });

        // credit notes written before this rule: a whole-document one credits every line of its original; a partial one names
        // its lines by the order item they share
        foreach (DB::table('invoices')->where('type', 'credit_note')->whereNotNull('corrects_invoice_id')->orderBy('created_at')->get(['id', 'corrects_invoice_id', 'total_minor']) as $note) {
            $originals = DB::table('invoice_lines')->where('invoice_id', $note->corrects_invoice_id)->orderBy('position')->get(['id', 'position', 'order_item_id', 'total_minor']);
            foreach (DB::table('invoice_lines')->where('invoice_id', $note->id)->orderBy('position')->get(['id', 'position', 'order_item_id', 'total_minor']) as $line) {
                $match = $originals->first(fn ($o) => $line->order_item_id !== null && $o->order_item_id === $line->order_item_id && (int) $o->total_minor === -(int) $line->total_minor)
                    ?? $originals->first(fn ($o) => (int) $o->total_minor === -(int) $line->total_minor);
                if ($match !== null) {
                    DB::table('invoice_lines')->where('id', $line->id)->update(['corrects_line_id' => $match->id]);
                    $originals = $originals->reject(fn ($o) => $o->id === $match->id)->values();
                }
            }
            DB::table('invoices')->where('id', $note->corrects_invoice_id)->increment('credited_minor', max(0, -(int) $note->total_minor));
        }
    }

    public function down(): void
    {
        Schema::table('chargeback_requests', function (Blueprint $table): void {
            $table->dropColumn('basis');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('credited_minor');
        });
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropIndex(['corrects_line_id']);
            $table->dropColumn('corrects_line_id');
        });
    }
};
