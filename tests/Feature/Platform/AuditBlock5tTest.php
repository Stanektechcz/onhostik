<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SurfaceDataController;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Incidents\Models\OnCallShift;
use Onhost\Domain\Incidents\OnCallRota;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\GameTemplates;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Files\VirusScanner;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Audit §5t: the operator stores a template's read-only variables from the console (fresh step-up, never shown or
 * audited); the wizard knows each plan's RAM; a running server whose customer input fails its rule is flagged in
 * Startup and its customer is told; the on-call rota travels as iCalendar and reminds the next person; the partner
 * portal sees the antivirus verdict; the doctor reads clamd's signature date.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('stores operator variables from the console behind a step-up without ever echoing the value', function () {
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail()->forceFill(['options' => ['eggs' => ['dayz' => ['nest' => 5, 'egg' => 18, 'required' => [['env' => 'STEAM_USER', 'rules' => 'required|string', 'editable' => false], ['env' => 'STEAM_PASS', 'rules' => 'required|string', 'editable' => false]]]]]])->save();
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');

    expect($this->getJson('/v1/staff/game/operator-variables')->assertOk()->json('data'))->toBe(['stored' => [], 'needed' => ['STEAM_USER' => ['dayz'], 'STEAM_PASS' => ['dayz']]]);
    $this->withHeader('Idempotency-Key', 'ov-0')->putJson('/v1/staff/game/operator-variables/STEAM_USER', ['value' => 'onhost-steam'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'ov-1')->putJson('/v1/staff/game/operator-variables/steam_user', ['value' => 'onhost-steam'])->assertOk()->assertJsonPath('stored', ['STEAM_USER']);
    $body = $this->withHeader('Idempotency-Key', 'ov-2')->putJson('/v1/staff/game/operator-variables/STEAM_PASS', ['value' => 'Tajne-Heslo-77'])->assertOk()->getContent();
    expect($body)->not->toContain('Tajne-Heslo-77')->and(app()->make(GameTemplates::class)->availability('dayz')['available'])->toBeTrue();
    expect(json_encode(AuditEvent::query()->get()->toArray()))->not->toContain('Tajne-Heslo-77')->not->toContain('onhost-steam');
    $this->withHeader('Idempotency-Key', 'ov-3')->putJson('/v1/staff/game/operator-variables/bad name', ['value' => 'x'])->assertStatus(422);
    $this->withHeader('Idempotency-Key', 'ov-4')->putJson('/v1/staff/game/operator-variables/STEAM_PASS', ['value' => ''])->assertOk()->assertJsonPath('stored', ['STEAM_USER']);
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js')))->toContain('/staff/game/operator-variables/');
});

it('flags a running server whose customer input fails its rule and tells the customer once a day', function () {
    $this->seed(NotificationTemplateSeeder::class);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $service->forceFill(['desired_spec' => array_merge((array) $service->desired_spec, ['egg' => 'cs2'])])->save();
    ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail()->forceFill(['options' => ['eggs' => ['cs2' => ['nest' => 5, 'egg' => 17, 'required' => [['env' => 'STEAM_GSLT', 'rules' => 'required|string|alpha_num|size:32', 'editable' => true], ['env' => 'RCON_PASSWORD', 'rules' => 'required|alpha_dash|between:1,30', 'editable' => true]]]]]])->save();
    $gslt = 'bad token';
    Http::fake(function (Request $request) use (&$gslt) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $variable = fn (string $env, string $value) => ['object' => 'egg_variable', 'attributes' => ['name' => $env, 'env_variable' => $env, 'server_value' => $value, 'default_value' => '', 'description' => '', 'is_editable' => true, 'rules' => 'required']];

        return str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/startup')
            ? Http::response(['object' => 'list', 'data' => [$variable('STEAM_GSLT', $gslt), $variable('RCON_PASSWORD', 'abc')], 'meta' => ['startup_command' => 'x', 'raw_startup_command' => 'x', 'docker_image' => 'img', 'docker_images' => []]])
            : Http::response([], 404);
    });

    $templates = app(GameTemplates::class);
    expect($templates->attention('cs2', [['key' => 'STEAM_GSLT', 'value' => str_repeat('A1', 16)], ['key' => 'RCON_PASSWORD', 'value' => '']]))->toBe([]); // passwords are not customer inputs
    $this->actingAs($user, 'sanctum');
    expect($this->getJson("/v1/services/{$service->id}/resources/startup?fresh=1")->assertOk()->json('data.attention'))->toBe(['STEAM_GSLT']);
    expect($templates->auditServices())->toBe(['checked' => 1, 'attention' => 1])->and($templates->auditServices()['attention'])->toBe(1);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'game.setup.attention')->count())->toBe(1) // once a day
        ->and(Notification::query()->where('event', 'game.setup.attention')->first()->body)->toContain('STEAM_GSLT');
    $gslt = str_repeat('C3', 16);
    expect($templates->auditServices()['attention'])->toBe(0);
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-workbench.api.js')))->toContain('su.attention');

    // the wizard knows the plan's RAM and checks the template floor before the quote
    $offer = collect((fn () => $this->panelCatalog('cs'))->call(app(SurfaceDataController::class)))->firstWhere('key', 'game');
    expect($offer['plans'][0]['ram_mb'])->toBeGreaterThan(0);
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-order.api.js')))->toContain('plan.ram_mb < chosen.min_ram_mb');
});

it('exports and imports the rota as iCalendar and reminds the next on-call an hour ahead', function () {
    $this->seed(NotificationTemplateSeeder::class);
    $jana = $this->staff('sre', ['name' => 'Jana Pohotová', 'email' => 'jana@onhost.test']);
    $this->staff('sre', ['name' => 'Petr Směnový', 'email' => 'petr@onhost.test']);
    $this->actingAs($jana, 'sanctum');
    $start = now()->addMinutes(40)->utc();
    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:shift-1@team\r\nDTSTART:".$start->format('Ymd\THis\Z')."\r\nDTEND:".$start->copy()->addHours(8)->format('Ymd\THis\Z')."\r\nSUMMARY:Jana\r\nATTENDEE;CN=Jana:mailto:jana@onhost.test\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:shift-2@team\r\nDTSTART;TZID=Europe/Prague:".now('Europe/Prague')->addDay()->format('Ymd\THis')."\r\nDTEND;TZID=Europe/Prague:".now('Europe/Prague')->addDay()->addHours(8)->format('Ymd\THis')."\r\nORGANIZER:mailto:petr@onhost.test\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:shift-3@team\r\nDTSTART:".$start->copy()->addHour()->format('Ymd\THis\Z')."\r\nDTEND:".$start->copy()->addHours(3)->format('Ymd\THis\Z')."\r\nATTENDEE:mailto:petr@onhost.test\r\nEND:VEVENT\r\n"
        ."BEGIN:VEVENT\r\nUID:shift-4@team\r\nDTSTART:20260101T000000Z\r\nDTEND:20260101T080000Z\r\nATTENDEE:mailto:nobody@example.com\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    $r = $this->withHeader('Idempotency-Key', 'ics-1')->postJson('/v1/staff/oncall/shifts/import', ['ical' => $ics])->assertOk()->json();
    expect($r['created'])->toBe(2)->and($r['updated'])->toBe(0)->and(array_column($r['skipped'], 'reason'))->toBe(['oncall_shift_overlap', 'oncall_shift_user_invalid']);
    $again = $this->withHeader('Idempotency-Key', 'ics-2')->postJson('/v1/staff/oncall/shifts/import', ['ical' => str_replace('SUMMARY:Jana', 'SUMMARY:Jana znovu', $ics)])->assertOk()->json();
    expect($again['updated'])->toBe(2)->and(OnCallShift::query()->count())->toBe(2);

    $feed = $this->get('/v1/staff/oncall/shifts.ics')->assertOk();
    expect((string) $feed->headers->get('Content-Type'))->toContain('text/calendar')->and($feed->getContent())->toContain('UID:shift-1@team')->toContain('ATTENDEE;CN=Jana Pohotová:mailto:jana@onhost.test')->toContain('SUMMARY:On-call: Petr Směnový');

    expect(app(OnCallRota::class)->remindUpcoming())->toBe(1)->and(app(OnCallRota::class)->remindUpcoming())->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'oncall.shift.starting')->where('user_id', $jana->id)->first()?->title)->toBe('Za hodinu začíná vaše on-call směna');
});

it('shows the antivirus verdict to the partner and reads the clamd signature date for the doctor', function () {
    expect(MarketplaceService::presentEvidence(['items' => ['report' => ['name' => 'r.pdf', 'size' => 10, 'mime' => 'application/pdf', 'scan' => ['result' => 'clean']], 'uptime' => '99.9']])['items'])
        ->toBe(['report' => ['file' => 'r.pdf', 'size' => 10, 'mime' => 'application/pdf', 'scan' => 'clean'], 'uptime' => '99.9']);
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-partner.api.js')))->toContain('function scanLabel');

    expect(app(VirusScanner::class)->version())->toBeNull();
    config()->set('onhost.storage.clamav.host', 'clamd.test');
    $scanner = new class extends VirusScanner
    {
        protected function command(string $command): string
        {
            return $command === 'zVERSION' ? 'ClamAV 1.0.7/27412/Mon Sep 14 03:10:00 2026' : '';
        }
    };
    expect($scanner->version())->toMatchArray(['engine' => 'ClamAV 1.0.7', 'database' => 27412])->and($scanner->version()['signatures_at'])->toStartWith('2026-09-14');
    app()->instance(VirusScanner::class, $scanner);
    $this->artisan('onhost:doctor --json')->expectsOutputToContain('virus scanner (clamd)');
    expect(file_exists(base_path('infra/ansible/roles/onhost_clamav/tasks/main.yml')))->toBeTrue();
    unset($scanner);
    app(GameTemplates::class); // the container still builds the service
    expect(CommandContext::system('t')->actorType)->toBe('system');
});
