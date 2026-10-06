<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Services\CustomIso\CustomIsoLibrary;
use Onhost\Domain\Services\Models\CustomIso;

/*
 * H1 (phase H, TASK-0121): the hourly sweep (`onhost:isos:sweep`) also takes what no row claims any more. G5's sweep removed
 * dead staging rows and loose files directly in `incoming/`; a file left in a folder under `incoming/`, or an image file whose row
 * is gone or deleted (a delete whose file removal failed, a restore of the database from before an upload) stayed on the disk
 * for ever and filled the dedicated mount. A file younger than the staging window is never touched (an upload may be writing it),
 * and the file of an image the organization keeps (READY) or an upload in flight (STAGING) is never touched at all.
 */

beforeEach(function () {
    Storage::fake('custom_isos');
    config(['onhost.custom_iso.staging_hours' => 6]);
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

it('removes image files no kept image claims, and files left in folders under incoming, once they are older than the window', function () {
    [, $org] = $this->customerWithOrganization();
    $kept = h1SweepFile('org_h1sweep/iso_kept.iso', CustomIso::READY, 48, $org->id);
    h1SweepFile('org_h1sweep/iso_gone.iso', null, 48);              // the row is gone
    $deleted = h1SweepFile('org_h1sweep/iso_deleted.iso', CustomIso::DELETED, 48, $org->id); // the delete could not remove the file
    h1SweepFile('org_h1sweep/iso_fresh.iso', null, 1);              // too young: an upload may be moving it right now
    h1SweepFile('incoming/nested/iso_part.part', null, 48);           // a folder under incoming/
    h1SweepFile('incoming/iso_live.part', CustomIso::STAGING, 1, $org->id);   // an upload in flight

    $stats = app(CustomIsoLibrary::class)->sweep();
    $disk = Storage::disk('custom_isos');

    expect($disk->exists('org_h1sweep/iso_kept.iso'))->toBeTrue()
        ->and($disk->exists('org_h1sweep/iso_gone.iso'))->toBeFalse()
        ->and($disk->exists('org_h1sweep/iso_deleted.iso'))->toBeFalse()
        ->and($disk->exists('org_h1sweep/iso_fresh.iso'))->toBeTrue()
        ->and($disk->exists('incoming/nested/iso_part.part'))->toBeFalse()
        ->and($disk->exists('incoming/iso_live.part'))->toBeTrue()
        ->and($stats['orphans'])->toBe(2)
        ->and($stats['files'])->toBe(1)
        ->and($kept->fresh()->state)->toBe(CustomIso::READY)
        ->and($deleted->fresh()->state)->toBe(CustomIso::DELETED);
});

it('says how many orphaned image files the command removed', function () {
    h1SweepFile('org_h1sweep/iso_gone.iso', null, 48);

    $this->artisan('onhost:isos:sweep')->assertSuccessful()->expectsOutputToContain('1 orphaned image file(s)');
});
