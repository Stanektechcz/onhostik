<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;

/*
 * A legal hold suspends deletion (Brain card H18) — of the service, and of every copy of its data. Retention keeps
 * counting under a hold but does not act: the archive of a cancelled service, often the only copy left, is still there
 * when the hold is lifted, and only then does the next pass remove what is overdue.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
});

it('keeps an archive past its retention while the organization is under a legal hold, and removes it once the hold is lifted', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $disk = (string) config('onhost.platform_backup.disk', 'local');
    Storage::fake($disk);
    $set = 'service-archives/'.$org->id.'/'.$service->id.'-h18';
    Storage::disk($disk)->put($set.'/service.json', '{"evidence":true}');
    $archive = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => true, 'size_bytes' => 17, 'started_at' => now()->subDays(70), 'finished_at' => now()->subDays(70), 'retention_until' => now()->subDays(10), 'meta' => ['set' => $set]]);
    $compliance = app(ComplianceService::class);
    $legal = $this->contextFor($this->staff('compliance_legal'));

    // sixty days are long gone, but a hold was placed on the organization in the meantime
    $compliance->setLegalHold($org, true, 'žádost PČR č. j. KRPA-1/2026', $legal);
    expect(app(FinalArchive::class)->prune())->toBe(0)
        ->and($archive->fresh()->state)->toBe('completed')->and(Storage::disk($disk)->exists($set.'/service.json'))->toBeTrue();

    // lifted: retention did not stop counting, so the first pass afterwards removes it
    $compliance->setLegalHold($org->fresh(), false, 'řízení ukončeno', $legal);
    expect(app(FinalArchive::class)->prune())->toBe(1)
        ->and($archive->fresh()->state)->toBe('expired')->and(Storage::disk($disk)->exists($set.'/service.json'))->toBeFalse();
});

it('does not let a copy of the data be destroyed by hand or by the schedule under a legal hold, while new backups are still made', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $old = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'scheduled', 'state' => 'completed', 'protected' => false, 'remote_id' => 'bk-old', 'size_bytes' => 1024, 'started_at' => now()->subDays(40), 'finished_at' => now()->subDays(40), 'retention_until' => now()->subDays(5)]);
    app(ComplianceService::class)->setLegalHold($org, true, 'žádost PČR č. j. KRPA-1/2026', $this->contextFor($this->staff('compliance_legal')));
    Queue::fake();
    $this->actingAs($owner, 'sanctum');

    // by hand: the customer under investigation cannot thin out the evidence
    foreach ([['backup.delete', ['remote_id' => 'bk-old']], ['snapshot.delete', ['remote_id' => 'snap-1']]] as $n => [$action, $params]) {
        $this->withHeader('Idempotency-Key', "h18-{$n}")->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params])->assertStatus(423)->assertJsonPath('error', 'legal_hold');
    }
    expect(Operation::query()->where('service_id', $service->id)->count())->toBe(0);

    // by the schedule: the overdue generation stays
    $this->artisan('onhost:backups:run')->assertExitCode(0);
    expect($old->fresh()->state)->toBe('completed');

    // making a new backup is not deletion: the same scheduled pass that left the old generation alone started a new one
    expect(Operation::query()->where('service_id', $service->id)->where('desired->action', 'backup')->count())->toBe(1);
});
