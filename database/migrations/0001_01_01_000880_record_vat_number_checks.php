<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TASK-0031 (D31.2): the VIES check is recorded as evidence on the organization and in vat_validations.
 *
 * organizations.vat_status has ONE vocabulary: unknown | valid | invalid. It is written only by RecordVatCheckHandler (a
 * VIES or format verdict) and the staff override; `payer` and `not_registered` were never written by production code.
 * No row is changed here (owner rule: no mass write to existing customers): a stored value outside the vocabulary is
 * read as `unknown` by VatStanding, and rows from before the check (vat_status_source NULL) keep today's money until
 * `onhost:vat:verify --apply` re-checks them.
 *
 * All columns are nullable additions: old code ignores them, new code reads NULL as "never checked". Row volume is the
 * number of organizations; the index serves the doctor and the operator command (status × country).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->timestamp('vat_checked_at')->nullable();
            $table->string('vat_checked_number', 20)->nullable();        // the number the recorded status is about (with its prefix)
            $table->string('vat_consultation_number', 80)->nullable();   // VIES requestIdentifier: the evidence for reverse charge
            $table->string('vat_validation_id', 40)->nullable();         // the vat_validations row behind the status
            $table->string('vat_status_source', 12)->nullable();         // vies | format | staff; NULL = written before the check existed
            $table->timestamp('vat_override_until')->nullable();         // a staff override counts until then
            $table->index(['vat_status', 'country'], 'organizations_vat_status_country_index');
        });

        Schema::table('vat_validations', function (Blueprint $table): void {
            $table->string('status', 12)->nullable();            // valid | invalid (an unknown answer is never recorded)
            $table->string('reason', 40)->nullable();            // what started the check: vat_id_changed | checkout | recheck | operator | staff
            $table->text('note')->nullable();
            $table->string('evidence', 1000)->nullable();        // what a staff override relied on
            $table->string('actor', 60)->nullable();
            $table->string('requester_vat_id', 20)->nullable();  // our own VAT ID the consultation number was issued to
            $table->string('country_code', 2)->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vat_validations', function (Blueprint $table): void {
            $table->dropColumn(['status', 'reason', 'note', 'evidence', 'actor', 'requester_vat_id', 'country_code', 'expires_at']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex('organizations_vat_status_country_index');
        });
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['vat_checked_at', 'vat_checked_number', 'vat_consultation_number', 'vat_validation_id', 'vat_status_source', 'vat_override_until']);
        });
    }
};
