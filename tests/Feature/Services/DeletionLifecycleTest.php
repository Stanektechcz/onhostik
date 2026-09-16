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
    $this->actingAs($this->staff(), 'sanctum');

    expect($this->getJson('/v1/staff/settings/lifecycle')->assertOk()->json('data.lifecycle'))
        ->toMatchArray(['grace_days' => 30, 'retention_days' => 60, 'identity_checks' => 5]);

    $this->withHeader('Idempotency-Key', 'lc-1')->putJson('/v1/staff/settings/lifecycle', ['grace_days' => 45, 'retention_days' => 120, 'download_fee_minor' => ['CZK' => 90000]])->assertOk();
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
