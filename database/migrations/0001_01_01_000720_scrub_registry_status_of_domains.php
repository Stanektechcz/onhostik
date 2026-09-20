<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Onhost\Platform\Redaction\Redactor;

/**
 * `domains.registry_status` kept the registrar's answer as it came, and Subreg's `Info_Domain` carries the transfer code
 * (`authid`). New rows are filtered by the model; this cleans what was stored before.
 */
return new class extends Migration
{
    public function up(): void
    {
        $redactor = new Redactor;
        DB::table('domains')->whereNotNull('registry_status')->orderBy('id')->chunkById(200, function ($rows) use ($redactor): void {
            foreach ($rows as $row) {
                $stored = json_decode((string) $row->registry_status, true);
                if (! is_array($stored)) {
                    continue;
                }
                $clean = $redactor->redact($stored);
                if ($clean !== $stored) {
                    DB::table('domains')->where('id', $row->id)->update(['registry_status' => json_encode($clean)]);
                }
            }
        }, 'id');
    }

    public function down(): void
    {
        // what was removed is a secret; there is nothing to put back
    }
};
