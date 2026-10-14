<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
 * P12 (phase Q): the metrics behind the operations alerts — managed certificates expiring within 7 days or expired, the newest
 * imported bank statement line and the unmatched ones, and the free share of the application's disk.
 */

it('exports certificates, the bank import and the disk for the operations alerts', function () {
    $body = fn () => (string) $this->get('/metrics', ['REMOTE_ADDR' => '127.0.0.1'])->assertOk()->getContent();

    $empty = $body();
    expect($empty)->toContain('onhost_certificates_expiring_7d 0')->toContain('onhost_certificates_expired 0')
        ->toContain('onhost_bank_statement_last_line_timestamp 0')->toContain('onhost_bank_unmatched_lines 0')
        ->toMatch('/onhost_disk_free_ratio\{path="app"\} 0\.\d+/');

    $cert = fn (string $id, $expires, string $state = 'issued') => DB::table('managed_certificates')->insert(array_filter([
        'id' => $id, 'state' => $state, 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
        'service_id' => 'srv_p12', 'organization_id' => 'org_p12', 'domains' => json_encode(['p12.example']),
    ], fn ($v) => $v !== null));
    $cert('mc_1', now()->addDays(3));
    $cert('mc_2', now()->subDay());
    $cert('mc_3', now()->addDays(40));
    $cert('mc_4', now()->subDay(), 'revoked');

    expect($body())->toContain('onhost_certificates_expiring_7d 1')->toContain('onhost_certificates_expired 1');
});
