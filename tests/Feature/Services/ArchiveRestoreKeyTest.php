<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\ServiceArchiveService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * Red-team round on the integrated Phase-0 chain (TASK-0035, IF-11 / IF-12):
 *  · the two doors to one archive restore kept three different keys — the archive endpoint `archive.restore:<backup>:<header>`
 *    (no target, no person, no request: the bus answered a retry for ANOTHER target with the first run once the HTTP answer
 *    was gone), the generic action endpoint `service.archive.restore:<target>:<person>:<header>` with the request's fingerprint,
 *    and the operation's own key built from whichever came in. One helper names the key for both doors now;
 *  · an archive of a family with no automated restore was "waived" and then refused inside the bus transaction: the waive was
 *    rolled back and the answer still said `waived: true` — the download was not free. The answer says what was kept;
 *  · the restore asked about the actor, not the person a staff member acts for.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
});

/** The HTTP layer's own answers are dropped, as after a 5xx or a process that died after the commit: only the bus remembers. */
function archiveKeyForgetHttp(): void
{
    DB::table('idempotency_keys')->where('key', 'like', 'http:%')->delete();
}

it('gives a retry with the same key for another target its own restore, and both doors the same operation', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $old = arsWebService($org, 'stary.cz');
    $archive = arsArchive($old);
    arsCancelled($old);
    $first = arsWebService($org, 'prvni.cz');
    $second = arsWebService($org, 'druhy.cz');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $send = fn (string $target) => $this->withHeader('Idempotency-Key', 'restore-once')->postJson("/v1/services/archives/{$archive->id}/restore", ['service_id' => $target]);

    $one = $send($first->id)->assertSuccessful()->json();
    archiveKeyForgetHttp();
    $two = $send($second->id)->assertSuccessful()->json();
    expect($two['service_id'])->toBe($second->id)->and($two['operation_id'])->not->toBe($one['operation_id'])
        ->and(Operation::query()->where('service_id', $second->id)->where('desired->action', 'archive.restore')->count())->toBe(1);

    // the generic door with the same key for the same target and archive is the same restore, not a second one over it
    archiveKeyForgetHttp();
    $generic = $this->withHeader('Idempotency-Key', 'restore-once')->postJson("/v1/services/{$first->id}/actions", ['action' => 'archive.restore', 'params' => ['backup_id' => $archive->id]])
        ->assertStatus(202)->json();
    expect($generic['operation_id'])->toBe($one['operation_id'])
        ->and(Operation::query()->where('service_id', $first->id)->where('desired->action', 'archive.restore')->count())->toBe(1);
});

it('answers a manual restore with what was kept: the refusal waives nothing, and says so', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $game = featureGameService($org);
    $archive = Backup::query()->create([
        'service_id' => 'svc_gone_game', 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'size_bytes' => 1024, 'retention_until' => now()->addDays(60),
        'meta' => ['set' => 'final/'.$org->id.'/svc_gone_game', 'family' => 'game', 'parts' => ['server.tar.gz'], 'gaps' => []],
    ]);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', 'manual-1')->postJson("/v1/services/archives/{$archive->id}/restore", ['service_id' => $game->id])
        ->assertStatus(409)->assertJsonPath('error', 'archive_restore_manual')->assertJsonPath('waived', false);
    expect(data_get($archive->fresh()->meta, 'download.waived'))->toBeNull()->and(data_get($archive->fresh()->meta, 'download.paid'))->toBeNull();
});

it('asks about the person a staff member acts for, not about the staff member', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $old = arsWebService($org, 'stary.cz');
    $archive = arsArchive($old);
    arsCancelled($old);
    $target = arsWebService($org, 'novy.cz');
    $viewer = arsPerson($org, 'viewer', 'organization', $org->id); // reads the archive, restores nothing
    $staff = $this->staff('platform_owner');

    $context = new CommandContext('user', $staff->id, $org->id, onBehalfOfUserId: $viewer->id);
    expect(fn () => app(ServiceArchiveService::class)->restore($archive, $target, $context, 'on-behalf-1'))
        ->toThrow(fn (DomainError $e) => expect([$e->error, $e->status])->toBe(['forbidden', 403]));
    expect(Operation::query()->where('service_id', $target->id)->exists())->toBeFalse();
});
