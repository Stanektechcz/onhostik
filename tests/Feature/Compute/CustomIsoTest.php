<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\CustomIso\ClamdIsoScanner;
use Onhost\Domain\Services\CustomIso\CustomIsoPolicy;
use Onhost\Domain\Services\CustomIso\IsoScanner;
use Onhost\Domain\Services\Models\CustomIso;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\RescueMode;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Files\VirusScanner;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * G5 (owner decision G-R5, TASK-0110): a customer may boot an installation image of their own — only when the VPS plan they
 * ordered sells it. Without it the feature is not there (`reason: plan`, „není v tarifu“) and the server refuses with 403; with
 * it an upload is an ISO 9660 image no larger than the plan says, scanned by clamd before it is kept (an infected file is
 * deleted and reported, no scanner is a refusal — never "kept unscanned"), inside the organization's quota; attaching copies it
 * to the hypervisor and makes it the CD drive, detaching puts back exactly the boot order that was there, deleting detaches
 * first. Everything against a STATEFUL Proxmox (e2ePveCluster + e2ePveIsoStorage): a call nobody expects lands in `unknown`.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('custom_isos');
    config(['onhost.custom_iso.org_quota_mb' => 4, 'onhost.custom_iso.org_max_images' => 3]);
    $this->isoScanner = ciScanner(IsoScanner::CLEAN);
    app()->instance(IsoScanner::class, $this->isoScanner);
});

/** A scanner double that answers what the test says, and counts what it was shown. */
function ciScanner(string $result, ?string $signature = null): IsoScanner
{
    return new class($result, $signature) implements IsoScanner
    {
        /** @var list<int> */
        public array $seen = [];

        public function __construct(private readonly string $result, private readonly ?string $signature) {}

        public function scan($stream): array
        {
            $this->seen[] = strlen((string) stream_get_contents($stream));

            return ['result' => $this->result, 'signature' => $this->signature];
        }
    };
}

/** The bytes of a small ISO 9660 image: the volume descriptor's `CD001` at byte 32769, then whatever `$fill` says. */
function ciIsoBytes(string $fill = 'a', int $size = 65536): string
{
    $head = str_repeat("\0", 32768)."\x01CD001\x01";

    return $head.str_repeat($fill, max(0, $size - strlen($head)));
}

/**
 * A running VPS of `$org` on the lab cluster (guest 1050, booting from its disk), the instance with a custom ISO storage.
 *
 * @param  array<string,mixed>  $pve
 * @param  array<string,mixed>  $entitlements
 */
function ciVps(object $org, array &$pve, array $entitlements = ['custom_iso' => true, 'custom_iso_max_mb' => 2], int $vmid = 1050): Service
{
    $instance = pveLab();
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['custom_iso_storage' => 'isostore'])])->save();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'hostname' => "vm-{$vmid}.cust.onhost.cz", 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'],
        'entitlements' => array_merge(['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160], $entitlements), 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => (string) $vmid, 'remote_node' => 'prg1-n2', 'meta' => ['name' => "vm-{$vmid}"], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "ci:{$service->id}", 'adapter_version' => '1.0.0']);
    $pve['guests'][$vmid] = ['name' => "vm-{$vmid}", 'description' => '', 'tags' => 'onhost;'.ProxmoxComputeProvider::serviceTag($service->id), 'status' => 'running', 'template' => 0,
        'config' => ['scsi0' => "local-zfs:vm-{$vmid}-disk-0,size=160G", 'boot' => 'order=scsi0;net0', 'cores' => 4, 'memory' => 8192], 'snapshots' => [], 'firewall_rules' => [], 'firewall_options' => []];

    return $service;
}

/** The stateful cluster with its custom ISO storage. @param array<string,mixed> $pve */
function ciCluster(array &$pve): void
{
    $pve = ['guests' => []];
    e2ePveClusterWithIsoStorage($pve);
}

function ciUpload(object $test, Service $service, string $content, string $name = 'debian-13-netinst.iso', ?string $key = null): TestResponse
{
    return $test->withHeaders(['Idempotency-Key' => $key ?? (string) Str::ulid(), 'Accept' => 'application/json'])
        ->post("/v1/services/{$service->id}/isos", ['file' => UploadedFile::fake()->createWithContent($name, $content)]);
}

/** @param array<string,mixed> $params */
function ciAction(object $test, Service $service, string $action, array $params = [], ?string $key = null): TestResponse
{
    return $test->withHeaders(['Idempotency-Key' => $key ?? (string) Str::ulid()])->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params]);
}

function ciRun(TestResponse $response): Operation
{
    $response->assertStatus(202);

    return driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
}

it('offers no custom ISO where the ordered plan does not have it: hidden with the reason, 403 to an upload and an attach', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve, []); // a plan without custom_iso
    $this->actingAs($owner, 'sanctum');

    $features = $this->getJson("/v1/services/{$vps->id}/features")->assertOk()->json('data.features');
    expect($features['custom_iso'])->toBe(['enabled' => false, 'reason' => ServiceFeatures::REASON_PLAN])
        ->and($features)->not->toHaveKey('custom_iso_exit');
    expect(app(ServiceFeatures::class)->actions($vps))->not->toContain('iso.attach')->not->toContain('iso.delete');

    ciUpload($this, $vps, ciIsoBytes())->assertForbidden()->assertJsonPath('error', 'custom_iso_not_in_plan');
    ciAction($this, $vps, 'iso.attach', ['iso_id' => 'iso_'.str_repeat('0', 26)])->assertForbidden()->assertJsonPath('error', 'custom_iso_not_in_plan');
    // the listing stays readable: what the organization holds is never hidden from it
    $this->getJson("/v1/services/{$vps->id}/isos")->assertOk()->assertJsonPath('data.offered', false)->assertJsonPath('data.images', []);

    expect(CustomIso::query()->count())->toBe(0)->and(Storage::disk('custom_isos')->allFiles())->toBe([])
        ->and($this->isoScanner->seen)->toBe([]) // nothing was even read
        ->and($pve['writes'])->toBe([])->and($pve['unknown'])->toBe([]);
});

it('uploads, attaches, detaches and deletes an image where the plan has it, putting back exactly the boot order it found', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $this->actingAs($owner, 'sanctum');

    $features = $this->getJson("/v1/services/{$vps->id}/features")->assertOk()->json('data.features.custom_iso');
    expect($features['enabled'])->toBeTrue()->and($features['options'])->toMatchArray(['max_bytes' => 2 * 1048576, 'quota_bytes' => 4 * 1048576, 'max_images' => 3, 'attached' => null]);

    // upload: scanned, kept under the platform's own name, the customer's name only as a label
    $bytes = ciIsoBytes();
    $uploaded = ciUpload($this, $vps, $bytes)->assertCreated();
    $iso = CustomIso::query()->findOrFail($uploaded->json('data.id'));
    expect($uploaded->json('data'))->toMatchArray(['name' => 'debian-13-netinst.iso', 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'scan' => 'clean', 'attached_service_id' => null])
        ->and($this->isoScanner->seen)->toBe([strlen($bytes)])
        ->and($iso->path)->toBe("{$org->id}/{$iso->id}.iso")->and(Storage::disk('custom_isos')->get($iso->path))->toBe($bytes)
        ->and(Storage::disk('custom_isos')->files('incoming'))->toBe([]); // nothing staged is left behind
    $listing = $this->getJson("/v1/services/{$vps->id}/isos")->assertOk()->json('data');
    expect($listing['quota'])->toMatchArray(['used_bytes' => strlen($bytes), 'images' => 1, 'max_images' => 3])->and(array_column($listing['images'], 'id'))->toBe([$iso->id]);
    ciNoVendorWords([$uploaded->json(), $listing]);

    // attach: copied to the node (checked against its SHA-256 there), made the CD drive and the first boot device; no reboot unasked
    $attached = ciRun(ciAction($this, $vps, 'iso.attach', ['iso_id' => $iso->id]));
    expect($attached->state)->toBe(Operation::SUCCEEDED, json_encode($attached->error));
    $volume = "isostore:iso/onhost-ciso-{$iso->id}.iso";
    expect($pve['custom_isos'])->toHaveKey($volume)->and($pve['custom_isos'][$volume]['sha256'])->toBe(hash('sha256', $bytes))
        ->and($pve['guests'][1050]['config']['ide2'])->toBe("{$volume},media=cdrom")->and($pve['guests'][1050]['config']['boot'])->toBe('order=ide2;scsi0;net0')
        ->and($pve['writes'])->not->toContain('POST /nodes/prg1-n2/qemu/1050/status/reboot');
    expect($iso->fresh()->attached_service_id)->toBe($vps->id)->and($iso->fresh()->previous_boot)->toBe(['iso' => null, 'boot' => 'order=scsi0;net0'])
        ->and($this->getJson("/v1/services/{$vps->id}/features")->json('data.features.custom_iso.options.attached'))->toBe($iso->id);

    // detach: the drive empty again and the order exactly as it was
    expect(ciRun(ciAction($this, $vps, 'iso.detach', ['reboot' => true]))->state)->toBe(Operation::SUCCEEDED);
    expect($pve['guests'][1050]['config']['ide2'])->toBe('none,media=cdrom')->and($pve['guests'][1050]['config']['boot'])->toBe('order=scsi0;net0')
        ->and($pve['writes'])->toContain('POST /nodes/prg1-n2/qemu/1050/status/reboot') // asked for this time
        ->and($iso->fresh()->attached_service_id)->toBeNull();
    ciAction($this, $vps, 'iso.detach')->assertStatus(409)->assertJsonPath('error', 'iso_not_attached');

    // delete: HIGH — a fresh step-up first; then the node's copy, the platform's file and the row
    ciAction($this, $vps, 'iso.delete', ['iso_id' => $iso->id])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    expect(ciRun(ciAction($this, $vps, 'iso.delete', ['iso_id' => $iso->id]))->state)->toBe(Operation::SUCCEEDED);
    expect($pve['custom_isos'])->toBe([])->and(Storage::disk('custom_isos')->exists($iso->path))->toBeFalse()
        ->and($iso->fresh()->state)->toBe(CustomIso::DELETED)->and($iso->fresh()->node_copies)->toBe([])
        ->and($this->getJson("/v1/services/{$vps->id}/isos")->json('data.images'))->toBe([]);

    expect(OutboxMessage::query()->where('organization_id', $org->id)->where('name', 'like', 'service.iso.%')->orderBy('id')->pluck('name')->all())
        ->toEqualCanonicalizing(['service.iso.uploaded', 'service.iso.attached', 'service.iso.detached', 'service.iso.deleted'])
        ->and(AuditEvent::query()->where('action', 'like', 'service.iso.%')->pluck('action')->all())->toContain('service.iso.upload', 'service.iso.attach', 'service.iso.detach', 'service.iso.delete');
    expect($pve['unknown'])->toBe([]);
});

it('detaches an image before it deletes it, and swapping one image for another keeps the server\'s own boot order', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $this->actingAs($owner, 'sanctum');
    $first = CustomIso::query()->findOrFail(ciUpload($this, $vps, ciIsoBytes('a'), 'first.iso')->assertCreated()->json('data.id'));
    $second = CustomIso::query()->findOrFail(ciUpload($this, $vps, ciIsoBytes('b'), 'second.iso')->assertCreated()->json('data.id'));

    expect(ciRun(ciAction($this, $vps, 'iso.attach', ['iso_id' => $first->id]))->state)->toBe(Operation::SUCCEEDED);
    expect(ciRun(ciAction($this, $vps, 'iso.attach', ['iso_id' => $second->id, 'boot_first' => true]))->state)->toBe(Operation::SUCCEEDED);
    expect($pve['guests'][1050]['config']['ide2'])->toBe("isostore:iso/onhost-ciso-{$second->id}.iso,media=cdrom")
        ->and($first->fresh()->attached_service_id)->toBeNull()->and($second->fresh()->previous_boot)->toBe(['iso' => null, 'boot' => 'order=scsi0;net0']);

    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    expect(ciRun(ciAction($this, $vps, 'iso.delete', ['iso_id' => $second->id]))->state)->toBe(Operation::SUCCEEDED);
    expect($pve['guests'][1050]['config']['ide2'])->toBe('none,media=cdrom')->and($pve['guests'][1050]['config']['boot'])->toBe('order=scsi0;net0')
        ->and($second->fresh()->state)->toBe(CustomIso::DELETED)
        ->and(array_keys($pve['custom_isos']))->toBe(["isostore:iso/onhost-ciso-{$first->id}.iso"]); // the other image's copy stays
    expect($pve['unknown'])->toBe([]);
});

it('refuses an infected image, deletes it and tells security; without a scanner nothing is kept', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $this->actingAs($owner, 'sanctum');

    app()->instance(IsoScanner::class, ciScanner(IsoScanner::INFECTED, 'Win.Test.EICAR_HDB-1'));
    ciUpload($this, $vps, ciIsoBytes())->assertStatus(422)->assertJsonPath('error', 'upload_infected');
    expect(CustomIso::query()->count())->toBe(0)->and(Storage::disk('custom_isos')->allFiles())->toBe([])
        ->and(OutboxMessage::query()->where('name', 'files.infected')->count())->toBe(1);

    // a scan that stopped at clamd's limits looked at part of the image only: refused, no malware alarm
    app()->instance(IsoScanner::class, ciScanner(IsoScanner::INCOMPLETE, 'Heuristics.Limits.Exceeded.MaxFileSize'));
    ciUpload($this, $vps, ciIsoBytes())->assertStatus(422)->assertJsonPath('error', 'iso_scan_incomplete');

    // no scanner answered: 503 with a clear word, and the file is gone — never kept to be scanned later
    app()->instance(IsoScanner::class, ciScanner(IsoScanner::UNAVAILABLE));
    ciUpload($this, $vps, ciIsoBytes())->assertStatus(503)->assertJsonPath('error', 'iso_scan_unavailable');
    expect(CustomIso::query()->count())->toBe(0)->and(Storage::disk('custom_isos')->allFiles())->toBe([])
        ->and(OutboxMessage::query()->where('name', 'files.infected')->count())->toBe(1);

    // the real scanner: no clamd configured is UNAVAILABLE here, even where the general upload scanner would let a file pass
    config(['onhost.storage.clamav.host' => '', 'onhost.storage.clamav.enforce' => false]);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, ciIsoBytes());
    rewind($stream);
    expect(app(VirusScanner::class)->scanStream($stream)['result'])->toBe(VirusScanner::OFF)
        ->and((new ClamdIsoScanner(app(VirusScanner::class)))->scan($stream)['result'])->toBe(IsoScanner::UNAVAILABLE);
    expect($pve['writes'])->toBe([]);
});

it('keeps to the plan\'s size, the scanner\'s reach and the organization\'s quota, and takes the same file twice as one image', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve, ['custom_iso' => true, 'custom_iso_max_mb' => 1]);
    $this->actingAs($owner, 'sanctum');

    ciUpload($this, $vps, ciIsoBytes('a', 1048576 + 1))->assertStatus(422)->assertJsonPath('error', 'iso_too_large')->assertJsonPath('max_bytes', 1048576);
    expect($this->isoScanner->seen)->toBe([]); // refused before the scan

    // what clamd cannot read in full is never accepted, whatever the plan says
    $vps->forceFill(['entitlements' => array_merge((array) $vps->entitlements, ['custom_iso_max_mb' => 4096])])->save();
    config(['onhost.custom_iso.scan_max_mb' => 1]);
    expect(CustomIsoPolicy::maxBytes($vps->fresh()))->toBe(1048576);
    ciUpload($this, $vps->fresh(), ciIsoBytes('a', 1048576 + 1))->assertStatus(422)->assertJsonPath('error', 'iso_too_large');
    config(['onhost.custom_iso.scan_max_mb' => 4096, 'onhost.custom_iso.org_max_images' => 2, 'onhost.custom_iso.org_quota_mb' => 3]);

    $one = ciUpload($this, $vps, ciIsoBytes('a', 1048576))->assertCreated()->json('data.id');
    expect(ciUpload($this, $vps, ciIsoBytes('a', 1048576), 'jine-jmeno.iso')->assertCreated()->json('data.id'))->toBe($one); // the same bytes: the same image
    ciUpload($this, $vps, ciIsoBytes('b', 1048576))->assertCreated();
    ciUpload($this, $vps, ciIsoBytes('c', 4096 * 16))->assertStatus(422)->assertJsonPath('error', 'iso_quota_exceeded')->assertJsonPath('max_images', 2);
    config(['onhost.custom_iso.org_max_images' => 5]);
    ciUpload($this, $vps, ciIsoBytes('d', 1048576 + 512))->assertStatus(422)->assertJsonPath('error', 'iso_quota_exceeded')->assertJsonPath('quota_bytes', 3 * 1048576);
    expect(CustomIso::query()->where('organization_id', $org->id)->count())->toBe(2)->and(Storage::disk('custom_isos')->files('incoming'))->toBe([]);
});

it('answers another organization 404 and never lets it near the image or the server', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $this->actingAs($owner, 'sanctum');
    $iso = ciUpload($this, $vps, ciIsoBytes())->assertCreated()->json('data.id');
    app('auth')->forgetGuards();

    [$stranger, $theirOrg] = $this->customerWithOrganization();
    $theirs = ciVps($theirOrg, $pve, ['custom_iso' => true, 'custom_iso_max_mb' => 2], 1051);
    app(StepUpService::class)->grant($stranger, 'totp', null, '127.0.0.1');
    $this->actingAs($stranger, 'sanctum');
    $this->getJson("/v1/services/{$vps->id}/isos")->assertNotFound();
    ciUpload($this, $vps, ciIsoBytes('z'))->assertNotFound();
    ciAction($this, $vps, 'iso.attach', ['iso_id' => $iso])->assertNotFound();
    // their own server with the feature, our image's id: no such image for them
    ciAction($this, $theirs, 'iso.attach', ['iso_id' => $iso])->assertNotFound()->assertJsonPath('error', 'not_found');
    ciAction($this, $theirs, 'iso.delete', ['iso_id' => $iso])->assertNotFound();
    expect($this->getJson("/v1/services/{$theirs->id}/isos")->assertOk()->json('data.images'))->toBe([]);

    expect(CustomIso::query()->findOrFail($iso)->state)->toBe(CustomIso::READY)->and($pve['custom_isos'])->toBe([])
        ->and($pve['guests'][1050]['config'])->not->toHaveKey('ide2')->and($pve['guests'][1051]['config'])->not->toHaveKey('ide2')
        ->and($pve['unknown'])->toBe([]);
});

it('replays a retried upload and a retried attach instead of doing either twice', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $this->actingAs($owner, 'sanctum');

    $first = ciUpload($this, $vps, ciIsoBytes(), 'a.iso', 'upload-once')->assertCreated()->json('data.id');
    expect(ciUpload($this, $vps, ciIsoBytes(), 'a.iso', 'upload-once')->json('data.id'))->toBe($first)
        ->and(CustomIso::query()->count())->toBe(1)->and(Storage::disk('custom_isos')->files('incoming'))->toBe([]);

    $one = ciAction($this, $vps, 'iso.attach', ['iso_id' => $first], 'attach-once')->assertStatus(202)->json('operation_id');
    $two = ciAction($this, $vps, 'iso.attach', ['iso_id' => $first], 'attach-once')->assertStatus(202)->json('operation_id');
    expect($two)->toBe($one);
    driveOperations();
    expect(Operation::query()->where('service_id', $vps->id)->where('kind', 'service.iso')->count())->toBe(1)
        ->and($pve['uploads'])->toHaveCount(1);
    // attaching it again later finds it on the node: no second copy
    expect(ciRun(ciAction($this, $vps, 'iso.attach', ['iso_id' => $first]))->state)->toBe(Operation::SUCCEEDED);
    expect($pve['uploads'])->toHaveCount(1)->and($pve['unknown'])->toBe([]);
});

it('keeps names and paths the platform\'s own: no traversal from a file name, an id or a volume', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $this->actingAs($owner, 'sanctum');

    $iso = CustomIso::query()->findOrFail(ciUpload($this, $vps, ciIsoBytes(), '../../../etc/cron.d/evil .ISO')->assertCreated()->json('data.id'));
    expect($iso->name)->toBe('evil.iso')->and($iso->path)->toBe("{$org->id}/{$iso->id}.iso")
        ->and(Storage::disk('custom_isos')->allFiles())->toBe([$iso->path]);
    expect(CustomIsoPolicy::displayName('..\\..\\Windows\\win.ini'))->toBe('win.ini.iso')
        ->and(CustomIsoPolicy::displayName("x\0y<script>.iso"))->toBe('x-y-script.iso')
        ->and(CustomIsoPolicy::displayName('....'))->toBe('image.iso');

    // not an ISO 9660 image: refused before the scan
    ciUpload($this, $vps, str_repeat('MZ', 40000), 'setup.iso')->assertStatus(422)->assertJsonPath('error', 'iso_not_iso9660');
    // an id that is a path is no image of anybody
    foreach (['../../etc/passwd', 'iso_../../x', $iso->id.'/../x'] as $bad) {
        ciAction($this, $vps, 'iso.attach', ['iso_id' => $bad])->assertNotFound();
    }
    // the adapter writes and deletes only under the platform's own names on the custom storage
    $adapter = app(ProviderRegistry::class)->forInstance(ProviderInstance::query()->where('key', 'proxmox-cz1')->firstOrFail());
    expect(fn () => $adapter->customIsoVolume('../../etc/passwd'))->toThrow(ProviderException::class)
        ->and(fn () => $adapter->deleteCustomIso('prg1-n2', 'local:iso/systemrescue-11.iso'))->toThrow(ProviderException::class)
        ->and(fn () => $adapter->deleteCustomIso('prg1-n2', 'isostore:iso/../../vm-1050-disk-0'))->toThrow(ProviderException::class);
    expect($pve['writes'])->toBe([])->and($pve['unknown'])->toBe([]);
});

it('shares the CD drive with the rescue mode one at a time, and never shows a customer image as a rescue image', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $this->actingAs($owner, 'sanctum');
    $iso = ciUpload($this, $vps, ciIsoBytes())->assertCreated()->json('data.id');
    expect(ciRun(ciAction($this, $vps, 'iso.attach', ['iso_id' => $iso]))->state)->toBe(Operation::SUCCEEDED);

    // a customer image on the operator's rescue storage would be bootable by every customer: the rescue list leaves it out
    $pve['isos'][] = "local:iso/onhost-ciso-{$iso}.iso";
    expect(array_column(app(RescueMode::class)->images($vps), 'name'))->toBe(['debian-13-netinst.iso', 'systemrescue-11.iso']);

    app(RescueMode::class)->start($vps->fresh(), $this->contextFor($owner, $org, 'webauthn'), null, 2);
    ciAction($this, $vps, 'iso.detach')->assertStatus(409)->assertJsonPath('error', 'iso_rescue_active');
    ciAction($this, $vps, 'iso.attach', ['iso_id' => $iso])->assertStatus(409)->assertJsonPath('error', 'iso_rescue_active');
    // the rescue session found the image and puts it back at its end
    app(RescueMode::class)->stop($vps->fresh(), $this->contextFor($owner, $org));
    expect($pve['guests'][1050]['config']['ide2'])->toBe("isostore:iso/onhost-ciso-{$iso}.iso,media=cdrom");
    expect(ciRun(ciAction($this, $vps, 'iso.detach'))->state)->toBe(Operation::SUCCEEDED);
    expect($pve['guests'][1050]['config']['boot'])->toBe('order=scsi0;net0')->and($pve['unknown'])->toBe([]);
});

it('leaves the way out open after a plan change took the feature away: detach and delete, never a new attach', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $this->actingAs($owner, 'sanctum');
    $iso = ciUpload($this, $vps, ciIsoBytes())->assertCreated()->json('data.id');
    expect(ciRun(ciAction($this, $vps, 'iso.attach', ['iso_id' => $iso]))->state)->toBe(Operation::SUCCEEDED);

    // what a plan change to a version without the feature writes: the plan's entitlements, nothing else
    $vps->forceFill(['entitlements' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160]])->save();
    app(ServiceFeatures::class)->forget($vps);
    $features = $this->getJson("/v1/services/{$vps->id}/features")->json('data.features');
    expect($features['custom_iso'])->toBe(['enabled' => false, 'reason' => ServiceFeatures::REASON_PLAN])->and($features['custom_iso_exit']['enabled'])->toBeTrue();
    ciUpload($this, $vps, ciIsoBytes('q'))->assertForbidden()->assertJsonPath('error', 'custom_iso_not_in_plan');
    ciAction($this, $vps, 'iso.attach', ['iso_id' => $iso])->assertForbidden();

    expect(ciRun(ciAction($this, $vps, 'iso.detach'))->state)->toBe(Operation::SUCCEEDED);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    expect(ciRun(ciAction($this, $vps, 'iso.delete', ['iso_id' => $iso]))->state)->toBe(Operation::SUCCEEDED);
    expect($pve['custom_isos'])->toBe([])->and($pve['guests'][1050]['config']['boot'])->toBe('order=scsi0;net0')
        ->and($this->getJson("/v1/services/{$vps->id}/features")->json('data.features'))->not->toHaveKey('custom_iso_exit');
});

it('says when the plan has it and the server is not set up for it, instead of pretending the plan lacks it', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $vps = ciVps($org, $pve);
    $instance = ProviderInstance::query()->where('key', 'proxmox-cz1')->firstOrFail();
    $instance->forceFill(['options' => array_diff_key((array) $instance->options, ['custom_iso_storage' => true])])->save();
    app(ServiceFeatures::class)->forget($vps);

    expect(app(ServiceFeatures::class)->features($vps->fresh())['custom_iso'])->toBe(['enabled' => false, 'reason' => ServiceFeatures::REASON_NODE]);
});

/** A customer answer never names the hypervisor, its node or its storage. */
function ciNoVendorWords(mixed $answer): void
{
    $text = strtolower((string) json_encode($answer));
    foreach (['proxmox', 'pve.mgmt', 'prg1-n2', 'isostore', 'onhost-ciso', 'incoming/'] as $vendor) {
        expect(str_contains($text, $vendor))->toBeFalse("a customer answer names {$vendor}");
    }
}

it('keeps an image to the project of the server it came through: another project of the organization neither sees nor deletes it', function () {
    $pve = [];
    ciCluster($pve);
    [$owner, $org] = $this->customerWithOrganization();
    $shop = Project::query()->create(['organization_id' => $org->id, 'name' => 'E-shop', 'slug' => 'ci-shop', 'tags' => []]);
    $blog = Project::query()->create(['organization_id' => $org->id, 'name' => 'Blog', 'slug' => 'ci-blog', 'tags' => []]);
    $ours = ciVps($org, $pve);
    $ours->forceFill(['project_id' => $shop->id])->save();
    $theirs = ciVps($org, $pve, ['custom_iso' => true, 'custom_iso_max_mb' => 2], 1052);
    $theirs->forceFill(['project_id' => $blog->id])->save();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $iso = ciUpload($this, $ours->fresh(), ciIsoBytes())->assertCreated()->json('data.id');
    expect(CustomIso::query()->findOrFail($iso)->project_id)->toBe($shop->id);
    expect($this->getJson("/v1/services/{$theirs->id}/isos")->assertOk()->json('data.images'))->toBe([])
        ->and($this->getJson("/v1/services/{$theirs->id}/isos")->json('data.quota.images'))->toBe(1); // the quota is the organization's
    ciAction($this, $theirs, 'iso.attach', ['iso_id' => $iso])->assertNotFound();
    ciAction($this, $theirs, 'iso.delete', ['iso_id' => $iso])->assertNotFound();
    // the same bytes through the other project are an image of that project, not a door to the first one
    $copy = ciUpload($this, $theirs->fresh(), ciIsoBytes())->assertCreated()->json('data.id');
    expect($copy)->not->toBe($iso)->and(CustomIso::query()->findOrFail($iso)->state)->toBe(CustomIso::READY)->and($pve['unknown'])->toBe([]);
});
