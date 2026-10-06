<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Services\CustomIso\CustomIsoLibrary;
use Onhost\Domain\Services\CustomIso\IsoScanner;
use Onhost\Domain\Services\Models\CustomIso;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;

/*
 * H1 (phase H, TASK-0121): the hourly sweep (`onhost:isos:sweep`) also takes what no row claims any more. G5's sweep removed
 * dead staging rows and loose files directly in `incoming/`; a file left in a folder under `incoming/`, or an image file whose row
 * is gone or deleted (a delete whose file removal failed, a restore of the database from before an upload) stayed on the disk
 * for ever and filled the dedicated mount. A file younger than the staging window is never touched (an upload may be writing it),
 * and the file of an image the organization keeps (READY) or an upload in flight (STAGING) is never touched at all.
 *
 * Security review of PR #116: an orphan is not deleted at once — it goes to `quarantine/<when>/…` and is deleted only after
 * `onhost.custom_iso.quarantine_days` (M1); `--dry-run` changes nothing (M1); an image `store()` just moved into the organization's
 * folder carries a fresh time, so a sweep that read the rows a moment earlier cannot take it (M2); only a folder named exactly like
 * an organization id is looked into (M3).
 */

beforeEach(function () {
    Storage::fake('custom_isos');
    config(['onhost.custom_iso.staging_hours' => 6, 'onhost.custom_iso.quarantine_days' => 14]);
});

/** A row of `$state` for `$path` (or none with `$state` null), the file on the disk, and its age. */
function h1SweepFile(string $path, ?string $state, int $hoursOld, ?string $organizationId = null): ?CustomIso
{
    $disk = Storage::disk('custom_isos');
    $disk->put($path, 'iso-bytes');
    touch($disk->path($path), now()->subHours($hoursOld)->getTimestamp());
    if ($state === null) {
        return null;
    }

    return CustomIso::query()->create([
        'organization_id' => $organizationId, 'name' => basename($path), 'path' => $path, 'size_bytes' => 9, 'sha256' => hash('sha256', $path), 'state' => $state, 'scan_result' => 'clean', 'node_copies' => [],
    ]);
}

/** @return list<string> every file under quarantine/ */
function h1Quarantined(): array
{
    return Storage::disk('custom_isos')->allFiles('quarantine');
}

it('quarantines image files no kept image claims, and files left in folders under incoming, once they are older than the window', function () {
    [, $org] = $this->customerWithOrganization();
    $dir = $org->id;
    $kept = h1SweepFile("{$dir}/iso_kept.iso", CustomIso::READY, 48, $org->id);
    h1SweepFile("{$dir}/iso_gone.iso", null, 48);                                // the row is gone
    $deleted = h1SweepFile("{$dir}/iso_deleted.iso", CustomIso::DELETED, 48, $org->id); // the delete could not remove the file
    h1SweepFile("{$dir}/iso_fresh.iso", null, 1);                                // too young: an upload may be moving it right now
    h1SweepFile('incoming/nested/iso_part.part', null, 48);                       // a folder under incoming/
    h1SweepFile('incoming/iso_live.part', CustomIso::STAGING, 1, $org->id);       // an upload in flight

    $stats = app(CustomIsoLibrary::class)->sweep();
    $disk = Storage::disk('custom_isos');

    expect($disk->exists("{$dir}/iso_kept.iso"))->toBeTrue()
        ->and($disk->exists("{$dir}/iso_gone.iso"))->toBeFalse()
        ->and($disk->exists("{$dir}/iso_deleted.iso"))->toBeFalse()
        ->and($disk->exists("{$dir}/iso_fresh.iso"))->toBeTrue()
        ->and($disk->exists('incoming/nested/iso_part.part'))->toBeFalse()
        ->and($disk->exists('incoming/iso_live.part'))->toBeTrue()
        ->and($stats['quarantined'])->toBe(2)
        ->and($stats['purged'])->toBe(0)
        ->and($stats['files'])->toBe(1)
        ->and($kept->fresh()->state)->toBe(CustomIso::READY)
        ->and($deleted->fresh()->state)->toBe(CustomIso::DELETED);
    // M1: nothing of a customer's image is deleted at once — the orphans wait in quarantine under their own path
    $held = h1Quarantined();
    expect($held)->toHaveCount(2)
        ->and(collect($held)->contains(fn (string $f) => str_ends_with($f, "/{$dir}/iso_gone.iso")))->toBeTrue()
        ->and(collect($held)->contains(fn (string $f) => str_ends_with($f, "/{$dir}/iso_deleted.iso")))->toBeTrue();
});

it('deletes a quarantined file only after the retention, and keeps it while it is younger', function () {
    [, $org] = $this->customerWithOrganization();
    h1SweepFile("{$org->id}/iso_gone.iso", null, 48);
    app(CustomIsoLibrary::class)->sweep();
    expect(h1Quarantined())->toHaveCount(1);

    $this->travel(13)->days();
    expect(app(CustomIsoLibrary::class)->sweep()['purged'])->toBe(0)->and(h1Quarantined())->toHaveCount(1);

    $this->travel(2)->days();
    expect(app(CustomIsoLibrary::class)->sweep()['purged'])->toBe(1)->and(h1Quarantined())->toBe([]);
});

it('changes nothing on a dry run and says what it would do', function () {
    [, $org] = $this->customerWithOrganization();
    h1SweepFile("{$org->id}/iso_gone.iso", null, 48);
    h1SweepFile('incoming/nested/old.part', null, 48);

    $this->artisan('onhost:isos:sweep', ['--dry-run' => true])->assertSuccessful()
        ->expectsOutputToContain('dry run')->expectsOutputToContain('1 orphaned image file(s)');

    $disk = Storage::disk('custom_isos');
    expect($disk->exists("{$org->id}/iso_gone.iso"))->toBeTrue()->and($disk->exists('incoming/nested/old.part'))->toBeTrue()->and(h1Quarantined())->toBe([]);
});

it('looks only into folders named exactly like an organization id', function () {
    [, $org] = $this->customerWithOrganization();
    expect($org->id)->toMatch('/^org_[0-9a-hjkmnp-tv-z]{26}$/'); // the real format the guard is written for (M3)
    h1SweepFile("{$org->id}/iso_gone.iso", null, 48);
    h1SweepFile('org_backup/keep.iso', null, 48);  // looks like one, is not one: the operator's folder
    h1SweepFile('lost+found/x', null, 48);

    expect(app(CustomIsoLibrary::class)->sweep()['quarantined'])->toBe(1);
    $disk = Storage::disk('custom_isos');
    expect($disk->exists('org_backup/keep.iso'))->toBeTrue()->and($disk->exists('lost+found/x'))->toBeTrue();
});

it('gives an image store() moves into the organization folder a fresh time, so a sweep cannot take it as an old orphan', function () {
    [, $org] = $this->customerWithOrganization();
    app()->instance(IsoScanner::class, new class implements IsoScanner
    {
        public function scan($stream): array
        {
            return ['result' => self::CLEAN, 'signature' => null];
        }

        public function selfTest(): array
        {
            return ['ok' => true, 'detail' => 'fake'];
        }
    });
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::ACTIVE, 'entitlements' => ['custom_iso' => true, 'custom_iso_max_mb' => 2], 'tags' => []]);
    $row = CustomIso::query()->create(['organization_id' => $org->id, 'uploaded_via_service_id' => $service->id, 'name' => 'a.iso', 'path' => 'incoming/placeholder.part', 'size_bytes' => 9, 'sha256' => str_repeat('a', 64), 'state' => CustomIso::STAGING, 'scan_result' => IsoScanner::CLEAN, 'node_copies' => []]);
    $row->forceFill(['path' => "incoming/{$row->id}.part"])->save();
    h1SweepFile("incoming/{$row->id}.part", null, 48); // a long upload: the staged file is older than the window

    $image = app(CustomIsoLibrary::class)->store($service, $row->id, CommandContext::system('test')->withScope($org->id));
    $disk = Storage::disk('custom_isos');
    expect($disk->exists($image->path))->toBeTrue()
        ->and($disk->lastModified($image->path))->toBeGreaterThanOrEqual(now()->subMinute()->getTimestamp());
});

it('says how many orphaned image files the command quarantined', function () {
    [, $org] = $this->customerWithOrganization();
    h1SweepFile("{$org->id}/iso_gone.iso", null, 48);

    $this->artisan('onhost:isos:sweep')->assertSuccessful()->expectsOutputToContain('1 orphaned image file(s) quarantined');
});
