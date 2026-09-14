<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Incidents\Models\OnCallShift;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\GameTemplates;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;
use Symfony\Component\Yaml\Yaml;

/*
 * Audit §5u: the development stack carries clamd and the trace backend (collector, Tempo, Grafana) and the doctor says
 * when traces are exported but not linked; the order asks for template inputs in a form with rule hints; a staff member
 * subscribes to the rota with a personal token URL; operator-held variables older than the rotation period are
 * reported once a month.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

it('ships clamd and the trace backend in the compose stack and checks trace links in the doctor', function () {
    $compose = Yaml::parseFile(base_path('infra/docker-compose.yml'));
    expect($compose['services'])->toHaveKeys(['clamav', 'otel-collector', 'tempo', 'grafana'])
        ->and($compose['services']['app']['environment'])->toMatchArray(['ONHOST_CLAMAV_HOST' => 'clamav', 'OTEL_EXPORTER_OTLP_ENDPOINT' => 'http://otel-collector:4318'])
        ->and($compose['services']['app']['environment']['ONHOST_TRACE_URL'])->toContain('{trace_id}')
        ->and($compose['services']['worker']['environment']['ONHOST_CLAMAV_HOST'])->toBe('clamav');
    expect(Yaml::parseFile(base_path('infra/monitoring/otel-collector.yaml'))['service']['pipelines']['traces']['exporters'])->toBe(['otlp/tempo'])
        ->and(Yaml::parseFile(base_path('infra/monitoring/grafana/datasources.yaml'))['datasources'][0]['uid'])->toBe('tempo');

    config()->set('onhost.observability.otlp_endpoint', 'http://otel-collector:4318');
    $this->artisan('onhost:doctor')->expectsOutputToContain('ONHOST_TRACE_URL is empty');
});

it('describes template inputs as form fields with hints and a help link', function () {
    expect(GameTemplates::form('STEAM_GSLT', 'required|string|alpha_num|size:32'))->toBe([
        'env' => 'STEAM_GSLT', 'rules' => 'required|string|alpha_num|size:32', 'label' => 'Steam Game Server Login Token',
        'hint' => 'Token vytvoříte na Steamu (App ID hry, pro CS2 730). Přesně 32 znaků, jen písmena a číslice.', 'help_url' => 'https://steamcommunity.com/dev/managegameservers', 'min' => 32, 'max' => 32, 'pattern' => '[A-Za-z0-9]+',
    ]);
    expect(GameTemplates::form('SERVER_NAME', 'required|alpha_dash|between:3,20'))->toMatchArray(['label' => 'Server Name', 'min' => 3, 'max' => 20, 'pattern' => '[A-Za-z0-9_-]+', 'help_url' => null]);
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-order.api.js'));
    expect($js)->toContain('function inputsForm(')->toContain('i.pattern = inp.pattern')->toContain('__inputs: values')->not->toContain("window.prompt(_('Šablona ");
});

it('subscribes a staff member to the rota with a personal token and revokes the old link', function () {
    $jana = $this->staff('sre', ['name' => 'Jana Pohotová', 'email' => 'jana@onhost.test']);
    OnCallShift::query()->create(['user_id' => $jana->id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(9)]);
    $this->actingAs($jana, 'sanctum');
    $first = $this->withHeader('Idempotency-Key', 'feed-1')->postJson('/v1/staff/oncall/feed-token')->assertStatus(201)->json('data.url');
    $second = $this->withHeader('Idempotency-Key', 'feed-2')->postJson('/v1/staff/oncall/feed-token')->assertStatus(201)->json('data.url');
    expect($first)->toMatch('#/v1/oncall/feed/[a-f0-9]{48}\.ics$#')->and($second)->not->toBe($first);
    expect(DB::table('oncall_feed_tokens')->count())->toBe(1)->and(DB::table('oncall_feed_tokens')->value('token_hash'))->not->toBe(basename($second, '.ics'));

    auth()->forgetGuards();
    $path = (string) parse_url($second, PHP_URL_PATH);
    $feed = $this->get($path)->assertOk();
    expect($feed->getContent())->toContain('SUMMARY:On-call: Jana Pohotová')->and((string) $feed->headers->get('Content-Type'))->toContain('text/calendar');
    $this->get((string) parse_url($first, PHP_URL_PATH))->assertNotFound();
    $jana->forceFill(['is_staff' => false])->save();
    $this->get($path)->assertNotFound();
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-admin.api.js')))->toContain('/staff/oncall/feed-token');
});

it('reminds operations once a month to rotate old operator variables', function () {
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail()->forceFill(['options' => ['eggs' => ['dayz' => ['nest' => 5, 'egg' => 18, 'required' => [['env' => 'STEAM_USER', 'rules' => 'required', 'editable' => false]]]]]])->save();
    $templates = app(GameTemplates::class);
    expect($templates->operatorRotation())->toBe(['stored_at' => null, 'stale' => false, 'days' => null]);
    $templates->setOperatorVariable('STEAM_USER', 'onhost-steam', CommandContext::system('test'));
    expect($templates->operatorRotation())->toMatchArray(['stale' => false, 'days' => 0]);

    DB::table('secrets')->where('name', 'game/operator-variables')->update(['rotated_at' => now()->subDays(200)]);
    $fresh = app()->make(GameTemplates::class);
    expect($fresh->operatorRotation())->toMatchArray(['stale' => true, 'days' => 200])->and($fresh->operatorStatus()['rotation']['stale'])->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'game.operator_variables.stale')->count())->toBe(1)
        ->and(Notification::query()->where('event', 'game.operator_variables.stale')->first()->body)->toContain('STEAM_USER')->not->toContain('onhost-steam');
});
