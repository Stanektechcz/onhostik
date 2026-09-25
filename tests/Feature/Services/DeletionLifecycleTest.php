<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\PortableArchive;
use Onhost\Domain\Services\ServiceIdentityCheck;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * The end of a service (audit §5ab): the archive stays for the retention the administration sets, the customer may
 * restore it onto a new paid service for free, and downloading it as one file costs the configured fee — once.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Storage::fake('local');
});

/** A finished archive on the backup disk, as the cancellation of a web service leaves it behind. */
function storedArchive(string $organizationId, string $serviceId, string $family = 'web'): Backup
{
    $set = FinalArchive::PREFIX.'/'.$organizationId.'/'.$serviceId.'-20260901-120000';
    Storage::disk('local')->put($set.'/service.json', json_encode(['service' => ['id' => $serviceId]]));
    Storage::disk('local')->put($set.'/site-files.tar.gz', str_repeat('files', 200));
    Storage::disk('local')->put($set.'/manifest.json', json_encode(['service_id' => $serviceId]));

    return Backup::query()->create([
        'service_id' => $serviceId, 'organization_id' => $organizationId, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'verified_at' => now()->subDay(), 'verify_status' => 'ok', 'size_bytes' => 1024,
        'retention_until' => now()->addDays(60), 'immutable_until' => now()->addDays(60), 'meta' => ['set' => $set, 'family' => $family, 'parts' => ['service.json', 'site-files.tar.gz'], 'gaps' => []],
    ]);
}

it('lets staff set the restore window, the retention and the download fee, and clamps nonsense (audit §5ab)', function () {
    $staff = $this->staff();
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->actingAs($staff, 'sanctum');

    expect($this->getJson('/v1/staff/settings/lifecycle')->assertOk()->json('data.lifecycle'))
        ->toMatchArray(['grace_days' => 30, 'retention_days' => 60, 'identity_checks' => 5]);

    // the download fee is a price (owner decision 13): a second person approves, the same request repeated goes through
    $body = ['grace_days' => 45, 'retention_days' => 120, 'download_fee_minor' => ['CZK' => 90000]];
    $asked = (string) $this->withHeader('Idempotency-Key', 'lc-1')->putJson('/v1/staff/settings/lifecycle', $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(app(DeletionPolicy::class)->graceDays())->toBe(30);
    secondPersonApproves($asked);
    $this->withHeader('Idempotency-Key', 'lc-1b')->putJson('/v1/staff/settings/lifecycle', $body)->assertOk();
    $policy = app(DeletionPolicy::class);
    expect($policy->graceDays())->toBe(45)->and($policy->retentionDays())->toBe(120)->and($policy->downloadFeeMinor('CZK'))->toBe(90000);

    // a retention below a month or fewer than five identity points is never accepted
    $this->withHeader('Idempotency-Key', 'lc-2')->putJson('/v1/staff/settings/lifecycle', ['retention_days' => 3, 'identity_checks' => 1])->assertStatus(422);
    expect(app(DeletionPolicy::class)->retentionDays())->toBe(120)->and(app(DeletionPolicy::class)->identityChecks())->toBe(5);
});

it('charges the fee once for downloading an archive and hands over a signed link (audit §5ab)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $backup = storedArchive($org->id, $service->id);
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'seed', CommandContext::system('test'));
    $this->actingAs($owner, 'sanctum');

    $list = $this->getJson('/v1/services/archives')->assertOk()->json('data');
    expect($list['policy'])->toMatchArray(['grace_days' => 30, 'retention_days' => 60, 'download_fee_minor' => 50000])
        ->and($list['archives'][0])->toMatchArray(['id' => $backup->id, 'paid' => false, 'restorable' => true]);

    $first = $this->withHeader('Idempotency-Key', 'dl-1')->postJson("/v1/services/archives/{$backup->id}/download")->assertOk()->json('data');
    expect($first['charged'])->toBeTrue()->and($first['fee_minor'])->toBe(50000)->and($first['url'])->toContain('/archiv/');
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(200000 - 60500); // 500 Kč + 21 % VAT
    expect(Invoice::query()->where('organization_id', $org->id)->where('state', '!=', 'draft')->exists())->toBeTrue();

    // the same archive is not charged twice, and the signed link streams the packaged file
    $second = $this->withHeader('Idempotency-Key', 'dl-2')->postJson("/v1/services/archives/{$backup->id}/download")->assertOk()->json('data');
    expect($second['charged'])->toBeFalse();
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(200000 - 60500);
    $this->get($second['url'])->assertOk()->assertHeader('content-type', 'application/zip');

    // an unsigned link never reaches the file (the signature is the credential)
    $this->get('/archiv/'.$org->id.'/'.$backup->id)->assertStatus(403);
});

it('restores an archive onto a new paid service free of charge (audit §5ab)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $old = featureWebService($org, 'ispconfig');
    $backup = storedArchive($org->id, $old->id);
    $old->forceFill(['state' => 'TERMINATED', 'terminated_at' => now()])->save();
    $old->delete();
    $new = featureWebService($org, 'aapanel'); // the new, paid service the customer just ordered
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'seed', CommandContext::system('test'));
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $result = $this->withHeader('Idempotency-Key', 'rs-1')->postJson("/v1/services/archives/{$backup->id}/restore", ['service_id' => $new->id])->assertOk()->json();
    expect($result['operation_id'])->not->toBeNull()->and($result['fee_minor'])->toBe(0);
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(200000); // nothing charged
    expect(data_get(Backup::query()->findOrFail($backup->id)->meta, 'download.waived'))->toBeTrue(); // and the download is free from now on
});

it('refuses an archive of another organization and one whose retention has run out', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization(['email' => 'other@shop.cz'], ['name' => 'Jiná firma']);
    $foreign = storedArchive($other->id, 'srv_other');
    $expired = storedArchive($org->id, 'srv_old');
    $expired->forceFill(['retention_until' => now()->subDay()])->save();
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', 'x-1')->postJson("/v1/services/archives/{$foreign->id}/download")->assertStatus(404);
    $this->withHeader('Idempotency-Key', 'x-2')->postJson("/v1/services/archives/{$expired->id}/download")->assertStatus(410);
});

it('verifies at least five identifiers of a service before anything may be deleted (audit §5ab)', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $report = app(ServiceIdentityCheck::class)->verify($service, null, null);

    expect($report['required'])->toBe(5)->and($report['checks'])->not->toBeEmpty();
    expect(collect($report['checks'])->firstWhere('key', 'remote_exists')['ok'])->toBeNull(); // no adapter: unknown, never assumed
    expect($report['ok'])->toBeFalse(); // and without the panel's confirmation the deletion does not start
});

/*
 * Usable without us (Brain card H28: "export lze obnovit mimo původní instanci"). The download is a plain zip of plain
 * formats; what makes it portable is that it says so itself: checksums a stock tool verifies, what each file is, how to
 * put it back on any server, and what is NOT in it. The test restores it the way a stranger would — with nothing of ours.
 */
it('hands over an archive that verifies and restores with stock tools only, and says what is missing', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $backup = storedArchive($org->id, $service->id);
    $set = (string) $backup->meta['set'];
    Storage::disk('local')->put($set.'/database-eshop.sql', "CREATE TABLE t (id INT);\nINSERT INTO t VALUES (1);\n");
    Storage::disk('local')->put($set.'/manifest.json', json_encode(['service_id' => $service->id, 'family' => 'web', 'created_at' => '2026-09-01T12:00:00+00:00', 'gaps' => ['mail: mailbox contents are not exportable through the panel API (IMAP copy is a separate migration)']]));

    $package = app(FinalArchive::class)->package($backup);
    $zipPath = tempnam(sys_get_temp_dir(), 'onhost-portable');
    file_put_contents($zipPath, Storage::disk('local')->get($package['path']));
    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    sort($names);
    expect($names)->toBe(['README.txt', 'SHA256SUMS', 'database-eshop.sql', 'manifest.json', 'service.json', 'site-files.tar.gz']);

    // what `sha256sum -c SHA256SUMS` does, done by hand: every listed file is in the zip and hashes to the listed sum
    $listed = [];
    foreach (array_filter(explode("\n", (string) $zip->getFromName('SHA256SUMS'))) as $line) {
        expect($line)->toMatch('/^[0-9a-f]{64}  \S+$/'); // the exact format the tool reads
        [$hash, $name] = explode('  ', $line, 2);
        $listed[$name] = $hash;
        expect(hash('sha256', (string) $zip->getFromName($name)))->toBe($hash);
    }
    expect(array_keys($listed))->toBe(['database-eshop.sql', 'manifest.json', 'service.json', 'site-files.tar.gz']);

    // the word about it: each part, a stock command for it, the gap, and no tool of ours
    $readme = (string) $zip->getFromName('README.txt');
    expect($readme)->toContain($service->id)->toContain('sha256sum -c SHA256SUMS')->toContain('tar -xzf site-files.tar.gz')->toContain('mysql -u')->toContain('database-eshop.sql')
        ->toContain('mailbox contents are not exportable')->toContain('NENÍ')
        ->not->toContain('artisan')->not->toContain('onhost:');
    $zip->close();
    @unlink($zipPath);

    // a package built before these files existed is rebuilt on the next download, a current one is reused
    expect($backup->fresh()->meta['download']['format'])->toBe(PortableArchive::FORMAT);
    $builtAt = $backup->fresh()->meta['download']['built_at'];
    $this->travel(5)->minutes();
    app(FinalArchive::class)->package($backup->fresh());
    expect($backup->fresh()->meta['download']['built_at'])->toBe($builtAt);
    $old = $backup->fresh();
    $old->forceFill(['meta' => array_replace_recursive((array) $old->meta, ['download' => ['format' => 1]])])->save();
    app(FinalArchive::class)->package($old->fresh());
    expect($backup->fresh()->meta['download']['built_at'])->not->toBe($builtAt)->and($backup->fresh()->meta['download']['format'])->toBe(PortableArchive::FORMAT);
});
