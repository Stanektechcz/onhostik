<?php

declare(strict_types=1);

use App\Http\Presenters\Presenters;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Incidents\OnCallRota;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Notifications\DigestService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\CapacityForecast;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Risk\Turnstile;
use Onhost\Platform\Files\VirusScanner;
use Onhost\Platform\Observability\Tracer;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Audit §5r: the on-call rota names the person behind every page and the digest shows the hand-over; operations carry
 * a trace link; a binary file reaches a game server through the panel's signed upload URL after a virus scan (an
 * infected one never does); the forecast prices next month's nodes against the budget cap; the public forms ask for
 * Turnstile.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** clamd double: the EICAR marker is infected, "clamd-down" is an unreachable daemon, anything else is clean. */
function fakeClamd(): void
{
    config()->set('onhost.storage.clamav.host', 'clamd.test');
    app()->instance(VirusScanner::class, new class extends VirusScanner
    {
        protected function instream($stream): string
        {
            $body = (string) stream_get_contents($stream);
            if (str_contains($body, 'clamd-down')) {
                throw new RuntimeException('clamd unreachable');
            }

            return str_contains($body, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE') ? 'stream: Eicar-Test-Signature FOUND' : 'stream: OK';
        }
    });
}

it('keeps an on-call rota, names the assignee on a page and shows the hand-over in the digest', function () {
    $staff = $this->staff('sre', ['name' => 'Jana Pohotová', 'email' => 'jana@onhost.test']);
    $next = $this->staff('sre', ['name' => 'Petr Směnový', 'email' => 'petr@onhost.test']);
    $this->actingAs($staff, 'sanctum');
    $this->withHeader('Idempotency-Key', 'rota-1')->postJson('/v1/staff/oncall/shifts', ['user' => 'jana@onhost.test', 'starts_at' => now()->subHour()->toIso8601String(), 'ends_at' => now()->addHours(8)->toIso8601String()])->assertStatus(201)->assertJsonPath('name', 'Jana Pohotová')->assertJsonPath('active', true);
    $this->withHeader('Idempotency-Key', 'rota-2')->postJson('/v1/staff/oncall/shifts', ['user' => $next->id, 'starts_at' => now()->addHours(7)->toIso8601String(), 'ends_at' => now()->addHours(20)->toIso8601String()])->assertStatus(409)->assertJsonPath('error', 'oncall_shift_overlap');
    [$customer] = $this->customerWithOrganization(['email' => 'not-staff@firma.cz']);
    $this->withHeader('Idempotency-Key', 'rota-3')->postJson('/v1/staff/oncall/shifts', ['user' => 'not-staff@firma.cz', 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDays(2)->toIso8601String()])->assertStatus(422)->assertJsonPath('error', 'oncall_shift_user_invalid');
    $second = $this->withHeader('Idempotency-Key', 'rota-4')->postJson('/v1/staff/oncall/shifts', ['user' => 'petr@onhost.test', 'starts_at' => now()->addHours(8)->toIso8601String(), 'ends_at' => now()->addHours(20)->toIso8601String(), 'note' => 'noční'])->assertStatus(201)->json();

    $list = $this->getJson('/v1/staff/oncall/shifts')->assertOk()->json();
    expect($list['data'])->toHaveCount(2)->and($list['on_call']['name'])->toBe('Jana Pohotová')->and($list['hand_over'])->toContain('On-call: Jana Pohotová do')->toContain('předává Petr Směnový od');

    config()->set('onhost.observability.trace_url', 'https://grafana.test/explore?trace={trace_id}');
    $alert = app(OnCallService::class)->open('platform.queue.stalled', 'queue', 'default', 'Fronta stojí', null, '/sprava', 'hot');
    expect(OnCallService::present($alert)['assignee'])->toMatchArray(['name' => 'Jana Pohotová', 'email' => 'jana@onhost.test']);
    expect($this->getJson('/v1/staff/oncall/alerts')->assertOk()->json('status.on_call.name'))->toBe('Jana Pohotová');

    $digest = app(DigestService::class)->staffDaily();
    expect(collect($digest['lines'])->last())->toContain('Jana Pohotová')->toContain('Petr Směnový');
    $this->withHeader('Idempotency-Key', 'rota-5')->deleteJson('/v1/staff/oncall/shifts/'.$second['id'])->assertOk()->assertJsonPath('removed', true);
    expect(app(OnCallRota::class)->handOverLine())->toContain('další směna není naplánovaná');
    $this->actingAs($customer, 'sanctum')->getJson('/v1/staff/oncall/shifts')->assertForbidden();
    expect(OnCallAlert::query()->count())->toBe(1);
});

it('links operations to their trace', function () {
    expect(Tracer::urlFor('corr-1'))->toBeNull();
    config()->set('onhost.observability.trace_url', 'https://grafana.test/explore?trace={trace_id}&c={correlation_id}');
    expect(Tracer::urlFor('corr 1'))->toBe('https://grafana.test/explore?trace='.md5('corr 1').'&c=corr%201')->and(Tracer::urlFor(null))->toBeNull();
    $op = new Operation(['id' => 'op_x', 'kind' => 'service.action', 'state' => 'failed', 'correlation_id' => 'corr-9']);
    expect(Presenters::operation($op, true)['trace_url'])->toBe('https://grafana.test/explore?trace='.md5('corr-9').'&c=corr-9')
        ->and(Presenters::operation($op))->not->toHaveKey('trace_url');
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js')))->toContain('o.trace_url');
});

it('scans a binary upload and sends it to the game server through the signed upload URL', function () {
    Storage::fake('local');
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $uploads = [];
    Http::fake(function (Request $request) use (&$uploads) {
        $url = $request->url();
        if (str_starts_with($url, PTERO) && str_ends_with((string) parse_url($url, PHP_URL_PATH), '/files/upload')) {
            return Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://wings.games01.test:8080/upload/file?token=one-time']]);
        }
        if (str_starts_with($url, 'https://wings.games01.test:8080/upload/file')) {
            $uploads[] = ['url' => $url, 'body' => $request->body()];

            return Http::response('', 200);
        }

        return str_starts_with($url, PTERO) ? Http::response(['object' => 'server', 'attributes' => ['current_state' => 'running', 'is_suspended' => false, 'resources' => []]]) : null;
    });
    $this->actingAs($user, 'sanctum');

    // no scanner configured: the file goes through, the scan says off
    $jar = UploadedFile::fake()->createWithContent('plugin.jar', "PK\x03\x04binary-jar-bytes\x00\xff");
    $opId = $this->withHeader('Idempotency-Key', 'up-1')->post("/v1/services/{$service->id}/game-files/upload", ['file' => $jar, 'directory' => 'plugins'], ['Accept' => 'application/json'])->assertStatus(202)->json('operation_id');
    $op = driveOperation(Operation::query()->findOrFail($opId));
    expect($op->state)->toBe(Operation::SUCCEEDED)->and($uploads)->toHaveCount(1)->and($uploads[0]['url'])->toContain('token=one-time')->toContain('directory=%2Fplugins')->and($uploads[0]['body'])->toContain('binary-jar-bytes')->toContain('filename="plugin.jar"');
    expect(Storage::disk('local')->files('game-uploads/tmp'))->toBe([]); // the staging copy is gone

    // with clamd: an infected file is refused, deleted and reported; clamd down while enforced is a retryable 503
    fakeClamd();
    $eicar = UploadedFile::fake()->createWithContent('evil.jar', 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');
    $this->withHeader('Idempotency-Key', 'up-2')->post("/v1/services/{$service->id}/game-files/upload", ['file' => $eicar], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('error', 'upload_infected')->assertJsonPath('signature', 'Eicar-Test-Signature');
    $this->withHeader('Idempotency-Key', 'up-3')->post("/v1/services/{$service->id}/game-files/upload", ['file' => UploadedFile::fake()->createWithContent('x.jar', 'clamd-down')], ['Accept' => 'application/json'])->assertStatus(503)->assertJsonPath('error', 'upload_scan_unavailable');
    expect(Storage::disk('local')->files('game-uploads/tmp'))->toBe([])->and($uploads)->toHaveCount(1);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'files.infected')->first()?->title)->toContain('evil.jar');

    // a forged staging path through the plain action endpoint is refused
    $this->withHeader('Idempotency-Key', 'up-4')->postJson("/v1/services/{$service->id}/actions", ['action' => 'gfile.upload', 'params' => ['name' => 'x.jar', 'tmp_path' => '../../.env']])->assertStatus(422)->assertJsonPath('error', 'action_param_invalid');
    $scanner = app(VirusScanner::class);
    expect($scanner->allows(VirusScanner::CLEAN))->toBeTrue()->and($scanner->allows(VirusScanner::UNAVAILABLE))->toBeFalse()->and($scanner->allows(VirusScanner::INFECTED))->toBeFalse();
    config()->set('onhost.storage.clamav.enforce', false);
    expect($scanner->allows(VirusScanner::UNAVAILABLE))->toBeTrue()->and($scanner->allows(null))->toBeTrue();
    expect(app(MarketplaceService::class)->rescanEvidence())->toBe(['scanned' => 0, 'clean' => 0, 'infected' => 0, 'unavailable' => 0]);
    expect((string) file_get_contents(resource_path('views/staff-console.blade.php')))->toContain("/game-files/upload'")->toContain('new FormData');
});

it('prices the nodes the trend needs next month against the budget cap', function () {
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    Node::query()->where('provider_instance_id', $instance->id)->update(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000]]);
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['ram_used_mb' => 0]]);
    $forecast = app(CapacityForecast::class);
    $pools = ([['role' => 'game', 'region' => 'cz1', 'nodes' => 2, 'sellable_mb' => 65536, 'sold_mb' => 60000, 'used_mb' => 60000, 'headroom_mb' => 5536, 'growth_mb_per_day' => 4000, 'days_left' => 1, 'low' => true, 'basis' => 'trend']]);

    config()->set('onhost.provisioning.capacity_forecast.node_monthly_minor', ['game' => 3049]);
    config()->set('onhost.provisioning.capacity_budget.monthly_minor', 5000);
    $b = $forecast->budget(30, $pools);
    // 30 days × 4 GB/day − 5.4 GB headroom = 114.5 GB → two 64 GB nodes × 30,49 € = 60,98 € > 50 € cap
    expect($b)->toMatchArray(['currency' => 'EUR', 'budget_minor' => 5000, 'total_minor' => 6098, 'nodes' => 2, 'over' => true, 'unpriced' => 0])->and($b['pools'][0])->toMatchArray(['need_mb' => 114464, 'node_mb' => 65536, 'nodes' => 2, 'unit_minor' => 3049]);
    config()->set('onhost.provisioning.capacity_forecast.node_monthly_minor', []);
    expect($forecast->budget(30, $pools))->toMatchArray(['total_minor' => 0, 'nodes' => 2, 'unpriced' => 1, 'over' => false]);

    // the real forecast feeds the console row
    $staff = $this->staff('infrastructure_admin');
    expect($this->actingAs($staff, 'sanctum')->getJson('/v1/staff/capacity')->assertOk()->json('data.budget_forecast'))->toHaveKeys(['month', 'total_minor', 'budget_minor', 'over', 'pools']);
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js')))->toContain('c.budget_forecast');
});

it('asks guests for Turnstile on the contact, tender and partner application forms', function () {
    $lead = ['kind' => 'contact', 'name' => 'Robot', 'email' => 'bot@example.cz', 'message' => 'Ahoj', 'consent' => true];
    $this->postJson('/v1/leads', $lead)->assertStatus(201); // off without keys

    config()->set('onhost.turnstile.site_key', '1x00000000000000000000AA');
    config()->set('onhost.turnstile.secret', '1x0000000000000000000000000000000AA');
    Http::fake([Turnstile::VERIFY_URL => Http::sequence()->push(['success' => false], 200)->push(['success' => true], 200)]);
    $this->postJson('/v1/leads', $lead)->assertStatus(422)->assertJsonPath('error', 'turnstile_required')->assertJsonPath('form', 'lead')->assertJsonPath('result', 'missing');
    $this->postJson('/v1/reseller/apply', ['name' => 'Bot', 'email' => 'bot@example.cz', 'company' => 'Bot s.r.o.', 'consent' => true, 'turnstile' => 'bad'])->assertStatus(422)->assertJsonPath('form', 'partner_application')->assertJsonPath('result', 'fail');
    $this->postJson('/v1/leads', $lead + ['turnstile' => 'good'])->assertStatus(201);
    $this->postJson('/v1/tender/request', ['name' => 'Bot', 'email' => 'bot@example.cz', 'company' => 'Úřad', 'consent' => true])->assertStatus(422)->assertJsonPath('form', 'tender');

    // a signed-in user is not asked; the switch turns the guard off
    [$user] = $this->customerWithOrganization();
    $signedIn = Illuminate\Http\Request::create('/v1/leads', 'POST');
    $signedIn->setUserResolver(fn () => $user);
    app(Turnstile::class)->requireForForm($signedIn, 'lead'); // no exception, no verify call
    config()->set('onhost.turnstile.enforce_forms', false);
    app(Turnstile::class)->requireForForm(Illuminate\Http\Request::create('/v1/leads', 'POST'), 'lead');
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-session-bridge.js')))->toContain('leads|tender\\/request|reseller\\/apply');
});
