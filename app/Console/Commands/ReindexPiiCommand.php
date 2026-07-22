<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Shared\Support\BlindIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Backfills encryption + blind index for existing PII rows (audit 31).
 *
 * When the phone column switches to encrypted-at-rest, rows written before the
 * switch still hold plaintext (which would then fail to decrypt) and have no
 * blind index. This walks the table using RAW values (bypassing the model cast)
 * and, for each row, encrypts it if it is still plaintext and (re)computes the
 * index. Idempotent — safe to run repeatedly.
 */
final class ReindexPiiCommand extends Command
{
    protected $signature = 'pii:reindex';

    protected $description = 'Encrypt + blind-index existing customer PII (phone)';

    public function handle(): int
    {
        $updated = 0;

        DB::table('customers')
            ->select('id', 'phone')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$updated): void {
                foreach ($rows as $row) {
                    $raw = (string) $row->phone;

                    // Already encrypted? decrypt to get the plaintext to index.
                    try {
                        $plain = Crypt::decryptString($raw);
                        $encrypted = $raw; // leave the stored ciphertext as-is
                    } catch (Throwable) {
                        // Plaintext (pre-switch row) — encrypt it now.
                        $plain = $raw;
                        $encrypted = Crypt::encryptString($raw);
                    }

                    DB::table('customers')->where('id', $row->id)->update([
                        'phone'      => $encrypted,
                        'phone_bidx' => BlindIndex::of($plain),
                    ]);

                    $updated++;
                }
            });

        $this->info("Reindexed {$updated} customer phone record(s).");

        return self::SUCCESS;
    }
}
